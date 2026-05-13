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
                    <div class="relative h-48 bg-muted">
                        <img
                            v-if="selectedTemplate?.thumbnail_url"
                            :src="selectedTemplate.thumbnail_url"
                            :alt="selectedTemplate?.name"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center bg-linear-to-br"
                            :class="cardGradient(selectedTemplate?.id ?? 0)"
                        >
                            <Icon icon="heroicons:video-camera" class="size-14 text-white/70" />
                        </div>
                        <div class="absolute inset-0 bg-linear-to-t from-black/60 to-transparent" />
                        <div class="absolute bottom-3 left-3 right-3">
                            <p class="text-sm font-semibold text-white leading-tight line-clamp-1">
                                {{ selectedTemplate?.name }}
                            </p>
                            <div class="mt-1 flex items-center gap-1.5">
                                <span class="rounded-full bg-white/20 px-2 py-0.5 text-[0.6rem] font-medium text-white capitalize backdrop-blur-sm">
                                    {{ selectedTemplate?.category }}
                                </span>
                                <span
                                    v-if="selectedTemplate?.conversion_style"
                                    class="rounded-full bg-white/20 px-2 py-0.5 text-[0.6rem] font-medium text-white capitalize backdrop-blur-sm"
                                >
                                    {{ selectedTemplate?.conversion_style?.replace('_', ' ') }}
                                </span>
                            </div>
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
                                <!-- mini thumb -->
                                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg overflow-hidden bg-muted">
                                    <img
                                        v-if="t.thumbnail_url"
                                        :src="t.thumbnail_url"
                                        :alt="t.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-full w-full items-center justify-center bg-linear-to-br"
                                        :class="cardGradient(idx)"
                                    >
                                        <Icon icon="heroicons:video-camera" class="size-4 text-white/80" />
                                    </div>
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
