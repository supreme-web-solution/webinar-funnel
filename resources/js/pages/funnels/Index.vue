<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface FunnelItem {
    id: number;
    name: string;
    slug: string;
    status: string;
    public_url: string | null;
    published_at: string | null;
    created_at: string;
    leads_count: number;
    kind?: 'webinar' | 'funnel';
    campaign_name?: string | null;
    template?: { name: string; category: string } | null;
}

const props = defineProps<{
    funnels: FunnelItem[];
    stats: {
        total: number;
        published: number;
        draft: number;
        archived: number;
    };
}>();

const search = ref('');
const activeFilter = ref<'all' | 'published' | 'draft' | 'archived'>('all');

const filtered = computed(() => {
    let list = props.funnels;

    if (activeFilter.value !== 'all') {
        list = list.filter((f) => f.status === activeFilter.value);
    }

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(
            (f) =>
                f.name.toLowerCase().includes(q)
                || f.slug.toLowerCase().includes(q)
                || (f.template?.name ?? '').toLowerCase().includes(q)
                || (f.campaign_name ?? '').toLowerCase().includes(q),
        );
    }

    return list;
});

const totalLeads = computed(() => props.funnels.reduce((sum, f) => sum + f.leads_count, 0));
const webinarCount = computed(() => props.funnels.filter((f) => f.kind === 'webinar').length);

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, sub: 'funnels', icon: 'heroicons:video-camera' },
    { label: 'Published', value: props.stats.published, sub: 'live', icon: 'heroicons:globe-alt' },
    { label: 'Drafts', value: props.stats.draft, sub: 'in progress', icon: 'heroicons:pencil-square' },
    { label: 'Leads', value: totalLeads.value, sub: 'captured', icon: 'heroicons:users' },
]);

const filterTabs: Array<{ key: 'all' | 'published' | 'draft' | 'archived'; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'published', label: 'Published' },
    { key: 'draft', label: 'Draft' },
    { key: 'archived', label: 'Archived' },
];

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function statusBadgeClass(status: string): string {
    if (status === 'published') return 'border-teal-200 bg-teal-50 text-teal-700';
    if (status === 'archived') return 'border-slate-200 bg-slate-50 text-slate-700';
    return 'border-amber-200 bg-amber-50 text-amber-700';
}

function funnelIcon(funnel: FunnelItem): string {
    if (funnel.kind === 'webinar') return 'heroicons:video-camera';
    if (funnel.status === 'published') return 'heroicons:globe-alt';
    if (funnel.status === 'archived') return 'heroicons:archive-box';
    return 'heroicons:pencil-square';
}
</script>

<template>
    <Head title="Webinars" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Webinars & funnels</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Campaign webinar rooms and legacy builds · {{ stats.published }} published · {{ webinarCount }} webinar{{ webinarCount === 1 ? '' : 's' }}
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/funnels/create?scratch=1">
                        <Icon icon="heroicons:sparkles" class="size-3.5" />
                        Legacy scratch
                    </Link>
                </Button>
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">
                        <Icon icon="heroicons:plus" class="size-3.5" />
                        New campaign
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
        <div class="flex flex-col gap-3 rounded-xl border border-border/60 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between md:p-4">
            <div class="relative max-w-md flex-1">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search webinars & funnels…" class="pl-9" />
            </div>
            <div class="flex gap-1.5">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeFilter === tab.key
                        ? 'bg-teal-600 text-white shadow-sm'
                        : 'border border-border/60 bg-muted/20 text-muted-foreground hover:bg-teal-50 hover:text-teal-800'"
                    @click="activeFilter = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Empty: no funnels -->
        <div
            v-if="stats.total === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-teal-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                <Icon icon="heroicons:video-camera" class="size-7 text-teal-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No webinars yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Create a campaign to get a webinar room, or build one from a legacy template.
                </p>
            </div>
            <div class="flex flex-wrap justify-center gap-2">
                <Button as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">New campaign</Link>
                </Button>
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/templates">Browse templates</Link>
                </Button>
            </div>
        </div>

        <!-- Empty: filtered -->
        <div
            v-else-if="filtered.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/60 bg-white px-6 py-12 text-center shadow-sm"
        >
            <Icon icon="heroicons:magnifying-glass" class="size-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No funnels match your filter.</p>
            <Button variant="ghost" size="sm" class="text-teal-700" @click="search = ''; activeFilter = 'all'">
                Clear filters
            </Button>
        </div>

        <!-- List -->
        <div v-else class="grid gap-3">
            <article
                v-for="funnel in filtered"
                :key="funnel.id"
                class="group overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-teal-200/60 hover:shadow-md"
            >
                <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10">
                            <Icon :icon="funnelIcon(funnel)" class="size-5 text-teal-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="`/funnels/${funnel.id}/edit`"
                                    class="truncate text-sm font-semibold text-foreground hover:text-teal-800 hover:underline"
                                >
                                    {{ funnel.name }}
                                </Link>
                                <Badge
                                    v-if="funnel.kind === 'webinar'"
                                    variant="outline"
                                    class="border-violet-200 bg-violet-50 text-[0.6rem] text-violet-700"
                                >
                                    Webinar
                                </Badge>
                                <Badge variant="outline" class="capitalize text-[0.6rem]" :class="statusBadgeClass(funnel.status)">
                                    {{ funnel.status }}
                                </Badge>
                            </div>
                            <p class="mt-0.5 truncate font-mono text-[0.65rem] text-muted-foreground">/{{ funnel.slug }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:users" class="size-3 text-teal-600" />
                                    {{ funnel.leads_count }} leads
                                </span>
                                <span v-if="funnel.campaign_name" class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:rocket-launch" class="size-3 text-teal-600" />
                                    {{ funnel.campaign_name }}
                                </span>
                                <span v-if="funnel.template" class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] capitalize text-muted-foreground">
                                    <Icon icon="heroicons:rectangle-stack" class="size-3 text-teal-600" />
                                    {{ funnel.template.name }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:calendar" class="size-3" />
                                    {{ fmtDate(funnel.published_at ?? funnel.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2 lg:flex-col lg:items-stretch xl:flex-row">
                        <Button
                            v-if="funnel.public_url"
                            as-child
                            size="sm"
                            variant="brand-outline"
                            class="min-w-[7rem]"
                        >
                            <a :href="funnel.public_url" target="_blank" rel="noopener noreferrer">
                                <Icon icon="heroicons:globe-alt" class="size-3.5" />
                                View live
                            </a>
                        </Button>
                        <Button as-child size="sm" variant="brand" class="min-w-[7rem]">
                            <Link :href="`/funnels/${funnel.id}/edit`">
                                <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                Edit
                            </Link>
                        </Button>
                        <Button as-child size="sm" variant="ghost" class="text-muted-foreground hover:text-teal-700">
                            <Link :href="`/funnels/${funnel.id}/chat`">
                                <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-3.5" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
