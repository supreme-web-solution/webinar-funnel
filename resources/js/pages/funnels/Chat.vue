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

    <!-- Full-viewport chat shell -->
    <div class="flex flex-col" style="height: calc(100vh - 56px)">

        <!-- ── Top nav bar ── -->
        <div class="flex items-center justify-between gap-3 border-b border-border/60 bg-background px-4 py-2.5 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <Button as-child variant="ghost" size="sm" class="shrink-0 h-8 w-8 p-0 text-muted-foreground">
                    <Link :href="`/funnels/${funnel.id}/edit`">
                        <Icon icon="heroicons:arrow-left" class="size-4" />
                    </Link>
                </Button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-foreground truncate">{{ funnel.name }}</span>
                        <Badge
                            class="text-[0.6rem] capitalize px-2 py-0.5 shrink-0"
                            :class="funnel.status === 'published'
                                ? 'bg-emerald-100 text-emerald-700 border-emerald-200'
                                : 'bg-amber-50 text-amber-700 border-amber-200'"
                        >
                            {{ funnel.status }}
                        </Badge>
                    </div>
                    <p class="text-[0.65rem] text-muted-foreground">Chat Manager · {{ conversations.length }} conversation{{ conversations.length !== 1 ? 's' : '' }}</p>
                </div>
            </div>
            <Button as-child variant="outline" size="sm" class="shrink-0 h-8 text-xs gap-1.5">
                <a :href="publicLinks.webinar" target="_blank" rel="noopener noreferrer">
                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                    Webinar
                </a>
            </Button>
        </div>

        <!-- ── Two-pane layout ── -->
        <div class="flex flex-1 overflow-hidden">

            <!-- ════════════ LEFT SIDEBAR ════════════ -->
            <div class="flex w-72 shrink-0 flex-col border-r border-border/60 bg-muted/20">

                <!-- Sidebar header + search -->
                <div class="border-b border-border/60 p-3 space-y-2">
                    <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wider px-1">Conversations</p>
                    <div class="relative">
                        <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground pointer-events-none" />
                        <Input
                            v-model="sidebarSearch"
                            placeholder="Search attendees…"
                            class="pl-8 h-8 text-xs bg-background"
                        />
                    </div>
                </div>

                <!-- Conversation list -->
                <div class="flex-1 overflow-y-auto">

                    <!-- Empty state -->
                    <div v-if="conversations.length === 0" class="flex flex-col items-center justify-center h-full gap-2 text-muted-foreground px-4 text-center py-12">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-10 opacity-25" />
                        <p class="text-xs font-medium">No conversations yet</p>
                        <p class="text-[0.65rem] opacity-70">Share your webinar link to start getting attendees.</p>
                    </div>

                    <!-- No filter match -->
                    <div v-else-if="filteredConvos.length === 0" class="flex flex-col items-center py-10 gap-2 text-muted-foreground">
                        <Icon icon="heroicons:magnifying-glass" class="size-6 opacity-30" />
                        <p class="text-xs">No matches</p>
                    </div>

                    <!-- List items -->
                    <button
                        v-for="convo in filteredConvos"
                        :key="convo.conversation_key"
                        class="flex w-full items-start gap-2.5 px-3 py-3 text-left transition-colors border-b border-border/30 last:border-0 hover:bg-muted/60 relative"
                        :class="activeKey === convo.conversation_key
                            ? 'bg-primary/8 border-l-[3px] border-l-primary pl-[9px]'
                            : 'border-l-[3px] border-l-transparent'"
                        @click="selectConvo(convo.conversation_key)"
                    >
                        <!-- Avatar -->
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold mt-0.5"
                            :class="activeKey === convo.conversation_key
                                ? 'bg-primary/20 text-primary'
                                : 'bg-muted-foreground/10 text-muted-foreground'"
                        >
                            {{ initials(convo.attendee_name) }}
                        </div>

                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <div class="flex items-baseline justify-between gap-1">
                                <p class="text-xs font-semibold text-foreground truncate">{{ convo.attendee_name }}</p>
                                <span class="text-[0.58rem] text-muted-foreground shrink-0">
                                    {{ fmtShort(undefined) }}
                                </span>
                            </div>
                            <p class="text-[0.65rem] text-muted-foreground/70 truncate mt-0.5">
                                {{ convo.latest_message ?? 'No messages yet' }}
                            </p>
                        </div>

                        <!-- Unread count badge -->
                        <span
                            v-if="convo.message_count > 0"
                            class="absolute right-2.5 bottom-3 flex size-4 items-center justify-center rounded-full bg-primary/15 text-[0.55rem] font-bold text-primary"
                        >
                            {{ convo.message_count > 99 ? '99+' : convo.message_count }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ════════════ RIGHT: CHAT PANEL ════════════ -->
            <div class="flex flex-1 flex-col overflow-hidden bg-background">

                <!-- ── No conversation selected ── -->
                <div v-if="!activeConvo" class="flex flex-1 flex-col items-center justify-center gap-3 text-muted-foreground">
                    <div class="flex size-16 items-center justify-center rounded-2xl bg-muted">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-8 opacity-40" />
                    </div>
                    <p class="text-sm font-medium text-foreground">Select a conversation</p>
                    <p class="text-xs max-w-xs text-center">Click an attendee on the left to open their chat thread.</p>
                </div>

                <!-- ── Active conversation ── -->
                <template v-else>

                    <!-- Chat header -->
                    <div class="flex items-center gap-3 border-b border-border/60 bg-muted/20 px-4 py-2.5 shrink-0">
                        <!-- Avatar -->
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-bold text-primary">
                            {{ initials(activeConvo.attendee_name) }}
                        </div>
                        <!-- Info -->
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ activeConvo.attendee_name }}</p>
                            <p class="text-[0.65rem] text-muted-foreground truncate">
                                {{ activeConvo.attendee_email ?? 'Anonymous' }}
                                <span class="mx-1 opacity-50">·</span>
                                {{ activeConvo.message_count }} message{{ activeConvo.message_count !== 1 ? 's' : '' }}
                            </p>
                        </div>

                        <!-- Delete button -->
                        <div class="flex items-center gap-2 shrink-0">
                            <template v-if="confirmDelete">
                                <span class="text-xs text-muted-foreground">Delete this chat?</span>
                                <Button
                                    size="sm"
                                    class="h-7 px-2.5 text-xs bg-rose-600 hover:bg-rose-700 text-white gap-1"
                                    :disabled="deleting"
                                    @click="doDeleteConvo"
                                >
                                    <Icon v-if="deleting" icon="heroicons:arrow-path" class="size-3 animate-spin" />
                                    <Icon v-else icon="heroicons:trash" class="size-3" />
                                    {{ deleting ? 'Deleting…' : 'Confirm' }}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="h-7 px-2 text-xs"
                                    @click="confirmDelete = false"
                                >
                                    Cancel
                                </Button>
                            </template>
                            <Button
                                v-else
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-muted-foreground hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                title="Delete conversation"
                                @click="confirmDelete = true"
                            >
                                <Icon icon="heroicons:trash" class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Messages area -->
                    <div
                        ref="messagesEl"
                        class="flex-1 overflow-y-auto px-4 py-4 space-y-1"
                        style="background: radial-gradient(ellipse at top, hsl(174 72% 56% / 0.03) 0%, transparent 60%)"
                    >
                        <!-- Empty thread -->
                        <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground py-12">
                            <Icon icon="heroicons:chat-bubble-oval-left" class="size-8 opacity-25" />
                            <p class="text-xs">No messages yet in this conversation.</p>
                        </div>

                        <!-- Message bubbles -->
                        <template v-else>
                            <div
                                v-for="(msg, idx) in messages"
                                :key="msg.id"
                                class="flex gap-2"
                                :class="msg.participant_role === 'owner' ? 'flex-row-reverse' : 'flex-row'"
                            >
                                <!-- Avatar — only show if different role from prev message -->
                                <div
                                    v-if="idx === 0 || messages[idx - 1].participant_role !== msg.participant_role"
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full text-[0.55rem] font-bold self-end mb-1"
                                    :class="msg.participant_role === 'owner'
                                        ? 'bg-primary/20 text-primary'
                                        : 'bg-muted text-muted-foreground'"
                                >
                                    {{ initials(msg.author_name) }}
                                </div>
                                <div v-else class="size-7 shrink-0" />

                                <!-- Bubble group -->
                                <div
                                    class="flex flex-col max-w-[65%]"
                                    :class="msg.participant_role === 'owner' ? 'items-end' : 'items-start'"
                                >
                                    <!-- Sender name — first in a group -->
                                    <p
                                        v-if="idx === 0 || messages[idx - 1].participant_role !== msg.participant_role"
                                        class="text-[0.6rem] font-semibold mb-0.5 px-1"
                                        :class="msg.participant_role === 'owner' ? 'text-primary text-right' : 'text-muted-foreground'"
                                    >
                                        {{ msg.participant_role === 'owner' ? 'You (Host)' : msg.author_name }}
                                    </p>

                                    <!-- Bubble -->
                                    <div
                                        class="rounded-2xl px-3.5 py-2 text-sm leading-relaxed shadow-sm"
                                        :class="msg.participant_role === 'owner'
                                            ? 'rounded-tr-sm bg-primary text-primary-foreground'
                                            : 'rounded-tl-sm bg-muted text-foreground'"
                                        :style="msg.participant_role === 'owner' ? 'background:#40E0D0; color:#0f172a' : ''"
                                    >
                                        {{ msg.message }}
                                    </div>

                                    <!-- Timestamp -->
                                    <p
                                        v-if="msg.created_at"
                                        class="text-[0.55rem] text-muted-foreground/60 mt-0.5 px-1"
                                    >
                                        {{ fmtTime(msg.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ── Reply input ── -->
                    <div class="border-t border-border/60 bg-muted/10 px-4 py-3 shrink-0">
                        <div class="flex items-end gap-2">
                            <!-- Host avatar -->
                            <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/20 text-[0.6rem] font-bold text-primary self-end mb-0.5" style="color:#40E0D0">
                                H
                            </div>

                            <!-- Textarea -->
                            <div class="flex-1 relative">
                                <textarea
                                    v-model="replyText"
                                    rows="1"
                                    placeholder="Reply as host…"
                                    class="w-full resize-none rounded-2xl border border-input bg-background px-4 py-2.5 pr-12 text-sm leading-5 focus:outline-none focus:ring-2 focus:ring-ring/40 transition-all placeholder:text-muted-foreground"
                                    style="min-height: 42px; max-height: 120px; overflow-y: auto; field-sizing: content"
                                    @keydown="handleKeydown"
                                />
                                <!-- Send button inside textarea -->
                                <button
                                    class="absolute right-2 bottom-2 flex size-7 items-center justify-center rounded-full transition-all disabled:opacity-30"
                                    :class="replyText.trim() && !sending
                                        ? 'shadow-sm hover:scale-105'
                                        : 'cursor-not-allowed'"
                                    :style="replyText.trim() && !sending
                                        ? 'background:#40E0D0; color:#0f172a'
                                        : 'background: hsl(var(--muted)); color: hsl(var(--muted-foreground))'"
                                    :disabled="sending || !replyText.trim()"
                                    @click="sendReply"
                                >
                                    <Icon
                                        v-if="sending"
                                        icon="heroicons:arrow-path"
                                        class="size-3.5 animate-spin"
                                    />
                                    <Icon v-else icon="heroicons:paper-airplane" class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <p class="mt-1.5 text-[0.58rem] text-muted-foreground/50 ml-10">
                            Enter to send · Shift + Enter for new line
                        </p>
                    </div>

                </template>
            </div>
        </div>
    </div>
</template>
