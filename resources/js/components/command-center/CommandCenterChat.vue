<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import EmployeeAvatar from '@/components/command-center/EmployeeAvatar.vue';
import { commandCenterFetch, type CommandCenterMessage, type CommandCenterState } from '@/lib/commandCenter';
import { renderChatContent } from '@/lib/chatMessageRender';

const props = defineProps<{
    state: CommandCenterState;
    compact?: boolean;
    class?: string;
}>();

const emit = defineEmits<{
    updated: [state: CommandCenterState];
}>();

const draft = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const scroller = ref<HTMLElement | null>(null);
let poll: ReturnType<typeof setInterval> | null = null;

const processing = computed(() => props.state.processing);
const messages = computed(() => props.state.messages.filter((m) => m.role === 'user' || m.role === 'assistant'));

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function renderContent(text: string): string {
    return renderChatContent(text);
}

function mergeIncoming(next: CommandCenterState): CommandCenterState {
    const locals = props.state.messages.filter((row) => String(row.id).startsWith('local-'));
    if (!locals.length) return next;
    const seen = new Set(next.messages.map((row) => row.content.trim()));
    const keep = locals.filter((row) => !seen.has(row.content.trim()));
    if (!keep.length) return next;

    return { ...next, messages: [...next.messages, ...keep] };
}

async function refresh(): Promise<void> {
    const response = await commandCenterFetch('/command-center/state');
    if (!response.ok) return;
    const data = (await response.json()) as CommandCenterState;
    emit('updated', mergeIncoming(data));
}

async function send(text?: string): Promise<void> {
    const message = (text ?? draft.value).trim();
    if (!message || sending.value || processing.value) return;
    sending.value = true;
    error.value = null;
    draft.value = '';
    const pending: CommandCenterMessage = {
        id: `local-${Date.now()}`,
        role: 'user',
        content: message,
        created_at: new Date().toISOString(),
    };
    emit('updated', {
        ...props.state,
        processing: true,
        progress: 'Thinking…',
        messages: [...props.state.messages, pending],
    });
    try {
        const response = await commandCenterFetch('/command-center/chat', {
            method: 'POST',
            body: JSON.stringify({ message }),
        });
        const payload = await response.json();
        if (!response.ok) {
            error.value = payload.message ?? 'Could not send.';
            draft.value = message;
            emit('updated', {
                ...props.state,
                processing: false,
                progress: null,
                messages: props.state.messages.filter((row) => row.id !== pending.id),
            });
            return;
        }
        if (payload.state) {
            const next = mergeIncoming(payload.state);
            const seen = next.messages.some((row) => row.content.trim() === message);
            emit('updated', seen ? next : { ...next, messages: [...next.messages, pending] });
        }
    } catch {
        error.value = 'Network error.';
        draft.value = message;
        emit('updated', {
            ...props.state,
            processing: false,
            progress: null,
            messages: props.state.messages.filter((row) => !row.id.startsWith('local-')),
        });
    } finally {
        sending.value = false;
    }
}

defineExpose({ send });

async function launch(id: number): Promise<void> {
    const response = await commandCenterFetch(`/command-center/approvals/${id}/launch`, { method: 'POST' });
    const payload = await response.json();
    if (payload.state) emit('updated', payload.state);
}

async function reject(id: number): Promise<void> {
    const response = await commandCenterFetch(`/command-center/approvals/${id}/reject`, { method: 'POST' });
    const payload = await response.json();
    if (payload.state) emit('updated', payload.state);
}

async function clearChat(): Promise<void> {
    const response = await commandCenterFetch('/command-center/clear', { method: 'POST' });
    const payload = await response.json();
    if (payload.state) emit('updated', payload.state);
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void send();
    }
}

watch(
    () => [props.state.messages.length, props.state.progress, props.state.processing],
    async () => {
        await nextTick();
        if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
    },
);

onMounted(() => {
    poll = setInterval(() => {
        if (props.state.processing) void refresh();
    }, 1600);
});

onBeforeUnmount(() => {
    if (poll) clearInterval(poll);
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col" :class="props.class">
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-border/60 px-4 py-2.5">
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Chat</p>
            <button
                type="button"
                class="cursor-pointer text-xs text-slate-500 transition-colors hover:text-blue-800"
                @click="clearChat"
            >
                Clear chat
            </button>
        </div>

        <div ref="scroller" class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-3 py-3 sm:px-4">
            <p v-if="messages.length === 0" class="text-sm text-muted-foreground">
                Ask {{ state.employee.name }} to check status, find offers, create campaigns, or plan content.
            </p>

            <div
                v-for="message in messages"
                :key="message.id"
                class="flex w-full gap-2"
                :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
            >
                <EmployeeAvatar
                    v-if="message.role === 'assistant'"
                    size="size-8"
                    class="mt-0.5 shrink-0"
                    :src="state.employee.avatar_url"
                    :alt="state.employee.name"
                />
                <div class="max-w-[min(85%,28rem)] space-y-1">
                    <div
                        class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed break-words"
                        :class="
                            message.role === 'user'
                                ? 'chat-bubble-user fill-brand-gradient border border-blue-400/20 text-white shadow-[var(--brand-gradient-shadow)]'
                                : 'chat-bubble-assistant border border-border/60 bg-muted/20 text-foreground'
                        "
                    >
                        <div class="chat-message-body" v-html="renderContent(message.content)" />
                    </div>
                    <p
                        class="px-1 text-[11px] text-muted-foreground"
                        :class="message.role === 'user' ? 'text-right' : ''"
                    >
                        <span v-if="message.role === 'user'" class="font-medium tracking-wide">WEB</span>
                        <span v-else>{{ state.employee.name }}</span>
                        <span v-if="message.created_at"> · {{ formatTime(message.created_at) }}</span>
                    </p>
                </div>
            </div>

            <div v-if="processing" class="flex items-center gap-2 text-sm text-muted-foreground">
                <EmployeeAvatar
                    size="size-7"
                    :src="state.employee.avatar_url"
                    :alt="state.employee.name"
                    thinking
                />
                <span>{{ state.progress || 'Thinking…' }}</span>
            </div>
        </div>

        <div v-if="state.approvals.length" class="space-y-2 border-t border-amber-200/80 bg-amber-50/70 px-4 py-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Review & Launch</p>
            <div
                v-for="approval in state.approvals"
                :key="approval.id"
                class="rounded-xl border border-amber-200 bg-white px-3 py-2"
            >
                <p class="text-sm font-medium text-slate-800">{{ approval.launch_label }}</p>
                <p class="mt-0.5 text-xs text-slate-600">{{ approval.summary }}</p>
                <div class="mt-2 flex gap-2">
                    <Button size="sm" variant="brand" class="cursor-pointer" @click="launch(approval.id)">
                        Approve
                    </Button>
                    <Button size="sm" variant="outline" class="cursor-pointer" @click="reject(approval.id)">
                        Reject
                    </Button>
                </div>
            </div>
        </div>

        <form class="shrink-0 border-t border-border/60 bg-white p-3" @submit.prevent="send()">
            <p v-if="error" class="mb-2 text-xs text-red-600">{{ error }}</p>
            <div class="flex items-end gap-2">
                <textarea
                    v-model="draft"
                    rows="1"
                    class="min-h-11 flex-1 resize-none rounded-xl border border-border/60 bg-white px-3 py-2.5 text-sm outline-none focus-visible:border-blue-400 focus-visible:ring-2 focus-visible:ring-blue-400/30"
                    :placeholder="compact ? `Message ${state.employee.name}…` : 'What do you want to accomplish?'"
                    :disabled="sending || processing || state.settings.killed"
                    @keydown="onKey"
                />
                <Button
                    type="submit"
                    size="icon"
                    variant="brand"
                    class="cursor-pointer"
                    :disabled="sending || processing || !draft.trim()"
                >
                    <Icon icon="heroicons:paper-airplane" class="size-4" />
                </Button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.chat-message-body :deep(strong) {
    font-weight: 600;
}

.chat-bubble-assistant :deep(.chat-message-link) {
    color: #2563eb;
    font-weight: 500;
    text-decoration: none;
    word-break: break-all;
}

.chat-bubble-assistant :deep(.chat-message-link:hover) {
    color: #1d4ed8;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.chat-bubble-user :deep(.chat-message-link) {
    color: inherit;
    text-decoration: none;
    word-break: break-all;
    opacity: 0.95;
}

.chat-bubble-user :deep(.chat-message-link:hover) {
    color: #dbeafe;
    text-decoration: underline;
    text-underline-offset: 2px;
    opacity: 1;
}
</style>
