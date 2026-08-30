<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BonusCoverArt from '@/components/campaigns/BonusCoverArt.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { bonusDetailLine, bonusOpenLabel, bonusTypeLabel, bonusValueLabel } from '@/composables/useBonusDisplay';

type BonusItem = {
    id: number;
    uuid: string;
    title: string;
    bonus_type: string;
    status: string;
    content: string | null;
    meta?: Record<string, unknown>;
    campaign: { id: number; name: string; slug: string } | null;
    created_at: string | null;
    viewer_url: string | null;
    value_label: string;
    cover_gradient: string;
    page_count: number;
    slide_count: number;
    subtitle: string;
};

const props = defineProps<{
    bonuses: BonusItem[];
}>();

const search = ref('');
const typeFilter = ref<'all' | 'ebook' | 'mini_course'>('all');
const dateFilter = ref<'all' | '7d' | '30d' | '90d'>('all');

const stats = computed(() => {
    const ebooks = props.bonuses.filter((b) => b.bonus_type === 'ebook').length;
    const courses = props.bonuses.filter((b) => b.bonus_type === 'mini_course').length;
    const withCampaign = props.bonuses.filter((b) => b.campaign).length;

    return { total: props.bonuses.length, ebooks, courses, withCampaign };
});

const statCards = computed(() => [
    { label: 'Total', value: stats.value.total, sub: 'bonuses', icon: 'heroicons:gift' },
    { label: 'Ebooks', value: stats.value.ebooks, sub: 'PDF ready', icon: 'heroicons:book-open' },
    { label: 'Mini courses', value: stats.value.courses, sub: 'swipeable', icon: 'heroicons:academic-cap' },
    { label: 'Campaigns', value: stats.value.withCampaign, sub: 'linked', icon: 'heroicons:folder' },
]);

const typeTabs = [
    { key: 'all' as const, label: 'All types' },
    { key: 'ebook' as const, label: 'Ebooks' },
    { key: 'mini_course' as const, label: 'Mini courses' },
];

const filtered = computed(() => {
    let list = [...props.bonuses];

    if (typeFilter.value !== 'all') {
        list = list.filter((b) => b.bonus_type === typeFilter.value);
    }

    if (dateFilter.value !== 'all') {
        const days = { '7d': 7, '30d': 30, '90d': 90 }[dateFilter.value];
        const cutoff = Date.now() - days * 86_400_000;
        list = list.filter((b) => {
            if (!b.created_at) return true;
            return new Date(b.created_at).getTime() >= cutoff;
        });
    }

    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(
            (b) =>
                b.title.toLowerCase().includes(q)
                || b.campaign?.name.toLowerCase().includes(q)
                || b.bonus_type.toLowerCase().includes(q),
        );
    }

    return list;
});

function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function detailLine(b: BonusItem): string {
    if (b.bonus_type === 'mini_course' && b.slide_count > 0) {
        return `${b.slide_count} lessons · swipeable course`;
    }
    if (b.bonus_type === 'ebook' && b.page_count > 0) {
        return `${b.page_count} chapters · PDF ready`;
    }
    if (b.subtitle) return b.subtitle;
    return bonusDetailLine(b.bonus_type, b.meta);
}

function typeIcon(type: string): string {
    if (type === 'mini_course') return 'heroicons:academic-cap';
    return 'heroicons:book-open';
}
</script>

<template>
    <Head title="Bonus Library" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Bonus library</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    Ebooks and mini courses from your campaigns — preview, reuse, and open the viewer.
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
                        <span class="text-xl font-bold leading-none">{{ stat.value }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + filters -->
        <div
            v-if="bonuses.length > 0"
            class="flex flex-col gap-3 rounded-xl border border-border/60 bg-white p-3 shadow-sm sm:flex-row sm:items-center sm:justify-between md:p-4"
        >
            <div class="relative max-w-md flex-1">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by title or campaign…" class="pl-9" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-1.5">
                    <button
                        v-for="tab in typeTabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="typeFilter === tab.key
                            ? 'bg-teal-600 text-white shadow-sm'
                            : 'border border-border/60 bg-muted/20 text-muted-foreground hover:bg-teal-50 hover:text-teal-800'"
                        @click="typeFilter = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <Select v-model="dateFilter">
                    <SelectTrigger class="h-8 w-full sm:w-36">
                        <Icon icon="heroicons:calendar-days" class="mr-2 size-3.5 text-muted-foreground" />
                        <SelectValue placeholder="Date" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All time</SelectItem>
                        <SelectItem value="7d">Last 7 days</SelectItem>
                        <SelectItem value="30d">Last 30 days</SelectItem>
                        <SelectItem value="90d">Last 90 days</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <!-- Empty: no bonuses at all -->
        <div
            v-if="bonuses.length === 0"
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-teal-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                <Icon icon="heroicons:gift" class="size-7 text-teal-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">No bonuses yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Generate ebooks and mini courses from the campaign wizard — they appear here with cover art.
                </p>
            </div>
            <Button as-child variant="brand" size="sm">
                <Link href="/campaigns/create">
                    <Icon icon="heroicons:sparkles" class="size-3.5" />
                    Create a campaign
                </Link>
            </Button>
        </div>

        <!-- Empty: filters -->
        <div
            v-else-if="filtered.length === 0"
            class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/60 bg-white px-6 py-12 text-center shadow-sm"
        >
            <Icon icon="heroicons:magnifying-glass" class="size-8 text-muted-foreground/40" />
            <p class="text-sm text-muted-foreground">No bonuses match your search or filters.</p>
        </div>

        <!-- Bonus grid -->
        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="b in filtered"
                :key="b.uuid"
                class="group flex flex-col overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-teal-200/60 hover:shadow-md"
            >
                <div class="flex items-center justify-center bg-linear-to-b from-teal-50/30 to-white px-6 pb-2 pt-8">
                    <BonusCoverArt
                        :title="b.title"
                        :subtitle="b.subtitle || detailLine(b)"
                        :bonus-type="b.bonus_type"
                        :gradient="b.cover_gradient"
                        size="lg"
                    />
                </div>

                <div class="flex flex-1 flex-col gap-3 p-4 pt-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" class="gap-1 border-teal-200 bg-teal-50 text-[10px] text-teal-700">
                            <Icon :icon="typeIcon(b.bonus_type)" class="size-3" />
                            {{ bonusTypeLabel(b.bonus_type) }}
                        </Badge>
                        <Badge variant="outline" class="text-[10px]">{{ b.value_label || bonusValueLabel(b.bonus_type, b.meta) }}</Badge>
                    </div>

                    <div>
                        <h2 class="line-clamp-2 font-semibold leading-snug">{{ b.title }}</h2>
                        <p class="mt-1 text-xs text-muted-foreground">{{ detailLine(b) }}</p>
                    </div>

                    <p v-if="b.content" class="line-clamp-2 text-xs leading-relaxed text-muted-foreground">
                        {{ b.content }}
                    </p>

                    <div class="mt-auto space-y-2 border-t border-border/60 pt-3">
                        <div v-if="b.campaign" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Icon icon="heroicons:folder" class="size-3.5 shrink-0 text-teal-600" />
                            <Link :href="`/campaigns/${b.campaign.id}/edit`" class="truncate hover:text-teal-700 hover:underline">
                                {{ b.campaign.name }}
                            </Link>
                        </div>
                        <div v-if="b.created_at" class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Icon icon="heroicons:clock" class="size-3.5 shrink-0" />
                            {{ formatDate(b.created_at) }}
                        </div>
                        <div class="flex flex-wrap gap-2 pt-1">
                            <Button v-if="b.viewer_url" as-child size="sm" variant="brand" class="flex-1 sm:flex-none">
                                <a :href="b.viewer_url" target="_blank" rel="noopener">
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                    {{ bonusOpenLabel(b.bonus_type) }}
                                </a>
                            </Button>
                            <Button v-if="b.campaign" as-child size="sm" variant="brand-outline" class="flex-1 sm:flex-none">
                                <Link :href="`/campaigns/${b.campaign.id}/edit?step=bonuses`">
                                    <Icon icon="heroicons:pencil-square" class="size-3.5" />
                                    Edit
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
