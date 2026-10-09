<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    campaign: { name: string; type: string; uuid: string };
    content: {
        title?: string;
        intro?: string;
        questions?: Array<{ question: string; options: string[] }>;
        result_headline?: string;
        result_body?: string;
        lead_magnet_title?: string | null;
    };
    username: string;
    slug: string;
    optin_url: string;
}>();

const questions = computed(() => props.content.questions ?? []);
const answers = ref<Array<number | undefined>>([]);
const step = ref<'questions' | 'results'>('questions');

const answeredCount = computed(() => answers.value.filter((a) => a !== undefined).length);
const allAnswered = computed(() => questions.value.length > 0 && answeredCount.value === questions.value.length);

const answerSummary = computed(() =>
    questions.value
        .map((q, qi) => {
            const index = answers.value[qi];

            return index === undefined ? null : { question: q.question, answer: q.options[index] ?? '' };
        })
        .filter((row): row is { question: string; answer: string } => row !== null && row.answer !== ''),
);

const guideLabel = computed(() => props.content.lead_magnet_title || 'your free guide');

const name = ref('');
const email = ref('');
const submitting = ref(false);
const errors = ref<Record<string, string>>({});

function showResults() {
    if (!allAnswered.value) {
        return;
    }

    step.value = 'results';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function submit() {
    submitting.value = true;
    errors.value = {};
    router.post(props.optin_url, {
        name: name.value,
        email: email.value,
        quiz_answers: answerSummary.value,
    }, {
        onError: (e) => {
 errors.value = e; 
},
        onFinish: () => {
 submitting.value = false; 
},
    });
}
</script>

<template>
    <Head :title="content.title || 'Quiz'" />
    <div class="min-h-screen bg-slate-50 py-12 px-4">
        <div class="mx-auto max-w-xl rounded-2xl border bg-white p-8 shadow-sm">
            <template v-if="step === 'questions'">
                <h1 class="text-2xl font-bold text-slate-900">{{ content.title || 'Quick Quiz' }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ content.intro }}</p>

                <div v-if="questions.length" class="mt-8 space-y-6">
                    <div v-for="(q, qi) in questions" :key="qi">
                        <p class="font-medium text-slate-800">{{ qi + 1 }}. {{ q.question }}</p>
                        <div class="mt-2 space-y-2">
                            <label
                                v-for="(opt, oi) in q.options"
                                :key="oi"
                                class="flex cursor-pointer items-center gap-2 rounded-lg border p-3 text-sm hover:bg-slate-50"
                                :class="answers[qi] === oi ? 'border-indigo-500 bg-indigo-50' : ''"
                            >
                                <input v-model="answers[qi]" type="radio" :value="oi" :name="'q'+qi" />
                                {{ opt }}
                            </label>
                        </div>
                    </div>
                </div>

                <div v-else class="mt-8 rounded-lg bg-slate-100 p-6 text-center text-sm text-slate-600">
                    This quiz has no questions yet.
                </div>

                <button
                    type="button"
                    class="mt-8 w-full rounded-lg bg-indigo-600 py-3 font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!allAnswered"
                    @click="showResults"
                >
                    See My Results
                </button>
                <p v-if="questions.length && !allAnswered" class="mt-2 text-center text-xs text-slate-500">
                    {{ answeredCount }} of {{ questions.length }} answered
                </p>
            </template>

            <template v-else>
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Your results</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ content.result_headline || 'Your results are ready!' }}</h1>
                <p class="mt-2 text-sm text-slate-600">{{ content.result_body || 'Enter your email below to get your personalized guide.' }}</p>

                <ul v-if="answerSummary.length" class="mt-6 space-y-2 rounded-lg bg-slate-50 p-4 text-sm">
                    <li v-for="(row, i) in answerSummary" :key="i">
                        <span class="text-slate-500">{{ row.question }}</span>
                        <span class="block font-medium text-slate-800">{{ row.answer }}</span>
                    </li>
                </ul>

                <form class="mt-6 flex flex-col gap-3" @submit.prevent="submit">
                    <p class="text-sm font-medium text-slate-800">Where should we send {{ guideLabel }}?</p>
                    <input v-model="name" class="rounded-lg border px-4 py-3 text-slate-900" type="text" name="name" placeholder="Your name" />
                    <input v-model="email" class="rounded-lg border px-4 py-3 text-slate-900" type="email" name="email" required placeholder="Email address" />
                    <p v-if="errors.email" class="text-xs text-red-600">{{ errors.email }}</p>
                    <button
                        type="submit"
                        class="rounded-lg bg-indigo-600 py-3 font-semibold text-white disabled:opacity-60"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Sending…' : 'Get My Free Guide' }}
                    </button>
                </form>

                <button type="button" class="mt-4 w-full text-center text-xs text-slate-500 underline" @click="step = 'questions'">
                    Change my answers
                </button>
            </template>
        </div>
    </div>
</template>
