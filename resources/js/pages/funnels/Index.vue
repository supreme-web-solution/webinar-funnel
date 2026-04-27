<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface FunnelItem {
    id: number;
    name: string;
    slug: string;
    status: string;
    published_at: string | null;
    created_at: string;
    leads_count: number;
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
                f.name.toLowerCase().includes(q) ||
                f.slug.toLowerCase().includes(q) ||
                (f.template?.name ?? '').toLowerCase().includes(q),
        );
    }

    return list;
});

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

const filterTabs: Array<{ key: 'all' | 'published' | 'draft' | 'archived'; label: string; count: number }> = [
    { key: 'all', label: 'All', count: props.stats.total },
    { key: 'published', label: 'Published', count: props.stats.published },
    { key: 'draft', label: 'Draft', count: props.stats.draft },
    { key: 'archived', label: 'Archived', count: props.stats.archived },
];
</script>

<template>
    <Head title="My Funnels" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">My Funnels</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    {{ stats.total }} funnel{{ stats.total !== 1 ? 's' : '' }} ·
                    {{ stats.published }} published ·
                    {{ stats.draft }} draft ·
                    {{ stats.archived }} archived
                </p>
            </div>
            <Button as-child size="sm" class="self-start sm:self-auto gap-1.5 bg-primary text-primary-foreground hover:opacity-90 shadow-sm">
                <Link href="/funnels/create">
                    <Icon icon="heroicons:plus" class="size-4" />
                    New Funnel
                </Link>
            </Button>
        </div>

        <!-- ── Stat cards ── -->
        <div class="grid gap-3 grid-cols-3">
            <Card class="border shadow-sm">
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10">
                        <Icon icon="heroicons:funnel" class="size-4 text-primary" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground leading-none">{{ stats.total }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Total</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="border shadow-sm">
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-emerald-50">
                        <Icon icon="heroicons:globe-alt" class="size-4 text-emerald-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground leading-none">{{ stats.published }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Published</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="border shadow-sm">
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="flex size-9 items-center justify-center rounded-lg bg-amber-50">
                        <Icon icon="heroicons:pencil-square" class="size-4 text-amber-600" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold text-foreground leading-none">{{ stats.draft }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Draft</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── Filters + search ── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Filter tabs -->
            <div class="flex gap-1 rounded-lg border bg-muted/40 p-0.5">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeFilter === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'"
                    @click="activeFilter = tab.key"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[0.6rem] font-bold leading-none"
                        :class="activeFilter === tab.key ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- Search -->
            <div class="relative sm:ml-auto sm:w-64">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                <Input v-model="search" placeholder="Search funnels…" class="pl-9 h-9 text-sm" />
            </div>
        </div>

        <!-- ── Funnels list ── -->
        <div v-if="filtered.length > 0" class="flex flex-col gap-2">
            <div
                v-for="funnel in filtered"
                :key="funnel.id"
                class="group flex items-center gap-4 rounded-xl border bg-card px-4 py-3.5 shadow-sm hover:shadow-md hover:border-primary/20 transition-all"
            >
                <!-- Icon -->
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl transition-colors"
                        :class="funnel.status === 'published'
                            ? 'bg-emerald-50'
                            : funnel.status === 'archived'
                                ? 'bg-slate-100'
                                : 'bg-amber-50'"
                >
                    <Icon
                        :icon="funnel.status === 'published'
                            ? 'heroicons:globe-alt'
                            : funnel.status === 'archived'
                                ? 'heroicons:archive-box'
                                : 'heroicons:pencil-square'"
                        class="size-5"
                        :class="funnel.status === 'published'
                            ? 'text-emerald-600'
                            : funnel.status === 'archived'
                                ? 'text-slate-600'
                                : 'text-amber-600'"
                    />
                </div>

                <!-- Main info -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-foreground truncate">{{ funnel.name }}</p>
                        <Badge
                            class="capitalize text-[0.6rem] px-2 py-0.5 shrink-0"
                            :class="funnel.status === 'published'
                                ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                : funnel.status === 'archived'
                                    ? 'bg-slate-100 text-slate-700 border-slate-200'
                                    : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >
                            <span
                                v-if="funnel.status === 'published'"
                                class="mr-1 inline-block size-1.5 rounded-full bg-emerald-500"
                            />
                            {{ funnel.status }}
                        </Badge>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap mt-0.5">
                        <p class="text-xs text-muted-foreground font-mono">/{{ funnel.slug }}</p>
                        <span v-if="funnel.template" class="text-muted-foreground/50">·</span>
                        <p v-if="funnel.template" class="text-xs text-muted-foreground capitalize">
                            {{ funnel.template.name }}
                        </p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="hidden md:flex items-center gap-6 shrink-0">
                    <!-- Lead count -->
                    <div class="text-center">
                        <p class="text-sm font-bold text-foreground">{{ funnel.leads_count }}</p>
                        <p class="text-[0.6rem] text-muted-foreground">Leads</p>
                    </div>
                    <!-- Date -->
                    <div class="text-right">
                        <p class="text-xs text-muted-foreground">
                            {{ funnel.published_at ? 'Published' : 'Created' }}
                        </p>
                        <p class="text-xs font-medium text-foreground">
                            {{ fmtDate(funnel.published_at ?? funnel.created_at) }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1.5 shrink-0">
                    <Button
                        as-child
                        size="sm"
                        class="h-8 px-3 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 opacity-80 group-hover:opacity-100 transition-opacity"
                    >
                        <Link :href="`/funnels/${funnel.id}/edit`">
                            <Icon icon="heroicons:pencil-square" class="size-3.5" />
                            Edit
                        </Link>
                    </Button>
                    <Button
                        as-child
                        variant="outline"
                        size="sm"
                        class="h-8 w-8 p-0 opacity-0 group-hover:opacity-100 transition-opacity"
                        title="View chat"
                    >
                        <a :href="`/funnels/${funnel.id}/chat`">
                            <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-3.5" />
                        </a>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Empty state — no funnels at all -->
        <div
            v-else-if="stats.total === 0"
            class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-primary/25 bg-primary/5 py-20 gap-4 text-muted-foreground"
        >
            <div class="flex size-16 items-center justify-center rounded-2xl bg-primary/10">
                <Icon icon="heroicons:funnel" class="size-8 text-primary" />
            </div>
            <div class="text-center">
                <p class="font-semibold text-foreground">No funnels yet</p>
                <p class="text-sm mt-0.5">Create your first webinar funnel from a template and start collecting leads.</p>
            </div>
            <Button as-child class="mt-1 gap-1.5 bg-primary text-primary-foreground hover:opacity-90 shadow-sm">
                <Link href="/templates">
                    <Icon icon="heroicons:rectangle-stack" class="size-4" />
                    Browse Templates
                </Link>
            </Button>
        </div>

        <!-- Empty state — filtered / no results -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-xl border border-dashed py-14 gap-3 text-muted-foreground"
        >
            <Icon icon="heroicons:magnifying-glass" class="size-8 opacity-30" />
            <p class="text-sm">No funnels match your filter.</p>
            <Button variant="ghost" size="sm" class="text-xs" @click="search = ''; activeFilter = 'all'">
                Clear filters
            </Button>
        </div>

    </div>
</template>
