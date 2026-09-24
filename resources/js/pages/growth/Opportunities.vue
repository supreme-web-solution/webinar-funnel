<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PromoteThisWeekCard from '@/components/growth/PromoteThisWeekCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { sourceLabel, campaignCreateUrl, campaignQuickStartPayload, type MarketplaceOffer } from '@/composables/useMarketplaceSearch';

type SearchLink = { label: string; marketplace: string; url: string };

const props = defineProps<{
    keyword: string;
    marketplace: string;
    results: MarketplaceOffer[];
    top_pick: MarketplaceOffer | null;
    search_links: SearchLink[];
    error: string | null;
    sources: string[];
    refreshed_at: string | null;
    integrations: {
        apify_configured: boolean;
        clickbank_live: boolean;
        scrapingbee_configured: boolean;
    };
}>();

const keywordInput = ref(props.keyword);
const marketplaceFilter = ref(props.marketplace || 'all');
const quickStarting = ref(false);
const quickStartOfferId = ref<string | null>(null);
const searching = ref(false);

const marketplaceOptions = [
    { id: 'all', label: 'All marketplaces' },
    { id: 'clickbank', label: 'ClickBank' },
    { id: 'jvzoo', label: 'JVZoo' },
    { id: 'warriorplus', label: 'WarriorPlus' },
];

const statCards = computed(() => [
    { label: 'Results', value: props.results.length, sub: props.keyword ? 'matches' : 'trending', icon: 'heroicons:light-bulb' },
    { label: 'ClickBank', value: props.integrations.clickbank_live ? 'Live' : 'Cache', sub: props.integrations.clickbank_live ? 'via Apify' : 'fallback', icon: 'heroicons:globe-alt' },
    { label: 'JVZoo & W+', value: 'Live', sub: 'via Jina', icon: 'heroicons:magnifying-glass' },
    { label: 'ScrapingBee', value: props.integrations.scrapingbee_configured ? 'On' : 'Off', sub: 'optional', icon: 'heroicons:bolt' },
]);

const hasPartialResults = computed(() => props.results.length > 0 && !!props.error);

function runSearch() {
    if (!keywordInput.value.trim() || searching.value) return;

    searching.value = true;
    router.get('/growth/opportunities', {
        q: keywordInput.value.trim(),
        marketplace: marketplaceFilter.value === 'all' ? undefined : marketplaceFilter.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            searching.value = false;
        },
    });
}

function scoreBadgeClass(label?: string): string {
    if (label === 'Hot') return 'border-blue-200 bg-blue-50 text-blue-700';
    if (label === 'Good') return 'border-blue-200 bg-blue-50 text-blue-700';
    return '';
}

function offerKey(offer: MarketplaceOffer, index: number): string {
    return `${offer.marketplace}-${offer.title}-${index}`;
}

function buildCampaignNow(offer: MarketplaceOffer, type: 'sales' | 'webinar' = 'sales') {
    quickStarting.value = true;
    quickStartOfferId.value = offerKey(offer, 0);
    router.post('/campaigns/quick-start', {
        ...campaignQuickStartPayload(offer, props.keyword),
        type,
    }, {
        onFinish: () => {
            quickStarting.value = false;
            quickStartOfferId.value = null;
        },
    });
}
</script>

<template>
    <Head title="AI Opportunity Finder" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">AI opportunity finder</h1>
                <p class="mt-0.5 text-sm text-muted-foreground">
                    JVZoo & WarriorPlus via Jina. ClickBank via Apify with cached fallback — search, score, and launch in one click.
                </p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="brand-outline" size="sm">
                    <Link href="/campaigns">
                        <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                        All campaigns
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

        <!-- Search -->
        <div class="rounded-xl border border-border/60 bg-white p-3 shadow-sm md:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="keywordInput"
                        class="pl-9"
                        placeholder="e.g. keto, ai software, weight loss…"
                        @keyup.enter="runSearch"
                    />
                </div>
                <select
                    v-model="marketplaceFilter"
                    class="h-9 rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm sm:w-44"
                >
                    <option v-for="m in marketplaceOptions" :key="m.id" :value="m.id">{{ m.label }}</option>
                </select>
                <Button variant="brand" :disabled="!keywordInput.trim() || searching" @click="runSearch">
                    <Icon v-if="searching" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                    <span v-else>Search</span>
                </Button>
            </div>
            <p v-if="error && !hasPartialResults" class="mt-3 rounded-lg border border-amber-200 bg-amber-50/80 p-2 text-xs text-amber-800">{{ error }}</p>
            <p v-else-if="hasPartialResults" class="mt-3 rounded-lg border border-blue-200 bg-blue-50/80 p-2 text-xs text-blue-900">
                {{ error }} — showing {{ results.length }} result(s) from available sources.
            </p>
        </div>

        <!-- Loading -->
        <div v-if="searching" class="rounded-xl border border-dashed border-blue-200/60 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3 text-sm">
                <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-blue-600" />
                <div>
                    <p class="font-medium text-foreground">Searching marketplaces…</p>
                    <p class="text-xs text-muted-foreground">
                        JVZoo, WarriorPlus{{ integrations.clickbank_live ? ', ClickBank' : '' }} — this can take a few seconds.
                    </p>
                </div>
            </div>
            <div class="mt-4 space-y-2">
                <div v-for="i in 3" :key="i" class="h-16 animate-pulse rounded-xl bg-blue-100/40" />
            </div>
        </div>

        <div v-if="sources.length && !searching" class="flex flex-wrap gap-1.5 text-xs text-muted-foreground">
            <span>Live sources:</span>
            <Badge v-for="s in sources" :key="s" variant="outline">{{ sourceLabel(s) }}</Badge>
        </div>

        <div v-if="!keyword && results.length && !searching" class="space-y-0.5">
            <p class="text-xs text-muted-foreground">Ranked by composite score — gravity, EPC, refunds, and marketplace signals.</p>
        </div>

        <!-- Top pick -->
        <PromoteThisWeekCard
            v-if="top_pick && !searching"
            :top-pick="top_pick"
            :refreshed-at="refreshed_at"
        />

        <!-- Results -->
        <div v-if="results.length && !searching" class="grid gap-3">
            <article
                v-for="(offer, i) in results"
                :key="offerKey(offer, i)"
                class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-blue-200/60 hover:shadow-md"
            >
                <div class="flex flex-col gap-3 p-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold text-sm">{{ offer.title }}</span>
                            <Badge variant="outline" class="text-[10px] uppercase">{{ offer.marketplace }}</Badge>
                            <Badge v-if="offer.score != null" variant="outline" :class="scoreBadgeClass(offer.score_label)">
                                {{ offer.score }} · {{ offer.score_label }}
                            </Badge>
                            <Badge v-if="offer.source" variant="outline" class="text-[10px]">{{ sourceLabel(offer.source) }}</Badge>
                        </div>
                        <p v-if="offer.promote_reason" class="text-xs text-muted-foreground">{{ offer.promote_reason }}</p>
                        <p v-else-if="offer.why" class="text-sm text-muted-foreground">{{ offer.why }}</p>
                        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                            <span v-if="offer.gravity_hint">Gravity: {{ offer.gravity_hint }}</span>
                            <span v-if="offer.epc_hint">EPC: {{ offer.epc_hint }}</span>
                            <span v-if="offer.refund_rate">Refund: {{ offer.refund_rate }}%</span>
                        </div>
                        <a :href="offer.url" target="_blank" rel="noopener noreferrer" class="block truncate text-xs text-blue-600 hover:text-blue-800 hover:underline">{{ offer.url }}</a>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button
                            size="sm"
                            variant="brand"
                            :disabled="quickStarting"
                            @click="buildCampaignNow(offer, 'sales')"
                        >
                            <Icon v-if="quickStarting && quickStartOfferId === offerKey(offer, i)" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                            Sales funnel
                        </Button>
                        <Button
                            size="sm"
                            variant="brand-outline"
                            :disabled="quickStarting"
                            @click="buildCampaignNow(offer, 'webinar')"
                        >
                            Webinar funnel
                        </Button>
                        <Button as-child size="sm" variant="ghost">
                            <Link :href="campaignCreateUrl(offer, keyword)">Wizard</Link>
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <!-- Manual search links -->
        <div v-if="search_links.length" class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
            <p class="text-sm font-medium">{{ results.length ? 'Also search manually' : 'Search marketplaces manually' }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <Button v-for="link in search_links" :key="link.url" as-child size="sm" variant="brand-outline">
                    <a :href="link.url" target="_blank" rel="noopener noreferrer">{{ link.label }}</a>
                </Button>
            </div>
        </div>
    </div>
</template>
