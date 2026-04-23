<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

/* ─── Types ─────────────────────────────────────────────────────────────── */
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

/* ─── Local filter state ─────────────────────────────────────────────────── */
const search   = ref(props.filters.search ?? '');
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

/* ─── Helpers ────────────────────────────────────────────────────────────── */
function fmtDate(dt: string): string {
    return new Date(dt).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: 'numeric',
    });
}

function fmtTime(dt: string): string {
    return new Date(dt).toLocaleTimeString('en-US', {
        hour: '2-digit', minute: '2-digit',
    });
}

function avatarInitials(name: string): string {
    return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2);
}

const AVATAR_COLORS = [
    ['#40E0D0', '#060d1a'],
    ['#FFAD00', '#1a0d00'],
    ['#6366f1', '#0e0d2a'],
    ['#10b981', '#031a10'],
    ['#f43f5e', '#1a030a'],
];

function avatarColor(id: number): { bg: string; color: string } {
    const [bg, color] = AVATAR_COLORS[id % AVATAR_COLORS.length];

    return { bg, color };
}

function exportCsv(): void {
    const params = new URLSearchParams();

    if (search.value) {
        params.set('search', search.value);
    }

    if (funnelId.value) {
        params.set('funnel_id', String(funnelId.value));
    }

    params.set('export', 'csv');
    window.location.href = `/leads?${params.toString()}`;
}

function clearFilters(): void {
    search.value = '';
    funnelId.value = null;
}

const hasFilters = () => search.value !== '' || funnelId.value !== null;
</script>

<template>
    <Head title="Leads" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">Leads</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    All opt-in registrations captured across your funnels.
                </p>
            </div>

            <Button
                variant="outline"
                size="sm"
                class="gap-1.5 shrink-0 self-start"
                @click="exportCsv"
            >
                <Icon icon="heroicons:arrow-down-tray" class="size-3.5" />
                Export CSV
            </Button>
        </div>

        <!-- ── Stats ── -->
        <div class="grid grid-cols-3 gap-3">
            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Total Leads</p>
                            <p class="text-2xl font-bold text-foreground mt-1">{{ stats.total.toLocaleString() }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
                            <Icon icon="heroicons:users" class="size-5 text-primary" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">This Week</p>
                            <p class="text-2xl font-bold text-[#40E0D0] mt-1">{{ stats.this_week.toLocaleString() }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-[#40E0D0]/10">
                            <Icon icon="heroicons:arrow-trending-up" class="size-5 text-[#40E0D0]" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="border shadow-sm">
                <CardContent class="p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-xs text-muted-foreground">Funnels w/ Leads</p>
                            <p class="text-2xl font-bold text-[#FFAD00] mt-1">{{ stats.funnel_count }}</p>
                        </div>
                        <div class="flex size-9 items-center justify-center rounded-lg bg-[#FFAD00]/10">
                            <Icon icon="heroicons:funnel" class="size-5 text-[#FFAD00]" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── Filters ── -->
        <Card class="border shadow-sm">
            <CardHeader class="pb-3 pt-4 px-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle class="text-sm font-semibold">
                        All Leads
                        <span v-if="leads.total > 0" class="ml-1.5 text-xs font-normal text-muted-foreground">
                            {{ leads.from }}–{{ leads.to }} of {{ leads.total.toLocaleString() }}
                        </span>
                    </CardTitle>

                    <div class="flex items-center gap-2">
                        <!-- Search -->
                        <div class="relative">
                            <Icon
                                icon="heroicons:magnifying-glass"
                                class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none"
                            />
                            <Input
                                v-model="search"
                                type="text"
                                placeholder="Search name or email…"
                                class="h-8 pl-8 text-xs w-48 sm:w-60"
                            />
                        </div>

                        <!-- Funnel filter -->
                        <select
                            v-model="funnelId"
                            class="h-8 rounded-md border border-input bg-background px-2.5 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        >
                            <option :value="null">All funnels</option>
                            <option v-for="f in funnels" :key="f.id" :value="f.id">{{ f.name }}</option>
                        </select>

                        <!-- Clear -->
                        <Button
                            v-if="hasFilters()"
                            variant="ghost"
                            size="sm"
                            class="h-8 px-2 text-xs gap-1 text-muted-foreground"
                            @click="clearFilters"
                        >
                            <Icon icon="heroicons:x-mark" class="size-3.5" />
                            Clear
                        </Button>
                    </div>
                </div>
            </CardHeader>

            <!-- Table -->
            <CardContent class="p-0">
                <!-- Empty state -->
                <div v-if="leads.data.length === 0" class="flex flex-col items-center justify-center py-14 text-center gap-3">
                    <div class="flex size-14 items-center justify-center rounded-full bg-primary/10">
                        <Icon icon="heroicons:users" class="size-7 text-primary" />
                    </div>
                    <div>
                        <p class="font-semibold text-foreground">
                            {{ hasFilters() ? 'No leads match your filters' : 'No leads yet' }}
                        </p>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ hasFilters() ? 'Try adjusting your search or funnel filter.' : 'Publish a funnel and share the opt-in link to start collecting leads.' }}
                        </p>
                    </div>
                    <Button v-if="hasFilters()" variant="outline" size="sm" class="mt-1 gap-1.5" @click="clearFilters">
                        <Icon icon="heroicons:x-mark" class="size-3.5" />
                        Clear filters
                    </Button>
                    <Button v-else as-child size="sm" class="mt-1 gap-1.5 bg-primary text-primary-foreground hover:opacity-90">
                        <Link href="/funnels">Go to Funnels</Link>
                    </Button>
                </div>

                <!-- Data table -->
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/30">
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">#</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Lead</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Funnel</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Source</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-muted-foreground">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(lead, i) in leads.data"
                                :key="lead.id"
                                class="hover:bg-muted/20 transition-colors"
                            >
                                <!-- Row number -->
                                <td class="px-4 py-3 text-xs text-muted-foreground tabular-nums w-10">
                                    {{ (leads.from ?? 0) + i }}
                                </td>

                                <!-- Lead avatar + name/email -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex size-8 shrink-0 items-center justify-center rounded-full text-[0.65rem] font-bold"
                                            :style="{ background: avatarColor(lead.id).bg, color: avatarColor(lead.id).color }"
                                        >
                                            {{ avatarInitials(lead.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium text-foreground truncate max-w-[180px]">{{ lead.name }}</p>
                                            <p class="text-xs text-muted-foreground truncate max-w-[180px]">{{ lead.email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Funnel -->
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="lead.funnel"
                                        :href="`/funnels/${lead.funnel.id}/edit`"
                                        class="group inline-flex items-center gap-1 text-xs font-medium text-foreground hover:text-primary transition-colors"
                                    >
                                        <Icon icon="heroicons:funnel" class="size-3 text-muted-foreground group-hover:text-primary" />
                                        <span class="truncate max-w-[140px]">{{ lead.funnel.name }}</span>
                                    </Link>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>

                                <!-- Source -->
                                <td class="px-4 py-3">
                                    <Badge
                                        class="text-[0.65rem] capitalize px-1.5 py-0"
                                        :class="lead.source === 'optin'
                                            ? 'bg-[#40E0D0]/10 text-[#40E0D0] border-[#40E0D0]/25'
                                            : 'bg-muted text-muted-foreground border-border'"
                                    >
                                        {{ lead.source }}
                                    </Badge>
                                </td>

                                <!-- Date -->
                                <td class="px-4 py-3">
                                    <div class="text-xs text-foreground">{{ fmtDate(lead.created_at) }}</div>
                                    <div class="text-[0.65rem] text-muted-foreground">{{ fmtTime(lead.created_at) }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div
                    v-if="leads.last_page > 1"
                    class="flex items-center justify-between border-t px-4 py-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Page {{ leads.current_page }} of {{ leads.last_page }}
                    </p>

                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in leads.links"
                            :key="link.label"
                            :disabled="!link.url"
                            class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="link.active
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-background text-foreground border-border hover:bg-muted'"
                            @click="link.url && router.get(link.url, {}, { preserveState: true })"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

    </div>
</template>
