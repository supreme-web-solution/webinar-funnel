<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type CampaignHubContext = {
    standalone?: boolean;
    campaign: { id: number; name: string; slug: string; type: string; status: string } | null;
    workspace?: { name: string; status: string };
    traffic_funnel: { id: number; name: string; slug: string; status: string };
    routes: {
        hub: string;
        free: string;
        promotion_posts: string;
        promotion_calendar: string;
        ads?: string;
        campaign_edit?: string;
        traffic_index?: string;
        setup?: string;
    };
};

const props = defineProps<{
    hub: CampaignHubContext;
    active: 'hub' | 'free' | 'promotion' | 'calendar' | 'ads';
    paidAdsEnabled?: boolean;
}>();

const isStandalone = computed(() => props.hub.standalone === true || props.hub.campaign === null);

const adsEnabled = computed(() => props.paidAdsEnabled ?? Boolean(props.hub.routes.ads));

const tabs = computed(() => {
    const items = [
        { id: 'hub' as const, label: 'Overview', href: props.hub.routes.hub, icon: 'heroicons:squares-2x2' },
        { id: 'free' as const, label: 'Free traffic', href: props.hub.routes.free, icon: 'heroicons:magnifying-glass-circle' },
        { id: 'promotion' as const, label: 'Social posts', href: props.hub.routes.promotion_posts, icon: 'heroicons:megaphone' },
        { id: 'calendar' as const, label: 'Calendar', href: props.hub.routes.promotion_calendar, icon: 'heroicons:calendar-days' },
    ];
    if (adsEnabled.value && props.hub.routes.ads) {
        items.push({ id: 'ads' as const, label: 'Paid ads', href: props.hub.routes.ads, icon: 'heroicons:currency-dollar' });
    }
    return items;
});

function typeIcon(type: string): string {
    return type === 'webinar' ? 'heroicons:video-camera' : 'heroicons:shopping-bag';
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                        <Icon icon="heroicons:signal" class="size-5 text-blue-600" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ isStandalone ? 'Standalone traffic' : 'Campaign traffic hub' }}
                        </p>
                        <h1 class="truncate text-xl font-bold tracking-tight md:text-2xl">
                            {{ isStandalone ? 'Your traffic workspace' : hub.campaign?.name }}
                        </h1>
                        <p v-if="isStandalone" class="mt-1 text-xs text-muted-foreground">
                            Ready to use — no campaign to create. Post, track mentions, or schedule content anytime.
                        </p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <template v-if="isStandalone">
                                <Badge variant="outline" class="border-blue-200 bg-blue-50 text-[0.65rem] text-blue-700">
                                    No offer required
                                </Badge>
                            </template>
                            <template v-else-if="hub.campaign">
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.65rem]"
                                    :class="hub.campaign.type === 'webinar' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-blue-200 bg-blue-50 text-blue-700'"
                                >
                                    {{ hub.campaign.type }}
                                </Badge>
                                <Badge
                                    variant="outline"
                                    class="capitalize text-[0.65rem]"
                                    :class="hub.campaign.status === 'published' ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-amber-200 bg-amber-50 text-amber-700'"
                                >
                                    {{ hub.campaign.status }}
                                </Badge>
                                <span class="text-[0.65rem] text-muted-foreground font-mono">/{{ hub.campaign.slug }}</span>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button as-child variant="brand-outline" size="sm">
                        <Link :href="hub.routes.traffic_index ?? '/traffic'">
                            <Icon icon="heroicons:signal" class="size-3.5" />
                            {{ isStandalone ? 'Traffic hub' : 'All hubs' }}
                        </Link>
                    </Button>
                    <Button v-if="isStandalone && hub.routes.setup" as-child variant="brand-outline" size="sm">
                        <Link :href="hub.routes.setup">
                            <Icon icon="heroicons:cog-6-tooth" class="size-3.5" />
                            Setup
                        </Link>
                    </Button>
                    <Button v-else-if="hub.routes.campaign_edit" as-child variant="brand" size="sm">
                        <Link :href="hub.routes.campaign_edit">
                            <Icon :icon="typeIcon(hub.campaign?.type ?? 'sales')" class="size-3.5" />
                            Campaign wizard
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex flex-wrap gap-1.5 rounded-xl border border-border/60 bg-white p-2 shadow-sm">
            <Link
                v-for="tab in tabs"
                :key="tab.id"
                :href="tab.href"
                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                :class="active === tab.id
                    ? 'chip-brand-active'
                    : 'text-muted-foreground hover:bg-blue-50 hover:text-blue-800'"
            >
                <Icon :icon="tab.icon" class="size-3.5" />
                {{ tab.label }}
            </Link>
        </div>

        <slot />
    </div>
</template>
