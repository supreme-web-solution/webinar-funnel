<script setup lang="ts">
import CampaignTrafficLayout, { type CampaignHubContext } from '@/components/campaign-traffic/CampaignTrafficLayout.vue';

defineProps<{
    campaignHub?: CampaignHubContext | null;
    active?: 'hub' | 'free' | 'promotion' | 'calendar' | 'ads';
    paidAdsEnabled?: boolean;
    standaloneClass?: string;
}>();
</script>

<template>
    <CampaignTrafficLayout
        v-if="campaignHub"
        :hub="campaignHub"
        :active="active ?? 'hub'"
        :paid-ads-enabled="paidAdsEnabled"
    >
        <slot />
    </CampaignTrafficLayout>
    <div v-else :class="standaloneClass ?? 'mx-auto flex w-full max-w-4xl flex-col gap-5 px-4 py-6 md:px-6'">
        <slot />
    </div>
</template>
