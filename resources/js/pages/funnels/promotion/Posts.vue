<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

// ─── Types ──────────────────────────────────────────────────────────────────
type PromotionPost = {
    id: number;
    title: string | null;
    topic: string | null;
    content_type: 'text' | 'image' | 'video' | 'email';
    platforms: string[];
    publish_mode: 'approve_first' | 'auto_publish';
    status: string;
    cta_url: string | null;
    cta_label: string | null;
    text_body: string | null;
    email_subject: string | null;
    email_body: string | null;
    hashtags: string[] | null;
    scheduled_for: string | null;
    published_at: string | null;
    last_error: string | null;
    generation_context?: Record<string, unknown> | null;
    primary_asset?: {
        id: number;
        asset_type: string;
        url: string | null;
        thumbnail_url: string | null;
        status: string;
    } | null;
};

type Paginator = {
    data: PromotionPost[];
    current_page: number;
    from: number | null;
    to: number | null;
    total: number;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type TopicSuggestion = { id: number; topic: string; angle: string | null; score: number };

type DIDAvatar = { id: string; name: string; thumbnail_url: string; talking_preview_url: string };
type DIDVoice  = { id: string; name: string; lang: string; style: string; preview_url: string };

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
    funnel: { id: number; name: string; status: string };
    posts: Paginator;
    stats: { total: number; draft: number; scheduled: number; published: number; failed: number };
    filters: { status?: string; type?: string; platform?: string; search?: string };
    suggestedTopics: TopicSuggestion[];
    availablePlatforms: string[];
    videoEnabled: boolean;
    availableAvatars: DIDAvatar[];
    availableVoices: DIDVoice[];
    routes: { store: string; bulk: string; calendar: string; topicsGenerate: string; scriptGenerate: string };
}>();

// ─── Background job polling ──────────────────────────────────────────────────
// Track previous statuses so we can notify when generation/publishing finishes.
const prevStatuses = ref<Record<number, string>>({});

watch(
    () => props.posts.data,
    (posts) => {
        posts.forEach((post) => {
            const prev = prevStatuses.value[post.id];
            if (prev === 'generating' && post.status === 'ready') {
                toast.success(`✅ "${post.topic ?? `Post #${post.id}`}" is ready to publish!`);
            }
            if (prev === 'generating' && post.status === 'failed') {
                toast.error(`❌ Generation failed for "${post.topic ?? `Post #${post.id}`}".`);
            }
            if (prev === 'publishing' && post.status === 'published') {
                toast.success(`🚀 "${post.topic ?? `Post #${post.id}`}" published!`);
            }
            prevStatuses.value[post.id] = post.status;
        });
    },
    { immediate: true, deep: true },
);

const isProcessing = computed(() =>
    props.posts.data.some((p) => p.status === 'generating' || p.status === 'publishing'),
);

let pollTimer: ReturnType<typeof setInterval> | undefined;

watch(isProcessing, (active) => {
    if (active && !pollTimer) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['posts', 'stats'] });
        }, 4500);
    } else if (!active && pollTimer) {
        clearInterval(pollTimer);
        pollTimer = undefined;
    }
}, { immediate: true });

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// ─── Form type ──────────────────────────────────────────────────────────────
type CreatePostFormData = {
    topic: string;
    content_type: 'text' | 'image' | 'video' | 'email';
    platforms: string[];
    publish_mode: 'approve_first' | 'auto_publish';
    cta_label: string;
    cta_url: string;
    generation_context: {
        context?: string;
        include_text?: boolean;
        include_image?: boolean;
        avatar_id?: string;
        voice_id?: string;
        email_type?: EmailType;
    };
    text_body?: string | null;
    auto_generate: boolean;
};

// ─── Modal / wizard state ────────────────────────────────────────────────────
type WizardStep = 1 | 2 | 3 | 4;
const dialogOpen = ref(false);
const wizardStep = ref<WizardStep>(1);

// Step 1 – Topic
const topicInput = ref('');
const topicContext = ref('');
const generatingTopics = ref(false);

type EmailType = 'promotional' | 'follow-up' | 'newsletter';

const EMAIL_TYPE_OPTIONS: Array<{ key: EmailType; label: string; description: string }> = [
    { key: 'promotional', label: 'Promotional', description: 'Drive clicks with a direct offer' },
    { key: 'follow-up', label: 'Follow-up', description: 'Nurture leads who showed interest' },
    { key: 'newsletter', label: 'Newsletter', description: 'Educate and build trust over time' },
];

// Step 2 – Format
type Format = 'post' | 'video' | 'email';
const selectedFormat = ref<Format>('post');
const includeText = ref(true);
const includeImage = ref(true);
const videoSubStep = ref<'avatar' | 'voice'>('avatar');
const selectedAvatarId = ref('');
const selectedVoiceId = ref('');
const selectedEmailType = ref<EmailType>('promotional');

// Step 3 (video only) – Script
const videoScript = ref('');
const generatingScript = ref(false);

// Launch step – Platforms + publish
const selectedPlatforms = ref<string[]>(['twitter']);
const publishMode = ref<'approve_first' | 'auto_publish'>('approve_first');
const campaignContext = ref('');
const ctaLabelInput = ref('');
const ctaUrlInput = ref('');
const showCta = ref(false);

const isVideoFormat = computed(() => selectedFormat.value === 'video');
const launchWizardStep = computed<WizardStep>(() => (isVideoFormat.value ? 4 : 3));
const wizardStepNumbers = computed(() => (isVideoFormat.value ? [1, 2, 3, 4] : [1, 2, 3]) as WizardStep[]);
const wizardStepLabels: Record<WizardStep, string> = {
    1: 'Topic',
    2: 'Format',
    3: 'Script',
    4: 'Launch',
};

function stepDisplayLabel(step: WizardStep): string {
    if (step === 3 && !isVideoFormat.value) return 'Launch';
    if (step === 4) return 'Launch';

    return wizardStepLabels[step];
}

function openDialog(): void {
    wizardStep.value = 1;
    videoSubStep.value = 'avatar';
    topicInput.value = '';
    topicContext.value = '';
    selectedFormat.value = 'post';
    includeText.value = true;
    includeImage.value = true;
    selectedAvatarId.value = '';
    selectedVoiceId.value = '';
    selectedEmailType.value = 'promotional';
    videoScript.value = '';
    generatingScript.value = false;
    selectedPlatforms.value = ['twitter'];
    publishMode.value = 'approve_first';
    campaignContext.value = '';
    ctaLabelInput.value = '';
    ctaUrlInput.value = '';
    showCta.value = false;
    dialogOpen.value = true;
}

// ─── Avatar / voice options (sourced from D-ID via props) ────────────────────
const AVATARS = computed(() => props.availableAvatars);
const VOICES  = computed(() => props.availableVoices);

// Voice preview playback
const loadingVoicePreview = ref<string | null>(null);
const playingVoiceId      = ref<string | null>(null);
let voicePreviewAudio: HTMLAudioElement | null = null;

function stopVoicePreview(): void {
    if (voicePreviewAudio) {
        voicePreviewAudio.pause();
        voicePreviewAudio.src = '';
        voicePreviewAudio = null;
    }
    playingVoiceId.value = null;
    loadingVoicePreview.value = null;
}

async function playVoicePreview(voice: DIDVoice, event: Event): Promise<void> {
    event.stopPropagation();

    if (playingVoiceId.value === voice.id) {
        stopVoicePreview();
        return;
    }

    stopVoicePreview();
    loadingVoicePreview.value = voice.id;

    const audio = new Audio(voice.preview_url);
    voicePreviewAudio = audio;

    audio.onended = () => {
        if (playingVoiceId.value === voice.id) {
            playingVoiceId.value = null;
        }
    };

    try {
        await audio.play();
        loadingVoicePreview.value = null;
        playingVoiceId.value = voice.id;
    } catch {
        loadingVoicePreview.value = null;
        playingVoiceId.value = null;
        toast.error('Could not play voice preview.');
    }
}

watch(dialogOpen, (open) => {
    if (!open) stopVoicePreview();
});

onUnmounted(() => {
    stopVoicePreview();
});

// ─── Platform meta ───────────────────────────────────────────────────────────
const PLATFORM_META: Record<string, { label: string; icon: string }> = {
    twitter: { label: 'X (Twitter)', icon: 'simple-icons:x'       },
    youtube: { label: 'YouTube',     icon: 'simple-icons:youtube'  },
    reddit:  { label: 'Reddit',      icon: 'simple-icons:reddit'   },
};

// ─── Wizard navigation ───────────────────────────────────────────────────────
function canAdvance(): boolean {
    if (wizardStep.value === 1) return topicInput.value.trim().length > 0;
    if (wizardStep.value === 2 && selectedFormat.value === 'video') {
        if (!props.videoEnabled) return false;
        if (videoSubStep.value === 'avatar') return selectedAvatarId.value !== '';
        return selectedAvatarId.value !== '' && selectedVoiceId.value !== '';
    }
    if (wizardStep.value === 2 && selectedFormat.value === 'post') {
        return includeText.value || includeImage.value;
    }
    if (wizardStep.value === 3 && isVideoFormat.value) {
        return videoScript.value.trim().length >= 20;
    }
    return true;
}

function nextStep(): void {
    if (!canAdvance()) return;
    if (wizardStep.value === 2 && selectedFormat.value === 'video' && videoSubStep.value === 'avatar') {
        videoSubStep.value = 'voice';
        return;
    }
    if (wizardStep.value < launchWizardStep.value) {
        wizardStep.value = (wizardStep.value + 1) as WizardStep;
        if (wizardStep.value === 3 && isVideoFormat.value && !videoScript.value) {
            generateVideoScript();
        }
    }
}

function prevStep(): void {
    if (wizardStep.value === 2 && selectedFormat.value === 'video' && videoSubStep.value === 'voice') {
        videoSubStep.value = 'avatar';
        return;
    }
    if (wizardStep.value > 1) wizardStep.value = (wizardStep.value - 1) as WizardStep;
}

function goStep(s: WizardStep): void {
    if (s <= wizardStep.value) wizardStep.value = s;
}

function selectTopic(topic: string): void {
    topicInput.value = topic;
    nextStep();
}

function toggleIncludeText(): void {
    // Can only turn off if image is still on
    if (includeText.value && includeImage.value) {
        includeText.value = false;
    } else {
        includeText.value = true;
    }
}

function toggleIncludeImage(): void {
    // Can only turn off if text is still on
    if (includeImage.value && includeText.value) {
        includeImage.value = false;
    } else {
        includeImage.value = true;
    }
}

function togglePlatform(platform: string): void {
    const idx = selectedPlatforms.value.indexOf(platform);
    if (idx === -1) {
        selectedPlatforms.value.push(platform);
    } else if (selectedPlatforms.value.length > 1) {
        selectedPlatforms.value.splice(idx, 1);
    }
}

const nextBtnLabel = computed(() => {
    if (wizardStep.value === 2 && selectedFormat.value === 'video' && videoSubStep.value === 'avatar') return 'Choose voice →';
    if (wizardStep.value === 2 && selectedFormat.value === 'video') return 'Write script →';
    if (wizardStep.value === 2) return 'Platform settings →';
    if (wizardStep.value === 3 && isVideoFormat.value) return 'Platform settings →';
    return 'Next →';
});

async function generateVideoScript(): Promise<void> {
    if (!topicInput.value.trim() || generatingScript.value) return;

    generatingScript.value = true;

    try {
        const response = await fetch(props.routes.scriptGenerate, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                topic: topicInput.value.trim(),
                generation_context: {
                    context: campaignContext.value || topicContext.value || undefined,
                },
                cta_label: ctaLabelInput.value || undefined,
                cta_url: ctaUrlInput.value || undefined,
            }),
        });

        if (!response.ok) {
            throw new Error(`Script request failed (${response.status})`);
        }

        const data = await response.json() as { script?: string };
        if (!data.script?.trim()) {
            throw new Error('Empty script response');
        }

        videoScript.value = data.script.trim();
    } catch {
        toast.error('Could not generate video script. Try again.');
    } finally {
        generatingScript.value = false;
    }
}

// ─── Form submission ─────────────────────────────────────────────────────────
const createForm = useForm<CreatePostFormData>({
    topic: '',
    content_type: 'image',
    platforms: [],
    publish_mode: 'approve_first',
    cta_label: '',
    cta_url: '',
    generation_context: {},
    auto_generate: true,
});

function resolveContentType(): 'text' | 'image' | 'video' | 'email' {
    if (selectedFormat.value === 'video') return 'video';
    if (selectedFormat.value === 'email') return 'email';
    if (includeImage.value) return 'image';
    return 'text';
}

function createPost(): void {
    createForm.topic = topicInput.value.trim();
    createForm.content_type = resolveContentType();
    createForm.platforms = selectedFormat.value === 'email' ? [] : [...selectedPlatforms.value];
    createForm.publish_mode = publishMode.value;
    createForm.cta_label = ctaLabelInput.value;
    createForm.cta_url = ctaUrlInput.value;
    createForm.generation_context = {
        context: campaignContext.value || undefined,
        include_text: includeText.value,
        include_image: includeImage.value,
        avatar_id: selectedAvatarId.value || undefined,
        voice_id: selectedVoiceId.value || undefined,
        email_type: selectedFormat.value === 'email' ? selectedEmailType.value : undefined,
    };
    createForm.text_body = selectedFormat.value === 'video' ? videoScript.value.trim() : undefined;

    createForm.post(props.routes.store, {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            toast.success('Post created — content is generating in the background.');
        },
    });
}

function generateTopics(): void {
    generatingTopics.value = true;
    router.post(props.routes.topicsGenerate, {
        count: 15,
        context: topicContext.value || undefined,
    }, {
        preserveScroll: true,
        onFinish: () => { generatingTopics.value = false; },
    });
}

// ─── Filters ─────────────────────────────────────────────────────────────────
const filterStatus   = ref(props.filters.status   ?? '');
const filterType     = ref(props.filters.type     ?? '');
const filterPlatform = ref(props.filters.platform ?? '');
const filterSearch   = ref(props.filters.search   ?? '');

let filterTimer: ReturnType<typeof setTimeout> | undefined;
watch([filterStatus, filterType, filterPlatform, filterSearch], () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
        router.get(`/funnels/${props.funnel.id}/promotion/posts`, {
            status:   filterStatus.value   || undefined,
            type:     filterType.value     || undefined,
            platform: filterPlatform.value || undefined,
            search:   filterSearch.value   || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
});

// ─── Post list actions ────────────────────────────────────────────────────────
const selectedIds     = ref<number[]>([]);
const bulkAction      = ref<'publish' | 'schedule' | 'duplicate' | 'delete'>('publish');
const bulkScheduledFor = ref('');
const editingPostId   = ref<number | null>(null);
const editText        = ref('');
const editSubject     = ref('');
const previewPost     = ref<PromotionPost | null>(null);

function toggleSelected(id: number): void {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((x) => x !== id)
        : [...selectedIds.value, id];
}

function toggleSelectAll(): void {
    const pageIds = props.posts.data.map((p) => p.id);
    const allSelected = pageIds.every((id) => selectedIds.value.includes(id));
    selectedIds.value = allSelected ? [] : [...pageIds];
}

function runBulkAction(): void {
    if (selectedIds.value.length === 0) return;
    if (bulkAction.value === 'delete' && !confirm(`Delete ${selectedIds.value.length} posts?`)) return;
    router.post(props.routes.bulk, {
        ids: selectedIds.value,
        action: bulkAction.value,
        scheduled_for: bulkAction.value === 'schedule' && bulkScheduledFor.value
            ? new Date(bulkScheduledFor.value).toISOString()
            : undefined,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; bulkScheduledFor.value = ''; },
    });
}

function startEdit(post: PromotionPost): void {
    editingPostId.value = editingPostId.value === post.id ? null : post.id;
    editText.value    = post.text_body     ?? '';
    editSubject.value = post.email_subject ?? '';
}

function saveEdit(post: PromotionPost): void {
    router.patch(`/funnels/${props.funnel.id}/promotion/posts/${post.id}`, {
        text_body:     editText.value    || undefined,
        email_subject: editSubject.value || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingPostId.value = null; toast.success('Post updated.'); },
    });
}

const updatingPlatformsId = ref<number | null>(null);

function togglePostPlatform(post: PromotionPost, platform: string): void {
    if (post.status === 'publishing' || post.status === 'published') return;

    const current = [...(post.platforms ?? [])];
    const idx = current.indexOf(platform);

    if (idx === -1) {
        if (current.length >= 3) {
            toast.error('Maximum 3 platforms per post.');
            return;
        }
        current.push(platform);
    } else {
        if (current.length <= 1) {
            toast.error('At least one platform is required.');
            return;
        }
        current.splice(idx, 1);
    }

    updatingPlatformsId.value = post.id;
    router.patch(`/funnels/${props.funnel.id}/promotion/posts/${post.id}`, {
        platforms: current,
    }, {
        preserveScroll: true,
        onFinish: () => { updatingPlatformsId.value = null; },
        onSuccess: () => toast.success('Platforms updated.'),
    });
}

function generate(post: PromotionPost): void {
    const types: string[] = [];
    const ctx = (post.generation_context ?? {}) as Record<string, unknown>;
    if (post.content_type === 'video')      types.push('video');
    else if (post.content_type === 'email') types.push('text');
    else if (post.content_type === 'image') {
        if (ctx.include_text !== false) types.push('text');
        types.push('image');
    } else {
        types.push('text');
    }

    router.post(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/generate-assets`, {
        types,
        wait_for_video: post.content_type === 'video',
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Regenerating content in background…'),
    });
}

function publish(post: PromotionPost): void {
    router.post(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/publish`, { sync: false }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Queued for publishing.'),
    });
}

function destroy(post: PromotionPost): void {
    if (!confirm('Delete this post?')) return;
    router.delete(`/funnels/${props.funnel.id}/promotion/posts/${post.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Post deleted.'),
    });
}

function schedule(post: PromotionPost, value: string): void {
    if (!value) return;
    router.patch(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/schedule`, {
        scheduled_for: new Date(value).toISOString(),
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'UTC',
    }, { preserveScroll: true });
}

// ─── Display helpers ──────────────────────────────────────────────────────────
function statusMeta(s: string) {
    const map: Record<string, { label: string; dot: string; text: string }> = {
        published:  { label: 'Published',   dot: 'bg-emerald-500',              text: 'text-emerald-600 dark:text-emerald-400' },
        scheduled:  { label: 'Scheduled',   dot: 'bg-blue-500',                 text: 'text-blue-600 dark:text-blue-400'       },
        failed:     { label: 'Failed',      dot: 'bg-rose-500',                 text: 'text-rose-600 dark:text-rose-400'       },
        generating: { label: 'Generating…', dot: 'bg-amber-500 animate-pulse',  text: 'text-amber-600 dark:text-amber-400'     },
        publishing: { label: 'Publishing…', dot: 'bg-amber-500 animate-pulse',  text: 'text-amber-600 dark:text-amber-400'     },
        ready:      { label: 'Ready',       dot: 'bg-primary',                  text: 'text-primary'                           },
        draft:      { label: 'Draft',       dot: 'bg-muted-foreground',         text: 'text-muted-foreground'                  },
    };
    return map[s] ?? map['draft'];
}

function typeIcon(t: string): string {
    return { video: 'heroicons:video-camera', email: 'heroicons:envelope', image: 'heroicons:photo', text: 'heroicons:document-text' }[t] ?? 'heroicons:document-text';
}

function typeColorClass(t: string): string {
    return { video: 'text-amber-500 bg-amber-500/10', email: 'text-purple-500 bg-purple-500/10', image: 'text-primary bg-primary/10', text: 'text-blue-500 bg-blue-500/10' }[t] ?? 'text-muted-foreground bg-muted';
}

function fmtDate(v: string | null): string {
    if (!v) return '';
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

const suggestions = computed(() => props.suggestedTopics ?? []);

const statItems = computed(() => [
    { key: 'Total',     val: props.stats.total,     color: '' },
    { key: 'Draft',     val: props.stats.draft,     color: '' },
    { key: 'Scheduled', val: props.stats.scheduled, color: 'text-blue-600' },
    { key: 'Published', val: props.stats.published, color: 'text-emerald-600' },
    { key: 'Failed',    val: props.stats.failed,    color: 'text-rose-600' },
]);
</script>

<template>
    <Head :title="`Promotion – ${funnel.name}`" />

    <!-- ── Create post modal ──────────────────────────────────────────────── -->
    <Dialog v-model:open="dialogOpen">
        <DialogContent
            class="max-w-2xl max-h-[90vh] flex flex-col gap-0 p-0 overflow-hidden"
            @pointer-down-outside="(event) => event.preventDefault()"
            @interact-outside="(event) => event.preventDefault()"
        >
            <DialogHeader class="shrink-0 px-5 pt-5 pb-4 border-b">
                <DialogTitle class="text-base">New promotion post</DialogTitle>
                <!-- Step progress -->
                <div class="flex items-center mt-3">
                    <template v-for="(s, idx) in wizardStepNumbers" :key="s">
                        <button
                            type="button"
                            class="flex items-center gap-1.5 text-xs font-medium transition-colors"
                            :class="wizardStep === s ? 'text-foreground' : wizardStep > s ? 'text-primary cursor-pointer hover:opacity-80' : 'text-muted-foreground/40 cursor-default'"
                            @click="goStep(s)"
                        >
                            <span
                                class="flex size-5 items-center justify-center rounded-full text-[0.6rem] font-bold border transition-all"
                                :class="wizardStep === s
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : wizardStep > s
                                        ? 'border-primary/40 bg-primary/10 text-primary'
                                        : 'border-border bg-background'"
                            >
                                <Icon v-if="wizardStep > s" icon="heroicons:check" class="size-2.5" />
                                <span v-else>{{ s }}</span>
                            </span>
                            <span>{{ stepDisplayLabel(s) }}</span>
                        </button>
                        <div v-if="idx < wizardStepNumbers.length - 1" class="mx-2.5 h-px flex-1 bg-border transition-colors" :class="wizardStep > s ? 'bg-primary/30' : ''" />
                    </template>
                </div>
            </DialogHeader>

            <!-- ── Step 1: Topic ──────────────────────────────────────── -->
            <div v-if="wizardStep === 1" class="flex flex-1 min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 px-5 pt-5 pb-3 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">What do you want to post about?</h3>
                        <p class="text-xs text-muted-foreground">Type your topic, or pick from AI-generated suggestions below.</p>
                    </div>

                    <div class="relative">
                        <Input
                            v-model="topicInput"
                            class="h-11 text-sm pr-10"
                            placeholder="e.g. How to grow your email list fast in 2026"
                            @keydown.enter="nextStep"
                        />
                        <button
                            v-if="topicInput"
                            type="button"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            @click="topicInput = ''"
                        >
                            <Icon icon="heroicons:x-mark" class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Scrollable suggestions -->
                <div class="flex-1 min-h-0 overflow-y-auto px-5 pb-4">
                    <div class="rounded-xl border border-dashed flex flex-col min-h-0">
                        <div class="shrink-0 p-4 space-y-3 border-b border-dashed border-border/60">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold">AI Topic Suggestions</p>
                                    <p class="text-xs text-muted-foreground">Based on your funnel. Click any topic to use it.</p>
                                </div>
                                <Button size="sm" variant="outline" class="h-7 text-xs gap-1.5 shrink-0" :disabled="generatingTopics" @click="generateTopics">
                                    <Icon :icon="generatingTopics ? 'heroicons:arrow-path' : 'heroicons:sparkles'" class="size-3.5" :class="generatingTopics ? 'animate-spin' : ''" />
                                    {{ generatingTopics ? 'Generating…' : 'Generate ideas' }}
                                </Button>
                            </div>
                            <Input v-model="topicContext" class="h-8 text-xs" placeholder="Optional context (audience, niche, offer…)" />
                        </div>
                        <div class="overflow-y-auto p-4">
                            <div v-if="suggestions.length === 0" class="py-8 text-center text-xs text-muted-foreground">
                                No suggestions yet — click "Generate ideas" above.
                            </div>
                            <div v-else class="grid gap-1.5 sm:grid-cols-2">
                                <button
                                    v-for="s in suggestions"
                                    :key="s.id"
                                    type="button"
                                    class="group rounded-lg border p-2.5 text-left transition-all hover:border-primary/50 hover:bg-primary/5"
                                    :class="topicInput === s.topic ? 'border-primary/60 bg-primary/5' : 'border-border'"
                                    @click="selectTopic(s.topic)"
                                >
                                    <p class="text-xs font-medium leading-snug group-hover:text-primary">{{ s.topic }}</p>
                                    <p v-if="s.angle" class="mt-0.5 text-[0.6rem] text-muted-foreground truncate">{{ s.angle }}</p>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Step 2: Format ─────────────────────────────────────── -->
            <div v-else-if="wizardStep === 2" class="flex flex-1 min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 px-5 pt-5 pb-3 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Choose your content format</h3>
                        <p class="text-xs text-muted-foreground">Topic: <span class="font-medium text-foreground">{{ topicInput }}</span></p>
                    </div>

                    <!-- Format cards -->
                    <div class="grid grid-cols-3 gap-2.5">
                        <button
                            v-for="fmt in [
                                { key: 'post',  icon: 'heroicons:photo',         label: 'Post',  sub: 'Text + Image', color: 'text-primary',      badge: 'Recommended' },
                                { key: 'video', icon: 'heroicons:video-camera',  label: 'Video', sub: 'Avatar video', color: 'text-amber-500',    badge: '' },
                                { key: 'email', icon: 'heroicons:envelope',      label: 'Email', sub: 'Email copy',   color: 'text-purple-500',   badge: '' },
                            ]"
                            :key="fmt.key"
                            type="button"
                            class="relative rounded-xl border p-4 text-center transition-all"
                            :class="selectedFormat === fmt.key ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/30' : 'border-border hover:bg-muted/30'"
                            @click="selectedFormat = fmt.key as Format; videoSubStep = 'avatar'"
                        >
                            <span v-if="fmt.badge" class="absolute -top-2 left-1/2 -translate-x-1/2 rounded-full bg-primary px-2 py-px text-[0.5rem] font-bold text-primary-foreground uppercase tracking-wide">
                                {{ fmt.badge }}
                            </span>
                            <Icon :icon="fmt.icon" class="size-6 mx-auto mb-1.5" :class="selectedFormat === fmt.key ? fmt.color : 'text-muted-foreground'" />
                            <p class="text-xs font-semibold">{{ fmt.label }}</p>
                            <p class="text-[0.6rem] text-muted-foreground">{{ fmt.sub }}</p>
                        </button>
                    </div>

                    <!-- Post: text + image toggles -->
                    <div v-if="selectedFormat === 'post'" class="rounded-xl border bg-muted/20 p-4 space-y-2.5">
                        <p class="text-xs font-semibold">What to generate</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center gap-2.5 rounded-lg border p-3 transition-all"
                                :class="includeText ? 'border-blue-500/50 bg-blue-500/5' : 'border-border opacity-60'"
                                @click="toggleIncludeText"
                            >
                                <div class="size-8 rounded-lg flex items-center justify-center shrink-0" :class="includeText ? 'bg-blue-500/15 text-blue-500' : 'bg-muted text-muted-foreground'">
                                    <Icon icon="heroicons:document-text" class="size-4" />
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-semibold">AI Text</p>
                                    <p class="text-[0.6rem] text-muted-foreground">Caption + hashtags</p>
                                </div>
                                <span class="ml-auto size-4 rounded-full border-2 flex items-center justify-center shrink-0" :class="includeText ? 'border-blue-500 bg-blue-500' : 'border-muted-foreground'">
                                    <Icon v-if="includeText" icon="heroicons:check" class="size-2.5 text-white" />
                                </span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-2.5 rounded-lg border p-3 transition-all"
                                :class="includeImage ? 'border-primary/50 bg-primary/5' : 'border-border opacity-60'"
                                @click="toggleIncludeImage"
                            >
                                <div class="size-8 rounded-lg flex items-center justify-center shrink-0" :class="includeImage ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                    <Icon icon="heroicons:photo" class="size-4" />
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-semibold">AI Image</p>
                                    <p class="text-[0.6rem] text-muted-foreground">GPT-generated visual</p>
                                </div>
                                <span class="ml-auto size-4 rounded-full border-2 flex items-center justify-center shrink-0" :class="includeImage ? 'border-primary bg-primary' : 'border-muted-foreground'">
                                    <Icon v-if="includeImage" icon="heroicons:check" class="size-2.5 text-white" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Video step headers (fixed) -->
                    <div v-if="selectedFormat === 'video' && videoSubStep === 'avatar'">
                        <p class="text-xs font-semibold">Choose your presenter</p>
                        <p class="text-xs text-muted-foreground">Select the AI presenter who will deliver your video.</p>
                    </div>
                    <div v-if="selectedFormat === 'video' && videoSubStep === 'voice'" class="flex items-center gap-3">
                        <div class="size-10 rounded-lg overflow-hidden shrink-0 bg-muted border">
                            <img
                                v-if="AVATARS.find(a => a.id === selectedAvatarId)?.thumbnail_url"
                                :src="AVATARS.find(a => a.id === selectedAvatarId)?.thumbnail_url"
                                class="w-full h-full object-cover" alt=""
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-sm font-bold text-muted-foreground">
                                {{ AVATARS.find(a => a.id === selectedAvatarId)?.name?.[0] ?? '?' }}
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-semibold">{{ AVATARS.find(a => a.id === selectedAvatarId)?.name ?? 'Presenter' }} · Choose a voice</p>
                        </div>
                    </div>

                    <!-- Email options -->
                    <div v-if="selectedFormat === 'email'" class="rounded-xl border bg-muted/20 p-4 space-y-3">
                        <p class="text-xs font-semibold">Email type</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="opt in EMAIL_TYPE_OPTIONS"
                                :key="opt.key"
                                type="button"
                                class="rounded-lg border p-2.5 text-left transition-all"
                                :class="selectedEmailType === opt.key
                                    ? 'border-primary/50 bg-primary/10 text-foreground'
                                    : 'border-border/60 bg-card text-muted-foreground hover:bg-muted/40 hover:text-foreground'"
                                @click="selectedEmailType = opt.key"
                            >
                                <p class="text-xs font-semibold">{{ opt.label }}</p>
                                <p class="text-[0.65rem] mt-0.5 leading-snug opacity-80">{{ opt.description }}</p>
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">The full email body will be generated based on your topic, funnel context, and selected email type.</p>
                    </div>
                </div>

                <!-- Scrollable: avatars or voices -->
                <div
                    v-if="selectedFormat === 'video' && (videoSubStep === 'avatar' || videoSubStep === 'voice')"
                    class="flex-1 min-h-0 overflow-y-auto px-5 pb-4"
                >
                    <!-- Avatar grid -->
                    <div v-if="videoSubStep === 'avatar'" class="rounded-xl border bg-muted/20 p-4">
                        <div v-if="!videoEnabled" class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 space-y-1">
                            <p class="font-semibold flex items-center gap-1.5"><Icon icon="heroicons:exclamation-triangle" class="size-3.5" /> D-ID video not configured</p>
                            <p>Add your <code class="font-mono bg-amber-500/10 px-1 rounded">DID_API_KEY</code> and set <code class="font-mono bg-amber-500/10 px-1 rounded">DID_ENABLED=true</code> in your <code class="font-mono bg-amber-500/10 px-1 rounded">.env</code> to enable video generation.</p>
                        </div>
                        <div v-else-if="AVATARS.length === 0" class="text-xs text-muted-foreground italic py-2">No presenters loaded — check your D-ID API key.</div>
                        <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                            <button
                                v-for="av in AVATARS"
                                :key="av.id"
                                type="button"
                                class="rounded-xl border overflow-hidden text-center transition-all focus:outline-none"
                                :class="selectedAvatarId === av.id ? 'border-primary/60 ring-2 ring-primary/30' : 'border-border hover:border-primary/30'"
                                @click="selectedAvatarId = av.id"
                            >
                                <div class="relative aspect-square overflow-hidden bg-muted">
                                    <img
                                        v-if="av.thumbnail_url"
                                        :src="av.thumbnail_url"
                                        :alt="av.name"
                                        class="w-full h-full object-cover"
                                    />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xl font-bold text-muted-foreground/40">
                                        {{ av.name[0] }}
                                    </div>
                                    <div v-if="selectedAvatarId === av.id" class="absolute inset-0 bg-primary/10 flex items-center justify-center">
                                        <span class="size-5 rounded-full bg-primary flex items-center justify-center">
                                            <Icon icon="heroicons:check" class="size-3 text-white" />
                                        </span>
                                    </div>
                                </div>
                                <p class="text-[0.65rem] font-semibold py-1.5 px-1 truncate">{{ av.name }}</p>
                            </button>
                        </div>
                    </div>

                    <!-- Voice list -->
                    <div v-else-if="videoSubStep === 'voice'" class="rounded-xl border bg-muted/20 p-4 space-y-1.5">
                        <div
                            v-for="v in VOICES"
                            :key="v.id"
                            role="button"
                            tabindex="0"
                            class="w-full flex items-center gap-3 rounded-lg border p-2.5 text-left transition-all cursor-pointer"
                            :class="selectedVoiceId === v.id ? 'border-primary/60 bg-primary/5' : 'border-border hover:bg-muted/30'"
                            @click="selectedVoiceId = v.id"
                            @keydown.enter="selectedVoiceId = v.id"
                        >
                            <button
                                type="button"
                                class="size-8 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                                :class="playingVoiceId === v.id
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-muted hover:bg-muted/80 text-muted-foreground hover:text-foreground'"
                                :title="playingVoiceId === v.id ? 'Stop preview' : 'Listen to voice'"
                                :disabled="loadingVoicePreview !== null && loadingVoicePreview !== v.id"
                                @click="playVoicePreview(v, $event)"
                            >
                                <Icon
                                    v-if="loadingVoicePreview === v.id"
                                    icon="heroicons:arrow-path"
                                    class="size-3.5 animate-spin"
                                />
                                <Icon
                                    v-else-if="playingVoiceId === v.id"
                                    icon="heroicons:stop"
                                    class="size-3.5"
                                />
                                <Icon
                                    v-else
                                    icon="heroicons:play"
                                    class="size-3.5 ml-0.5"
                                />
                            </button>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold">{{ v.name }}</p>
                                <p class="text-[0.6rem] text-muted-foreground">{{ v.lang }} · {{ v.style }}</p>
                            </div>
                            <span v-if="selectedVoiceId === v.id" class="size-4 rounded-full bg-primary flex items-center justify-center shrink-0">
                                <Icon icon="heroicons:check" class="size-2.5 text-white" />
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Step 3 (video): Script ───────────────────────────── -->
            <div v-else-if="wizardStep === 3 && isVideoFormat" class="flex flex-1 min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 px-5 pt-5 pb-3 space-y-2">
                    <h3 class="text-sm font-semibold">Video script</h3>
                    <p class="text-xs text-muted-foreground">
                        This is what your avatar will say. Edit it before generating the video.
                    </p>
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs text-muted-foreground">
                            Topic: <span class="font-medium text-foreground">{{ topicInput }}</span>
                        </p>
                        <Button size="sm" variant="outline" class="h-7 text-xs gap-1.5 shrink-0" :disabled="generatingScript" @click="generateVideoScript">
                            <Icon :icon="generatingScript ? 'heroicons:arrow-path' : 'heroicons:sparkles'" class="size-3.5" :class="generatingScript ? 'animate-spin' : ''" />
                            {{ generatingScript ? 'Generating…' : videoScript ? 'Regenerate' : 'Generate script' }}
                        </Button>
                    </div>
                </div>
                <div class="flex-1 min-h-0 overflow-y-auto px-5 pb-5">
                    <Textarea
                        v-model="videoScript"
                        class="min-h-[220px] text-sm leading-relaxed"
                        placeholder="Generate a spoken script for your avatar, or write your own…"
                        maxlength="800"
                    />
                    <p class="mt-2 text-[0.65rem] text-muted-foreground">
                        {{ videoScript.length }}/800 characters · aim for 45–60 seconds when spoken
                    </p>
                </div>
            </div>

            <!-- ── Launch step ─────────────────────────────────────── -->
            <div v-else-if="wizardStep === launchWizardStep" class="flex-1 min-h-0 overflow-y-auto px-5 py-5 space-y-5">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Where & how to publish</h3>
                        <p class="text-xs text-muted-foreground">Topic: <span class="font-medium text-foreground">{{ topicInput }}</span></p>
                    </div>

                    <!-- Email audience notice (replaces platform picker) -->
                    <div v-if="selectedFormat === 'email'" class="flex items-start gap-3 rounded-xl border border-purple-500/20 bg-purple-500/5 px-4 py-3">
                        <Icon icon="heroicons:envelope" class="size-4 text-purple-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-xs font-semibold text-purple-700 dark:text-purple-400">Sends to your leads list</p>
                            <p class="text-xs text-muted-foreground mt-1">This email will be delivered to all leads captured through this funnel. You can schedule it below or publish immediately from the posts list.</p>
                        </div>
                    </div>

                    <!-- Platforms (social posts only) -->
                    <div v-else class="space-y-2">
                        <Label class="text-xs font-semibold">Publish to</Label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="platform in availablePlatforms"
                                :key="platform"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border px-3.5 py-2 text-xs font-medium transition-all"
                                :class="selectedPlatforms.includes(platform)
                                    ? 'border-primary/50 bg-primary/10 text-primary'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted'"
                                @click="togglePlatform(platform)"
                            >
                                <Icon :icon="PLATFORM_META[platform]?.icon ?? 'heroicons:share'" class="size-3.5" />
                                {{ PLATFORM_META[platform]?.label ?? platform }}
                                <span v-if="selectedPlatforms.includes(platform)" class="size-3.5 rounded-full bg-primary flex items-center justify-center">
                                    <Icon icon="heroicons:check" class="size-2 text-white" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Publish mode -->
                    <div class="space-y-2">
                        <Label class="text-xs font-semibold">Publish mode</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                class="rounded-lg border p-3 text-left transition-all"
                                :class="publishMode === 'approve_first' ? 'border-primary/50 bg-primary/5' : 'border-border hover:bg-muted/30'"
                                @click="publishMode = 'approve_first'"
                            >
                                <div class="flex items-center gap-1.5 mb-1">
                                    <Icon icon="heroicons:eye" class="size-3.5" :class="publishMode === 'approve_first' ? 'text-primary' : 'text-muted-foreground'" />
                                    <span class="text-xs font-semibold">Review first</span>
                                </div>
                                <p class="text-[0.6rem] text-muted-foreground">You approve before it goes live.</p>
                            </button>
                            <button type="button"
                                class="rounded-lg border p-3 text-left transition-all"
                                :class="publishMode === 'auto_publish' ? 'border-primary/50 bg-primary/5' : 'border-border hover:bg-muted/30'"
                                @click="publishMode = 'auto_publish'"
                            >
                                <div class="flex items-center gap-1.5 mb-1">
                                    <Icon icon="heroicons:bolt" class="size-3.5" :class="publishMode === 'auto_publish' ? 'text-primary' : 'text-muted-foreground'" />
                                    <span class="text-xs font-semibold">Auto-publish</span>
                                </div>
                                <p class="text-[0.6rem] text-muted-foreground">Publishes automatically when content is ready.</p>
                            </button>
                        </div>
                    </div>

                    <!-- Optional context / CTA -->
                    <div class="rounded-xl border border-dashed">
                        <button type="button" class="w-full flex items-center justify-between p-3.5 text-xs font-medium" @click="showCta = !showCta">
                            <span class="flex items-center gap-1.5">
                                <Icon icon="heroicons:adjustments-horizontal" class="size-3.5 text-muted-foreground" />
                                Add context, goal or CTA overrides (optional)
                            </span>
                            <Icon :icon="showCta ? 'heroicons:chevron-up' : 'heroicons:chevron-down'" class="size-3.5 text-muted-foreground" />
                        </button>
                        <div v-if="showCta" class="px-4 pb-4 space-y-3 border-t">
                            <div class="space-y-1 pt-3">
                                <Label class="text-xs">Campaign context</Label>
                                <Textarea v-model="campaignContext" class="min-h-[72px] text-sm" placeholder="Audience, offer angle, tone, constraints…" />
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <Label class="text-xs">CTA label override</Label>
                                    <Input v-model="ctaLabelInput" class="h-9 text-sm" placeholder="Watch free webinar" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">CTA URL override</Label>
                                    <Input v-model="ctaUrlInput" class="h-9 text-sm" placeholder="https://…" />
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

            <!-- ── Modal footer (always visible) ─────────────────────────── -->
            <div class="shrink-0 flex items-center justify-between border-t px-5 py-4 bg-muted/10">
                <Button
                    v-if="wizardStep > 1"
                    size="sm"
                    variant="outline"
                    class="h-9 text-sm gap-1.5"
                    :disabled="createForm.processing"
                    @click="prevStep"
                >
                    <Icon icon="heroicons:arrow-left" class="size-3.5" />
                    Back
                </Button>
                <div v-else />

                <Button
                    v-if="wizardStep < launchWizardStep"
                    size="sm"
                    class="h-9 text-sm gap-1.5 bg-primary text-primary-foreground hover:opacity-90 ml-auto"
                    :disabled="!canAdvance() || generatingScript"
                    @click="nextStep"
                >
                    {{ nextBtnLabel }}
                    <Icon icon="heroicons:arrow-right" class="size-3.5" />
                </Button>

                <Button
                    v-else
                    size="sm"
                    class="h-9 text-sm gap-1.5 bg-primary text-primary-foreground hover:opacity-90 ml-auto"
                    :disabled="createForm.processing || (selectedFormat !== 'email' && selectedPlatforms.length === 0)"
                    @click="createPost"
                >
                    <Icon v-if="createForm.processing" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                    <Icon v-else icon="heroicons:sparkles" class="size-3.5" />
                    {{ createForm.processing ? 'Creating…' : 'Generate & Create' }}
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- ── Page ──────────────────────────────────────────────────────────── -->
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 px-4 py-6 md:px-6">

        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors">Funnels</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors truncate max-w-[160px]">{{ funnel.name }}</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <span class="text-foreground font-medium">Promotion Posts</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight">Promotion Posts</h1>
                <p class="text-sm text-muted-foreground">Create and schedule rich social campaigns for this funnel.</p>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child size="sm" variant="outline" class="h-8 text-xs gap-1.5">
                    <Link :href="routes.calendar">
                        <Icon icon="heroicons:calendar-days" class="size-3.5" />
                        Calendar
                    </Link>
                </Button>
                <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90" @click="openDialog">
                    <Icon icon="heroicons:plus" class="size-3.5" />
                    New post
                </Button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-5 gap-2">
            <Card v-for="item in statItems" :key="item.key" class="border shadow-none">
                <CardContent class="p-3 text-center">
                    <p class="text-[0.65rem] font-medium text-muted-foreground uppercase tracking-wide">{{ item.key }}</p>
                    <p class="text-2xl font-bold mt-0.5" :class="item.color">{{ item.val }}</p>
                </CardContent>
            </Card>
        </div>

        <!-- ── Active jobs banner ────────────────────────────────────────── -->
        <div v-if="isProcessing" class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3">
            <div class="relative shrink-0">
                <div class="size-8 rounded-full bg-amber-500/15 flex items-center justify-center">
                    <Icon icon="heroicons:arrow-path" class="size-4 text-amber-600 animate-spin" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                    Content is generating in the background
                </p>
                <p class="text-xs text-amber-600/80 dark:text-amber-400/70">
                    {{ posts.data.filter(p => p.status === 'generating' || p.status === 'publishing').length }} post(s) processing.
                </p>
            </div>
            <div class="flex gap-1 shrink-0">
                <span v-for="i in 3" :key="i" class="size-1.5 rounded-full bg-amber-500 opacity-80 animate-bounce" :style="{ animationDelay: `${(i - 1) * 0.2}s` }" />
            </div>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="filterStatus" class="h-8 rounded-lg border bg-background px-3 text-xs text-muted-foreground">
                <option value="">All statuses</option>
                <option v-for="s in ['draft','generating','ready','scheduled','publishing','published','failed']" :key="s" :value="s" class="capitalize">{{ s }}</option>
            </select>
            <select v-model="filterType" class="h-8 rounded-lg border bg-background px-3 text-xs text-muted-foreground">
                <option value="">All types</option>
                <option v-for="t in ['text','image','video','email']" :key="t" :value="t" class="capitalize">{{ t }}</option>
            </select>
            <select v-model="filterPlatform" class="h-8 rounded-lg border bg-background px-3 text-xs text-muted-foreground">
                <option value="">All platforms</option>
                <option v-for="p in availablePlatforms" :key="p" :value="p" class="capitalize">{{ p }}</option>
            </select>
            <div class="relative ml-auto">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                <Input v-model="filterSearch" class="h-8 pl-7 text-xs w-52" placeholder="Search topic, text…" />
            </div>
        </div>

        <!-- ── Bulk actions ────────────────────────────────────────────── -->
        <div v-if="posts.data.length > 0" class="flex flex-wrap items-center gap-2 rounded-lg border bg-muted/20 px-3 py-2">
            <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="toggleSelectAll">
                {{ posts.data.every(p => selectedIds.includes(p.id)) ? 'Deselect all' : 'Select page' }}
            </button>
            <span v-if="selectedIds.length > 0" class="text-xs font-medium text-primary">{{ selectedIds.length }} selected</span>
            <div v-if="selectedIds.length > 0" class="flex items-center gap-2 ml-auto">
                <select v-model="bulkAction" class="h-7 rounded-md border bg-background px-2.5 text-xs">
                    <option value="publish">Publish now</option>
                    <option value="schedule">Schedule</option>
                    <option value="duplicate">Duplicate</option>
                    <option value="delete">Delete</option>
                </select>
                <Input v-if="bulkAction === 'schedule'" v-model="bulkScheduledFor" type="datetime-local" class="h-7 text-xs" />
                <Button size="sm" class="h-7 text-xs bg-primary text-primary-foreground hover:opacity-90" :disabled="bulkAction === 'schedule' && !bulkScheduledFor" @click="runBulkAction">
                    Apply
                </Button>
            </div>
        </div>

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-if="posts.data.length === 0" class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
            <div class="size-12 rounded-full bg-muted flex items-center justify-center">
                <Icon icon="heroicons:megaphone" class="size-6 text-muted-foreground" />
            </div>
            <div>
                <p class="text-sm font-semibold">No promotion posts yet</p>
                <p class="text-xs text-muted-foreground mt-0.5">Use the creator to generate your first campaign post.</p>
            </div>
            <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground" @click="openDialog">
                <Icon icon="heroicons:plus" class="size-3.5" />
                Create first post
            </Button>
        </div>

        <!-- ── Post cards grid ─────────────────────────────────────────── -->
        <div v-else class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="post in posts.data"
                :key="post.id"
                class="rounded-2xl border bg-card shadow-sm overflow-hidden flex flex-col transition-shadow hover:shadow-md"
            >
                <!-- ── Image / visual banner ───────────────────────────── -->
                <div class="relative shrink-0 overflow-hidden bg-muted/40" style="aspect-ratio: 16/9">
                    <!-- Type accent bar -->
                    <div class="absolute top-0 left-0 right-0 h-[3px] z-10" :class="{
                        'bg-primary':    post.content_type === 'image',
                        'bg-amber-500':  post.content_type === 'video',
                        'bg-purple-500': post.content_type === 'email',
                        'bg-blue-500':   post.content_type === 'text',
                    }" />

                    <!-- Generated image -->
                    <img
                        v-if="post.primary_asset?.url && post.primary_asset?.asset_type === 'image'"
                        :src="post.primary_asset.url"
                        class="w-full h-full object-cover"
                        alt=""
                    />
                    <!-- Generated video -->
                    <video
                        v-else-if="post.primary_asset?.url && post.primary_asset?.asset_type === 'video'"
                        :src="post.primary_asset.url"
                        :poster="post.primary_asset.thumbnail_url ?? undefined"
                        class="w-full h-full object-cover"
                        controls
                        preload="metadata"
                    />
                    <!-- Generating / publishing spinner -->
                    <div v-else-if="post.status === 'generating' || post.status === 'publishing'"
                         class="flex flex-col items-center justify-center h-full gap-2.5 text-muted-foreground bg-muted/20">
                        <Icon icon="heroicons:arrow-path" class="size-8 animate-spin opacity-50" />
                        <span class="text-xs font-medium">{{ post.status === 'publishing' ? 'Publishing…' : 'Generating content…' }}</span>
                    </div>
                    <!-- Placeholder icon -->
                    <div v-else class="flex flex-col items-center justify-center h-full gap-2" :class="typeColorClass(post.content_type)">
                        <Icon :icon="typeIcon(post.content_type)" class="size-10 opacity-40" />
                        <span class="text-xs font-medium capitalize opacity-60">{{ post.content_type }} post</span>
                    </div>

                    <!-- Status badge top-right -->
                    <div class="absolute top-2.5 right-2.5 z-10 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.65rem] font-semibold backdrop-blur-sm bg-background/80 shadow-sm" :class="statusMeta(post.status).text">
                        <span class="size-1.5 rounded-full inline-block shrink-0" :class="statusMeta(post.status).dot" />
                        {{ statusMeta(post.status).label }}
                    </div>

                    <!-- Checkbox top-left -->
                    <label class="absolute top-2.5 left-2.5 z-10 flex items-center justify-center size-6 rounded-lg bg-background/75 backdrop-blur-sm cursor-pointer hover:bg-background/90 transition-colors shadow-sm">
                        <input type="checkbox" :checked="selectedIds.includes(post.id)" class="rounded border-border" @change="toggleSelected(post.id)" />
                    </label>

                    <!-- Animated progress bar when processing -->
                    <div v-if="post.status === 'generating' || post.status === 'publishing'" class="absolute bottom-0 left-0 right-0 h-0.5 overflow-hidden">
                        <div class="h-full bg-amber-500" style="animation: progressBar 2s ease-in-out infinite;" />
                    </div>
                </div>

                <!-- ── Card body ────────────────────────────────────────── -->
                <div class="flex flex-col flex-1 px-4 pt-3.5 pb-3 gap-2.5">
                    <!-- Title -->
                    <h3 class="text-sm font-semibold leading-snug line-clamp-2">
                        {{ post.title || post.topic || `Post #${post.id}` }}
                    </h3>

                    <!-- Error notice -->
                    <p v-if="post.last_error" class="text-xs text-rose-500 flex items-start gap-1">
                        <Icon icon="heroicons:exclamation-triangle" class="size-3.5 shrink-0 mt-0.5" />
                        <span class="line-clamp-2">{{ post.last_error }}</span>
                    </p>

                    <!-- Text preview — 3 readable lines + Preview button -->
                    <div class="flex-1">
                        <p v-if="post.text_body" class="text-sm text-muted-foreground leading-relaxed line-clamp-3">
                            {{ post.text_body }}
                        </p>
                        <p v-else class="text-sm text-muted-foreground/50 italic">No content generated yet.</p>
                    </div>

                    <!-- Hashtags -->
                    <div v-if="post.hashtags?.length" class="flex flex-wrap gap-1">
                        <span
                            v-for="tag in (post.hashtags ?? []).slice(0, 5)"
                            :key="tag"
                            class="text-[0.68rem] font-medium text-primary/80 bg-primary/8 rounded-md px-1.5 py-0.5 border border-primary/10"
                        >{{ tag.startsWith('#') ? tag : `#${tag}` }}</span>
                        <span v-if="(post.hashtags?.length ?? 0) > 5" class="text-[0.68rem] text-muted-foreground px-1">+{{ (post.hashtags?.length ?? 0) - 5 }}</span>
                    </div>

                    <!-- Platforms (social posts only — not email) -->
                    <div v-if="post.content_type !== 'email'" class="space-y-1.5">
                        <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide">Publish to</p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="platform in availablePlatforms"
                                :key="platform"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md border px-2 py-1 text-[0.65rem] font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="(post.platforms ?? []).includes(platform)
                                    ? 'border-primary/50 bg-primary/10 text-primary'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted/40 hover:text-foreground'"
                                :disabled="updatingPlatformsId === post.id || post.status === 'publishing' || post.status === 'published'"
                                :title="(post.platforms ?? []).includes(platform) ? `Remove ${PLATFORM_META[platform]?.label ?? platform}` : `Add ${PLATFORM_META[platform]?.label ?? platform}`"
                                @click="togglePostPlatform(post, platform)"
                            >
                                <Icon
                                    :icon="updatingPlatformsId === post.id ? 'heroicons:arrow-path' : (PLATFORM_META[platform]?.icon ?? 'heroicons:share')"
                                    class="size-3.5 shrink-0"
                                    :class="updatingPlatformsId === post.id ? 'animate-spin' : ''"
                                />
                                {{ PLATFORM_META[platform]?.label ?? platform }}
                            </button>
                        </div>
                    </div>

                    <!-- Email audience notice -->
                    <div v-else class="flex items-start gap-2 rounded-lg border border-purple-500/20 bg-purple-500/5 px-3 py-2">
                        <Icon icon="heroicons:users" class="size-3.5 text-purple-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-purple-700 dark:text-purple-400">Sends to your leads list</p>
                            <p class="text-[0.6rem] text-muted-foreground mt-0.5">Schedule this email and it will be delivered to all leads captured through this funnel.</p>
                        </div>
                    </div>

                    <!-- Schedule / published date -->
                    <div v-if="post.scheduled_for || post.published_at" class="flex items-center justify-end">
                        <span v-if="post.scheduled_for" class="text-xs text-muted-foreground flex items-center gap-1">
                            <Icon icon="heroicons:calendar" class="size-3.5" />{{ fmtDate(post.scheduled_for) }}
                        </span>
                        <span v-else-if="post.published_at" class="text-xs text-emerald-600 flex items-center gap-1">
                            <Icon icon="heroicons:check-circle" class="size-3.5" />{{ fmtDate(post.published_at) }}
                        </span>
                    </div>
                </div>

                <!-- ── Action bar ───────────────────────────────────────── -->
                <div class="shrink-0 border-t border-border/50 bg-muted/10 px-4 py-2.5 space-y-2">
                    <!-- Schedule input -->
                    <input
                        type="datetime-local"
                        class="w-full h-8 rounded-lg border bg-background px-2.5 text-xs text-muted-foreground"
                        :value="post.scheduled_for ? new Date(post.scheduled_for).toISOString().slice(0, 16) : ''"
                        @change="schedule(post, ($event.target as HTMLInputElement).value)"
                    />
                    <!-- Buttons row -->
                    <div class="flex items-center gap-1.5">
                        <!-- Preview -->
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8 px-2.5 text-xs gap-1.5 text-muted-foreground hover:text-foreground"
                            :disabled="!post.text_body && !post.primary_asset?.url"
                            title="Preview full content"
                            @click="previewPost = post"
                        >
                            <Icon icon="heroicons:eye" class="size-3.5 shrink-0" />
                            Preview
                        </Button>
                        <div class="flex-1" />
                        <!-- Regen -->
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 w-8 p-0"
                            :disabled="post.status === 'generating'"
                            title="Regenerate"
                            @click="generate(post)"
                        >
                            <Icon icon="heroicons:arrow-path" class="size-3.5" :class="post.status === 'generating' ? 'animate-spin' : ''" />
                        </Button>
                        <!-- Edit -->
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 w-8 p-0"
                            :class="editingPostId === post.id ? 'border-primary/50 bg-primary/5 text-primary' : ''"
                            title="Edit"
                            @click="startEdit(post)"
                        >
                            <Icon icon="heroicons:pencil" class="size-3.5" />
                        </Button>
                        <!-- Publish -->
                        <Button
                            size="sm"
                            class="h-8 px-3 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                            @click="publish(post)"
                        >
                            <Icon icon="heroicons:paper-airplane" class="size-3.5 shrink-0" />
                            Publish
                        </Button>
                        <!-- Delete -->
                        <Button
                            size="sm"
                            variant="ghost"
                            class="h-8 w-8 p-0 text-destructive hover:bg-destructive/10 hover:text-destructive shrink-0"
                            title="Delete"
                            @click="destroy(post)"
                        >
                            <Icon icon="heroicons:trash" class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- ── Inline edit panel ────────────────────────────────── -->
                <div v-if="editingPostId === post.id" class="border-t border-border/40 bg-muted/5 p-4 space-y-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Post text</Label>
                        <Textarea v-model="editText" class="min-h-[140px] text-sm" placeholder="Edit your post content…" />
                    </div>
                    <div v-if="post.content_type === 'email'" class="space-y-1.5">
                        <Label class="text-xs font-semibold">Email subject</Label>
                        <Input v-model="editSubject" class="h-9 text-sm" />
                    </div>
                    <div v-if="post.primary_asset?.url && post.primary_asset?.asset_type === 'image'" class="space-y-1.5">
                        <Label class="text-xs font-semibold">Generated image</Label>
                        <img :src="post.primary_asset.url" class="w-full rounded-xl object-cover border border-border/50" style="max-height: 200px" alt="" />
                        <Button size="sm" variant="outline" class="h-8 text-xs w-full gap-1.5" @click="generate(post)">
                            <Icon icon="heroicons:arrow-path" class="size-3.5" />
                            Regenerate image
                        </Button>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" class="h-8 text-xs bg-primary text-primary-foreground hover:opacity-90" @click="saveEdit(post)">Save changes</Button>
                        <Button size="sm" variant="outline" class="h-8 text-xs" @click="editingPostId = null">Cancel</Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Pagination ──────────────────────────────────────────────── -->
        <div v-if="posts.last_page > 1" class="flex items-center justify-between border-t pt-4">
            <p class="text-xs text-muted-foreground">
                <template v-if="posts.from && posts.to">{{ posts.from }}–{{ posts.to }} of </template>{{ posts.total }} posts
            </p>
            <div class="flex flex-wrap items-center gap-1">
                <button
                    v-for="link in posts.links"
                    :key="`${link.label}-${link.url ?? 'x'}`"
                    type="button"
                    class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border px-1.5 text-xs transition-colors"
                    :class="link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:bg-muted disabled:opacity-40'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>

    <!-- ── Post Preview Modal ────────────────────────────────────────────── -->
    <Dialog :open="previewPost !== null" @update:open="(v) => { if (!v) previewPost = null }">
        <DialogContent class="max-w-2xl w-full p-0 overflow-hidden rounded-2xl gap-0 flex flex-col max-h-[90vh]">
            <DialogHeader class="sr-only">
                <DialogTitle>Post preview</DialogTitle>
                <DialogDescription>Full post content preview</DialogDescription>
            </DialogHeader>

            <template v-if="previewPost">
                <!-- Image — capped so it never dominates on small screens -->
                <div v-if="previewPost.primary_asset?.url && previewPost.primary_asset?.asset_type === 'image'"
                     class="w-full shrink-0 overflow-hidden bg-muted/20" style="max-height: 220px">
                    <img :src="previewPost.primary_asset.url" class="w-full h-full object-cover" alt="" />
                </div>
                <!-- Video preview -->
                <div v-else-if="previewPost.primary_asset?.url && previewPost.primary_asset?.asset_type === 'video'"
                     class="w-full shrink-0 overflow-hidden bg-black" style="max-height: 320px">
                    <video
                        :src="previewPost.primary_asset.url"
                        :poster="previewPost.primary_asset.thumbnail_url ?? undefined"
                        class="w-full h-full object-contain"
                        controls
                        preload="metadata"
                    />
                </div>
                <!-- Placeholder when no image -->
                <div v-else class="flex items-center justify-center shrink-0 bg-muted/20 py-8" :class="typeColorClass(previewPost.content_type)">
                    <Icon :icon="typeIcon(previewPost.content_type)" class="size-12 opacity-30" />
                </div>

                <!-- Scrollable content body -->
                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5 space-y-4">
                    <!-- Meta row -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-base font-bold leading-snug">
                                {{ previewPost.title || previewPost.topic || `Post #${previewPost.id}` }}
                            </h2>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="inline-flex items-center gap-1 text-xs capitalize px-2 py-0.5 rounded-full border" :class="typeColorClass(previewPost.content_type).split(' ').slice(0,2).join(' ')">
                                    <Icon :icon="typeIcon(previewPost.content_type)" class="size-3" />
                                    {{ previewPost.content_type }}
                                </span>
                                <div class="flex items-center gap-1.5">
                                    <Icon
                                        v-for="p in (previewPost.platforms ?? [])"
                                        :key="p"
                                        :icon="PLATFORM_META[p]?.icon ?? 'heroicons:share'"
                                        class="size-4 text-muted-foreground"
                                        :title="PLATFORM_META[p]?.label ?? p"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold border" :class="statusMeta(previewPost.status).text">
                            <span class="size-2 rounded-full" :class="statusMeta(previewPost.status).dot" />
                            {{ statusMeta(previewPost.status).label }}
                        </div>
                    </div>

                    <!-- Full text body -->
                    <div v-if="previewPost.text_body" class="rounded-xl bg-muted/30 p-4 max-h-72 overflow-y-auto">
                        <p class="text-sm leading-7 whitespace-pre-wrap text-foreground">{{ previewPost.text_body }}</p>
                    </div>

                    <!-- Email subject / body -->
                    <div v-if="previewPost.email_subject || previewPost.email_body" class="space-y-2">
                        <div v-if="previewPost.email_subject" class="rounded-xl border px-4 py-2.5">
                            <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide mb-0.5">Subject</p>
                            <p class="text-sm font-medium">{{ previewPost.email_subject }}</p>
                        </div>
                        <div v-if="previewPost.email_body" class="rounded-xl bg-muted/30 p-4 max-h-56 overflow-y-auto">
                            <p class="text-sm leading-7 whitespace-pre-wrap">{{ previewPost.email_body }}</p>
                        </div>
                    </div>

                    <!-- Hashtags -->
                    <div v-if="previewPost.hashtags?.length" class="flex flex-wrap gap-1.5">
                        <span
                            v-for="tag in previewPost.hashtags"
                            :key="tag"
                            class="text-xs font-medium text-primary bg-primary/8 border border-primary/15 rounded-md px-2 py-1"
                        >{{ tag.startsWith('#') ? tag : `#${tag}` }}</span>
                    </div>

                    <!-- CTA -->
                    <div v-if="previewPost.cta_url" class="flex items-center gap-3 rounded-xl border border-primary/20 bg-primary/5 px-4 py-3">
                        <Icon icon="heroicons:cursor-arrow-rays" class="size-4 text-primary shrink-0" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-primary">{{ previewPost.cta_label || 'CTA Link' }}</p>
                            <a :href="previewPost.cta_url" target="_blank" class="text-xs text-muted-foreground hover:underline truncate block">{{ previewPost.cta_url }}</a>
                        </div>
                    </div>

                    <!-- Scheduling info -->
                    <div v-if="previewPost.scheduled_for || previewPost.published_at" class="text-xs text-muted-foreground flex items-center gap-1.5">
                        <Icon icon="heroicons:calendar-days" class="size-3.5" />
                        <span v-if="previewPost.scheduled_for">Scheduled for {{ fmtDate(previewPost.scheduled_for) }}</span>
                        <span v-else-if="previewPost.published_at" class="text-emerald-600">Published {{ fmtDate(previewPost.published_at) }}</span>
                    </div>

                </div>

                <!-- Sticky action footer -->
                <div class="shrink-0 flex items-center gap-2 px-6 py-3.5 border-t border-border/50 bg-background">
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 text-xs gap-1.5"
                        :disabled="previewPost.status === 'generating'"
                        @click="generate(previewPost); previewPost = null"
                    >
                        <Icon icon="heroicons:arrow-path" class="size-3.5" />
                        Regenerate
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 text-xs gap-1.5"
                        @click="startEdit(previewPost); previewPost = null"
                    >
                        <Icon icon="heroicons:pencil" class="size-3.5" />
                        Edit
                    </Button>
                    <Button
                        size="sm"
                        class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 ml-auto"
                        @click="publish(previewPost); previewPost = null"
                    >
                        <Icon icon="heroicons:paper-airplane" class="size-3.5" />
                        Publish now
                    </Button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes progressBar {
    0%   { width: 0%;   margin-left: 0;   }
    50%  { width: 60%;  margin-left: 20%; }
    100% { width: 0%;   margin-left: 100%; }
}
</style>
