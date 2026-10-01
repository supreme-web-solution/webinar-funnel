<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CommandCenterChat from '@/components/command-center/CommandCenterChat.vue';
import EmployeeAvatar from '@/components/command-center/EmployeeAvatar.vue';
import WhatsAppConnectPanel from '@/components/command-center/WhatsAppConnectPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { commandCenterFetch, mergeCommandCenterState, type CommandCenterState } from '@/lib/commandCenter';

const props = defineProps<CommandCenterState>();
const state = ref<CommandCenterState>({
    execute_tools: [],
    ...props,
});
const chatRef = ref<{ send: (text?: string) => Promise<void> } | null>(null);

const autonomyLevels = [
    { id: 'copilot', label: 'Copilot' },
    { id: 'assisted', label: 'Assisted' },
    { id: 'autopilot', label: 'Autopilot' },
] as const;

const shortcutChips = [
    { label: 'attention', message: 'attention' },
    { label: 'status', message: 'status' },
] as const;

const allowlist = computed(() => new Set(state.value.settings.execute_allowlist ?? []));

async function patchSettings(payload: Record<string, unknown>): Promise<void> {
    const response = await commandCenterFetch('/command-center/settings', {
        method: 'PATCH',
        body: JSON.stringify(payload),
    });
    const data = await response.json();
    if (data.state) state.value = data.state;
}

async function toggleAllow(tool: string): Promise<void> {
    const next = new Set(allowlist.value);
    if (next.has(tool)) next.delete(tool);
    else next.add(tool);
    await patchSettings({ execute_allowlist: [...next] });
}

async function startPairing(): Promise<void> {
    const response = await commandCenterFetch('/command-center/whatsapp/pairing', { method: 'POST' });
    const data = await response.json();
    if (data.state) state.value = data.state;
}

async function disconnectWhatsApp(): Promise<void> {
    const phone = state.value.whatsapp.linked_phone;
    const confirmed = window.confirm(
        phone
            ? `Disconnect WhatsApp ${phone}? You can link it again with Setup.`
            : 'Disconnect WhatsApp? You can link it again with Setup.',
    );
    if (!confirmed) return;

    const response = await commandCenterFetch('/command-center/whatsapp/disconnect', { method: 'POST' });
    const data = await response.json();
    if (data.state) state.value = mergeCommandCenterState(state.value, data.state);
}

function applyState(next: CommandCenterState): void {
    state.value = mergeCommandCenterState(state.value, next);
}

function sendChip(message: string): void {
    void chatRef.value?.send(message);
}
</script>

<template>
    <Head :title="`${state.employee.name} — ${state.employee.title}`" />

    <div
        class="mx-auto flex h-[calc(100dvh-3.5rem)] max-h-[calc(100dvh-3.5rem)] w-full max-w-6xl min-h-0 flex-col gap-2 overflow-hidden p-2 md:gap-2.5 md:p-4"
    >
        <!-- Header -->
        <div class="flex shrink-0 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <EmployeeAvatar
                    size="size-10 shrink-0"
                    :src="state.employee.avatar_url"
                    :alt="state.employee.name"
                    :thinking="state.processing"
                />
                <div class="min-w-0">
                    <h1 class="truncate text-lg font-bold tracking-tight text-foreground md:text-xl">
                        {{ state.employee.name }}
                        <span class="font-normal text-muted-foreground">— {{ state.employee.title }}</span>
                    </h1>
                    <p class="hidden max-w-2xl text-xs text-muted-foreground xl:block">
                        One brain for web and WhatsApp. Staged actions need Approve or LAUNCH; Activity shows what ran.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                <button
                    v-for="level in autonomyLevels"
                    :key="level.id"
                    type="button"
                    class="cursor-pointer rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                    :class="
                        state.settings.autonomy === level.id
                            ? 'chip-brand-active'
                            : 'border-[#E2E8F0] bg-white text-[#64748B] hover:border-[#BFDBFE] hover:bg-[#F8FAFC] hover:text-[#334155]'
                    "
                    @click="patchSettings({ autonomy: level.id })"
                >
                    {{ level.label }}
                </button>
            </div>
        </div>

        <!-- Suggestions -->
        <div class="shrink-0 rounded-xl border border-border/60 bg-white px-3 py-2 shadow-sm">
            <p class="mb-1.5 text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">Try asking</p>
            <div class="flex gap-2 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    v-for="chip in state.suggestions"
                    :key="chip"
                    type="button"
                    class="shrink-0 cursor-pointer rounded-full border border-border/60 bg-muted/30 px-3 py-1 text-xs font-medium whitespace-nowrap text-foreground transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-900"
                    @click="sendChip(chip)"
                >
                    {{ chip }}
                </button>
                <button
                    v-for="chip in shortcutChips"
                    :key="chip.label"
                    type="button"
                    class="shrink-0 cursor-pointer rounded-full border border-dashed border-border/60 px-2.5 py-1 text-[11px] font-medium whitespace-nowrap text-muted-foreground hover:border-blue-300 hover:text-blue-800"
                    @click="sendChip(chip.message)"
                >
                    {{ chip.label }}
                </button>
            </div>
            <div v-if="state.settings.killed" class="mt-2">
                <Badge variant="destructive">Employee paused — turn off kill switch to chat</Badge>
            </div>
        </div>

        <!-- Main: chat + sidebar -->
        <div
            class="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden lg:grid lg:grid-cols-[minmax(0,1fr)_280px] lg:grid-rows-1 lg:items-stretch"
        >
            <div
                class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm lg:h-full lg:min-h-0"
            >
                <CommandCenterChat ref="chatRef" class="h-full min-h-0" :state="state" @updated="applyState" />
            </div>

            <aside class="hidden min-h-0 flex-col gap-3 overflow-y-auto lg:flex">
                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-foreground">{{ state.employee.name }} settings</p>
                        <Label class="flex items-center gap-2 text-xs text-muted-foreground">
                            Kill switch
                            <Switch
                                :checked="state.settings.killed"
                                class="cursor-pointer"
                                @update:checked="(value) => patchSettings({ killed: Boolean(value) })"
                            />
                        </Label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Copilot reads and drafts. Assisted stages mutating tools for LAUNCH. Autopilot runs allowlisted
                        executes only.
                    </p>
                    <div
                        v-if="state.settings.autonomy === 'autopilot'"
                        class="mt-3 space-y-2 border-t border-border/60 pt-3"
                    >
                        <p class="text-xs font-medium text-foreground">Autopilot allowlist</p>
                        <label
                            v-for="tool in state.execute_tools"
                            :key="tool.name"
                            class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground"
                        >
                            <input
                                type="checkbox"
                                class="size-3.5 cursor-pointer rounded border-border text-blue-700"
                                :checked="allowlist.has(tool.name)"
                                @change="toggleAllow(tool.name)"
                            />
                            {{ tool.label }}
                        </label>
                    </div>
                </div>

                <WhatsAppConnectPanel
                    :employee-name="state.employee.name"
                    :employee-avatar="state.employee.avatar_url"
                    :configured="state.whatsapp.configured"
                    :linked-phone="state.whatsapp.linked_phone"
                    :pairing="state.whatsapp.pairing"
                    @setup="startPairing"
                    @disconnect="disconnectWhatsApp"
                />

                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="mb-2 text-sm font-semibold text-foreground">Needs attention</p>
                    <p v-if="!state.attention.length" class="text-xs text-muted-foreground">Inbox is clear.</p>
                    <ul class="space-y-2">
                        <li v-for="item in state.attention.slice(0, 5)" :key="item.title">
                            <Link
                                v-if="item.href"
                                :href="item.href"
                                class="block text-sm text-foreground transition-colors hover:text-blue-800"
                            >
                                {{ item.title }}
                            </Link>
                            <p v-else class="text-sm text-foreground">{{ item.title }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.detail }}</p>
                        </li>
                    </ul>
                </div>

                <div class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                    <p class="mb-2 text-sm font-semibold text-foreground">Activity</p>
                    <p v-if="!state.activity.length" class="text-xs text-muted-foreground">No tool runs yet.</p>
                    <ul class="space-y-1.5">
                        <li v-for="row in state.activity.slice(0, 8)" :key="row.id" class="text-xs text-muted-foreground">
                            <span class="font-medium text-foreground">{{ row.tool_name }}</span>
                            <span> · {{ row.status }}</span>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</template>
