<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import EmployeeAvatar from '@/components/command-center/EmployeeAvatar.vue';
import { buildChatTimeline, mergeMessagesById } from '@/lib/chatTimeline';
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
const loadingOlder = ref(false);
const stickToBottom = ref(true);

const messages = ref<CommandCenterMessage[]>([...props.state.messages]);
const hasOlder = ref(props.state.messages_meta?.has_older ?? false);

let poll: ReturnType<typeof setInterval> | null = null;

const processing = computed(() => props.state.processing);
const visibleMessages = computed(() =>
    messages.value.filter((m) => m.role === 'user' || m.role === 'assistant'),
);
const timeline = computed(() => buildChatTimeline(visibleMessages.value));

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

function renderContent(text: string): string {
    return renderChatContent(text);
}

function isLocalId(id: string): boolean {
    return String(id).startsWith('local-');
}

function lastPersistedMessage(): CommandCenterMessage | undefined {
    return [...visibleMessages.value].reverse().find((m) => !isLocalId(String(m.id)));
}

function emitStatePatch(patch: Partial<CommandCenterState>): void {
    emit('updated', {
        ...props.state,
        ...patch,
        messages: messages.value,
        messages_meta: {
            has_older: hasOlder.value,
            oldest_id: visibleMessages.value[0]?.id ?? props.state.messages_meta?.oldest_id ?? null,
        },
    });
}

function syncFromProps(force = false): void {
    if (force || props.state.messages.length === 0) {
        messages.value = [...props.state.messages];
        hasOlder.value = props.state.messages_meta?.has_older ?? false;
    }
}

watch(
    () => props.state.conversation_id,
    (next, prev) => {
        if (next !== prev) {
            syncFromProps(true);
            stickToBottom.value = true;
        }
    },
);

watch(
    () => props.state.messages,
    (next) => {
        if (next.length === 0 && visibleMessages.value.length > 0) {
            messages.value = [];
            hasOlder.value = false;
        }
    },
);

function mergeIncoming(next: CommandCenterState): CommandCenterState {
    const locals = messages.value.filter((row) => isLocalId(String(row.id)));
    const merged = mergeMessagesById(messages.value.filter((row) => !isLocalId(String(row.id))), next.messages);
    messages.value = locals.length ? [...merged, ...locals] : merged;
    if (next.messages_meta) {
        hasOlder.value = next.messages_meta.has_older;
    }

    return { ...next, messages: messages.value, messages_meta: { has_older: hasOlder.value, oldest_id: visibleMessages.value[0]?.id ?? null } };
}

async function scrollToBottom(): Promise<void> {
    await nextTick();
    if (scroller.value) {
        scroller.value.scrollTop = scroller.value.scrollHeight;
    }
}

function onScroll(): void {
    const el = scroller.value;
    if (!el) return;
    const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
    stickToBottom.value = distanceFromBottom < 120;
    if (el.scrollTop < 80 && hasOlder.value && !loadingOlder.value) {
        void loadOlder();
    }
}

async function loadOlder(): Promise<void> {
    const oldest = visibleMessages.value[0];
    if (!oldest || isLocalId(String(oldest.id)) || loadingOlder.value) return;

    loadingOlder.value = true;
    const el = scroller.value;
    const prevHeight = el?.scrollHeight ?? 0;

    try {
        const response = await commandCenterFetch(
            `/command-center/messages?before=${encodeURIComponent(String(oldest.id))}`,
        );
        if (!response.ok) return;
        const payload = (await response.json()) as {
            data: CommandCenterMessage[];
            has_older?: boolean;
        };
        if (payload.data?.length) {
            messages.value = mergeMessagesById(payload.data, messages.value);
            hasOlder.value = Boolean(payload.has_older);
            await nextTick();
            if (el) {
                el.scrollTop = el.scrollHeight - prevHeight;
            }
        } else {
            hasOlder.value = false;
        }
    } finally {
        loadingOlder.value = false;
    }
}

async function refreshTail(): Promise<void> {
    const last = lastPersistedMessage();
    const stateUrl = '/command-center/state?messages=0';
    const fetches: Promise<Response>[] = [commandCenterFetch(stateUrl)];

    if (last) {
        fetches.push(
            commandCenterFetch(`/command-center/messages?after=${encodeURIComponent(String(last.id))}`),
        );
    }

    const [stateRes, tailRes] = await Promise.all(fetches);
    if (!stateRes.ok) return;

    const stateData = (await stateRes.json()) as CommandCenterState;

    if (tailRes?.ok) {
        const tail = (await tailRes.json()) as { data?: CommandCenterMessage[] };
        if (tail.data?.length) {
            messages.value = mergeMessagesById(messages.value, tail.data);
        }
    } else if (!last) {
        syncFromProps(true);
    }

    emit('updated', {
        ...stateData,
        messages: messages.value,
        messages_meta: {
            has_older: hasOlder.value,
            oldest_id: visibleMessages.value[0]?.id ?? null,
        },
    });

    if (stickToBottom.value) {
        await scrollToBottom();
    }
}

async function refresh(): Promise<void> {
    await refreshTail();
}

async function send(text?: string): Promise<void> {
    const message = (text ?? draft.value).trim();
    if (!message || sending.value || processing.value) return;
    sending.value = true;
    error.value = null;
    draft.value = '';
    stickToBottom.value = true;
    const pending: CommandCenterMessage = {
        id: `local-${Date.now()}`,
        role: 'user',
        content: message,
        created_at: new Date().toISOString(),
    };
    messages.value = [...messages.value, pending];
    emitStatePatch({ processing: true, progress: 'Thinking…' });

    try {
        const response = await commandCenterFetch('/command-center/chat', {
            method: 'POST',
            body: JSON.stringify({ message }),
        });
        const payload = await response.json();
        if (!response.ok) {
            error.value = payload.message ?? 'Could not send.';
            draft.value = message;
            messages.value = messages.value.filter((row) => row.id !== pending.id);
            emitStatePatch({ processing: false, progress: null });
            return;
        }
        if (payload.state) {
            mergeIncoming(payload.state as CommandCenterState);
            emit('updated', {
                ...(payload.state as CommandCenterState),
                messages: messages.value,
                messages_meta: {
                    has_older: hasOlder.value,
                    oldest_id: visibleMessages.value[0]?.id ?? null,
                },
            });
        }
    } catch {
        error.value = 'Network error.';
        draft.value = message;
        messages.value = messages.value.filter((row) => !isLocalId(String(row.id)));
        emitStatePatch({ processing: false, progress: null });
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
}

defineExpose({ send });

async function launch(id: number): Promise<void> {
    const response = await commandCenterFetch(`/command-center/approvals/${id}/launch`, { method: 'POST' });
    const payload = await response.json();
    if (payload.state) {
        mergeIncoming(payload.state);
        emit('updated', { ...payload.state, messages: messages.value });
    }
}

async function reject(id: number): Promise<void> {
    const response = await commandCenterFetch(`/command-center/approvals/${id}/reject`, { method: 'POST' });
    const payload = await response.json();
    if (payload.state) {
        mergeIncoming(payload.state);
        emit('updated', { ...payload.state, messages: messages.value });
    }
}

async function clearChat(): Promise<void> {
    const response = await commandCenterFetch('/command-center/clear', { method: 'POST' });
    const payload = await response.json();
    if (payload.state) {
        messages.value = [];
        hasOlder.value = false;
        emit('updated', payload.state);
    }
}

function onKey(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void send();
    }
}

watch(
    () => [visibleMessages.value.length, props.state.progress, props.state.processing],
    async () => {
        if (stickToBottom.value) {
            await scrollToBottom();
        }
    },
);

onMounted(async () => {
    syncFromProps(true);
    await scrollToBottom();
    poll = setInterval(() => {
        if (props.state.processing) void refreshTail();
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

        <div
            ref="scroller"
            class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain px-3 py-3 sm:px-4"
            @scroll="onScroll"
        >
            <div v-if="loadingOlder" class="flex justify-center py-1">
                <Icon icon="heroicons:arrow-path" class="size-4 animate-spin text-muted-foreground" />
            </div>
            <p
                v-else-if="hasOlder"
                class="text-center text-[0.65rem] text-muted-foreground"
            >
                Scroll up for older messages
            </p>

            <p v-if="visibleMessages.length === 0" class="text-sm text-muted-foreground">
                Ask {{ state.employee.name }} to check status, find offers, create campaigns, or plan content.
            </p>

            <template v-for="item in timeline" :key="item.key">
                <div
                    v-if="item.kind === 'date'"
                    class="flex items-center gap-2 py-1"
                >
                    <div class="h-px flex-1 bg-border/70" />
                    <span class="shrink-0 rounded-full bg-muted/60 px-2.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-muted-foreground">
                        {{ item.label }}
                    </span>
                    <div class="h-px flex-1 bg-border/70" />
                </div>

                <div
                    v-else
                    class="flex w-full gap-2"
                    :class="item.message.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <EmployeeAvatar
                        v-if="item.message.role === 'assistant'"
                        size="size-8"
                        class="mt-0.5 shrink-0"
                        :src="state.employee.avatar_url"
                        :alt="state.employee.name"
                    />
                    <div class="max-w-[min(85%,28rem)] space-y-1">
                        <div
                            class="rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed break-words"
                            :class="
                                item.message.role === 'user'
                                    ? 'chat-bubble-user fill-brand-gradient border border-blue-400/20 text-white shadow-[var(--brand-gradient-shadow)]'
                                    : 'chat-bubble-assistant border border-border/60 bg-muted/20 text-foreground'
                            "
                        >
                            <div class="chat-message-body" v-html="renderContent(item.message.content)" />
                        </div>
                        <p
                            class="px-1 text-[11px] text-muted-foreground"
                            :class="item.message.role === 'user' ? 'text-right' : ''"
                        >
                            <span v-if="item.message.role === 'user'" class="font-medium tracking-wide">WEB</span>
                            <span v-else>{{ state.employee.name }}</span>
                            <span v-if="item.message.created_at"> · {{ formatTime(item.message.created_at) }}</span>
                        </p>
                    </div>
                </div>
            </template>

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
