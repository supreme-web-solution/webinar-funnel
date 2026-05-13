<script setup lang="ts">
import { Icon } from '@iconify/vue';
</script>

<template>
    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary">
        <Icon icon="heroicons:video-camera" class="size-4.5 text-sidebar-primary-foreground" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-tight">
        <span class="truncate text-[0.8rem] font-bold tracking-tight text-sidebar-accent-foreground">
            DFY Webinar Funnels
        </span>
        <span class="text-[0.65rem] text-sidebar-foreground/50 truncate">Funnel Builder</span>
    </div>
</template>
