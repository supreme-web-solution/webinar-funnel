<script setup lang="ts">
import { computed } from 'vue';
import CampaignTrafficLayout, { type CampaignHubContext } from '@/components/campaign-traffic/CampaignTrafficLayout.vue';
import FreeTrafficPanel from '@/components/campaign-traffic/FreeTrafficPanel.vue';
import type { FreeTrafficRoutes, TrafficData, TrafficSettings } from '@/composables/useFreeTrafficPanel';

const props = defineProps<{
    campaign: CampaignHubContext['campaign'];
    traffic_funnel: CampaignHubContext['traffic_funnel'];
    routes: CampaignHubContext['routes'] & Record<string, string>;
    traffic: TrafficData;
    settings: TrafficSettings | null;
}>();

const hub = computed(() => ({
    campaign: props.campaign,
    traffic_funnel: props.traffic_funnel,
    routes: props.routes,
}));

const panelRoutes = computed((): FreeTrafficRoutes => ({
    filter: props.routes.free,
    keywords_store: props.routes.keywords_store,
    keywords_update: props.routes.keywords_update,
    keywords_destroy: props.routes.keywords_destroy,
    keywords_fetch: props.routes.keywords_fetch,
    settings_patch: props.routes.settings_patch,
    draft_reply: props.routes.draft_reply,
}));
</script>

<template>
    <CampaignTrafficLayout :hub="hub" active="free">
        <FreeTrafficPanel
            :traffic="traffic"
            :settings="settings"
            :routes="panelRoutes"
            title="Free traffic"
            description="Track mentions and conversations per campaign keyword."
        />
    </CampaignTrafficLayout>
</template>
