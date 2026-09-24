<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
} from '@/components/ui/select';
import {
    promotionPlatformIcon,
    promotionPlatformLabel,
} from '@/lib/promotionPlatforms';

type PlanItem = {
    id: number;
    format_key: string;
    platform: string;
    topic: string;
    angle: string | null;
    scheduled_for: string | null;
    status: string;
    format_label: string;
    promotion_post_id: number | null;
};

type Plan = {
    id: number;
    uuid: string;
    week_start: string;
    status: string;
    source?: string;
    meta?: {
        auto_select_formats?: boolean;
        topic_source?: string;
        topic_source_label?: string;
        has_campaign_knowledge?: boolean;
        planning_brief?: string | null;
    };
    campaign: { id: number; name: string } | null;
    items: PlanItem[];
};

type CampaignOption = {
    id: number;
    name: string;
    type: string;
    status: string;
    product_name: string | null;
    has_knowledge: boolean;
};

const props = defineProps<{
    week_start: string;
    campaigns: CampaignOption[];
    plans: Plan[];
    profile: {
        profile: { intensity: string; enabled_formats: string[]; auto_select_formats: boolean; plan_platforms: string[]; planning_brief?: string };
        catalog: Array<{ key: string; label: string; platform_label: string; platform: string }>;
        connected_accounts: Array<{ platform: string; username: string | null }>;
        platforms: Array<{ key: string; label: string }>;
    };
    format_count: number;
    routes: {
        generate_plan: string;
        traffic_setup: string;
        social_settings: string;
        profile_update: string;
    };
}>();

/** Standalone is the default — users opt in to campaign-backed planning. */
const contentSource = ref<'standalone' | 'campaign'>('standalone');
const selectedCampaignId = ref<string>(props.campaigns[0] ? String(props.campaigns[0].id) : '');
const planningBrief = ref(props.profile.profile.planning_brief ?? '');
const savingBrief = ref(false);
const autoSelectFormats = ref(props.profile.profile.auto_select_formats ?? false);
const planPlatforms = ref<string[]>([...props.profile.profile.plan_platforms]);
const savingAutoPreference = ref(false);
const savingPlatforms = ref(false);
const generating = ref(false);
const executingPlanId = ref<number | null>(null);

watch(contentSource, (mode) => {
    if (mode === 'campaign' && !selectedCampaignId.value && props.campaigns[0]) {
        selectedCampaignId.value = String(props.campaigns[0].id);
    }
});

const connectedAccounts = computed(() => props.profile.connected_accounts ?? []);
const hasConnectedAccounts = computed(() => connectedAccounts.value.length > 0);

const selectedCampaign = computed(() =>
    props.campaigns.find((c) => String(c.id) === selectedCampaignId.value) ?? null,
);

const contextHint = computed(() => {
    if (contentSource.value === 'standalone') {
        const hasBrief = planningBrief.value.trim().length >= 20;
        return {
            tone: hasBrief ? 'teal' as const : 'amber' as const,
            icon: hasBrief ? 'heroicons:document-text' : 'heroicons:pencil-square',
            title: hasBrief ? 'Your brief drives planning' : 'Add a content brief',
            text: hasBrief
                ? 'Topics and post copy are built from your brief below — niche, audience, offer, hooks, or one topic per line.'
                : 'Standalone needs your context. Describe what you sell, who you help, and the angles you want — otherwise topics stay generic.',
        };
    }

    const campaign = selectedCampaign.value;
    if (!campaign) {
        return {
            tone: 'amber' as const,
            icon: 'heroicons:exclamation-triangle',
            title: 'Pick a campaign',
            text: 'Select a campaign below to pull topics from its offer and knowledge base.',
        };
    }

    if (campaign.has_knowledge) {
        return {
            tone: 'teal' as const,
            icon: 'heroicons:book-open',
            title: 'Campaign knowledge connected',
            text: `Topics come from "${truncateText(campaign.name, 40)}" KB. Add optional guidance below to steer angles. Generation uses KB + your notes.`,
        };
    }

    return {
        tone: 'amber' as const,
        icon: 'heroicons:information-circle',
        title: 'Limited campaign context',
        text: `"${truncateText(campaign.name, 40)}" has no KB yet. Add a brief below or run the campaign wizard — otherwise topics fall back to templates.`,
    };
});

function truncateText(text: string, max = 52): string {
    const trimmed = text.trim();
    if (trimmed.length <= max) return trimmed;
    return `${trimmed.slice(0, max - 1).trimEnd()}…`;
}

function campaignFullLabel(c: CampaignOption): string {
    const name = c.name.trim();
    const product = (c.product_name ?? '').trim();
    if (!product || product === name) return name;

    const nameLower = name.toLowerCase();
    const productLower = product.toLowerCase();
    if (nameLower.includes(productLower) || productLower.includes(nameLower.slice(0, Math.min(40, nameLower.length)))) {
        return name;
    }

    return `${name} · ${product}`;
}

function campaignDisplayName(c: CampaignOption, max = 48): string {
    return truncateText(campaignFullLabel(c), max);
}

const selectedCampaignDisplay = computed(() => {
    const campaign = selectedCampaign.value;
    if (!campaign) return 'Select a campaign…';
    return campaignDisplayName(campaign, 56);
});

function planTopicSourceLabel(plan: Plan): string | null {
    return plan.meta?.topic_source_label ?? null;
}

function isPlanPlatformSelected(platform: string): boolean {
    return planPlatforms.value.includes(platform);
}

function togglePlanPlatform(platform: string): void {
    if (!connectedAccounts.value.some((a) => a.platform === platform)) return;

    if (isPlanPlatformSelected(platform)) {
        if (planPlatforms.value.length <= 1) {
            toast.error('Keep at least one platform selected for planning.');
            return;
        }
        planPlatforms.value = planPlatforms.value.filter((p) => p !== platform);
    } else {
        planPlatforms.value = [...planPlatforms.value, platform];
    }

    savingPlatforms.value = true;
    router.patch(
        props.routes.profile_update,
        { plan_platforms: planPlatforms.value },
        {
            preserveScroll: true,
            onFinish: () => { savingPlatforms.value = false; },
        },
    );
}

const formatLabel = (key: string): string =>
    props.profile.catalog.find((f) => f.key === key)?.label ?? key;

function savePlanningBrief(): void {
    savingBrief.value = true;
    router.patch(
        props.routes.profile_update,
        { planning_brief: planningBrief.value },
        {
            preserveScroll: true,
            onFinish: () => { savingBrief.value = false; },
        },
    );
}

function generatePlan(): void {
    if (!hasConnectedAccounts.value) {
        toast.error('Connect at least one social account before planning.');
        return;
    }
    if (planPlatforms.value.length === 0) {
        toast.error('Select at least one platform to plan for.');
        return;
    }
    if (contentSource.value === 'campaign' && !selectedCampaignId.value) {
        toast.error('Select a campaign or switch to Standalone mode.');
        return;
    }
    if (contentSource.value === 'standalone' && planningBrief.value.trim().length < 20) {
        toast.error('Add a content brief (at least a few sentences) so we know what to plan around.');
        return;
    }

    generating.value = true;
    router.post(
        props.routes.generate_plan,
        {
            campaign_id: contentSource.value === 'campaign' ? Number(selectedCampaignId.value) : null,
            week_start: props.week_start,
            auto_select_formats: autoSelectFormats.value,
            save_auto_preference: true,
            plan_platforms: planPlatforms.value,
            planning_brief: planningBrief.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Weekly plan generated.'),
            onError: (errors) => {
                const msg = (errors as Record<string, string>).plan
                    ?? (errors as Record<string, string>).planning_brief;
                if (msg) toast.error(msg);
            },
            onFinish: () => { generating.value = false; },
        },
    );
}

function toggleAutoFormats(): void {
    autoSelectFormats.value = !autoSelectFormats.value;
    savingAutoPreference.value = true;
    router.patch(
        props.routes.profile_update,
        { auto_select_formats: autoSelectFormats.value },
        {
            preserveScroll: true,
            onFinish: () => { savingAutoPreference.value = false; },
        },
    );
}

function approvePlan(plan: Plan): void {
    router.post(`/growth/content-employee/plans/${plan.id}/approve`, {}, { preserveScroll: true });
}

function executePlan(plan: Plan): void {
    executingPlanId.value = plan.id;
    router.post(
        `/growth/content-employee/plans/${plan.id}/execute`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Posts created — generation queued in background.'),
            onError: (errors) => {
                const msg = (errors as Record<string, string>).plan;
                if (msg) toast.error(msg);
            },
            onFinish: () => { executingPlanId.value = null; },
        },
    );
}

function deletePlan(plan: Plan): void {
    if (!confirm('Delete this plan?')) return;
    router.delete(`/growth/content-employee/plans/${plan.id}`, { preserveScroll: true });
}

function statusBadgeClass(status: string): string {
    if (status === 'completed' || status === 'approved') return 'border-blue-200 bg-blue-50 text-blue-700';
    if (status === 'executing') return 'border-indigo-200 bg-indigo-50 text-indigo-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
}

function dayLabel(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function contextHintClasses(tone: 'teal' | 'amber' | 'slate'): string {
    if (tone === 'teal') return 'border-blue-200 bg-blue-50/80 text-blue-900';
    if (tone === 'amber') return 'border-amber-200 bg-amber-50/80 text-amber-900';
    return 'border-slate-200 bg-slate-50/80 text-slate-800';
}
</script>

<template>
    <Head title="Content Employee" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-4 p-3 md:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight md:text-2xl">AI Content Employee</h1>
                <p class="mt-0.5 max-w-2xl text-sm text-muted-foreground">
                    Plans your week from Traffic setup + campaign knowledge — {{ format_count }} formats across all platforms.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="brand-outline" size="sm">
                    <Link :href="routes.traffic_setup">
                        <Icon icon="heroicons:cog-6-tooth" class="size-3.5" />
                        Traffic setup
                    </Link>
                </Button>
                <Button as-child variant="brand-outline" size="sm">
                    <Link :href="routes.social_settings">Connect accounts</Link>
                </Button>
            </div>
        </div>

        <!-- Profile summary -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                <p class="text-xs text-muted-foreground">Intensity</p>
                <p class="text-lg font-semibold capitalize">{{ profile.profile.intensity }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                <p class="text-xs text-muted-foreground">Format mode</p>
                <p class="text-lg font-semibold">{{ autoSelectFormats ? 'Auto-pick' : 'Traffic setup' }}</p>
            </div>
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                <p class="text-xs text-muted-foreground">Week of</p>
                <p class="text-lg font-semibold">{{ week_start }}</p>
            </div>
        </div>

        <!-- Connected accounts + plan scope -->
        <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm space-y-3">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold">Plan for platforms</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        Only connected accounts are planned — no wasted posts for platforms you cannot publish to.
                    </p>
                </div>
                <Button as-child variant="brand-outline" size="sm">
                    <Link :href="routes.social_settings">Connect accounts</Link>
                </Button>
            </div>

            <div
                v-if="!hasConnectedAccounts"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                No social accounts connected yet. Connect at least one account before planning or creating posts.
            </div>

            <div v-else class="flex flex-wrap gap-2">
                <button
                    v-for="account in connectedAccounts"
                    :key="account.platform"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="isPlanPlatformSelected(account.platform)
                        ? 'border-blue-600 bg-blue-50 text-blue-800'
                        : 'border-border/60 bg-muted/20 text-muted-foreground hover:border-blue-300'"
                    :disabled="savingPlatforms"
                    @click="togglePlanPlatform(account.platform)"
                >
                    <Icon :icon="promotionPlatformIcon(account.platform)" class="size-4" />
                    {{ promotionPlatformLabel(account.platform) }}
                    <span v-if="account.username" class="text-[0.65rem] opacity-70">@{{ account.username }}</span>
                </button>
            </div>
            <p v-if="hasConnectedAccounts" class="text-[0.65rem] text-muted-foreground">
                Selected: {{ planPlatforms.length }} platform(s). Unselected accounts are excluded from this week&apos;s plan.
            </p>
        </div>

        <!-- Generate -->
        <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold">Generate weekly plan</h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Choose standalone traffic content or tie topics to a campaign knowledge base.
                    </p>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-3 rounded-xl border px-3 py-2 text-left transition-colors shrink-0"
                    :class="autoSelectFormats
                        ? 'border-indigo-300 bg-indigo-50/80'
                        : 'border-border/60 bg-muted/20'"
                    :disabled="savingAutoPreference"
                    @click="toggleAutoFormats"
                >
                    <div
                        class="relative h-5 w-9 rounded-full transition-colors"
                        :class="autoSelectFormats ? 'bg-indigo-600' : 'bg-muted-foreground/30'"
                    >
                        <span
                            class="absolute top-0.5 size-4 rounded-full bg-white shadow transition-transform"
                            :class="autoSelectFormats ? 'translate-x-4' : 'translate-x-0.5'"
                        />
                    </div>
                    <div>
                        <p class="text-xs font-semibold">Auto-pick formats</p>
                        <p class="text-[0.65rem] text-muted-foreground max-w-[200px]">
                            {{ autoSelectFormats
                                ? 'AI chooses the best format per topic from the full catalog'
                                : 'Uses formats configured in Traffic setup' }}
                        </p>
                    </div>
                </button>
            </div>

            <!-- Content source -->
            <div class="space-y-3">
                <Label class="text-xs font-semibold">Content source</Label>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="flex items-start gap-3 rounded-xl border p-3 text-left transition-all"
                        :class="contentSource === 'standalone'
                            ? 'border-blue-500 bg-blue-50/60 ring-1 ring-blue-500/30 shadow-sm'
                            : 'border-border/60 bg-muted/10 hover:border-blue-200'"
                        @click="contentSource = 'standalone'"
                    >
                        <div
                            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="contentSource === 'standalone' ? 'chip-brand-active' : 'bg-muted text-muted-foreground'"
                        >
                            <Icon icon="heroicons:signal" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">Standalone</p>
                            <p class="mt-0.5 text-[0.7rem] leading-snug text-muted-foreground">
                                You provide the brief — niche, offer, audience, topic ideas. AI plans around your context.
                            </p>
                            <Badge
                                v-if="contentSource === 'standalone'"
                                variant="outline"
                                class="mt-2 border-blue-300 bg-blue-100 text-[0.6rem] text-blue-800"
                            >
                                Selected
                            </Badge>
                        </div>
                    </button>

                    <button
                        type="button"
                        class="flex items-start gap-3 rounded-xl border p-3 text-left transition-all"
                        :class="contentSource === 'campaign'
                            ? 'border-indigo-400 bg-indigo-50/60 ring-1 ring-indigo-400/30 shadow-sm'
                            : 'border-border/60 bg-muted/10 hover:border-indigo-200'"
                        @click="contentSource = 'campaign'"
                    >
                        <div
                            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg"
                            :class="contentSource === 'campaign' ? 'bg-indigo-600 text-white' : 'bg-muted text-muted-foreground'"
                        >
                            <Icon icon="heroicons:megaphone" class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold">Campaign</p>
                            <p class="mt-0.5 text-[0.7rem] leading-snug text-muted-foreground">
                                Topics from campaign KB — hooks, pains, objections tied to your offer.
                            </p>
                            <Badge
                                v-if="contentSource === 'campaign'"
                                variant="outline"
                                class="mt-2 border-indigo-300 bg-indigo-100 text-[0.6rem] text-indigo-800"
                            >
                                Selected
                            </Badge>
                        </div>
                    </button>
                </div>

                <div v-if="contentSource === 'campaign'" class="space-y-1.5">
                    <Label for="campaign-select">Campaign</Label>
                    <Select v-model="selectedCampaignId">
                        <SelectTrigger
                            id="campaign-select"
                            class="h-11 w-full min-w-0 justify-between rounded-lg border-border/70 bg-muted/10 px-3 shadow-sm hover:bg-muted/20"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                                <Icon icon="heroicons:megaphone" class="size-4 shrink-0 text-muted-foreground" />
                                <span class="truncate text-sm font-medium">{{ selectedCampaignDisplay }}</span>
                                <Badge
                                    v-if="selectedCampaign?.has_knowledge"
                                    variant="outline"
                                    class="ml-auto shrink-0 border-blue-200 bg-blue-50 text-[0.55rem] text-blue-700"
                                >
                                    KB ready
                                </Badge>
                                <Badge
                                    v-else-if="selectedCampaign"
                                    variant="outline"
                                    class="ml-auto shrink-0 border-amber-200 bg-amber-50 text-[0.55rem] text-amber-700"
                                >
                                    No KB
                                </Badge>
                            </div>
                        </SelectTrigger>
                        <SelectContent class="max-h-72 w-[var(--reka-select-trigger-width)]">
                            <SelectGroup v-if="campaigns.length === 0">
                                <SelectLabel>No campaigns yet</SelectLabel>
                            </SelectGroup>
                            <SelectGroup v-else>
                                <SelectLabel>Your campaigns</SelectLabel>
                                <SelectItem
                                    v-for="c in campaigns"
                                    :key="c.id"
                                    :value="String(c.id)"
                                    class="py-2.5"
                                >
                                    <div class="flex w-full min-w-0 items-center justify-between gap-3">
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm"
                                            :title="campaignFullLabel(c)"
                                        >
                                            {{ campaignDisplayName(c, 64) }}
                                        </span>
                                        <Badge
                                            v-if="c.has_knowledge"
                                            variant="outline"
                                            class="shrink-0 border-blue-200 bg-blue-50 text-[0.55rem] text-blue-700"
                                        >
                                            KB ready
                                        </Badge>
                                        <Badge
                                            v-else
                                            variant="outline"
                                            class="shrink-0 border-amber-200 bg-amber-50 text-[0.55rem] text-amber-700"
                                        >
                                            No KB
                                        </Badge>
                                    </div>
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Content brief -->
                <div class="space-y-2 rounded-xl border border-border/60 bg-muted/5 p-3">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <Label for="planning-brief" class="text-xs font-semibold">
                                {{ contentSource === 'standalone' ? 'Content brief' : 'Extra guidance' }}
                                <span v-if="contentSource === 'standalone'" class="text-rose-500">*</span>
                            </Label>
                            <p class="text-[0.65rem] text-muted-foreground mt-0.5">
                                {{ contentSource === 'standalone'
                                    ? 'What should this week\'s posts be about? One topic per line works great.'
                                    : 'Optional — steer KB topics or fill gaps when KB is missing.' }}
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="h-7 shrink-0 text-xs"
                            :disabled="savingBrief"
                            @click="savePlanningBrief"
                        >
                            {{ savingBrief ? 'Saving…' : 'Save brief' }}
                        </Button>
                    </div>
                    <Textarea
                        id="planning-brief"
                        v-model="planningBrief"
                        :placeholder="contentSource === 'standalone'
                            ? 'Example:\nI promote a course on email list building for coaches.\nAudience: solopreneurs stuck at 0–500 subscribers.\n\nTopics:\n- Why most lead magnets fail\n- The 3-email welcome sequence that converts\n- Mistake: posting without a CTA'
                            : 'Optional: focus on launch week, objection handling, or specific hooks to emphasize…'"
                        class="min-h-[120px] resize-y bg-white text-sm leading-relaxed"
                    />
                    <p class="text-[0.65rem] text-muted-foreground">
                        {{ planningBrief.trim().length }}/2000 characters
                        <span v-if="contentSource === 'standalone' && planningBrief.trim().length < 20" class="text-amber-700">
                            — add at least a few sentences before planning
                        </span>
                    </p>
                </div>

                <div
                    class="flex gap-2.5 rounded-lg border px-3 py-2.5 text-xs"
                    :class="contextHintClasses(contextHint.tone)"
                >
                    <Icon :icon="contextHint.icon" class="mt-0.5 size-4 shrink-0" />
                    <div>
                        <p class="font-semibold">{{ contextHint.title }}</p>
                        <p class="mt-0.5 leading-relaxed opacity-90">{{ contextHint.text }}</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end border-t border-border/40 pt-4">
                <Button
                    variant="brand"
                    :disabled="generating || !hasConnectedAccounts || planPlatforms.length === 0 || (contentSource === 'standalone' && planningBrief.trim().length < 20)"
                    @click="generatePlan"
                >
                    <Icon icon="heroicons:sparkles" class="size-4" />
                    {{ generating ? 'Planning…' : 'Plan this week' }}
                </Button>
            </div>
        </div>

        <!-- Plans -->
        <div v-if="plans.length === 0" class="rounded-xl border border-dashed border-border/60 bg-white px-6 py-12 text-center">
            <Icon icon="heroicons:calendar-days" class="mx-auto size-10 text-muted-foreground/40" />
            <p class="mt-3 font-medium">No plans yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Configure formats in Traffic setup, then generate your first weekly plan.</p>
        </div>

        <div v-for="plan in plans" :key="plan.id" class="rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-sm font-semibold">Week of {{ plan.week_start }}</h2>
                        <Badge variant="outline" class="capitalize text-[0.6rem]" :class="statusBadgeClass(plan.status)">
                            {{ plan.status }}
                        </Badge>
                        <Badge
                            v-if="plan.campaign"
                            variant="outline"
                            class="text-[0.6rem] border-indigo-200 bg-indigo-50 text-indigo-700 max-w-[180px] truncate"
                            :title="plan.campaign.name"
                        >
                            {{ truncateText(plan.campaign.name, 28) }}
                        </Badge>
                        <Badge
                            v-else
                            variant="outline"
                            class="text-[0.6rem] border-blue-200 bg-blue-50 text-blue-700"
                        >
                            Standalone
                        </Badge>
                        <Badge
                            v-if="planTopicSourceLabel(plan)"
                            variant="outline"
                            class="text-[0.6rem]"
                            :class="plan.meta?.has_campaign_knowledge
                                ? 'border-blue-200 bg-blue-50 text-blue-700'
                                : 'border-slate-200 bg-slate-50 text-slate-600'"
                        >
                            {{ planTopicSourceLabel(plan) }}
                        </Badge>
                        <Badge v-if="plan.meta?.auto_select_formats" variant="outline" class="text-[0.6rem] border-indigo-200 bg-indigo-50 text-indigo-700">
                            Auto formats
                        </Badge>
                        <Badge v-else variant="outline" class="text-[0.6rem]">Traffic formats</Badge>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">{{ plan.items.length }} content items</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="plan.status === 'draft'"
                        size="sm"
                        variant="brand-outline"
                        @click="approvePlan(plan)"
                    >
                        Approve
                    </Button>
                    <Button
                        v-if="['draft', 'approved'].includes(plan.status)"
                        size="sm"
                        variant="brand"
                        :disabled="executingPlanId === plan.id || !hasConnectedAccounts"
                        @click="executePlan(plan)"
                    >
                        {{ executingPlanId === plan.id ? 'Queuing posts…' : 'Create posts' }}
                    </Button>
                    <Button
                        v-if="plan.status !== 'executing'"
                        size="sm"
                        variant="ghost"
                        class="text-rose-600"
                        @click="deletePlan(plan)"
                    >
                        Delete
                    </Button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-muted/30 text-xs text-muted-foreground">
                            <th class="px-4 py-2 font-medium">When</th>
                            <th class="px-4 py-2 font-medium">Format</th>
                            <th class="px-4 py-2 font-medium">Topic</th>
                            <th class="px-4 py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in plan.items"
                            :key="item.id"
                            class="border-b border-border/40 last:border-0"
                        >
                            <td class="whitespace-nowrap px-4 py-2.5 text-xs text-muted-foreground">
                                {{ dayLabel(item.scheduled_for) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class="font-medium">{{ item.format_label || formatLabel(item.format_key) }}</span>
                                <span class="ml-1 text-xs text-muted-foreground">({{ item.platform }})</span>
                            </td>
                            <td class="max-w-xs px-4 py-2.5">
                                <p class="line-clamp-2 text-xs font-medium">{{ item.topic }}</p>
                                <p v-if="item.angle" class="mt-0.5 line-clamp-1 text-[0.65rem] text-muted-foreground">{{ item.angle }}</p>
                            </td>
                            <td class="px-4 py-2.5">
                                <Badge variant="outline" class="text-[0.6rem] capitalize">{{ item.status }}</Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
