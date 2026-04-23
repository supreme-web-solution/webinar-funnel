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

const categoryGradients = [
    'from-teal-400 to-cyan-500',
    'from-violet-400 to-purple-500',
    'from-amber-400 to-orange-500',
    'from-rose-400 to-pink-500',
    'from-sky-400 to-blue-500',
    'from-emerald-400 to-green-500',
];

function cardGradient(idx: number): string {
    return categoryGradients[idx % categoryGradients.length];
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
                <!-- Thumbnail / placeholder -->
                <div class="relative h-44 overflow-hidden bg-muted">
                    <img
                        v-if="template.thumbnail_url"
                        :src="template.thumbnail_url"
                        :alt="template.name"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                    />
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center bg-gradient-to-br"
                        :class="cardGradient(idx)"
                    >
                        <Icon icon="heroicons:video-camera" class="size-12 text-white/70" />
                    </div>

                    <!-- Hover overlay -->
                    <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                        <Link
                            :href="`/funnels/create?template_id=${template.id}`"
                            class="flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-md hover:bg-primary hover:text-primary-foreground transition-colors"
                        >
                            <Icon icon="heroicons:arrow-right-circle" class="size-4" />
                            Use Template
                        </Link>
                    </div>

                    <!-- Template # badge -->
                    <div class="absolute top-2 left-2 rounded-md bg-black/50 px-1.5 py-0.5 text-[0.6rem] font-medium text-white backdrop-blur-sm">
                        #{{ template.id }}
                    </div>
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
