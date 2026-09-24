<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import TrackedLinkClickChart from '@/components/tracked-links/TrackedLinkClickChart.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { countryLabel } from '@/data/trackedLinkCountries';

type AnalyticsPayload = {
    link: {
        id: number;
        label: string | null;
        code: string;
        click_count: number;
        public_url: string;
        is_active: boolean;
        geo_rules?: Record<string, string> | null;
        device_rules?: Record<string, string> | null;
    };
    summary: {
        clicks_last_7_days: number;
        unique_countries: number;
    };
    clicks_by_day: Array<{ label: string; date: string; clicks: number }>;
    recent_clicks: Array<{
        country: string | null;
        device: string | null;
        referrer: string | null;
        created_at: string | null;
    }>;
    by_country: Record<string, number>;
    by_device: Record<string, number>;
    top_referrers: Array<{ referrer: string; total: number }>;
};

const props = defineProps<{
    open: boolean;
    linkId: number | null;
}>();

const emit = defineEmits<{ 'update:open': [value: boolean] }>();

const loading = ref(false);
const error = ref<string | null>(null);
const data = ref<AnalyticsPayload | null>(null);

watch(
    () => [props.open, props.linkId] as const,
    async ([isOpen, id]) => {
        if (!isOpen || !id) {
            data.value = null;
            error.value = null;
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            const res = await fetch(`/tracked-links/${id}`, {
                headers: { Accept: 'application/json' },
            });

            if (!res.ok) {
                throw new Error('Could not load analytics.');
            }

            data.value = await res.json();
        } catch (e) {
            error.value = e instanceof Error ? e.message : 'Could not load analytics.';
        } finally {
            loading.value = false;
        }
    },
    { immediate: true },
);

function barWidth(value: number, total: number): string {
    if (total <= 0) return '0%';
    return `${Math.max(6, Math.round((value / total) * 100))}%`;
}

function countryTotal(): number {
    if (!data.value) return 0;
    return Object.values(data.value.by_country).reduce((s, n) => s + n, 0);
}

function deviceTotal(): number {
    if (!data.value) return 0;
    return Object.values(data.value.by_device).reduce((s, n) => s + n, 0);
}

function deviceIcon(device: string): string {
    if (device === 'mobile') return 'heroicons:device-phone-mobile';
    if (device === 'tablet') return 'heroicons:device-tablet';
    if (device === 'bot') return 'heroicons:cpu-chip';
    return 'heroicons:computer-desktop';
}

async function copyUrl(url: string) {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Cloaked URL copied.');
    } catch {
        toast.error('Could not copy URL.');
    }
}

function ruleCount(rules?: Record<string, string> | null): number {
    return rules ? Object.keys(rules).length : 0;
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto rounded-xl border border-border/60">
            <DialogHeader>
                <DialogTitle>Link analytics</DialogTitle>
                <DialogDescription>
                    {{ data?.link.label || data?.link.code || 'Tracked link' }}
                    — {{ data?.link.click_count ?? 0 }} all-time clicks
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-10 text-center text-sm text-muted-foreground">Loading analytics…</div>
            <div v-else-if="error" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
            <div v-else-if="data" class="space-y-5">
                <!-- Summary strip -->
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                        <p class="text-[0.65rem] uppercase tracking-wide text-muted-foreground">This week</p>
                        <p class="text-xl font-bold tabular-nums">{{ data.summary.clicks_last_7_days }}</p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                        <p class="text-[0.65rem] uppercase tracking-wide text-muted-foreground">All time</p>
                        <p class="text-xl font-bold tabular-nums">{{ data.link.click_count }}</p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                        <p class="text-[0.65rem] uppercase tracking-wide text-muted-foreground">Countries</p>
                        <p class="text-xl font-bold tabular-nums">{{ data.summary.unique_countries }}</p>
                    </div>
                    <div class="rounded-lg border border-border/60 bg-muted/10 p-3">
                        <p class="text-[0.65rem] uppercase tracking-wide text-muted-foreground">Routing</p>
                        <p class="text-sm font-semibold">
                            {{ ruleCount(data.link.geo_rules) }} geo · {{ ruleCount(data.link.device_rules) }} device
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-blue-200/60 bg-blue-50/50 px-3 py-2">
                    <code class="min-w-0 flex-1 truncate text-xs text-blue-800">{{ data.link.public_url }}</code>
                    <Button type="button" variant="brand-outline" size="sm" @click="copyUrl(data.link.public_url)">
                        <Icon icon="heroicons:clipboard-document" class="size-3.5" />
                        Copy
                    </Button>
                </div>

                <TrackedLinkClickChart :days="data.clicks_by_day" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs font-medium text-muted-foreground">By country</p>
                        <div v-if="Object.keys(data.by_country).length" class="mt-3 space-y-2">
                            <div v-for="(total, country) in data.by_country" :key="country">
                                <div class="mb-1 flex justify-between text-xs">
                                    <span>{{ countryLabel(String(country)) }} ({{ country }})</span>
                                    <Badge variant="secondary" class="tabular-nums">{{ total }}</Badge>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full fill-brand-gradient"
                                        :style="{ width: barWidth(total, countryTotal()) }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-muted-foreground">No country data yet — enable Cloudflare or pass ?country=XX for testing.</p>
                    </div>

                    <div class="rounded-lg border p-3">
                        <p class="text-xs font-medium text-muted-foreground">By device</p>
                        <div v-if="Object.keys(data.by_device).length" class="mt-3 space-y-2">
                            <div v-for="(total, device) in data.by_device" :key="device">
                                <div class="mb-1 flex justify-between text-xs capitalize">
                                    <span class="flex items-center gap-1.5">
                                        <Icon :icon="deviceIcon(String(device))" class="size-3.5 text-muted-foreground" />
                                        {{ device }}
                                    </span>
                                    <Badge variant="secondary" class="tabular-nums">{{ total }}</Badge>
                                </div>
                                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full fill-brand-gradient"
                                        :style="{ width: barWidth(total, deviceTotal()) }"
                                    />
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-muted-foreground">No device breakdown yet.</p>
                    </div>
                </div>

                <div v-if="data.top_referrers.length">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">Top referrers</p>
                    <div class="space-y-1.5">
                        <div
                            v-for="(row, i) in data.top_referrers"
                            :key="i"
                            class="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-xs"
                        >
                            <span class="min-w-0 truncate">{{ row.referrer }}</span>
                            <Badge variant="outline" class="shrink-0 tabular-nums">{{ row.total }}</Badge>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-medium text-muted-foreground">Recent clicks</p>
                    <div v-if="data.recent_clicks.length" class="max-h-52 space-y-2 overflow-y-auto">
                        <div
                            v-for="(click, i) in data.recent_clicks"
                            :key="i"
                            class="flex items-start justify-between gap-2 rounded-md border px-3 py-2 text-xs"
                        >
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 font-medium">
                                    <Icon :icon="deviceIcon(click.device || 'desktop')" class="size-3.5 text-muted-foreground" />
                                    <span class="capitalize">{{ click.device || 'unknown' }}</span>
                                    <span class="text-muted-foreground">·</span>
                                    <span>{{ click.country ? countryLabel(click.country) : 'Unknown' }}</span>
                                </p>
                                <p v-if="click.referrer" class="truncate text-muted-foreground">{{ click.referrer }}</p>
                            </div>
                            <span class="shrink-0 text-muted-foreground">
                                {{ click.created_at ? new Date(click.created_at).toLocaleString() : '—' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                        <Icon icon="heroicons:cursor-arrow-rays" class="mx-auto mb-1 size-5 opacity-50" />
                        No clicks recorded yet. Share your cloaked URL to start tracking.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
