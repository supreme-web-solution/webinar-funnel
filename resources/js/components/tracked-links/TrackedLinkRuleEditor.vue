<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { DeviceRule, GeoRule } from '@/composables/useTrackedLinkRules';

const props = defineProps<{
    geoRules: GeoRule[];
    deviceRules: DeviceRule[];
}>();

const emit = defineEmits<{
    'update:geoRules': [rules: GeoRule[]];
    'update:deviceRules': [rules: DeviceRule[]];
}>();

const countryOptions = [
    { code: 'US', label: 'United States' },
    { code: 'GB', label: 'United Kingdom' },
    { code: 'CA', label: 'Canada' },
    { code: 'AU', label: 'Australia' },
    { code: 'DE', label: 'Germany' },
    { code: 'FR', label: 'France' },
    { code: 'IN', label: 'India' },
    { code: 'BR', label: 'Brazil' },
    { code: 'MX', label: 'Mexico' },
    { code: 'NL', label: 'Netherlands' },
    { code: 'ES', label: 'Spain' },
    { code: 'IT', label: 'Italy' },
    { code: 'PH', label: 'Philippines' },
    { code: 'SG', label: 'Singapore' },
    { code: 'ZA', label: 'South Africa' },
];

const deviceOptions = [
    { id: 'mobile', label: 'Mobile' },
    { id: 'tablet', label: 'Tablet' },
    { id: 'desktop', label: 'Desktop' },
    { id: 'bot', label: 'Bot / crawler' },
];

const usedCountries = computed(() => new Set(props.geoRules.map((r) => r.country)));
const usedDevices = computed(() => new Set(props.deviceRules.map((r) => r.device)));

function addGeoRule() {
    const next = countryOptions.find((c) => !usedCountries.value.has(c.code));
    if (!next) return;
    emit('update:geoRules', [...props.geoRules, { country: next.code, url: '' }]);
}

function addDeviceRule() {
    const next = deviceOptions.find((d) => !usedDevices.value.has(d.id));
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
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <Label class="text-sm font-medium">Geo overrides</Label>
                <Button type="button" variant="outline" size="sm" :disabled="geoRules.length >= countryOptions.length" @click="addGeoRule">
                    <Icon icon="heroicons:plus" class="mr-1 size-3.5" />
                    Add country
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">Visitors from a matched country are sent to the override URL instead of the default destination.</p>
            <div v-if="!geoRules.length" class="rounded-md border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
                No geo rules — all countries use the default URL.
            </div>
            <div v-for="(rule, index) in geoRules" :key="`geo-${index}`" class="grid gap-2 rounded-lg border p-3 sm:grid-cols-[140px_1fr_auto]">
                <select
                    :value="rule.country"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    @change="updateGeo(index, { country: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="c in countryOptions" :key="c.code" :value="c.code" :disabled="usedCountries.has(c.code) && c.code !== rule.country">
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
            <div class="flex items-center justify-between gap-2">
                <Label class="text-sm font-medium">Device overrides</Label>
                <Button type="button" variant="outline" size="sm" :disabled="deviceRules.length >= deviceOptions.length" @click="addDeviceRule">
                    <Icon icon="heroicons:plus" class="mr-1 size-3.5" />
                    Add device
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">Device rules apply after geo matching. Bot traffic can be routed to a safe page.</p>
            <div v-if="!deviceRules.length" class="rounded-md border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
                No device rules — all devices use the matched geo/default URL.
            </div>
            <div v-for="(rule, index) in deviceRules" :key="`device-${index}`" class="grid gap-2 rounded-lg border p-3 sm:grid-cols-[140px_1fr_auto]">
                <select
                    :value="rule.device"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                    @change="updateDevice(index, { device: ($event.target as HTMLSelectElement).value })"
                >
                    <option v-for="d in deviceOptions" :key="d.id" :value="d.id" :disabled="usedDevices.has(d.id) && d.id !== rule.device">
                        {{ d.label }}
                    </option>
                </select>
                <Input
                    :model-value="rule.url"
                    placeholder="https://device-specific-landing…"
                    @update:model-value="updateDevice(index, { url: String($event) })"
                />
                <Button type="button" variant="ghost" size="icon" class="shrink-0 text-rose-600" @click="removeDevice(index)">
                    <Icon icon="heroicons:trash" class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
