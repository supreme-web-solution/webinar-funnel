<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface CampaignItem {
    id: number;
    name: string;
    slug: string;
    type: string;
    status: string;
    wizard_step: number;
    created_at: string;
    published_at: string | null;
    bonuses_count: number;
    emails_count: number;
    funnels_count: number;
    tracked_links_count: number;
    knowledge_ready?: boolean;
    is_generating?: boolean;
    generation_failed?: boolean;
    generation_message?: string | null;
    generation_progress?: number;
    generation_error?: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: CampaignItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

const props = defineProps<{
    campaigns: Paginator;
    stats: { total: number; published: number; draft: number; knowledge_ready: number };
    filters: { search: string; status: 'all' | 'published' | 'draft' };
}>();

const search = ref(props.filters.search ?? '');
const filter = ref<'all' | 'published' | 'draft'>(props.filters.status ?? 'all');

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, sub: 'campaigns', icon: 'heroicons:rocket-launch' },
    { label: 'Published', value: props.stats.published, sub: 'live', icon: 'heroicons:globe-alt' },
    { label: 'Drafts', value: props.stats.draft, sub: 'in progress', icon: 'heroicons:pencil-square' },
    { label: 'Knowledge', value: props.stats.knowledge_ready, sub: 'ready', icon: 'heroicons:book-open' },
]);

const filterTabs = [
    { key: 'all' as const, label: 'All' },
    { key: 'published' as const, label: 'Published' },
    { key: 'draft' as const, label: 'Draft' },
];

const hasGenerating = computed(() => props.campaigns.data.some((c) => c.is_generating));

let pollTimer: ReturnType<typeof setInterval> | null = null;
let searchDebounce: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    router.get(
        '/campaigns',
        {
            search: search.value.trim() || undefined,
            status: filter.value === 'all' ? undefined : filter.value,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

watch(filter, () => applyFilters());

watch(search, () => {
    if (searchDebounce) clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => applyFilters(), 350);
});

onMounted(() => {
    pollTimer = setInterval(() => {
        if (hasGenerating.value) {
            router.reload({ only: ['campaigns', 'stats'] });
        }
    }, 4000);
});

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer);
    if (searchDebounce) clearTimeout(searchDebounce);
});

function removeCampaign(id: number) {
    if (!confirm('Delete this campaign?')) return;
    router.delete(`/campaigns/${id}`);
}

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function typeIcon(type: string): string {
    return type === 'webinar' ? 'heroicons:video-camera' : 'heroicons:shopping-bag';
}

function wizardProgress(step: number): number {
    return Math.min(100, Math.round((step / 8) * 100));
}

function listProgress(c: CampaignItem): number {
    if (c.is_generating && (c.generation_progress ?? 0) > 0) {
        return Math.min(100, c.generation_progress ?? 0);
    }
    return wizardProgress(c.wizard_step);
}
</script>

<template>
    <Head title="Campaigns" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">

        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Your campaigns</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    AI-built funnels from a single offer — edit, publish, and send traffic from here.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">
                        <Icon icon="heroicons:plus" class="size-3.5" />
                        New Campaign
                    </Link>
                </Button>
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/growth/opportunities">
                        <Icon icon="heroicons:light-bulb" class="size-3.5" />
                        Find Offers
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-4 md:gap-3">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="flex items-center gap-3 rounded-xl border border-border/60 bg-white px-3 py-2.5 shadow-sm"
            >
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                    <Icon :icon="stat.icon" class="size-4 text-blue-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">{{ stat.label }}</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-bold leading-none">{{ stat.value }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + filters -->
        <div class="flex flex-col gap-3 rounded-xl border border-border/60 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between md:p-4">
            <div class="relative max-w-md flex-1">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search campaigns…" class="pl-9" />
            </div>
            <div class="flex gap-1.5">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="filter === tab.key
                        ? 'chip-brand-active'
                        : 'border border-border/60 bg-muted/20 text-muted-foreground hover:bg-blue-50 hover:text-blue-800'"
                    @click="filter = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="campaigns.data.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-blue-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-blue-500/10">
                <Icon icon="heroicons:rocket-launch" class="size-7 text-blue-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No campaigns found</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Paste an offer URL or pick one from Opportunities — AI builds everything in minutes.
                </p>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">Start New Campaign</Link>
                </Button>
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/growth/opportunities">Browse Offers</Link>
                </Button>
            </div>
        </div>

        <!-- Campaign list -->
        <div v-else class="grid gap-3">
            <article
                v-for="c in campaigns.data"
                :key="c.id"
                class="group overflow-hidden rounded-xl border bg-white shadow-sm transition-all hover:shadow-md"
                :class="c.is_generating
                    ? 'border-amber-400/70 ring-1 ring-amber-400/30'
                    : c.generation_failed
                        ? 'border-red-200/80'
                        : 'border-border/60 hover:border-blue-200/60'"
            >
                <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div
                            class="relative flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10"
                        >
                            <Icon :icon="typeIcon(c.type)" class="size-5 text-blue-600" />
                            <span
                                v-if="c.is_generating"
                                class="absolute -right-0.5 -top-0.5 size-2.5 animate-pulse rounded-full bg-amber-500 ring-2 ring-white"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="`/campaigns/${c.id}/edit`"
                                    class="truncate text-sm font-semibold text-foreground hover:text-blue-800 hover:underline"
                                >
                                    {{ c.name }}
                                </Link>
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.6rem]"
                                    :class="c.type === 'webinar' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-blue-200 bg-blue-50 text-blue-700'"
                                >
                                    {{ c.type }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.6rem]"
                                    :class="c.status === 'published' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ c.status }}
                                </Badge>
                                <Badge
                                    v-if="c.is_generating"
                                    variant="outline"
                                    class="border-amber-300 bg-amber-50 text-[0.6rem] text-amber-800"
                                >
                                    <Icon icon="heroicons:arrow-path" class="mr-0.5 inline size-3 animate-spin" />
                                    Generating…
                                </Badge>
                                <Badge
                                    v-else-if="c.generation_failed"
                                    variant="outline"
                                    class="border-red-200 bg-red-50 text-[0.6rem] text-red-700"
                                >
                                    Generation failed
                                </Badge>
                                <Badge
                                    v-else-if="c.knowledge_ready"
                                    variant="outline"
                                    class="border-blue-200 bg-blue-50 text-[0.6rem] text-blue-700"
                                >
                                    Knowledge ready
                                </Badge>
                            </div>
                            <p class="mt-0.5 truncate text-[0.65rem] text-muted-foreground">{{ c.slug }}</p>

                            <p
                                v-if="c.is_generating && c.generation_message"
                                class="mt-1.5 text-[0.65rem] font-medium text-amber-800"
                            >
                                {{ c.generation_message }}
                            </p>
                            <p
                                v-else-if="c.generation_failed && c.generation_error"
                                class="mt-1.5 text-[0.65rem] text-red-600 line-clamp-2"
                            >
                                {{ c.generation_error }}
                            </p>

                            <!-- Progress -->
                            <div class="mt-2 flex max-w-xs items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="c.is_generating ? 'bg-amber-500 animate-pulse' : 'fill-brand-gradient'"
                                        :style="{ width: `${listProgress(c)}%` }"
                                    />
                                </div>
                                <span class="shrink-0 text-[0.6rem] font-medium text-muted-foreground">
                                    <template v-if="c.is_generating && (c.generation_progress ?? 0) > 0">
                                        {{ c.generation_progress }}%
                                    </template>
                                    <template v-else>Step {{ c.wizard_step }}/8</template>
                                </span>
                            </div>

                            <!-- Asset chips -->
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:gift" class="size-3 text-blue-600" />
                                    {{ c.bonuses_count }} bonuses
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:envelope" class="size-3 text-blue-600" />
                                    {{ c.emails_count }} emails
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:shield-check" class="size-3 text-blue-600" />
                                    {{ c.tracked_links_count }} links
                                </span>
                                <span
                                    v-if="c.type === 'webinar'"
                                    class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground"
                                >
                                    <Icon icon="heroicons:video-camera" class="size-3 text-blue-600" />
                                    {{ c.funnels_count }} funnels
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:calendar" class="size-3" />
                                    {{ fmtDate(c.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2 lg:flex-col lg:items-stretch xl:flex-row">
                        <Button as-child size="sm" variant="brand" class="min-w-[7rem]">
                            <Link :href="`/campaigns/${c.id}/edit`">
                                <Icon :icon="c.is_generating ? 'heroicons:eye' : 'heroicons:arrow-right'" class="size-3.5" />
                                {{ c.is_generating ? 'View progress' : 'Continue' }}
                            </Link>
                        </Button>
                        <Button as-child size="sm" variant="brand-outline" class="min-w-[7rem]">
                            <Link :href="`/campaigns/${c.id}/traffic`">
                                <Icon icon="heroicons:signal" class="size-3.5" />
                                Traffic
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="text-muted-foreground hover:text-destructive"
                            :disabled="c.is_generating"
                            @click="removeCampaign(c.id)"
                        >
                            <Icon icon="heroicons:trash" class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </article>

            <!-- Pagination -->
            <div
                v-if="campaigns.last_page > 1"
                class="flex flex-col gap-2 rounded-xl border border-border/60 bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-xs text-muted-foreground">
                    Showing {{ campaigns.from ?? 0 }}–{{ campaigns.to ?? 0 }} of {{ campaigns.total }}
                    · Page {{ campaigns.current_page }} of {{ campaigns.last_page }}
                </p>
                <div class="flex flex-wrap items-center gap-1">
                    <button
                        v-for="link in campaigns.links"
                        :key="link.label"
                        :disabled="!link.url"
                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                        :class="link.active
                            ? 'chip-brand-active'
                            : 'border-border/60 bg-white text-foreground hover:bg-blue-50'"
                        @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
