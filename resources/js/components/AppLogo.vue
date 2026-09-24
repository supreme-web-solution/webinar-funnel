<script setup lang="ts">
import { useAppName } from '@/composables/useAppName';

const appName = useAppName();
</script>

<template>
    <div class="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-lg ring-1 ring-sidebar-primary/30">
        <img :alt="appName" src="/favicon.png" class="size-full object-cover" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-tight">
        <span class="truncate text-[0.8rem] font-bold tracking-tight text-sidebar-accent-foreground">
            {{ appName }}
        </span>
        <span class="text-[0.65rem] text-sidebar-foreground/50 truncate">Affiliate Business Builder</span>
    </div>
</template>
