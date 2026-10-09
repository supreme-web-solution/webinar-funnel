<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

defineProps<{
    signupUrl: string;
}>();
</script>

<template>
    <Head title="Affiliate Campaign Vault" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4">
        <!-- Header -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-bold tracking-tight text-foreground md:text-2xl">Affiliate Campaign Vault</h1>
                <p class="mt-0.5 text-sm leading-relaxed text-muted-foreground">
                    Your Affiliate Campaign Vault access is active. Create your AffiliMachine account to unlock the vault.
                </p>
            </div>
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                <Icon icon="heroicons:archive-box" class="size-5 text-blue-600" />
            </div>
        </div>

        <!-- Signup -->
        <div class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
            <div class="border-b border-border/60 bg-blue-50/20 px-4 py-3 md:px-5">
                <div class="flex items-center gap-2">
                    <span class="flex size-6 shrink-0 items-center justify-center rounded-md bg-brand-gradient text-[0.65rem] font-bold text-white">1</span>
                    <h2 class="text-base font-semibold text-foreground">Sign up to AffiliMachine</h2>
                </div>
            </div>
            <div class="flex flex-col gap-4 p-4 md:flex-row md:items-center md:justify-between md:p-5">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Register your free AffiliMachine account using the button below, then log in to access your campaign vault.
                </p>
                <Button as-child variant="brand" size="sm" class="shrink-0">
                    <a :href="signupUrl" target="_blank" rel="noopener noreferrer">
                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        Sign up to AffiliMachine
                    </a>
                </Button>
            </div>
        </div>
    </div>
</template>
