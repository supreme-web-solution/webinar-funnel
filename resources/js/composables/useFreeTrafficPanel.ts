import { router, useForm } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

export type TrafficKeyword = {
    id: number;
    name: string;
    is_active: boolean;
    email_notifications: boolean;
    platforms: string[];
    mentions_count: number;
    mention_cap_reached: boolean;
    mention_counts_by_platform: Record<string, number>;
};

export type TrafficMentionRow = {
    id: number;
    keyword_id: number;
    title: string | null;
    content: string | null;
    source_type: string;
    username: string | null;
    permalink: string | null;
    posted_at: string | null;
    url?: string | null;
    keyword: { id: number; name: string } | null;
    traffic_reply_attempt?: {
        status: string;
        skip_reason: string | null;
        last_error: string | null;
        posted_at: string | null;
        external_comment_id: string | null;
    } | null;
};

export type TrafficData = {
    keywords: TrafficKeyword[];
    suggested_keywords: string[];
    mentions: {
        data: TrafficMentionRow[];
        total: number;
        from: number | null;
        to: number | null;
        last_page: number;
        current_page: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    stats: {
        total: number;
        this_week: number;
        keywords_count: number;
        platforms: Record<string, number>;
    };
    limits: {
        max_keywords_per_funnel: number;
        max_mentions_per_keyword: number;
    };
    filters: {
        search?: string;
        platform?: string;
        keyword_id?: number | string | null;
    };
    social_accounts: Array<{
        id: number;
        platform: string;
        platform_username: string | null;
        posts_today: number;
        posts_today_reset_on: string | null;
    }>;
    max_replies_per_day_per_account: number;
};

export type FreeTrafficRoutes = {
    filter: string;
    keywords_store: string;
    keywords_update: string;
    keywords_destroy: string;
    keywords_fetch: string;
    settings_patch: string;
    draft_reply: string;
};

export type TrafficSettings = {
    traffic_ai_reply_enabled?: boolean;
    traffic_ai_link_override?: string | null;
    traffic_ai_extra_context?: string | null;
    traffic_ai_social_account_ids?: Record<string, number | null>;
};

export const PLATFORM_OPTIONS = ['reddit', 'youtube', 'twitter', 'news'] as const;

export const PLATFORM_META: Record<string, { label: string; icon: string; color: string; bg: string }> = {
    reddit: { label: 'Reddit', icon: 'simple-icons:reddit', color: '#ff6b35', bg: 'rgba(255,69,0,0.12)' },
    youtube: { label: 'YouTube', icon: 'simple-icons:youtube', color: '#ff4444', bg: 'rgba(255,0,0,0.12)' },
    twitter: { label: 'Twitter', icon: 'simple-icons:x', color: '#e2e8f0', bg: 'rgba(255,255,255,0.08)' },
    news: { label: 'News', icon: 'heroicons:newspaper', color: '#4e9af1', bg: 'rgba(26,115,232,0.12)' },
};

export function trafficPlatformMeta(key: string) {
    const normalized = String(key).toLowerCase();

    return PLATFORM_META[normalized] ?? { label: key, icon: 'heroicons:globe-alt', color: '#94a3b8', bg: 'rgba(148,163,184,0.12)' };
}

function routeFor(template: string, id: number | string): string {
    return template.replace('__ID__', String(id));
}

export function useFreeTrafficPanel(
    traffic: ComputedRef<TrafficData>,
    settings: ComputedRef<TrafficSettings | null>,
    routes: ComputedRef<FreeTrafficRoutes>,
) {
    const trafficSearch = ref(traffic.value.filters.search ?? '');
    const trafficPlatform = ref(traffic.value.filters.platform ?? '');
    const trafficKeywordId = ref<number | string>(traffic.value.filters.keyword_id ?? '');
    const trafficKeywordModalOpen = ref(false);
    const trafficAiSectionOpen = ref(false);
    const savingSettings = ref(false);
    const trafficAiLinkError = ref<string | null>(null);

    let trafficDebounce: ReturnType<typeof setTimeout>;

    const trafficKeywordForm = useForm({
        name: '',
        platforms: ['reddit', 'youtube', 'twitter', 'news'] as string[],
    });

    const settingsForm = useForm({
        traffic_ai_reply_enabled: settings.value?.traffic_ai_reply_enabled ?? false,
        traffic_ai_link_override: settings.value?.traffic_ai_link_override ?? '',
        traffic_ai_extra_context: settings.value?.traffic_ai_extra_context ?? '',
        traffic_ai_social_account_ids: {
            reddit: settings.value?.traffic_ai_social_account_ids?.reddit ?? null,
            youtube: settings.value?.traffic_ai_social_account_ids?.youtube ?? null,
            twitter: settings.value?.traffic_ai_social_account_ids?.twitter ?? null,
        } as { reddit: number | null; youtube: number | null; twitter: number | null },
    });

    const trafficMaxKeywords = computed(() => traffic.value.limits.max_keywords_per_funnel);
    const trafficMaxMentionsPerKeyword = computed(() => traffic.value.limits.max_mentions_per_keyword);
    const trafficAtKeywordLimit = computed(() => traffic.value.keywords.length >= trafficMaxKeywords.value);
    const trafficSuggestedKeywords = computed(() => traffic.value.suggested_keywords ?? []);
    const trafficRemainingKeywordSlots = computed(() => Math.max(0, trafficMaxKeywords.value - traffic.value.keywords.length));

    const trafficKeywordCapTooltip = computed(
        () =>
            `This keyword hit the ${trafficMaxMentionsPerKeyword.value.toLocaleString()} mention limit. Fetching was paused automatically. Delete some mentions, or delete this keyword and add a new one to fetch again.`,
    );

    const trafficKeywordFetchCapTooltip = computed(
        () =>
            `Fetch is off — this keyword reached the ${trafficMaxMentionsPerKeyword.value.toLocaleString()} mention limit and was paused automatically.`,
    );

    const trafficActiveKeyword = computed(() => {
        if (!trafficKeywordId.value) return null;

        return traffic.value.keywords.find((kw) => String(kw.id) === String(trafficKeywordId.value)) ?? null;
    });

    const trafficStatsScopeLabel = computed(() =>
        trafficActiveKeyword.value ? `for #${trafficActiveKeyword.value.name}` : 'all keywords',
    );

    const trafficPlatformTabs = computed(() => {
        const platformLabels: Record<string, string> = {
            reddit: 'Reddit',
            youtube: 'YouTube',
            twitter: 'X (Twitter)',
            news: 'News',
        };

        const all = { key: '', label: 'All', count: traffic.value.stats.total };
        const entries = Object.entries(traffic.value.stats.platforms ?? {}).map(([k, v]) => {
            const key = String(k).toLowerCase();

            return {
                key,
                label: platformLabels[key] ?? key.charAt(0).toUpperCase() + key.slice(1),
                count: Number(v),
            };
        });

        return [all, ...entries];
    });

    const trafficMaxRepliesPerDay = computed(() => traffic.value.max_replies_per_day_per_account ?? 20);

    const trafficReplyPlatforms = [
        { key: 'reddit' as const, label: 'Reddit', icon: 'simple-icons:reddit', color: '#ff6b35' },
        { key: 'youtube' as const, label: 'YouTube', icon: 'simple-icons:youtube', color: '#ff0000' },
        { key: 'twitter' as const, label: 'X (Twitter)', icon: 'simple-icons:x', color: '#e2e8f0' },
    ];

    function trafficKeywordIsTracked(name: string): boolean {
        const needle = name.trim().toLowerCase();

        return traffic.value.keywords.some((kw) => kw.name.trim().toLowerCase() === needle);
    }

    function trafficKeywordIsFetching(kw: { is_active: boolean; mention_cap_reached: boolean }): boolean {
        return kw.is_active && !kw.mention_cap_reached;
    }

    function trafficKeywordPlayPauseDisabled(kw: { mention_cap_reached: boolean }): boolean {
        return kw.mention_cap_reached;
    }

    function trafficKeywordFilterActive(kw: { id: number }): boolean {
        return String(trafficKeywordId.value) === String(kw.id);
    }

    function trafficAccountsForPlatform(platform: string) {
        return traffic.value.social_accounts.filter(
            (a) => a.platform === platform || (platform === 'twitter' && a.platform === 'x'),
        );
    }

    function trafficAccountForPlatform(platform: 'reddit' | 'youtube' | 'twitter') {
        return trafficAccountsForPlatform(platform)[0] ?? null;
    }

    function trafficRepliesPostedToday(platform: 'reddit' | 'youtube' | 'twitter'): number {
        const account = trafficAccountForPlatform(platform);
        if (!account) return 0;
        const resetOn = account.posts_today_reset_on;
        if (resetOn) {
            const resetDate = new Date(resetOn);
            const today = new Date();
            if (
                resetDate.getFullYear() !== today.getFullYear()
                || resetDate.getMonth() !== today.getMonth()
                || resetDate.getDate() !== today.getDate()
            ) {
                return 0;
            }
        }

        return account.posts_today ?? 0;
    }

    function autoAssignTrafficAccounts(): boolean {
        let changed = false;
        for (const p of trafficReplyPlatforms) {
            const account = trafficAccountForPlatform(p.key);
            if (account && settingsForm.traffic_ai_social_account_ids[p.key] !== account.id) {
                settingsForm.traffic_ai_social_account_ids[p.key] = account.id;
                changed = true;
            }
            if (!account && settingsForm.traffic_ai_social_account_ids[p.key] !== null) {
                settingsForm.traffic_ai_social_account_ids[p.key] = null;
                changed = true;
            }
        }

        return changed;
    }

    autoAssignTrafficAccounts();

    watch(
        () => settings.value,
        (s) => {
            if (!s) return;
            settingsForm.traffic_ai_reply_enabled = Boolean(s.traffic_ai_reply_enabled);
            settingsForm.traffic_ai_link_override = s.traffic_ai_link_override ?? '';
            settingsForm.traffic_ai_extra_context = s.traffic_ai_extra_context ?? '';
            const map = s.traffic_ai_social_account_ids;
            if (map) {
                settingsForm.traffic_ai_social_account_ids = {
                    reddit: map.reddit ?? null,
                    youtube: map.youtube ?? null,
                    twitter: map.twitter ?? null,
                };
            }
        },
        { deep: true },
    );

    const TRAFFIC_PLATFORM_PICK_ORDER = ['twitter', 'youtube', 'reddit', 'news'] as const;

    function firstTrafficPlatformWithMentions(counts: Record<string, number>): string {
        for (const key of TRAFFIC_PLATFORM_PICK_ORDER) {
            if ((counts[key] ?? 0) > 0) return key;
        }
        for (const [key, count] of Object.entries(counts)) {
            if (count > 0) return key.toLowerCase();
        }

        return '';
    }

    function pickTrafficPlatformForKeyword(counts: Record<string, number>): string {
        const current = String(trafficPlatform.value).toLowerCase();
        if (current && (counts[current] ?? 0) > 0) return current;

        return firstTrafficPlatformWithMentions(counts);
    }

    function syncTrafficPlatformToKeywordStats(): void {
        if (!trafficKeywordId.value) return;
        const counts = traffic.value.stats.platforms ?? {};
        const next = pickTrafficPlatformForKeyword(counts);
        const current = String(trafficPlatform.value).toLowerCase();
        if (next !== current) trafficPlatform.value = next;
    }

    watch([trafficSearch, trafficPlatform, trafficKeywordId], ([s, p, k]) => {
        clearTimeout(trafficDebounce);
        trafficDebounce = setTimeout(() => {
            router.get(routes.value.filter, {
                traffic_search: s || undefined,
                traffic_platform: p || undefined,
                traffic_keyword_id: k || undefined,
                page: 1,
            }, {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            });
        }, 350);
    });

    watch(
        () => [traffic.value.filters.keyword_id, traffic.value.stats.platforms] as const,
        () => syncTrafficPlatformToKeywordStats(),
        { immediate: true },
    );

    function toggleTrafficPlatform(p: string): void {
        const idx = trafficKeywordForm.platforms.indexOf(p);
        if (idx === -1) trafficKeywordForm.platforms.push(p);
        else trafficKeywordForm.platforms.splice(idx, 1);
    }

    function resetTrafficKeywordForm(): void {
        trafficKeywordForm.reset();
        trafficKeywordForm.platforms = ['reddit', 'youtube', 'twitter', 'news'];
        trafficKeywordForm.clearErrors();
    }

    function openTrafficKeywordModal(): void {
        if (trafficAtKeywordLimit.value) return;
        resetTrafficKeywordForm();
        trafficKeywordModalOpen.value = true;
    }

    function closeTrafficKeywordModal(): void {
        trafficKeywordModalOpen.value = false;
        resetTrafficKeywordForm();
    }

    function submitTrafficKeyword(): void {
        trafficKeywordForm.post(routes.value.keywords_store, {
            preserveScroll: true,
            onSuccess: () => {
                closeTrafficKeywordModal();
            },
        });
    }

    function selectTrafficSuggestedKeyword(keyword: string): void {
        if (trafficAtKeywordLimit.value || trafficKeywordIsTracked(keyword)) return;
        trafficKeywordForm.name = keyword;
    }

    function addTrafficSuggestedKeyword(keyword: string): void {
        if (trafficAtKeywordLimit.value || trafficKeywordIsTracked(keyword) || trafficKeywordForm.processing) return;
        trafficKeywordForm.name = keyword;
        submitTrafficKeyword();
    }

    function toggleTrafficKeywordFilter(kw: { id: number; mention_counts_by_platform?: Record<string, number> }): void {
        if (trafficKeywordFilterActive(kw)) {
            trafficKeywordId.value = '';

            return;
        }
        trafficKeywordId.value = kw.id;
        trafficPlatform.value = pickTrafficPlatformForKeyword(kw.mention_counts_by_platform ?? {});
    }

    function toggleTrafficKeywordActive(keyword: { id: number; is_active: boolean; mention_cap_reached: boolean }): void {
        if (trafficKeywordPlayPauseDisabled(keyword)) return;
        router.patch(routeFor(routes.value.keywords_update, keyword.id), {
            is_active: !keyword.is_active,
        }, { preserveScroll: true });
    }

    function canFetchTrafficKeyword(keyword: { mention_cap_reached: boolean }): boolean {
        return !keyword.mention_cap_reached;
    }

    function toggleTrafficKeywordNotifications(keyword: { id: number; email_notifications: boolean }): void {
        router.patch(routeFor(routes.value.keywords_update, keyword.id), {
            email_notifications: !keyword.email_notifications,
        }, { preserveScroll: true });
    }

    function fetchTrafficKeywordNow(keyword: { id: number }): void {
        router.post(routeFor(routes.value.keywords_fetch, keyword.id), {}, { preserveScroll: true });
    }

    function deleteTrafficKeyword(keyword: { id: number; name: string }): void {
        if (!window.confirm(`Delete keyword "${keyword.name}" and its mentions?`)) return;
        router.delete(routeFor(routes.value.keywords_destroy, keyword.id), { preserveScroll: true });
    }

    function trafficAiSettingsPayload(): Record<string, unknown> {
        const linkOverride = settingsForm.traffic_ai_link_override.trim();

        return {
            traffic_ai_reply_enabled: settingsForm.traffic_ai_reply_enabled,
            traffic_ai_link_override: linkOverride === '' ? null : linkOverride,
            traffic_ai_extra_context: settingsForm.traffic_ai_extra_context,
            traffic_ai_social_account_ids: { ...settingsForm.traffic_ai_social_account_ids },
        };
    }

    function saveTrafficAiSettings(): void {
        autoAssignTrafficAccounts();
        trafficAiLinkError.value = null;
        savingSettings.value = true;
        router.patch(routes.value.settings_patch, trafficAiSettingsPayload(), {
            preserveScroll: true,
            onSuccess: () => toast.success('Auto-reply settings saved.'),
            onError: (errors) => {
                const linkErr = errors.traffic_ai_link_override;
                const linkMsg = Array.isArray(linkErr) ? linkErr[0] : linkErr;
                trafficAiLinkError.value = typeof linkMsg === 'string' ? linkMsg : null;
                const first = linkMsg ?? errors.traffic_ai_reply_enabled ?? Object.values(errors)[0];
                toast.error(typeof first === 'string' ? first : 'Could not save auto-reply settings.');
            },
            onFinish: () => {
                savingSettings.value = false;
            },
        });
    }

    function saveTrafficAiReplyEnabled(enabled: boolean): void {
        settingsForm.traffic_ai_reply_enabled = enabled;
        saveTrafficAiSettings();
    }

    function goToTrafficMentionsPage(url: string | null): void {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }

    function fmtTrafficDate(dt: string | null): string {
        if (!dt) return '';
        const d = new Date(dt);
        const diff = (Date.now() - d.getTime()) / 1000;
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;

        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function trunc(text: string | null, len = 200): string {
        if (!text) return '';

        return text.length > len ? `${text.slice(0, len)}…` : text;
    }

    type TrafficMentionReplyAttempt = NonNullable<TrafficMentionRow['traffic_reply_attempt']>;

    function trafficReplyPostBlockedMessage(attempt: TrafficMentionReplyAttempt): boolean {
        const err = (attempt.last_error ?? '').toLowerCase();
        if (err === '') return false;

        return err.includes('too old') || err.includes('rate limit') || err.includes('archived') || err.includes('locked') || err.includes('cannot comment');
    }

    function trafficReplyStatusMeta(attempt: TrafficMentionReplyAttempt | null | undefined): { label: string; class: string } | null {
        if (!attempt) return null;
        if (attempt.status === 'failed') {
            if (trafficReplyPostBlockedMessage(attempt)) {
                return { label: 'Not posted', class: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-400' };
            }

            return { label: 'Could not post', class: 'border-slate-500/40 bg-slate-500/10 text-muted-foreground' };
        }

        const map: Record<string, { label: string; class: string }> = {
            posted: { label: 'Auto-replied', class: 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400' },
            queued_post: { label: 'Posting…', class: 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400' },
            generating: { label: 'Generating…', class: 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400' },
            pending_evaluation: { label: 'Queued', class: 'border-slate-500/40 bg-slate-500/10 text-muted-foreground' },
            skipped_gate: { label: 'Filtered out', class: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-400' },
            skipped_no_account: { label: 'No account', class: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-400' },
            skipped_daily_cap: { label: 'Daily cap', class: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-400' },
            skipped_unsupported: { label: 'Not supported', class: 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-400' },
        };

        return map[attempt.status] ?? { label: attempt.status.replaceAll('_', ' '), class: 'border-border bg-muted/30 text-muted-foreground' };
    }

    function trafficReplyStatusHint(attempt: TrafficMentionReplyAttempt | null | undefined): string | null {
        if (!attempt) return null;
        if (attempt.status === 'posted' && attempt.posted_at) return `Posted ${fmtTrafficDate(attempt.posted_at)}`;
        if (attempt.last_error) return attempt.last_error;
        if (attempt.skip_reason) return attempt.skip_reason.replaceAll('_', ' ');

        return null;
    }

    function trafficMentionAutoReplied(attempt: TrafficMentionReplyAttempt | null | undefined): boolean {
        return attempt?.status === 'posted';
    }

    function trafficMentionCanDraftReply(mention: TrafficMentionRow): boolean {
        return String(mention.source_type ?? '').toLowerCase() !== 'news';
    }

    const trafficReplyDraftModalOpen = ref(false);
    const trafficReplyDraftLoading = ref(false);
    const trafficReplyDraftText = ref('');
    const trafficReplyDraftWarning = ref<string | null>(null);
    const trafficReplyDraftSource = ref<'openai' | 'fallback' | null>(null);
    const trafficReplyDraftMention = ref<TrafficMentionRow | null>(null);

    async function openTrafficReplyDraftModal(mention: TrafficMentionRow): Promise<void> {
        trafficReplyDraftMention.value = mention;
        trafficReplyDraftText.value = '';
        trafficReplyDraftWarning.value = null;
        trafficReplyDraftSource.value = null;
        trafficReplyDraftModalOpen.value = true;
        trafficReplyDraftLoading.value = true;

        try {
            const response = await fetch(routeFor(routes.value.draft_reply, mention.id), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                trafficReplyDraftModalOpen.value = false;
                toast.error(typeof payload.message === 'string' ? payload.message : 'Could not generate reply.');

                return;
            }

            trafficReplyDraftText.value = typeof payload.reply === 'string' ? payload.reply : '';
            trafficReplyDraftSource.value = payload.source === 'openai' || payload.source === 'fallback' ? payload.source : null;
            trafficReplyDraftWarning.value = typeof payload.warning === 'string' ? payload.warning : null;

            if (trafficReplyDraftWarning.value) toast.warning(trafficReplyDraftWarning.value);
        } catch {
            trafficReplyDraftModalOpen.value = false;
            toast.error('Could not generate reply.');
        } finally {
            trafficReplyDraftLoading.value = false;
        }
    }

    async function copyTrafficReplyDraft(): Promise<void> {
        if (!trafficReplyDraftText.value) return;
        try {
            await navigator.clipboard.writeText(trafficReplyDraftText.value);
            toast.success('Reply copied to clipboard');
        } catch {
            toast.error('Could not copy to clipboard');
        }
    }

    return {
        PLATFORM_OPTIONS,
        trafficSearch,
        trafficPlatform,
        trafficKeywordId,
        trafficKeywordModalOpen,
        trafficAiSectionOpen,
        savingSettings,
        trafficAiLinkError,
        trafficKeywordForm,
        settingsForm,
        trafficMaxKeywords,
        trafficMaxMentionsPerKeyword,
        trafficAtKeywordLimit,
        trafficSuggestedKeywords,
        trafficRemainingKeywordSlots,
        trafficKeywordCapTooltip,
        trafficKeywordFetchCapTooltip,
        trafficActiveKeyword,
        trafficStatsScopeLabel,
        trafficPlatformTabs,
        trafficMaxRepliesPerDay,
        trafficReplyPlatforms,
        trafficPlatformMeta,
        trafficKeywordIsFetching,
        trafficKeywordPlayPauseDisabled,
        trafficKeywordFilterActive,
        trafficAccountForPlatform,
        trafficRepliesPostedToday,
        openTrafficKeywordModal,
        closeTrafficKeywordModal,
        submitTrafficKeyword,
        selectTrafficSuggestedKeyword,
        addTrafficSuggestedKeyword,
        toggleTrafficPlatform,
        toggleTrafficKeywordFilter,
        toggleTrafficKeywordActive,
        canFetchTrafficKeyword,
        toggleTrafficKeywordNotifications,
        fetchTrafficKeywordNow,
        deleteTrafficKeyword,
        saveTrafficAiSettings,
        saveTrafficAiReplyEnabled,
        goToTrafficMentionsPage,
        fmtTrafficDate,
        trunc,
        trafficReplyStatusMeta,
        trafficReplyStatusHint,
        trafficMentionAutoReplied,
        trafficMentionCanDraftReply,
        trafficReplyDraftModalOpen,
        trafficReplyDraftLoading,
        trafficReplyDraftText,
        trafficReplyDraftWarning,
        trafficReplyDraftSource,
        trafficReplyDraftMention,
        openTrafficReplyDraftModal,
        copyTrafficReplyDraft,
    };
}
