<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const APPLY_STEPS = [
    { id: 'save', label: 'Saving hop link', icon: 'heroicons:link' },
    { id: 'tracked', label: 'Updating tracked links', icon: 'heroicons:shield-check' },
    { id: 'thankyou', label: 'Thank-you bridge URL', icon: 'heroicons:document-text' },
    { id: 'bonus', label: 'Bonus page CTAs', icon: 'heroicons:gift' },
] as const;

const props = defineProps<{
    open: boolean;
    campaignId: number;
    campaignName: string;
    offerUrl?: string | null;
    marketplace?: string | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const form = useForm({
    affiliate_link: '',
});

const applyStepIndex = ref(-1);
const applyComplete = ref(false);
let stepTimer: ReturnType<typeof setInterval> | null = null;

const isApplying = computed(() => form.processing || applyComplete.value);

const canSubmit = computed(() => form.affiliate_link.trim().length > 0 && !isApplying.value);

const progressPercent = computed(() => {
    if (!isApplying.value) return 0;
    if (applyComplete.value) return 100;
    if (applyStepIndex.value < 0) return 8;
    return Math.round(((applyStepIndex.value + 1) / APPLY_STEPS.length) * 92);
});

const buttonLabel = computed(() => {
    if (applyComplete.value) return 'All set!';
    if (!form.processing) return 'Save & apply everywhere';
    if (applyStepIndex.value < 0) return 'Starting…';
    const step = APPLY_STEPS[applyStepIndex.value];
    return step ? `${step.label}…` : 'Applying…';
});

function stepStatus(index: number): 'pending' | 'active' | 'done' {
    if (applyComplete.value) return 'done';
    if (index < applyStepIndex.value) return 'done';
    if (index === applyStepIndex.value && form.processing) return 'active';
    return 'pending';
}

function startApplyProgress() {
    stopApplyProgress();
    applyComplete.value = false;
    applyStepIndex.value = 0;
    stepTimer = setInterval(() => {
        if (applyStepIndex.value < APPLY_STEPS.length - 1) {
            applyStepIndex.value += 1;
        }
    }, 700);
}

function stopApplyProgress(reset = true) {
    if (stepTimer) {
        clearInterval(stepTimer);
        stepTimer = null;
    }
    if (reset) {
        applyStepIndex.value = -1;
        applyComplete.value = false;
    }
}

function finishApplyProgress() {
    if (stepTimer) {
        clearInterval(stepTimer);
        stepTimer = null;
    }
    applyStepIndex.value = APPLY_STEPS.length - 1;
    applyComplete.value = true;
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            stopApplyProgress();
            form.reset('affiliate_link');
        }
    },
);

onUnmounted(() => stopApplyProgress());

function submit() {
    const link = form.affiliate_link.trim();
    if (!link || isApplying.value) {
        if (!link) toast.error('Paste your affiliate hop link first.');
        return;
    }

    startApplyProgress();

    form.patch(`/campaigns/${props.campaignId}`, {
        affiliate_link: link,
        preserveScroll: true,
        onSuccess: () => {
            finishApplyProgress();
            window.setTimeout(() => {
                emit('update:open', false);
                emit('saved');
                stopApplyProgress();
                form.reset('affiliate_link');
            }, 500);
        },
        onError: (errors) => {
            stopApplyProgress();
            const first = Object.values(errors)[0];
            toast.error(typeof first === 'string' ? first : 'Could not save affiliate link.');
        },
        onFinish: () => {
            if (!applyComplete.value) {
                stopApplyProgress();
            }
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="gap-0 overflow-hidden rounded-xl border border-border/60 p-0 shadow-xl sm:max-w-lg" :show-close-button="false">
            <!-- Header -->
            <div class="relative bg-linear-to-br from-blue-50 via-blue-50/60 to-white px-6 pt-6 pb-4">
                <div class="pointer-events-none absolute -right-6 -top-6 size-24 rounded-full bg-blue-400/15 blur-2xl" />
                <DialogHeader class="relative text-left">
                    <div class="flex items-start gap-3">
                        <div class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-white text-blue-600 shadow-sm ring-1 ring-blue-100">
                            <Icon icon="heroicons:link" class="size-5" />
                        </div>
                        <div>
                            <DialogTitle class="text-base">Add your affiliate hop link</DialogTitle>
                            <DialogDescription class="text-xs">
                                Your campaign is built — paste your hop link so CTAs, thank-you bridge, bonus page, and tracked links use your commission URL.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>
            </div>

            <div class="space-y-4 bg-white px-6 py-4">
                <div class="rounded-xl border border-border/60 bg-blue-50/30 p-3 text-sm">
                    <p class="font-semibold text-foreground">{{ campaignName }}</p>
                    <p v-if="offerUrl" class="mt-1 truncate text-xs text-muted-foreground">{{ offerUrl }}</p>
                    <p v-if="marketplace" class="mt-1 text-xs capitalize text-blue-700">{{ marketplace }} offer</p>
                </div>

                <div class="space-y-2">
                    <Label for="affiliate-hop-link">Affiliate hop link</Label>
                    <Input
                        id="affiliate-hop-link"
                        v-model="form.affiliate_link"
                        placeholder="https://www.jvzoo.com/affiliate/…"
                        autocomplete="off"
                        :disabled="isApplying"
                        @keyup.enter="submit"
                    />
                    <p v-if="!isApplying" class="text-xs text-muted-foreground">
                        Get this from your affiliate dashboard after approving the offer. We cloak it automatically for your funnel CTAs.
                    </p>
                    <p v-if="form.errors.affiliate_link" class="text-xs text-destructive">{{ form.errors.affiliate_link }}</p>
                </div>

                <div v-if="isApplying" class="space-y-3 rounded-xl border border-blue-200/60 bg-blue-50/40 p-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-blue-900">Applying to your campaign</span>
                        <span class="tabular-nums text-blue-700">{{ progressPercent }}%</span>
                    </div>
                    <div class="h-1.5 overflow-hidden rounded-full bg-blue-100">
                        <div
                            class="h-full rounded-full fill-brand-gradient transition-all duration-500 ease-out"
                            :style="{ width: `${progressPercent}%` }"
                        />
                    </div>
                    <ul class="space-y-1.5">
                        <li
                            v-for="(step, i) in APPLY_STEPS"
                            :key="step.id"
                            class="flex items-center gap-2 text-xs"
                            :class="{
                                'font-medium text-blue-900': stepStatus(i) === 'active',
                                'text-blue-700': stepStatus(i) === 'done',
                                'text-muted-foreground': stepStatus(i) === 'pending',
                            }"
                        >
                            <Icon
                                v-if="stepStatus(i) === 'done'"
                                icon="heroicons:check-circle"
                                class="size-3.5 shrink-0 text-blue-600"
                            />
                            <Icon
                                v-else-if="stepStatus(i) === 'active'"
                                icon="heroicons:arrow-path"
                                class="size-3.5 shrink-0 animate-spin text-blue-600"
                            />
                            <Icon
                                v-else
                                :icon="step.icon"
                                class="size-3.5 shrink-0 opacity-40"
                            />
                            {{ step.label }}
                        </li>
                    </ul>
                </div>
            </div>

            <DialogFooter class="border-t border-border/60 bg-muted/10 px-6 py-4">
                <Button variant="brand" :disabled="!canSubmit" @click="submit">
                    <Icon
                        v-if="form.processing && !applyComplete"
                        icon="heroicons:arrow-path"
                        class="size-4 animate-spin"
                    />
                    <Icon
                        v-else-if="applyComplete"
                        icon="heroicons:check-circle"
                        class="size-4"
                    />
                    {{ buttonLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
