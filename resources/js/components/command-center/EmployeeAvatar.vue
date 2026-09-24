<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

const props = defineProps<{
    size?: string;
    thinking?: boolean;
    online?: boolean;
}>();

const sizeClass = computed(() => props.size ?? 'size-11');
</script>

<template>
    <div class="relative shrink-0" :class="sizeClass">
        <div
            v-if="thinking"
            class="absolute -inset-1 rounded-full border-2 border-sky-400/70 border-t-transparent animate-spin"
        />
        <div
            class="bg-brand-gradient relative flex h-full w-full items-center justify-center overflow-hidden rounded-full text-white shadow-[var(--brand-gradient-shadow)]"
        >
            <Icon icon="heroicons:user" class="size-[55%] opacity-90" />
        </div>
        <span
            v-if="online !== false"
            class="absolute bottom-0 right-0 size-2.5 rounded-full border-2 border-white bg-blue-500"
        />
    </div>
</template>
