<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type CampaignCreateType = 'sales' | 'webinar';
export type CampaignIntakeMode = 'links' | 'keyword';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    complete: [payload: { type: CampaignCreateType; mode: CampaignIntakeMode }];
}>();

const step = ref<'type' | 'source'>('type');
const selectedType = ref<CampaignCreateType>('sales');

function pickType(type: CampaignCreateType) {
    selectedType.value = type;
    step.value = 'source';
}

function pickSource(mode: CampaignIntakeMode) {
    emit('complete', { type: selectedType.value, mode });
    step.value = 'type';
}

function goBack() {
    step.value = 'type';
}
</script>

<template>
    <Dialog :open="open">
        <DialogContent class="gap-0 overflow-hidden rounded-xl border border-border/60 p-0 shadow-xl sm:max-w-xl" :show-close-button="false">
            <div class="bg-linear-to-br from-blue-50 via-blue-50/60 to-white px-6 pt-6 pb-3">
                <DialogHeader class="text-left">
                    <DialogTitle>{{ step === 'type' ? 'What kind of campaign?' : 'How do you want to import the offer?' }}</DialogTitle>
                    <DialogDescription class="text-xs">
                        {{ step === 'type'
                            ? 'Pick the funnel style — you can change this later.'
                            : 'Paste your links directly, or search marketplaces by keyword.' }}
                    </DialogDescription>
                </DialogHeader>
            </div>

            <div class="space-y-3 bg-white px-6 pb-6 pt-4">
            <div v-if="step === 'type'" class="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-xl border-2 border-border/60 bg-white p-5 text-left shadow-sm transition-all hover:border-blue-400 hover:shadow-md"
                    @click="pickType('sales')"
                >
                    <Icon icon="heroicons:shopping-bag" class="mb-3 size-9 text-blue-600" />
                    <p class="text-lg font-semibold">Sales funnel</p>
                    <p class="mt-1 text-sm text-muted-foreground">Squeeze page, lead magnet, bonus stack, and promo emails for a direct-sale offer.</p>
                </button>
                <button
                    type="button"
                    class="rounded-xl border-2 border-border/60 bg-white p-5 text-left shadow-sm transition-all hover:border-blue-400 hover:shadow-md"
                    @click="pickType('webinar')"
                >
                    <Icon icon="heroicons:video-camera" class="mb-3 size-9 text-blue-600" />
                    <p class="text-lg font-semibold">Webinar funnel</p>
                    <p class="mt-1 text-sm text-muted-foreground">Everything in sales plus an auto-built webinar room to pitch live or replay.</p>
                </button>
            </div>

            <div v-else class="space-y-3">
                <button
                    type="button"
                    class="flex w-full items-start gap-4 rounded-xl border-2 border-border/60 bg-white p-4 text-left shadow-sm transition-all hover:border-blue-400 hover:shadow-sm"
                    @click="pickSource('links')"
                >
                    <Icon icon="heroicons:link" class="mt-0.5 size-8 shrink-0 text-blue-600" />
                    <div>
                        <p class="font-semibold">I have my links</p>
                        <p class="mt-1 text-sm text-muted-foreground">Paste the vendor sales page URL and your affiliate hop link — we extract everything in one click.</p>
                    </div>
                </button>
                <button
                    type="button"
                    class="flex w-full items-start gap-4 rounded-xl border-2 border-border/60 bg-white p-4 text-left shadow-sm transition-all hover:border-blue-400 hover:shadow-sm"
                    @click="pickSource('keyword')"
                >
                    <Icon icon="heroicons:magnifying-glass" class="mt-0.5 size-8 shrink-0 text-blue-600" />
                    <div>
                        <p class="font-semibold">Find by keyword</p>
                        <p class="mt-1 text-sm text-muted-foreground">Search JVZoo, WarriorPlus, and ClickBank-style listings. Pick a product, add your hop link, then continue.</p>
                    </div>
                </button>
                <Button variant="ghost" size="sm" class="mt-2 text-blue-700 hover:text-blue-800" @click="goBack">← Back</Button>
            </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
