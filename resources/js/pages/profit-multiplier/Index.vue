<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

export interface ProfitMultiplierLink {
    label: string;
    url: string | null;
}

defineProps<{
    links: ProfitMultiplierLink[];
}>();
</script>

<template>
    <Head title="Profit Multiplier" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Profit Multiplier</h1>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">
                    Your Profit Multiplier access is active. Open the resources below to get started.
                </p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                <Icon icon="heroicons:arrow-trending-up" class="size-5 text-blue-600" />
            </div>
        </div>

        <!-- Links -->
        <article
            v-for="(link, index) in links"
            :key="`${link.label}-${index}`"
            class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm"
        >
            <div class="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between md:p-5">
                <div class="flex items-center gap-2">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-brand-gradient text-[0.65rem] font-bold text-white">
                        {{ index + 1 }}
                    </span>
                    <h2 class="text-base font-semibold text-foreground">{{ link.label }}</h2>
                </div>
                <Button v-if="link.url" as-child variant="brand" size="sm" class="shrink-0">
                    <a :href="link.url" target="_blank" rel="noopener noreferrer">
                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        Open
                    </a>
                </Button>
                <Button v-else variant="outline" size="sm" class="shrink-0" disabled>
                    <Icon icon="heroicons:clock" class="size-3.5" />
                    Coming soon
                </Button>
            </div>
        </article>
    </div>
</template>
