<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type AnalyticsPayload = {
    link: {
        id: number;
        label: string | null;
        code: string;
        click_count: number;
        public_url: string;
        is_active: boolean;
    };
    recent_clicks: Array<{
        country: string | null;
        device: string | null;
        referrer: string | null;
        created_at: string | null;
    }>;
    by_country: Record<string, number>;
    by_device: Record<string, number>;
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
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[85vh] max-w-lg overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Click analytics</DialogTitle>
                <DialogDescription>
                    {{ data?.link.label || data?.link.code || 'Tracked link' }} — {{ data?.link.click_count ?? 0 }} total clicks
                </DialogDescription>
            </DialogHeader>

            <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">Loading…</div>
            <div v-else-if="error" class="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">{{ error }}</div>
            <div v-else-if="data" class="space-y-5">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border p-3">
                        <p class="text-xs font-medium text-muted-foreground">By country</p>
                        <div v-if="Object.keys(data.by_country).length" class="mt-2 space-y-1">
                            <div v-for="(total, country) in data.by_country" :key="country" class="flex justify-between text-sm">
                                <span>{{ country }}</span>
                                <Badge variant="secondary">{{ total }}</Badge>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-muted-foreground">No country data yet.</p>
                    </div>
                    <div class="rounded-lg border p-3">
                        <p class="text-xs font-medium text-muted-foreground">By device</p>
                        <div v-if="Object.keys(data.by_device).length" class="mt-2 space-y-1">
                            <div v-for="(total, device) in data.by_device" :key="device" class="flex justify-between text-sm capitalize">
                                <span>{{ device }}</span>
                                <Badge variant="secondary">{{ total }}</Badge>
                            </div>
                        </div>
                        <p v-else class="mt-2 text-xs text-muted-foreground">No device data yet.</p>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-medium text-muted-foreground">Recent clicks</p>
                    <div v-if="data.recent_clicks.length" class="max-h-48 space-y-2 overflow-y-auto">
                        <div
                            v-for="(click, i) in data.recent_clicks"
                            :key="i"
                            class="flex items-start justify-between gap-2 rounded-md border px-3 py-2 text-xs"
                        >
                            <div class="min-w-0">
                                <p class="font-medium capitalize">{{ click.device || 'unknown' }} · {{ click.country || '??' }}</p>
                                <p v-if="click.referrer" class="truncate text-muted-foreground">{{ click.referrer }}</p>
                            </div>
                            <span class="shrink-0 text-muted-foreground">
                                {{ click.created_at ? new Date(click.created_at).toLocaleString() : '—' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="rounded-md border border-dashed p-4 text-center text-xs text-muted-foreground">
                        <Icon icon="heroicons:cursor-arrow-rays" class="mx-auto mb-1 size-5 opacity-50" />
                        No clicks recorded yet.
                    </p>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
