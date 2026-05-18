<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div
            class="relative overflow-hidden rounded-2xl border border-emerald-200/60 bg-linear-to-r from-emerald-50 via-cyan-50 to-sky-50 p-5 shadow-sm"
        >
            <div class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full bg-emerald-200/40 blur-2xl" />
            <div class="relative flex items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-500 to-cyan-500 text-white shadow-sm"
                >
                    <Icon icon="heroicons:academic-cap" class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">Tutorial</h1>
                    <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        {{ intro }}
                    </p>
                </div>
            </div>
        </div>

        <template v-if="hasSections">
            <Card
                v-for="(section, index) in sections"
                :key="`${section.title}-${index}`"
                class="border-border/80 shadow-sm"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-base">{{ section.title }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 pt-0">
                    <p
                        v-if="section.body"
                        class="whitespace-pre-wrap text-sm text-muted-foreground leading-relaxed"
                    >
                        {{ section.body }}
                    </p>
                    <div
                        v-if="embedVideoUrl(section.video_url)"
                        class="aspect-video overflow-hidden rounded-lg border border-border/80 bg-muted/30"
                    >
                        <iframe
                            :src="embedVideoUrl(section.video_url)!"
                            class="size-full"
                            :title="section.title"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                    <a
                        v-else-if="section.video_url"
                        :href="section.video_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                    >
                        <Icon icon="heroicons:play-circle" class="size-4" />
                        Watch video
                    </a>
                </CardContent>
            </Card>
        </template>

        <Card v-else class="border-dashed border-border/80 bg-muted/20">
            <CardHeader>
                <CardTitle class="text-base">Content coming soon</CardTitle>
                <CardDescription>
                    Tutorial steps will be added here. Your admin can paste guides into
                    <code class="rounded bg-muted px-1 py-0.5 text-xs">config/tutorial.php</code>
                    on the server.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="space-y-2 text-sm text-muted-foreground">
                    <li class="flex items-start gap-2">
                        <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        Create and publish webinar funnels
                    </li>
                    <li class="flex items-start gap-2">
                        <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        Connect social accounts for traffic auto-reply
                    </li>
                    <li class="flex items-start gap-2">
                        <Icon icon="heroicons:check-circle" class="mt-0.5 size-4 shrink-0 text-emerald-600" />
                        Track keywords and mentions
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
