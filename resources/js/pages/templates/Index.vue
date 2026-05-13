<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface TemplateItem {
    id: number;
    name: string;
    slug: string;
    category: string;
    conversion_style: string | null;
    thumbnail_url: string | null;
}

const props = defineProps<{
    templates: {
        data: TemplateItem[];
        current_page?: number;
        last_page?: number;
        total?: number;
    };
}>();

const search = ref('');
const activeCategory = ref('all');

const categories = computed(() => {
    const cats = new Set(props.templates.data.map((t) => t.category));
    return ['all', ...Array.from(cats).sort()];
});

const filtered = computed(() => {
    let list = props.templates.data;
    if (activeCategory.value !== 'all') {
        list = list.filter((t) => t.category === activeCategory.value);
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase();
        list = list.filter(
            (t) => t.name.toLowerCase().includes(q) || t.category.toLowerCase().includes(q) || (t.conversion_style ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

const styleColor: Record<string, string> = {
    urgency: 'bg-rose-50 text-rose-600 border-rose-200',
    scarcity: 'bg-orange-50 text-orange-600 border-orange-200',
    social_proof: 'bg-sky-50 text-sky-600 border-sky-200',
    authority: 'bg-violet-50 text-violet-600 border-violet-200',
    curiosity: 'bg-amber-50 text-amber-600 border-amber-200',
    general: 'bg-slate-50 text-slate-500 border-slate-200',
};

function styleClass(s: string | null): string {
    return styleColor[s ?? 'general'] ?? styleColor['general'];
}

// Per-category accent palettes: [bg-from, bg-to, accentHex, glowHex]
const categoryPalette: Record<string, [string, string, string, string]> = {
    consulting:  ['#0a0f1e', '#0e1830', '#40E0D0', 'rgba(64,224,208,0.18)'],
    marketing:   ['#0f0a1e', '#180e30', '#a78bfa', 'rgba(167,139,250,0.18)'],
    business:    ['#0a1218', '#0c1a24', '#38bdf8', 'rgba(56,189,248,0.18)'],
    education:   ['#0f1a0a', '#122010', '#4ade80', 'rgba(74,222,128,0.18)'],
    ecommerce:   ['#1a100a', '#241508', '#fb923c', 'rgba(251,146,60,0.18)'],
    finance:     ['#0a0f1e', '#0d1530', '#facc15', 'rgba(250,204,21,0.18)'],
    health:      ['#0a1812', '#0d2018', '#34d399', 'rgba(52,211,153,0.18)'],
    'real-estate':['#1a0a10', '#200d14', '#f472b6', 'rgba(244,114,182,0.18)'],
    crypto:      ['#0a0f1a', '#0d1422', '#f59e0b', 'rgba(245,158,11,0.18)'],
    coaching:    ['#10080f', '#180d1c', '#e879f9', 'rgba(232,121,249,0.18)'],
};

const fallbackPalette: [string, string, string, string] = ['#0a0f1e', '#0d1530', '#40E0D0', 'rgba(64,224,208,0.18)'];

function coverPalette(cat: string): [string, string, string, string] {
    return categoryPalette[cat] ?? fallbackPalette;
}

const categoryIcon: Record<string, string> = {
    consulting:   'heroicons:briefcase',
    marketing:    'heroicons:megaphone',
    business:     'heroicons:building-office',
    education:    'heroicons:academic-cap',
    ecommerce:    'heroicons:shopping-bag',
    finance:      'heroicons:chart-bar',
    health:       'heroicons:heart',
    'real-estate':'heroicons:home',
    crypto:       'heroicons:currency-dollar',
    coaching:     'heroicons:user-group',
};

function coverIcon(cat: string): string {
    return categoryIcon[cat] ?? 'heroicons:rectangle-stack';
}
</script>

<template>
    <Head title="Template Library" />

    <div class="flex flex-col gap-6 p-4 md:p-6 w-full max-w-screen-xl mx-auto">

        <!-- ── Page header ── -->
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground">Template Library</h1>
                <p class="text-sm text-muted-foreground mt-0.5">
                    Choose from {{ templates.data.length }} professionally designed funnel templates
                </p>
            </div>
            <p class="text-xs text-muted-foreground self-end">
                {{ filtered.length }} result{{ filtered.length !== 1 ? 's' : '' }}
            </p>
        </div>

        <!-- ── Search + category filters ── -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Search -->
            <div class="relative flex-1 max-w-xs">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground pointer-events-none" />
                <Input
                    v-model="search"
                    placeholder="Search templates…"
                    class="pl-9 h-9 text-sm bg-background"
                />
            </div>

            <!-- Category chips -->
            <div class="flex gap-1.5 flex-wrap">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    class="rounded-full border px-3 py-1 text-xs font-medium capitalize transition-colors"
                    :class="activeCategory === cat
                        ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                        : 'border-border text-muted-foreground hover:border-primary/40 hover:text-foreground'"
                    @click="activeCategory = cat"
                >
                    {{ cat === 'all' ? 'All Templates' : cat }}
                </button>
            </div>
        </div>

        <!-- ── Template grid ── -->
        <div v-if="filtered.length > 0" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <div
                v-for="(template, idx) in filtered"
                :key="template.id"
                class="group relative flex flex-col rounded-xl border bg-card shadow-sm overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
            >
                <!-- Designed cover -->
                <div
                    class="relative h-44 overflow-hidden select-none"
                    :style="`background: linear-gradient(145deg, ${coverPalette(template.category)[0]} 0%, ${coverPalette(template.category)[1]} 100%);`"
                >
                    <!-- Dot-grid background pattern (SVG) -->
                    <svg class="absolute inset-0 h-full w-full opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="dots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                                <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#dots)" />
                    </svg>

                    <!-- Top-right accent glow blob -->
                    <div
                        class="pointer-events-none absolute -top-8 -right-8 h-32 w-32 rounded-full blur-2xl"
                        :style="`background: ${coverPalette(template.category)[3]};`"
                    />
                    <!-- Bottom-left accent glow blob -->
                    <div
                        class="pointer-events-none absolute -bottom-6 -left-6 h-24 w-24 rounded-full blur-xl"
                        :style="`background: ${coverPalette(template.category)[3]};`"
                    />

                    <!-- Top bar -->
                    <div class="absolute inset-x-0 top-0 flex items-center justify-between px-3 pt-2.5">
                        <!-- LIVE badge -->
                        <span class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/8 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-widest text-white/70 backdrop-blur-sm">
                            <span class="size-1.5 rounded-full animate-pulse" :style="`background:${coverPalette(template.category)[2]}`" />
                            Live Webinar
                        </span>
                        <!-- Category icon chip -->
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide"
                            :style="`background:${coverPalette(template.category)[3]}; color:${coverPalette(template.category)[2]};`"
                        >
                            <Icon :icon="coverIcon(template.category)" class="size-2.5" />
                            {{ template.category }}
                        </span>
                    </div>

                    <!-- Center content -->
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4">
                        <!-- Big category icon background -->
                        <div
                            class="pointer-events-none absolute opacity-[0.06]"
                            style="bottom:-8px; right:8px;"
                        >
                            <Icon :icon="coverIcon(template.category)" style="width:80px;height:80px;color:white" />
                        </div>

                        <!-- Funnel flow graphic -->
                        <div class="flex items-center gap-1.5 mb-1">
                            <div class="h-5 w-8 rounded-sm opacity-70" :style="`background:${coverPalette(template.category)[2]}22;border:1px solid ${coverPalette(template.category)[2]}55`" />
                            <Icon icon="heroicons:arrow-right" class="size-2.5 opacity-50" style="color:white" />
                            <div class="h-5 w-8 rounded-sm opacity-70" :style="`background:${coverPalette(template.category)[2]}22;border:1px solid ${coverPalette(template.category)[2]}55`" />
                            <Icon icon="heroicons:arrow-right" class="size-2.5 opacity-50" style="color:white" />
                            <div class="flex h-5 w-8 items-center justify-center rounded-sm" :style="`background:${coverPalette(template.category)[2]}33;border:1px solid ${coverPalette(template.category)[2]}88`">
                                <Icon icon="heroicons:currency-dollar" class="size-2.5" :style="`color:${coverPalette(template.category)[2]}`" />
                            </div>
                        </div>

                        <!-- Template name -->
                        <h3
                            class="text-center text-sm font-bold leading-snug text-white drop-shadow"
                            style="text-shadow: 0 1px 8px rgba(0,0,0,0.7); max-width: 200px;"
                        >
                            {{ template.name.replace(/ Offer$/i, '') }}
                        </h3>

                        <!-- Funnel label -->
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[0.6rem] font-semibold tracking-wide"
                            :style="`background:${coverPalette(template.category)[2]}22; color:${coverPalette(template.category)[2]}; border:1px solid ${coverPalette(template.category)[2]}44`"
                        >
                            Webinar Funnel
                        </span>
                    </div>

                    <!-- Bottom bar -->
                    <div class="absolute inset-x-0 bottom-0 flex items-center justify-between border-t px-3 pb-2 pt-1.5" :style="`border-color:${coverPalette(template.category)[2]}22`">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/50">
                                <Icon icon="heroicons:document-text" class="size-3" />
                                Opt-in page
                            </span>
                            <span class="text-white/25 text-[0.55rem]">+</span>
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/50">
                                <Icon icon="heroicons:video-camera" class="size-3" />
                                Webinar room
                            </span>
                        </div>
                        <span class="text-[0.55rem] font-bold" :style="`color:${coverPalette(template.category)[2]}99`">
                            DFY
                        </span>
                    </div>

                    <!-- Hover overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 backdrop-blur-[1px]">
                        <Link
                            :href="`/funnels/create?template_id=${template.id}`"
                            class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold shadow-xl transition-colors"
                            :style="`background:${coverPalette(template.category)[2]}; color:#0a0f1e;`"
                        >
                            <Icon icon="heroicons:arrow-right-circle" class="size-4" />
                            Use Template
                        </Link>
                    </div>

                    <!-- Template # badge -->
                    <div class="absolute top-2 left-2" style="display:none"><!-- id moved to top bar --></div>
                </div>

                <!-- Card body -->
                <div class="flex flex-col gap-2 p-3.5">
                    <h3 class="font-semibold text-sm text-foreground leading-tight line-clamp-1">
                        {{ template.name }}
                    </h3>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        <Badge class="capitalize text-[0.65rem] px-2 py-0.5 rounded-full bg-primary/10 text-primary border-primary/20">
                            {{ template.category }}
                        </Badge>
                        <span
                            v-if="template.conversion_style"
                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[0.65rem] font-medium capitalize"
                            :class="styleClass(template.conversion_style)"
                        >
                            {{ template.conversion_style.replace('_', ' ') }}
                        </span>
                    </div>

                    <!-- Footer CTA -->
                    <div class="mt-1 flex items-center justify-between">
                        <div class="flex items-center gap-1 text-[0.65rem] text-muted-foreground">
                            <Icon icon="heroicons:rectangle-stack" class="size-3" />
                            2 pages
                        </div>
                        <Button as-child size="sm" class="h-7 px-3 text-xs bg-primary text-primary-foreground hover:opacity-90 shadow-sm">
                            <Link :href="`/funnels/create?template_id=${template.id}`">
                                Use
                                <Icon icon="heroicons:arrow-right" class="ml-1 size-3" />
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center rounded-xl border border-dashed bg-muted/30 py-16 gap-3 text-muted-foreground">
            <Icon icon="heroicons:rectangle-stack" class="size-12 opacity-30" />
            <p class="text-sm font-medium">No templates match your search</p>
            <Button variant="ghost" size="sm" class="text-xs" @click="search = ''; activeCategory = 'all'">
                Clear filters
            </Button>
        </div>

    </div>
</template>
