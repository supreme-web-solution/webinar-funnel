<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CatalogItem = {
    key: string;
    platform: string;
    platform_label: string;
    label: string;
    content_type: string;
    generator: string;
    frequency_per_week: number;
};

type ProfilePayload = {
    profile: {
        intensity: string;
        enabled_platforms: string[];
        enabled_formats: string[];
    };
    intensity_presets: Array<{ key: string; label: string; description: string }>;
    platforms: Array<{ key: string; label: string; icon: string; formats: string[] }>;
    catalog: CatalogItem[];
    connected_accounts: Array<{ platform: string; username: string | null }>;
};

const props = defineProps<{
    campaigns: Array<{
        id: number;
        name: string;
        type: string;
        status: string;
        product_name: string | null;
        has_traffic_hub: boolean;
        traffic_hub_url: string;
        campaign_edit_url: string;
    }>;
    profile: ProfilePayload;
    workspace: {
        funnel: { id: number; name: string; slug: string };
        routes: {
            promotion_posts: string;
            promotion_calendar: string;
            free_traffic: string;
        };
    };
    post_stats: { total: number; scheduled: number; draft: number };
    current_plan: {
        id: number;
        status: string;
        items: Array<{ id: number; format_key: string; topic: string; scheduled_for: string | null }>;
    } | null;
    routes: { profile_update: string; social_settings: string };
}>();

const activeTab = ref<'setup' | 'workspace' | 'campaigns'>(
    new URLSearchParams(window.location.search).get('tab') === 'setup' ? 'setup' : 'workspace',
);
const formatsOpen = ref(false);
const openPlatformKeys = ref<Record<string, boolean>>({});
const search = ref('');
const filter = ref<'all' | 'published' | 'draft'>('all');

const setupForm = useForm({
    intensity: props.profile.profile.intensity,
    enabled_formats: [...props.profile.profile.enabled_formats],
    enabled_platforms: [...props.profile.profile.enabled_platforms],
});

const formatsByPlatform = computed(() => {
    const map: Record<string, CatalogItem[]> = {};
    for (const item of props.profile.catalog) {
        if (!map[item.platform]) map[item.platform] = [];
        map[item.platform].push(item);
    }
    return map;
});

const enabledFormatCount = computed(() => setupForm.enabled_formats.length);
const connectedCount = computed(() => props.profile.connected_accounts.length);

const filteredCampaigns = computed(() => {
    let list = props.campaigns;
    if (filter.value !== 'all') {
        list = list.filter((c) => c.status === filter.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(
            (c) => c.name.toLowerCase().includes(q) || (c.product_name ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

function isFormatEnabled(key: string): boolean {
    return setupForm.enabled_formats.includes(key);
}

function toggleFormat(key: string): void {
    if (isFormatEnabled(key)) {
        setupForm.enabled_formats = setupForm.enabled_formats.filter((k) => k !== key);
    } else {
        setupForm.enabled_formats = [...setupForm.enabled_formats, key];
    }
    syncPlatformsFromFormats();
}

function selectPreset(key: string): void {
    setupForm.intensity = key;
    if (key === 'starter') {
        setupForm.enabled_formats = props.profile.catalog
            .filter((f) => [
                'instagram_reel', 'instagram_carousel', 'tiktok_short', 'facebook_reel',
                'x_text_post', 'linkedin_text_post', 'youtube_short', 'pinterest_static_pin',
            ].includes(f.key))
            .map((f) => f.key);
    } else {
        setupForm.enabled_formats = props.profile.catalog.map((f) => f.key);
    }
    syncPlatformsFromFormats();
}

function syncPlatformsFromFormats(): void {
    const platforms = new Set<string>();
    for (const key of setupForm.enabled_formats) {
        const item = props.profile.catalog.find((f) => f.key === key);
        if (item) platforms.add(item.platform);
    }
    setupForm.enabled_platforms = [...platforms];
}

function saveProfile(): void {
    setupForm.patch(props.routes.profile_update, { preserveScroll: true });
}

function typeIcon(type: string): string {
    return type === 'webinar' ? 'heroicons:video-camera' : 'heroicons:shopping-bag';
}

function togglePlatformSection(key: string): void {
    openPlatformKeys.value[key] = !openPlatformKeys.value[key];
}

function platformEnabledCount(platformKey: string): number {
    return (formatsByPlatform.value[platformKey] ?? []).filter((f) => isFormatEnabled(f.key)).length;
}

const workspaceActions = [
        {
            title: 'Social promotion',
            description: 'Create posts — pick a topic, generate, publish. Start here.',
            icon: 'heroicons:megaphone',
            href: '/traffic/workspace/promotion/posts',
            cta: 'Create post',
            variant: 'brand' as const,
        },
    {
        title: 'Free traffic',
        description: 'Keywords, mentions & AI reply.',
        icon: 'heroicons:magnifying-glass-circle',
        href: '/traffic/workspace/free',
        cta: 'Open',
        variant: 'brand-outline' as const,
    },
    {
        title: 'Promo calendar',
        description: 'Drag-and-drop your content schedule.',
        icon: 'heroicons:calendar-days',
        href: '/traffic/workspace/promotion/calendar',
        cta: 'View calendar',
        variant: 'brand-outline' as const,
    },
];

const tabs = [
    { key: 'setup' as const, label: 'Setup', icon: 'heroicons:cog-6-tooth' },
    { key: 'workspace' as const, label: 'Workspace', icon: 'heroicons:squares-2x2' },
    { key: 'campaigns' as const, label: 'Campaigns', icon: 'heroicons:rocket-launch' },
];
</script>

<template>
    <Head title="Traffic Hub" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Traffic hub</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Configure platforms & formats once — post standalone or per campaign. Content Employee uses this setup.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand-outline" size="sm">
                    <Link :href="routes.social_settings">
                        <Icon icon="heroicons:link" class="size-3.5" />
                        Connect accounts
                    </Link>
                </Button>
                <Button as-child variant="brand" size="sm">
                    <Link href="/growth/content-employee">
                        <Icon icon="heroicons:sparkles" class="size-3.5" />
                        Content Employee
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-1.5 rounded-xl border border-border/60 bg-white p-1.5 shadow-sm">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition-colors sm:text-sm"
                :class="activeTab === tab.key
                    ? 'chip-brand-active'
                    : 'text-muted-foreground hover:bg-blue-50 hover:text-blue-800'"
                @click="activeTab = tab.key"
            >
                <Icon :icon="tab.icon" class="size-4" />
                {{ tab.label }}
            </button>
        </div>

        <!-- Setup tab -->
        <div v-if="activeTab === 'setup'" class="space-y-4">
            <div class="grid gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Formats enabled</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ enabledFormatCount }}</p>
                    <p class="text-xs text-muted-foreground">of {{ profile.catalog.length }} in catalog</p>
                </div>
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Platforms</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ setupForm.enabled_platforms.length }}</p>
                    <p class="text-xs text-muted-foreground">Instagram → Pinterest</p>
                </div>
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Connected accounts</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums">{{ connectedCount }}</p>
                    <Link :href="routes.social_settings" class="text-xs text-blue-600 underline">Manage</Link>
                </div>
            </div>

            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold">Posting intensity</h2>
                <p class="mt-1 text-xs text-muted-foreground">Pick a preset — customize individual formats below if needed.</p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <button
                        v-for="preset in profile.intensity_presets"
                        :key="preset.key"
                        type="button"
                        class="rounded-lg border p-3 text-left transition-colors"
                        :class="setupForm.intensity === preset.key
                            ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-500/30'
                            : 'border-border/60 hover:border-blue-200'"
                        @click="selectPreset(preset.key)"
                    >
                        <p class="text-sm font-semibold">{{ preset.label }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">{{ preset.description }}</p>
                    </button>
                </div>
            </div>

            <Collapsible v-model:open="formatsOpen" class="rounded-xl border border-border/60 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 p-4">
                    <CollapsibleTrigger class="flex min-w-0 flex-1 items-center gap-3 text-left">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                            <Icon icon="heroicons:adjustments-horizontal" class="size-4 text-blue-600" />
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold">Content formats</h2>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ enabledFormatCount }} enabled · {{ profile.platforms.length }} platforms
                                <span class="hidden sm:inline">— expand to fine-tune</span>
                            </p>
                        </div>
                        <Icon
                            icon="heroicons:chevron-down"
                            class="ml-auto size-4 shrink-0 text-muted-foreground transition-transform"
                            :class="formatsOpen ? 'rotate-180' : ''"
                        />
                    </CollapsibleTrigger>
                    <Button size="sm" variant="brand" class="shrink-0" :disabled="setupForm.processing" @click.stop="saveProfile">
                        Save
                    </Button>
                </div>

                <CollapsibleContent class="border-t border-border/60 px-4 pb-4">
                    <div class="mt-4 space-y-3">
                        <Collapsible
                            v-for="platform in profile.platforms"
                            :key="platform.key"
                            :open="openPlatformKeys[platform.key] ?? false"
                            class="rounded-lg border border-border/60"
                            @update:open="(v) => { openPlatformKeys[platform.key] = v; }"
                        >
                            <CollapsibleTrigger class="flex w-full items-center gap-2 px-3 py-2.5 text-left hover:bg-muted/30">
                                <Icon v-if="platform.icon" :icon="platform.icon" class="size-4 text-muted-foreground" />
                                <span class="text-xs font-semibold">{{ platform.label }}</span>
                                <Badge variant="outline" class="text-[0.6rem]">
                                    {{ platformEnabledCount(platform.key) }}/{{ (formatsByPlatform[platform.key] ?? []).length }}
                                </Badge>
                                <Icon
                                    icon="heroicons:chevron-down"
                                    class="ml-auto size-3.5 text-muted-foreground transition-transform"
                                    :class="openPlatformKeys[platform.key] ? 'rotate-180' : ''"
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent class="border-t border-border/40 px-3 pb-3 pt-2">
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <label
                                        v-for="format in formatsByPlatform[platform.key] ?? []"
                                        :key="format.key"
                                        class="flex cursor-pointer items-start gap-2 rounded-lg border p-2.5 transition-colors"
                                        :class="isFormatEnabled(format.key)
                                            ? 'border-blue-300 bg-blue-50/60'
                                            : 'border-border/60 hover:border-blue-200'"
                                    >
                                        <input
                                            type="checkbox"
                                            class="mt-0.5"
                                            :checked="isFormatEnabled(format.key)"
                                            @change="toggleFormat(format.key)"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-xs font-medium">{{ format.label }}</p>
                                            <p class="text-[0.65rem] text-muted-foreground">
                                                {{ format.content_type }} · {{ format.frequency_per_week }}/wk
                                            </p>
                                        </div>
                                    </label>
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>

        <!-- Workspace tab -->
        <div v-else-if="activeTab === 'workspace'" class="space-y-4">
            <div class="rounded-xl border border-blue-200/50 bg-blue-50/30 p-4">
                <p class="text-sm font-semibold text-blue-900">How standalone traffic works</p>
                <ol class="mt-2 grid gap-2 text-xs text-blue-800/90 sm:grid-cols-3">
                    <li class="flex items-start gap-2 rounded-lg border border-blue-200/40 bg-white/60 p-2.5">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">1</span>
                        <span>
                            <button type="button" class="font-semibold underline-offset-2 hover:underline" @click="activeTab = 'setup'">Set up once</button>
                            — connect accounts & pick formats (optional if using defaults).
                        </span>
                    </li>
                    <li class="flex items-start gap-2 rounded-lg border border-blue-200/40 bg-white/60 p-2.5">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">2</span>
                        <span>
                            <Link href="/traffic/workspace/promotion/posts" class="font-semibold underline-offset-2 hover:underline">Create posts</Link>
                            — no campaign needed; workspace is created for you automatically.
                        </span>
                    </li>
                    <li class="flex items-start gap-2 rounded-lg border border-blue-200/40 bg-white/60 p-2.5">
                        <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">3</span>
                        <span>
                            Schedule on the
                            <Link href="/traffic/workspace/promotion/calendar" class="font-semibold underline-offset-2 hover:underline">calendar</Link>
                            or let Content Employee plan the week.
                        </span>
                    </li>
                </ol>
            </div>

            <div class="flex flex-col gap-3 rounded-xl border border-border/60 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10">
                        <Icon icon="heroicons:squares-2x2" class="size-5 text-blue-600" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold">Standalone workspace</h2>
                        <p class="text-xs text-muted-foreground">Post, track mentions & schedule — no campaign needed.</p>
                    </div>
                </div>
                <Button as-child variant="brand" size="sm" class="shrink-0">
                    <Link href="/traffic/workspace">
                        <Icon icon="heroicons:arrow-right" class="size-3.5" />
                        Open workspace
                    </Link>
                </Button>
            </div>

            <div class="grid grid-cols-3 gap-2 md:gap-3">
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground">Standalone posts</p>
                    <p class="text-2xl font-bold tabular-nums">{{ post_stats.total }}</p>
                </div>
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground">Scheduled</p>
                    <p class="text-2xl font-bold tabular-nums">{{ post_stats.scheduled }}</p>
                </div>
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="text-xs text-muted-foreground">Drafts</p>
                    <p class="text-2xl font-bold tabular-nums">{{ post_stats.draft }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <article
                    v-for="card in workspaceActions"
                    :key="card.title"
                    class="flex flex-col rounded-xl border border-border/60 bg-white p-4 shadow-sm transition-all hover:border-blue-200/60 hover:shadow-md"
                >
                    <div class="flex size-10 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                        <Icon :icon="card.icon" class="size-5 text-blue-600" />
                    </div>
                    <h2 class="mt-3 text-sm font-semibold">{{ card.title }}</h2>
                    <p class="mt-1 flex-1 text-xs text-muted-foreground">{{ card.description }}</p>
                    <Button as-child size="sm" :variant="card.variant" class="mt-4 w-fit">
                        <Link :href="card.href">{{ card.cta }}</Link>
                    </Button>
                </article>
            </div>

            <div v-if="current_plan" class="rounded-xl border border-indigo-200/60 bg-indigo-50/40 p-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold text-indigo-900">This week's plan (standalone)</h2>
                        <p class="text-xs text-indigo-700/80">{{ current_plan.items.length }} items · {{ current_plan.status }}</p>
                    </div>
                    <Button as-child size="sm" variant="brand-outline">
                        <Link href="/growth/content-employee">View in Content Employee</Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Campaigns tab -->
        <div v-else class="space-y-4">
            <div
                v-if="campaigns.length > 0"
                class="flex flex-col gap-3 rounded-xl border border-border/60 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between md:p-4"
            >
                <div class="relative max-w-md flex-1">
                    <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="search" placeholder="Search campaigns…" class="pl-9" />
                </div>
                <div class="flex gap-1.5">
                    <button
                        v-for="tab in [{ key: 'all', label: 'All' }, { key: 'published', label: 'Published' }, { key: 'draft', label: 'Draft' }]"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="filter === tab.key ? 'chip-brand-active' : 'border border-border/60 text-muted-foreground'"
                        @click="filter = tab.key as typeof filter"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="campaigns.length === 0"
                class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-blue-200/60 bg-white px-6 py-14 text-center"
            >
                <p class="font-semibold">No campaigns yet</p>
                <p class="max-w-sm text-sm text-muted-foreground">Use the standalone workspace above, or create a campaign for offer-specific traffic.</p>
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">New campaign</Link>
                </Button>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="c in filteredCampaigns"
                    :key="c.id"
                    class="flex flex-col rounded-xl border border-border/60 bg-white p-4 shadow-sm hover:border-blue-200/60"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/10">
                            <Icon :icon="typeIcon(c.type)" class="size-5 text-blue-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="line-clamp-2 text-sm font-semibold">{{ c.name }}</h2>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                <Badge variant="outline" class="capitalize text-[0.6rem]">{{ c.type }}</Badge>
                                <Badge variant="outline" class="capitalize text-[0.6rem]">{{ c.status }}</Badge>
                            </div>
                            <p v-if="c.product_name" class="mt-2 line-clamp-2 text-xs text-muted-foreground">{{ c.product_name }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button as-child size="sm" variant="brand" class="flex-1 sm:flex-none">
                            <Link :href="c.traffic_hub_url">Open hub</Link>
                        </Button>
                        <Button as-child size="sm" variant="brand-outline" class="flex-1 sm:flex-none">
                            <Link :href="c.campaign_edit_url">Campaign</Link>
                        </Button>
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>
