<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

type Slide = {
    kind: string;
    title: string;
    subtitle?: string;
    body_html?: string;
    cover_gradient?: string;
};

const props = defineProps<{
    campaign: { name: string; uuid: string };
    bonus: {
        uuid: string;
        title: string;
        subtitle: string;
        bonus_type: string;
        slides: Slide[];
        download_url: string | null;
        stack_url: string;
    };
    username: string;
    slug: string;
}>();

const index = ref(0);
const touchStartX = ref(0);

const slides = computed(() => props.bonus.slides ?? []);
const total = computed(() => slides.value.length);
const current = computed(() => slides.value[index.value]);
const progress = computed(() => ((index.value + 1) / Math.max(total.value, 1)) * 100);
const lessonCount = computed(() =>
    props.bonus.bonus_type === 'mini_course'
        ? Math.max(0, total.value - 2)
        : Math.max(0, total.value - 3),
);

function go(delta: number) {
    index.value = Math.max(0, Math.min(index.value + delta, total.value - 1));
}

function goTo(i: number) {
    index.value = Math.max(0, Math.min(i, total.value - 1));
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'ArrowRight' || e.key === ' ') {
        e.preventDefault();
        go(1);
    }
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        go(-1);
    }
}

function onTouchStart(e: TouchEvent) {
    touchStartX.value = e.changedTouches[0].screenX;
}

function onTouchEnd(e: TouchEvent) {
    const diff = e.changedTouches[0].screenX - touchStartX.value;
    if (Math.abs(diff) > 50) go(diff < 0 ? 1 : -1);
}

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Head :title="bonus.title" />
    <div class="course-app">
        <div class="progress-track">
            <div class="progress-fill" :style="{ width: `${progress}%` }" />
        </div>

        <header class="course-header">
            <div class="brand">
                <div class="brand-icon">C</div>
                <div class="min-w-0">
                    <p class="brand-title">{{ bonus.title }}</p>
                    <p class="brand-meta">{{ lessonCount }} modules</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="page-badge">{{ index + 1 }} / {{ total }}</span>
                <Link :href="bonus.stack_url" class="back-link">All bonuses</Link>
            </div>
        </header>

        <main
            class="course-main"
            @touchstart.passive="onTouchStart"
            @touchend.passive="onTouchEnd"
        >
            <div v-if="current?.kind === 'cover'" class="slide-cover">
                <div
                    class="cover-art"
                    :style="{ background: current.cover_gradient || 'linear-gradient(145deg, #991b1b 0%, #450a0a 100%)' }"
                />
                <span class="preview-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    {{ bonus.bonus_type === 'mini_course' ? 'Course preview' : 'Ebook preview' }}
                </span>
                <h1 class="cover-title">{{ current.title }}</h1>
                <p v-if="current.subtitle" class="cover-lead">{{ current.subtitle }}</p>
                <p v-else-if="current.body_html" class="cover-lead viewer-body" v-html="current.body_html" />
                <p v-else class="cover-lead">Swipe or use the arrows below to explore this mini course.</p>
                <a
                    v-if="bonus.download_url"
                    :href="bonus.download_url"
                    class="download-btn"
                >
                    Download PDF
                </a>
            </div>

            <div v-else-if="current?.kind === 'outline'" class="w-full">
                <div class="content-card">
                    <p class="eyebrow">Structure</p>
                    <h2 class="slide-heading">{{ current.title }}</h2>
                    <p v-if="current.subtitle" class="slide-meta">{{ current.subtitle }}</p>
                    <div class="viewer-body" v-html="current.body_html" />
                </div>
            </div>

            <div v-else-if="current?.kind === 'intro'" class="w-full">
                <div class="content-card">
                    <p class="eyebrow">Introduction</p>
                    <h2 class="slide-heading">{{ current.title }}</h2>
                    <div class="viewer-body slide-body" v-html="current.body_html" />
                </div>
            </div>

            <div v-else class="w-full">
                <div class="content-card">
                    <p v-if="current?.subtitle" class="lesson-label">{{ current.subtitle }}</p>
                    <p v-else class="lesson-label">Lesson</p>
                    <h2 class="slide-heading">{{ current?.title }}</h2>
                    <div class="viewer-body slide-body" v-html="current?.body_html" />
                </div>
            </div>
        </main>

        <footer class="course-footer">
            <button
                type="button"
                class="btn-ghost"
                :disabled="index === 0"
                @click="go(-1)"
            >
                <span aria-hidden="true">‹</span> Previous
            </button>

            <div class="dots">
                <button
                    v-for="(_, i) in slides"
                    :key="i"
                    type="button"
                    class="dot"
                    :class="{ active: i === index }"
                    :aria-label="`Go to slide ${i + 1}`"
                    @click="goTo(i)"
                />
            </div>

            <button
                type="button"
                class="btn-primary"
                :disabled="index >= total - 1"
                @click="go(1)"
            >
                Next <span aria-hidden="true">›</span>
            </button>
        </footer>
    </div>
</template>

<style scoped>
.course-app {
    max-width: 720px;
    margin: 0 auto;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    color: #0f172a;
    font-family: Inter, system-ui, sans-serif;
}

.progress-track {
    height: 3px;
    background: #ede9fe;
    width: 100%;
}

.progress-fill {
    height: 100%;
    background: #7c3aed;
    transition: width 0.35s ease;
}

.course-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #e2e8f0;
    background: #fff;
}

.brand {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.brand-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: #7c3aed;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 15px;
    flex-shrink: 0;
}

.brand-title {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 220px;
}

.brand-meta {
    margin: 2px 0 0;
    font-size: 12px;
    color: #64748b;
}

.page-badge {
    font-size: 12px;
    font-weight: 500;
    color: #64748b;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 6px 12px;
    border-radius: 999px;
}

.back-link {
    font-size: 12px;
    color: #7c3aed;
    text-decoration: none;
}

.back-link:hover {
    text-decoration: underline;
}

.course-main {
    flex: 1;
    padding: 32px 20px 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.slide-cover {
    text-align: center;
    max-width: 420px;
    margin: 0 auto;
    width: 100%;
}

.cover-art {
    width: 100%;
    max-width: 280px;
    aspect-ratio: 3 / 4;
    margin: 0 auto 24px;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12);
    position: relative;
    overflow: hidden;
}

.cover-art::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.35;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 8px,
        rgba(255, 255, 255, 0.08) 8px,
        rgba(255, 255, 255, 0.08) 16px
    );
}

.preview-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    color: #6d28d9;
    background: #ede9fe;
    padding: 6px 12px;
    border-radius: 999px;
    margin-bottom: 16px;
}

.cover-title {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 12px;
    line-height: 1.2;
    letter-spacing: -0.02em;
}

.cover-lead {
    font-size: 15px;
    color: #64748b;
    line-height: 1.6;
    margin: 0;
}

.download-btn {
    display: inline-flex;
    margin-top: 24px;
    padding: 10px 20px;
    border-radius: 999px;
    background: #7c3aed;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
}

.content-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 28px 24px;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.04);
    width: 100%;
}

.eyebrow,
.lesson-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #7c3aed;
    margin: 0 0 8px;
}

.slide-heading {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 8px;
    line-height: 1.25;
    letter-spacing: -0.02em;
}

.slide-meta {
    font-size: 14px;
    color: #64748b;
    margin: 0 0 20px;
}

.course-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px 24px;
    border-top: 1px solid #e2e8f0;
    background: #fff;
}

.btn-ghost,
.btn-primary {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 10px 18px;
    font-size: 14px;
    font-weight: 600;
    border-radius: 999px;
    cursor: pointer;
    font-family: inherit;
    transition: opacity 0.2s, background 0.2s;
}

.btn-ghost {
    background: transparent;
    border: 1px solid #e2e8f0;
    color: #64748b;
}

.btn-ghost:hover:not(:disabled) {
    background: #f8fafc;
    color: #0f172a;
}

.btn-ghost:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.btn-primary {
    background: #7c3aed;
    border: none;
    color: #fff;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.35);
}

.btn-primary:hover:not(:disabled) {
    background: #6d28d9;
}

.btn-primary:disabled {
    opacity: 0.35;
    cursor: not-allowed;
    box-shadow: none;
}

.dots {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    flex: 1;
    max-width: 200px;
}

.dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #cbd5e1;
    border: none;
    padding: 0;
    cursor: pointer;
    transition: all 0.25s ease;
}

.dot.active {
    width: 28px;
    background: #7c3aed;
}

.viewer-body :deep(.viewer-lead) {
    font-size: 15px;
    line-height: 1.6;
    color: #64748b;
}

.viewer-body :deep(.viewer-outline) {
    list-style: none;
    padding: 0;
    margin: 8px 0 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.viewer-body :deep(.viewer-outline li) {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #f8fafc;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
}

.viewer-body :deep(.viewer-outline li span) {
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #7c3aed;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    flex-shrink: 0;
}

.slide-body {
    font-size: 15px;
    line-height: 1.75;
    color: #334155;
}

.viewer-body :deep(.viewer-takeaway) {
    margin-top: 24px;
    padding: 14px 16px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 12px;
    font-size: 14px;
    color: #1e40af;
}

.viewer-body :deep(.slide-card),
.viewer-body :deep(.doc-card) {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px 18px;
    margin: 16px 0;
}

.viewer-body :deep(.slide-checklist),
.viewer-body :deep(.doc-checklist) {
    list-style: none;
    padding: 0;
}

.viewer-body :deep(.slide-checklist li),
.viewer-body :deep(.doc-checklist li) {
    padding: 8px 0 8px 24px;
    position: relative;
    border-bottom: 1px solid #f1f5f9;
}

.viewer-body :deep(.slide-checklist li::before),
.viewer-body :deep(.doc-checklist li::before) {
    content: '✓';
    position: absolute;
    left: 0;
    color: #7c3aed;
    font-weight: 700;
}
</style>
