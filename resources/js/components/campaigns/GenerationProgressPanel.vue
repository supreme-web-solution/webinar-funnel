<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

export type GenerationEvent = {
    at?: string;
    phase?: string;
    message?: string;
    detail?: string | null;
};

export type GenerationState = {
    status?: string;
    step?: string;
    phase?: string;
    message?: string;
    detail?: string | null;
    progress?: number;
    pages_done?: number;
    pages_total?: number;
    model?: string | null;
    selected_title?: string | null;
    events?: GenerationEvent[];
    error?: string | null;
};

const props = defineProps<{
    generation: GenerationState | null;
}>();

const isRunning = computed(() => props.generation?.status === 'running');
const isFailed = computed(() => props.generation?.status === 'failed');
const progress = computed(() => Math.min(100, Math.max(0, props.generation?.progress ?? 0)));
const recentEvents = computed(() => (props.generation?.events ?? []).slice(-6).reverse());

const phaseLabel = computed(() => {
    const phase = props.generation?.phase ?? '';
    const labels: Record<string, string> = {
        queued: 'In queue',
        fetch: 'Extracting page content',
        fetch_done: 'Content extracted',
        pass1: 'Knowledge pass 1',
        pass1_done: 'Research dossier received',
        pass2: 'Knowledge pass 2',
        pass2_done: 'Asset blueprint received',
        suggest: 'Loading ideas',
        init: 'Initializing',
        blueprint: 'Building PRD blueprint',
        blueprint_done: 'Blueprint received',
        content_chunk: 'Writing pages',
        content_chunk_done: 'AI response saved',
        polish: 'Design polish',
        polish_expand: 'Depth expansion',
        finalize: 'Final assembly',
        pages: 'Building funnel pages',
        bonuses: 'Generating bonuses',
        emails: 'Writing emails',
        webinar: 'Creating webinar funnels',
        completed: 'Complete',
        failed: 'Failed',
    };

    return labels[phase] ?? phase.replace(/_/g, ' ');
});
</script>

<template>
    <div
        v-if="generation && (isRunning || isFailed)"
        class="space-y-3 rounded-xl border p-4 shadow-sm"
        :class="isFailed ? 'border-red-200 bg-red-50/60' : 'border-blue-200/60 bg-blue-50/30'"
    >
        <div class="flex items-start gap-3">
            <Icon
                :icon="isFailed ? 'heroicons:exclamation-circle' : 'heroicons:arrow-path'"
                class="mt-0.5 size-5 shrink-0"
                :class="[isFailed ? 'text-red-600' : 'animate-spin text-blue-600']"
            />
            <div class="min-w-0 flex-1 space-y-1">
                <p class="text-sm font-medium">{{ generation.message || 'Working…' }}</p>
                <p v-if="generation.detail" class="text-xs text-muted-foreground">{{ generation.detail }}</p>
                <p class="text-xs text-blue-700">
                    <span class="font-medium">{{ phaseLabel }}</span>
                    <span v-if="generation.selected_title"> · {{ generation.selected_title }}</span>
                    <span v-if="generation.pages_total"> · {{ generation.pages_done ?? 0 }}/{{ generation.pages_total }} pages</span>
                    <span v-if="generation.model"> · {{ generation.model }}</span>
                </p>
            </div>
        </div>

        <div v-if="isRunning" class="space-y-1">
            <div class="h-2 overflow-hidden rounded-full bg-blue-100">
                <div
                    class="h-full rounded-full fill-brand-gradient transition-all duration-500"
                    :style="{ width: `${Math.max(progress, 8)}%` }"
                />
            </div>
            <p class="text-right text-[10px] tabular-nums text-muted-foreground">{{ progress }}%</p>
        </div>

        <p v-if="isFailed && generation.error" class="text-xs text-red-700">{{ generation.error }}</p>

        <ul v-if="recentEvents.length" class="space-y-1 border-t border-blue-200/40 pt-2 text-[11px] text-muted-foreground">
            <li v-for="(ev, i) in recentEvents" :key="i" class="flex gap-2">
                <span class="shrink-0 text-blue-500">●</span>
                <span>{{ ev.message }}<span v-if="ev.detail" class="text-muted-foreground/80"> — {{ ev.detail }}</span></span>
            </li>
        </ul>
    </div>
</template>
