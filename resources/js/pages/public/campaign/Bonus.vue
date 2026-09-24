<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type BonusItem = {
    uuid: string;
    title: string;
    bonus_type: string;
    type_label: string;
    description: string;
    value_label: string;
    cover_gradient: string;
    features: string[];
    viewer_url: string;
    download_url: string | null;
    cta_label: string;
    secondary_cta_label?: string | null;
};

const props = defineProps<{
    campaign: { name: string; type: string; uuid: string };
    content: {
        headline?: string;
        intro?: string;
        hero_label?: string;
        bonuses?: BonusItem[];
        affiliate_url?: string;
        cta?: string;
        brand_color?: string;
        total_value_label?: string;
    };
}>();

const brand = computed(() => props.content.brand_color || '#ea580c');
const bonuses = computed(() => props.content.bonuses ?? []);

function primaryHref(b: BonusItem): string {
    if (b.bonus_type === 'ebook' && b.download_url) return b.download_url;
    if (b.bonus_type === 'mini_course') return b.viewer_url;
    return b.download_url || b.viewer_url || '#';
}

function primaryTarget(b: BonusItem): string {
    return b.bonus_type === 'ebook' ? '_self' : '_blank';
}
</script>

<template>
    <Head :title="content.headline || 'Your Bonuses'" />
    <div class="min-h-screen bg-slate-100">
        <header
            class="relative overflow-hidden px-6 py-14 text-center text-white sm:py-16"
            :style="{ background: `linear-gradient(135deg, ${brand} 0%, #c2410c 100%)` }"
        >
            <p class="text-xs font-semibold uppercase tracking-[0.2em] opacity-90">{{ content.hero_label || 'YOUR BONUSES' }}</p>
            <h1 class="mx-auto mt-3 max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">{{ content.headline || 'Thanks — your bonuses are ready' }}</h1>
            <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-white/90">{{ content.intro }}</p>
            <div
                v-if="bonuses.length"
                class="mx-auto mt-6 inline-flex rounded-full bg-black/20 px-4 py-1.5 text-sm font-medium backdrop-blur"
            >
                {{ content.total_value_label || `${bonuses.length} bonuses` }}
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
            <div v-if="!bonuses.length" class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">
                Bonuses will appear here once configured in your campaign wizard.
            </div>

            <article
                v-for="b in bonuses"
                :key="b.uuid"
                class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-start">
                    <div
                        class="mx-auto flex h-40 w-32 shrink-0 items-end justify-center rounded-lg p-4 shadow-lg sm:mx-0"
                        :style="{ background: b.cover_gradient }"
                    >
                        <span class="text-center text-sm font-bold leading-tight text-white drop-shadow">{{ b.title }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2 class="text-xl font-bold text-slate-900">{{ b.title }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-brand-gradient px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{{ b.type_label }}</span>
                            <span class="text-sm font-semibold text-blue-700">{{ b.value_label }}</span>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ b.description }}</p>
                        <ul v-if="b.features?.length" class="mt-4 space-y-2">
                            <li v-for="(f, i) in b.features" :key="i" class="flex gap-2 text-sm text-slate-700">
                                <span class="mt-0.5 text-blue-600">✓</span>
                                <span>{{ f }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="flex shrink-0 flex-col gap-2 sm:w-44">
                        <a
                            :href="primaryHref(b)"
                            :target="primaryTarget(b)"
                            class="inline-flex items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
                            :style="{ background: brand }"
                        >
                            {{ b.cta_label }}
                        </a>
                        <a
                            v-if="b.bonus_type !== 'ebook'"
                            :href="b.viewer_url"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-800 transition hover:bg-slate-50"
                        >
                            Preview ↗
                        </a>
                        <a
                            v-else
                            :href="b.viewer_url"
                            target="_blank"
                            class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-5 py-2.5 text-sm font-medium text-slate-800 transition hover:bg-slate-50"
                        >
                            Read online ↗
                        </a>
                    </div>
                </div>
            </article>

            <div v-if="content.affiliate_url" class="mt-10 text-center">
                <a
                    :href="content.affiliate_url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex rounded-full px-8 py-3 text-base font-semibold text-white shadow-lg transition hover:opacity-90"
                    :style="{ background: brand }"
                >
                    {{ content.cta || 'Get Everything Now' }}
                </a>
            </div>
        </main>
    </div>
</template>
