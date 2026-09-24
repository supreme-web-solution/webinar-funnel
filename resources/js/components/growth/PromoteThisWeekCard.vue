<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { campaignCreateUrl, campaignQuickStartPayload, type MarketplaceOffer } from '@/composables/useMarketplaceSearch';

const props = withDefaults(defineProps<{
    topPick: MarketplaceOffer | null;
    alternates?: MarketplaceOffer[];
    refreshedAt?: string | null;
    compact?: boolean;
}>(), {
    alternates: () => [],
    refreshedAt: null,
    compact: false,
});

const quickStarting = ref(false);
const quickStartType = ref<'sales' | 'webinar' | null>(null);

function scoreBadgeClass(label?: string): string {
    if (label === 'Hot') return 'border-blue-200 bg-blue-50 text-blue-700';
    if (label === 'Good') return 'border-blue-200 bg-blue-50 text-blue-700';
    return '';
}

function buildCampaign(offer: MarketplaceOffer, type: 'sales' | 'webinar') {
    quickStarting.value = true;
    quickStartType.value = type;
    router.post('/campaigns/quick-start', {
        ...campaignQuickStartPayload(offer, offer.search_keyword ?? ''),
        type,
    }, {
        onFinish: () => {
            quickStarting.value = false;
            quickStartType.value = null;
        },
    });
}
</script>

<template>
    <section
        v-if="topPick"
        class="overflow-hidden rounded-xl border border-blue-200/60 bg-linear-to-br from-blue-50/80 to-white shadow-sm"
        :class="compact ? '' : 'md:p-1'"
    >
        <div class="space-y-3 p-4" :class="compact ? 'p-3' : 'md:p-5'">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Badge class="chip-brand-active">
                        <Icon icon="heroicons:sparkles" class="mr-1 size-3" />
                        Promote this week
                    </Badge>
                    <Badge variant="outline" :class="scoreBadgeClass(topPick.score_label)">
                        Score {{ topPick.score }} · {{ topPick.score_label }}
                    </Badge>
                    <Badge variant="outline" class="text-[10px] uppercase">{{ topPick.marketplace }}</Badge>
                </div>
                <Link
                    v-if="!compact"
                    href="/growth/opportunities"
                    class="text-xs font-medium text-blue-700 hover:underline"
                >
                    Browse all offers
                </Link>
            </div>

            <div>
                <p class="font-semibold" :class="compact ? 'text-sm' : 'text-lg'">{{ topPick.title }}</p>
                <p v-if="topPick.promote_reason" class="mt-1 text-xs text-muted-foreground md:text-sm">
                    {{ topPick.promote_reason }}
                </p>
                <p v-if="refreshedAt" class="mt-1 text-[0.65rem] text-muted-foreground">
                    Daily scan · {{ new Date(refreshedAt).toLocaleString() }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                <span v-if="topPick.gravity_hint">Gravity: {{ topPick.gravity_hint }}</span>
                <span v-if="topPick.epc_hint">EPC: {{ topPick.epc_hint }}</span>
                <span v-if="topPick.refund_rate">Refund: {{ topPick.refund_rate }}%</span>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    variant="brand"
                    size="sm"
                    :disabled="quickStarting"
                    @click="buildCampaign(topPick, 'sales')"
                >
                    <Icon v-if="quickStarting && quickStartType === 'sales'" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                    Build sales campaign
                </Button>
                <Button
                    variant="brand-outline"
                    size="sm"
                    :disabled="quickStarting"
                    @click="buildCampaign(topPick, 'webinar')"
                >
                    <Icon v-if="quickStarting && quickStartType === 'webinar'" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                    Build webinar funnel
                </Button>
                <Button as-child variant="ghost" size="sm">
                    <Link :href="campaignCreateUrl(topPick)">Customize</Link>
                </Button>
            </div>

            <div v-if="alternates.length && !compact" class="border-t border-blue-200/40 pt-3">
                <p class="mb-2 text-xs font-medium text-muted-foreground">Also worth testing</p>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="(alt, i) in alternates"
                        :key="`${alt.title}-${i}`"
                        variant="outline"
                        size="sm"
                        class="h-auto max-w-full py-1.5 text-left text-xs"
                        :disabled="quickStarting"
                        @click="buildCampaign(alt, 'sales')"
                    >
                        <span class="truncate">{{ alt.title }}</span>
                        <Badge v-if="alt.score != null" variant="outline" class="ml-2 shrink-0 text-[10px]">
                            {{ alt.score }}
                        </Badge>
                    </Button>
                </div>
            </div>
        </div>
    </section>
</template>
