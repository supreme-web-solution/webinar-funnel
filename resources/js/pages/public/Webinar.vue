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
            webinar_cta_label?: string | null;
            webinar_cta_url?: string | null;
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

// Simulated live viewer count
const viewerCount = ref(Math.floor(Math.random() * 180) + 120);
let viewerTimer: number | undefined;

const webinarTitle = computed(() => props.funnel.settings?.webinar_title ?? `${props.funnel.name} Webinar`);
const webinarDesc = computed(() => props.funnel.settings?.webinar_description ?? 'Watch the exclusive webinar training below.');
const webinarCtaLabel = computed(() => props.funnel.settings?.webinar_cta_label?.trim() || 'Claim Your Spot');
const webinarCtaUrl = computed(() => props.funnel.settings?.webinar_cta_url?.trim() || '');
const hasWebinarCta = computed(() => webinarCtaUrl.value.length > 0);

/** Converts any YouTube or Vimeo URL variant into the proper embed URL */
const videoEmbedUrl = computed((): string | null => {
    const raw = props.funnel.settings?.video_url?.trim();
    if (!raw) return null;

    // Already a proper embed URL — pass through
    if (raw.includes('youtube.com/embed/') || raw.includes('player.vimeo.com/video/')) {
        return raw;
    }

    // youtu.be/ID short URL
    const youtuBe = raw.match(/youtu\.be\/([a-zA-Z0-9_-]+)/);
    if (youtuBe) {
        return `https://www.youtube.com/embed/${youtuBe[1]}?rel=0&modestbranding=1&color=white`;
    }

    // youtube.com/watch?v=ID  (may have extra params)
    const ytWatch = raw.match(/[?&]v=([a-zA-Z0-9_-]+)/);
    if (raw.includes('youtube.com') && ytWatch) {
        return `https://www.youtube.com/embed/${ytWatch[1]}?rel=0&modestbranding=1&color=white`;
    }

    // vimeo.com/ID  (numeric ID, not /channels/ etc.)
    const vimeoMatch = raw.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
        return `https://player.vimeo.com/video/${vimeoMatch[1]}?title=0&byline=0&portrait=0&color=40E0D0`;
    }

    // Unknown — return as-is and hope for the best
    return raw;
});

const videoProvider = computed((): 'youtube' | 'vimeo' | 'unknown' => {
    const raw = props.funnel.settings?.video_url?.trim() ?? '';
    if (raw.includes('youtube') || raw.includes('youtu.be')) return 'youtube';
    if (raw.includes('vimeo')) return 'vimeo';
    return 'unknown';
});

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
    // Simulate live viewer count fluctuation
    viewerTimer = window.setInterval(() => {
        const delta = Math.floor(Math.random() * 7) - 3;
        viewerCount.value = Math.max(80, viewerCount.value + delta);
    }, 5000);
});

onUnmounted(() => {
    if (poller) window.clearInterval(poller);
    if (viewerTimer) window.clearInterval(viewerTimer);
});
</script>

<template>
    <Head :title="webinarTitle" />

    <!-- Locked to viewport — no page scroll -->
    <div class="h-screen overflow-hidden bg-[#0a0f1e] text-white flex flex-col">

        <!-- ── Top bar ── -->
        <header class="flex shrink-0 items-center justify-between border-b border-white/10 bg-[#0d1424] px-4 py-2">

            <!-- Left: icon + title -->
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-md" style="background:rgba(64,224,208,0.15)">
                    <Icon icon="heroicons:video-camera" class="size-4" style="color:#40E0D0" />
                </div>
                <span class="truncate text-sm font-semibold text-white/90">{{ webinarTitle }}</span>
            </div>

            <!-- Right: CTA + meta badges -->
            <div class="flex items-center gap-2.5 shrink-0">
                <!-- Primary CTA button — visible at top always -->
                <a
                    v-if="hasWebinarCta"
                    :href="webinarCtaUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hidden sm:inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold shrink-0 cta-pulse"
                    style="background:#40E0D0;color:#0a0f1e;"
                >
                    <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                    {{ webinarCtaLabel }}
                </a>

                <!-- Viewer count -->
                <span class="hidden md:inline-flex items-center gap-1 text-xs text-white/45">
                    <Icon icon="heroicons:eye" class="size-3.5" />
                    {{ viewerCount.toLocaleString() }} watching
                </span>

                <!-- LIVE badge -->
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/20 border border-rose-500/30 px-2.5 py-1 text-[11px] font-bold text-rose-400 uppercase tracking-wider">
                    <span class="size-1.5 rounded-full bg-rose-500 animate-pulse" />
                    Live
                </span>

                <!-- Provider badge -->
                <span
                    v-if="videoProvider !== 'unknown'"
                    class="hidden sm:inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                    :class="videoProvider === 'youtube'
                        ? 'bg-red-600/20 border border-red-500/30 text-red-400'
                        : 'bg-sky-600/20 border border-sky-500/30 text-sky-400'"
                >
                    <Icon :icon="videoProvider === 'youtube' ? 'mdi:youtube' : 'mdi:vimeo'" class="size-3.5" />
                    {{ videoProvider === 'youtube' ? 'YouTube' : 'Vimeo' }}
                </span>
            </div>
        </header>

        <!-- ── Body: video + chat, fills remaining height ── -->
        <div class="flex flex-1 flex-col lg:flex-row overflow-hidden min-h-0">

            <!-- ── Video column ── -->
            <main class="flex flex-col flex-1 min-h-0 p-3 gap-2">

                <!-- CTA strip on mobile (header CTA is hidden on small screens) -->
                <a
                    v-if="hasWebinarCta"
                    :href="webinarCtaUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sm:hidden flex items-center justify-center gap-2 rounded-lg py-2.5 text-sm font-bold shrink-0 cta-pulse"
                    style="background:#40E0D0;color:#0a0f1e;"
                >
                    <Icon icon="heroicons:rocket-launch" class="size-4" />
                    {{ webinarCtaLabel }}
                </a>

                <!-- Video — fills all remaining vertical space -->
                <div
                    class="relative flex-1 min-h-0 overflow-hidden rounded-xl bg-black"
                    style="box-shadow: 0 0 0 1px rgba(255,255,255,0.07), 0 20px 50px rgba(0,0,0,0.6);"
                >
                    <iframe
                        v-if="videoEmbedUrl"
                        class="absolute inset-0 h-full w-full"
                        :src="videoEmbedUrl"
                        frameborder="0"
                        allowfullscreen
                        allow="autoplay; fullscreen; picture-in-picture"
                        referrerpolicy="strict-origin-when-cross-origin"
                    />
                    <div v-else class="flex h-full flex-col items-center justify-center gap-3 text-white/40">
                        <Icon icon="heroicons:video-camera-slash" class="size-14" />
                        <p class="text-sm">Video not configured yet</p>
                    </div>

                    <!-- Floating LIVE + viewer overlay on video -->
                    <div v-if="videoEmbedUrl" class="pointer-events-none absolute left-3 top-3 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/85 px-2.5 py-1 text-[11px] font-bold text-white uppercase tracking-wider backdrop-blur-sm">
                            <span class="size-1.5 rounded-full bg-white animate-pulse" />
                            Live
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-black/60 px-2.5 py-1 text-[11px] text-white/80 backdrop-blur-sm">
                            <Icon icon="heroicons:eye" class="size-3" />
                            {{ viewerCount.toLocaleString() }}
                        </span>
                    </div>
                </div>

                <!-- Compact info strip below video -->
                <div class="flex shrink-0 items-center gap-4 text-[11px] text-white/35 px-0.5">
                    <span class="flex items-center gap-1">
                        <Icon icon="heroicons:users" class="size-3.5" />
                        Live now
                    </span>
                    <span class="flex items-center gap-1">
                        <Icon icon="heroicons:shield-check" class="size-3.5" />
                        Private &amp; secure
                    </span>
                    <span class="flex items-center gap-1">
                        <Icon icon="heroicons:clock" class="size-3.5" />
                        Limited availability
                    </span>
                </div>
            </main>

            <!-- ── Chat sidebar ── -->
            <aside class="flex flex-col border-t border-white/10 lg:border-t-0 lg:border-l lg:w-[360px] min-h-0 overflow-hidden">
                <!-- Chat header -->
                <div class="flex shrink-0 items-center justify-between border-b border-white/10 bg-[#0d1424] px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-4 text-white/60" />
                        <span class="text-sm font-semibold text-white/80">Webinar Chat</span>
                    </div>
                    <span class="text-xs text-white/40">{{ messages.length }} messages</span>
                </div>

                <!-- Pinned CTA in chat -->
                <div
                    v-if="hasWebinarCta"
                    class="shrink-0 border-b border-primary/20 bg-primary/10 px-3 py-2"
                >
                    <a
                        :href="webinarCtaUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center justify-between gap-2 rounded-lg border border-primary/30 bg-[#0d1424] px-3 py-2 text-xs"
                    >
                        <span class="flex items-center gap-1.5 text-white/85">
                            <Icon icon="heroicons:megaphone" class="size-3.5" style="color:#40E0D0" />
                            {{ webinarCtaLabel }}
                        </span>
                        <span class="inline-flex items-center gap-1 font-semibold" style="color:#40E0D0">
                            Open
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        </span>
                    </a>
                </div>

                <!-- Messages -->
                <div
                    ref="chatBody"
                    class="flex-1 min-h-0 overflow-y-auto p-3 space-y-2 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-white/10"
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

<style scoped>
@keyframes cta-blink {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(64, 224, 208, 0.7), 0 0 12px rgba(64, 224, 208, 0.4);
        opacity: 1;
    }
    50% {
        box-shadow: 0 0 0 8px rgba(64, 224, 208, 0), 0 0 24px rgba(64, 224, 208, 0.15);
        opacity: 0.82;
    }
}

.cta-pulse {
    animation: cta-blink 1.6s ease-in-out infinite;
}

.cta-pulse:hover {
    animation: none;
    opacity: 0.9;
    box-shadow: 0 0 20px rgba(64, 224, 208, 0.5);
}
</style>
