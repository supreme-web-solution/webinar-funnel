<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import CampaignTrafficLayout, { type CampaignHubContext } from '@/components/campaign-traffic/CampaignTrafficLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    campaign: CampaignHubContext['campaign'];
    traffic_funnel: CampaignHubContext['traffic_funnel'];
    routes: CampaignHubContext['routes'];
    stats: {
        keywords: number;
        mentions: number;
        promotion_posts: number;
        scheduled_posts: number;
        ad_campaigns: number;
        active_ads: number;
    };
    paid_ads_enabled: boolean;
}>();

const statCards = computed(() => {
    const cards = [
        { label: 'Keywords', value: props.stats.keywords, sub: 'tracked', icon: 'heroicons:hashtag' },
        { label: 'Mentions', value: props.stats.mentions, sub: 'found', icon: 'heroicons:chat-bubble-left-right' },
        { label: 'Social posts', value: props.stats.promotion_posts, sub: 'generated', icon: 'heroicons:megaphone' },
        { label: 'Scheduled', value: props.stats.scheduled_posts, sub: 'queued', icon: 'heroicons:calendar-days' },
    ];
    if (props.paid_ads_enabled) {
        cards.push({ label: 'Ad campaigns', value: props.stats.ad_campaigns, sub: `${props.stats.active_ads} active`, icon: 'heroicons:currency-dollar' });
    }
    return cards;
});

const featureCards = computed(() => {
    const items = [
        {
            title: 'Free traffic',
            description: 'Track Reddit, YouTube, X & news mentions. AI auto-reply optional.',
            icon: 'heroicons:magnifying-glass-circle',
            href: props.routes.free,
            cta: 'Open',
            badge: null as string | null,
            variant: 'brand' as const,
        },
        {
            title: 'Social promotion',
            description: 'Generate posts, scripts & schedule content for this offer.',
            icon: 'heroicons:megaphone',
            href: props.routes.promotion_posts,
            cta: 'Manage posts',
            badge: props.stats.scheduled_posts > 0 ? `${props.stats.scheduled_posts} scheduled` : null,
            variant: 'brand' as const,
        },
        {
            title: 'Promo calendar',
            description: "Drag-and-drop schedule for this campaign's content.",
            icon: 'heroicons:calendar-days',
            href: props.routes.promotion_calendar,
            cta: 'View calendar',
            badge: null,
            variant: 'brand-outline' as const,
        },
    ];
    if (props.paid_ads_enabled && props.routes.ads) {
        items.push({
            title: 'Paid ads',
            description: 'Facebook ad campaigns with AI creatives.',
            icon: 'heroicons:currency-dollar',
            href: props.routes.ads,
            cta: 'Manage ads',
            badge: props.stats.active_ads > 0 ? `${props.stats.active_ads} active` : null,
            variant: 'brand' as const,
        });
    }
    return items;
});
</script>

<template>
    <CampaignTrafficLayout
        :hub="{ campaign, traffic_funnel, routes }"
        active="hub"
        :paid-ads-enabled="paid_ads_enabled"
    >
        <!-- Stats -->
        <div class="grid grid-cols-2 gap-2 md:grid-cols-4 md:gap-3" :class="paid_ads_enabled ? 'lg:grid-cols-5' : ''">
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

        <!-- Feature cards -->
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="card in featureCards"
                :key="card.title"
                class="flex flex-col overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm transition-all hover:border-teal-200/60 hover:shadow-md"
            >
                <div class="flex flex-1 flex-col gap-3 p-4 md:p-5">
                    <div class="flex size-10 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10">
                        <Icon :icon="card.icon" class="size-5 text-teal-600" />
                    </div>
                    <div class="flex-1">
                        <h2 class="font-semibold text-foreground">{{ card.title }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ card.description }}</p>
                        <Badge v-if="card.badge" variant="outline" class="mt-2 border-teal-200 bg-teal-50 text-[0.65rem] text-teal-700">
                            {{ card.badge }}
                        </Badge>
                    </div>
                    <Button as-child size="sm" :variant="card.variant" class="mt-auto w-fit">
                        <Link :href="card.href">{{ card.cta }}</Link>
                    </Button>
                </div>
            </article>
        </div>
    </CampaignTrafficLayout>
</template>
