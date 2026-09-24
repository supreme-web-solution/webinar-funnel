<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { GenerationState } from '@/components/campaigns/GenerationProgressPanel.vue';

const props = defineProps<{
    open: boolean;
    generation: GenerationState | null;
    stepLabel?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const isRunning = computed(() => props.generation?.status === 'running');
const isFailed = computed(() => props.generation?.status === 'failed');
const progress = computed(() => Math.min(100, Math.max(0, props.generation?.progress ?? 0)));

const displayedMessage = ref('');
const streamTarget = ref('');
let streamTimer: ReturnType<typeof setInterval> | null = null;

watch(
    () => props.generation?.message,
    (msg) => {
        streamTarget.value = msg ?? 'Working…';
    },
    { immediate: true },
);

watch(streamTarget, (target) => {
    if (streamTimer) clearInterval(streamTimer);
    if (!target) {
        displayedMessage.value = '';
        return;
    }
    displayedMessage.value = '';
    let i = 0;
    streamTimer = setInterval(() => {
        if (i >= target.length) {
            if (streamTimer) clearInterval(streamTimer);
            streamTimer = null;
            return;
        }
        displayedMessage.value += target[i];
        i += 1;
    }, 14);
});

const phaseLabel = computed(() => {
    const phase = props.generation?.phase ?? '';
    const labels: Record<string, string> = {
        queued: 'Queued — waiting for worker',
        fetch: 'Extracting sales page content',
        fetch_done: 'Content extracted',
        pass1: 'AI pass 1 — research dossier',
        pass1_done: 'Research dossier saved',
        pass2: 'AI pass 2 — asset blueprint',
        pass2_done: 'Blueprint saved',
        suggest: 'Generating ideas',
        bonuses_suggest: 'Generating bonus concepts',
        init: 'Initializing pipeline',
        blueprint: 'Building document blueprint',
        blueprint_done: 'Blueprint ready',
        content_chunk: 'Writing content pages',
        content_chunk_done: 'Pages saved to draft',
        polish: 'Design polish pass',
        polish_expand: 'Expanding depth & quality',
        finalize: 'Assembling final document',
        pages: 'Building funnel pages',
        bonuses: 'Building bonus deliverable',
        emails: 'Writing email swipes',
        webinar: 'Creating webinar funnels',
        completed: 'Complete',
        failed: 'Failed',
    };

    return labels[phase] ?? phase.replace(/_/g, ' ');
});

const eventStream = computed(() => {
    const events = props.generation?.events ?? [];
    return [...events].slice(-8).reverse();
});

const skeletonLines = computed(() => {
    const p = progress.value;
    if (p >= 90) return 1;
    if (p >= 60) return 2;
    if (p >= 30) return 3;

    return 4;
});

function closeModal() {
    if (isRunning.value) return;
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="closeModal">
        <DialogContent
            class="gap-0 overflow-hidden rounded-xl border border-border/60 p-0 shadow-xl sm:max-w-md"
            :show-close-button="!isRunning"
            @pointer-down-outside="(e) => isRunning && e.preventDefault()"
            @escape-key-down="(e) => isRunning && e.preventDefault()"
        >
            <!-- Header -->
            <div
                class="relative px-6 pt-6 pb-4"
                :class="isFailed ? 'bg-linear-to-br from-red-50 to-white' : 'bg-linear-to-br from-blue-50 via-blue-50/60 to-white'"
            >
                <div class="pointer-events-none absolute -right-8 -top-8 size-32 rounded-full bg-blue-400/20 blur-2xl" />
                <div class="pointer-events-none absolute -left-4 bottom-0 size-24 rounded-full bg-blue-400/15 blur-xl" />

                <DialogHeader class="relative text-left">
                    <div class="mb-3 flex items-center gap-3">
                        <div
                            class="flex size-11 items-center justify-center rounded-xl shadow-sm"
                            :class="isFailed ? 'bg-red-100 text-red-600' : 'bg-white text-blue-600 ring-1 ring-blue-100'"
                        >
                            <Icon
                                :icon="isFailed ? 'heroicons:exclamation-triangle' : 'heroicons:sparkles'"
                                class="size-5"
                                :class="isRunning ? 'animate-pulse' : ''"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <DialogTitle class="text-base">
                                {{ isFailed ? 'Generation failed' : (stepLabel ?? 'AI generation') }}
                            </DialogTitle>
                            <DialogDescription class="text-xs">
                                {{ isRunning ? 'Running in background — stay on this step until complete' : 'Something went wrong' }}
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <!-- Live message -->
                <div class="relative mt-1 min-h-12 rounded-xl border border-blue-200/60 bg-white/80 px-3 py-2.5 backdrop-blur-sm">
                    <p class="text-sm font-medium leading-snug text-foreground">
                        {{ displayedMessage }}<span v-if="isRunning" class="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-blue-500 align-middle" />
                    </p>
                    <p v-if="generation?.detail" class="mt-1 text-xs text-muted-foreground">
                        {{ generation.detail }}
                    </p>
                </div>
            </div>

            <div class="space-y-4 bg-white px-6 pb-6 pt-4">
                <!-- Progress -->
                <div v-if="isRunning" class="space-y-2">
                    <div class="flex items-center justify-between text-[11px] text-muted-foreground">
                        <span class="font-medium text-blue-700">{{ phaseLabel }}</span>
                        <span class="tabular-nums">{{ progress }}%</span>
                    </div>
                    <div class="relative h-2 overflow-hidden rounded-full bg-blue-100/80">
                        <div
                            class="absolute inset-y-0 left-0 rounded-full fill-brand-gradient transition-all duration-700 ease-out"
                            :style="{ width: `${Math.max(progress, 6)}%` }"
                        />
                        <div class="absolute inset-0 animate-shimmer bg-linear-to-r from-transparent via-white/40 to-transparent" />
                    </div>
                    <div v-if="generation?.pages_total" class="text-[11px] text-muted-foreground">
                        Pages {{ generation.pages_done ?? 0 }} / {{ generation.pages_total }}
                        <span v-if="generation.selected_title"> · {{ generation.selected_title }}</span>
                    </div>
                </div>

                <!-- Skeleton preview -->
                <div v-if="isRunning" class="space-y-2 rounded-xl border border-dashed border-blue-200/50 bg-blue-50/20 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Preview assembling</p>
                    <div v-for="n in skeletonLines" :key="n" class="space-y-1.5">
                        <div
                            class="h-2.5 animate-pulse rounded-md bg-blue-200/50"
                            :style="{ width: `${100 - n * 12}%`, animationDelay: `${n * 120}ms` }"
                        />
                    </div>
                    <div class="mt-2 flex gap-2">
                        <div class="h-16 flex-1 animate-pulse rounded-lg bg-blue-100/50" />
                        <div class="h-16 w-1/3 animate-pulse rounded-lg bg-blue-200/40" style="animation-delay: 200ms" />
                    </div>
                </div>

                <!-- Event stream -->
                <div v-if="eventStream.length && isRunning" class="max-h-36 overflow-y-auto rounded-xl border border-border/60 bg-blue-50/30 p-2.5">
                    <p class="mb-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Activity</p>
                    <ul class="space-y-2">
                        <li
                            v-for="(ev, i) in eventStream"
                            :key="i"
                            class="flex gap-2 text-[11px] leading-snug"
                            :class="i === 0 ? 'font-medium text-blue-800' : 'text-muted-foreground'"
                        >
                            <Icon icon="heroicons:chevron-right" class="mt-0.5 size-3 shrink-0 text-blue-500/70" />
                            <span>
                                {{ ev.message }}
                                <span v-if="ev.detail" class="block text-[10px] font-normal opacity-75">{{ ev.detail }}</span>
                            </span>
                        </li>
                    </ul>
                </div>

                <!-- Failed -->
                <div v-if="isFailed" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    {{ generation?.error ?? generation?.detail ?? 'Generation failed. Check queue worker and try again.' }}
                </div>

                <p v-if="isRunning" class="text-center text-[10px] text-muted-foreground">
                    You can keep this tab open — we&apos;ll update when finished
                </p>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(200%); }
}
.animate-shimmer {
    animation: shimmer 2s infinite;
}
</style>
