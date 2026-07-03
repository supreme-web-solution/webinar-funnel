<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

defineProps<{
    videoUrl: string;
    checkoutUrl: string;
}>();

function embedVideoUrl(url: string): string | null {
    try {
        const parsed = new URL(url.trim());
        const host = parsed.hostname.replace(/^www\./, '');

        if (host === 'vimeo.com') {
            const id = parsed.pathname.replace(/^\//, '').split('/')[0];
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
    <Head title="1-on-1 Coaching" />

    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
        <div
            class="relative overflow-hidden rounded-2xl border border-emerald-200/60 bg-linear-to-r from-emerald-50 via-cyan-50 to-sky-50 p-5 shadow-sm"
        >
            <div class="pointer-events-none absolute -right-8 -top-10 h-36 w-36 rounded-full bg-emerald-200/40 blur-2xl" />
            <div class="relative flex items-start gap-3">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-emerald-500 to-cyan-500 text-white shadow-sm"
                >
                    <Icon icon="heroicons:user-group" class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-foreground">Exclusive 1-on-1 Coaching</h1>
                    <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Build your own profitable affiliate marketing business with personal guidance.
                    </p>
                </div>
            </div>
        </div>

        <Card class="border-border/80 shadow-sm">
            <CardHeader class="pb-2">
                <CardTitle class="text-base leading-snug">
                    Exclusive 1-on-1 Coaching to Build Your Own Profitable Affiliate Marketing Business
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-5 pt-0">
                <p class="text-sm text-muted-foreground">
                    Learn more by clicking on the video below.
                </p>

                <div class="rounded-lg border border-amber-200/80 bg-amber-50/80 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">
                        Watch This 10 Minutes Video First!!!
                    </p>
                </div>

                <div
                    v-if="embedVideoUrl(videoUrl)"
                    class="aspect-video overflow-hidden rounded-lg border border-border/80 bg-muted/30"
                >
                    <iframe
                        :src="embedVideoUrl(videoUrl)!"
                        class="size-full"
                        title="2026 1-on-1 AM Coaching Program"
                        allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media"
                        allowfullscreen
                    />
                </div>

                <div class="space-y-3 rounded-xl border border-border/80 bg-muted/20 p-5 text-center">
                    <p class="text-base font-semibold text-foreground">Interested?</p>
                    <p class="text-sm text-muted-foreground">
                        Click below to make your payment and let's get started today.
                    </p>
                    <Button
                        as-child
                        class="h-11 px-6 text-sm font-semibold shadow-sm"
                        style="background:#40E0D0; color:#0f172a"
                    >
                        <a :href="checkoutUrl" target="_blank" rel="noopener noreferrer">
                            <Icon icon="heroicons:credit-card" class="mr-2 size-4" />
                            Make Your Payment &amp; Get Started
                        </a>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
