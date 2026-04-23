<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    funnel: {
        name: string;
        settings?: {
            webinar_title?: string | null;
            webinar_description?: string | null;
            video_url?: string | null;
        } | null;
    };
    chatMessages: Array<{
        id: number;
        author_name: string;
        participant_role?: string;
        message: string;
    }>;
    chatEndpoints: {
        fetch: string;
        send: string;
    };
}>();

const messages = ref(props.chatMessages ?? []);
const messageInput = ref('');
const sending = ref(false);
const chatBody = ref<HTMLElement | null>(null);
let poller: number | undefined;

const webinarTitle = computed(() => props.funnel.settings?.webinar_title ?? `${props.funnel.name} Webinar`);
const webinarDesc = computed(() => props.funnel.settings?.webinar_description ?? 'Watch the exclusive webinar training below.');

const scrollToBottom = async (): Promise<void> => {
    await nextTick();

    if (chatBody.value) {
        chatBody.value.scrollTop = chatBody.value.scrollHeight;
    }
};

const fetchMessages = async (): Promise<void> => {
    const response = await fetch(props.chatEndpoints.fetch, { headers: { Accept: 'application/json' } });
    const data = await response.json();
    const incoming = data.messages ?? [];
    const hadNew = incoming.length > messages.value.length;

    messages.value = incoming;

    if (hadNew) {
        scrollToBottom();
    }
};

const sendMessage = async (): Promise<void> => {
    const msg = messageInput.value.trim();

    if (!msg || sending.value) {
        return;
    }

    sending.value = true;

    try {
        await fetch(props.chatEndpoints.send, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ message: msg }),
        });
        messageInput.value = '';
        await fetchMessages();
        scrollToBottom();
    } finally {
        sending.value = false;
    }
};

const handleKeydown = (e: KeyboardEvent): void => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
};

onMounted(() => {
    scrollToBottom();
    poller = window.setInterval(fetchMessages, 4000);
});

onUnmounted(() => {
    if (poller) {
        window.clearInterval(poller);
    }
});
</script>

<template>
    <Head :title="webinarTitle" />

    <!-- Dark webinar room -->
    <div class="min-h-screen bg-[#0a0f1e] text-white flex flex-col">

        <!-- Top bar -->
        <header class="flex items-center justify-between border-b border-white/10 bg-[#0d1424] px-4 py-2.5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/20">
                    <Icon icon="heroicons:video-camera" class="size-4 text-primary" style="color:#40E0D0" />
                </div>
                <span class="truncate text-sm font-semibold text-white/90">{{ webinarTitle }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <!-- LIVE badge -->
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/20 border border-rose-500/30 px-2.5 py-1 text-xs font-bold text-rose-400 uppercase tracking-wider">
                    <span class="size-1.5 rounded-full bg-rose-500 animate-pulse" />
                    Live
                </span>
            </div>
        </header>

        <!-- Main content -->
        <div class="flex flex-1 flex-col lg:flex-row overflow-hidden">

            <!-- ── Video section ── -->
            <main class="flex flex-col gap-4 p-4 lg:flex-1 lg:p-6">
                <!-- Video player -->
                <div class="aspect-video w-full overflow-hidden rounded-xl bg-black ring-1 ring-white/10 shadow-2xl">
                    <iframe
                        v-if="funnel.settings?.video_url"
                        class="h-full w-full"
                        :src="funnel.settings.video_url"
                        allowfullscreen
                        allow="autoplay; fullscreen"
                    />
                    <div
                        v-else
                        class="flex h-full flex-col items-center justify-center gap-3 text-white/40"
                    >
                        <Icon icon="heroicons:video-camera-slash" class="size-14" />
                        <p class="text-sm">Video not configured yet</p>
                    </div>
                </div>

                <!-- Webinar info below video -->
                <div class="space-y-1.5">
                    <h1 class="text-lg font-bold text-white leading-snug">{{ webinarTitle }}</h1>
                    <p class="text-sm text-white/50 leading-relaxed">{{ webinarDesc }}</p>
                </div>

                <!-- Info strip -->
                <div class="flex flex-wrap items-center gap-4 text-xs text-white/40">
                    <span class="flex items-center gap-1.5">
                        <Icon icon="heroicons:users" class="size-4" />
                        Live now
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Icon icon="heroicons:shield-check" class="size-4" />
                        Private &amp; secure
                    </span>
                    <span class="flex items-center gap-1.5">
                        <Icon icon="heroicons:clock" class="size-4" />
                        Limited availability
                    </span>
                </div>
            </main>

            <!-- ── Chat sidebar ── -->
            <aside class="flex flex-col border-t border-white/10 lg:border-t-0 lg:border-l lg:w-[360px] lg:h-[calc(100vh-48px)]">
                <!-- Chat header -->
                <div class="flex items-center justify-between border-b border-white/10 bg-[#0d1424] px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-4 text-white/60" />
                        <span class="text-sm font-semibold text-white/80">Webinar Chat</span>
                    </div>
                    <span class="text-xs text-white/40">{{ messages.length }} messages</span>
                </div>

                <!-- Messages -->
                <div
                    ref="chatBody"
                    class="flex-1 overflow-y-auto p-3 space-y-2 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10"
                    style="min-height: 200px; max-height: 60vh"
                >
                    <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full gap-2 text-white/30 py-10">
                        <Icon icon="heroicons:chat-bubble-oval-left" class="size-8" />
                        <p class="text-xs">Be the first to say hello!</p>
                    </div>

                    <div
                        v-for="msg in messages"
                        :key="msg.id"
                        class="flex gap-2.5"
                        :class="msg.participant_role === 'owner' ? 'flex-row-reverse' : ''"
                    >
                        <!-- Avatar -->
                        <div
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[0.55rem] font-bold mt-0.5"
                            :class="msg.participant_role === 'owner'
                                ? 'bg-primary/30 text-primary'
                                : 'bg-white/10 text-white/60'"
                            :style="msg.participant_role === 'owner' ? 'color:#40E0D0' : ''"
                        >
                            {{ msg.author_name.charAt(0).toUpperCase() }}
                        </div>

                        <!-- Bubble -->
                        <div class="max-w-[80%]" :class="msg.participant_role === 'owner' ? 'items-end flex flex-col' : ''">
                            <p
                                class="text-[0.6rem] font-semibold mb-0.5"
                                :class="msg.participant_role === 'owner' ? 'text-right' : ''"
                                :style="msg.participant_role === 'owner' ? 'color:#40E0D0' : 'color: rgba(255,255,255,0.5)'"
                            >
                                {{ msg.author_name }}
                                <span v-if="msg.participant_role === 'owner'" class="ml-1 opacity-70">• Host</span>
                            </p>
                            <div
                                class="rounded-xl px-3 py-2 text-sm leading-relaxed"
                                :class="msg.participant_role === 'owner'
                                    ? 'rounded-tr-sm text-right'
                                    : 'rounded-tl-sm bg-white/8 text-white/85'"
                                :style="msg.participant_role === 'owner' ? 'background: rgba(64,224,208,0.15); color: rgba(255,255,255,0.9)' : ''"
                            >
                                {{ msg.message }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="border-t border-white/10 bg-[#0d1424] p-3">
                    <div class="flex gap-2">
                        <textarea
                            v-model="messageInput"
                            rows="2"
                            placeholder="Type a message… (Enter to send)"
                            class="flex-1 resize-none rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30 focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/30 transition-colors"
                            style="color:white"
                            @keydown="handleKeydown"
                        />
                        <button
                            class="flex shrink-0 size-[calc(2rem+4px*2+2px*2)] items-center justify-center self-end rounded-lg transition-all disabled:opacity-40"
                            :class="messageInput.trim() && !sending
                                ? 'bg-primary text-primary-foreground hover:opacity-90 shadow-lg shadow-primary/20'
                                : 'bg-white/5 text-white/30 cursor-not-allowed'"
                            style="background: #40E0D0; color: #0a0f1e"
                            :disabled="sending || !messageInput.trim()"
                            @click="sendMessage"
                        >
                            <Icon v-if="sending" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                            <Icon v-else icon="heroicons:paper-airplane" class="size-4" />
                        </button>
                    </div>
                    <p class="mt-1.5 text-[0.6rem] text-white/25 text-center">Press Enter to send · Shift+Enter for new line</p>
                </div>
            </aside>
        </div>
    </div>
</template>
