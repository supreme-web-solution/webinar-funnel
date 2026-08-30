<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    campaign: { name: string; type: string; uuid: string };
    content: {
        title?: string;
        intro?: string;
        questions?: Array<{ question: string; options: string[] }>;
        result_headline?: string;
        result_body?: string;
    };
    username: string;
    slug: string;
    optin_url?: string;
}>();

const answers = ref<number[]>([]);

function submitQuiz() {
    window.location.href = `/${props.username}/${props.slug}/p/thankyou`;
}
</script>

<template>
    <Head :title="content.title || 'Quiz'" />
    <div class="min-h-screen bg-slate-50 py-12 px-4">
        <div class="mx-auto max-w-xl rounded-2xl border bg-white p-8 shadow-sm">
            <h1 class="text-2xl font-bold text-slate-900">{{ content.title || 'Quick Quiz' }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ content.intro }}</p>

            <div v-if="content.questions?.length" class="mt-8 space-y-6">
                <div v-for="(q, qi) in content.questions" :key="qi">
                    <p class="font-medium text-slate-800">{{ q.question }}</p>
                    <div class="mt-2 space-y-2">
                        <label
                            v-for="(opt, oi) in q.options"
                            :key="oi"
                            class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm hover:bg-slate-50"
                        >
                            <input v-model="answers[qi]" type="radio" :value="oi" :name="'q'+qi" />
                            {{ opt }}
                        </label>
                    </div>
                </div>
            </div>

            <div v-else class="mt-8 rounded-lg bg-slate-100 p-6 text-center text-sm text-slate-600">
                <p class="font-medium">{{ content.result_headline || 'Almost there!' }}</p>
                <p class="mt-2">{{ content.result_body || 'Enter your email on the squeeze page to get your guide.' }}</p>
            </div>

            <button
                type="button"
                class="mt-8 w-full rounded-lg bg-indigo-600 py-3 font-semibold text-white"
                @click="submitQuiz"
            >
                See My Results
            </button>
        </div>
    </div>
</template>
