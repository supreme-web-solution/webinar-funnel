<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/* ── Types ───────────────────────────────────────────────── */
interface Conversation {
    conversation_key: string;
    attendee_name: string;
    attendee_email?: string | null;
    latest_message?: string | null;
    message_count: number;
    latest_id?: number;
}

interface Message {
    id: number;
    author_name: string;
    participant_role: string;
    attendee_name?: string | null;
    attendee_email?: string | null;
    message: string;
    created_at?: string;
}

/* ── Props ───────────────────────────────────────────────── */
const props = defineProps<{
    funnel: { id: number; name: string; slug: string; status: string };
    conversations: Conversation[];
    publicLinks: { webinar: string };
}>();

/* ── State ───────────────────────────────────────────────── */
const conversations = ref<Conversation[]>(props.conversations ?? []);
const activeKey = ref<string>(props.conversations[0]?.conversation_key ?? '');
const messages = ref<Message[]>([]);
const replyText = ref('');
const sending = ref(false);
const deleting = ref(false);
const confirmDelete = ref(false);
const sidebarSearch = ref('');
const messagesEl = ref<HTMLElement | null>(null);
let poller: number | undefined;

/* ── Derived ─────────────────────────────────────────────── */
const activeConvo = computed(() =>
    conversations.value.find((c) => c.conversation_key === activeKey.value) ?? null,
);

const filteredConvos = computed(() => {
    const q = sidebarSearch.value.toLowerCase().trim();

    if (!q) {
        return conversations.value;
    }

    return conversations.value.filter(
        (c) =>
            c.attendee_name.toLowerCase().includes(q) ||
            (c.attendee_email ?? '').toLowerCase().includes(q),
    );
});

/* ── Helpers ─────────────────────────────────────────────── */
function initials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function fmtTime(iso?: string): string {
    if (!iso) {
        return '';
    }

    return new Date(iso).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

function fmtShort(iso?: string): string {
    if (!iso) {
        return '';
    }

    const d = new Date(iso);
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();

    return sameDay
        ? d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

const scrollToBottom = async (): Promise<void> => {
    await nextTick();

    if (messagesEl.value) {
        messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
    }
};

/* ── API calls ───────────────────────────────────────────── */
function csrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

const fetchConversations = async (): Promise<void> => {
    const res = await fetch(`/funnels/${props.funnel.id}/chat/conversations`, {
        headers: { Accept: 'application/json' },
    });

    if (!res.ok) {
        return;
    }

    const payload = await res.json();
    conversations.value = payload?.conversations ?? [];
};

const fetchMessages = async (): Promise<void> => {
    if (!activeKey.value) {
        messages.value = [];

        return;
    }

    const res = await fetch(
        `/funnels/${props.funnel.id}/chat/messages?conversation_key=${encodeURIComponent(activeKey.value)}`,
        { headers: { Accept: 'application/json' } },
    );
    const data = await res.json();
    const incoming: Message[] = data.messages ?? [];
    const hadNew = incoming.length > messages.value.length;

    messages.value = incoming;

    if (hadNew) {
        scrollToBottom();
    }
};

const selectConvo = (key: string): void => {
    activeKey.value = key;
    confirmDelete.value = false;
    messages.value = [];
    fetchMessages();
};

const sendReply = async (): Promise<void> => {
    const msg = replyText.value.trim();

    if (!msg || sending.value || !activeKey.value) {
        return;
    }

    sending.value = true;

    try {
        await fetch(`/funnels/${props.funnel.id}/chat/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ message: msg, conversation_key: activeKey.value }),
        });
        replyText.value = '';
        await fetchMessages();
        scrollToBottom();
    } finally {
        sending.value = false;
    }
};

const handleKeydown = (e: KeyboardEvent): void => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendReply();
    }
};

const doDeleteConvo = async (): Promise<void> => {
    if (!activeKey.value || deleting.value) {
        return;
    }

    deleting.value = true;

    try {
        await fetch(`/funnels/${props.funnel.id}/chat/conversations`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ conversation_key: activeKey.value }),
        });

        conversations.value = conversations.value.filter(
            (c) => c.conversation_key !== activeKey.value,
        );

        const next = conversations.value[0]?.conversation_key ?? '';

        activeKey.value = next;
        messages.value = [];
        confirmDelete.value = false;

        if (next) {
            fetchMessages();
        }
    } finally {
        deleting.value = false;
    }
};

/* ── Lifecycle ───────────────────────────────────────────── */
onMounted(() => {
    fetchMessages();
    scrollToBottom();
    poller = window.setInterval(() => {
        fetchConversations();
        fetchMessages();
    }, 3500);
});

onUnmounted(() => {
    if (poller) {
        window.clearInterval(poller);
    }
});
</script>

<template>
    <Head :title="`Chat — ${funnel.name}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-3 p-3 md:gap-4 md:p-4" style="height: calc(100vh - 56px)">

        <!-- Header -->
        <div class="shrink-0 rounded-xl border border-border/60 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/15 bg-blue-500/10">
                        <Icon icon="heroicons:chat-bubble-left-right" class="size-5 text-blue-600" />
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="truncate text-xl font-bold tracking-tight md:text-2xl">{{ funnel.name }}</h1>
                            <Badge
                                variant="outline"
                                class="capitalize text-[0.65rem]"
                                :class="funnel.status === 'published'
                                    ? 'border-blue-200 bg-blue-50 text-blue-700'
                                    : 'border-amber-200 bg-amber-50 text-amber-700'"
                            >
                                {{ funnel.status }}
                            </Badge>
                        </div>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            Chat manager · {{ conversations.length }} conversation{{ conversations.length !== 1 ? 's' : '' }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <Button as-child variant="brand-outline" size="sm">
                        <Link :href="`/funnels/${funnel.id}/edit`">
                            <Icon icon="heroicons:arrow-left" class="size-3.5" />
                            Back to editor
                        </Link>
                    </Button>
                    <Button as-child variant="brand-outline" size="sm">
                        <a :href="publicLinks.webinar" target="_blank" rel="noopener noreferrer">
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                            Open webinar
                        </a>
                    </Button>
                </div>
            </div>
        </div>

        <!-- Chat workspace -->
        <div
            class="flex min-h-0 flex-1 overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm"
            style="min-height: 480px"
        >
            <!-- Left: conversation list -->
            <div class="flex w-72 shrink-0 flex-col border-r border-border/60 bg-muted/30">

                <div class="space-y-2 border-b border-border/60 p-3">
                    <p class="px-1 text-[0.65rem] font-semibold uppercase tracking-wider text-muted-foreground">Conversations</p>
                    <div class="relative">
                        <Icon icon="heroicons:magnifying-glass" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="sidebarSearch"
                            placeholder="Search attendees…"
                            class="h-8 bg-white pl-8 text-xs"
                        />
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto">
                    <div v-if="conversations.length === 0" class="flex h-full flex-col items-center justify-center gap-2 px-4 py-12 text-center text-muted-foreground">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-10 opacity-25" />
                        <p class="text-xs font-medium text-foreground">No conversations yet</p>
                        <p class="text-[0.65rem]">Share your webinar link to start getting attendees.</p>
                    </div>

                    <div v-else-if="filteredConvos.length === 0" class="flex flex-col items-center gap-2 py-10 text-muted-foreground">
                        <Icon icon="heroicons:magnifying-glass" class="size-6 opacity-30" />
                        <p class="text-xs">No matches</p>
                    </div>

                    <button
                        v-for="convo in filteredConvos"
                        :key="convo.conversation_key"
                        class="relative flex w-full items-start gap-2.5 border-b border-border/30 px-3 py-3 text-left transition-colors last:border-0 hover:bg-white/80"
                        :class="activeKey === convo.conversation_key
                            ? 'border-l-[3px] border-l-blue-600 bg-blue-50/60 pl-[9px]'
                            : 'border-l-[3px] border-l-transparent'"
                        @click="selectConvo(convo.conversation_key)"
                    >
                        <div
                            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                            :class="activeKey === convo.conversation_key
                                ? 'bg-blue-500/15 text-blue-700'
                                : 'bg-muted text-muted-foreground'"
                        >
                            {{ initials(convo.attendee_name) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-1">
                                <p class="truncate text-xs font-semibold text-foreground">{{ convo.attendee_name }}</p>
                                <span class="shrink-0 text-[0.58rem] text-muted-foreground">
                                    {{ fmtShort(undefined) }}
                                </span>
                            </div>
                            <p class="mt-0.5 truncate text-[0.65rem] text-muted-foreground">
                                {{ convo.latest_message ?? 'No messages yet' }}
                            </p>
                        </div>

                        <span
                            v-if="convo.message_count > 0"
                            class="absolute bottom-3 right-2.5 flex size-4 items-center justify-center rounded-full bg-brand-gradient text-[0.55rem] font-bold text-white"
                        >
                            {{ convo.message_count > 99 ? '99+' : convo.message_count }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- Right: active thread -->
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden bg-white">

                <div v-if="!activeConvo" class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground">
                    <div class="flex size-16 items-center justify-center rounded-2xl border border-blue-500/15 bg-blue-500/10">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-8 text-blue-600/60" />
                    </div>
                    <p class="text-sm font-medium text-foreground">Select a conversation</p>
                    <p class="max-w-xs text-center text-xs">Click an attendee on the left to open their chat thread.</p>
                </div>

                <template v-else>
                    <!-- Thread header -->
                    <div class="flex shrink-0 items-center gap-3 border-b border-border/60 bg-muted/20 px-4 py-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-500/15 text-xs font-bold text-blue-700">
                            {{ initials(activeConvo.attendee_name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-foreground">{{ activeConvo.attendee_name }}</p>
                            <p class="truncate text-[0.65rem] text-muted-foreground">
                                {{ activeConvo.attendee_email ?? 'Anonymous' }}
                                <span class="mx-1 opacity-50">·</span>
                                {{ activeConvo.message_count }} message{{ activeConvo.message_count !== 1 ? 's' : '' }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <template v-if="confirmDelete">
                                <span class="text-xs text-muted-foreground">Delete this chat?</span>
                                <Button
                                    size="sm"
                                    class="h-7 gap-1 bg-rose-600 px-2.5 text-xs text-white hover:bg-rose-700"
                                    :disabled="deleting"
                                    @click="doDeleteConvo"
                                >
                                    <Icon v-if="deleting" icon="heroicons:arrow-path" class="size-3 animate-spin" />
                                    <Icon v-else icon="heroicons:trash" class="size-3" />
                                    {{ deleting ? 'Deleting…' : 'Confirm' }}
                                </Button>
                                <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="confirmDelete = false">
                                    Cancel
                                </Button>
                            </template>
                            <Button
                                v-else
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-muted-foreground transition-colors hover:bg-rose-50 hover:text-rose-600"
                                title="Delete conversation"
                                @click="confirmDelete = true"
                            >
                                <Icon icon="heroicons:trash" class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div
                        ref="messagesEl"
                        class="flex-1 space-y-1 overflow-y-auto px-4 py-4"
                        style="background: radial-gradient(ellipse at top, hsl(174 72% 56% / 0.04) 0%, transparent 60%)"
                    >
                        <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-2 py-12 text-muted-foreground">
                            <Icon icon="heroicons:chat-bubble-oval-left" class="size-8 opacity-25" />
                            <p class="text-xs">No messages yet in this conversation.</p>
                        </div>

                        <template v-else>
                            <div
                                v-for="(msg, idx) in messages"
                                :key="msg.id"
                                class="flex gap-2"
                                :class="msg.participant_role === 'owner' ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <div
                                    v-if="idx === 0 || messages[idx - 1].participant_role !== msg.participant_role"
                                    class="mb-1 flex size-7 shrink-0 items-center justify-center self-end rounded-full text-[0.55rem] font-bold"
                                    :class="msg.participant_role === 'owner'
                                        ? 'bg-blue-500/20 text-blue-700'
                                        : 'bg-muted text-muted-foreground'"
                                >
                                    {{ initials(msg.author_name) }}
                                </div>
                                <div v-else class="size-7 shrink-0" />

                                <div
                                    class="flex max-w-[65%] flex-col"
                                    :class="msg.participant_role === 'owner' ? 'items-end' : 'items-start'"
                                >
                                    <p
                                        v-if="idx === 0 || messages[idx - 1].participant_role !== msg.participant_role"
                                        class="mb-0.5 px-1 text-[0.6rem] font-semibold"
                                        :class="msg.participant_role === 'owner' ? 'text-right text-blue-700' : 'text-muted-foreground'"
                                    >
                                        {{ msg.participant_role === 'owner' ? 'You (Host)' : msg.author_name }}
                                    </p>

                                    <div
                                        class="rounded-2xl px-3.5 py-2 text-sm leading-relaxed shadow-sm"
                                        :class="msg.participant_role === 'owner'
                                            ? 'rounded-tr-sm bg-blue-500 text-slate-900'
                                            : 'rounded-tl-sm border border-border/60 bg-muted/50 text-foreground'"
                                    >
                                        {{ msg.message }}
                                    </div>

                                    <p v-if="msg.created_at" class="mt-0.5 px-1 text-[0.55rem] text-muted-foreground">
                                        {{ fmtTime(msg.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Reply -->
                    <div class="shrink-0 border-t border-border/60 bg-muted/20 px-4 py-3">
                        <div class="flex items-end gap-2">
                            <div class="mb-0.5 flex size-8 shrink-0 items-center justify-center self-end rounded-full bg-blue-500/15 text-[0.6rem] font-bold text-blue-700">
                                H
                            </div>

                            <div class="relative flex-1">
                                <textarea
                                    v-model="replyText"
                                    rows="1"
                                    placeholder="Reply as host…"
                                    class="w-full resize-none rounded-2xl border border-border/60 bg-white px-4 py-2.5 pr-12 text-sm leading-5 transition-all placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                                    style="min-height: 42px; max-height: 120px; overflow-y: auto; field-sizing: content"
                                    @keydown="handleKeydown"
                                />
                                <button
                                    class="absolute bottom-2 right-2 flex size-7 items-center justify-center rounded-full transition-all disabled:opacity-30"
                                    :class="replyText.trim() && !sending
                                        ? 'chip-brand-active'
                                        : 'cursor-not-allowed bg-muted text-muted-foreground'"
                                    :disabled="sending || !replyText.trim()"
                                    @click="sendReply"
                                >
                                    <Icon v-if="sending" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                    <Icon v-else icon="heroicons:paper-airplane" class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <p class="ml-10 mt-1.5 text-[0.58rem] text-muted-foreground">
                            Enter to send · Shift + Enter for new line
                        </p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>
