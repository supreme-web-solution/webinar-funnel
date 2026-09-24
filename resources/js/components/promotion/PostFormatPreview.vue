<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref } from 'vue';

export type PreviewSlide = {
    headline?: string;
    body?: string;
    image_url?: string;
};

const props = withDefaults(
    defineProps<{
        kind: 'carousel' | 'thread' | 'image' | 'video' | 'text' | 'email';
        aspectRatio?: string | null;
        slides?: PreviewSlide[];
        threadParts?: string[];
        imageUrl?: string | null;
        videoUrl?: string | null;
        videoPoster?: string | null;
        textBody?: string | null;
        emailSubject?: string | null;
        generating?: boolean;
        progressLabel?: string | null;
        progressPercent?: number | null;
        formatLabel?: string | null;
    }>(),
    {
        aspectRatio: null,
        slides: () => [],
        threadParts: () => [],
        imageUrl: null,
        videoUrl: null,
        videoPoster: null,
        textBody: null,
        emailSubject: null,
        generating: false,
        progressLabel: null,
        progressPercent: null,
        formatLabel: null,
    },
);

const activeSlide = ref(0);

const slideCount = computed(() => Math.max(props.slides.length, 1));

const aspectClass = computed(() => {
    if (props.kind === 'carousel') {
        return aspectToClass(props.aspectRatio ?? '4:5');
    }
    if (props.kind === 'video') {
        return props.aspectRatio === '16:9' ? 'aspect-video' : 'aspect-[9/16]';
    }
    if (props.kind === 'image' || props.kind === 'thread') {
        return aspectToClass(props.aspectRatio ?? '4:5');
    }
    if (props.kind === 'email') {
        return 'aspect-[4/3]';
    }
    return 'min-h-[140px]';
});

const currentSlide = computed(() => props.slides[activeSlide.value] ?? props.slides[0] ?? null);

const slidesWithVisual = computed(() =>
    props.slides.filter((s) => s.image_url || s.headline || s.body),
);

function aspectToClass(ratio: string): string {
    switch (ratio) {
        case '9:16':
            return 'aspect-[9/16]';
        case '16:9':
            return 'aspect-video';
        case '1:1':
            return 'aspect-square';
        case '2:3':
            return 'aspect-[2/3]';
        case '4:5':
        default:
            return 'aspect-[4/5]';
    }
}

function goTo(index: number): void {
    if (slidesWithVisual.value.length === 0) return;
    const max = Math.max(props.slides.length, 1);
    activeSlide.value = ((index % max) + max) % max;
}

function prev(): void {
    goTo(activeSlide.value - 1);
}

function next(): void {
    goTo(activeSlide.value + 1);
}

let touchStartX = 0;

function onTouchStart(e: TouchEvent): void {
    touchStartX = e.changedTouches[0]?.clientX ?? 0;
}

function onTouchEnd(e: TouchEvent): void {
    const delta = (e.changedTouches[0]?.clientX ?? 0) - touchStartX;
    if (Math.abs(delta) < 40) return;
    if (delta < 0) next();
    else prev();
}
</script>

<template>
    <!-- Carousel — swipeable, format-native aspect -->
    <div
        v-if="kind === 'carousel'"
        class="relative w-full overflow-hidden bg-neutral-100 touch-pan-y"
        :class="aspectClass"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
    >
        <template v-if="slides.length > 0">
            <div class="absolute inset-0 flex items-center justify-center">
                <img
                    v-if="currentSlide?.image_url"
                    :src="currentSlide.image_url"
                    :alt="currentSlide.headline ?? `Slide ${activeSlide + 1}`"
                    class="h-full w-full object-cover"
                />
                <div
                    v-else
                    class="flex h-full w-full flex-col justify-start gap-4 border-l-4 border-blue-600 bg-gradient-to-br from-stone-50 to-white px-8 py-12 text-left"
                >
                    <span class="text-[0.6rem] font-semibold uppercase tracking-[0.2em] text-blue-700/75">
                        Slide {{ activeSlide + 1 }} / {{ slides.length }}
                    </span>
                    <p v-if="currentSlide?.headline" class="max-w-[92%] font-serif text-xl font-bold leading-relaxed text-stone-900">
                        {{ currentSlide.headline }}
                    </p>
                    <p v-if="currentSlide?.body" class="max-w-[90%] text-sm leading-7 text-stone-600 line-clamp-5">
                        {{ currentSlide.body }}
                    </p>
                    <p v-if="!currentSlide?.headline && !currentSlide?.body" class="text-sm text-muted-foreground italic">
                        Waiting for slide content…
                    </p>
                </div>
            </div>

            <button
                v-if="slides.length > 1"
                type="button"
                class="absolute left-2 top-1/2 z-20 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60"
                aria-label="Previous slide"
                @click.stop="prev"
            >
                <Icon icon="heroicons:chevron-left" class="size-4" />
            </button>
            <button
                v-if="slides.length > 1"
                type="button"
                class="absolute right-2 top-1/2 z-20 flex size-8 -translate-y-1/2 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur-sm transition hover:bg-black/60"
                aria-label="Next slide"
                @click.stop="next"
            >
                <Icon icon="heroicons:chevron-right" class="size-4" />
            </button>

            <div
                v-if="slides.length > 1"
                class="absolute bottom-3 left-0 right-0 z-20 flex items-center justify-center gap-1.5"
            >
                <button
                    v-for="(_, i) in slides"
                    :key="i"
                    type="button"
                    class="rounded-full transition-all"
                    :class="i === activeSlide ? 'size-2 bg-white shadow' : 'size-1.5 bg-white/50 hover:bg-white/80'"
                    :aria-label="`Go to slide ${i + 1}`"
                    @click.stop="goTo(i)"
                />
            </div>

            <div class="absolute left-3 top-3 z-20 rounded-full bg-black/50 px-2 py-0.5 text-[0.6rem] font-semibold text-white backdrop-blur-sm">
                {{ activeSlide + 1 }} / {{ slides.length }}
            </div>
        </template>

        <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
            <Icon icon="heroicons:squares-2x2" class="size-10 opacity-40" />
            <span class="text-xs">{{ formatLabel ?? 'Carousel' }}</span>
        </div>

        <div
            v-if="generating && progressLabel"
            class="absolute inset-x-0 bottom-0 z-30 bg-black/60 px-3 py-2 text-center text-[0.65rem] font-medium text-white"
        >
            {{ progressLabel }}
            <div v-if="progressPercent !== null" class="mx-auto mt-1.5 h-1 max-w-[120px] overflow-hidden rounded-full bg-white/25">
                <div class="h-full rounded-full bg-white transition-all" :style="{ width: `${progressPercent}%` }" />
            </div>
        </div>
    </div>

    <!-- Thread -->
    <div
        v-else-if="kind === 'thread'"
        class="w-full overflow-y-auto bg-slate-950 p-4 text-left"
        :class="aspectClass"
    >
        <div
            v-for="(part, partIdx) in threadParts.slice(0, 6)"
            :key="partIdx"
            class="mb-2.5 rounded-xl border border-slate-700 bg-slate-900/90 px-3 py-2.5"
        >
            <p class="text-[0.6rem] font-semibold text-slate-400">{{ partIdx + 1 }}/{{ threadParts.length }}</p>
            <p class="mt-1 text-xs leading-relaxed text-slate-100 line-clamp-4">{{ part }}</p>
        </div>
        <p v-if="threadParts.length > 6" class="text-center text-[0.65rem] text-slate-500">+{{ threadParts.length - 6 }} more posts</p>
    </div>

    <!-- Video -->
    <div v-else-if="kind === 'video'" class="relative w-full bg-black" :class="aspectClass">
        <video
            v-if="videoUrl"
            :src="videoUrl"
            :poster="videoPoster ?? undefined"
            class="h-full w-full object-cover"
            controls
            preload="metadata"
        />
        <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-white/70">
            <Icon icon="heroicons:video-camera" class="size-10 opacity-50" />
            <span class="text-xs">{{ generating ? (progressLabel ?? 'Generating video…') : 'Video post' }}</span>
        </div>
    </div>

    <!-- Single image -->
    <div v-else-if="kind === 'image'" class="relative w-full overflow-hidden bg-muted/30" :class="aspectClass">
        <img v-if="imageUrl" :src="imageUrl" alt="" class="h-full w-full object-cover" />
        <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
            <Icon icon="heroicons:photo" class="size-10 opacity-40" />
            <span class="text-xs">{{ generating ? (progressLabel ?? 'Generating image…') : (formatLabel ?? 'Image post') }}</span>
        </div>
    </div>

    <!-- Email -->
    <div
        v-else-if="kind === 'email'"
        class="flex w-full flex-col justify-center gap-2 border-b border-purple-200/40 bg-gradient-to-br from-purple-50 to-white px-5 py-6"
        :class="aspectClass"
    >
        <Icon icon="heroicons:envelope" class="size-8 text-purple-500/70" />
        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700/80">Email copy</p>
        <p v-if="emailSubject" class="text-sm font-bold leading-snug text-purple-950 line-clamp-2">{{ emailSubject }}</p>
        <p v-if="textBody" class="text-xs leading-relaxed text-muted-foreground line-clamp-3">{{ textBody }}</p>
    </div>

    <!-- Text-only -->
    <div
        v-else
        class="flex w-full flex-col justify-center gap-2 bg-gradient-to-br from-sky-50/80 to-white px-5 py-6"
        :class="aspectClass"
    >
        <Icon icon="heroicons:chat-bubble-bottom-center-text" class="size-8 text-sky-500/60" />
        <p v-if="formatLabel" class="text-[0.65rem] font-semibold uppercase tracking-wide text-sky-800/70">{{ formatLabel }}</p>
        <p v-if="textBody" class="text-sm leading-relaxed text-foreground line-clamp-5">{{ textBody }}</p>
        <p v-else class="text-sm italic text-muted-foreground">{{ generating ? (progressLabel ?? 'Generating…') : 'Text post' }}</p>
    </div>
</template>
