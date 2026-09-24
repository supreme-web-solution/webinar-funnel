<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CampaignVideoEmbed from '@/components/campaigns/CampaignVideoEmbed.vue';
import QuizQuestionsEditor, { type QuizQuestion } from '@/components/campaigns/QuizQuestionsEditor.vue';

const props = defineProps<{
    pageType: 'squeeze' | 'thankyou' | 'quiz' | 'bonus';
    modelValue: Record<string, unknown>;
    publicUrl?: string;
    title?: string;
    previewBonuses?: Array<{
        uuid: string;
        title: string;
        bonus_type: string;
        content?: string | null;
        meta?: Record<string, unknown> | null;
    }>;
    featuredBonusUuids?: string[];
}>();

const emit = defineEmits<{
    save: [content: Record<string, unknown>];
    close: [];
}>();

const draft = ref<Record<string, unknown>>({ ...props.modelValue });
const device = ref<'desktop' | 'mobile'>('desktop');
const selectedBlock = ref<string>('headline');

watch(
    () => props.modelValue,
    (v) => {
        draft.value = { ...v };
    },
    { deep: true },
);

const brandColor = computed(() => {
    if (props.pageType === 'bonus') {
        return String(draft.value.brand_color ?? '#ea580c');
    }

    return String(draft.value.brand_color ?? '#4f46e5');
});
const bullets = computed(() => (Array.isArray(draft.value.bullet_points) ? draft.value.bullet_points as string[] : []));
const quizQuestions = computed({
    get: (): QuizQuestion[] => (Array.isArray(draft.value.questions) ? draft.value.questions as QuizQuestion[] : []),
    set: (value: QuizQuestion[]) => setField('questions', value),
});

type BonusPreviewItem = {
    uuid: string;
    title: string;
    bonus_type: string;
    type_label: string;
    description: string;
    value_label: string;
    cover_gradient: string;
    features: string[];
    cta_label: string;
};

function bonusTypeLabel(type: string): string {
    if (type === 'mini_course') return 'MINI COURSE';
    if (type === 'mini_app') return 'MINI APP';
    return 'PDF GUIDE';
}

function bonusCtaLabel(type: string): string {
    if (type === 'mini_app') return 'Get access ↗';
    const pageCta = typeof draft.value.cta === 'string' ? draft.value.cta.trim() : '';
    return pageCta || 'Get instant access ↗';
}

function bonusValueLabel(type: string, meta: Record<string, unknown>): string {
    if (typeof meta.value_label === 'string' && meta.value_label !== '') {
        return meta.value_label;
    }
    if (type === 'mini_course') return '$97 value';
    if (type === 'mini_app') return '$47 value';
    return '$27 value';
}

function bonusCoverGradient(type: string, meta: Record<string, unknown>): string {
    if (typeof meta.cover_gradient === 'string' && meta.cover_gradient !== '') {
        return meta.cover_gradient;
    }
    if (type === 'mini_course') return 'linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%)';
    if (type === 'mini_app') return 'linear-gradient(135deg,#0ea5e9 0%,#06b6d4 100%)';
    return 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)';
}

function bonusFeatures(type: string, meta: Record<string, unknown>): string[] {
    if (Array.isArray(meta.features) && meta.features.length > 0) {
        return meta.features.map(String);
    }
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

function bonusDescription(content: string | null | undefined): string {
    const text = (content ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (text.length <= 280) return text;
    return `${text.slice(0, 277)}…`;
}

const previewBonusCards = computed((): BonusPreviewItem[] => {
    const all = props.previewBonuses ?? [];
    const featured = props.featuredBonusUuids ?? [];
    const list = featured.length > 0 ? all.filter((b) => featured.includes(b.uuid)) : all;

    return list.map((b) => {
        const meta = (b.meta ?? {}) as Record<string, unknown>;
        return {
            uuid: b.uuid,
            title: b.title,
            bonus_type: b.bonus_type,
            type_label: bonusTypeLabel(b.bonus_type),
            description: bonusDescription(b.content),
            value_label: bonusValueLabel(b.bonus_type, meta),
            cover_gradient: bonusCoverGradient(b.bonus_type, meta),
            features: bonusFeatures(b.bonus_type, meta),
            cta_label: bonusCtaLabel(b.bonus_type),
        };
    });
});

const bonusTotalLabel = computed(() => {
    const count = previewBonusCards.value.length;
    if (count === 0) return '';
    const total = previewBonusCards.value.reduce((sum, b) => {
        const m = b.value_label.match(/\$(\d+)/);
        return sum + (m ? Number(m[1]) : 0);
    }, 0);
    if (total > 0) return `${count} bonus${count === 1 ? '' : 'es'} · $${total} total value`;
    return `${count} bonus${count === 1 ? '' : 'es'}`;
});

const canvasMaxWidth = computed(() => {
    if (props.pageType === 'bonus') {
        return device.value === 'mobile' ? 'w-[375px]' : 'w-full max-w-4xl';
    }

    return device.value === 'mobile' ? 'w-[375px]' : 'w-full max-w-2xl';
});

const blocks = computed(() => {
    if (props.pageType === 'squeeze') {
        return [
            { id: 'headline', label: 'Headline', icon: 'heroicons:h1' },
            { id: 'subheadline', label: 'Subheadline', icon: 'heroicons:bars-3-bottom-left' },
            { id: 'video', label: 'Video', icon: 'heroicons:video-camera' },
            { id: 'bullets', label: 'Bullet list', icon: 'heroicons:list-bullet' },
            { id: 'cta', label: 'Button', icon: 'heroicons:cursor-arrow-rays' },
            { id: 'brand_color', label: 'Brand color', icon: 'heroicons:swatch' },
        ];
    }
    if (props.pageType === 'thankyou') {
        return [
            { id: 'headline', label: 'Headline', icon: 'heroicons:h1' },
            { id: 'download_cta', label: 'Download button', icon: 'heroicons:arrow-down-tray' },
            { id: 'bridge_headline', label: 'Bridge headline', icon: 'heroicons:arrow-right' },
            { id: 'bridge_body', label: 'Bridge body', icon: 'heroicons:document-text' },
            { id: 'bridge_cta', label: 'Bridge CTA', icon: 'heroicons:link' },
            { id: 'brand_color', label: 'Brand color', icon: 'heroicons:swatch' },
        ];
    }
    if (props.pageType === 'quiz') {
        return [
            { id: 'title', label: 'Title', icon: 'heroicons:h1' },
            { id: 'intro', label: 'Intro', icon: 'heroicons:chat-bubble-left' },
            { id: 'questions', label: 'Questions', icon: 'heroicons:question-mark-circle' },
            { id: 'result_headline', label: 'Result headline', icon: 'heroicons:check-badge' },
        ];
    }

    return [
        { id: 'hero_label', label: 'Hero label', icon: 'heroicons:tag' },
        { id: 'headline', label: 'Headline', icon: 'heroicons:h1' },
        { id: 'intro', label: 'Intro', icon: 'heroicons:document-text' },
        { id: 'bonus_stack', label: 'Bonus cards', icon: 'heroicons:gift' },
        { id: 'cta', label: 'Footer CTA', icon: 'heroicons:cursor-arrow-rays' },
        { id: 'brand_color', label: 'Brand color', icon: 'heroicons:swatch' },
    ];
});

function setField(key: string, value: unknown) {
    draft.value = { ...draft.value, [key]: value };
}

function selectBlock(id: string) {
    selectedBlock.value = id;
}

function isSelected(id: string) {
    return selectedBlock.value === id;
}

function blockRing(id: string) {
    return isSelected(id) ? 'ring-2 ring-blue-500 ring-offset-2' : 'hover:ring-2 hover:ring-blue-200 hover:ring-offset-1';
}

function onInlineBlur(key: string, event: FocusEvent) {
    const el = event.target as HTMLElement;
    setField(key, el.innerText.trim());
}

const newBullet = ref('');

function addBullet() {
    const text = newBullet.value.trim();
    if (!text) return;
    setField('bullet_points', [...bullets.value, text]);
    newBullet.value = '';
    selectBlock('bullets');
}

function removeBullet(i: number) {
    setField('bullet_points', bullets.value.filter((_, idx) => idx !== i));
}

function moveBullet(i: number, dir: -1 | 1) {
    const next = [...bullets.value];
    const j = i + dir;
    if (j < 0 || j >= next.length) return;
    [next[i], next[j]] = [next[j], next[i]];
    setField('bullet_points', next);
}

function save() {
    emit('save', { ...draft.value });
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex flex-col bg-slate-900/95">
        <!-- Top bar -->
        <div class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-700 bg-slate-900 px-4 py-3 text-white">
            <div>
                <p class="font-semibold">{{ title ?? 'Page editor' }}</p>
                <p class="text-xs text-slate-400">Click any element on the page or pick a block — edits update live</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-lg border border-slate-600 p-0.5">
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-medium"
                        :class="device === 'desktop' ? 'chip-brand-active' : 'text-slate-400'"
                        @click="device = 'desktop'"
                    >
                        Desktop
                    </button>
                    <button
                        type="button"
                        class="rounded-md px-3 py-1.5 text-xs font-medium"
                        :class="device === 'mobile' ? 'chip-brand-active' : 'text-slate-400'"
                        @click="device = 'mobile'"
                    >
                        Mobile
                    </button>
                </div>
                <Button v-if="publicUrl" as-child variant="outline" size="sm" class="border-slate-600 bg-transparent text-white hover:bg-slate-800">
                    <a :href="publicUrl" target="_blank">Live page</a>
                </Button>
                <Button variant="ghost" size="sm" class="text-slate-300 hover:bg-slate-800 hover:text-white" @click="emit('close')">
                    Close
                </Button>
                <Button size="sm" variant="brand" @click="save">
                    Save changes
                </Button>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
            <!-- Block list -->
            <aside class="shrink-0 border-b border-slate-700 bg-slate-900 p-3 lg:w-52 lg:border-b-0 lg:border-r">
                <p class="mb-2 px-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">Page blocks</p>
                <div class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
                    <button
                        v-for="b in blocks"
                        :key="b.id"
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors lg:w-full"
                        :class="isSelected(b.id) ? 'chip-brand-active' : 'text-slate-300 hover:bg-slate-800'"
                        @click="selectBlock(b.id)"
                    >
                        <Icon :icon="b.icon" class="size-4 shrink-0" />
                        <span>{{ b.label }}</span>
                    </button>
                </div>
            </aside>

            <!-- Canvas -->
            <div class="flex flex-1 items-start justify-center overflow-auto bg-slate-200 p-4 lg:p-8">
                <div
                    class="origin-top transition-all shadow-2xl"
                    :class="canvasMaxWidth"
                >
                    <!-- Squeeze -->
                    <div v-if="pageType === 'squeeze'" class="min-h-[520px] bg-slate-950 px-6 py-12 text-center text-white">
                        <h1
                            contenteditable
                            suppresscontenteditablewarning
                            class="cursor-text rounded px-2 text-2xl font-bold outline-none sm:text-3xl"
                            :class="blockRing('headline')"
                            :style="{ color: brandColor }"
                            @click="selectBlock('headline')"
                            @blur="onInlineBlur('headline', $event)"
                        >{{ draft.headline || 'Click to edit headline' }}</h1>
                        <p
                            contenteditable
                            suppresscontenteditablewarning
                            class="mx-auto mt-4 max-w-lg cursor-text rounded px-2 text-sm text-slate-300 outline-none"
                            :class="blockRing('subheadline')"
                            @click="selectBlock('subheadline')"
                            @blur="onInlineBlur('subheadline', $event)"
                        >{{ draft.subheadline || 'Click to edit subheadline' }}</p>
                        <CampaignVideoEmbed
                            v-if="draft.video_url"
                            :url="String(draft.video_url)"
                            :title="String(draft.video_title ?? '')"
                            class="mx-auto mt-6 max-w-lg text-left"
                            :class="blockRing('video')"
                            @click="selectBlock('video')"
                        />
                        <ul
                            class="mx-auto mt-6 max-w-sm space-y-2 text-left text-sm text-slate-300"
                            :class="blockRing('bullets')"
                            @click="selectBlock('bullets')"
                        >
                            <li v-for="(b, i) in bullets" :key="i" class="flex gap-2 rounded px-1 py-0.5">
                                <span class="text-blue-400">✓</span>
                                <span>{{ b }}</span>
                            </li>
                            <li v-if="!bullets.length" class="text-slate-500 italic">Add bullet points in the panel →</li>
                        </ul>
                        <div class="mx-auto mt-8 max-w-sm space-y-2">
                            <div class="rounded-lg bg-white/10 px-4 py-3 text-left text-xs text-slate-400">Name field</div>
                            <div class="rounded-lg bg-white/10 px-4 py-3 text-left text-xs text-slate-400">Email field</div>
                            <button
                                type="button"
                                class="w-full rounded-lg py-3 font-semibold text-white"
                                :style="{ background: brandColor }"
                                :class="blockRing('cta')"
                                @click.stop="selectBlock('cta')"
                            >
                                <span
                                    contenteditable
                                    suppresscontenteditablewarning
                                    class="block outline-none"
                                    @blur="onInlineBlur('cta', $event)"
                                >{{ draft.cta || 'Get Instant Access' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Thank you -->
                    <div v-else-if="pageType === 'thankyou'" class="min-h-[520px] bg-slate-50 px-6 py-12 text-center">
                        <h1
                            contenteditable
                            suppresscontenteditablewarning
                            class="cursor-text rounded px-2 text-2xl font-bold text-slate-900 outline-none"
                            :class="blockRing('headline')"
                            @click="selectBlock('headline')"
                            @blur="onInlineBlur('headline', $event)"
                        >{{ draft.headline || 'Thank you headline' }}</h1>
                        <button
                            type="button"
                            class="mx-auto mt-6 inline-flex rounded-lg px-6 py-3 font-bold text-white"
                            :class="blockRing('download_cta')"
                            :style="{ background: brandColor }"
                            @click.stop="selectBlock('download_cta')"
                        >
                            <span
                                contenteditable
                                suppresscontenteditablewarning
                                class="outline-none"
                                @blur="onInlineBlur('download_cta', $event)"
                            >{{ draft.download_cta || 'DOWNLOAD NOW' }}</span>
                        </button>
                        <div
                            class="mt-8 rounded-2xl border bg-white p-5 text-left shadow-sm"
                            :class="blockRing('bridge_headline')"
                            @click="selectBlock('bridge_headline')"
                        >
                            <h2
                                contenteditable
                                suppresscontenteditablewarning
                                class="cursor-text font-semibold outline-none"
                                @blur="onInlineBlur('bridge_headline', $event)"
                            >{{ draft.bridge_headline || 'What happens next?' }}</h2>
                            <p
                                contenteditable
                                suppresscontenteditablewarning
                                class="mt-2 cursor-text text-sm text-slate-600 outline-none"
                                :class="blockRing('bridge_body')"
                                @click.stop="selectBlock('bridge_body')"
                                @blur="onInlineBlur('bridge_body', $event)"
                            >{{ draft.bridge_body || 'Bridge copy goes here…' }}</p>
                            <p
                                contenteditable
                                suppresscontenteditablewarning
                                class="mt-3 cursor-text text-sm font-semibold underline outline-none"
                                :class="blockRing('bridge_cta')"
                                :style="{ color: brandColor }"
                                @click.stop="selectBlock('bridge_cta')"
                                @blur="onInlineBlur('bridge_cta', $event)"
                            >{{ draft.bridge_cta || 'Continue to offer' }}</p>
                        </div>
                    </div>

                    <!-- Quiz -->
                    <div v-else-if="pageType === 'quiz'" class="min-h-[520px] bg-white px-6 py-12 text-center">
                        <h1
                            contenteditable
                            suppresscontenteditablewarning
                            class="cursor-text rounded text-2xl font-bold outline-none"
                            :class="blockRing('title')"
                            @click="selectBlock('title')"
                            @blur="onInlineBlur('title', $event)"
                        >{{ draft.title || 'Quiz title' }}</h1>
                        <p
                            contenteditable
                            suppresscontenteditablewarning
                            class="mx-auto mt-3 max-w-md cursor-text text-muted-foreground outline-none"
                            :class="blockRing('intro')"
                            @click="selectBlock('intro')"
                            @blur="onInlineBlur('intro', $event)"
                        >{{ draft.intro || 'Quiz intro text' }}</p>
                        <div
                            v-if="quizQuestions.length"
                            class="mx-auto mt-8 max-w-md space-y-4 text-left"
                            :class="blockRing('questions')"
                            @click.stop="selectBlock('questions')"
                        >
                            <div v-for="(q, qi) in quizQuestions" :key="qi" class="rounded-lg border bg-slate-50 p-3">
                                <p class="text-sm font-medium text-slate-800">{{ q.question || `Question ${qi + 1}` }}</p>
                                <div class="mt-2 space-y-1">
                                    <div v-for="(opt, oi) in q.options" :key="oi" class="rounded border bg-white px-2 py-1 text-xs text-slate-600">
                                        {{ opt || `Option ${oi + 1}` }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mx-auto mt-6 max-w-md text-xs text-muted-foreground">No questions yet — add them in the sidebar.</p>
                    </div>

                    <!-- Bonus stack (matches public Bonus.vue) -->
                    <div v-else class="min-h-[520px] overflow-hidden rounded-lg bg-slate-100 text-left">
                        <header
                            class="relative px-6 py-10 text-center text-white sm:py-12"
                            :style="{ background: `linear-gradient(135deg, ${brandColor} 0%, #c2410c 100%)` }"
                        >
                            <p
                                contenteditable
                                suppresscontenteditablewarning
                                class="cursor-text rounded text-xs font-semibold uppercase tracking-[0.2em] opacity-90 outline-none"
                                :class="blockRing('hero_label')"
                                @click="selectBlock('hero_label')"
                                @blur="onInlineBlur('hero_label', $event)"
                            >{{ draft.hero_label || 'YOUR BONUSES' }}</p>
                            <h1
                                contenteditable
                                suppresscontenteditablewarning
                                class="mx-auto mt-3 max-w-3xl cursor-text rounded px-2 text-2xl font-bold tracking-tight outline-none sm:text-3xl"
                                :class="blockRing('headline')"
                                @click="selectBlock('headline')"
                                @blur="onInlineBlur('headline', $event)"
                            >{{ draft.headline || 'Thanks — your bonuses are ready' }}</h1>
                            <p
                                contenteditable
                                suppresscontenteditablewarning
                                class="mx-auto mt-4 max-w-2xl cursor-text rounded px-2 text-sm leading-relaxed text-white/90 outline-none"
                                :class="blockRing('intro')"
                                @click="selectBlock('intro')"
                                @blur="onInlineBlur('intro', $event)"
                            >{{ draft.intro || 'Exclusive bonuses when you purchase through our link.' }}</p>
                            <div
                                v-if="bonusTotalLabel"
                                class="mx-auto mt-5 inline-flex rounded-full bg-black/20 px-4 py-1.5 text-sm font-medium backdrop-blur"
                            >
                                {{ bonusTotalLabel }}
                            </div>
                        </header>

                        <main class="px-4 py-8 sm:px-6">
                            <div
                                v-if="!previewBonusCards.length"
                                class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500"
                                :class="blockRing('bonus_stack')"
                                @click="selectBlock('bonus_stack')"
                            >
                                Bonus cards appear here once you generate bonuses in Step 5.
                            </div>

                            <article
                                v-for="b in previewBonusCards"
                                :key="b.uuid"
                                class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                                :class="blockRing('bonus_stack')"
                                @click="selectBlock('bonus_stack')"
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
                                            <span class="rounded-full bg-brand-gradient px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">{{ b.type_label }}</span>
                                            <span class="text-sm font-semibold text-blue-700">{{ b.value_label }}</span>
                                        </div>
                                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ b.description }}</p>
                                        <ul v-if="b.features.length" class="mt-3 space-y-1.5">
                                            <li v-for="(f, i) in b.features" :key="i" class="flex gap-2 text-sm text-slate-700">
                                                <span class="mt-0.5 text-blue-600">✓</span>
                                                <span>{{ f }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="flex shrink-0 flex-col gap-2 sm:w-40">
                                        <span
                                            class="inline-flex items-center justify-center rounded-full px-4 py-2 text-sm font-semibold text-white"
                                            :style="{ background: brandColor }"
                                        >{{ b.cta_label }}</span>
                                        <span class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800">View the offer ↗</span>
                                    </div>
                                </div>
                            </article>

                            <div class="mt-8 text-center">
                                <button
                                    type="button"
                                    class="inline-flex rounded-full px-8 py-3 text-base font-semibold text-white shadow-lg"
                                    :style="{ background: brandColor }"
                                    :class="blockRing('cta')"
                                    @click.stop="selectBlock('cta')"
                                >
                                    <span
                                        contenteditable
                                        suppresscontenteditablewarning
                                        class="outline-none"
                                        @blur="onInlineBlur('cta', $event)"
                                    >{{ draft.cta || 'Get Everything Now' }}</span>
                                </button>
                            </div>
                        </main>
                    </div>
                </div>
            </div>

            <!-- Properties -->
            <aside class="shrink-0 border-t border-slate-700 bg-white p-4 lg:w-80 lg:border-t-0 lg:border-l">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {{ blocks.find((b) => b.id === selectedBlock)?.label ?? 'Properties' }}
                </p>
                <div class="max-h-[50vh] space-y-4 overflow-y-auto lg:max-h-none">
                    <div v-if="selectedBlock === 'brand_color'" class="space-y-2">
                        <Label>Brand color</Label>
                        <div class="flex gap-2">
                            <input
                                type="color"
                                :value="brandColor"
                                class="h-10 w-14 cursor-pointer rounded border"
                                @input="setField('brand_color', ($event.target as HTMLInputElement).value)"
                            />
                            <Input :model-value="brandColor" @update:model-value="setField('brand_color', $event)" />
                        </div>
                    </div>

                    <template v-if="pageType === 'squeeze'">
                        <div v-if="selectedBlock === 'headline'" class="space-y-2">
                            <Label>Headline</Label>
                            <Textarea :model-value="String(draft.headline ?? '')" rows="3" @update:model-value="setField('headline', $event)" />
                        </div>
                        <div v-if="selectedBlock === 'subheadline'" class="space-y-2">
                            <Label>Subheadline</Label>
                            <Textarea :model-value="String(draft.subheadline ?? '')" rows="3" @update:model-value="setField('subheadline', $event)" />
                        </div>
                        <div v-if="selectedBlock === 'video'" class="space-y-2">
                            <Label>Video title (optional)</Label>
                            <Input :model-value="String(draft.video_title ?? '')" placeholder="Watch this first…" @update:model-value="setField('video_title', $event)" />
                            <Label>Video URL</Label>
                            <Input
                                :model-value="String(draft.video_url ?? '')"
                                placeholder="YouTube, Vimeo, or direct .mp4 link"
                                @update:model-value="setField('video_url', $event)"
                            />
                            <p class="text-xs text-muted-foreground">Paste a YouTube, Vimeo, or direct MP4 URL — shown above the opt-in form.</p>
                        </div>
                        <div v-if="selectedBlock === 'cta'" class="space-y-2">
                            <Label>Button text</Label>
                            <Input :model-value="String(draft.cta ?? '')" @update:model-value="setField('cta', $event)" />
                        </div>
                        <div v-if="selectedBlock === 'bullets'" class="space-y-2">
                            <Label>Bullet points</Label>
                            <div v-for="(b, i) in bullets" :key="i" class="flex gap-1">
                                <Input
                                    class="flex-1"
                                    :model-value="b"
                                    @update:model-value="(v) => { const next = [...bullets]; next[i] = v; setField('bullet_points', next); }"
                                />
                                <Button variant="ghost" size="icon" @click="moveBullet(i, -1)"><Icon icon="heroicons:chevron-up" class="size-4" /></Button>
                                <Button variant="ghost" size="icon" @click="moveBullet(i, 1)"><Icon icon="heroicons:chevron-down" class="size-4" /></Button>
                                <Button variant="ghost" size="icon" @click="removeBullet(i)"><Icon icon="heroicons:trash" class="size-4" /></Button>
                            </div>
                            <div class="flex gap-2">
                                <Input v-model="newBullet" placeholder="Add bullet…" @keyup.enter="addBullet" />
                                <Button variant="outline" size="sm" @click="addBullet">Add</Button>
                            </div>
                        </div>
                    </template>

                    <template v-else-if="pageType === 'thankyou'">
                        <div v-if="selectedBlock === 'headline'" class="space-y-2"><Label>Headline</Label><Textarea :model-value="String(draft.headline ?? '')" rows="2" @update:model-value="setField('headline', $event)" /></div>
                        <div v-if="selectedBlock === 'download_cta'" class="space-y-2"><Label>Download button</Label><Input :model-value="String(draft.download_cta ?? '')" @update:model-value="setField('download_cta', $event)" /></div>
                        <div v-if="selectedBlock === 'bridge_headline'" class="space-y-2"><Label>Bridge headline</Label><Input :model-value="String(draft.bridge_headline ?? '')" @update:model-value="setField('bridge_headline', $event)" /></div>
                        <div v-if="selectedBlock === 'bridge_body'" class="space-y-2"><Label>Bridge body</Label><Textarea :model-value="String(draft.bridge_body ?? '')" rows="4" @update:model-value="setField('bridge_body', $event)" /></div>
                        <div v-if="selectedBlock === 'bridge_cta'" class="space-y-2">
                        <Label>Bridge CTA text</Label>
                        <Input :model-value="String(draft.bridge_cta ?? '')" @update:model-value="setField('bridge_cta', $event)" />
                        <p class="text-xs text-muted-foreground">Link destination comes from your campaign affiliate link — updates everywhere when you change it in Import Offer.</p>
                    </div>
                    </template>

                    <template v-else-if="pageType === 'quiz'">
                        <div v-if="selectedBlock === 'title'" class="space-y-2"><Label>Title</Label><Input :model-value="String(draft.title ?? '')" @update:model-value="setField('title', $event)" /></div>
                        <div v-if="selectedBlock === 'intro'" class="space-y-2"><Label>Intro</Label><Textarea :model-value="String(draft.intro ?? '')" rows="3" @update:model-value="setField('intro', $event)" /></div>
                        <div v-if="selectedBlock === 'questions'" class="space-y-2">
                            <QuizQuestionsEditor v-model="quizQuestions" />
                        </div>
                        <div v-if="selectedBlock === 'result_headline'" class="space-y-2"><Label>Result headline</Label><Input :model-value="String(draft.result_headline ?? '')" @update:model-value="setField('result_headline', $event)" /></div>
                    </template>

                    <template v-else>
                        <div v-if="selectedBlock === 'hero_label'" class="space-y-2">
                            <Label>Hero label</Label>
                            <Input :model-value="String(draft.hero_label ?? 'YOUR BONUSES')" @update:model-value="setField('hero_label', $event)" />
                            <p class="text-xs text-muted-foreground">Small uppercase label above the headline (e.g. YOUR BONUSES).</p>
                        </div>
                        <div v-if="selectedBlock === 'headline'" class="space-y-2">
                            <Label>Headline</Label>
                            <Input :model-value="String(draft.headline ?? '')" @update:model-value="setField('headline', $event)" />
                        </div>
                        <div v-if="selectedBlock === 'intro'" class="space-y-2">
                            <Label>Intro</Label>
                            <Textarea :model-value="String(draft.intro ?? '')" rows="4" @update:model-value="setField('intro', $event)" />
                        </div>
                        <div v-if="selectedBlock === 'cta'" class="space-y-2">
                            <Label>Footer button text</Label>
                            <Input :model-value="String(draft.cta ?? 'Get Everything Now')" @update:model-value="setField('cta', $event)" />
                            <p class="text-xs text-muted-foreground">Links to your cloaked affiliate URL on the live page.</p>
                        </div>
                        <div v-if="selectedBlock === 'bonus_stack'" class="space-y-2">
                            <Label>Bonus cards</Label>
                            <p class="text-sm text-muted-foreground">
                                Cards are pulled from your generated bonuses. To change which appear here, go to
                                <strong>Step 5 — Bonuses</strong> and use the checkboxes, then click <strong>Save bonus page</strong>.
                            </p>
                            <p v-if="previewBonusCards.length" class="text-xs text-blue-700">
                                Showing {{ previewBonusCards.length }} bonus{{ previewBonusCards.length === 1 ? '' : 'es' }} in preview.
                            </p>
                        </div>
                    </template>
                </div>
            </aside>
        </div>
    </div>
</template>
