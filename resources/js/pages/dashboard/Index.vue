<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

const props = defineProps<{
    metrics: {
        funnelCount: number;
        publishedCount: number;
        draftCount: number;
        leadCount: number;
        recentLeads: number;
        previousWeekLeads: number;
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
}>();

const page = usePage();

const userName = computed(() => {
    const user = (page.props.auth as { user?: { name: string } })?.user;

    return user?.name?.split(' ')[0] ?? 'there';
});

const currentHour = new Date().getHours();

const greeting = computed(() => {
    if (currentHour < 12) {
        return 'Good morning';
    }

    if (currentHour < 17) {
        return 'Good afternoon';
    }

    return 'Good evening';
});

const currentDate = computed(() =>
    new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }),
);

function leadTrend(): { value: number; positive: boolean } {
    const prev = props.metrics.previousWeekLeads;
    const curr = props.metrics.recentLeads;

    if (prev === 0) {
        return { value: curr > 0 ? 100 : 0, positive: true };
    }

    const pct = Math.round(((curr - prev) / prev) * 100);

    return { value: Math.abs(pct), positive: pct >= 0 };
}

const trend = computed(() => leadTrend());

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'published') {
        return 'default';
    }

    if (status === 'draft') {
        return 'secondary';
    }

    return 'outline';
}

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">
                    {{ greeting }}, {{ userName }} 👋
                </h1>
                <p class="text-sm text-muted-foreground mt-0.5">{{ currentDate }}</p>
            </div>
            <Button as-child size="sm" class="self-start sm:self-auto gap-1.5 bg-primary text-primary-foreground hover:opacity-90 shadow-sm">
                <Link href="/funnels/create">
                    <Icon icon="heroicons:plus" class="size-4" />
                    New Funnel
                </Link>
            </Button>
        </div>

        <!-- ── KPI metric cards ── -->
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">

            <!-- Total Funnels -->
            <Card class="border shadow-sm hover:shadow-md transition-shadow">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        Total Funnels
                    </CardTitle>
                    <div class="flex size-8 items-center justify-center rounded-lg bg-primary/10">
                        <Icon icon="heroicons:funnel" class="size-4 text-primary" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold text-foreground">{{ metrics.funnelCount }}</p>
                    <p class="text-xs text-muted-foreground mt-1">
                        <span class="text-emerald-600 font-medium">{{ metrics.publishedCount }} live</span>
                        &nbsp;·&nbsp;
                        <span>{{ metrics.draftCount }} draft</span>
                    </p>
                </CardContent>
            </Card>

            <!-- Published -->
            <Card class="border shadow-sm hover:shadow-md transition-shadow">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        Published
                    </CardTitle>
                    <div class="flex size-8 items-center justify-center rounded-lg" style="background: rgba(64,224,208,0.12)">
                        <Icon icon="heroicons:globe-alt" class="size-4" style="color:#40E0D0" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold text-foreground">{{ metrics.publishedCount }}</p>
                    <p class="text-xs text-muted-foreground mt-1">
                        <span v-if="metrics.funnelCount > 0">
                            {{ Math.round((metrics.publishedCount / metrics.funnelCount) * 100) }}% of funnels live
                        </span>
                        <span v-else>No funnels yet</span>
                    </p>
                </CardContent>
            </Card>

            <!-- Total Leads -->
            <Card class="border shadow-sm hover:shadow-md transition-shadow">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        Total Leads
                    </CardTitle>
                    <div class="flex size-8 items-center justify-center rounded-lg" style="background: rgba(255,173,0,0.12)">
                        <Icon icon="heroicons:users" class="size-4" style="color:#FFAD00" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold text-foreground">{{ metrics.leadCount }}</p>
                    <p class="text-xs mt-1">
                        <span
                            class="inline-flex items-center gap-0.5 font-medium"
                            :class="trend.positive ? 'text-emerald-600' : 'text-rose-500'"
                        >
                            <Icon
                                :icon="trend.positive ? 'heroicons:arrow-trending-up' : 'heroicons:arrow-trending-down'"
                                class="size-3"
                            />
                            {{ trend.value }}%
                        </span>
                        <span class="text-muted-foreground"> vs last week</span>
                    </p>
                </CardContent>
            </Card>

            <!-- This Week -->
            <Card class="border shadow-sm hover:shadow-md transition-shadow">
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                        This Week
                    </CardTitle>
                    <div class="flex size-8 items-center justify-center rounded-lg bg-violet-50">
                        <Icon icon="heroicons:calendar-days" class="size-4 text-violet-500" />
                    </div>
                </CardHeader>
                <CardContent>
                    <p class="text-3xl font-bold text-foreground">{{ metrics.recentLeads }}</p>
                    <p class="text-xs text-muted-foreground mt-1">New leads (last 7 days)</p>
                </CardContent>
            </Card>
        </div>

        <!-- ── Main content grid ── -->
        <div class="grid gap-6 lg:grid-cols-3">

            <!-- Recent Funnels table – 2/3 width -->
            <div class="lg:col-span-2">
                <Card class="border shadow-sm h-full">
                    <CardHeader class="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle class="text-base font-semibold">Recent Funnels</CardTitle>
                            <CardDescription class="text-xs">Your latest funnel activity</CardDescription>
                        </div>
                        <Button variant="outline" size="sm" as-child class="text-xs h-7 px-3">
                            <Link href="/funnels">View all</Link>
                        </Button>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="recentFunnels.length === 0" class="flex flex-col items-center justify-center py-14 gap-3 text-muted-foreground">
                            <Icon icon="heroicons:funnel" class="size-10 opacity-30" />
                            <p class="text-sm">No funnels yet. Create your first one!</p>
                            <Button as-child size="sm" class="bg-primary text-primary-foreground hover:opacity-90">
                                <Link href="/funnels/create">Create Funnel</Link>
                            </Button>
                        </div>
                        <div v-else class="divide-y divide-border/60">
                            <div
                                v-for="funnel in recentFunnels"
                                :key="funnel.id"
                                class="flex items-center gap-3 px-5 py-3 hover:bg-muted/40 transition-colors group"
                            >
                                <!-- Icon col -->
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <Icon icon="heroicons:funnel" class="size-4 text-primary" />
                                </div>
                                <!-- Info col -->
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-foreground truncate">{{ funnel.name }}</p>
                                    <p class="text-xs text-muted-foreground truncate">
                                        {{ funnel.template?.name ?? 'Custom' }}
                                        <span class="mx-1">·</span>
                                        {{ fmtDate(funnel.created_at) }}
                                    </p>
                                </div>
                                <!-- Status badge -->
                                <Badge
                                    :variant="statusVariant(funnel.status)"
                                    class="shrink-0 capitalize text-[0.65rem] px-2 py-0.5"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 border-emerald-200': funnel.status === 'published',
                                        'bg-amber-50 text-amber-700 border-amber-200': funnel.status === 'draft',
                                    }"
                                >
                                    {{ funnel.status }}
                                </Badge>
                                <!-- Edit link -->
                                <Link
                                    :href="`/funnels/${funnel.id}/edit`"
                                    class="shrink-0 opacity-0 group-hover:opacity-100 flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-all"
                                    title="Edit funnel"
                                >
                                    <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Right column: Quick actions + Top funnels -->
            <div class="flex flex-col gap-4">

                <!-- Quick actions -->
                <Card class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Quick Actions</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-2">
                        <Button as-child variant="outline" class="justify-start gap-2.5 h-9 text-sm font-medium hover:bg-primary/5 hover:border-primary/30 transition-colors">
                            <Link href="/funnels/create">
                                <Icon icon="heroicons:plus-circle" class="size-4 text-primary" />
                                Create New Funnel
                            </Link>
                        </Button>
                        <Button as-child variant="outline" class="justify-start gap-2.5 h-9 text-sm font-medium hover:bg-primary/5 hover:border-primary/30 transition-colors">
                            <Link href="/templates">
                                <Icon icon="heroicons:rectangle-stack" class="size-4 text-primary" />
                                Browse Templates
                            </Link>
                        </Button>
                        <Button as-child variant="outline" class="justify-start gap-2.5 h-9 text-sm font-medium hover:bg-primary/5 hover:border-primary/30 transition-colors">
                            <Link href="/leads">
                                <Icon icon="heroicons:users" class="size-4 text-primary" />
                                View All Leads
                            </Link>
                        </Button>
                        <Button as-child variant="outline" class="justify-start gap-2.5 h-9 text-sm font-medium hover:bg-primary/5 hover:border-primary/30 transition-colors">
                            <Link href="/integrations">
                                <Icon icon="heroicons:puzzle-piece" class="size-4 text-primary" />
                                Manage Integrations
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <!-- Top funnels by leads -->
                <Card class="border shadow-sm flex-1">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-semibold">Top Funnels</CardTitle>
                        <CardDescription class="text-xs">Ranked by lead count</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="topFunnels.length === 0" class="flex flex-col items-center py-6 gap-2 text-muted-foreground">
                            <Icon icon="heroicons:chart-bar" class="size-8 opacity-30" />
                            <p class="text-xs text-center">Publish funnels and capture leads<br>to see rankings here.</p>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="(funnel, idx) in topFunnels"
                                :key="funnel.id"
                                class="flex items-center gap-2.5"
                            >
                                <!-- Rank -->
                                <span
                                    class="flex size-5 shrink-0 items-center justify-center rounded-full text-[0.6rem] font-bold"
                                    :class="{
                                        'bg-amber-400/20 text-amber-600': idx === 0,
                                        'bg-slate-100 text-slate-500': idx > 0,
                                    }"
                                >{{ idx + 1 }}</span>
                                <!-- Name -->
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-foreground truncate">{{ funnel.name }}</p>
                                    <!-- Mini bar -->
                                    <div class="mt-0.5 h-1 rounded-full bg-muted overflow-hidden">
                                        <div
                                            class="h-full rounded-full"
                                            style="background: #40E0D0"
                                            :style="{ width: topFunnels[0].leads_count > 0 ? `${Math.round((funnel.leads_count / topFunnels[0].leads_count) * 100)}%` : '0%' }"
                                        />
                                    </div>
                                </div>
                                <!-- Count -->
                                <span class="text-xs font-semibold text-foreground shrink-0">{{ funnel.leads_count }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </div>

        <!-- ── Getting started banner (shown when no funnels) ── -->
        <div
            v-if="metrics.funnelCount === 0"
            class="rounded-xl border-2 border-dashed border-primary/30 bg-primary/5 p-6 flex flex-col sm:flex-row items-center gap-4"
        >
            <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
                <Icon icon="heroicons:rocket-launch" class="size-6 text-primary" />
            </div>
            <div class="text-center sm:text-left">
                <h3 class="font-semibold text-foreground">Ready to launch your first webinar funnel?</h3>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Browse 50+ pre-built templates, customise your opt-in page, connect your ESP, and go live in minutes.
                </p>
            </div>
            <Button as-child class="shrink-0 sm:ml-auto bg-primary text-primary-foreground hover:opacity-90 shadow-sm">
                <Link href="/templates">Explore Templates</Link>
            </Button>
        </div>

    </div>
</template>
