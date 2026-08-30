<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type BonusMeta = {
    download_url?: string | null;
    viewer_url?: string | null;
    pages?: Array<{ page?: number; title?: string; body_html?: string }>;
    slides?: Array<{ slide?: number; title?: string; subtitle?: string; body_html?: string; takeaway?: string }>;
};

const props = defineProps<{
    bonus: {
        id: number;
        title: string;
        bonus_type: string;
        content: string | null;
        meta?: BonusMeta | null;
    };
}>();

const slideIndex = ref(0);

const slides = computed(() => props.bonus.meta?.slides ?? []);
const pages = computed(() => props.bonus.meta?.pages ?? []);
const isCourse = computed(() => props.bonus.bonus_type === 'mini_course');
const viewerUrl = computed(() => props.bonus.meta?.viewer_url ?? props.bonus.meta?.download_url ?? null);

const currentSlide = computed(() => slides.value[slideIndex.value] ?? null);

function prevSlide() {
    slideIndex.value = Math.max(0, slideIndex.value - 1);
}

function nextSlide() {
    slideIndex.value = Math.min(slides.value.length - 1, slideIndex.value + 1);
}
</script>

<template>
    <div class="rounded-lg border bg-white p-4">
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <p class="font-semibold text-slate-900">{{ bonus.title }}</p>
            <Badge variant="secondary">{{ bonus.bonus_type === 'mini_course' ? 'Mini Course' : 'Ebook' }}</Badge>
            <a
                v-if="viewerUrl"
                :href="viewerUrl"
                target="_blank"
                class="ml-auto text-sm text-indigo-600 underline"
            >
                Open full viewer / Save as PDF
            </a>
        </div>

        <!-- Mini course slide preview -->
        <div v-if="isCourse && slides.length" class="course-preview rounded-lg border border-slate-800 bg-slate-900 p-6 text-slate-100">
            <p class="mb-2 text-xs uppercase tracking-wide text-slate-400">
                Lesson {{ slideIndex + 1 }} / {{ slides.length }}
            </p>
            <h4 class="mb-2 text-xl font-bold">{{ currentSlide?.title }}</h4>
            <p v-if="currentSlide?.subtitle" class="mb-4 text-sm text-slate-400">{{ currentSlide.subtitle }}</p>
            <div class="course-slide-body max-h-64 overflow-auto text-sm leading-relaxed" v-html="currentSlide?.body_html" />
            <p v-if="currentSlide?.takeaway" class="mt-4 rounded-lg bg-sky-950/50 p-3 text-sm text-sky-100">
                <strong>Takeaway:</strong> {{ currentSlide.takeaway }}
            </p>
            <div class="mt-4 flex gap-2">
                <Button variant="outline" size="sm" :disabled="slideIndex === 0" @click="prevSlide">
                    <Icon icon="heroicons:chevron-left" class="size-4" /> Prev
                </Button>
                <Button variant="outline" size="sm" :disabled="slideIndex >= slides.length - 1" @click="nextSlide">
                    Next <Icon icon="heroicons:chevron-right" class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Ebook page preview -->
        <div v-else-if="pages.length" class="max-h-[28rem] space-y-6 overflow-auto rounded-lg border p-4">
            <div v-for="p in pages" :key="p.page" class="border-b pb-6 last:border-0">
                <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-indigo-600">Chapter {{ p.page }}</p>
                <h4 class="mb-3 font-serif text-lg font-bold text-slate-900">{{ p.title }}</h4>
                <div class="bonus-doc-preview prose prose-sm max-w-none" v-html="p.body_html" />
            </div>
        </div>

        <p v-else-if="bonus.content" class="text-sm text-muted-foreground whitespace-pre-wrap">{{ bonus.content }}</p>
    </div>
</template>

<style scoped>
.course-slide-body :deep(.slide-card) {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid #475569;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 12px 0;
}
.bonus-doc-preview :deep(.doc-card),
.bonus-doc-preview :deep(.doc-tip) {
    background: #f8fafc;
    border-left: 4px solid #4f46e5;
    padding: 12px 16px;
    margin: 12px 0;
    border-radius: 0 8px 8px 0;
}
</style>
