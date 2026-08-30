<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type QuizQuestion = { question: string; options: string[] };

const props = defineProps<{
    modelValue: QuizQuestion[];
}>();

const emit = defineEmits<{ 'update:modelValue': [value: QuizQuestion[]] }>();

const questions = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

function addQuestion() {
    questions.value = [
        ...questions.value,
        { question: '', options: ['', ''] },
    ];
}

function removeQuestion(index: number) {
    questions.value = questions.value.filter((_, i) => i !== index);
}

function updateQuestion(index: number, patch: Partial<QuizQuestion>) {
    questions.value = questions.value.map((q, i) => (i === index ? { ...q, ...patch } : q));
}

function addOption(qIndex: number) {
    const q = questions.value[qIndex];
    if (!q || q.options.length >= 5) return;
    updateQuestion(qIndex, { options: [...q.options, ''] });
}

function updateOption(qIndex: number, oIndex: number, value: string) {
    const q = questions.value[qIndex];
    if (!q) return;
    const options = q.options.map((opt, i) => (i === oIndex ? value : opt));
    updateQuestion(qIndex, { options });
}

function removeOption(qIndex: number, oIndex: number) {
    const q = questions.value[qIndex];
    if (!q || q.options.length <= 2) return;
    updateQuestion(qIndex, { options: q.options.filter((_, i) => i !== oIndex) });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-2">
            <Label>Quiz questions</Label>
            <Button type="button" variant="outline" size="sm" :disabled="questions.length >= 5" @click="addQuestion">
                <Icon icon="heroicons:plus" class="mr-1 size-3.5" />
                Add question
            </Button>
        </div>

        <p v-if="!questions.length" class="rounded-md border border-dashed bg-muted/20 p-3 text-xs text-muted-foreground">
            No questions yet. Add at least 2 qualification questions for the quiz step.
        </p>

        <div v-for="(q, qi) in questions" :key="qi" class="space-y-3 rounded-lg border p-3">
            <div class="flex items-start justify-between gap-2">
                <Label class="text-xs text-muted-foreground">Question {{ qi + 1 }}</Label>
                <Button type="button" variant="ghost" size="icon" class="size-7 text-rose-600" @click="removeQuestion(qi)">
                    <Icon icon="heroicons:trash" class="size-3.5" />
                </Button>
            </div>
            <Input
                :model-value="q.question"
                placeholder="What's your biggest challenge with…?"
                @update:model-value="updateQuestion(qi, { question: String($event) })"
            />
            <div class="space-y-2 pl-2">
                <div v-for="(_, oi) in q.options" :key="oi" class="flex gap-2">
                    <Input
                        :model-value="q.options[oi]"
                        :placeholder="`Option ${oi + 1}`"
                        @update:model-value="updateOption(qi, oi, String($event))"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-muted-foreground"
                        :disabled="q.options.length <= 2"
                        @click="removeOption(qi, oi)"
                    >
                        <Icon icon="heroicons:x-mark" class="size-3.5" />
                    </Button>
                </div>
                <Button type="button" variant="ghost" size="sm" class="h-7 text-xs" :disabled="q.options.length >= 5" @click="addOption(qi)">
                    Add option
                </Button>
            </div>
        </div>
    </div>
</template>
