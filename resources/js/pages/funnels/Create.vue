<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface TemplateItem {
    id: number;
    name: string;
    category: string;
    conversion_style: string | null;
    thumbnail_url?: string | null;
}

const props = defineProps<{
    templates: TemplateItem[];
}>();

const templateIdFromUrl = Number(new URLSearchParams(window.location.search).get('template_id') ?? 0);
const scratchModeFromUrl = new URLSearchParams(window.location.search).get('scratch') === '1';
const firstTemplateId = props.templates[0]?.id || 0;

const form = useForm({
    template_id: scratchModeFromUrl ? firstTemplateId : (templateIdFromUrl || firstTemplateId),
    name: '',
    slug: '',
    is_scratch: scratchModeFromUrl,
});

const selectedTemplate = computed(() => props.templates.find((t) => t.id === form.template_id) ?? props.templates[0]);

const showPicker = ref(false);
const pickerSearch = ref('');

const filteredTemplates = computed(() => {
    if (!pickerSearch.value.trim()) {
        return props.templates;
    }

    const q = pickerSearch.value.toLowerCase();

    return props.templates.filter(
        (t) => t.name.toLowerCase().includes(q) || t.category.toLowerCase().includes(q),
    );
});

function selectTemplate(id: number): void {
    if (form.is_scratch) return;
    form.template_id = id;
    showPicker.value = false;
    pickerSearch.value = '';
}

/* Auto-generate slug from funnel name */
watch(
    () => form.name,
    (val) => {
        form.slug = val
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .trim()
            .replace(/\s+/g, '-');
    },
);

const submit = (): void => {
    form.post('/funnels');
};

const categoryPalette: Record<string, [string, string, string]> = {
    consulting:    ['#0a0f1e', '#0e1830', '#40E0D0'],
    marketing:     ['#0f0a1e', '#180e30', '#a78bfa'],
    business:      ['#0a1218', '#0c1a24', '#38bdf8'],
    education:     ['#0f1a0a', '#122010', '#4ade80'],
    ecommerce:     ['#1a100a', '#241508', '#fb923c'],
    finance:       ['#0a0f1e', '#0d1530', '#facc15'],
    health:        ['#0a1812', '#0d2018', '#34d399'],
    'real-estate': ['#1a0a10', '#200d14', '#f472b6'],
    crypto:        ['#0a0f1a', '#0d1422', '#f59e0b'],
    coaching:      ['#10080f', '#180d1c', '#e879f9'],
};

const fallbackCoverPalette: [string, string, string] = ['#0a0f1e', '#0d1530', '#40E0D0'];

function coverPalette(cat: string): [string, string, string] {
    return categoryPalette[cat] ?? fallbackCoverPalette;
}

const categoryIcon: Record<string, string> = {
    consulting:    'heroicons:briefcase',
    marketing:     'heroicons:megaphone',
    business:      'heroicons:building-office',
    education:     'heroicons:academic-cap',
    ecommerce:     'heroicons:shopping-bag',
    finance:       'heroicons:chart-bar',
    health:        'heroicons:heart',
    'real-estate': 'heroicons:home',
    crypto:        'heroicons:currency-dollar',
    coaching:      'heroicons:user-group',
};

function coverIcon(cat: string): string {
    return categoryIcon[cat] ?? 'heroicons:rectangle-stack';
}
</script>

<template>
    <Head title="Create Funnel" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 md:p-6">

        <!-- ── Page header ── -->
        <div class="flex items-center gap-3">
            <Button as-child variant="ghost" size="sm" class="text-muted-foreground h-8 px-2 -ml-1">
                <Link href="/templates">
                    <Icon icon="heroicons:arrow-left" class="size-4 mr-1" />
                    Templates
                </Link>
            </Button>
            <div class="h-4 w-px bg-border/60" />
            <div>
                <h1 class="text-xl font-bold tracking-tight text-foreground leading-tight">Create New Funnel</h1>
                <p class="text-xs text-muted-foreground">Configure your funnel details below</p>
            </div>
        </div>

        <!-- ── Two-panel layout ── -->
        <div class="grid gap-6 lg:grid-cols-5">

            <!-- LEFT: Template preview + picker (2/5) -->
            <div class="lg:col-span-2 flex flex-col gap-4">

                <!-- Selected template card -->
                <Card class="border shadow-sm overflow-hidden">
                    <div
                        class="relative h-48 overflow-hidden select-none"
                        :style="selectedTemplate ? `background:linear-gradient(145deg,${coverPalette(selectedTemplate.category)[0]} 0%,${coverPalette(selectedTemplate.category)[1]} 100%)` : 'background:#0a0f1e'"
                    >
                        <!-- dot grid -->
                        <svg class="absolute inset-0 h-full w-full opacity-[0.07]" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <pattern id="sdots" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                                    <circle cx="1.5" cy="1.5" r="1.5" fill="white" />
                                </pattern>
                            </defs>
                            <rect width="100%" height="100%" fill="url(#sdots)" />
                        </svg>
                        <!-- glow blobs -->
                        <div
                            class="pointer-events-none absolute -top-10 -right-10 h-36 w-36 rounded-full blur-2xl"
                            :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}28` : ''"
                        />
                        <!-- top bar -->
                        <div class="absolute inset-x-0 top-0 flex items-center justify-between px-3 pt-3">
                            <span class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/8 px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-widest text-white/60">
                                <span class="size-1.5 rounded-full animate-pulse" :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}` : 'background:#40E0D0'" />
                                Live Webinar
                            </span>
                            <span
                                v-if="selectedTemplate"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] font-semibold capitalize"
                                :style="`background:${coverPalette(selectedTemplate.category)[2]}22; color:${coverPalette(selectedTemplate.category)[2]};`"
                            >
                                <Icon :icon="coverIcon(selectedTemplate.category)" class="size-2.5" />
                                {{ selectedTemplate.category }}
                            </span>
                        </div>
                        <!-- center -->
                        <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 px-4">
                            <div class="flex items-center gap-1.5 mb-1">
                                <div class="h-5 w-8 rounded-sm opacity-60" :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}22;border:1px solid ${coverPalette(selectedTemplate.category)[2]}44` : ''" />
                                <Icon icon="heroicons:arrow-right" class="size-2.5 text-white/40" />
                                <div class="h-5 w-8 rounded-sm opacity-60" :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}22;border:1px solid ${coverPalette(selectedTemplate.category)[2]}44` : ''" />
                                <Icon icon="heroicons:arrow-right" class="size-2.5 text-white/40" />
                                <div class="flex h-5 w-8 items-center justify-center rounded-sm" :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}33;border:1px solid ${coverPalette(selectedTemplate.category)[2]}88` : ''">
                                    <Icon icon="heroicons:currency-dollar" class="size-2.5" :style="selectedTemplate ? `color:${coverPalette(selectedTemplate.category)[2]}` : 'color:#40E0D0'" />
                                </div>
                            </div>
                            <p class="text-center text-sm font-bold text-white leading-snug" style="text-shadow:0 1px 8px rgba(0,0,0,0.7);max-width:220px">
                                {{ selectedTemplate?.name?.replace(/ Offer$/i, '') ?? 'Select a template' }}
                            </p>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[0.6rem] font-semibold"
                                :style="selectedTemplate ? `background:${coverPalette(selectedTemplate.category)[2]}22; color:${coverPalette(selectedTemplate.category)[2]}; border:1px solid ${coverPalette(selectedTemplate.category)[2]}44` : 'background:#40E0D025;color:#40E0D0;border:1px solid #40E0D044'"
                            >
                                Webinar Funnel
                            </span>
                        </div>
                        <!-- bottom bar -->
                        <div class="absolute inset-x-0 bottom-0 flex items-center gap-3 px-3 pb-2 pt-1.5 border-t" :style="selectedTemplate ? `border-color:${coverPalette(selectedTemplate.category)[2]}22` : 'border-color:#40E0D022'">
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/45">
                                <Icon icon="heroicons:document-text" class="size-3" />
                                Opt-in page
                            </span>
                            <span class="text-white/20 text-[0.55rem]">+</span>
                            <span class="flex items-center gap-1 text-[0.55rem] text-white/45">
                                <Icon icon="heroicons:video-camera" class="size-3" />
                                Webinar room
                            </span>
                        </div>
                    </div>
                    <CardContent class="p-3.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Icon icon="heroicons:check-circle" class="size-4 text-primary" />
                                Template selected
                            </div>
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 px-3 text-xs"
                                :disabled="form.is_scratch"
                                @click="showPicker = !showPicker"
                            >
                                <Icon icon="heroicons:arrows-right-left" class="size-3.5 mr-1" />
                                {{ form.is_scratch ? 'Fixed template' : 'Change' }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- What you get info -->
                <Card class="border shadow-sm">
                    <CardHeader class="pb-2">
                        <CardTitle class="text-sm font-semibold">What's included</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-2.5">
                        <div class="flex items-start gap-2.5 text-sm">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 mt-0.5">
                                <Icon icon="heroicons:cursor-arrow-ripple" class="size-3.5 text-primary" />
                            </div>
                            <div>
                                <p class="font-medium text-foreground text-xs">Opt-in Page</p>
                                <p class="text-xs text-muted-foreground">Hero section + email capture form, fully editable with GrapesJS</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 text-sm">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 mt-0.5">
                                <Icon icon="heroicons:video-camera" class="size-3.5 text-primary" />
                            </div>
                            <div>
                                <p class="font-medium text-foreground text-xs">Webinar Room</p>
                                <p class="text-xs text-muted-foreground">Video embed + live chat sidebar for attendees</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 text-sm">
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg" style="background: rgba(255,173,0,0.1)" mt-0.5>
                                <Icon icon="heroicons:puzzle-piece" class="size-3.5" style="color:#FFAD00" />
                            </div>
                            <div>
                                <p class="font-medium text-foreground text-xs">ESP Integration</p>
                                <p class="text-xs text-muted-foreground">Auto-subscribe leads to Mailchimp, GetResponse &amp; 8 more</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- RIGHT: Funnel form (3/5) -->
            <div class="lg:col-span-3 flex flex-col gap-4">

                <!-- Template picker panel (toggled) -->
                <Card v-if="showPicker && !form.is_scratch" class="border shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle class="text-sm font-semibold">Switch Template</CardTitle>
                            <button
                                class="text-muted-foreground hover:text-foreground transition-colors"
                                @click="showPicker = false"
                            >
                                <Icon icon="heroicons:x-mark" class="size-4" />
                            </button>
                        </div>
                        <div class="relative mt-2">
                            <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                            <Input v-model="pickerSearch" placeholder="Search templates…" class="pl-8 h-8 text-xs" />
                        </div>
                    </CardHeader>
                    <CardContent class="p-0 pb-2">
                        <div class="max-h-72 overflow-y-auto divide-y divide-border/60">
                            <button
                                v-for="(t, idx) in filteredTemplates"
                                :key="t.id"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-muted/50 transition-colors"
                                :class="form.template_id === t.id ? 'bg-primary/5' : ''"
                                @click="selectTemplate(t.id)"
                            >
                                <!-- mini thumb — designed cover -->
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg overflow-hidden relative"
                                    :style="`background:linear-gradient(135deg,${coverPalette(t.category)[0]} 0%,${coverPalette(t.category)[1]} 100%)`"
                                >
                                    <Icon :icon="coverIcon(t.category)" class="size-4" :style="`color:${coverPalette(t.category)[2]}; opacity:0.85`" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-foreground truncate">{{ t.name }}</p>
                                    <p class="text-[0.65rem] text-muted-foreground capitalize">{{ t.category }}</p>
                                </div>
                                <Icon
                                    v-if="form.template_id === t.id"
                                    icon="heroicons:check-circle"
                                    class="size-4 shrink-0 text-primary"
                                />
                            </button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Main funnel details form -->
                <Card class="border shadow-sm">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-base font-semibold">Funnel Details</CardTitle>
                        <CardDescription class="text-xs">
                            Give your funnel a name and a unique URL slug. You can edit content after creation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form class="space-y-5" @submit.prevent="submit">

                            <!-- Funnel name -->
                            <div class="space-y-1.5">
                                <Label for="name" class="text-xs font-semibold text-foreground">
                                    Funnel Name <span class="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g. My Webinar — Grow Your Business in 90 Days"
                                    class="h-10 text-sm"
                                    :class="form.errors.name ? 'border-destructive focus-visible:ring-destructive' : ''"
                                    autofocus
                                />
                                <p v-if="form.errors.name" class="flex items-center gap-1 text-xs text-destructive">
                                    <Icon icon="heroicons:exclamation-circle" class="size-3.5" />
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <!-- Slug -->
                            <div class="space-y-1.5">
                                <Label for="slug" class="text-xs font-semibold text-foreground">
                                    URL Slug <span class="text-destructive">*</span>
                                </Label>
                                <div class="flex rounded-md border overflow-hidden focus-within:ring-2 focus-within:ring-ring/50" :class="form.errors.slug ? 'border-destructive' : ''">
                                    <span class="flex items-center bg-muted px-3 text-xs text-muted-foreground border-r select-none whitespace-nowrap">
                                        /funnel/you/
                                    </span>
                                    <input
                                        id="slug"
                                        v-model="form.slug"
                                        type="text"
                                        placeholder="my-webinar-funnel"
                                        class="flex-1 bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground"
                                    />
                                </div>
                                <p v-if="form.errors.slug" class="flex items-center gap-1 text-xs text-destructive">
                                    <Icon icon="heroicons:exclamation-circle" class="size-3.5" />
                                    {{ form.errors.slug }}
                                </p>
                                <p v-else class="text-[0.65rem] text-muted-foreground">
                                    Auto-generated from name. Alphanumeric and hyphens only.
                                </p>
                            </div>

                            <!-- Selected template preview in form (hidden input) -->
                            <input type="hidden" :value="form.template_id" />
                            <input type="hidden" :value="form.is_scratch ? 1 : 0" />

                            <!-- Template confirmation row -->
                            <div class="flex items-center justify-between rounded-lg border bg-muted/30 px-3.5 py-2.5">
                                <div class="flex items-center gap-2 text-xs">
                                    <Icon icon="heroicons:rectangle-stack" class="size-4 text-primary" />
                                    <span class="text-muted-foreground">Using template:</span>
                                    <span class="font-medium text-foreground">{{ selectedTemplate?.name }}</span>
                                </div>
                                <Badge class="text-[0.6rem] bg-primary/10 text-primary border-primary/20 capitalize">
                                    {{ selectedTemplate?.category }}
                                </Badge>
                            </div>

                            <div v-if="form.is_scratch" class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3.5 py-2.5 text-xs text-emerald-800">
                                Scratch mode: this uses the first base template structure, but starts with empty page content and webinar settings.
                            </div>

                            <!-- Submit -->
                            <div class="flex items-center gap-3 pt-1">
                                <Button
                                    type="submit"
                                    class="flex-1 h-10 bg-primary text-primary-foreground hover:opacity-90 shadow-sm font-semibold"
                                    :disabled="form.processing || !form.name || !form.slug"
                                >
                                    <Icon
                                        v-if="form.processing"
                                        icon="heroicons:arrow-path"
                                        class="size-4 mr-2 animate-spin"
                                    />
                                    <Icon v-else icon="heroicons:rocket-launch" class="size-4 mr-2" />
                                    {{ form.processing ? 'Creating funnel…' : 'Create Funnel' }}
                                </Button>
                                <Button as-child variant="outline" class="h-10">
                                    <Link href="/templates">Cancel</Link>
                                </Button>
                            </div>

                        </form>
                    </CardContent>
                </Card>

                <!-- Steps hint -->
                <div class="flex items-start gap-4 rounded-xl border border-dashed border-primary/30 bg-primary/5 p-4">
                    <Icon icon="heroicons:information-circle" class="size-5 shrink-0 text-primary mt-0.5" />
                    <div class="text-xs text-muted-foreground space-y-1">
                        <p class="font-semibold text-foreground">What happens next?</p>
                        <p>After creating your funnel you'll be taken to the editor where you can:</p>
                        <ul class="space-y-0.5 pl-3 list-disc marker:text-primary/60">
                            <li>Customise the opt-in page layout, colours, and copy with the visual editor</li>
                            <li>Set your webinar video URL and room settings</li>
                            <li>Connect an ESP to auto-subscribe new leads</li>
                            <li>Publish and share your unique funnel link</li>
                        </ul>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
