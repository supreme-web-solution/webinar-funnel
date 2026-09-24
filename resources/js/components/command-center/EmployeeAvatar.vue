<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    size?: string;
    thinking?: boolean;
    online?: boolean;
    src?: string | null;
    alt?: string;
}>();

const sizeClass = computed(() => props.size ?? 'size-11');
const imageFailed = ref(false);

const showImage = computed(() => Boolean(props.src) && !imageFailed.value);
</script>

<template>
    <div class="relative shrink-0" :class="sizeClass">
        <div
            v-if="thinking"
            class="absolute -inset-1 rounded-full border-2 border-sky-400/70 border-t-transparent animate-spin"
        />
        <div
            class="relative flex h-full w-full items-center justify-center overflow-hidden rounded-full shadow-[var(--brand-gradient-shadow)]"
            :class="showImage ? 'bg-muted ring-1 ring-border/40' : 'bg-brand-gradient text-white'"
        >
            <img
                v-if="showImage"
                :src="src!"
                :alt="alt ?? 'AI employee'"
                class="h-full w-full object-cover object-center"
                @error="imageFailed = true"
            />
            <Icon v-else icon="heroicons:user" class="size-[55%] opacity-90" />
        </div>
        <span
            v-if="online !== false"
            class="absolute bottom-0 right-0 size-2.5 rounded-full border-2 border-white bg-blue-500"
        />
    </div>
</template>
