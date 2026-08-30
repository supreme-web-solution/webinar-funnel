<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
}

const props = defineProps<{
    campaigns: CampaignItem[];
    stats: { total: number; published: number; draft: number };
}>();

const search = ref('');
const filter = ref<'all' | 'published' | 'draft'>('all');

const filtered = computed(() => {
    let list = props.campaigns;
    if (filter.value !== 'all') {
        list = list.filter((c) => c.status === filter.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter((c) => c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q));
    }
    return list;
});

const readyCount = computed(() => props.campaigns.filter((c) => c.knowledge_ready).length);

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, sub: 'campaigns', icon: 'heroicons:rocket-launch' },
    { label: 'Published', value: props.stats.published, sub: 'live', icon: 'heroicons:globe-alt' },
    { label: 'Drafts', value: props.stats.draft, sub: 'in progress', icon: 'heroicons:pencil-square' },
    { label: 'Knowledge', value: readyCount.value, sub: 'ready', icon: 'heroicons:book-open' },
]);

const filterTabs = [
    { key: 'all' as const, label: 'All' },
    { key: 'published' as const, label: 'Published' },
    { key: 'draft' as const, label: 'Draft' },
];

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
</script>

<template>
    <Head title="Campaigns" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">

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
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-500/10">
                    <Icon :icon="stat.icon" class="size-4 text-teal-600" />
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
                        ? 'bg-teal-600 text-white shadow-sm'
                        : 'border border-border/60 bg-muted/20 text-muted-foreground hover:bg-teal-50 hover:text-teal-800'"
                    @click="filter = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div
            v-if="filtered.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-teal-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                <Icon icon="heroicons:rocket-launch" class="size-7 text-teal-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No campaigns yet</p>
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
                v-for="c in filtered"
                :key="c.id"
                class="group overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-teal-200/60 hover:shadow-md"
            >
                <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10"
                        >
                            <Icon :icon="typeIcon(c.type)" class="size-5 text-teal-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="`/campaigns/${c.id}/edit`"
                                    class="truncate text-sm font-semibold text-foreground hover:text-teal-800 hover:underline"
                                >
                                    {{ c.name }}
                                </Link>
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.6rem]"
                                    :class="c.type === 'webinar' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-teal-200 bg-teal-50 text-teal-700'"
                                >
                                    {{ c.type }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.6rem]"
                                    :class="c.status === 'published' ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ c.status }}
                                </Badge>
                                <Badge
                                    v-if="c.knowledge_ready"
                                    variant="outline"
                                    class="border-cyan-200 bg-cyan-50 text-[0.6rem] text-cyan-700"
                                >
                                    Knowledge ready
                                </Badge>
                            </div>
                            <p class="mt-0.5 truncate text-[0.65rem] text-muted-foreground">{{ c.slug }}</p>

                            <!-- Progress -->
                            <div class="mt-2 flex max-w-xs items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-linear-to-r from-teal-600 to-cyan-400 transition-all"
                                        :style="{ width: `${wizardProgress(c.wizard_step)}%` }"
                                    />
                                </div>
                                <span class="shrink-0 text-[0.6rem] font-medium text-muted-foreground">Step {{ c.wizard_step }}/8</span>
                            </div>

                            <!-- Asset chips -->
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:gift" class="size-3 text-teal-600" />
                                    {{ c.bonuses_count }} bonuses
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:envelope" class="size-3 text-teal-600" />
                                    {{ c.emails_count }} emails
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:shield-check" class="size-3 text-teal-600" />
                                    {{ c.tracked_links_count }} links
                                </span>
                                <span
                                    v-if="c.type === 'webinar'"
                                    class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground"
                                >
                                    <Icon icon="heroicons:video-camera" class="size-3 text-teal-600" />
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
                                <Icon icon="heroicons:arrow-right" class="size-3.5" />
                                Continue
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
                            @click="removeCampaign(c.id)"
                        >
                            <Icon icon="heroicons:trash" class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
