<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CommandCenterChat from '@/components/command-center/CommandCenterChat.vue';
import EmployeeAvatar from '@/components/command-center/EmployeeAvatar.vue';
import { commandCenterFetch, type CommandCenterState } from '@/lib/commandCenter';

const page = usePage();
const open = ref(false);
const loading = ref(false);
const state = ref<CommandCenterState | null>(null);

const hidden = computed(() => {
    const component = String(page.component ?? '');
    return !page.props.auth?.user || component === 'command-center/Index';
});

async function load(): Promise<void> {
    loading.value = true;
    try {
        const response = await commandCenterFetch('/command-center/state');
        if (response.ok) state.value = await response.json();
    } finally {
        loading.value = false;
    }
}

async function toggle(): Promise<void> {
    open.value = !open.value;
    if (open.value && !state.value) await load();
}

watch(open, (value) => {
    if (value) void load();
});

let poll: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    poll = setInterval(() => {
        if (open.value && state.value?.processing) void load();
    }, 1800);
});
onBeforeUnmount(() => {
    if (poll) clearInterval(poll);
});
</script>

<template>
    <div v-if="!hidden" class="pointer-events-none fixed bottom-5 right-5 z-40 flex flex-col items-end gap-3">
        <div
            v-if="open"
            class="pointer-events-auto flex h-[min(640px,calc(100dvh-6rem))] w-[min(400px,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
        >
            <div class="flex items-center justify-between border-b border-slate-200 px-3 py-2.5">
                <div class="flex items-center gap-2">
                    <EmployeeAvatar
                        size="size-8"
                        :src="state?.employee.avatar_url"
                        :alt="state?.employee.name"
                        :thinking="Boolean(state?.processing)"
                    />
                    <div>
                        <p class="text-sm font-semibold text-slate-800">
                            {{ state?.employee.name ?? 'Alex' }}
                        </p>
                        <p class="text-[11px] text-slate-400">Same thread as WhatsApp</p>
                    </div>
                </div>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                    @click="open = false"
                >
                    ×
                </button>
            </div>
            <CommandCenterChat
                v-if="state"
                compact
                :state="state"
                @updated="state = $event"
            />
            <p v-else-if="loading" class="p-4 text-sm text-slate-500">Loading chat…</p>
        </div>

        <button
            type="button"
            class="pointer-events-auto cursor-pointer rounded-full shadow-lg transition-opacity hover:opacity-95"
            :aria-label="open ? 'Close Command Center' : 'Open Command Center'"
            @click="toggle"
        >
            <EmployeeAvatar
                size="size-14"
                :src="state?.employee.avatar_url ?? '/images/ai-employee/alex.png'"
                :alt="state?.employee.name ?? 'Alex'"
                :thinking="Boolean(state?.processing)"
            />
        </button>
    </div>
</template>
