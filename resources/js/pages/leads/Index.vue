<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface LeadRow {
    id: number;
    name: string;
    email: string;
    source: string;
    created_at: string;
    funnel?: { id: number; name: string; slug: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginator {
    data: LeadRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface FunnelOption {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    leads: Paginator;
    funnels: FunnelOption[];
    stats: { total: number; this_week: number; funnel_count: number };
    filters: { search: string; funnel_id: number | null };
}>();

const search = ref(props.filters.search ?? '');
const funnelId = ref<number | null>(props.filters.funnel_id ?? null);

let debounce: ReturnType<typeof setTimeout>;

watch([search, funnelId], ([s, f]) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/leads', { search: s || undefined, funnel_id: f || undefined }, {
            preserveState: true,
            replace: true,
        });
    }, 350);
});

const statCards = computed(() => [
    { label: 'Total', value: props.stats.total, sub: 'leads', icon: 'heroicons:users' },
    { label: 'This week', value: props.stats.this_week, sub: 'new', icon: 'heroicons:arrow-trending-up' },
    { label: 'Funnels', value: props.stats.funnel_count, sub: 'with leads', icon: 'heroicons:funnel' },
]);

function fmtDate(dt: string): string {
    return new Date(dt).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function fmtTime(dt: string): string {
    return new Date(dt).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function avatarInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2);
}

const AVATAR_COLORS = [
    ['#0d9488', '#ecfdf5'],
    ['#0891b2', '#ecfeff'],
    ['#6366f1', '#eef2ff'],
    ['#059669', '#ecfdf5'],
    ['#d97706', '#fffbeb'],
];

function avatarColor(id: number): { bg: string; color: string } {
    const [color, bg] = AVATAR_COLORS[id % AVATAR_COLORS.length];
    return { bg, color };
}

function exportCsv(): void {
    const params = new URLSearchParams();
    if (search.value) params.set('search', search.value);
    if (funnelId.value) params.set('funnel_id', String(funnelId.value));
    params.set('export', 'csv');
    window.location.href = `/leads?${params.toString()}`;
}

function clearFilters(): void {
    search.value = '';
    funnelId.value = null;
}

const hasFilters = computed(() => search.value !== '' || funnelId.value !== null);
</script>

<template>
    <Head title="Leads" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Leads</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Opt-in registrations captured across your funnels and campaigns.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button variant="brand-outline" size="sm" class="gap-1.5" @click="exportCsv">
                    <Icon icon="heroicons:arrow-down-tray" class="size-3.5" />
                    Export CSV
                </Button>
                <Button as-child variant="brand" size="sm">
                    <Link href="/funnels">
                        <Icon icon="heroicons:video-camera" class="size-3.5" />
                        View funnels
                    </Link>
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-3 md:gap-3">
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
                        <span class="text-xl font-bold leading-none tabular-nums">{{ stat.value.toLocaleString() }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters + table -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-border/60 p-3 md:flex-row md:items-center md:justify-between md:p-4">
                <div>
                    <p class="text-sm font-semibold text-foreground">All leads</p>
                    <p v-if="leads.total > 0" class="text-xs text-muted-foreground">
                        {{ leads.from }}–{{ leads.to }} of {{ leads.total.toLocaleString() }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-[12rem] flex-1 sm:flex-none">
                        <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input v-model="search" placeholder="Search name or email…" class="pl-9" />
                    </div>
                    <select
                        v-model="funnelId"
                        class="h-9 rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm"
                    >
                        <option :value="null">All funnels</option>
                        <option v-for="f in funnels" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                    <Button
                        v-if="hasFilters"
                        variant="ghost"
                        size="sm"
                        class="text-teal-700"
                        @click="clearFilters"
                    >
                        <Icon icon="heroicons:x-mark" class="size-3.5" />
                        Clear
                    </Button>
                </div>
            </div>

            <!-- Empty -->
            <div v-if="leads.data.length === 0" class="flex flex-col items-center justify-center gap-4 px-6 py-14 text-center">
                <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                    <Icon icon="heroicons:users" class="size-7 text-teal-600/60" />
                </div>
                <div>
                    <p class="font-semibold text-foreground">
                        {{ hasFilters ? 'No leads match your filters' : 'No leads yet' }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ hasFilters ? 'Try adjusting your search or funnel filter.' : 'Publish a funnel and share the opt-in link to start collecting leads.' }}
                    </p>
                </div>
                <Button v-if="hasFilters" variant="brand-outline" size="sm" @click="clearFilters">Clear filters</Button>
                <Button v-else as-child variant="brand" size="sm">
                    <Link href="/campaigns/create">Create a campaign</Link>
                </Button>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border/60 bg-teal-50/20">
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">#</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Lead</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Funnel</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Source</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="(lead, i) in leads.data" :key="lead.id" class="transition-colors hover:bg-teal-50/20">
                            <td class="w-10 px-4 py-3 text-xs tabular-nums text-muted-foreground">
                                {{ (leads.from ?? 0) + i }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                        :style="{ background: avatarColor(lead.id).bg, color: avatarColor(lead.id).color }"
                                    >
                                        {{ avatarInitials(lead.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="max-w-[180px] truncate font-medium text-foreground">{{ lead.name }}</p>
                                        <p class="max-w-[180px] truncate text-xs text-muted-foreground">{{ lead.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="lead.funnel"
                                    :href="`/funnels/${lead.funnel.id}/edit`"
                                    class="group inline-flex max-w-[160px] items-center gap-1 truncate text-xs font-medium text-foreground hover:text-teal-700"
                                >
                                    <Icon icon="heroicons:funnel" class="size-3 shrink-0 text-teal-600" />
                                    {{ lead.funnel.name }}
                                </Link>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant="outline"
                                    class="text-[0.65rem] capitalize"
                                    :class="lead.source === 'optin' ? 'border-teal-200 bg-teal-50 text-teal-700' : ''"
                                >
                                    {{ lead.source }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-xs text-foreground">{{ fmtDate(lead.created_at) }}</div>
                                <div class="text-[0.65rem] text-muted-foreground">{{ fmtTime(lead.created_at) }}</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="leads.last_page > 1" class="flex items-center justify-between border-t border-border/60 px-4 py-3">
                <p class="text-xs text-muted-foreground">
                    Page {{ leads.current_page }} of {{ leads.last_page }}
                </p>
                <div class="flex items-center gap-1">
                    <button
                        v-for="link in leads.links"
                        :key="link.label"
                        :disabled="!link.url"
                        class="inline-flex h-7 min-w-7 items-center justify-center rounded-lg border px-1.5 text-xs transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                        :class="link.active
                            ? 'border-teal-600 bg-teal-600 text-white'
                            : 'border-border/60 bg-white text-foreground hover:bg-teal-50'"
                        @click="link.url && router.get(link.url, {}, { preserveState: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
