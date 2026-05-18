<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import grapesjs from 'grapesjs';
import 'grapesjs/dist/css/grapes.min.css';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const props = defineProps<{
    funnel: {
        id: number;
        name: string;
        slug: string;
        status: string;
        settings: {
            webinar_title?: string | null;
            webinar_description?: string | null;
            video_url?: string | null;
            webinar_duration_seconds?: number | null;
            webinar_cta_label?: string | null;
            webinar_cta_url?: string | null;
            affiliate_request_link?: string | null;
            jv_page?: string | null;
            offers?: Array<{
                title: string;
                description?: string | null;
                cta_label: string;
                cta_url: string;
                placement: 'chat' | 'pinned' | 'popup';
                timing_seconds: number;
                enabled?: boolean;
            }>;
            exit_popup_enabled?: boolean;
            exit_popup_show_close?: boolean;
            exit_popup_title?: string | null;
            exit_popup_description?: string | null;
            exit_popup_cta_label?: string | null;
            exit_popup_cta_url?: string | null;
            redirect_enabled?: boolean;
            redirect_url?: string | null;
            webinar_ai_enabled?: boolean;
            webinar_ai_auto_reply_enabled?: boolean;
            webinar_ai_assistant_name?: string | null;
            traffic_ai_reply_enabled?: boolean;
            traffic_ai_link_override?: string | null;
            traffic_ai_extra_context?: string | null;
            traffic_ai_social_account_ids?: Record<string, number | null>;
            chat_mode: string;
            allow_replay: boolean;
            chat_seed_messages?: Array<{ author: string; message: string }>;
        } | null;
        pages: Array<{
            page_type: 'optin' | 'webinar';
            schema: Record<string, unknown>;
        }>;
        integrations: Array<{ integration_account: { id: number; name: string; provider: string } }>;
    };
    integrationAccounts: Array<{ id: number; name: string; provider: string }>;
    conversationSummaries: Array<{
        conversation_key: string;
        attendee_name: string;
        attendee_email?: string | null;
        latest_message?: string | null;
        message_count: number;
    }>;
    aiSourceUrls: {
        index: string | null;
        url: string | null;
        transcript: string | null;
        file: string | null;
        bulk_delete: string | null;
    };
    aiSources: Array<{
        id: number;
        type: string;
        title: string | null;
        source_url: string | null;
        status: string;
        error_message: string | null;
        processed_at: string | null;
        chunk_count: number;
        chunks_url: string;
        delete_url: string;
    }>;
    publicLinks: {
        optin: string;
        webinar: string;
    };
    traffic: {
        keywords: Array<{
            id: number;
            name: string;
            is_active: boolean;
            email_notifications: boolean;
            platforms: string[];
            mentions_count: number;
        }>;
        mentions: {
            data: Array<{
                id: number;
                keyword_id: number;
                title: string | null;
                content: string | null;
                source_type: string;
                username: string | null;
                like_count: number;
                retweet_count: number;
                comments_count: number;
                views: number | null;
                votes: number | null;
                permalink: string | null;
                posted_at: string | null;
                keyword: { id: number; name: string } | null;
            }>;
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
        filters: {
            search?: string;
            platform?: string;
            keyword_id?: number | string;
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
    videoStats: {
        accessed: number;
        watched_60s: number;
        watched_50_percent: number;
        watched_to_end: number;
        avg_watch_seconds: number;
    };
}>();

const optinPage = props.funnel.pages.find((p) => p.page_type === 'optin');

/* ─── Editor refs ──────────────────────────────────────────────────────── */
const editorContainer = ref<HTMLElement | null>(null);
const blocksContainer = ref<HTMLElement | null>(null);
const stylesContainer = ref<HTMLElement | null>(null);
const gjsEditor        = ref<any>(null);
const showStyles       = ref(true);
const activeDevice     = ref<'desktop' | 'mobile'>('desktop');
const isFullscreen     = ref(false);

const copiedLink    = ref<'optin' | 'webinar' | null>(null);
const shareModalOpen = ref(false);
const shareActiveLink = ref<'webinar' | 'optin'>('webinar');
const shareLinkCopied = ref(false);

const shareCurrentUrl = computed(() =>
    shareActiveLink.value === 'webinar' ? props.publicLinks.webinar : props.publicLinks.optin,
);
const shareCurrentText = computed(() =>
    shareActiveLink.value === 'webinar'
        ? `Join me for this FREE webinar: ${props.funnel.name}`
        : `Register for this FREE webinar: ${props.funnel.name}`,
);

const openSharePlatform = (platform: string): void => {
    const url  = encodeURIComponent(shareCurrentUrl.value);
    const text = encodeURIComponent(shareCurrentText.value);
    const links: Record<string, string> = {
        facebook:  `https://www.facebook.com/sharer/sharer.php?u=${url}`,
        x:         `https://twitter.com/intent/tweet?url=${url}&text=${text}`,
        whatsapp:  `https://wa.me/?text=${encodeURIComponent(shareCurrentText.value + ' ' + shareCurrentUrl.value)}`,
        linkedin:  `https://www.linkedin.com/sharing/share-offsite/?url=${url}`,
        telegram:  `https://t.me/share/url?url=${url}&text=${text}`,
        email:     `mailto:?subject=${encodeURIComponent('You are invited: ' + props.funnel.name)}&body=${encodeURIComponent(shareCurrentText.value + '\n\n' + shareCurrentUrl.value)}`,
    };
    if (links[platform]) {
        window.open(links[platform], '_blank', 'noopener,noreferrer,width=620,height=500');
    }
};

const copyShareLink = async (): Promise<void> => {
    try {
        await navigator.clipboard.writeText(shareCurrentUrl.value);
        shareLinkCopied.value = true;
        window.setTimeout(() => { shareLinkCopied.value = false; }, 2000);
    } catch { /* ignore */ }
};
const savingPage    = ref(false);
const savingSettings = ref(false);
const publishing    = ref(false);
const activeTab     = ref('optin');

/* Refresh canvas when returning to optin tab (hidden iframe can collapse to 0×0) */
const refreshEditorCanvas = (): void => {
    nextTick(() => {
        if (!gjsEditor.value) {
            return;
        }

        // Run multiple refresh passes because container geometry settles across frames.
        gjsEditor.value.refresh();
        requestAnimationFrame(() => {
            gjsEditor.value?.refresh();
        });
        setTimeout(() => {
            gjsEditor.value?.refresh();
        }, 80);
    });
};

watch(activeTab, (tab) => {
    if (tab === 'optin') {
        refreshEditorCanvas();
    }
    if (tab === 'ai-assistant' && aiSourcesList.value.length === 0 && props.aiSourceUrls.index) {
        void loadAiSources();
    }
});

/* ─── Toolbar actions ──────────────────────────────────────────────────── */
const editorUndo = () => gjsEditor.value?.runCommand('core:undo');
const editorRedo = () => gjsEditor.value?.runCommand('core:redo');

function setDevice(device: 'desktop' | 'mobile'): void {
    activeDevice.value = device;
    gjsEditor.value?.setDevice(device === 'desktop' ? 'Desktop' : 'Mobile');
    refreshEditorCanvas();
}

function toggleFullscreen(): void {
    isFullscreen.value = !isFullscreen.value;
    // Recalculate canvas dimensions after the DOM updates
    refreshEditorCanvas();
}

const onGlobalKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && isFullscreen.value) {
        isFullscreen.value = false;
        refreshEditorCanvas();
    }
};

const pageForm = useForm<{ page_type: 'optin' | 'webinar'; schema: any }>({
    page_type: 'optin',
    schema: optinPage?.schema ?? {},
});

const publishForm = useForm({});
const unpublishForm = useForm({});
const archiveForm = useForm({});
const deleteForm = useForm({});

const settingsForm = useForm<{
    webinar_title: string;
    webinar_description: string;
    video_url: string;
    webinar_duration_seconds: number | null;
    webinar_cta_label: string;
    webinar_cta_url: string;
    affiliate_request_link: string;
    jv_page: string;
    offers: Array<{
        title: string;
        description: string;
        cta_label: string;
        cta_url: string;
        placement: 'chat' | 'pinned' | 'popup';
        timing_seconds: number;
        enabled: boolean;
    }>;
    exit_popup_enabled: boolean;
    exit_popup_show_close: boolean;
    exit_popup_title: string;
    exit_popup_description: string;
    exit_popup_cta_label: string;
    exit_popup_cta_url: string;
    redirect_enabled: boolean;
    redirect_url: string;
    webinar_ai_enabled: boolean;
    webinar_ai_auto_reply_enabled: boolean;
    webinar_ai_assistant_name: string;
    chat_mode: string;
    allow_replay: boolean;
    chat_seed_messages: Array<{ author: string; message: string }>;
    branding: { primary: string; secondary: string };
    integration_account_ids: number[];
    traffic_ai_reply_enabled: boolean;
    traffic_ai_link_override: string;
    traffic_ai_extra_context: string;
    traffic_ai_social_account_ids: { reddit: number | null; youtube: number | null; twitter: number | null };
}>({
    webinar_title: props.funnel.settings?.webinar_title ?? '',
    webinar_description: props.funnel.settings?.webinar_description ?? '',
    video_url: props.funnel.settings?.video_url ?? '',
    webinar_duration_seconds: props.funnel.settings?.webinar_duration_seconds ?? null,
    webinar_cta_label: props.funnel.settings?.webinar_cta_label ?? 'Claim Your Spot',
    webinar_cta_url: props.funnel.settings?.webinar_cta_url ?? '',
    affiliate_request_link: props.funnel.settings?.affiliate_request_link ?? '',
    jv_page: props.funnel.settings?.jv_page ?? '',
    offers: (props.funnel.settings?.offers ?? []).map((offer) => ({
        title: offer.title ?? '',
        description: offer.description ?? '',
        cta_label: offer.cta_label ?? 'Get Offer',
        cta_url: offer.cta_url ?? '',
        placement: offer.placement ?? 'pinned',
        timing_seconds: Number(offer.timing_seconds ?? 0),
        enabled: offer.enabled !== false,
    })),
    exit_popup_enabled: props.funnel.settings?.exit_popup_enabled ?? false,
    exit_popup_show_close: props.funnel.settings?.exit_popup_show_close ?? true,
    exit_popup_title: props.funnel.settings?.exit_popup_title ?? 'Wait! Before You Go...',
    exit_popup_description: props.funnel.settings?.exit_popup_description ?? 'Claim this special offer before leaving this webinar room.',
    exit_popup_cta_label: props.funnel.settings?.exit_popup_cta_label ?? 'Claim Offer Now',
    exit_popup_cta_url: props.funnel.settings?.exit_popup_cta_url ?? '',
    redirect_enabled: props.funnel.settings?.redirect_enabled ?? false,
    redirect_url: props.funnel.settings?.redirect_url ?? '',
    webinar_ai_enabled: props.funnel.settings?.webinar_ai_enabled ?? false,
    webinar_ai_auto_reply_enabled: props.funnel.settings?.webinar_ai_auto_reply_enabled ?? true,
    webinar_ai_assistant_name: props.funnel.settings?.webinar_ai_assistant_name ?? '',
    chat_mode: props.funnel.settings?.chat_mode ?? 'simulated',
    allow_replay: props.funnel.settings?.allow_replay ?? true,
    chat_seed_messages: props.funnel.settings?.chat_seed_messages ?? [],
    branding: { primary: '#111827', secondary: '#F9FAFB' },
    integration_account_ids: props.funnel.integrations.map((i) => i.integration_account.id),
    traffic_ai_reply_enabled: props.funnel.settings?.traffic_ai_reply_enabled ?? false,
    traffic_ai_link_override: props.funnel.settings?.traffic_ai_link_override ?? '',
    traffic_ai_extra_context: props.funnel.settings?.traffic_ai_extra_context ?? '',
    traffic_ai_social_account_ids: {
        reddit: (props.funnel.settings?.traffic_ai_social_account_ids as Record<string, number | null> | undefined)?.reddit ?? null,
        youtube: (props.funnel.settings?.traffic_ai_social_account_ids as Record<string, number | null> | undefined)?.youtube ?? null,
        twitter: (props.funnel.settings?.traffic_ai_social_account_ids as Record<string, number | null> | undefined)?.twitter ?? null,
    },
});

const savePage = (): void => {
    if (editorContainer.value && (editorContainer.value as any).__gjsEditor) {
        const editor = (editorContainer.value as any).__gjsEditor;

        /*
         * Only store plain strings — never pass GrapesJS component/style
         * manager objects into the form, as their internal reactive proxies
         * cause Inertia's hasFiles() serialiser to overflow the call stack.
         */
        pageForm.schema = {
            html: String(editor.getHtml()),
            css:  String(editor.getCss()),
        };
    }

    savingPage.value = true;
    pageForm.patch(`/funnels/${props.funnel.id}/pages`, {
        onFinish: () => {
            savingPage.value = false;
        },
    });
};

const saveSettings = (): void => {
    savingSettings.value = true;
    settingsForm.patch(`/funnels/${props.funnel.id}/settings`, {
        preserveScroll: true,
        onError: (errors) => {
            const first = Object.values(errors)[0];
            if (first) {
                toast.error(typeof first === 'string' ? first : 'Could not save settings.');
            }
        },
        onFinish: () => {
            savingSettings.value = false;
        },
    });
};

function saveTrafficAiReplyEnabled(enabled: boolean): void {
    settingsForm.traffic_ai_reply_enabled = enabled;
    autoAssignTrafficAccounts();
    savingSettings.value = true;
    settingsForm.patch(`/funnels/${props.funnel.id}/settings`, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(enabled ? 'Traffic AI reply enabled' : 'Traffic AI reply disabled');
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            toast.error(typeof first === 'string' ? first : 'Could not save auto-reply setting.');
            settingsForm.traffic_ai_reply_enabled = !enabled;
        },
        onFinish: () => {
            savingSettings.value = false;
        },
    });
}

/* Auto-save for individual toggles — debounced so rapid toggles batch into one PATCH. */
let autoSaveTimer: number | undefined;
const autoSaveSettings = (label = 'Saved'): void => {
    if (autoSaveTimer !== undefined) {
        window.clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = window.setTimeout(() => {
        savingSettings.value = true;
        settingsForm.patch(`/funnels/${props.funnel.id}/settings`, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(label);
            },
            onError: (errors) => {
                const first = Object.values(errors)[0];
                toast.error(typeof first === 'string' ? first : 'Could not save change.');
            },
            onFinish: () => {
                savingSettings.value = false;
            },
        });
    }, 350);
};

const addOfferRow = (): void => {
    settingsForm.offers.push({
        title: '',
        description: '',
        cta_label: 'Get Offer',
        cta_url: '',
        placement: 'pinned',
        timing_seconds: 30,
        enabled: true,
    });
};

const removeOfferRow = (index: number): void => {
    settingsForm.offers.splice(index, 1);
};

const publish = (): void => {
    publishing.value = true;
    publishForm.post(`/funnels/${props.funnel.id}/publish`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const unpublish = (): void => {
    publishing.value = true;
    unpublishForm.post(`/funnels/${props.funnel.id}/unpublish`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const archive = (): void => {
    if (!window.confirm('Archive this funnel? It will no longer be publicly accessible.')) {
        return;
    }

    publishing.value = true;
    archiveForm.post(`/funnels/${props.funnel.id}/archive`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const removeFunnel = (): void => {
    if (!window.confirm('Delete this funnel permanently? This cannot be undone.')) {
        return;
    }

    publishing.value = true;
    deleteForm.delete(`/funnels/${props.funnel.id}`, {
        onFinish: () => {
            publishing.value = false;
        },
    });
};

const copyLink = async (type: 'optin' | 'webinar'): Promise<void> => {
    await navigator.clipboard.writeText(props.publicLinks[type]);
    copiedLink.value = type;
    setTimeout(() => {
        copiedLink.value = null;
    }, 2000);
};

const openExternalLink = (url: string): void => {
    const normalized = url.trim();
    if (!normalized) {
        return;
    }

    window.open(normalized, '_blank', 'noopener,noreferrer');
};

const espProviderIcon: Record<string, string> = {
    mailchimp: 'simple-icons:mailchimp',
    getresponse: 'simple-icons:getresponse',
    activecampaign: 'simple-icons:activecampaign',
    convertkit: 'simple-icons:convertkit',
    aweber: 'logos:aweber',
    drip: 'simple-icons:drip',
};

function providerIcon(provider: string): string {
    return espProviderIcon[provider.toLowerCase()] ?? 'heroicons:envelope';
}

/* ─── Webinar AI Assistant state ────────────────────────────────────────── */
const AI_SOURCE_LIMIT = 3;
const aiSourcesList = ref(props.aiSources ?? []);
const aiSourceCount = computed(() => aiSourcesList.value.length);
const aiSourceLimitReached = computed(() => aiSourceCount.value >= AI_SOURCE_LIMIT);
const aiSourceSlotsRemaining = computed(() => Math.max(0, AI_SOURCE_LIMIT - aiSourceCount.value));
const aiSourceLoading = ref(false);

const aiUrlForm = useForm({
    title: '',
    url: '',
});

const aiTranscriptForm = useForm({
    title: '',
    transcript: '',
});

const aiFileForm = useForm<{
    title: string;
    file: File | null;
}>({
    title: '',
    file: null,
});

const loadAiSources = async (): Promise<void> => {
    if (!props.aiSourceUrls.index) return;
    aiSourceLoading.value = true;
    try {
        const response = await fetch(props.aiSourceUrls.index, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const payload = await response.json();
        aiSourcesList.value = payload.data ?? [];
    } catch {
        // keep current list on fetch failures
    } finally {
        aiSourceLoading.value = false;
    }
};

const addAiUrlSource = (): void => {
    if (!props.aiSourceUrls.url || aiSourceLimitReached.value) return;
    aiUrlForm.post(props.aiSourceUrls.url, {
        preserveScroll: true,
        onSuccess: async () => {
            aiUrlForm.reset();
            await loadAiSources();
        },
    });
};

const addAiTranscriptSource = (): void => {
    if (!props.aiSourceUrls.transcript || aiSourceLimitReached.value) return;
    aiTranscriptForm.post(props.aiSourceUrls.transcript, {
        preserveScroll: true,
        onSuccess: async () => {
            aiTranscriptForm.reset();
            await loadAiSources();
        },
    });
};

const setAiFile = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    aiFileForm.file = input.files?.[0] ?? null;
};

const addAiFileSource = (): void => {
    if (!props.aiSourceUrls.file || aiSourceLimitReached.value || !aiFileForm.file) return;
    aiFileForm.post(props.aiSourceUrls.file, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: async () => {
            aiFileForm.reset();
            await loadAiSources();
        },
    });
};

const deleteAiSource = async (source: { delete_url: string }): Promise<void> => {
    if (!source.delete_url) return;
    try {
        await fetch(source.delete_url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        });
    } finally {
        await loadAiSources();
    }
};

/* ─── AI source chunk preview ──────────────────────────── */
const addSourceTab = ref<'url' | 'text' | 'file'>('url');
const expandedSourceIds = ref<Set<number>>(new Set());
const sourceChunks = ref<Record<number, Array<{ id: string; chunk_index: number; content: string }>>>({});
const sourceChunksLoading = ref<Record<number, boolean>>({});

const loadSourceChunks = async (source: { id: number; chunks_url: string }): Promise<void> => {
    if (sourceChunks.value[source.id]) return;
    sourceChunksLoading.value = { ...sourceChunksLoading.value, [source.id]: true };
    try {
        const res = await fetch(`${source.chunks_url}?per_page=20`, { headers: { Accept: 'application/json' } });
        if (!res.ok) return;
        const payload = await res.json();
        sourceChunks.value = { ...sourceChunks.value, [source.id]: payload.data ?? [] };
    } catch {
        // ignore
    } finally {
        sourceChunksLoading.value = { ...sourceChunksLoading.value, [source.id]: false };
    }
};

const toggleSourceChunks = (source: { id: number; chunks_url: string }): void => {
    const next = new Set(expandedSourceIds.value);
    if (next.has(source.id)) {
        next.delete(source.id);
    } else {
        next.add(source.id);
        void loadSourceChunks(source);
    }
    expandedSourceIds.value = next;
};

/* ─── Traffic Settings state ───────────────────────────────────────────── */
const trafficSearch = ref(props.traffic.filters.search ?? '');
const trafficPlatform = ref(props.traffic.filters.platform ?? '');
const trafficKeywordId = ref<number | string>(props.traffic.filters.keyword_id ?? '');
const trafficKeywordModalOpen = ref(false);
const trafficAiSectionOpen = ref(false);
let trafficDebounce: ReturnType<typeof setTimeout>;

const trafficKeywordForm = useForm({
    name: '',
    platforms: ['reddit', 'youtube', 'twitter', 'news'],
});

const PLATFORM_OPTIONS = ['reddit', 'youtube', 'twitter', 'news'];
const PLATFORM_META: Record<string, { label: string; icon: string; color: string; bg: string }> = {
    reddit: { label: 'Reddit', icon: 'simple-icons:reddit', color: '#ff6b35', bg: 'rgba(255,69,0,0.12)' },
    youtube: { label: 'YouTube', icon: 'simple-icons:youtube', color: '#ff4444', bg: 'rgba(255,0,0,0.12)' },
    twitter: { label: 'Twitter', icon: 'simple-icons:x', color: '#e2e8f0', bg: 'rgba(255,255,255,0.08)' },
    news: { label: 'News', icon: 'heroicons:newspaper', color: '#4e9af1', bg: 'rgba(26,115,232,0.12)' },
};

function trafficPlatformMeta(key: string) {
    const normalized = String(key).toLowerCase();
    return PLATFORM_META[normalized] ?? { label: key, icon: 'heroicons:globe-alt', color: '#94a3b8', bg: 'rgba(148,163,184,0.12)' };
}

const trafficPlatformTabs = computed(() => {
    const all = { key: '', label: 'All', count: props.traffic.stats.total };
    const entries = Object.entries(props.traffic.stats.platforms ?? {}).map(([k, v]) => ({
        key: String(k).toLowerCase(),
        label: k,
        count: Number(v),
    }));
    return [all, ...entries];
});

watch([trafficSearch, trafficPlatform, trafficKeywordId], ([s, p, k]) => {
    clearTimeout(trafficDebounce);
    trafficDebounce = setTimeout(() => {
        router.get(`/funnels/${props.funnel.id}/edit`, {
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

const goToTrafficMentionsPage = (url: string | null): void => {
    if (!url) {
        return;
    }

    router.get(url, {}, { preserveState: true, preserveScroll: true });
};

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
    resetTrafficKeywordForm();
    trafficKeywordModalOpen.value = true;
}

function closeTrafficKeywordModal(): void {
    trafficKeywordModalOpen.value = false;
    resetTrafficKeywordForm();
}

function submitTrafficKeyword(): void {
    trafficKeywordForm.post(`/funnels/${props.funnel.id}/traffic/keywords`, {
        preserveScroll: true,
        onSuccess: () => {
            closeTrafficKeywordModal();
        },
    });
}

function toggleTrafficKeywordActive(keyword: { id: number; is_active: boolean }): void {
    router.patch(`/funnels/${props.funnel.id}/traffic/keywords/${keyword.id}`, {
        is_active: !keyword.is_active,
    }, { preserveScroll: true });
}

function toggleTrafficKeywordNotifications(keyword: { id: number; email_notifications: boolean }): void {
    router.patch(`/funnels/${props.funnel.id}/traffic/keywords/${keyword.id}`, {
        email_notifications: !keyword.email_notifications,
    }, { preserveScroll: true });
}

function fetchTrafficKeywordNow(keyword: { id: number }): void {
    router.post(`/funnels/${props.funnel.id}/traffic/keywords/${keyword.id}/fetch`, {}, { preserveScroll: true });
}

function deleteTrafficKeyword(keyword: { id: number; name: string }): void {
    if (!window.confirm(`Delete keyword "${keyword.name}" and its mentions?`)) return;
    router.delete(`/funnels/${props.funnel.id}/traffic/keywords/${keyword.id}`, { preserveScroll: true });
}

function fmtTrafficDate(dt: string | null): string {
    if (!dt) return '';
    const d = new Date(dt);
    const now = Date.now();
    const diff = (now - d.getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const trafficReplyPlatforms = [
    { key: 'reddit' as const, label: 'Reddit', icon: 'simple-icons:reddit', color: '#ff6b35' },
    { key: 'youtube' as const, label: 'YouTube', icon: 'simple-icons:youtube', color: '#ff0000' },
    { key: 'twitter' as const, label: 'X (Twitter)', icon: 'simple-icons:x', color: '#e2e8f0' },
];

function trafficAccountsForPlatform(platform: string): Array<{ id: number; platform: string; platform_username: string | null }> {
    return props.traffic.social_accounts.filter((a) => a.platform === platform || (platform === 'twitter' && a.platform === 'x'));
}

function trafficAccountForPlatform(platform: 'reddit' | 'youtube' | 'twitter') {
    return trafficAccountsForPlatform(platform)[0] ?? null;
}

const trafficMaxRepliesPerDay = computed(() => props.traffic.max_replies_per_day_per_account ?? 20);

function trafficRepliesPostedToday(platform: 'reddit' | 'youtube' | 'twitter'): number {
    const account = trafficAccountForPlatform(platform);
    if (!account) {
        return 0;
    }
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

/** One Zernio account per platform — wire IDs automatically. */
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

function trunc(text: string | null, len = 200): string {
    if (!text) return '';
    return text.length > len ? `${text.slice(0, len)}…` : text;
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);

    if (!editorContainer.value || !blocksContainer.value || !stylesContainer.value) {
        return;
    }

    const schema = pageForm.schema as any;

    const initialHtml = schema?.html ?? `
<div class="dfy-page">
  <section class="dfy-hero">
    <div class="dfy-inner">
      <span class="dfy-badge">🎓 FREE WEBINAR</span>
      <h1 class="dfy-headline">${schema?.hero?.headline ?? 'Your Webinar Headline Here'}</h1>
      <p class="dfy-sub">${schema?.hero?.subheadline ?? 'Register below to secure your free spot.'}</p>
      <form class="dfy-form" data-locked-form="true">
        <input class="dfy-input" name="name" type="text" placeholder="Your full name" required />
        <input class="dfy-input" name="email" type="email" placeholder="Your best email" required />
        <button class="dfy-btn" type="submit">${schema?.hero?.cta ?? 'Reserve My Spot →'}</button>
      </form>
    </div>
  </section>
</div>`;

    const initialCss = schema?.css ?? `
.dfy-page{min-height:100vh;background:linear-gradient(140deg,#060d1a 0%,#0d2039 100%);display:flex;align-items:center;justify-content:center;padding:40px 16px}
.dfy-inner{max-width:520px;width:100%;text-align:center}
.dfy-badge{display:inline-block;background:rgba(255,80,80,.15);border:1px solid rgba(255,80,80,.3);color:#ff7070;padding:6px 16px;border-radius:100px;font-size:11px;font-weight:700;margin-bottom:24px}
.dfy-headline{font-size:2.2rem;font-weight:900;color:#fff;line-height:1.2;margin-bottom:14px}
.dfy-sub{font-size:1rem;color:rgba(255,255,255,.6);line-height:1.7;margin-bottom:28px}
.dfy-form{background:rgba(255,255,255,.05);border:1px solid rgba(64,224,208,.2);border-radius:16px;padding:28px}
.dfy-input{display:block;width:100%;padding:13px 16px;border:1px solid rgba(255,255,255,.15);border-radius:8px;background:rgba(255,255,255,.07);color:#fff;font-size:15px;outline:none;margin-bottom:12px}
.dfy-input::placeholder{color:rgba(255,255,255,.35)}
.dfy-btn{width:100%;padding:15px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:16px;font-weight:800;border:none;border-radius:8px;cursor:pointer}`;

    /* ── Inline SVG icons for blocks ─────────────────────────────────────── */
    const svg = (path: string) =>
        `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${path}</svg>`;

    /* ── GrapesJS init ───────────────────────────────────────────────────── */
    const editor = grapesjs.init({
        container: editorContainer.value,
        fromElement: false,
        height: '100%',
        width: 'auto',
        storageManager: false,
        components: initialHtml,
        style: initialCss,

        /* Disable ALL default panels — we build our own toolbar in Vue */
        panels: { defaults: [] },

        deviceManager: {
            devices: [
                { name: 'Desktop', width: '' },
                { name: 'Mobile',  width: '375px', widthMedia: '480px' },
            ],
        },

        /* Inject into the canvas iframe: fill height + load Google Fonts */
        canvas: {
            styles: [
                'data:text/css,html,body{min-height:100%;height:100%;}',
                'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&family=Roboto:wght@400;700&family=Open+Sans:wght@400;600;700&family=Lato:wght@400;700&family=Montserrat:wght@400;600;700;900&family=Poppins:wght@400;600;700;900&family=Raleway:wght@400;600;700&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,700&family=Plus+Jakarta+Sans:wght@400;600;700&family=Outfit:wght@400;600;700;900&family=Nunito:wght@400;600;700&family=Oswald:wght@400;500;600;700&family=Source+Sans+3:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Lora:ital,wght@0,400;0,700;1,400&display=swap',
            ],
        },

        /* Put the block picker in our custom left sidebar */
        blockManager: {
            appendTo: blocksContainer.value,
        },

        /* Put the style editor in our custom right sidebar */
        styleManager: ({
            appendTo: stylesContainer.value,
            sectors: [
                {
                    name: 'Typography', open: true,
                    properties: [
                        { label: 'Font Family', property: 'font-family', type: 'select', defaults: 'inherit',
                            options: [
                                { value: 'inherit',                                    name: '— Default —' },
                                /* ── Sans-serif ── */
                                { value: "'Inter', sans-serif",                        name: 'Inter' },
                                { value: "'Roboto', sans-serif",                       name: 'Roboto' },
                                { value: "'Open Sans', sans-serif",                    name: 'Open Sans' },
                                { value: "'Lato', sans-serif",                         name: 'Lato' },
                                { value: "'Montserrat', sans-serif",                   name: 'Montserrat' },
                                { value: "'Poppins', sans-serif",                      name: 'Poppins' },
                                { value: "'Raleway', sans-serif",                      name: 'Raleway' },
                                { value: "'DM Sans', sans-serif",                      name: 'DM Sans' },
                                { value: "'Plus Jakarta Sans', sans-serif",            name: 'Plus Jakarta Sans' },
                                { value: "'Outfit', sans-serif",                       name: 'Outfit' },
                                { value: "'Nunito', sans-serif",                       name: 'Nunito' },
                                { value: "'Oswald', sans-serif",                       name: 'Oswald' },
                                { value: "'Source Sans 3', sans-serif",                name: 'Source Sans 3' },
                                /* ── Serif ── */
                                { value: "'Playfair Display', serif",                  name: 'Playfair Display' },
                                { value: "'Merriweather', serif",                      name: 'Merriweather' },
                                { value: "'Lora', serif",                              name: 'Lora' },
                                { value: "Georgia, serif",                             name: 'Georgia' },
                                /* ── System ── */
                                { value: "Arial, Helvetica, sans-serif",              name: 'Arial' },
                                { value: "'Helvetica Neue', Helvetica, sans-serif",   name: 'Helvetica Neue' },
                                { value: "'Trebuchet MS', sans-serif",                name: 'Trebuchet MS' },
                                { value: "'Times New Roman', Times, serif",           name: 'Times New Roman' },
                            ],
                        },
                        { label: 'Size',        property: 'font-size',   type: 'integer', units: ['px','rem','em','%'], defaults: '16px' },
                        { label: 'Weight',      property: 'font-weight', type: 'select',  defaults: '400',
                            options: [{ value: '300', name: 'Light' }, { value: '400', name: 'Regular' }, { value: '600', name: 'Semi-Bold' }, { value: '700', name: 'Bold' }, { value: '900', name: 'Black' }] },
                        { label: 'Color',       property: 'color',       type: 'color' },
                        { label: 'Align',       property: 'text-align',  type: 'radio',   defaults: 'left',
                            options: [{ value: 'left', name: 'L' }, { value: 'center', name: 'C' }, { value: 'right', name: 'R' }] },
                        { label: 'Line Height', property: 'line-height', type: 'integer', units: ['','px','em'], defaults: '1.5' },
                    ],
                },
                {
                    name: 'Background', open: false,
                    properties: [
                        { label: 'Color',    property: 'background-color', type: 'color' },
                    ],
                },
                {
                    name: 'Spacing', open: false,
                    properties: [
                        { property: 'padding', type: 'composite',
                            properties: [
                                { property: 'padding-top',    type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-right',  type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-bottom', type: 'integer', units: ['px','%','em'], defaults: '0' },
                                { property: 'padding-left',   type: 'integer', units: ['px','%','em'], defaults: '0' },
                            ]},
                        { property: 'margin', type: 'composite',
                            properties: [
                                { property: 'margin-top',    type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-right',  type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-bottom', type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                                { property: 'margin-left',   type: 'integer', units: ['px','%','em','auto'], defaults: '0' },
                            ]},
                    ],
                },
                {
                    name: 'Border', open: false,
                    properties: [
                        { label: 'Radius', property: 'border-radius', type: 'integer', units: ['px','%'] },
                        { label: 'Width',  property: 'border-width',  type: 'integer', units: ['px'] },
                        { label: 'Color',  property: 'border-color',  type: 'color' },
                        { label: 'Style',  property: 'border-style',  type: 'select',
                            options: [{ value: 'none', name: 'None' }, { value: 'solid', name: 'Solid' }, { value: 'dashed', name: 'Dashed' }, { value: 'dotted', name: 'Dotted' }] },
                    ],
                },
                {
                    name: 'Size', open: false,
                    properties: [
                        { label: 'Width',     property: 'width',     type: 'integer', units: ['px','%','vw','auto'] },
                        { label: 'Max Width', property: 'max-width', type: 'integer', units: ['px','%','none'] },
                        { label: 'Height',    property: 'height',    type: 'integer', units: ['px','%','vh','auto'] },
                    ],
                },
            ],
        } as any),
    });

    /* ── Pre-built drag-and-drop blocks ──────────────────────────────────── */
    const BLOCKS = [
        {
            id: 'heading', label: 'Heading', category: 'Content',
            media: svg('<path d="M4 6h16M4 12h10M4 18h7"/>'),
            content: '<h2 style="font-size:2rem;font-weight:800;color:#111827;margin:0 0 8px;line-height:1.2;">Your Headline Here</h2>',
        },
        {
            id: 'paragraph', label: 'Paragraph', category: 'Content',
            media: svg('<path d="M4 6h16M4 10h16M4 14h12M4 18h9"/>'),
            content: '<p style="font-size:1rem;color:#4B5563;line-height:1.7;margin:0 0 16px;">Click to edit this paragraph. Write something compelling about your webinar or offer.</p>',
        },
        {
            id: 'button', label: 'Button', category: 'Content',
            media: svg('<rect x="2" y="7" width="20" height="10" rx="3"/><path d="M9 12h6"/>'),
            content: '<a href="#" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#40E0D0,#2dc4b5);color:#060d1a;font-size:15px;font-weight:700;border-radius:8px;text-decoration:none;">Register Now →</a>',
        },
        {
            id: 'badge', label: 'Badge', category: 'Content',
            media: svg('<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M9 12h6"/>'),
            content: '<span style="display:inline-block;background:rgba(64,224,208,0.12);border:1px solid rgba(64,224,208,0.3);color:#0d9488;padding:6px 16px;border-radius:100px;font-size:12px;font-weight:700;letter-spacing:0.06em;">🎓 FREE WEBINAR</span>',
        },
        {
            id: 'list', label: 'Check List', category: 'Content',
            media: svg('<path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138"/>'),
            content: `<ul style="list-style:none;padding:0;margin:0;">
<li style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>First benefit or feature here</li>
<li style="display:flex;align-items:flex-start;gap:10px;margin-bottom:12px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>Second benefit or feature here</li>
<li style="display:flex;align-items:flex-start;gap:10px;font-size:15px;color:#374151;"><span style="color:#40E0D0;font-weight:800;font-size:16px;line-height:1.5;">✓</span>Third benefit or feature here</li>
</ul>`,
        },
        {
            id: 'testimonial', label: 'Testimonial', category: 'Content',
            media: svg('<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/>'),
            content: `<blockquote style="background:rgba(64,224,208,0.07);border-left:4px solid #40E0D0;padding:20px 24px;border-radius:0 12px 12px 0;margin:0;">
<p style="font-size:1rem;color:#374151;font-style:italic;line-height:1.7;margin:0 0 10px;">"This webinar completely changed how I approach my business. Practical, actionable, and incredibly valuable!"</p>
<cite style="font-size:0.85rem;font-weight:600;color:#111827;font-style:normal;">— Jane Smith, CEO at Example Co.</cite>
</blockquote>`,
        },
        {
            id: 'image', label: 'Image', category: 'Media',
            media: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'),
            content: '<img src="https://placehold.co/800x400/40E0D0/060d1a?text=Your+Image" style="max-width:100%;height:auto;display:block;border-radius:10px;" alt="Image"/>',
        },
        {
            id: 'video', label: 'Video', category: 'Media',
            media: svg('<rect x="2" y="4" width="20" height="16" rx="2"/><polygon points="10 9 16 12 10 15 10 9"/>'),
            content: '<div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;background:#000;"><iframe style="position:absolute;top:0;left:0;width:100%;height:100%;" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen></iframe></div>',
        },
        {
            id: 'cols-2', label: '2 Columns', category: 'Layout',
            media: svg('<rect x="2" y="4" width="9" height="16" rx="1.5"/><rect x="13" y="4" width="9" height="16" rx="1.5"/>'),
            content: '<div style="display:flex;gap:16px;"><div style="flex:1;padding:20px 16px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:80px;"><p style="color:#374151;margin:0;">Column 1</p></div><div style="flex:1;padding:20px 16px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:80px;"><p style="color:#374151;margin:0;">Column 2</p></div></div>',
        },
        {
            id: 'cols-3', label: '3 Columns', category: 'Layout',
            media: svg('<rect x="2" y="4" width="6" height="16" rx="1.5"/><rect x="9" y="4" width="6" height="16" rx="1.5"/><rect x="16" y="4" width="6" height="16" rx="1.5"/>'),
            content: '<div style="display:flex;gap:12px;"><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 1</p></div><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 2</p></div><div style="flex:1;padding:16px 12px;background:rgba(0,0,0,0.03);border-radius:8px;min-height:70px;"><p style="color:#374151;margin:0;font-size:14px;">Col 3</p></div></div>',
        },
        {
            id: 'section', label: 'Section', category: 'Layout',
            media: svg('<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9h20"/>'),
            content: '<section style="padding:60px 24px;background:linear-gradient(135deg,#060d1a,#0d2039);text-align:center;"><h2 style="font-size:2rem;font-weight:900;color:#fff;margin:0 0 12px;">Section Heading</h2><p style="font-size:1rem;color:rgba(255,255,255,0.6);margin:0 auto;max-width:480px;line-height:1.7;">Add your description here to tell visitors what this section is about.</p></section>',
        },
        {
            id: 'divider', label: 'Divider', category: 'Layout',
            media: svg('<line x1="5" y1="12" x2="19" y2="12"/>'),
            content: '<hr style="border:none;border-top:1px solid rgba(0,0,0,0.1);margin:24px 0;"/>',
        },
        {
            id: 'spacer', label: 'Spacer', category: 'Layout',
            media: svg('<path d="M12 3v18M5 8l7-5 7 5M5 16l7 5 7-5"/>'),
            content: '<div style="height:48px;"></div>',
        },
    ];

    BLOCKS.forEach((b) => {
        editor.BlockManager.add(b.id, { label: b.label, category: b.category, media: b.media, content: b.content });
    });

    /* ── Lock the opt-in form ─────────────────────────────────────────────── */
    editor.on('component:remove:before', (component: any, _remove: () => void, opts: any) => {
        if (component?.getAttributes()?.['data-locked-form']) {
            opts.abort = true;
        }
    });

    /* ── Inject a modern light theme over GrapesJS defaults ──────────────── */
    if (!document.getElementById('gjs-dfy-theme')) {
        const s = document.createElement('style');
        s.id = 'gjs-dfy-theme';
        s.textContent = `
/* ──────────────────────────────────────────────────────────────────────────
   DFY GrapesJS Light Theme — overrides the default dark gray UI
   ──────────────────────────────────────────────────────────────────────── */

/* Canvas — force full-width AND full-height fill of the container */
.gjs-cv-canvas {
  background: #111827 !important;
  width: 100% !important;
  height: 100% !important;
  top: 0 !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
}
.gjs-cv-canvas__frames {
  width: 100% !important;
  height: 100% !important;
}
/* Do NOT force width on frame-wrapper/frame — GrapesJS needs to set 375px for mobile */
.gjs-frame-wrapper { height: 100% !important; }
.gjs-frame          { height: 100% !important; min-height: 100% !important; }

/* Hide built-in GrapesJS panel bar (we replaced it with our Vue toolbar) */
.gjs-pn-panels { display: none !important; }

/* Block categories */
.gjs-block-category .gjs-title {
  background: transparent !important;
  color: #9ca3af !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  letter-spacing: .08em !important;
  text-transform: uppercase !important;
  padding: 12px 10px 4px !important;
  border-bottom: 1px solid #f3f4f6 !important;
}
.gjs-block-category .gjs-caret-icon { color: #9ca3af !important; }

/* Block grid */
.gjs-blocks-c {
  display: grid !important;
  grid-template-columns: 1fr 1fr !important;
  gap: 5px !important;
  padding: 8px !important;
}

/* Single block tile */
.gjs-block {
  background: #fff !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 8px !important;
  padding: 10px 4px 7px !important;
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  gap: 5px !important;
  cursor: grab !important;
  transition: border-color .15s, box-shadow .15s, transform .15s !important;
  min-height: unset !important;
  width: auto !important;
}
.gjs-block:hover {
  border-color: #40E0D0 !important;
  background: rgba(64,224,208,.06) !important;
  transform: translateY(-1px) !important;
  box-shadow: 0 3px 10px rgba(64,224,208,.18) !important;
}
.gjs-block svg {
  width: 22px !important; height: 22px !important;
  color: #9ca3af !important;
  transition: color .15s !important;
}
.gjs-block:hover svg { color: #0d9488 !important; }

/* Block label */
.gjs-block-label {
  font-size: 10px !important;
  font-weight: 600 !important;
  color: #374151 !important;
  text-align: center !important;
  line-height: 1.2 !important;
}

/* Style manager sectors */
.gjs-sm-sector {
  border: none !important;
  border-bottom: 1px solid #f3f4f6 !important;
  background: transparent !important;
}
.gjs-sm-sector .gjs-sm-title {
  background: transparent !important;
  padding: 10px 12px 8px !important;
  font-size: 10px !important;
  font-weight: 700 !important;
  color: #9ca3af !important;
  text-transform: uppercase !important;
  letter-spacing: .06em !important;
  border: none !important;
}
.gjs-sm-properties { padding: 4px 10px 12px !important; }

/* Style inputs */
.gjs-field {
  background: #fff !important;
  border: 1px solid #e5e7eb !important;
  border-radius: 6px !important;
  color: #111827 !important;
  font-size: 12px !important;
}
.gjs-field:focus-within { border-color: #40E0D0 !important; box-shadow: 0 0 0 2px rgba(64,224,208,.15) !important; }
.gjs-sm-label { font-size: 11px !important; color: #6b7280 !important; font-weight: 500 !important; margin-bottom: 3px !important; }

/* Select element highlight */
.gjs-selected { outline: 2px solid #40E0D0 !important; }

/* Floating element toolbar (del, move, etc.) */
.gjs-toolbar { background: #111827 !important; border-radius: 8px !important; box-shadow: 0 4px 12px rgba(0,0,0,.3) !important; }
.gjs-toolbar-item { border-right: 1px solid rgba(255,255,255,.08) !important; }
.gjs-toolbar-item:hover { background: rgba(64,224,208,.15) !important; }
.gjs-toolbar-item svg { color: #fff !important; }

/* Drop placeholder */
.gjs-placeholder { background: #40E0D0 !important; opacity: .4 !important; }
.gjs-placeholder-int { background: #2dc4b5 !important; }

/* Scrollbar */
.gjs-blocks-c::-webkit-scrollbar, .gjs-sm-properties::-webkit-scrollbar { width: 3px !important; }
.gjs-blocks-c::-webkit-scrollbar-thumb, .gjs-sm-properties::-webkit-scrollbar-thumb { background: #d1d5db !important; border-radius: 2px !important; }
        `;
        document.head.appendChild(s);
    }

    gjsEditor.value = editor;
    (editorContainer.value as any).__gjsEditor = editor;
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
});
</script>

<template>
    <Head :title="`Edit — ${funnel.name}`" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 md:p-6">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3 min-w-0">
                <Button as-child variant="ghost" size="sm" class="shrink-0 text-muted-foreground h-8 px-2 -ml-1 mt-0.5">
                    <Link href="/dashboard">
                        <Icon icon="heroicons:arrow-left" class="size-4" />
                    </Link>
                </Button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-bold tracking-tight text-foreground truncate">{{ funnel.name }}</h1>
                        <Badge
                            class="capitalize text-[0.65rem] px-2 py-0.5 shrink-0"
                            :class="funnel.status === 'published'
                                ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                : funnel.status === 'archived'
                                    ? 'bg-slate-100 text-slate-700 border-slate-200'
                                    : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >
                            <span
                                v-if="funnel.status === 'published'"
                                class="mr-1 inline-block size-1.5 rounded-full bg-emerald-500"
                            />
                            {{ funnel.status }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground mt-0.5">/{{ funnel.slug }}</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto flex-wrap justify-end">
                <!-- Compact video stats chip -->
                <TooltipProvider :delay-duration="120">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <button
                                type="button"
                                class="hidden md:inline-flex items-center gap-2 rounded-md border border-border bg-muted/40 px-2.5 h-8 text-[0.7rem] font-medium hover:bg-muted/70 transition-colors"
                            >
                                <span class="inline-flex items-center gap-1 text-muted-foreground">
                                    <Icon icon="heroicons:eye" class="size-3.5" />
                                    <span class="text-foreground tabular-nums">{{ props.videoStats.accessed.toLocaleString() }}</span>
                                </span>
                                <span class="inline-block h-3 w-px bg-border" />
                                <span class="inline-flex items-center gap-1 text-muted-foreground">
                                    <Icon icon="heroicons:play" class="size-3.5" />
                                    <span class="text-[#0aa89a] tabular-nums">{{ props.videoStats.watched_60s.toLocaleString() }}</span>
                                </span>
                                <Icon icon="heroicons:chevron-down" class="size-3 text-muted-foreground" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" align="end" class="px-3 py-2 max-w-[260px]">
                            <p class="text-[0.65rem] font-semibold uppercase tracking-wide opacity-70 mb-1.5">Video watch stats</p>
                            <div class="space-y-1 text-[0.7rem]">
                                <div class="flex justify-between gap-4"><span class="opacity-80">Accessed link</span><span class="font-semibold tabular-nums">{{ props.videoStats.accessed.toLocaleString() }}</span></div>
                                <div class="flex justify-between gap-4"><span class="opacity-80">Watched 60s</span><span class="font-semibold tabular-nums">{{ props.videoStats.watched_60s.toLocaleString() }}</span></div>
                                <div class="flex justify-between gap-4"><span class="opacity-80">Watched 50%</span><span class="font-semibold tabular-nums">{{ props.videoStats.watched_50_percent.toLocaleString() }}</span></div>
                                <div class="flex justify-between gap-4"><span class="opacity-80">Watched end</span><span class="font-semibold tabular-nums">{{ props.videoStats.watched_to_end.toLocaleString() }}</span></div>
                                <div class="flex justify-between gap-4"><span class="opacity-80">Avg watch (s)</span><span class="font-semibold tabular-nums">{{ props.videoStats.avg_watch_seconds.toLocaleString() }}</span></div>
                            </div>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                <Button as-child variant="outline" size="sm" class="h-8 text-xs gap-1.5">
                    <a :href="`/funnels/${funnel.id}/chat`">
                        <Icon icon="heroicons:chat-bubble-left-right" class="size-3.5" />
                        Chat Manager
                    </a>
                </Button>

                <!-- Share button -->
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs gap-1.5"
                    @click="shareModalOpen = true"
                >
                    <Icon icon="heroicons:share" class="size-3.5" />
                    Share
                </Button>

                <Button
                    v-if="funnel.status === 'published'"
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs gap-1.5"
                    :disabled="publishing"
                    @click="unpublish"
                >
                    <Icon icon="heroicons:arrow-uturn-left" class="size-3.5" />
                    Unpublish
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs gap-1.5"
                    :disabled="publishing"
                    @click="archive"
                >
                    <Icon icon="heroicons:archive-box" class="size-3.5" />
                    Archive
                </Button>
                <Button
                    size="sm"
                    class="h-8 text-xs gap-1.5 font-semibold"
                    :class="funnel.status === 'published'
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white'
                        : 'bg-primary text-primary-foreground hover:opacity-90'"
                    :disabled="publishing"
                    @click="publish"
                >
                    <Icon
                        v-if="publishing"
                        icon="heroicons:arrow-path"
                        class="size-3.5 animate-spin"
                    />
                    <Icon v-else icon="heroicons:rocket-launch" class="size-3.5" />
                    {{ publishing ? 'Publishing…' : funnel.status === 'published' ? 'Re-publish' : 'Publish' }}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-8 text-xs gap-1.5 border-destructive/30 text-destructive hover:bg-destructive/5"
                    :disabled="publishing"
                    @click="removeFunnel"
                >
                    <Icon icon="heroicons:trash" class="size-3.5" />
                    Delete
                </Button>
            </div>
        </div>

        <!-- ── Tabs ── -->
        <Tabs v-model="activeTab" default-value="optin" class="space-y-5">
            <TabsList class="h-auto gap-0.5 p-1 bg-muted rounded-xl w-full sm:w-auto">
                <TabsTrigger value="optin" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-3.5" />
                    Opt-in Editor
                </TabsTrigger>
                <TabsTrigger value="webinar" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:video-camera" class="size-3.5" />
                    Webinar Room
                </TabsTrigger>
                <TabsTrigger value="offer" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:gift" class="size-3.5" />
                    Offer
                </TabsTrigger>
                <TabsTrigger value="ai-assistant" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:sparkles" class="size-3.5" />
                    AI Assistant
                </TabsTrigger>
                <TabsTrigger value="integrations" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:puzzle-piece" class="size-3.5" />
                    Integrations
                </TabsTrigger>
                <TabsTrigger value="links" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:link" class="size-3.5" />
                    Share Links
                </TabsTrigger>
                <TabsTrigger value="chat" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-3.5" />
                    Chat
                    <span
                        v-if="conversationSummaries.length > 0"
                        class="ml-0.5 flex size-4 items-center justify-center rounded-full bg-primary text-[0.6rem] font-bold text-primary-foreground"
                    >
                        {{ conversationSummaries.length }}
                    </span>
                </TabsTrigger>
                <TabsTrigger value="traffic" class="rounded-lg text-xs px-3 py-1.5 gap-1.5">
                    <Icon icon="heroicons:megaphone" class="size-3.5" />
                    Traffic Settings
                </TabsTrigger>
            </TabsList>

            <!-- ── Tab: Opt-in Editor ── -->
            <TabsContent value="optin" force-mount class="m-0 p-0 data-[state=inactive]:hidden">
                <!-- Full-height 3-pane editor workspace -->
                <div
                    class="flex flex-col overflow-hidden border shadow-sm"
                    :class="isFullscreen
                        ? 'fixed inset-0 z-50 rounded-none'
                        : 'rounded-xl'"
                    :style="isFullscreen ? '' : 'height: calc(100vh - 210px); min-height: 520px;'"
                >
                    <!-- ── Toolbar ── -->
                    <div class="flex shrink-0 items-center gap-1 border-b bg-card px-3 py-1.5">

                        <!-- Undo / Redo -->
                        <button
                            title="Undo"
                            class="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="editorUndo"
                        >
                            <Icon icon="heroicons:arrow-uturn-left" class="size-3.5" />
                        </button>
                        <button
                            title="Redo"
                            class="flex size-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            @click="editorRedo"
                        >
                            <Icon icon="heroicons:arrow-uturn-right" class="size-3.5" />
                        </button>

                        <div class="mx-1.5 h-4 w-px bg-border" />

                        <!-- Device switcher -->
                        <button
                            title="Desktop preview"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="activeDevice === 'desktop' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="setDevice('desktop')"
                        >
                            <Icon icon="heroicons:computer-desktop" class="size-3.5" />
                            Desktop
                        </button>
                        <button
                            title="Mobile preview"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="activeDevice === 'mobile' ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="setDevice('mobile')"
                        >
                            <Icon icon="heroicons:device-phone-mobile" class="size-3.5" />
                            Mobile
                        </button>

                        <div class="flex-1" />

                        <!-- Lock indicator -->
                        <div class="mr-1 flex items-center gap-1 text-[0.68rem] text-muted-foreground">
                            <Icon icon="heroicons:lock-closed" class="size-3 text-[#FFAD00]" />
                            Form locked
                        </div>

                        <!-- Toggle styles panel -->
                        <button
                            title="Toggle Styles panel"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="showStyles ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="showStyles = !showStyles"
                        >
                            <Icon icon="heroicons:paint-brush" class="size-3.5" />
                            Styles
                        </button>

                        <!-- Fullscreen toggle -->
                        <button
                            :title="isFullscreen ? 'Exit fullscreen' : 'Fullscreen'"
                            class="flex items-center gap-1 rounded-md px-2 py-1 text-xs transition-colors"
                            :class="isFullscreen ? 'bg-primary/10 text-primary font-semibold' : 'text-muted-foreground hover:bg-muted hover:text-foreground'"
                            @click="toggleFullscreen"
                        >
                            <Icon
                                :icon="isFullscreen ? 'heroicons:arrows-pointing-in' : 'heroicons:arrows-pointing-out'"
                                class="size-3.5"
                            />
                            {{ isFullscreen ? 'Exit' : 'Expand' }}
                        </button>

                        <div class="mx-1.5 h-4 w-px bg-border" />

                        <!-- Save button -->
                        <Button
                            size="sm"
                            class="h-7 gap-1.5 bg-primary text-xs text-primary-foreground hover:opacity-90"
                            :disabled="savingPage || pageForm.processing"
                            @click="savePage"
                        >
                            <Icon v-if="savingPage" icon="heroicons:arrow-path" class="size-3 animate-spin" />
                            <Icon v-else icon="heroicons:cloud-arrow-up" class="size-3" />
                            {{ savingPage ? 'Saving…' : 'Save Page' }}
                        </Button>
                    </div>

                    <!-- ── Main editor area ── -->
                    <div class="flex min-h-0 flex-1 overflow-hidden">

                        <!-- Left: Blocks panel -->
                        <div class="flex w-40 shrink-0 flex-col border-r bg-card">
                            <div class="shrink-0 border-b px-3 py-2">
                                <p class="text-[0.63rem] font-bold uppercase tracking-widest text-muted-foreground">Elements</p>
                                <p class="mt-0.5 text-[0.6rem] text-muted-foreground/70">Drag onto canvas</p>
                            </div>
                            <!-- GrapesJS BlockManager appended here -->
                            <div ref="blocksContainer" class="flex-1 overflow-y-auto" />
                        </div>

                        <!-- Center: Canvas -->
                        <div ref="editorContainer" class="relative min-h-0 flex-1 overflow-hidden bg-slate-100" />

                        <!-- Right: Styles panel (toggleable) -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 translate-x-3"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-to-class="opacity-0 translate-x-3"
                        >
                            <div v-show="showStyles" class="flex w-52 shrink-0 flex-col border-l bg-card">
                                <div class="flex shrink-0 items-center justify-between border-b px-3 py-2">
                                    <p class="text-[0.63rem] font-bold uppercase tracking-widest text-muted-foreground">Styles</p>
                                    <button
                                        class="flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                                        @click="showStyles = false"
                                    >
                                        <Icon icon="heroicons:x-mark" class="size-3" />
                                    </button>
                                </div>
                                <!-- GrapesJS StyleManager appended here -->
                                <div ref="stylesContainer" class="flex-1 overflow-y-auto" />
                            </div>
                        </Transition>
                    </div>
                </div>
            </TabsContent>

            <!-- ── Tab: Webinar Room ── -->
            <TabsContent value="webinar" class="space-y-4">
                <div class="grid gap-4 lg:grid-cols-2">

                    <!-- Room details -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Room Details</CardTitle>
                            <CardDescription class="text-xs">Configure the webinar room content</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Webinar Title</Label>
                                <Input
                                    v-model="settingsForm.webinar_title"
                                    class="h-9 text-sm"
                                    placeholder="How to grow your business in 90 days"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Description</Label>
                                <Textarea
                                    v-model="settingsForm.webinar_description"
                                    class="h-20 resize-none text-sm"
                                    placeholder="Brief description shown above the video"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Video URL</Label>
                                <div class="relative">
                                    <Icon icon="heroicons:play-circle" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        v-model="settingsForm.video_url"
                                        type="url"
                                        class="pl-9 h-9 text-sm"
                                        placeholder="https://www.youtube.com/embed/…"
                                    />
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Paste a YouTube or Vimeo embed URL</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Video Duration (seconds)</Label>
                                <div class="relative">
                                    <Icon icon="heroicons:clock" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        :model-value="settingsForm.webinar_duration_seconds ?? ''"
                                        type="number"
                                        min="1"
                                        class="h-9 pl-9 text-sm"
                                        placeholder="e.g. 3600"
                                        @update:model-value="(v) => { settingsForm.webinar_duration_seconds = v === '' || v === undefined ? null : Number(v); }"
                                    />
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Used to track 50% watch and completed watch analytics.</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">CTA Button Label</Label>
                                <Input
                                    v-model="settingsForm.webinar_cta_label"
                                    class="h-9 text-sm"
                                    placeholder="Claim Your Spot"
                                />
                                <p class="text-[0.65rem] text-muted-foreground">Shown as the call-to-action button on the public webinar page.</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">CTA Link URL</Label>
                                <div class="relative">
                                    <Icon icon="heroicons:link" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                                    <Input
                                        v-model="settingsForm.webinar_cta_url"
                                        type="url"
                                        class="pl-9 h-9 text-sm"
                                        placeholder="https://your-offer-page.com"
                                    />
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Attendees click this after watching the webinar.</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Affiliate Request Link</Label>
                                <div class="flex overflow-hidden rounded-md border bg-muted/20">
                                    <div class="inline-flex h-9 items-center px-3 text-muted-foreground">
                                        <Icon icon="heroicons:link" class="size-4 pointer-events-none" />
                                    </div>
                                    <Input
                                        v-model="settingsForm.affiliate_request_link"
                                        type="url"
                                        readonly
                                        class="h-9 rounded-none border-0 bg-transparent text-sm shadow-none focus-visible:ring-0"
                                        placeholder="https://www.jvzoo.com/affiliate/..."
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center border-l px-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="!settingsForm.affiliate_request_link?.trim()"
                                        title="Open in new tab"
                                        @click="openExternalLink(settingsForm.affiliate_request_link)"
                                    >
                                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                    </button>
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Used to request affiliate access for this offer.</p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">JV Page</Label>
                                <div class="flex overflow-hidden rounded-md border bg-muted/20">
                                    <div class="inline-flex h-9 items-center px-3 text-muted-foreground">
                                        <Icon icon="heroicons:globe-alt" class="size-4 pointer-events-none" />
                                    </div>
                                    <Input
                                        v-model="settingsForm.jv_page"
                                        type="url"
                                        readonly
                                        class="h-9 rounded-none border-0 bg-transparent text-sm shadow-none focus-visible:ring-0"
                                        placeholder="https://your-offer.com/jv"
                                    />
                                    <button
                                        type="button"
                                        class="inline-flex h-9 items-center border-l px-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="!settingsForm.jv_page?.trim()"
                                        title="Open in new tab"
                                        @click="openExternalLink(settingsForm.jv_page)"
                                    >
                                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                    </button>
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground">Partner resources and launch details page.</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Video Watch Stats + replay -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-base font-semibold">Video Watch Stats</CardTitle>
                            <CardDescription class="text-xs">Live funnel performance based on webinar viewer milestones.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                                <div class="rounded-lg border bg-muted/30 p-2.5">
                                    <p class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Accessed Link</p>
                                    <p class="mt-0.5 text-lg font-bold text-foreground tabular-nums">{{ props.videoStats.accessed.toLocaleString() }}</p>
                                </div>
                                <div class="rounded-lg border bg-muted/30 p-2.5">
                                    <p class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Watched 60s</p>
                                    <p class="mt-0.5 text-lg font-bold text-[#0aa89a] tabular-nums">{{ props.videoStats.watched_60s.toLocaleString() }}</p>
                                </div>
                                <div class="rounded-lg border bg-muted/30 p-2.5">
                                    <p class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Watched 50%</p>
                                    <p class="mt-0.5 text-lg font-bold text-violet-600 tabular-nums">{{ props.videoStats.watched_50_percent.toLocaleString() }}</p>
                                </div>
                                <div class="rounded-lg border bg-muted/30 p-2.5">
                                    <p class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Watched End</p>
                                    <p class="mt-0.5 text-lg font-bold text-emerald-600 tabular-nums">{{ props.videoStats.watched_to_end.toLocaleString() }}</p>
                                </div>
                                <div class="rounded-lg border bg-muted/30 p-2.5 col-span-2 sm:col-span-1">
                                    <p class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Avg Watch (s)</p>
                                    <p class="mt-0.5 text-lg font-bold text-foreground tabular-nums">{{ props.videoStats.avg_watch_seconds.toLocaleString() }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-foreground">Allow Replay</p>
                                    <p class="text-[0.7rem] text-muted-foreground">If the video ends, attendees can replay the recording instead of seeing an "event ended" screen.</p>
                                </div>
                                <Switch
                                    :checked="settingsForm.allow_replay"
                                    @update:checked="
                                        settingsForm.allow_replay = $event;
                                        autoSaveSettings(settingsForm.allow_replay ? 'Replay enabled' : 'Replay disabled');
                                    "
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="flex justify-end">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="savingSettings || settingsForm.processing"
                        @click="saveSettings"
                    >
                        <Icon
                            v-if="savingSettings"
                            icon="heroicons:arrow-path"
                            class="size-3.5 animate-spin"
                        />
                        <Icon v-else icon="heroicons:check" class="size-3.5" />
                        {{ savingSettings ? 'Saving…' : 'Save settings' }}
                    </Button>
                </div>
            </TabsContent>

            <!-- ── Tab: Offer Settings ── -->
            <TabsContent value="offer" class="space-y-4">
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-base font-semibold">Timed Offers</CardTitle>
                                <CardDescription class="text-xs">
                                    Configure offers and where they appear in the webinar room (chat, pinned, or popup).
                                </CardDescription>
                            </div>
                            <Button size="sm" class="h-8 text-xs gap-1.5" @click="addOfferRow">
                                <Icon icon="heroicons:plus" class="size-3.5" />
                                Add Offer
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="settingsForm.offers.length === 0" class="rounded-lg border border-dashed py-10 text-center text-xs text-muted-foreground">
                            No offers yet. Add an offer to display at a specific time in the webinar.
                        </div>

                        <div v-for="(offer, index) in settingsForm.offers" :key="index" class="rounded-xl border p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold">Offer #{{ index + 1 }}</p>
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs">Enabled</Label>
                                    <Switch :checked="offer.enabled" @update:checked="offer.enabled = $event" />
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-destructive" @click="removeOfferRow(index)">
                                        <Icon icon="heroicons:trash" class="size-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">Offer Title</Label>
                                    <Input v-model="offer.title" class="h-9 text-sm" placeholder="Special bonus offer" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">CTA Label</Label>
                                    <Input v-model="offer.cta_label" class="h-9 text-sm" placeholder="Claim Offer" />
                                </div>
                                <div class="space-y-1.5 md:col-span-2">
                                    <Label class="text-xs font-semibold">Description</Label>
                                    <Textarea v-model="offer.description" class="h-16 resize-none text-sm" placeholder="Short description of this offer..." />
                                </div>
                                <div class="space-y-1.5 md:col-span-2">
                                    <Label class="text-xs font-semibold">Offer Link URL</Label>
                                    <Input v-model="offer.cta_url" type="url" class="h-9 text-sm" placeholder="https://your-offer-link.com" />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">Display Type</Label>
                                    <select v-model="offer.placement" class="h-9 w-full rounded-md border border-input bg-background px-2.5 text-sm">
                                        <option value="chat">Chat Message</option>
                                        <option value="pinned">Pinned Top of Chat</option>
                                        <option value="popup">Popup in Webinar Room</option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">Time (seconds)</Label>
                                    <Input v-model.number="offer.timing_seconds" type="number" min="0" class="h-9 text-sm" placeholder="30" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Exit-Intent Popup</CardTitle>
                        <CardDescription class="text-xs">
                            Show a final offer popup when the attendee attempts to leave the webinar page.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex w-full items-center justify-between rounded-lg border p-3 text-left">
                            <div>
                                <p class="text-sm font-medium">Enable Exit-Intent Popup</p>
                                <p class="text-xs text-muted-foreground">Triggers when cursor moves to top to close or leave tab.</p>
                            </div>
                            <Switch
                                :model-value="Boolean(settingsForm.exit_popup_enabled)"
                                @update:model-value="
                                    settingsForm.exit_popup_enabled = Boolean($event);
                                    autoSaveSettings(settingsForm.exit_popup_enabled ? 'Exit popup enabled' : 'Exit popup disabled');
                                "
                            />
                        </div>

                        <div v-if="Boolean(settingsForm.exit_popup_enabled)" class="space-y-3">
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">Popup Title</Label>
                                    <Input v-model="settingsForm.exit_popup_title" class="h-9 text-sm" placeholder="Wait! Before You Go..." />
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs font-semibold">CTA Label</Label>
                                    <Input v-model="settingsForm.exit_popup_cta_label" class="h-9 text-sm" placeholder="Claim Offer Now" />
                                </div>
                                <div class="space-y-1.5 md:col-span-2">
                                    <Label class="text-xs font-semibold">Popup Description</Label>
                                    <Textarea v-model="settingsForm.exit_popup_description" class="h-16 resize-none text-sm" placeholder="Claim this special offer before leaving..." />
                                </div>
                                <div class="space-y-1.5 md:col-span-2">
                                    <Label class="text-xs font-semibold">CTA URL</Label>
                                    <Input v-model="settingsForm.exit_popup_cta_url" type="url" class="h-9 text-sm" placeholder="https://your-exit-offer-link.com" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Video End Redirect</CardTitle>
                        <CardDescription class="text-xs">
                            Redirect attendees after the webinar reaches its configured video duration.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="flex w-full items-center justify-between rounded-lg border p-3 text-left">
                            <div>
                                <p class="text-sm font-medium">Enable Redirect After Video Ends</p>
                                <p class="text-xs text-muted-foreground">When enabled, users are redirected as soon as watch time reaches video duration.</p>
                            </div>
                            <Switch
                                :model-value="Boolean(settingsForm.redirect_enabled)"
                                @update:model-value="
                                    settingsForm.redirect_enabled = Boolean($event);
                                    autoSaveSettings(settingsForm.redirect_enabled ? 'Redirect enabled' : 'Redirect disabled');
                                "
                            />
                        </div>

                        <div v-if="Boolean(settingsForm.redirect_enabled)" class="space-y-1.5">
                            <Label class="text-xs font-semibold">Redirect URL</Label>
                            <Input
                                v-model="settingsForm.redirect_url"
                                type="url"
                                class="h-9 text-sm"
                                placeholder="https://your-redirect-page.com"
                            />
                            <p class="text-[0.65rem] text-muted-foreground">This opens automatically at video completion.</p>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="savingSettings || settingsForm.processing"
                        @click="saveSettings"
                    >
                        <Icon v-if="savingSettings" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                        <Icon v-else icon="heroicons:check" class="size-3.5" />
                        {{ savingSettings ? 'Saving…' : 'Save offer settings' }}
                    </Button>
                </div>
            </TabsContent>

            <!-- ── Tab: AI Assistant ── -->
            <TabsContent value="ai-assistant" class="space-y-5">

                <!-- ── Hero toggle card ─────────────────────────────── -->
                <div class="flex items-center justify-between gap-4 rounded-xl border bg-linear-to-r from-violet-50/60 to-indigo-50/60 p-4 dark:from-violet-950/20 dark:to-indigo-950/20">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/40">
                            <Icon icon="heroicons:cpu-chip" class="size-5 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground">Webinar AI Assistant</p>
                            <p class="text-xs text-muted-foreground">Replies to attendee chat using your uploaded knowledge base.</p>
                        </div>
                    </div>
                    <Switch
                        :model-value="Boolean(settingsForm.webinar_ai_enabled)"
                        @update:model-value="
                            settingsForm.webinar_ai_enabled = Boolean($event);
                            autoSaveSettings(settingsForm.webinar_ai_enabled ? 'AI Assistant turned on' : 'AI Assistant turned off');
                        "
                    />
                </div>

                <template v-if="Boolean(settingsForm.webinar_ai_enabled)">

                    <!-- ── Assistant settings row ───────────────────── -->
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Assistant display name</Label>
                            <Input
                                v-model="settingsForm.webinar_ai_assistant_name"
                                class="h-9 text-sm"
                                placeholder="Webinar Assistant"
                            />
                            <p class="text-[0.68rem] text-muted-foreground">Name shown in chat when the AI replies.</p>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3">
                            <div>
                                <p class="text-sm font-medium">Auto-reply</p>
                                <p class="text-[0.7rem] text-muted-foreground leading-relaxed">Automatically respond to every<br>attendee message in real time.</p>
                            </div>
                            <Switch
                                :model-value="Boolean(settingsForm.webinar_ai_auto_reply_enabled)"
                                @update:model-value="
                                    settingsForm.webinar_ai_auto_reply_enabled = Boolean($event);
                                    autoSaveSettings(settingsForm.webinar_ai_auto_reply_enabled ? 'Auto-reply enabled' : 'Auto-reply disabled');
                                "
                            />
                        </div>
                    </div>

                    <!-- ── Knowledge base section ───────────────────── -->
                    <div class="space-y-3">
                        <!-- section header with slot indicators -->
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold">Knowledge Base</p>
                                <p class="text-xs text-muted-foreground">Sources the AI reads when generating replies.</p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span
                                    v-for="i in AI_SOURCE_LIMIT"
                                    :key="i"
                                    class="h-2 w-8 rounded-full transition-colors"
                                    :class="i <= aiSourceCount ? 'bg-violet-500' : 'bg-muted'"
                                />
                                <span class="ml-1 text-xs text-muted-foreground">{{ aiSourceCount }}/{{ AI_SOURCE_LIMIT }}</span>
                            </div>
                        </div>

                        <!-- Add source tabbed card -->
                        <Card class="border shadow-sm overflow-hidden">
                            <!-- tab buttons -->
                            <div class="flex border-b bg-muted/30">
                                <button
                                    v-for="tab in (['url', 'text', 'file'] as const)"
                                    :key="tab"
                                    class="flex flex-1 items-center justify-center gap-1.5 px-3 py-2.5 text-xs font-medium transition-colors"
                                    :class="addSourceTab === tab
                                        ? 'border-b-2 border-violet-500 bg-background text-violet-600 dark:text-violet-400'
                                        : 'text-muted-foreground hover:text-foreground'"
                                    :disabled="aiSourceLimitReached"
                                    @click="addSourceTab = tab"
                                >
                                    <Icon
                                        :icon="tab === 'url' ? 'heroicons:globe-alt' : tab === 'text' ? 'heroicons:document-text' : 'heroicons:paper-clip'"
                                        class="size-3.5"
                                    />
                                    {{ tab === 'url' ? 'Website URL' : tab === 'text' ? 'Paste Text' : 'Upload File' }}
                                </button>
                            </div>

                            <CardContent class="pt-4 pb-4">
                                <!-- limit banner -->
                                <div v-if="aiSourceLimitReached" class="mb-3 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:border-amber-700/40 dark:bg-amber-900/20 dark:text-amber-400">
                                    <Icon icon="heroicons:exclamation-triangle" class="size-3.5 shrink-0" />
                                    Limit reached — delete a source to add a new one.
                                </div>

                                <!-- URL tab -->
                                <div v-if="addSourceTab === 'url'" class="space-y-2.5">
                                    <Input v-model="aiUrlForm.title" class="h-8 text-xs" placeholder="Optional title" />
                                    <Input v-model="aiUrlForm.url" type="url" class="h-8 text-xs" placeholder="https://example.com/page" />
                                    <p class="text-[0.68rem] text-muted-foreground">The page will be scraped and its readable text extracted for AI training.</p>
                                    <Button
                                        size="sm" class="h-8 text-xs gap-1.5 w-full"
                                        :disabled="aiUrlForm.processing || aiSourceLimitReached || !aiUrlForm.url.trim()"
                                        @click="addAiUrlSource"
                                    >
                                        <Icon v-if="aiUrlForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                        <Icon v-else icon="heroicons:globe-alt" class="size-3.5" />
                                        {{ aiUrlForm.processing ? 'Scraping…' : 'Add URL source' }}
                                    </Button>
                                </div>

                                <!-- Text tab -->
                                <div v-else-if="addSourceTab === 'text'" class="space-y-2.5">
                                    <Input v-model="aiTranscriptForm.title" class="h-8 text-xs" placeholder="Optional title" />
                                    <Textarea
                                        v-model="aiTranscriptForm.transcript"
                                        class="min-h-[110px] text-xs resize-y"
                                        placeholder="Paste product info, FAQ, webinar transcript, or any knowledge text…"
                                    />
                                    <Button
                                        size="sm" class="h-8 text-xs gap-1.5 w-full"
                                        :disabled="aiTranscriptForm.processing || aiSourceLimitReached || !aiTranscriptForm.transcript.trim()"
                                        @click="addAiTranscriptSource"
                                    >
                                        <Icon v-if="aiTranscriptForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                        <Icon v-else icon="heroicons:document-text" class="size-3.5" />
                                        {{ aiTranscriptForm.processing ? 'Saving…' : 'Add text source' }}
                                    </Button>
                                </div>

                                <!-- File tab -->
                                <div v-else class="space-y-2.5">
                                    <Input v-model="aiFileForm.title" class="h-8 text-xs" placeholder="Optional title" />
                                    <label class="flex cursor-pointer flex-col items-center gap-2 rounded-lg border-2 border-dashed px-4 py-5 text-center transition-colors hover:border-violet-400 hover:bg-violet-50/40 dark:hover:bg-violet-950/20">
                                        <Icon icon="heroicons:arrow-up-tray" class="size-6 text-muted-foreground" />
                                        <span class="text-xs text-muted-foreground">
                                            <span class="font-medium text-foreground">Click to browse</span> or drag &amp; drop
                                        </span>
                                        <span class="text-[0.65rem] text-muted-foreground">PDF · TXT · MD · CSV · XLSX · DOCX — max 20 MB</span>
                                        <input type="file" class="sr-only" accept=".pdf,.txt,.md,.csv,.xlsx,.xls,.docx" @change="setAiFile" />
                                    </label>
                                    <p v-if="aiFileForm.file" class="flex items-center gap-1.5 text-xs text-foreground">
                                        <Icon icon="heroicons:document" class="size-3.5 text-violet-500" />
                                        {{ aiFileForm.file.name }}
                                    </p>
                                    <Button
                                        size="sm" class="h-8 text-xs gap-1.5 w-full"
                                        :disabled="aiFileForm.processing || aiSourceLimitReached || !aiFileForm.file"
                                        @click="addAiFileSource"
                                    >
                                        <Icon v-if="aiFileForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                        <Icon v-else icon="heroicons:paper-clip" class="size-3.5" />
                                        {{ aiFileForm.processing ? 'Uploading…' : 'Upload file source' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- ── Indexed sources list ───────────────── -->
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">Indexed Sources</p>

                            <div v-if="aiSourceLoading" class="flex items-center gap-2 py-6 text-xs text-muted-foreground">
                                <Icon icon="heroicons:arrow-path" class="size-4 animate-spin" />
                                Loading sources…
                            </div>

                            <div v-else-if="aiSourcesList.length === 0" class="flex flex-col items-center gap-2 rounded-xl border border-dashed py-10 text-center">
                                <Icon icon="heroicons:circle-stack" class="size-8 text-muted-foreground/40" />
                                <p class="text-xs text-muted-foreground">No sources yet. Add one above.</p>
                            </div>

                            <div v-else class="space-y-2">
                                <div
                                    v-for="source in aiSourcesList"
                                    :key="source.id"
                                    class="overflow-hidden rounded-xl border bg-card shadow-sm transition-shadow"
                                >
                                    <!-- source header row -->
                                    <div class="flex items-start gap-3 p-3.5">
                                        <!-- type icon -->
                                        <div class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                                            :class="source.type === 'url' ? 'bg-sky-100 dark:bg-sky-900/30' : source.type === 'text' ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-orange-100 dark:bg-orange-900/30'"
                                        >
                                            <Icon
                                                :icon="source.type === 'url' ? 'heroicons:globe-alt' : source.type === 'text' ? 'heroicons:document-text' : 'heroicons:paper-clip'"
                                                class="size-4"
                                                :class="source.type === 'url' ? 'text-sky-600' : source.type === 'text' ? 'text-emerald-600' : 'text-orange-600'"
                                            />
                                        </div>

                                        <!-- info -->
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <p class="text-sm font-semibold text-foreground truncate">{{ source.title || 'Untitled source' }}</p>
                                                <!-- status badge -->
                                                <span
                                                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-semibold"
                                                    :class="{
                                                        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': source.status === 'queued',
                                                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': source.status === 'processing',
                                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': source.status === 'ready',
                                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': source.status === 'failed',
                                                    }"
                                                >
                                                    <span class="size-1.5 rounded-full"
                                                        :class="{
                                                            'bg-yellow-500': source.status === 'queued',
                                                            'bg-blue-500 animate-pulse': source.status === 'processing',
                                                            'bg-emerald-500': source.status === 'ready',
                                                            'bg-red-500': source.status === 'failed',
                                                        }"
                                                    />
                                                    {{ source.status }}
                                                </span>
                                                <!-- chunk count pill -->
                                                <span v-if="source.chunk_count > 0" class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[0.65rem] font-semibold text-violet-700 dark:bg-violet-900/30 dark:text-violet-400">
                                                    <Icon icon="heroicons:square-3-stack-3d" class="size-3" />
                                                    {{ source.chunk_count }} chunks
                                                </span>
                                            </div>
                                            <p v-if="source.source_url" class="mt-0.5 text-[0.68rem] text-muted-foreground truncate">{{ source.source_url }}</p>
                                            <p v-if="source.error_message" class="mt-0.5 text-[0.68rem] text-destructive">{{ source.error_message }}</p>
                                        </div>

                                        <!-- actions -->
                                        <div class="flex shrink-0 items-center gap-1">
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger as-child>
                                                        <Button
                                                            v-if="source.chunk_count > 0"
                                                            variant="ghost" size="sm"
                                                            class="h-7 w-7 p-0 text-muted-foreground hover:text-violet-600"
                                                            @click="toggleSourceChunks(source)"
                                                        >
                                                            <Icon
                                                                :icon="expandedSourceIds.has(source.id) ? 'heroicons:chevron-up' : 'heroicons:eye'"
                                                                class="size-3.5"
                                                            />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent side="top" class="text-xs">
                                                        {{ expandedSourceIds.has(source.id) ? 'Hide chunks' : 'Preview chunks' }}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                            <Button variant="ghost" size="sm" class="h-7 w-7 p-0 text-muted-foreground hover:text-destructive" @click="deleteAiSource(source)">
                                                <Icon icon="heroicons:trash" class="size-3.5" />
                                            </Button>
                                        </div>
                                    </div>

                                    <!-- chunk preview panel -->
                                    <div v-if="expandedSourceIds.has(source.id)" class="border-t bg-muted/20 px-3.5 py-3">
                                        <div v-if="sourceChunksLoading[source.id]" class="flex items-center gap-2 text-xs text-muted-foreground py-2">
                                            <Icon icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                            Loading chunks…
                                        </div>
                                        <div v-else-if="!sourceChunks[source.id] || sourceChunks[source.id].length === 0" class="py-2 text-xs text-muted-foreground">
                                            No chunks available.
                                        </div>
                                        <div v-else class="space-y-2">
                                            <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground mb-2">
                                                Showing {{ sourceChunks[source.id].length }} of {{ source.chunk_count }} chunks
                                            </p>
                                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                                <div
                                                    v-for="chunk in sourceChunks[source.id]"
                                                    :key="chunk.id"
                                                    class="group relative flex flex-col gap-1.5 rounded-lg border bg-background p-2.5 text-xs shadow-sm"
                                                >
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded bg-violet-100 px-1 text-[0.6rem] font-bold text-violet-700 dark:bg-violet-900/40 dark:text-violet-400">
                                                            #{{ chunk.chunk_index }}
                                                        </span>
                                                        <span class="text-[0.65rem] text-muted-foreground">chunk</span>
                                                    </div>
                                                    <p class="line-clamp-4 text-[0.72rem] leading-relaxed text-foreground/80">{{ chunk.content }}</p>
                                                    <div class="absolute inset-0 rounded-lg ring-1 ring-transparent transition group-hover:ring-violet-300 dark:group-hover:ring-violet-700" />
                                                </div>
                                            </div>
                                            <p v-if="source.chunk_count > (sourceChunks[source.id]?.length ?? 0)" class="text-[0.65rem] text-muted-foreground">
                                                + {{ source.chunk_count - (sourceChunks[source.id]?.length ?? 0) }} more chunks stored (showing first 20)
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </template>

                <!-- ── Save button ──────────────────────────────────── -->
                <div class="flex justify-end pt-1">
                    <Button
                        size="sm"
                        class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        :disabled="savingSettings || settingsForm.processing"
                        @click="saveSettings"
                    >
                        <Icon v-if="savingSettings" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                        <Icon v-else icon="heroicons:check" class="size-3.5" />
                        {{ savingSettings ? 'Saving…' : 'Save AI settings' }}
                    </Button>
                </div>
            </TabsContent>

            <!-- ── Tab: Integrations ── -->
            <TabsContent value="integrations" class="space-y-4">
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">ESP Integrations</CardTitle>
                        <CardDescription class="text-xs">
                            Connect an email service provider — leads will be auto-subscribed when they register.
                            <Link href="/integrations" class="text-primary underline ml-1">Add more accounts →</Link>
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="integrationAccounts.length === 0" class="flex flex-col items-center py-10 gap-3 text-muted-foreground">
                            <Icon icon="heroicons:puzzle-piece" class="size-10 opacity-30" />
                            <p class="text-sm">No integration accounts yet.</p>
                            <Button as-child size="sm" variant="outline">
                                <Link href="/integrations">Connect an ESP</Link>
                            </Button>
                        </div>

                        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="account in integrationAccounts"
                                :key="account.id"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition-colors"
                                :class="settingsForm.integration_account_ids.includes(account.id)
                                    ? 'border-primary bg-primary/5'
                                    : 'hover:border-border/80'"
                            >
                                <input
                                    v-model="settingsForm.integration_account_ids"
                                    type="checkbox"
                                    class="sr-only"
                                    :value="account.id"
                                />
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <Icon :icon="providerIcon(account.provider)" class="size-5" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-foreground truncate">{{ account.name }}</p>
                                    <p class="text-xs text-muted-foreground capitalize">{{ account.provider }}</p>
                                </div>
                                <Icon
                                    v-if="settingsForm.integration_account_ids.includes(account.id)"
                                    icon="heroicons:check-circle"
                                    class="size-5 shrink-0 text-primary"
                                />
                                <Icon
                                    v-else
                                    icon="heroicons:plus-circle"
                                    class="size-5 shrink-0 text-muted-foreground/50"
                                />
                            </label>
                        </div>

                        <div v-if="integrationAccounts.length > 0" class="flex justify-end mt-4">
                            <Button
                                size="sm"
                                class="gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="savingSettings || settingsForm.processing"
                                @click="saveSettings"
                            >
                                <Icon icon="heroicons:check" class="size-3.5" />
                                Save integrations
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- ── Tab: Share Links ── -->
            <TabsContent value="links" class="space-y-4">
                <!-- Status banner -->
                <div
                    v-if="funnel.status !== 'published'"
                    class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4"
                >
                    <Icon icon="heroicons:exclamation-triangle" class="size-5 shrink-0 text-amber-600 mt-0.5" />
                    <div class="text-sm">
                        <p class="font-semibold text-amber-800">Funnel is not published yet</p>
                        <p class="text-amber-700 text-xs mt-0.5">These links won't be publicly accessible until you publish the funnel.</p>
                    </div>
                    <Button
                        size="sm"
                        class="shrink-0 ml-auto h-7 text-xs bg-amber-600 text-white hover:bg-amber-700"
                        :disabled="publishing"
                        @click="publish"
                    >
                        Publish now
                    </Button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Opt-in link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                                    <Icon icon="heroicons:cursor-arrow-ripple" class="size-4 text-primary" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Opt-in Page</CardTitle>
                                    <CardDescription class="text-xs">Share this to collect registrations</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.optin }}</p>
                                <a
                                    :href="publicLinks.optin"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('optin')"
                            >
                                <Icon
                                    :icon="copiedLink === 'optin' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'optin' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'optin' ? 'Copied!' : 'Copy opt-in link' }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Webinar link -->
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-lg" style="background:rgba(255,173,0,0.1)">
                                    <Icon icon="heroicons:video-camera" class="size-4" style="color:#FFAD00" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-semibold">Webinar Room</CardTitle>
                                    <CardDescription class="text-xs">Direct link to the webinar room</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex rounded-lg border bg-muted/40 overflow-hidden">
                                <p class="flex-1 truncate px-3 py-2 text-xs text-muted-foreground">{{ publicLinks.webinar }}</p>
                                <a
                                    :href="publicLinks.webinar"
                                    target="_blank"
                                    class="flex items-center border-l px-2 text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                    title="Open in new tab"
                                >
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="w-full gap-1.5 text-xs h-8"
                                @click="copyLink('webinar')"
                            >
                                <Icon
                                    :icon="copiedLink === 'webinar' ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                    class="size-3.5"
                                    :class="copiedLink === 'webinar' ? 'text-emerald-600' : ''"
                                />
                                {{ copiedLink === 'webinar' ? 'Copied!' : 'Copy webinar link' }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <!-- ── Tab: Chat Threads ── -->
            <TabsContent value="chat" class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Attendee Conversations</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">{{ conversationSummaries.length }} thread{{ conversationSummaries.length !== 1 ? 's' : '' }} so far</p>
                    </div>
                    <Button as-child size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90">
                        <a :href="`/funnels/${funnel.id}/chat`">
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                            Open Chat Manager
                        </a>
                    </Button>
                </div>

                <div v-if="conversationSummaries.length === 0" class="flex flex-col items-center rounded-xl border border-dashed py-14 gap-3 text-muted-foreground">
                    <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-10 opacity-30" />
                    <p class="text-sm">No conversations yet. Publish your funnel and share the webinar link.</p>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <a
                        v-for="thread in conversationSummaries"
                        :key="thread.conversation_key"
                        :href="`/funnels/${funnel.id}/chat`"
                        class="flex items-start gap-3 rounded-xl border p-3.5 hover:border-primary/30 hover:bg-muted/30 transition-colors"
                    >
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <span class="text-xs font-bold text-primary">{{ thread.attendee_name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground">{{ thread.attendee_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ thread.attendee_email ?? 'Anonymous' }}</p>
                            <p class="text-xs text-muted-foreground truncate mt-0.5 italic">{{ thread.latest_message ?? 'No messages yet' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-muted px-1.5 py-0.5 text-[0.6rem] font-medium text-muted-foreground">
                            {{ thread.message_count }}
                        </span>
                    </a>
                </div>
            </TabsContent>

            <!-- ── Tab: Traffic Settings ── -->
            <TabsContent value="traffic" class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">Funnel Traffic Settings</h3>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            Track mentions and conversations per funnel keyword.
                        </p>
                    </div>
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                        @click="openTrafficKeywordModal"
                    >
                        <Icon icon="heroicons:plus" class="size-3.5" />
                        Add Keyword
                    </Button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Total Mentions</p><p class="text-2xl font-bold mt-1">{{ props.traffic.stats.total.toLocaleString() }}</p></CardContent></Card>
                    <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">This Week</p><p class="text-2xl font-bold mt-1 text-[#40E0D0]">{{ props.traffic.stats.this_week.toLocaleString() }}</p></CardContent></Card>
                    <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Keywords</p><p class="text-2xl font-bold mt-1 text-[#FFAD00]">{{ props.traffic.stats.keywords_count }}</p></CardContent></Card>
                    <Card class="border shadow-sm"><CardContent class="p-4"><p class="text-xs text-muted-foreground">Platforms</p><p class="text-2xl font-bold mt-1 text-[#a78bfa]">{{ Object.keys(props.traffic.stats.platforms ?? {}).length }}</p></CardContent></Card>
                </div>

                <Card class="border shadow-sm border-dashed border-primary/25 overflow-hidden">
                    <Collapsible v-model:open="trafficAiSectionOpen">
                        <CardHeader class="space-y-0 pb-3 pt-4 px-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <CollapsibleTrigger
                                    type="button"
                                    class="flex flex-1 min-w-0 items-start gap-2 rounded-lg border border-transparent px-1 py-0.5 text-left outline-none ring-offset-background transition-colors hover:border-border hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <Icon
                                        icon="heroicons:chevron-right"
                                        class="size-5 shrink-0 text-muted-foreground transition-transform duration-200"
                                        :class="{ 'rotate-90': trafficAiSectionOpen }"
                                    />
                                    <div class="min-w-0 space-y-0.5">
                                        <CardTitle class="text-sm font-semibold leading-tight">AI traffic auto-reply</CardTitle>
                                        <p class="text-[0.65rem] text-muted-foreground leading-snug">
                                            <span v-if="!trafficAiSectionOpen">Collapsed — expand to enable auto-replies and set your link / tone.</span>
                                            <span v-else>Uses your connected Social posting accounts (one per platform).</span>
                                        </p>
                                    </div>
                                </CollapsibleTrigger>
                                <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                                    <Button size="sm" variant="outline" class="h-9 text-xs gap-1.5" as-child>
                                        <Link href="/settings/social-traffic">
                                            <Icon icon="heroicons:link-20-solid" class="size-3.5" />
                                            Connect accounts
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CollapsibleContent>
                            <CardContent class="space-y-3 border-t border-border/60 px-4 pb-4 pt-3">
                                
                                <div class="flex flex-wrap gap-2">
                                    <Button size="sm" class="h-9 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90" as-child>
                                        <Link href="/settings/social-traffic">
                                            <Icon icon="simple-icons:reddit" class="size-3.5" />
                                            Go to Social posting settings
                                        </Link>
                                    </Button>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <Label class="text-xs">Enable auto-replies for this funnel</Label>
                                    <Switch
                                        :checked="settingsForm.traffic_ai_reply_enabled"
                                        :disabled="savingSettings || settingsForm.processing"
                                        @update:checked="saveTrafficAiReplyEnabled($event)"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Link override (optional)</Label>
                                    <Input v-model="settingsForm.traffic_ai_link_override" type="url" class="h-9 text-xs" placeholder="Else: affiliate → offer → webinar CTA" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">Extra instructions for the model</Label>
                                    <Textarea v-model="settingsForm.traffic_ai_extra_context" class="min-h-[72px] text-xs resize-y" placeholder="Tone, product angle, compliance notes…" />
                                </div>
                                <div class="space-y-2">
                                    <Label class="text-xs">Posting accounts (from Settings → Social posting)</Label>
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <div
                                            v-for="p in trafficReplyPlatforms"
                                            :key="p.key"
                                            class="rounded-lg border border-border/80 bg-muted/20 px-3 py-2.5"
                                            :class="trafficAccountForPlatform(p.key) ? 'border-green-500/40' : ''"
                                        >
                                            <div class="flex items-center gap-2">
                                                <Icon :icon="p.icon" class="size-4 shrink-0" :style="{ color: p.color }" />
                                                <span class="text-xs font-medium">{{ p.label }}</span>
                                            </div>
                                            <p v-if="trafficAccountForPlatform(p.key)" class="mt-1.5 text-[0.65rem] text-green-600 dark:text-green-400">
                                                Connected · {{ trafficAccountForPlatform(p.key)?.platform_username || 'account linked' }}
                                            </p>
                                            <p v-if="trafficAccountForPlatform(p.key)" class="text-[0.65rem] text-muted-foreground">
                                                {{ trafficRepliesPostedToday(p.key) }} / {{ trafficMaxRepliesPerDay }} replies sent today
                                            </p>
                                            <p v-else class="mt-1.5 text-[0.65rem] text-muted-foreground">
                                                Not connected —
                                                <Link href="/settings/social-traffic" class="underline hover:text-foreground">connect</Link>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    size="sm"
                                    class="h-9 text-xs bg-primary text-primary-foreground hover:opacity-90"
                                    :disabled="savingSettings || settingsForm.processing"
                                    @click="autoAssignTrafficAccounts(); saveSettings();"
                                >
                                    Save auto-reply settings
                                </Button>
                            </CardContent>
                        </CollapsibleContent>
                    </Collapsible>
                </Card>

                <Dialog v-model:open="trafficKeywordModalOpen">
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Track a new traffic keyword</DialogTitle>
                            <DialogDescription>
                                Search Reddit, YouTube, X, and news for this term and attach mentions to this funnel.
                            </DialogDescription>
                        </DialogHeader>
                        <form id="traffic-keyword-form" class="flex flex-col gap-4" @submit.prevent="submitTrafficKeyword">
                            <div class="space-y-2">
                                <Label for="traffic-keyword-name" class="text-xs">Keyword</Label>
                                <Input
                                    id="traffic-keyword-name"
                                    v-model="trafficKeywordForm.name"
                                    placeholder="e.g. your brand name…"
                                    class="h-9 text-sm"
                                    autocomplete="off"
                                />
                                <p v-if="trafficKeywordForm.errors.name" class="text-xs text-destructive">
                                    {{ trafficKeywordForm.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs">Platforms</Label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button
                                        v-for="p in PLATFORM_OPTIONS"
                                        :key="p"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border text-xs font-medium transition-colors"
                                        :class="trafficKeywordForm.platforms.includes(p) ? 'bg-primary/15 border-primary/40 text-primary' : 'bg-muted/30 border-border text-muted-foreground'"
                                        @click="toggleTrafficPlatform(p)"
                                    >
                                        <Icon :icon="trafficPlatformMeta(p).icon" class="size-3" />
                                        {{ p }}
                                    </button>
                                </div>
                            </div>
                        </form>
                        <DialogFooter class="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" size="sm" class="h-9" @click="closeTrafficKeywordModal">
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                form="traffic-keyword-form"
                                size="sm"
                                class="h-9 bg-primary text-primary-foreground hover:opacity-90"
                                :disabled="trafficKeywordForm.processing || !trafficKeywordForm.name.trim()"
                            >
                                Add & Fetch
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-4 items-start">
                    <Card class="border shadow-sm">
                        <CardHeader class="pb-2 pt-4 px-4">
                            <CardTitle class="text-sm font-semibold">Tracked Keywords ({{ props.traffic.keywords.length }})</CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <div v-if="props.traffic.keywords.length === 0" class="py-8 text-center text-xs text-muted-foreground">No keywords yet.</div>
                            <ul v-else class="divide-y divide-border">
                                <li v-for="kw in props.traffic.keywords" :key="kw.id" class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <button class="text-sm font-medium truncate max-w-full text-left" :class="{ 'opacity-50 line-through': !kw.is_active }" @click="trafficKeywordId = trafficKeywordId == kw.id ? '' : kw.id">{{ kw.name }}</button>
                                            <p class="text-xs text-muted-foreground mt-0.5">{{ kw.mentions_count }} mentions</p>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <button class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/50" title="Fetch now" @click="fetchTrafficKeywordNow(kw)"><Icon icon="heroicons:arrow-path" class="size-3.5" /></button>
                                            <button class="flex size-7 items-center justify-center rounded-md" :class="kw.is_active ? 'text-[#40E0D0]' : 'text-muted-foreground'" @click="toggleTrafficKeywordActive(kw)"><Icon :icon="kw.is_active ? 'heroicons:pause' : 'heroicons:play'" class="size-3.5" /></button>
                                            <button class="flex size-7 items-center justify-center rounded-md" :class="kw.email_notifications ? 'text-[#FFAD00]' : 'text-muted-foreground'" @click="toggleTrafficKeywordNotifications(kw)"><Icon :icon="kw.email_notifications ? 'heroicons:bell' : 'heroicons:bell-slash'" class="size-3.5" /></button>
                                            <button class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:text-destructive" @click="deleteTrafficKeyword(kw)"><Icon icon="heroicons:trash" class="size-3.5" /></button>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <div class="space-y-3">
                        <Card class="border shadow-sm">
                            <CardContent class="p-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <button
                                        v-for="tab in trafficPlatformTabs"
                                        :key="tab.key"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-md text-xs font-medium transition-colors"
                                        :class="trafficPlatform === tab.key ? 'bg-primary/15 text-primary border border-primary/30' : 'text-muted-foreground hover:bg-muted/50 border border-transparent'"
                                        @click="trafficPlatform = tab.key"
                                    >
                                        <Icon v-if="tab.key" :icon="trafficPlatformMeta(tab.key).icon" class="size-3" />
                                        <Icon v-else icon="heroicons:squares-2x2" class="size-3" />
                                        {{ tab.label }}
                                        <span class="text-[0.6rem] text-muted-foreground">{{ tab.count }}</span>
                                    </button>
                                </div>
                                <div class="relative shrink-0">
                                    <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                                    <Input v-model="trafficSearch" placeholder="Search mentions…" class="h-8 pl-8 text-xs w-full sm:w-52" />
                                </div>
                            </CardContent>
                        </Card>

                        <Card v-if="props.traffic.mentions.total === 0" class="border shadow-sm">
                            <CardContent class="py-10 text-center text-sm text-muted-foreground">No traffic mentions found for this funnel.</CardContent>
                        </Card>

                        <div v-else class="flex flex-col gap-3">
                            <Card v-for="mention in props.traffic.mentions.data" :key="mention.id" class="border shadow-sm">
                                <CardContent class="p-4">
                                    <div class="flex items-start gap-3">
                                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg mt-0.5" :style="{ background: trafficPlatformMeta(mention.source_type).bg }">
                                            <Icon :icon="trafficPlatformMeta(mention.source_type).icon" class="size-4.5" :style="{ color: trafficPlatformMeta(mention.source_type).color }" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between gap-2 mb-1">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <Badge class="text-[0.6rem] px-1.5 py-0 border font-semibold">{{ mention.source_type }}</Badge>
                                                    <span v-if="mention.keyword" class="text-[0.65rem] text-muted-foreground truncate">#{{ mention.keyword.name }}</span>
                                                </div>
                                                <span class="text-[0.65rem] text-muted-foreground">{{ fmtTrafficDate(mention.posted_at) }}</span>
                                            </div>
                                            <p v-if="mention.title" class="text-sm font-semibold leading-snug mb-1">{{ trunc(mention.title, 140) }}</p>
                                            <p v-if="mention.content && mention.content !== mention.title" class="text-xs text-muted-foreground leading-relaxed">{{ trunc(mention.content, 220) }}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <div
                                v-if="props.traffic.mentions.last_page > 1"
                                class="flex flex-col gap-2 rounded-lg border bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <p class="text-xs text-muted-foreground">
                                    <template v-if="props.traffic.mentions.from && props.traffic.mentions.to">
                                        {{ props.traffic.mentions.from }}–{{ props.traffic.mentions.to }} of
                                    </template>
                                    {{ props.traffic.mentions.total.toLocaleString() }} mentions
                                    <span class="text-muted-foreground/80">(page {{ props.traffic.mentions.current_page }} / {{ props.traffic.mentions.last_page }})</span>
                                </p>
                                <div class="flex flex-wrap items-center gap-1">
                                    <button
                                        v-for="link in props.traffic.mentions.links"
                                        :key="`${link.label}-${link.url ?? 'disabled'}`"
                                        type="button"
                                        :disabled="!link.url"
                                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                        :class="link.active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-background text-foreground hover:bg-muted'"
                                        @click="goToTrafficMentionsPage(link.url)"
                                        v-html="link.label"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </TabsContent>

        </Tabs>

        <!-- ── Share modal ──────────────────────────────────────── -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="shareModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="shareModalOpen = false" />

                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0 scale-95 translate-y-1"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                    appear
                >
                    <div class="relative z-10 w-full max-w-md rounded-2xl border bg-card p-5 shadow-2xl">

                        <!-- modal header -->
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <p class="text-[0.65rem] font-semibold uppercase tracking-widest text-muted-foreground">Funnel · {{ funnel.name }}</p>
                                <h3 class="text-base font-bold text-foreground mt-0.5">Share your funnel</h3>
                            </div>
                            <button class="flex size-7 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground" @click="shareModalOpen = false">
                                <Icon icon="heroicons:x-mark" class="size-4" />
                            </button>
                        </div>

                        <!-- link picker tabs -->
                        <div class="mb-4 flex gap-1 rounded-lg border bg-muted/40 p-1">
                            <button
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-md py-1.5 text-xs font-medium transition"
                                :class="shareActiveLink === 'webinar'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'"
                                @click="shareActiveLink = 'webinar'"
                            >
                                <Icon icon="heroicons:video-camera" class="size-3.5" />
                                Webinar page
                            </button>
                            <button
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-md py-1.5 text-xs font-medium transition"
                                :class="shareActiveLink === 'optin'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'"
                                @click="shareActiveLink = 'optin'"
                            >
                                <Icon icon="heroicons:cursor-arrow-ripple" class="size-3.5" />
                                Opt-in page
                            </button>
                        </div>

                        <!-- platform grid -->
                        <div class="grid grid-cols-3 gap-2 mb-4">
                            <button
                                v-for="p in [
                                    { key: 'facebook', label: 'Facebook',  icon: 'simple-icons:facebook', color: '#4a90e2',  bg: 'rgba(24,119,242,0.1)'  },
                                    { key: 'x',        label: 'X',         icon: 'simple-icons:x',        color: '#e2e8f0',  bg: 'rgba(255,255,255,0.07)' },
                                    { key: 'whatsapp', label: 'WhatsApp',  icon: 'simple-icons:whatsapp', color: '#25d366',  bg: 'rgba(37,211,102,0.1)'  },
                                    { key: 'linkedin', label: 'LinkedIn',  icon: 'simple-icons:linkedin', color: '#0a66c2',  bg: 'rgba(10,102,194,0.1)'  },
                                    { key: 'telegram', label: 'Telegram',  icon: 'simple-icons:telegram', color: '#0088cc',  bg: 'rgba(0,136,204,0.1)'   },
                                    { key: 'email',    label: 'Email',     icon: 'heroicons:envelope',    color: 'hsl(var(--primary))', bg: 'hsl(var(--muted))' },
                                ]"
                                :key="p.key"
                                class="flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 text-xs font-semibold transition hover:scale-105 active:scale-100"
                                :style="`background:${p.bg};color:${p.color};border-color:${p.bg};`"
                                @click="openSharePlatform(p.key)"
                            >
                                <Icon :icon="p.icon" class="size-5" />
                                {{ p.label }}
                            </button>
                        </div>

                        <!-- copy link row -->
                        <div class="flex items-center gap-2 rounded-xl border bg-muted/30 px-3 py-2.5">
                            <Icon icon="heroicons:link" class="size-3.5 shrink-0 text-muted-foreground" />
                            <span class="flex-1 truncate text-xs text-muted-foreground">{{ shareCurrentUrl }}</span>
                            <button
                                class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                :class="shareLinkCopied
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-primary/10 text-primary hover:bg-primary/20'"
                                @click="copyShareLink"
                            >
                                <span v-if="shareLinkCopied" class="flex items-center gap-1">
                                    <Icon icon="heroicons:check" class="size-3" /> Copied!
                                </span>
                                <span v-else>Copy link</span>
                            </button>
                        </div>

                    </div>
                </Transition>
            </div>
        </Transition>

    </div>
</template>
