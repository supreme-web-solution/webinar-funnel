<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CampaignVideoEmbed from '@/components/campaigns/CampaignVideoEmbed.vue';

const props = defineProps<{
    campaign: { name: string; type: string; uuid: string };
    content: {
        headline?: string;
        subheadline?: string;
        cta?: string;
        bullet_points?: string[];
        brand_color?: string;
        video_url?: string;
        video_title?: string;
    };
    username: string;
    slug: string;
    optin_url: string;
}>();

const name = ref('');
const email = ref('');
const submitting = ref(false);

function submit() {
    submitting.value = true;
    router.post(props.optin_url, { name: name.value, email: email.value }, {
        onFinish: () => { submitting.value = false; },
    });
}
</script>

<template>
    <Head :title="content.headline || campaign.name" />
    <div class="min-h-screen bg-slate-950 text-white">
        <div class="mx-auto flex min-h-screen max-w-2xl flex-col justify-center gap-6 px-6 py-16 text-center">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl" :style="{ color: content.brand_color || '#a5b4fc' }">
                {{ content.headline || campaign.name }}
            </h1>
            <p class="text-base text-slate-300">{{ content.subheadline }}</p>

            <CampaignVideoEmbed
                v-if="content.video_url"
                :url="content.video_url"
                :title="content.video_title"
                class="mx-auto w-full max-w-lg text-left"
            />

            <ul v-if="content.bullet_points?.length" class="mx-auto max-w-md space-y-2 text-left text-sm text-slate-300">
                <li v-for="(b, i) in content.bullet_points" :key="i" class="flex gap-2">
                    <span class="text-indigo-400">✓</span>
                    <span>{{ b }}</span>
                </li>
            </ul>
            <form class="mx-auto flex w-full max-w-md flex-col gap-3" @submit.prevent="submit">
                <input v-model="name" class="rounded-lg border-0 bg-white px-4 py-3 text-slate-900" type="text" name="name" placeholder="Your name" />
                <input v-model="email" class="rounded-lg border-0 bg-white px-4 py-3 text-slate-900" type="email" name="email" required placeholder="Email address" />
                <button
                    type="submit"
                    class="rounded-lg px-4 py-3 font-semibold text-white disabled:opacity-60"
                    :style="{ background: content.brand_color || '#4f46e5' }"
                    :disabled="submitting"
                >
                    {{ content.cta || 'Get Instant Access' }}
                </button>
            </form>
        </div>
    </div>
</template>
