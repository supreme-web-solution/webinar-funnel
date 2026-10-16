<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

const page = usePage<{
    auth?: {
        user?: { name?: string; email?: string } | null;
        impersonating?: boolean;
    };
}>();

const leaving = ref(false);

const active = computed(() => Boolean(page.props.auth?.impersonating));
const label = computed(() => page.props.auth?.user?.email || page.props.auth?.user?.name || 'this user');

function leave(): void {
    if (leaving.value) return;
    leaving.value = true;
    router.post('/impersonation/leave', {}, {
        onFinish: () => {
            leaving.value = false;
        },
    });
}
</script>

<template>
    <div
        v-if="active"
        class="flex flex-wrap items-center justify-between gap-2 border-b border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950"
    >
        <div class="flex min-w-0 items-center gap-2">
            <Icon icon="heroicons:eye" class="size-4 shrink-0 text-amber-700" />
            <p class="min-w-0">
                Viewing as <span class="font-semibold">{{ label }}</span>
            </p>
        </div>
        <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-7 border-amber-300 bg-white text-xs text-amber-950 hover:bg-amber-100"
            :disabled="leaving"
            @click="leave"
        >
            <Icon
                :icon="leaving ? 'heroicons:arrow-path' : 'heroicons:arrow-uturn-left'"
                class="size-3.5"
                :class="leaving ? 'animate-spin' : ''"
            />
            Back to admin
        </Button>
    </div>
</template>
