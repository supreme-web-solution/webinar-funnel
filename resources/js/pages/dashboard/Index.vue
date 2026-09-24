<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DashboardPerformanceChart, { type ChartDay } from '@/components/dashboard/DashboardPerformanceChart.vue';
import PromoteThisWeekCard from '@/components/growth/PromoteThisWeekCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { MarketplaceOffer } from '@/composables/useMarketplaceSearch';

const props = defineProps<{
    metrics: {
        funnelCount: number;
        publishedCount: number;
        draftCount: number;
        leadCount: number;
        recentLeads: number;
        previousWeekLeads: number;
        totalViewCount: number;
        recentViewCount: number;
        previousWeekViewCount: number;
    };
    topFunnels: Array<{
        id: number;
        name: string;
        slug: string;
        status: string;
        leads_count: number;
    }>;
    recentFunnels: Array<{
        id: number;
        name: string;
        slug: string;
        status: string;
        created_at: string;
        template?: { name: string } | null;
    }>;
    chartDays: ChartDay[];
    promoteThisWeek?: {
        top_pick: MarketplaceOffer | null;
        refreshed_at: string | null;
        alternates: MarketplaceOffer[];
    };
}>();

const page = usePage();

const userName = computed(() => {
    const user = (page.props.auth as { user?: { name: string } })?.user;
    return user?.name?.split(' ')[0] ?? 'there';
});

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

function weekTrend(current: number, previous: number): { value: number; positive: boolean } {
    if (previous === 0) return { value: current > 0 ? 100 : 0, positive: true };
    const pct = Math.round(((current - previous) / previous) * 100);
    return { value: Math.abs(pct), positive: pct >= 0 };
}

const leadTrend = computed(() => weekTrend(props.metrics.recentLeads, props.metrics.previousWeekLeads));
const viewTrend = computed(() => weekTrend(props.metrics.recentViewCount, props.metrics.previousWeekViewCount));

const recentItems = computed(() => props.recentFunnels.slice(0, 5));
const topItems = computed(() => props.topFunnels.slice(0, 3));

const quickLinks = [
    { label: 'New Campaign', icon: 'heroicons:rocket-launch', href: '/campaigns/create' },
    { label: 'Find Offers', icon: 'heroicons:light-bulb', href: '/growth/opportunities' },
    { label: 'Traffic', icon: 'heroicons:signal', href: '/traffic' },
    { label: 'Bonuses', icon: 'heroicons:gift', href: '/bonuses' },
    { label: 'Links', icon: 'heroicons:shield-check', href: '/tracked-links' },
];

const stats = computed(() => [
    {
        label: 'Live',
        value: props.metrics.publishedCount,
        sub: props.metrics.draftCount > 0 ? `${props.metrics.draftCount} drafts` : 'funnels',
        icon: 'heroicons:globe-alt',
        trend: null,
    },
    {
        label: 'Leads',
        value: props.metrics.leadCount,
        sub: 'total',
        icon: 'heroicons:users',
        trend: leadTrend.value,
    },
    {
        label: 'Views',
        value: props.metrics.totalViewCount,
        sub: 'all time',
        icon: 'heroicons:eye',
        trend: viewTrend.value,
    },
    {
        label: 'This week',
        value: props.metrics.recentLeads,
        sub: 'new leads',
        icon: 'heroicons:calendar-days',
        trend: null,
    },
]);

const weekLeadTotal = computed(() => props.chartDays.reduce((s, d) => s + d.leads, 0));
const weekViewTotal = computed(() => props.chartDays.reduce((s, d) => s + d.views, 0));

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 pb-6 md:gap-4 md:p-4 md:pb-8">

        <!-- Hero -->
        <section class="hero-banner shrink-0">
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-xl font-bold tracking-tight text-white md:text-2xl">
                        {{ greeting }}, {{ userName }}
                    </p>
                    <p class="mt-1 max-w-xl text-sm text-blue-50/90 md:text-[0.9375rem]">
                        Paste one offer — AI builds campaigns, bonuses, emails & traffic for you.
                    </p>
                </div>
                <div class="relative flex shrink-0 flex-wrap gap-2">
                    <Button as-child size="sm" class="btn-hero-inverted h-9">
                        <Link href="/campaigns/create">
                            <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                            New Campaign
                        </Link>
                    </Button>
                    <Button as-child variant="brand-outline-on-dark" size="sm">
                        <Link href="/growth/opportunities">
                            <Icon icon="heroicons:light-bulb" class="size-3.5" />
                            Find Offers
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <!-- Promote this week -->
        <PromoteThisWeekCard
            v-if="promoteThisWeek?.top_pick"
            :top-pick="promoteThisWeek.top_pick"
            :alternates="promoteThisWeek.alternates"
            :refreshed-at="promoteThisWeek.refreshed_at"
            compact
        />

        <!-- KPI strip -->
        <div class="grid shrink-0 grid-cols-2 gap-2 md:grid-cols-4 md:gap-3">
            <div
                v-for="stat in stats"
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
                        <span v-if="stat.trend" class="text-[0.65rem] font-medium" :class="stat.trend.positive ? 'text-blue-600' : 'text-rose-500'">
                            {{ stat.trend.positive ? '↑' : '↓' }}{{ stat.trend.value }}%
                        </span>
                        <span v-else class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom — separate cards -->
        <div class="grid gap-3 md:grid-cols-12 md:gap-4">

            <!-- Weekly chart -->
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm md:col-span-8">
                <div class="mb-3">
                    <p class="text-sm font-semibold">Weekly performance</p>
                    <p class="text-[0.65rem] text-muted-foreground">
                        {{ weekLeadTotal }} leads · {{ weekViewTotal }} views this week
                    </p>
                </div>
                <DashboardPerformanceChart :days="chartDays" />
            </div>

            <!-- Best funnels -->
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm md:col-span-4 md:min-h-[260px]">
                <div class="mb-3">
                    <p class="text-sm font-semibold">Best funnels</p>
                    <p class="text-[0.65rem] text-muted-foreground">Ranked by leads captured</p>
                </div>

                <div v-if="topItems.length === 0" class="flex items-center justify-center rounded-lg bg-muted/20 px-3 py-6 text-center">
                    <div>
                        <Icon icon="heroicons:chart-bar" class="mx-auto mb-2 size-7 text-muted-foreground/40" />
                        <p class="text-xs text-muted-foreground">No lead data yet</p>
                    </div>
                </div>

                <ul v-else class="flex flex-col gap-3">
                    <li
                        v-for="(funnel, idx) in topItems"
                        :key="funnel.id"
                        class="flex items-center gap-3"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[0.65rem] font-bold"
                            :class="idx === 0 ? 'chip-brand-active' : 'bg-muted text-muted-foreground'"
                        >{{ idx + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-xs font-medium">{{ funnel.name }}</p>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full fill-brand-gradient"
                                    :style="{ width: topItems[0].leads_count > 0 ? `${Math.max(8, Math.round((funnel.leads_count / topItems[0].leads_count) * 100))}%` : '0%' }"
                                />
                            </div>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums text-blue-700">{{ funnel.leads_count }}</span>
                    </li>
                </ul>
            </div>

            <!-- Latest activity -->
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm md:col-span-8">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold">Latest</p>
                        <p class="text-[0.65rem] text-muted-foreground">Recent campaigns & funnels</p>
                    </div>
                    <Link href="/campaigns" class="text-[0.65rem] font-medium text-blue-700 hover:underline">All campaigns</Link>
                </div>

                <div v-if="recentItems.length === 0" class="flex items-center gap-3 rounded-lg bg-muted/15 px-3 py-4">
                    <Icon icon="heroicons:rocket-launch" class="size-5 shrink-0 text-blue-600/50" />
                    <p class="text-xs text-muted-foreground">Start your first campaign to see activity here.</p>
                </div>

                <ul v-else class="space-y-1">
                    <li v-for="funnel in recentItems" :key="funnel.id">
                        <Link
                            :href="`/funnels/${funnel.id}/edit`"
                            class="flex items-center gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-blue-50/60"
                        >
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                                <Icon icon="heroicons:rocket-launch" class="size-4 text-blue-600" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-medium">{{ funnel.name }}</p>
                                <p class="text-[0.65rem] text-muted-foreground">{{ fmtDate(funnel.created_at) }}</p>
                            </div>
                            <Badge
                                variant="outline"
                                class="shrink-0 px-1.5 py-0 text-[0.6rem] capitalize"
                                :class="funnel.status === 'published' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                            >
                                {{ funnel.status }}
                            </Badge>
                        </Link>
                    </li>
                </ul>
            </div>

            <!-- Shortcuts -->
            <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm md:col-span-4">
                <div class="mb-3">
                    <p class="text-sm font-semibold">Shortcuts</p>
                    <p class="text-[0.65rem] text-muted-foreground">Jump to a tool</p>
                </div>
                <div class="flex flex-col gap-2">
                    <Link
                        v-for="link in quickLinks"
                        :key="link.href"
                        :href="link.href"
                        class="flex items-center gap-2.5 rounded-lg border border-border/50 bg-muted/15 px-3 py-2 text-xs font-medium text-foreground transition-colors hover:border-blue-300/50 hover:bg-blue-50 hover:text-blue-800"
                    >
                        <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-blue-500/10">
                            <Icon :icon="link.icon" class="size-3.5 text-blue-600" />
                        </div>
                        {{ link.label }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
