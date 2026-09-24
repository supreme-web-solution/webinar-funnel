<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { TRACKED_LINK_COUNTRIES } from '@/data/trackedLinkCountries';

import type { DeviceRule, GeoRule } from '@/composables/useTrackedLinkRules';

const props = defineProps<{
    geoRules: GeoRule[];
    deviceRules: DeviceRule[];
}>();

const emit = defineEmits<{
    'update:geoRules': [rules: GeoRule[]];
    'update:deviceRules': [rules: DeviceRule[]];
}>();

const countryFilter = ref('');

const deviceOptions = [
    { id: 'mobile', label: 'Mobile', icon: 'heroicons:device-phone-mobile' },
    { id: 'tablet', label: 'Tablet', icon: 'heroicons:device-tablet' },
    { id: 'desktop', label: 'Desktop', icon: 'heroicons:computer-desktop' },
    { id: 'bot', label: 'Bot / crawler', icon: 'heroicons:cpu-chip' },
];

const usedCountries = computed(() => new Set(props.geoRules.map((r) => r.country)));
const usedDevices = computed(() => new Set(props.deviceRules.map((r) => r.device)));

const filteredCountries = computed(() => {
    const q = countryFilter.value.trim().toLowerCase();
    if (!q) return TRACKED_LINK_COUNTRIES;
    return TRACKED_LINK_COUNTRIES.filter(
        (c) => c.label.toLowerCase().includes(q) || c.code.toLowerCase().includes(q) || c.region.toLowerCase().includes(q),
    );
});

function addGeoRule(countryCode?: string) {
    const next = countryCode
        ? TRACKED_LINK_COUNTRIES.find((c) => c.code === countryCode && !usedCountries.value.has(c.code))
        : TRACKED_LINK_COUNTRIES.find((c) => !usedCountries.value.has(c.code));
    if (!next) return;
    emit('update:geoRules', [...props.geoRules, { country: next.code, url: '' }]);
}

function addDeviceRule(deviceId?: string) {
    const next = deviceId
        ? deviceOptions.find((d) => d.id === deviceId && !usedDevices.value.has(d.id))
        : deviceOptions.find((d) => !usedDevices.value.has(d.id));
    if (!next) return;
    emit('update:deviceRules', [...props.deviceRules, { device: next.id, url: '' }]);
}

function updateGeo(index: number, patch: Partial<GeoRule>) {
    const copy = props.geoRules.map((r, i) => (i === index ? { ...r, ...patch } : r));
    emit('update:geoRules', copy);
}

function updateDevice(index: number, patch: Partial<DeviceRule>) {
    const copy = props.deviceRules.map((r, i) => (i === index ? { ...r, ...patch } : r));
    emit('update:deviceRules', copy);
}

function removeGeo(index: number) {
    emit('update:geoRules', props.geoRules.filter((_, i) => i !== index));
}

function removeDevice(index: number) {
    emit('update:deviceRules', props.deviceRules.filter((_, i) => i !== index));
}

function addBotSafePage() {
    if (usedDevices.value.has('bot')) return;
    emit('update:deviceRules', [...props.deviceRules, { device: 'bot', url: 'https://example.com/safe-page' }]);
}

function addTier1Geo() {
    const tier1 = ['US', 'CA', 'GB', 'AU'];
    const additions = tier1
        .filter((code) => !usedCountries.value.has(code))
        .map((code) => ({ country: code, url: '' }));
    if (additions.length) {
        emit('update:geoRules', [...props.geoRules, ...additions]);
    }
}
</script>

<template>
    <div class="space-y-5 rounded-xl border border-border/60 bg-muted/5 p-4">
        <div class="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" @click="addTier1Geo">
                <Icon icon="heroicons:globe-alt" class="mr-1 size-3.5" />
                Add Tier-1 countries
            </Button>
            <Button type="button" variant="outline" size="sm" @click="addBotSafePage">
                <Icon icon="heroicons:shield-check" class="mr-1 size-3.5" />
                Route bots to safe page
            </Button>
        </div>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Label class="text-sm font-medium">Geo overrides</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="geoRules.length >= TRACKED_LINK_COUNTRIES.length"
                    @click="addGeoRule()"
                >
                    <Icon icon="heroicons:plus" class="mr-1 size-3.5" />
                    Add country
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Geo rules run first — matched countries get their override URL before device rules apply.
            </p>
            <Input v-model="countryFilter" placeholder="Filter countries…" class="max-w-xs" />
            <div v-if="!geoRules.length" class="rounded-md border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
                No geo rules — all countries use the default destination.
            </div>
            <div v-for="(rule, index) in geoRules" :key="`geo-${index}`" class="grid gap-2 rounded-lg border bg-white p-3 sm:grid-cols-[160px_1fr_auto]">
                <select
                    :value="rule.country"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    @change="updateGeo(index, { country: ($event.target as HTMLSelectElement).value })"
                >
                    <option
                        v-for="c in filteredCountries"
                        :key="c.code"
                        :value="c.code"
                        :disabled="usedCountries.has(c.code) && c.code !== rule.country"
                    >
                        {{ c.label }} ({{ c.code }})
                    </option>
                </select>
                <Input
                    :model-value="rule.url"
                    placeholder="https://country-specific-offer…"
                    @update:model-value="updateGeo(index, { url: String($event) })"
                />
                <Button type="button" variant="ghost" size="icon" class="shrink-0 text-rose-600" @click="removeGeo(index)">
                    <Icon icon="heroicons:trash" class="size-4" />
                </Button>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Label class="text-sm font-medium">Device overrides</Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="deviceRules.length >= deviceOptions.length"
                    @click="addDeviceRule()"
                >
                    <Icon icon="heroicons:plus" class="mr-1 size-3.5" />
                    Add device
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Device rules apply after geo — mobile/tablet/desktop/bot can each get a different landing page.
            </p>
            <div v-if="!deviceRules.length" class="rounded-md border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
                No device rules — all devices use the geo/default URL.
            </div>
            <div v-for="(rule, index) in deviceRules" :key="`device-${index}`" class="grid gap-2 rounded-lg border bg-white p-3 sm:grid-cols-[160px_1fr_auto]">
                <select
                    :value="rule.device"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    @change="updateDevice(index, { device: ($event.target as HTMLSelectElement).value })"
                >
                    <option
                        v-for="d in deviceOptions"
                        :key="d.id"
                        :value="d.id"
                        :disabled="usedDevices.has(d.id) && d.id !== rule.device"
                    >
                        {{ d.label }}
                    </option>
                </select>
                <Input
                    :model-value="rule.url"
                    :placeholder="rule.device === 'bot' ? 'https://safe-page-for-bots…' : 'https://device-specific-landing…'"
                    @update:model-value="updateDevice(index, { url: String($event) })"
                />
                <Button type="button" variant="ghost" size="icon" class="shrink-0 text-rose-600" @click="removeDevice(index)">
                    <Icon icon="heroicons:trash" class="size-4" />
                </Button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="d in deviceOptions"
                    :key="d.id"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border/60 bg-white px-2.5 py-1.5 text-xs transition-colors hover:border-blue-300 hover:bg-blue-50 disabled:opacity-40"
                    :disabled="usedDevices.has(d.id)"
                    @click="addDeviceRule(d.id)"
                >
                    <Icon :icon="d.icon" class="size-3.5 text-blue-600" />
                    {{ d.label }}
                </button>
            </div>
        </div>
    </div>
</template>
