<script setup lang="ts">
import { computed } from 'vue';

type BonusInput = {
    uuid: string;
    title: string;
    bonus_type: string;
    content?: string | null;
    meta?: Record<string, unknown> | null;
};

const props = defineProps<{
    headline?: string;
    intro?: string;
    heroLabel?: string;
    brandColor?: string;
    cta?: string;
    featuredUuids?: string[];
    bonuses: BonusInput[];
}>();

const brand = computed(() => props.brandColor || '#ea580c');

function typeLabel(type: string): string {
    if (type === 'mini_course') return 'MINI COURSE';
    if (type === 'mini_app') return 'MINI APP';
    return 'PDF GUIDE';
}

function valueLabel(type: string, meta: Record<string, unknown>): string {
    if (typeof meta.value_label === 'string' && meta.value_label) return meta.value_label;
    if (type === 'mini_course') return '$97 value';
    if (type === 'mini_app') return '$47 value';
    return '$27 value';
}

function coverGradient(type: string, meta: Record<string, unknown>): string {
    if (typeof meta.cover_gradient === 'string' && meta.cover_gradient) return meta.cover_gradient;
    if (type === 'mini_course') return 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)';
    if (type === 'mini_app') return 'linear-gradient(135deg,#0ea5e9 0%,#06b6d4 100%)';
    return 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)';
}

function description(content: string | null | undefined): string {
    const text = (content ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (text.length <= 280) return text;
    return `${text.slice(0, 277)}…`;
}

function features(type: string, meta: Record<string, unknown>): string[] {
    if (Array.isArray(meta.features) && meta.features.length) return meta.features.map(String);
    if (type === 'ebook') {
        const count = Array.isArray(meta.pages) ? meta.pages.length : 0;
        return [
            count > 0 ? `${count}-chapter professional PDF guide` : 'Professional PDF guide',
            'Instant download — yours to keep',
            'Actionable tactics specific to this offer',
        ];
    }
    const count = Array.isArray(meta.slides) ? meta.slides.length : 0;
    return [
        count > 0 ? `${count} swipeable lessons` : 'Step-by-step mini course format',
        'Step-by-step mini course format',
        'Works on mobile and desktop',
    ];
}

function ctaLabel(type: string): string {
    if (type === 'mini_course') return 'Open course ↗';
    if (type === 'mini_app') return 'Get access ↗';
    return 'Download PDF';
}

const cards = computed(() => {
    const featured = props.featuredUuids ?? [];
    const list = featured.length
        ? props.bonuses.filter((b) => featured.includes(b.uuid))
        : props.bonuses;

    return list.map((b) => {
        const meta = (b.meta ?? {}) as Record<string, unknown>;
        return {
            uuid: b.uuid,
            title: b.title,
            type_label: typeLabel(b.bonus_type),
            description: description(b.content),
            value_label: valueLabel(b.bonus_type, meta),
            cover_gradient: coverGradient(b.bonus_type, meta),
            features: features(b.bonus_type, meta),
            cta_label: ctaLabel(b.bonus_type),
        };
    });
});

const totalLabel = computed(() => {
    const count = cards.value.length;
    if (!count) return '';
    const total = cards.value.reduce((sum, b) => {
        const m = b.value_label.match(/\$(\d+)/);
        return sum + (m ? Number(m[1]) : 0);
    }, 0);
    if (total > 0) return `${count} bonus${count === 1 ? '' : 'es'} · $${total} total value`;
    return `${count} bonus${count === 1 ? '' : 'es'}`;
});
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-slate-100">
        <header
            class="relative px-6 py-10 text-center text-white sm:py-12"
            :style="{ background: `linear-gradient(135deg, ${brand} 0%, #c2410c 100%)` }"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.2em] opacity-90">{{ heroLabel || 'YOUR BONUSES' }}</p>
            <h1 class="mx-auto mt-3 max-w-3xl text-2xl font-bold tracking-tight sm:text-3xl">{{ headline || 'Thanks — your bonuses are ready' }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-white/90">{{ intro || 'Exclusive bonuses when you purchase through our link.' }}</p>
            <div v-if="totalLabel" class="mx-auto mt-5 inline-flex rounded-full bg-black/20 px-4 py-1.5 text-sm font-medium backdrop-blur">
                {{ totalLabel }}
            </div>
        </header>

        <main class="px-4 py-8 sm:px-6">
            <div v-if="!cards.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500">
                Select bonuses below to preview your public bonus stack page.
            </div>

            <article
                v-for="b in cards"
                :key="b.uuid"
                class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm last:mb-0"
            >
                <div class="flex flex-col gap-5 p-5 sm:flex-row sm:items-start">
                    <div
                        class="mx-auto flex h-36 w-28 shrink-0 items-end justify-center rounded-lg p-3 shadow-lg sm:mx-0"
                        :style="{ background: b.cover_gradient }"
                    >
                        <span class="text-center text-xs font-bold leading-tight text-white drop-shadow">{{ b.title }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-bold text-slate-900">{{ b.title }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-emerald-600 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{{ b.type_label }}</span>
                            <span class="text-sm font-semibold text-emerald-700">{{ b.value_label }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ b.description }}</p>
                        <ul v-if="b.features.length" class="mt-3 space-y-1.5">
                            <li v-for="(f, i) in b.features" :key="i" class="flex gap-2 text-sm text-slate-700">
                                <span class="mt-0.5 text-emerald-600">✓</span>
                                <span>{{ f }}</span>
                            </li>
                        </ul>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:w-40">
                        <span class="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-white" :style="{ background: brand }">{{ b.cta_label }}</span>
                        <span class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800">Preview ↗</span>
                    </div>
                </div>
            </article>

            <div v-if="cards.length" class="mt-8 text-center">
                <span class="inline-flex rounded-full px-8 py-3 text-base font-semibold text-white shadow-lg" :style="{ background: brand }">
                    {{ cta || 'Get Everything Now' }}
                </span>
            </div>
        </main>
    </div>
</template>
