<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
}>();

const search = ref('');
const filter = ref<'all' | 'published' | 'draft'>('all');

const stats = computed(() => ({
    total: props.campaigns.length,
    published: props.campaigns.filter((c) => c.status === 'published').length,
    draft: props.campaigns.filter((c) => c.status === 'draft').length,
    ready: props.campaigns.filter((c) => c.has_traffic_hub).length,
}));

const statCards = computed(() => [
    { label: 'Campaigns', value: stats.value.total, sub: 'total', icon: 'heroicons:rocket-launch' },
    { label: 'Published', value: stats.value.published, sub: 'live', icon: 'heroicons:globe-alt' },
    { label: 'Drafts', value: stats.value.draft, sub: 'in progress', icon: 'heroicons:pencil-square' },
    { label: 'Hubs ready', value: stats.value.ready, sub: 'traffic', icon: 'heroicons:signal' },
]);

const filterTabs = [
    { key: 'all' as const, label: 'All' },
    { key: 'published' as const, label: 'Published' },
    { key: 'draft' as const, label: 'Draft' },
];

const filtered = computed(() => {
    let list = props.campaigns;
    if (filter.value !== 'all') {
        list = list.filter((c) => c.status === filter.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(
            (c) =>
                c.name.toLowerCase().includes(q)
                || (c.product_name ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

function typeIcon(type: string): string {
    return type === 'webinar' ? 'heroicons:video-camera' : 'heroicons:shopping-bag';
}
</script>

<template>
    <Head title="Traffic Hub" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Traffic hub</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Free mentions, social promotion, promo calendar & paid ads — one workspace per campaign.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">
                        <Icon icon="heroicons:plus" class="size-3.5" />
                        New campaign
                    </Link>
                </Button>
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/campaigns">
                        <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                        All campaigns
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
                        <span class="text-xl font-bold leading-none tabular-nums">{{ stat.value }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + filters -->
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

        <!-- Empty -->
        <div
            v-if="campaigns.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-teal-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                <Icon icon="heroicons:signal" class="size-7 text-teal-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No campaigns yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Create a campaign first — each gets its own traffic workspace.
                </p>
            </div>
            <Button as-child variant="brand" size="sm">
                <Link href="/campaigns/create">New campaign</Link>
            </Button>
        </div>

        <!-- No results -->
        <div
            v-else-if="filtered.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/60 bg-white px-6 py-12 text-center shadow-sm"
        >
            <Icon icon="heroicons:magnifying-glass" class="size-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No campaigns match your search.</p>
        </div>

        <!-- Campaign grid -->
        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="c in filtered"
                :key="c.id"
                class="flex flex-col overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-teal-200/60 hover:shadow-md"
            >
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10">
                            <Icon :icon="typeIcon(c.type)" class="size-5 text-teal-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="line-clamp-2 text-sm font-semibold leading-snug">{{ c.name }}</h2>
                            </div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
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
                                    v-if="c.has_traffic_hub"
                                    variant="outline"
                                    class="border-cyan-200 bg-cyan-50 text-[0.6rem] text-cyan-700"
                                >
                                    Hub ready
                                </Badge>
                            </div>
                            <p v-if="c.product_name" class="mt-2 line-clamp-2 text-xs text-muted-foreground">{{ c.product_name }}</p>
                        </div>
                    </div>

                    <div class="mt-auto flex flex-wrap gap-2 pt-1">
                        <Button as-child size="sm" variant="brand" class="flex-1 sm:flex-none">
                            <Link :href="c.traffic_hub_url">
                                <Icon icon="heroicons:signal" class="size-3.5" />
                                Open hub
                            </Link>
                        </Button>
                        <Button as-child size="sm" variant="brand-outline" class="flex-1 sm:flex-none">
                            <Link :href="c.campaign_edit_url">
                                <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                Campaign
                            </Link>
                        </Button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
