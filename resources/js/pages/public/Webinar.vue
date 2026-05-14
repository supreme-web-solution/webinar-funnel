<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const props = defineProps<{
    funnel: {
        name: string;
        slug: string;
        settings?: {
            webinar_title?: string | null;
            webinar_description?: string | null;
            video_url?: string | null;
            webinar_duration_seconds?: number | null;
            webinar_cta_label?: string | null;
            webinar_cta_url?: string | null;
            offers?: Array<{
                title: string;
                description?: string | null;
                cta_label: string;
                cta_url: string;
                placement: 'chat' | 'pinned' | 'popup';
                timing_seconds: number;
                enabled?: boolean;
            }> | null;
            exit_popup_enabled?: boolean;
            exit_popup_show_close?: boolean;
            exit_popup_title?: string | null;
            exit_popup_description?: string | null;
            exit_popup_cta_label?: string | null;
            exit_popup_cta_url?: string | null;
            redirect_enabled?: boolean;
            redirect_url?: string | null;
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
    analyticsEndpoint: string;
    chatRealtime?: {
        funnel_id: number;
        conversation_key: string;
    } | null;
}>();

const messages = ref(props.chatMessages ?? []);
const messageInput = ref('');
const sending = ref(false);
const chatBody = ref<HTMLElement | null>(null);
const iframeRef = ref<HTMLIFrameElement | null>(null);
let poller: number | undefined;
let echo: Echo<'reverb'> | null = null;
const soundEnabled = ref(false);
const startedAtMs = ref(Date.now());
const elapsedSeconds = ref(0);
let offerTimer: number | undefined;
let playbackKeepAliveTimer: number | undefined;
let analyticsHeartbeatTimer: number | undefined;
const dismissedPopupIds = ref<Set<number>>(new Set());
const exitPopupVisible = ref(false);
const exitPopupShownOnce = ref(false);
const lastMouseY = ref<number | null>(null);
const sentMilestones = ref<Set<string>>(new Set());
const sessionKey = ref('');
const redirectedAtEnd = ref(false);

/** YouTube/Vimeo: top/bottom black bars until sound is enabled, then stay 8s more before lifting */
const videoIntroActive = ref(false);
let videoIntroTimer: number | undefined;

const hasVideoIntroChromeMask = computed(
    () => Boolean(videoEmbedUrl.value) && (videoProvider.value === 'youtube' || videoProvider.value === 'vimeo'),
);

/** Top/bottom black bars: YouTube/Vimeo follow intro ref; other embeds stay masked */
const showChromeMaskBars = computed(
    () =>
        Boolean(videoEmbedUrl.value)
        && (hasVideoIntroChromeMask.value ? videoIntroActive.value : true),
);

const endVideoIntro = (): void => {
    videoIntroActive.value = false;
    if (videoIntroTimer !== undefined) {
        window.clearTimeout(videoIntroTimer);
        videoIntroTimer = undefined;
    }
};

const scheduleChromeMaskLift = (): void => {
    if (!hasVideoIntroChromeMask.value) {
        return;
    }
    if (videoIntroTimer !== undefined) {
        window.clearTimeout(videoIntroTimer);
    }
    videoIntroTimer = window.setTimeout(() => {
        endVideoIntro();
    }, 8000);
};

// Simulated live viewer count
const viewerCount = ref(Math.floor(Math.random() * 180) + 120);
let viewerTimer: number | undefined;

const webinarTitle = computed(() => props.funnel.settings?.webinar_title ?? `${props.funnel.name} Webinar`);
const webinarDesc = computed(() => props.funnel.settings?.webinar_description ?? 'Watch the exclusive webinar training below.');
const webinarCtaLabel = computed(() => props.funnel.settings?.webinar_cta_label?.trim() || 'Claim Your Spot');
const webinarCtaUrl = computed(() => props.funnel.settings?.webinar_cta_url?.trim() || '');
const hasWebinarCta = computed(() => webinarCtaUrl.value.length > 0);
const videoDurationSeconds = computed(() => Number(props.funnel.settings?.webinar_duration_seconds ?? 0));

/** Converts any YouTube or Vimeo URL variant into the proper embed URL */
const baseVideoEmbedUrl = computed((): string | null => {
    const raw = props.funnel.settings?.video_url?.trim();
    if (!raw) return null;

    // Already a proper embed URL — normalize to standard youtube.com/embed.
    if (raw.includes('youtube.com/embed/') || raw.includes('youtube-nocookie.com/embed/')) {
        const id = raw.match(/embed\/([a-zA-Z0-9_-]+)/)?.[1];
        return id ? `https://www.youtube.com/embed/${id}` : raw;
    }
    if (raw.includes('player.vimeo.com/video/')) {
        return raw;
    }

    // youtu.be/ID short URL
    const youtuBe = raw.match(/youtu\.be\/([a-zA-Z0-9_-]+)/);
    if (youtuBe) {
        return `https://www.youtube.com/embed/${youtuBe[1]}`;
    }

    // youtube.com/watch?v=ID  (may have extra params)
    const ytWatch = raw.match(/[?&]v=([a-zA-Z0-9_-]+)/);
    if (raw.includes('youtube.com') && ytWatch) {
        return `https://www.youtube.com/embed/${ytWatch[1]}`;
    }

    // youtube live/shorts URL patterns
    const ytLive = raw.match(/youtube\.com\/live\/([a-zA-Z0-9_-]+)/);
    if (ytLive) {
        return `https://www.youtube.com/embed/${ytLive[1]}`;
    }
    const ytShorts = raw.match(/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/);
    if (ytShorts) {
        return `https://www.youtube.com/embed/${ytShorts[1]}`;
    }

    // vimeo.com/ID  (numeric ID, not /channels/ etc.)
    const vimeoMatch = raw.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch) {
        return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
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

const videoEmbedUrl = computed((): string | null => {
    const base = baseVideoEmbedUrl.value;
    if (!base) return null;

    const separator = base.includes('?') ? '&' : '?';
    const origin = typeof window !== 'undefined' ? window.location.origin : '';

    if (videoProvider.value === 'youtube') {
        // Stream-style playback: no YouTube chrome + JS API control.
        return `${base}${separator}autoplay=1&mute=${soundEnabled.value ? '0' : '1'}&controls=0&disablekb=1&fs=0&loop=0&iv_load_policy=3&modestbranding=1&rel=0&showinfo=0&cc_load_policy=0&playsinline=1&enablejsapi=1&origin=${encodeURIComponent(origin)}`;
    }

    if (videoProvider.value === 'vimeo') {
        return `${base}${separator}autoplay=1&muted=${soundEnabled.value ? '0' : '1'}&controls=0&loop=0&title=0&byline=0&portrait=0&keyboard=0&dnt=1&playsinline=1`;
    }

    return base;
});

const offers = computed(() => {
    const rows = props.funnel.settings?.offers ?? [];
    return rows
        .map((offer, idx) => ({
            id: idx,
            title: offer.title ?? '',
            description: offer.description ?? '',
            cta_label: offer.cta_label ?? 'Get Offer',
            cta_url: offer.cta_url ?? '',
            placement: offer.placement ?? 'pinned',
            timing_seconds: Number(offer.timing_seconds ?? 0),
            enabled: offer.enabled !== false,
        }))
        .filter((o) => o.enabled && o.title && o.cta_url);
});

const activeOffers = computed(() =>
    offers.value.filter((offer) => elapsedSeconds.value >= offer.timing_seconds),
);

const pinnedOffers = computed(() => activeOffers.value.filter((o) => o.placement === 'pinned'));
const chatOffers = computed(() => activeOffers.value.filter((o) => o.placement === 'chat'));
const popupOffers = computed(() => activeOffers.value.filter((o) => o.placement === 'popup'));

const visiblePopupOffer = computed(() =>
    popupOffers.value.find((offer) => !dismissedPopupIds.value.has(offer.id)) ?? null,
);

const chatOfferMessages = computed(() =>
    chatOffers.value.map((offer) => ({
        id: `offer-chat-${offer.id}`,
        author_name: 'Host',
        participant_role: 'owner',
        message: `${offer.title}${offer.description ? `\n${offer.description}` : ''}`,
        cta_label: offer.cta_label,
        cta_url: offer.cta_url,
    })),
);
const shownChatOfferIds = ref<Set<number>>(new Set());

type RealtimeMessage = {
    id: number;
    author_name: string;
    participant_role?: string;
    message: string;
};

const exitPopupEnabled = computed(() => props.funnel.settings?.exit_popup_enabled === true);
const exitPopupShowClose = computed(() => props.funnel.settings?.exit_popup_show_close !== false);
const exitPopupTitle = computed(() => props.funnel.settings?.exit_popup_title?.trim() || 'Wait! Before You Go...');
const exitPopupDescription = computed(() => props.funnel.settings?.exit_popup_description?.trim() || 'Claim this special offer before leaving this webinar room.');
const exitPopupCtaLabel = computed(() => props.funnel.settings?.exit_popup_cta_label?.trim() || 'Claim Offer');
const exitPopupCtaUrl = computed(() => props.funnel.settings?.exit_popup_cta_url?.trim() || '');
const hasExitPopupCta = computed(() => exitPopupCtaUrl.value.length > 0);
const videoEndRedirectEnabled = computed(() => props.funnel.settings?.redirect_enabled === true);
const videoEndRedirectUrl = computed(() => props.funnel.settings?.redirect_url?.trim() || '');
const hasVideoEndRedirect = computed(() => videoEndRedirectEnabled.value && videoEndRedirectUrl.value.length > 0);

const dismissOfferPopup = (): void => {
    if (!visiblePopupOffer.value) return;
    dismissedPopupIds.value.add(visiblePopupOffer.value.id);
};

const enableSoundAndPlay = (): void => {
    soundEnabled.value = true;
    scheduleChromeMaskLift();
    const win = iframeRef.value?.contentWindow;
    if (!win) return;

    if (videoProvider.value === 'youtube') {
        win.postMessage(JSON.stringify({ event: 'command', func: 'unMute', args: [] }), '*');
        win.postMessage(JSON.stringify({ event: 'command', func: 'setVolume', args: [100] }), '*');
        win.postMessage(JSON.stringify({ event: 'command', func: 'playVideo', args: [] }), '*');
    } else if (videoProvider.value === 'vimeo') {
        win.postMessage(JSON.stringify({ method: 'setVolume', value: '1' }), '*');
        win.postMessage(JSON.stringify({ method: 'play' }), '*');
    }

    window.setTimeout(tryResumePlayback, 150);
    window.setTimeout(tryResumePlayback, 1500);
};

const onIframeLoad = (): void => {
    const win = iframeRef.value?.contentWindow;
    if (!win) return;

    if (videoProvider.value === 'youtube') {
        win.postMessage(JSON.stringify({ event: 'listening' }), '*');
    }
    if (videoProvider.value === 'vimeo') {
        win.postMessage(JSON.stringify({ method: 'addEventListener', value: 'ended' }), '*');
    }
};

const tryResumePlayback = (): void => {
    if (!soundEnabled.value) return;
    const win = iframeRef.value?.contentWindow;
    if (!win) return;

    if (videoProvider.value === 'youtube') {
        win.postMessage(JSON.stringify({ event: 'command', func: 'playVideo', args: [] }), '*');
    } else if (videoProvider.value === 'vimeo') {
        win.postMessage(JSON.stringify({ method: 'play' }), '*');
    }
};

const ensureSessionKey = (): string => {
    if (sessionKey.value) return sessionKey.value;
    const storageKey = `webinar_session_${props.funnel.slug}`;
    const existing = window.localStorage.getItem(storageKey);
    if (existing) {
        sessionKey.value = existing;
        return existing;
    }
    const generated = `${props.funnel.slug}_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
    window.localStorage.setItem(storageKey, generated);
    sessionKey.value = generated;
    return generated;
};

const postAnalytics = async (event: 'access' | 'heartbeat' | 'milestone_60' | 'milestone_50' | 'milestone_100'): Promise<void> => {
    if (!props.analyticsEndpoint) return;
    const body = JSON.stringify({
        session_key: ensureSessionKey(),
        event,
        watched_seconds: elapsedSeconds.value,
    });

    try {
        await fetch(props.analyticsEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body,
            keepalive: true,
        });
    } catch {
        // Silently ignore analytics failures.
    }
};

const maybeTrackMilestones = (): void => {
    if (elapsedSeconds.value >= 60 && !sentMilestones.value.has('milestone_60')) {
        sentMilestones.value.add('milestone_60');
        postAnalytics('milestone_60');
    }

    const duration = videoDurationSeconds.value;
    if (duration > 0) {
        const half = Math.ceil(duration * 0.5);
        if (elapsedSeconds.value >= half && !sentMilestones.value.has('milestone_50')) {
            sentMilestones.value.add('milestone_50');
            postAnalytics('milestone_50');
        }

        if (elapsedSeconds.value >= duration && !sentMilestones.value.has('milestone_100')) {
            sentMilestones.value.add('milestone_100');
            postAnalytics('milestone_100');
        }
    }
};

const maybeRedirectAtVideoEnd = (): void => {
    if (redirectedAtEnd.value || !hasVideoEndRedirect.value) {
        return;
    }

    const duration = videoDurationSeconds.value;
    if (duration <= 0 || elapsedSeconds.value < duration) {
        return;
    }

    redirectedAtEnd.value = true;
    window.location.assign(videoEndRedirectUrl.value);
};

watch(
    chatOffers,
    (list) => {
        let hasNew = false;
        for (const offer of list) {
            if (!shownChatOfferIds.value.has(offer.id)) {
                shownChatOfferIds.value.add(offer.id);
                hasNew = true;
            }
        }
        if (hasNew) {
            scrollToBottom();
        }
    },
    { immediate: true },
);

const openExitPopup = (): void => {
    if (!exitPopupEnabled.value || exitPopupShownOnce.value) return;
    exitPopupVisible.value = true;
    exitPopupShownOnce.value = true;
};

/**
 * Exit intent trigger: if cursor is moving upward and reaches
 * the top edge of viewport, show the popup once.
 */
const onMouseMoveExitIntent = (event: MouseEvent): void => {
    if (!exitPopupEnabled.value || exitPopupShownOnce.value) return;

    const y = event.clientY;
    const prev = lastMouseY.value;
    lastMouseY.value = y;

    if (prev === null) return;

    const movingUpFast = prev - y >= 8;
    const nearTopEdge = y <= 12;

    if (movingUpFast && nearTopEdge) {
        openExitPopup();
    }
};

/**
 * Backup trigger for when pointer leaves browser window at top.
 */
const onMouseOutExitIntent = (event: MouseEvent): void => {
    if (!exitPopupEnabled.value || exitPopupShownOnce.value) return;
    if (event.relatedTarget !== null) return;
    if (event.clientY <= 10) {
        openExitPopup();
    }
};

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

const upsertMessage = (incoming: RealtimeMessage): void => {
    const idx = messages.value.findIndex((message) => Number(message.id) === Number(incoming.id));
    if (idx >= 0) {
        messages.value[idx] = incoming;
        return;
    }
    messages.value.push(incoming);
    scrollToBottom();
};

const setupRealtimeChat = (): void => {
    const realtime = props.chatRealtime;
    if (!realtime || !realtime.funnel_id || !realtime.conversation_key) {
        return;
    }

    const appKey = (import.meta.env.VITE_REVERB_APP_KEY ?? '').toString().trim();
    const wsHost = (import.meta.env.VITE_REVERB_HOST ?? window.location.hostname).toString();
    const wsPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
    const wsScheme = (import.meta.env.VITE_REVERB_SCHEME ?? 'http').toString();
    const wsPath = (import.meta.env.VITE_REVERB_PATH ?? '').toString();
    const wsUseTls = wsScheme === 'https';

    if (!appKey) {
        return;
    }

    const pusher = new Pusher(appKey, {
        wsHost,
        wsPort,
        wssPort: wsPort,
        forceTLS: wsUseTls,
        enabledTransports: ['ws', 'wss'],
        cluster: 'mt1',
        wsPath: wsPath || undefined,
    });

    echo = new Echo({
        broadcaster: 'reverb',
        client: pusher,
    });

    echo
        .channel(`webinar.${realtime.funnel_id}.${realtime.conversation_key}`)
        .listen('.webinar.chat.message.created', (event: { message?: RealtimeMessage }) => {
            const message = event?.message;
            if (!message || !message.id) {
                return;
            }
            upsertMessage(message);
        });
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
    ensureSessionKey();
    postAnalytics('access');
    scrollToBottom();
    if (hasVideoIntroChromeMask.value) {
        videoIntroActive.value = true;
    }
    poller = window.setInterval(fetchMessages, 4000);
    // Simulate live viewer count fluctuation
    viewerTimer = window.setInterval(() => {
        const delta = Math.floor(Math.random() * 7) - 3;
        viewerCount.value = Math.max(80, viewerCount.value + delta);
    }, 5000);
    startedAtMs.value = Date.now();
    offerTimer = window.setInterval(() => {
        elapsedSeconds.value = Math.floor((Date.now() - startedAtMs.value) / 1000);
        maybeTrackMilestones();
        maybeRedirectAtVideoEnd();
    }, 1000);
    playbackKeepAliveTimer = window.setInterval(tryResumePlayback, 12000);
    analyticsHeartbeatTimer = window.setInterval(() => {
        postAnalytics('heartbeat');
    }, 15000);
    document.addEventListener('mousemove', onMouseMoveExitIntent, { passive: true });
    document.addEventListener('mouseout', onMouseOutExitIntent, { passive: true });
    setupRealtimeChat();
});

onUnmounted(() => {
    endVideoIntro();
    if (poller) window.clearInterval(poller);
    if (viewerTimer) window.clearInterval(viewerTimer);
    if (offerTimer) window.clearInterval(offerTimer);
    if (playbackKeepAliveTimer) window.clearInterval(playbackKeepAliveTimer);
    if (analyticsHeartbeatTimer) window.clearInterval(analyticsHeartbeatTimer);
    postAnalytics('heartbeat');
    document.removeEventListener('mousemove', onMouseMoveExitIntent);
    document.removeEventListener('mouseout', onMouseOutExitIntent);
    echo?.disconnect();
    echo = null;
});
</script>

<template>
    <Head :title="webinarTitle" />

    <!-- Locked to viewport — no page scroll -->
    <div class="flex h-screen flex-col overflow-hidden bg-linear-to-br from-emerald-50 via-cyan-50/70 to-white text-slate-900">

        <!-- ── Top bar ── -->
        <header class="flex shrink-0 items-center justify-between border-b border-emerald-200/70 bg-white/85 px-4 py-2 backdrop-blur">

            <!-- Left: icon + title -->
            <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="flex size-7 shrink-0 items-center justify-center rounded-md" style="background:rgba(64,224,208,0.15)">
                    <Icon icon="heroicons:video-camera" class="size-4" style="color:#40E0D0" />
                </div>
                <span class="truncate text-sm font-semibold text-slate-800">{{ webinarTitle }}</span>
            </div>

            <!-- Right: CTA + meta badges -->
            <div class="flex items-center gap-2.5 shrink-0">
                <!-- Primary CTA button — visible at top always -->
                <a
                    v-if="hasWebinarCta"
                    :href="webinarCtaUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="hidden sm:inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold shrink-0 cta-pulse first-letter:uppercase"
                    style="background:#40E0D0;color:#0a0f1e;"
                >
                    <Icon icon="heroicons:rocket-launch" class="size-3.5" />
                    {{ webinarCtaLabel }}
                </a>

                <!-- Viewer count -->
                <span class="hidden md:inline-flex items-center gap-1 text-xs text-slate-500">
                    <Icon icon="heroicons:eye" class="size-3.5" />
                    {{ viewerCount.toLocaleString() }} watching
                </span>

                <!-- LIVE badge -->
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/20 border border-rose-500/30 px-2.5 py-1 text-[11px] font-bold text-rose-400 uppercase tracking-wider">
                    <span class="size-1.5 rounded-full bg-rose-500 animate-pulse" />
                    Live
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
                        ref="iframeRef"
                        class="absolute inset-0 h-full w-full"
                        :src="videoEmbedUrl"
                        frameborder="0"
                        allow="autoplay; encrypted-media; picture-in-picture"
                        referrerpolicy="strict-origin-when-cross-origin"
                        @load="onIframeLoad"
                    />
                    <div v-else class="flex h-full flex-col items-center justify-center gap-3 text-white/40">
                        <Icon icon="heroicons:video-camera-slash" class="size-14" />
                        <p class="text-sm">Video not configured yet</p>
                    </div>

                    <!-- Click blocker to prevent hover/click exposing native controls -->
                    <div v-if="videoEmbedUrl" class="absolute inset-0 z-10" />

                    <!-- Force stream-style look by masking provider chrome (above blocker, pointer-events none) -->
                    <template v-if="videoEmbedUrl">
                        <div
                            v-if="showChromeMaskBars"
                            class="chrome-mask-top pointer-events-none absolute inset-x-0 top-0 z-15 min-h-24 md:min-h-28"
                        />
                        <div
                            v-if="showChromeMaskBars"
                            class="chrome-mask-bottom pointer-events-none absolute inset-x-0 bottom-0 z-15 min-h-32 md:min-h-40"
                        />
                    </template>

                    <!-- Sound gate overlay: stream starts muted by default -->
                    <div
                        v-if="videoEmbedUrl && !soundEnabled"
                        class="absolute inset-0 z-20 flex items-center justify-center bg-black/50 p-4"
                    >
                        <button
                            class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold shadow-xl transition hover:opacity-90"
                            style="background:#40E0D0;color:#0a0f1e;"
                            @click="enableSoundAndPlay"
                        >
                            <Icon icon="heroicons:speaker-wave" class="size-4" />
                            Click to enable sound
                        </button>
                    </div>

                    <!-- Floating LIVE + viewer overlay on video -->
                    <div v-if="videoEmbedUrl" class="pointer-events-none absolute left-3 top-3 z-30 flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-600/85 px-2.5 py-1 text-[11px] font-bold text-white uppercase tracking-wider backdrop-blur-sm">
                            <span class="size-1.5 rounded-full bg-white animate-pulse" />
                            Live
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-black/60 px-2.5 py-1 text-[11px] text-white/80 backdrop-blur-sm">
                            <Icon icon="heroicons:eye" class="size-3" />
                            {{ viewerCount.toLocaleString() }}
                        </span>
                    </div>

                    <!-- Timed popup offer -->
                    <div v-if="visiblePopupOffer" class="absolute inset-0 z-40 flex items-center justify-center bg-black/55 p-4">
                        <div class="w-full max-w-md rounded-xl border border-[#40E0D0]/30 bg-[#0d1424] p-5 shadow-2xl">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[0.65rem] font-bold uppercase tracking-wider text-[#40E0D0]">Limited Offer</p>
                                    <h3 class="mt-1 text-lg font-bold text-white leading-snug">{{ visiblePopupOffer.title }}</h3>
                                </div>
                                <button class="text-white/50 hover:text-white" @click="dismissOfferPopup">
                                    <Icon icon="heroicons:x-mark" class="size-5" />
                                </button>
                            </div>
                            <p v-if="visiblePopupOffer.description" class="mt-2 text-sm text-white/70 leading-relaxed">
                                {{ visiblePopupOffer.description }}
                            </p>
                            <a
                                :href="visiblePopupOffer.cta_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold"
                                style="background:#40E0D0;color:#0a0f1e;"
                            >
                                {{ visiblePopupOffer.cta_label }}
                                <Icon icon="heroicons:arrow-top-right-on-square" class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Compact info strip below video -->
                <div class="flex shrink-0 items-center gap-4 px-0.5 text-[11px] text-slate-500">
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
            <aside class="flex min-h-0 flex-col overflow-hidden border-t border-emerald-200/70 bg-white/70 lg:w-[360px] lg:border-l lg:border-t-0">
                <!-- Chat header -->
                <div class="flex shrink-0 items-center justify-between border-b border-emerald-200/70 bg-white/90 px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Icon icon="heroicons:chat-bubble-oval-left-ellipsis" class="size-4 text-slate-500" />
                        <span class="text-sm font-semibold text-slate-800">Webinar Chat</span>
                    </div>
                    <span class="text-xs text-slate-500">{{ messages.length }} messages</span>
                </div>

                <!-- Pinned offers in chat -->
                <div v-if="pinnedOffers.length > 0 || hasWebinarCta" class="shrink-0 space-y-2 border-b border-emerald-200/70 bg-emerald-50/70 px-3 py-2">
                    <a
                        v-for="offer in pinnedOffers"
                        :key="`pinned-${offer.id}`"
                        :href="offer.cta_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200/70 bg-white px-3 py-2 text-xs"
                    >
                        <span class="flex min-w-0 items-center gap-1.5 text-slate-700">
                            <Icon icon="heroicons:megaphone" class="size-3.5" style="color:#40E0D0" />
                            <span class="truncate">{{ offer.title }}</span>
                        </span>
                        <span class="inline-flex items-center gap-1 font-semibold shrink-0" style="color:#40E0D0">
                            {{ offer.cta_label }}
                            <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                        </span>
                    </a>
                    <a
                        v-if="hasWebinarCta"
                        :href="webinarCtaUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center justify-between gap-2 rounded-lg border border-emerald-200/70 bg-white px-3 py-2 text-xs"
                    >
                        <span class="flex items-center gap-1.5 text-slate-700">
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
                    class="flex-1 min-h-0 space-y-2 overflow-y-auto p-3 scrollbar-thin scrollbar-track-transparent scrollbar-thumb-slate-300/80"
                >
                    <div v-if="messages.length === 0" class="flex h-full flex-col items-center justify-center gap-2 py-10 text-slate-400">
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
                                : 'bg-slate-200 text-slate-500'"
                            :style="msg.participant_role === 'owner' ? 'color:#40E0D0' : ''"
                        >
                            {{ msg.author_name.charAt(0).toUpperCase() }}
                        </div>

                        <!-- Bubble -->
                        <div class="max-w-[80%]" :class="msg.participant_role === 'owner' ? 'items-end flex flex-col' : ''">
                            <p
                                class="text-[0.6rem] font-semibold mb-0.5"
                                :class="msg.participant_role === 'owner' ? 'text-right' : ''"
                                :style="msg.participant_role === 'owner' ? 'color:#40E0D0' : 'color: rgb(100 116 139)'"
                            >
                                {{ msg.author_name }}
                                <span v-if="msg.participant_role === 'owner'" class="ml-1 opacity-70">• Host</span>
                            </p>
                            <div
                                class="rounded-xl px-3 py-2 text-sm leading-relaxed whitespace-pre-line"
                                :class="msg.participant_role === 'owner'
                                    ? 'rounded-tr-sm text-right'
                                    : 'rounded-tl-sm bg-slate-100 text-slate-700'"
                                :style="msg.participant_role === 'owner' ? 'background: rgba(64,224,208,0.18); color: rgb(15 23 42)' : ''"
                            >
                                {{ msg.message }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="offerMsg in chatOfferMessages"
                        :key="offerMsg.id"
                        class="flex gap-2.5 flex-row-reverse"
                    >
                        <div
                            class="flex size-6 shrink-0 items-center justify-center rounded-full text-[0.55rem] font-bold mt-0.5 bg-primary/30 text-primary"
                            style="color:#40E0D0"
                        >
                            H
                        </div>
                        <div class="max-w-[80%] items-end flex flex-col">
                            <p class="text-[0.6rem] font-semibold mb-0.5 text-right" style="color:#40E0D0">
                                {{ offerMsg.author_name }}
                                <span class="ml-1 opacity-70">• Host</span>
                            </p>
                            <div
                                class="rounded-xl rounded-tr-sm px-3 py-2 text-sm leading-relaxed text-right whitespace-pre-line"
                                style="background: rgba(64,224,208,0.18); color: rgb(15 23 42)"
                            >
                                <p>{{ offerMsg.message }}</p>
                                <a
                                    :href="offerMsg.cta_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="mt-2 inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold"
                                    style="background:#40E0D0;color:#0a0f1e;"
                                >
                                    {{ offerMsg.cta_label }}
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="size-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Input -->
                <div class="border-t border-emerald-200/70 bg-white/90 p-3">
                    <div class="flex gap-2">
                        <textarea
                            v-model="messageInput"
                            rows="2"
                            placeholder="Type a message… (Enter to send)"
                            class="flex-1 resize-none rounded-lg border border-emerald-200/70 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 transition-colors focus:border-primary/50 focus:outline-none focus:ring-1 focus:ring-primary/30"
                            @keydown="handleKeydown"
                        />
                        <button
                            class="flex shrink-0 size-[calc(2rem+4px*2+2px*2)] items-center justify-center self-end rounded-lg transition-all disabled:opacity-40"
                            :class="messageInput.trim() && !sending
                                ? 'bg-primary text-primary-foreground hover:opacity-90 shadow-lg shadow-primary/20'
                                : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
                            style="background: #40E0D0; color: #0a0f1e"
                            :disabled="sending || !messageInput.trim()"
                            @click="sendMessage"
                        >
                            <Icon v-if="sending" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                            <Icon v-else icon="heroicons:paper-airplane" class="size-4" />
                        </button>
                    </div>
                    <p class="mt-1.5 text-center text-[0.6rem] text-slate-500">Press Enter to send · Shift+Enter for new line</p>
                </div>
            </aside>
        </div>

        <!-- Exit-intent popup -->
        <div v-if="exitPopupVisible && exitPopupEnabled" class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 p-4">
            <div class="w-full max-w-lg rounded-xl border border-[#40E0D0]/30 bg-[#0d1424] p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[0.65rem] font-bold uppercase tracking-wider text-[#40E0D0]">Before You Leave</p>
                        <h3 class="mt-1 text-xl font-bold text-white leading-snug">{{ exitPopupTitle }}</h3>
                    </div>
                    <button
                        v-if="exitPopupShowClose"
                        class="text-white/50 hover:text-white"
                        @click="exitPopupVisible = false"
                    >
                        <Icon icon="heroicons:x-mark" class="size-5" />
                    </button>
                </div>
                <p class="mt-3 text-sm text-white/75 leading-relaxed">{{ exitPopupDescription }}</p>
                <div class="mt-5 flex gap-2">
                    <a
                        v-if="hasExitPopupCta"
                        :href="exitPopupCtaUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg px-4 py-2.5 text-sm font-semibold"
                        style="background:#40E0D0;color:#0a0f1e;"
                    >
                        {{ exitPopupCtaLabel }}
                        <Icon icon="heroicons:arrow-top-right-on-square" class="size-4" />
                    </a>
                    <button
                        v-if="exitPopupShowClose"
                        class="inline-flex items-center justify-center rounded-lg border border-white/15 px-4 py-2.5 text-sm font-semibold text-white/80 hover:bg-white/5"
                        @click="exitPopupVisible = false"
                    >
                        Continue Watching
                    </button>
                </div>
            </div>
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

/* Top/bottom chrome masks: very dark outer + mid, softer at the edge meeting the video */
.chrome-mask-top {
    background: linear-gradient(
        to bottom,
        #000000 0%,
        #050505 38%,
        #020202 58%,
        rgba(0, 0, 0, 0.55) 82%,
        rgba(0, 0, 0, 0.12) 96%,
        rgba(0, 0, 0, 0) 100%
    );
}

.chrome-mask-bottom {
    background: linear-gradient(
        to top,
        #000000 0%,
        #050505 38%,
        #020202 58%,
        rgba(0, 0, 0, 0.55) 82%,
        rgba(0, 0, 0, 0.12) 96%,
        rgba(0, 0, 0, 0) 100%
    );
}
</style>
