<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    campaign: { name: string; type: string; uuid: string };
    content: {
        headline?: string;
        download_cta?: string;
        download_url?: string;
        bridge_headline?: string;
        bridge_body?: string;
        bridge_cta?: string;
        bridge_url?: string;
        brand_color?: string;
    };
}>();
</script>

<template>
    <Head :title="'Thank you — ' + campaign.name" />
    <div class="min-h-screen bg-slate-50">
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center gap-8 px-6 py-16 text-center">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                {{ content.headline || 'Congrats! Click the link below to download your free gift…' }}
            </h1>
            <a
                v-if="content.download_url"
                :href="content.download_url"
                download
                class="mx-auto inline-flex rounded-lg px-8 py-4 text-lg font-bold text-white transition-opacity hover:opacity-90"
                :style="{ background: content.brand_color || '#4f46e5' }"
            >
                {{ content.download_cta || 'DOWNLOAD NOW' }}
            </a>
            <p v-else class="text-sm text-muted-foreground">Download link will appear after opt-in.</p>

            <div class="rounded-2xl border bg-white p-6 text-left shadow-sm">
                <h2 class="text-xl font-semibold text-slate-900">{{ content.bridge_headline || 'What Next?' }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ content.bridge_body }}</p>
                <a
                    v-if="content.bridge_url"
                    :href="content.bridge_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-4 inline-flex text-sm font-semibold underline"
                    :style="{ color: content.brand_color || '#4f46e5' }"
                >
                    {{ content.bridge_cta || 'Continue' }}
                </a>
            </div>
        </div>
    </div>
</template>
