<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

export interface TutorialSection {
    title: string;
    body?: string | null;
    video_url?: string | null;
}

const props = defineProps<{
    intro: string;
    sections: TutorialSection[];
}>();

const hasSections = computed(() => props.sections.length > 0);

const statCards = computed(() => [
    { label: 'Sections', value: props.sections.length, sub: 'guides', icon: 'heroicons:book-open' },
    { label: 'Videos', value: props.sections.filter((s) => s.video_url).length, sub: 'embedded', icon: 'heroicons:play-circle' },
]);

function embedVideoUrl(url: string | null | undefined): string | null {
    if (!url?.trim()) {
        return null;
    }

    const trimmed = url.trim();

    try {
        const parsed = new URL(trimmed);
        const host = parsed.hostname.replace(/^www\./, '');

        if (host === 'youtube.com' || host === 'm.youtube.com') {
            const id = parsed.searchParams.get('v');
            if (id) {
                return `https://www.youtube.com/embed/${id}`;
            }
        }

        if (host === 'youtu.be') {
            const id = parsed.pathname.replace(/^\//, '');
            if (id) {
                return `https://www.youtube.com/embed/${id}`;
            }
        }

        if (host === 'vimeo.com') {
            const id = parsed.pathname.replace(/^\//, '');
            if (id) {
                return `https://player.vimeo.com/video/${id}`;
            }
        }
    } catch {
        return null;
    }

    return null;
}
</script>

<template>
    <Head title="Tutorial" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Tutorial</h1>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">{{ intro }}</p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10">
                <Icon icon="heroicons:academic-cap" class="size-5 text-teal-600" />
            </div>
        </div>

        <!-- Stats -->
        <div v-if="hasSections" class="grid grid-cols-2 gap-2 md:gap-3">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="flex items-center gap-3 rounded-xl border border-border/60 bg-white px-3 py-2.5 shadow-sm"
            >
                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-500/10">
                    <Icon :icon="stat.icon" class="size-4 text-teal-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">{{ stat.label }}</p>
                    <div class="flex items-baseline gap-1.5">
                        <span class="text-xl font-bold leading-none">{{ stat.value }}</span>
                        <span class="text-[0.65rem] text-muted-foreground">{{ stat.sub }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sections -->
        <template v-if="hasSections">
            <article
                v-for="(section, index) in sections"
                :key="`${section.title}-${index}`"
                class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm"
            >
                <div class="border-b border-border/60 bg-teal-50/20 px-4 py-3 md:px-5">
                    <div class="flex items-center gap-2">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-teal-600 text-[0.65rem] font-bold text-white">
                            {{ index + 1 }}
                        </span>
                        <h2 class="text-base font-semibold text-foreground">{{ section.title }}</h2>
                    </div>
                </div>
                <div class="space-y-4 p-4 md:p-5">
                    <p
                        v-if="section.body"
                        class="whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ section.body }}
                    </p>
                    <div
                        v-if="embedVideoUrl(section.video_url)"
                        class="aspect-video overflow-hidden rounded-xl border border-border/60 bg-muted/20"
                    >
                        <iframe
                            :src="embedVideoUrl(section.video_url)!"
                            class="size-full"
                            :title="section.title"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                    <Button
                        v-else-if="section.video_url"
                        as-child
                        variant="brand-outline"
                        size="sm"
                    >
                        <a :href="section.video_url" target="_blank" rel="noopener noreferrer">
                            <Icon icon="heroicons:play-circle" class="size-3.5" />
                            Watch video
                        </a>
                    </Button>
                </div>
            </article>
        </template>

        <!-- Empty -->
        <div
            v-else
            class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-teal-200/60 bg-white px-6 py-14 text-center shadow-sm"
        >
            <div class="flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                <Icon icon="heroicons:academic-cap" class="size-7 text-teal-600/60" />
            </div>
            <div>
                <p class="font-semibold text-foreground">Content coming soon</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    Tutorial steps will be added here. Your admin can paste guides into
                    <code class="rounded bg-muted px-1 py-0.5 text-xs">config/tutorial.php</code>
                    on the server.
                </p>
            </div>
            <ul class="mt-2 space-y-2 text-left text-sm text-muted-foreground">
                <li class="flex items-start gap-2">
                    <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-teal-600" />
                    Create and publish webinar funnels
                </li>
                <li class="flex items-start gap-2">
                    <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-teal-600" />
                    Connect social accounts for traffic auto-reply
                </li>
                <li class="flex items-start gap-2">
                    <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-teal-600" />
                    Track keywords and mentions
                </li>
            </ul>
        </div>
    </div>
</template>
