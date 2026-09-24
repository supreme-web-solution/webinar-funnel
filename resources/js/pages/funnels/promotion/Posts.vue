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
import {
    promotionPlatformIcon,
    promotionPlatformLabel,
} from '@/lib/promotionPlatforms';
import TrafficFeatureShell from '@/components/campaign-traffic/TrafficFeatureShell.vue';
import PostFormatPreview from '@/components/promotion/PostFormatPreview.vue';
import type { CampaignHubContext } from '@/components/campaign-traffic/CampaignTrafficLayout.vue';

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
    metadata?: {
        format_key?: string;
        format_spec?: {
            key?: string;
            platform?: string;
            label?: string;
            generator?: string;
            content_type?: string;
        };
        format_payload?: {
            generator?: string;
            slides?: Array<{ headline?: string; body?: string; image_url?: string }>;
            thread_parts?: string[];
            hook?: string;
            script?: string;
            beats?: string[];
        };
        generation_progress?: {
            phase?: string;
            current?: number;
            total?: number;
            message?: string;
            updated_at?: string;
        };
        publish_result?: {
            success?: boolean;
            zernio_post_id?: string | null;
            published?: Array<{ platform?: string; external_id?: string | null; url?: string | null }>;
            failures?: Array<{ platform?: string; error?: string }>;
        };
    } | null;
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

type ConnectedPlatform = { platform: string; username: string | null; social_account_id: number };

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
    funnel: { id: number; name: string; status: string };
    posts: Paginator;
    stats: { total: number; draft: number; scheduled: number; published: number; failed: number };
    filters: { status?: string; type?: string; platform?: string; search?: string };
    suggestedTopics: TopicSuggestion[];
    connectedPlatforms?: ConnectedPlatform[];
    availablePlatforms: string[];
    socialTrafficUrl?: string;
    videoEnabled: boolean;
    availableAvatars: DIDAvatar[];
    availableVoices: DIDVoice[];
    routes: { store: string; bulk: string; calendar: string; topicsGenerate: string; scriptGenerate: string };
    defaultCta?: { url: string | null; label: string | null };
    campaignHub?: CampaignHubContext | null;
    contentFormatCatalog?: ContentFormatOption[];
    carouselLayoutTemplates?: CarouselLayoutTemplate[];
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

const generatingPosts = computed(() =>
    props.posts.data.filter((p) => p.status === 'generating' || p.status === 'publishing'),
);

const isProcessing = computed(() => generatingPosts.value.length > 0);

let pollTimer: ReturnType<typeof setInterval> | undefined;

watch(isProcessing, (active) => {
    if (active && !pollTimer) {
        pollTimer = setInterval(() => {
            router.reload({ only: ['posts', 'stats'] });
        }, 3000);
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
    content_format?: string | null;
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
        content_format?: string;
        video_render_provider?: VideoRenderProvider;
        carousel_ai_template?: boolean;
        carousel_layout_type?: string;
    };
    text_body?: string | null;
    auto_generate: boolean;
};

type CarouselLayoutTemplate = {
    key: string;
    label: string;
    description: string;
    is_dark: boolean;
};

type ContentFormatOption = {
    key: string;
    platform: string;
    platform_label: string;
    label: string;
    content_type: string;
    generator: string;
    frequency_per_week: number;
    duration_seconds?: number[] | null;
    aspect_ratio?: string | null;
    size?: string | null;
};

// ─── Modal / wizard state ────────────────────────────────────────────────────
type WizardStep = 1 | 2 | 3 | 4 | 5;
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
const selectedCatalogFormat = ref<string | null>(null);
const catalogPlatformFilter = ref<string>('all');
const carouselAiTemplate = ref(true);
const selectedCarouselLayout = ref<string | null>(null);

/** How the MP4 is produced after the script is ready — only avatar is live today. */
type VideoRenderProvider = 'avatar_did' | 'elevenlabs' | 'stock_footage' | 'ai_broll';

type VideoRenderOption = {
    key: VideoRenderProvider;
    label: string;
    description: string;
    icon: string;
    available: boolean;
    badge?: string;
};

const VIDEO_RENDER_OPTIONS: VideoRenderOption[] = [
    {
        key: 'avatar_did',
        label: 'Avatar presenter',
        description: 'Talking-head video — D-ID renders your script with a virtual presenter.',
        icon: 'heroicons:user-circle',
        available: true,
    },
    // Other render pipelines (ElevenLabs, stock, AI b-roll) — not in v1; keep types for legacy metadata only.
    // {
    //     key: 'elevenlabs',
    //     label: 'ElevenLabs voiceover',
    //     description: 'Premium AI voice narrates over visuals — no on-camera avatar.',
    //     icon: 'heroicons:microphone',
    //     available: false,
    //     badge: 'Coming soon',
    // },
    // {
    //     key: 'stock_footage',
    //     label: 'Stock footage + voice',
    //     description: 'B-roll clips matched to your script with AI narration.',
    //     icon: 'heroicons:film',
    //     available: false,
    //     badge: 'Coming soon',
    // },
    // {
    //     key: 'ai_broll',
    //     label: 'AI-generated scenes',
    //     description: 'Synthetic visuals built from your script — scene-by-scene.',
    //     icon: 'heroicons:sparkles',
    //     available: false,
    //     badge: 'Coming soon',
    // },
];

const selectedVideoRenderProvider = ref<VideoRenderProvider | null>(null);

const videoRenderOptions = computed((): VideoRenderOption[] =>
    VIDEO_RENDER_OPTIONS.map((option) => (
        option.key === 'avatar_did'
            ? { ...option, available: props.videoEnabled, badge: props.videoEnabled ? undefined : 'D-ID required' }
            : option
    )),
);

const selectedVideoRenderOption = computed(() =>
    videoRenderOptions.value.find((o) => o.key === selectedVideoRenderProvider.value) ?? null,
);

function selectVideoRenderProvider(key: VideoRenderProvider): void {
    const option = videoRenderOptions.value.find((o) => o.key === key);
    if (!option?.available) return;
    selectedVideoRenderProvider.value = key;
    if (key !== 'avatar_did') {
        selectedAvatarId.value = '';
        selectedVoiceId.value = '';
    }
    videoSubStep.value = 'avatar';
}

const catalogFormats = computed(() => props.contentFormatCatalog ?? []);
const carouselLayoutTemplates = computed(() => props.carouselLayoutTemplates ?? []);
const isCarouselFormat = computed(() => selectedCatalogSpec.value?.generator === 'carousel');

/** Social tab — carousels, threads, images, text (no video formats). */
const socialCatalogFormats = computed(() =>
    catalogFormats.value.filter((f) => f.content_type !== 'video'),
);

/** Video tab — all platform video formats from the content matrix. */
const videoCatalogFormats = computed(() =>
    catalogFormats.value.filter((f) => f.content_type === 'video'),
);

const catalogFormatsByPlatform = computed(() => {
    const map: Record<string, ContentFormatOption[]> = {};
    for (const item of socialCatalogFormats.value) {
        if (!map[item.platform]) map[item.platform] = [];
        map[item.platform].push(item);
    }
    return map;
});

const videoCatalogFormatsByPlatform = computed(() => {
    const map: Record<string, ContentFormatOption[]> = {};
    for (const item of videoCatalogFormats.value) {
        if (!map[item.platform]) map[item.platform] = [];
        map[item.platform].push(item);
    }
    return map;
});

const catalogPlatformTabs = computed(() => {
    const platforms = Object.keys(catalogFormatsByPlatform.value);
    return [{ key: 'all', label: 'All' }, ...platforms.map((p) => ({
        key: p,
        label: socialCatalogFormats.value.find((f) => f.platform === p)?.platform_label ?? p,
    }))];
});

const videoCatalogPlatformTabs = computed(() => {
    const platforms = Object.keys(videoCatalogFormatsByPlatform.value);
    return [{ key: 'all', label: 'All' }, ...platforms.map((p) => ({
        key: p,
        label: videoCatalogFormats.value.find((f) => f.platform === p)?.platform_label ?? p,
    }))];
});

const filteredCatalogFormats = computed(() => {
    if (catalogPlatformFilter.value === 'all') return socialCatalogFormats.value;
    return catalogFormatsByPlatform.value[catalogPlatformFilter.value] ?? [];
});

const filteredVideoCatalogFormats = computed(() => {
    if (catalogPlatformFilter.value === 'all') return videoCatalogFormats.value;
    return videoCatalogFormatsByPlatform.value[catalogPlatformFilter.value] ?? [];
});

const selectedCatalogSpec = computed(() =>
    catalogFormats.value.find((f) => f.key === selectedCatalogFormat.value) ?? null,
);

function catalogPlatformToPromotion(platform: string): string {
    return platform === 'twitter' ? 'twitter' : platform;
}

function promotionPlatformForFormatSpec(spec: ContentFormatOption | null): string | null {
    if (!spec) return null;
    return catalogPlatformToPromotion(spec.platform);
}

const formatLockedToPlatform = computed(
    () => selectedCatalogSpec.value !== null && selectedFormat.value !== 'email',
);

function formatCardMeta(spec: ContentFormatOption): string {
    const parts: string[] = [spec.platform_label, spec.content_type];
    if (spec.aspect_ratio) parts.push(spec.aspect_ratio);
    if (spec.size) parts.push(spec.size);
    return parts.join(' · ');
}

function videoFormatMeta(spec: ContentFormatOption): string {
    const parts: string[] = [spec.platform_label];
    if (Array.isArray(spec.duration_seconds) && spec.duration_seconds.length >= 2) {
        parts.push(`${spec.duration_seconds[0]}–${spec.duration_seconds[1]}s`);
    }
    if (spec.aspect_ratio) {
        parts.push(spec.aspect_ratio);
    }
    if (spec.size) {
        parts.push(spec.size);
    }
    return parts.join(' · ');
}

const launchPlatformRows = computed(() => {
    if (selectedFormat.value === 'email') return [];
    const required = promotionPlatformForFormatSpec(selectedCatalogSpec.value);
    if (!required) return connectedPlatformRows.value;
    return connectedPlatformRows.value.filter((row) => row.platform === required);
});

const launchPlatformMissing = computed(() => {
    const required = promotionPlatformForFormatSpec(selectedCatalogSpec.value);
    if (!required || selectedFormat.value === 'email') return false;
    return !connectedPlatformKeys.value.includes(required);
});

function applyGeneratorDefaults(spec: ContentFormatOption): void {
    const gen = spec.generator;
    if (['carousel', 'image', 'pin', 'story'].includes(gen) || (gen === 'text' && spec.content_type === 'image')) {
        includeText.value = true;
        includeImage.value = true;
    } else if (['thread', 'reel_script', 'poll', 'longform_text', 'longform_outline', 'reddit_discussion', 'reddit_ama', 'live_outline'].includes(gen)) {
        includeText.value = true;
        includeImage.value = false;
    } else if (spec.content_type === 'video') {
        includeText.value = true;
        includeImage.value = false;
    } else {
        includeText.value = true;
        includeImage.value = spec.content_type === 'image';
    }
}

function syncPlatformsForFormat(): void {
    if (selectedFormat.value === 'email') {
        selectedPlatforms.value = [];
        return;
    }

    const required = promotionPlatformForFormatSpec(selectedCatalogSpec.value);
    if (required) {
        selectedPlatforms.value = connectedPlatformKeys.value.includes(required) ? [required] : [];
        return;
    }

    if (selectedPlatforms.value.length === 0) {
        const defaults = defaultSelectedPlatforms();
        selectedPlatforms.value = defaults.length > 0 ? [defaults[0]] : [];
    }
}

function selectCatalogFormat(key: string): void {
    const next = selectedCatalogFormat.value === key ? null : key;
    selectedCatalogFormat.value = next;
    if (!next) {
        syncPlatformsForFormat();
        return;
    }

    const spec = catalogFormats.value.find((f) => f.key === key);
    if (!spec) return;

    catalogPlatformFilter.value = spec.platform;
    applyGeneratorDefaults(spec);
    syncPlatformsForFormat();
}

watch(selectedCatalogFormat, () => syncPlatformsForFormat());

function postFormatKey(post: PromotionPost): string | null {
    return post.metadata?.format_key
        ?? post.metadata?.format_spec?.key
        ?? post.generation_context?.content_format as string | undefined
        ?? null;
}

function postFormatSpec(post: PromotionPost): ContentFormatOption | null {
    const key = postFormatKey(post);
    if (key) {
        const fromCatalog = catalogFormats.value.find((f) => f.key === key);
        if (fromCatalog) return fromCatalog;
    }
    const metaSpec = post.metadata?.format_spec;
    if (metaSpec?.label && metaSpec.platform) {
        return {
            key: key ?? metaSpec.key ?? '',
            platform: metaSpec.platform,
            platform_label: catalogFormats.value.find((f) => f.platform === metaSpec.platform)?.platform_label ?? metaSpec.platform,
            label: metaSpec.label,
            content_type: metaSpec.content_type ?? 'text',
            generator: metaSpec.generator ?? 'text',
            frequency_per_week: 0,
        };
    }
    return null;
}

function postFormatLabel(post: PromotionPost): string | null {
    const spec = postFormatSpec(post);
    if (!spec) return null;
    return `${spec.platform_label} · ${spec.label}`;
}

function postFormatPayload(post: PromotionPost): NonNullable<PromotionPost['metadata']>['format_payload'] | null {
    return post.metadata?.format_payload ?? null;
}

function postCarouselSlides(post: PromotionPost): Array<{ headline?: string; body?: string; image_url?: string }> {
    const slides = postFormatPayload(post)?.slides;
    return Array.isArray(slides) ? slides : [];
}

function postGenerationProgress(post: PromotionPost) {
    return post.metadata?.generation_progress ?? null;
}

function postSlideImagesReady(post: PromotionPost): number {
    return postCarouselSlides(post).filter((s) => s.image_url).length;
}

function postGenerationProgressLabel(post: PromotionPost): string | null {
    const prog = postGenerationProgress(post);
    if (prog?.message) return prog.message;
    if (prog?.phase === 'slide_images' && prog.total) {
        const current = prog.current ?? 0;
        return current > 0
            ? `Generating slide ${current} of ${prog.total}…`
            : `Preparing ${prog.total} slide images…`;
    }
    if (post.status === 'generating' && postSlideImagesReady(post) > 0) {
        const total = prog?.total ?? postCarouselSlides(post).length;
        return `Generated ${postSlideImagesReady(post)} of ${total} slide images…`;
    }
    return null;
}

function postGenerationProgressPercent(post: PromotionPost): number | null {
    const prog = postGenerationProgress(post);
    if (!prog?.total || prog.total <= 0) return null;
    const current = prog.current ?? postSlideImagesReady(post);
    return Math.min(100, Math.round((current / prog.total) * 100));
}

function generatingStatusLabel(post: PromotionPost): string | null {
    const prog = postGenerationProgress(post);
    if (post.status !== 'generating' || prog?.phase !== 'slide_images' || !prog.total) {
        return null;
    }
    const current = Math.max(prog.current ?? 0, postSlideImagesReady(post));

    return current > 0 ? `Slide ${current}/${prog.total}` : null;
}

function postHasPartialSlidePreview(post: PromotionPost): boolean {
    return postCarouselSlides(post).some((s) => !!s.image_url)
        || (post.status === 'generating' && postCarouselSlides(post).length > 0);
}

function postThreadParts(post: PromotionPost): string[] {
    const parts = postFormatPayload(post)?.thread_parts;
    return Array.isArray(parts) ? parts.filter((p): p is string => typeof p === 'string' && p.trim() !== '') : [];
}

function postRequiredPlatform(post: PromotionPost): string | null {
    return promotionPlatformForFormatSpec(postFormatSpec(post));
}

function platformsForPost(post: PromotionPost): string[] {
    const required = postRequiredPlatform(post);
    const onPost = post.platforms ?? [];
    if (required) {
        return connectedPlatformKeys.value.includes(required)
            ? [required, ...onPost.filter((p) => p !== required)]
            : [...new Set([required, ...onPost])];
    }
    return [...new Set([...connectedPlatformKeys.value, ...onPost])];
}

function previewAspectRatio(post: PromotionPost): string | null {
    return postFormatSpec(post)?.aspect_ratio ?? null;
}

function visualPreviewKind(post: PromotionPost): 'carousel' | 'thread' | 'image' | 'video' | 'text' | 'email' {
    const payload = postFormatPayload(post);
    if (payload?.generator === 'carousel' || postCarouselSlides(post).length > 0) return 'carousel';
    if (payload?.generator === 'thread' || postThreadParts(post).length > 0) return 'thread';
    if (post.content_type === 'video') return 'video';
    if (post.content_type === 'email') return 'email';
    if (post.primary_asset?.url && post.primary_asset.asset_type === 'image') return 'image';
    if (post.content_type === 'image') return 'image';
    return 'text';
}

function setCatalogPlatformFilter(key: string): void {
    catalogPlatformFilter.value = key;
    if (key !== 'all' && selectedCatalogSpec.value && selectedCatalogSpec.value.platform !== key) {
        selectedCatalogFormat.value = null;
        syncPlatformsForFormat();
    }
}

const contentChannelHint = computed(() => {
    if (selectedFormat.value === 'email') {
        return 'Sends to your funnel leads — not posted on social media.';
    }
    if (selectedFormat.value === 'video') {
        if (selectedCatalogSpec.value) {
            return `${selectedCatalogSpec.value.platform_label} ${selectedCatalogSpec.value.label} — write script first, then choose how to render the video.`;
        }
        return 'Pick a platform video format, write the script, then choose a render method (avatar is available now).';
    }
    if (selectedCatalogSpec.value) {
        return `Social post → ${selectedCatalogSpec.value.platform_label} · ${selectedCatalogSpec.value.label}`;
    }
    return 'Pick a platform format below (carousel, thread, image…). All video formats are under Video.';
});

function selectTopFormat(key: Format): void {
    selectedFormat.value = key;
    videoSubStep.value = 'avatar';
    catalogPlatformFilter.value = 'all';
    if (key === 'post' && selectedCatalogSpec.value?.content_type === 'video') {
        selectedCatalogFormat.value = null;
    } else if (key === 'email') {
        selectedCatalogFormat.value = null;
    } else if (key === 'video') {
        if (selectedCatalogSpec.value?.content_type !== 'video') {
            selectedCatalogFormat.value = null;
        }
        selectedVideoRenderProvider.value = null;
        selectedAvatarId.value = '';
        selectedVoiceId.value = '';
    }
}

function generationOptionsLabel(): string {
    const spec = selectedCatalogSpec.value;
    if (!spec) return 'What to generate';
    if (spec.generator === 'carousel') return 'Carousel content';
    if (spec.generator === 'thread') return 'Thread content';
    if (spec.generator === 'reel_script') return 'Short-form script';
    return 'Generation options';
}

function showImageGenerationToggle(): boolean {
    const spec = selectedCatalogSpec.value;
    if (!spec) return true;
    return !['thread', 'reel_script', 'poll', 'longform_text', 'longform_outline', 'reddit_discussion', 'reddit_ama', 'live_outline'].includes(spec.generator);
}

// Step 3 (video only) – Script
const videoScript = ref('');
const generatingScript = ref(false);

// Launch step – Platforms + publish
const selectedPlatforms = ref<string[]>([]);
const publishMode = ref<'approve_first' | 'auto_publish'>('approve_first');
const campaignContext = ref('');
const ctaLabelInput = ref('');
const ctaUrlInput = ref('');
const showCta = ref(false);

const isVideoFormat = computed(() => selectedFormat.value === 'video');
const needsCarouselTemplateStep = computed(
    () => selectedFormat.value === 'post' && isCarouselFormat.value,
);
const launchWizardStep = computed<WizardStep>(() => {
    if (isVideoFormat.value) return 5;
    if (needsCarouselTemplateStep.value) return 4;
    return 3;
});
const wizardStepNumbers = computed(() => {
    if (isVideoFormat.value) return [1, 2, 3, 4, 5] as WizardStep[];
    if (needsCarouselTemplateStep.value) return [1, 2, 3, 4] as WizardStep[];
    return [1, 2, 3] as WizardStep[];
});
const wizardStepLabels: Record<WizardStep, string> = {
    1: 'Topic',
    2: 'Format',
    3: 'Script',
    4: 'Render',
    5: 'Launch',
};

function stepDisplayLabel(step: WizardStep): string {
    if (isVideoFormat.value) {
        return wizardStepLabels[step];
    }

    if (needsCarouselTemplateStep.value) {
        if (step === 3) return 'Template';
        if (step === 4) return 'Launch';
    }

    if (step === 3) return 'Launch';

    return wizardStepLabels[step];
}

function defaultSelectedPlatforms(): string[] {
    const keys = connectedPlatformRows.value.map((row) => row.platform);
    return keys.length > 0 ? [...keys] : [];
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
    selectedVideoRenderProvider.value = null;
    selectedEmailType.value = 'promotional';
    selectedCatalogFormat.value = null;
    catalogPlatformFilter.value = 'all';
    carouselAiTemplate.value = true;
    selectedCarouselLayout.value = null;
    videoScript.value = '';
    generatingScript.value = false;
    selectedPlatforms.value = [];
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

const connectedPlatformRows = computed(() => props.connectedPlatforms ?? []);
const socialTrafficUrl = computed(() => props.socialTrafficUrl ?? '/settings/social-traffic');

const isStandaloneTraffic = computed(
    () => props.campaignHub?.standalone === true || props.campaignHub?.campaign === null,
);

const showStandaloneGettingStarted = computed(
    () => isStandaloneTraffic.value && props.stats.total === 0,
);

const connectedPlatformKeys = computed(() => connectedPlatformRows.value.map((row) => row.platform));

const connectedPlatformMap = computed(() =>
    Object.fromEntries(connectedPlatformRows.value.map((row) => [row.platform, row])),
);

function canTogglePlatform(platform: string, post?: PromotionPost): boolean {
    if (!connectedPlatformKeys.value.includes(platform)) return false;
    const required = post ? postRequiredPlatform(post) : promotionPlatformForFormatSpec(selectedCatalogSpec.value);
    if (required && platform !== required) return false;
    return true;
}

function platformButtonLabel(platform: string): string {
    const username = connectedPlatformMap.value[platform]?.username;
    const base = promotionPlatformLabel(platform);
    return username ? `${base} (${username})` : base;
}

// ─── Wizard navigation ───────────────────────────────────────────────────────
function canAdvance(): boolean {
    if (wizardStep.value === 1) return topicInput.value.trim().length > 0;
    if (wizardStep.value === 2 && selectedFormat.value === 'video') {
        return selectedCatalogFormat.value !== null;
    }
    if (wizardStep.value === 2 && selectedFormat.value === 'post') {
        return includeText.value || includeImage.value;
    }
    if (wizardStep.value === 3 && needsCarouselTemplateStep.value) {
        return carouselAiTemplate.value || Boolean(selectedCarouselLayout.value);
    }
    if (wizardStep.value === 3 && isVideoFormat.value) {
        return videoScript.value.trim().length >= 20;
    }
    if (wizardStep.value === 4 && isVideoFormat.value) {
        if (!selectedVideoRenderProvider.value) return false;
        if (selectedVideoRenderProvider.value === 'avatar_did') {
            if (!props.videoEnabled) return false;
            if (videoSubStep.value === 'avatar') return selectedAvatarId.value !== '';
            return selectedAvatarId.value !== '' && selectedVoiceId.value !== '';
        }
        return false;
    }
    return true;
}

function nextStep(): void {
    if (!canAdvance()) return;
    if (wizardStep.value === 4 && isVideoFormat.value && selectedVideoRenderProvider.value === 'avatar_did' && videoSubStep.value === 'avatar') {
        videoSubStep.value = 'voice';
        return;
    }
    if (wizardStep.value < launchWizardStep.value) {
        const next = (wizardStep.value + 1) as WizardStep;
        if (next === launchWizardStep.value) {
            syncPlatformsForFormat();
        }
        wizardStep.value = next;
        if (wizardStep.value === 3 && isVideoFormat.value && !videoScript.value) {
            generateVideoScript();
        }
        if (wizardStep.value === 4 && isVideoFormat.value) {
            videoSubStep.value = 'avatar';
            if (props.videoEnabled && !selectedVideoRenderProvider.value) {
                selectedVideoRenderProvider.value = 'avatar_did';
            }
        }
    }
}

function prevStep(): void {
    if (wizardStep.value === 4 && isVideoFormat.value && selectedVideoRenderProvider.value === 'avatar_did' && videoSubStep.value === 'voice') {
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
    // Carousel slide copy is required — without it no generation jobs run.
    if (isCarouselFormat.value) {
        includeText.value = true;
        return;
    }
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
    if (formatLockedToPlatform.value) {
        if (connectedPlatformKeys.value.includes(platform)) {
            selectedPlatforms.value = [platform];
        }
        return;
    }

    const idx = selectedPlatforms.value.indexOf(platform);
    if (idx === -1) {
        selectedPlatforms.value.push(platform);
    } else if (selectedPlatforms.value.length > 1) {
        selectedPlatforms.value.splice(idx, 1);
    }
}

const nextBtnLabel = computed(() => {
    if (wizardStep.value === 2 && selectedFormat.value === 'video') return 'Write script →';
    if (wizardStep.value === 2 && needsCarouselTemplateStep.value) return 'Choose template →';
    if (wizardStep.value === 2) return 'Platform settings →';
    if (wizardStep.value === 3 && needsCarouselTemplateStep.value) return 'Platform settings →';
    if (wizardStep.value === 3 && isVideoFormat.value) return 'Choose render method →';
    if (wizardStep.value === 4 && isVideoFormat.value && selectedVideoRenderProvider.value === 'avatar_did' && videoSubStep.value === 'avatar') return 'Choose voice →';
    if (wizardStep.value === 4 && isVideoFormat.value) return 'Platform settings →';
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
                    content_format: selectedCatalogFormat.value || undefined,
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
    content_format: null,
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
    if (selectedCatalogSpec.value) {
        const t = selectedCatalogSpec.value.content_type;
        if (t === 'video' || t === 'image' || t === 'text') return t;
    }
    if (includeImage.value) return 'image';
    return 'text';
}

function createPost(): void {
    if (selectedFormat.value === 'video' && !selectedVideoRenderProvider.value) {
        toast.error('Choose how to render the video before creating the post.');
        return;
    }
    if (selectedFormat.value !== 'email' && selectedPlatforms.value.length === 0) {
        if (launchPlatformMissing.value && selectedCatalogSpec.value) {
            toast.error(`Connect ${selectedCatalogSpec.value.platform_label} in Settings to use this format.`);
        } else {
            toast.error('Select at least one connected platform.');
        }
        return;
    }
    if (isCarouselFormat.value && !carouselAiTemplate.value && !selectedCarouselLayout.value) {
        toast.error('Pick a carousel template, or turn AI template selection back on.');
        return;
    }

    createForm.topic = topicInput.value.trim();
    createForm.content_type = resolveContentType();
    createForm.content_format = selectedCatalogFormat.value;
    createForm.platforms = selectedFormat.value === 'email' ? [] : [...selectedPlatforms.value];
    createForm.publish_mode = publishMode.value;
    createForm.cta_label = ctaLabelInput.value;
    createForm.cta_url = ctaUrlInput.value;
    createForm.generation_context = {
        context: campaignContext.value || undefined,
        include_text: isCarouselFormat.value ? true : includeText.value,
        include_image: includeImage.value,
        avatar_id: selectedAvatarId.value || undefined,
        voice_id: selectedVoiceId.value || undefined,
        email_type: selectedFormat.value === 'email' ? selectedEmailType.value : undefined,
        content_format: selectedCatalogFormat.value || undefined,
        video_render_provider: selectedFormat.value === 'video' ? selectedVideoRenderProvider.value ?? undefined : undefined,
        carousel_ai_template: isCarouselFormat.value ? carouselAiTemplate.value : undefined,
        carousel_layout_type: isCarouselFormat.value && !carouselAiTemplate.value
            ? selectedCarouselLayout.value ?? undefined
            : undefined,
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

type PostsViewMode = 'card' | 'table';
const postsViewMode = ref<PostsViewMode>('card');

const filterControlClass = 'h-8 rounded-lg border border-border/60 bg-white px-3 text-xs text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-primary/20';

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
const editingPost     = ref<PromotionPost | null>(null);
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
        onSuccess: () => {
            selectedIds.value = [];
            bulkScheduledFor.value = '';
            if (bulkAction.value === 'duplicate') {
                toast.success('Posts copied — edit and publish when ready.');
            }
        },
    });
}

function duplicatePost(post: PromotionPost): void {
    router.post(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/duplicate`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Post copied — caption tweaked so you can republish within 24h.'),
    });
}

function startEdit(post: PromotionPost): void {
    editingPost.value = post;
    editText.value    = post.text_body     ?? '';
    editSubject.value = post.email_subject ?? '';
}

function closeEdit(): void {
    editingPost.value = null;
}

function saveEdit(): void {
    if (!editingPost.value) return;
    const post = editingPost.value;
    router.patch(`/funnels/${props.funnel.id}/promotion/posts/${post.id}`, {
        text_body:     editText.value    || undefined,
        email_subject: editSubject.value || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => { closeEdit(); toast.success('Post updated.'); },
    });
}

const updatingPlatformsId = ref<number | null>(null);

function formatPublishError(error: string | null | undefined): string {
    if (!error) return '';
    const lower = error.toLowerCase();
    if (error.includes('already scheduled, publishing, or was posted') || lower.includes('duplicate')) {
        return 'Blocked a duplicate: the same caption and video/image were already posted to this account in the last 24 hours. Edit the caption, regenerate the video, or wait 24 hours. Copying a post now auto-adjusts the caption — try Copy again, or edit this one manually.';
    }
    if (lower.includes('access token has expired') || lower.includes('auth_expired') || lower.includes('reconnect your account')) {
        return 'Your social account session expired. Go to Settings → Social posting, disconnect and reconnect the account, then retry publish.';
    }
    if (lower.includes('did not return a platform post id') || lower.includes('did not confirm the post')) {
        if (lower.includes('instagram')) {
            return 'Instagram did not confirm the post. Make sure Instagram is connected separately in Settings → Social posting (not just Facebook), the image meets Instagram size limits, and you have not posted the same image and caption in the last 24 hours. Then retry publish.';
        }
        return 'The platform did not confirm the post was published. It may still be processing, saved as a draft, or blocked as duplicate content. Wait a minute and use Retry publish, or edit the caption/media first.';
    }
    if (lower.includes('tiktok') && (lower.includes('90 characters') || lower.includes('slideshow title'))) {
        return 'TikTok limits photo slideshow titles to 90 characters. This post should auto-shorten on retry — click Publish again. If it still fails, shorten the post title in Edit.';
    }
    if (lower.includes('youtube') && lower.includes('100 characters')) {
        return 'YouTube limits video titles to 100 characters. This post should auto-shorten on retry — click Publish again. If it still fails, shorten the post title in Edit.';
    }
    return error;
}

function togglePostPlatform(post: PromotionPost, platform: string): void {
    if (post.status === 'publishing' || post.status === 'published') return;
    if (!canTogglePlatform(platform, post)) return;

    const required = postRequiredPlatform(post);
    if (required) {
        updatingPlatformsId.value = post.id;
        router.patch(`/funnels/${props.funnel.id}/promotion/posts/${post.id}`, {
            platforms: [required],
        }, {
            preserveScroll: true,
            onFinish: () => { updatingPlatformsId.value = null; },
        });
        return;
    }

    const current = [...(post.platforms ?? [])];
    const idx = current.indexOf(platform);

    if (idx === -1) {
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
    const provider = (post.metadata as { video_render_provider?: string })?.video_render_provider
        ?? (post.generation_context?.video_render_provider as string | undefined)
        ?? 'avatar_did';

    router.post(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/generate-assets`, {
        types: [],
        wait_for_video: post.content_type === 'video' && provider === 'avatar_did',
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Regenerating content in background…'),
    });
}

function publish(post: PromotionPost): void {
    publishingPostId.value = post.id;
    router.post(`/funnels/${props.funnel.id}/promotion/posts/${post.id}/publish`, { sync: true }, {
        preserveScroll: true,
        onSuccess: () => toast.success(needsRepublish(post) ? 'Retrying publish…' : 'Queued for publishing.'),
        onError: (errors) => {
            const msg = (errors as Record<string, string>).publish;
            if (msg) toast.error(msg);
        },
        onFinish: () => {
            publishingPostId.value = null;
        },
    });
}

const publishingPostId = ref<number | null>(null);

function isPublishingPost(post: PromotionPost): boolean {
    return publishingPostId.value === post.id || post.status === 'publishing';
}

function needsRepublish(post: PromotionPost): boolean {
    if (post.status !== 'published') return false;
    const rows = post.metadata?.publish_result?.published ?? [];
    if (rows.length === 0) {
        return post.metadata?.publish_result?.success === true;
    }
    return rows.some((row) => !row.external_id && !row.url);
}

function platformPublishUrl(post: PromotionPost, platform: string): string | null {
    const row = (post.metadata?.publish_result?.published ?? []).find((r) => r.platform === platform);
    return row?.url ?? null;
}

function canPublishPost(post: PromotionPost): boolean {
    if (post.content_type === 'email') return false;
    if (post.status === 'generating' || post.status === 'publishing') return false;
    if (needsRepublish(post)) return true;
    if (post.status === 'published') return false;
    return post.status === 'ready' || post.status === 'scheduled' || post.status === 'failed';
}

function publishButtonLabel(post: PromotionPost): string {
    return needsRepublish(post) ? 'Retry publish' : 'Publish';
}

function emailCopyText(post: PromotionPost): string {
    const subject = (post.email_subject ?? '').trim();
    const body = (post.email_body ?? post.text_body ?? '').trim();
    if (subject === '') {
        return body;
    }

    return `Subject: ${subject}\n\n${body}`;
}

function emailExportUrl(post: PromotionPost): string {
    return `/funnels/${props.funnel.id}/promotion/posts/${post.id}/email-export`;
}

async function copyEmailContent(post: PromotionPost): Promise<void> {
    const text = emailCopyText(post);
    if (!text) {
        toast.error('Generate email content first.');
        return;
    }
    try {
        await navigator.clipboard.writeText(text);
        toast.success('Email copied — paste into your ESP.');
    } catch {
        toast.error('Could not copy to clipboard.');
    }
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

function displayStatus(post: PromotionPost): string {
    if (post.status === 'published' && post.last_error) return 'partial';
    return post.status;
}

// ─── Display helpers ──────────────────────────────────────────────────────────
function statusMeta(s: string) {
    const map: Record<string, { label: string; dot: string; text: string }> = {
        published:  { label: 'Published',   dot: 'bg-blue-500',              text: 'text-blue-600 dark:text-blue-400' },
        partial:    { label: 'Partial',     dot: 'bg-amber-500',                text: 'text-amber-600 dark:text-amber-400'     },
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

function postTableDate(post: PromotionPost): string {
    if (post.scheduled_for) return fmtDate(post.scheduled_for);
    if (post.published_at) return fmtDate(post.published_at);
    return '—';
}

function postTablePreview(post: PromotionPost): string {
    const body = post.text_body?.trim();
    if (body) return body;
    if (post.email_subject) return post.email_subject;
    if (visualPreviewKind(post) === 'carousel') {
        return `${postCarouselSlides(post).length} carousel slides`;
    }
    if (postThreadParts(post).length > 0) {
        return `${postThreadParts(post).length}-part thread`;
    }
    return 'No content yet';
}

const suggestions = computed(() => props.suggestedTopics ?? []);

const statItems = computed(() => [
    { key: 'Total',     val: props.stats.total,     color: '' },
    { key: 'Draft',     val: props.stats.draft,     color: '' },
    { key: 'Scheduled', val: props.stats.scheduled, color: 'text-blue-600' },
    { key: 'Published', val: props.stats.published, color: 'text-blue-600' },
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
                    <div class="rounded-xl border border-dashed bg-white flex flex-col min-h-0">
                        <div class="shrink-0 p-4 space-y-3 border-b border-dashed border-border/60">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold">AI Topic Suggestions</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ isStandaloneTraffic
                                            ? 'General topic ideas — add optional context below. Click any topic to use it.'
                                            : campaignHub
                                                ? 'Based on this campaign\'s knowledge base. Click any topic to use it.'
                                                : 'Based on your funnel. Click any topic to use it.' }}
                                    </p>
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
                            <div v-else class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                                <button
                                    v-for="s in suggestions"
                                    :key="s.id"
                                    type="button"
                                    class="group rounded-lg border bg-white p-2.5 text-left transition-all hover:border-primary/50"
                                    :class="topicInput === s.topic ? 'border-primary/60 ring-1 ring-primary/20' : 'border-border'"
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
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pt-5 pb-4 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Choose where this content goes</h3>
                        <p class="text-xs text-muted-foreground">Topic: <span class="font-medium text-foreground">{{ topicInput }}</span></p>
                        <p class="text-[0.65rem] text-muted-foreground">{{ contentChannelHint }}</p>
                    </div>

                    <!-- Channel: social vs video vs email -->
                    <div class="grid grid-cols-3 gap-2.5">
                        <button
                            v-for="fmt in [
                                { key: 'post',  icon: 'heroicons:megaphone',     label: 'Social media', sub: 'Posts, carousels, threads', color: 'text-primary',   badge: 'Most posts' },
                                { key: 'video', icon: 'heroicons:video-camera',  label: 'Video',        sub: 'Platform video posts',    color: 'text-amber-500', badge: '' },
                                { key: 'email', icon: 'heroicons:envelope',      label: 'Email',        sub: 'Subject & body to copy', color: 'text-purple-500', badge: '' },
                            ]"
                            :key="fmt.key"
                            type="button"
                            class="relative rounded-xl border bg-white p-4 text-center transition-all"
                            :class="selectedFormat === fmt.key ? 'border-primary/60 ring-1 ring-primary/30' : 'border-border hover:border-primary/30'"
                            @click="selectTopFormat(fmt.key as Format)"
                        >
                            <span v-if="fmt.badge" class="absolute -top-2 left-1/2 -translate-x-1/2 rounded-full bg-primary px-2 py-px text-[0.5rem] font-bold text-primary-foreground uppercase tracking-wide">
                                {{ fmt.badge }}
                            </span>
                            <Icon :icon="fmt.icon" class="size-6 mx-auto mb-1.5" :class="selectedFormat === fmt.key ? fmt.color : 'text-muted-foreground'" />
                            <p class="text-xs font-semibold">{{ fmt.label }}</p>
                            <p class="text-[0.6rem] text-muted-foreground">{{ fmt.sub }}</p>
                        </button>
                    </div>

                    <!-- Video: full platform format catalog -->
                    <div v-if="selectedFormat === 'video' && videoCatalogFormats.length > 0" class="rounded-xl border bg-white p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold">Platform video format</p>
                                <p class="text-[0.65rem] text-muted-foreground">Reels, Shorts, native video, live outlines — matched to each platform’s specs.</p>
                            </div>
                            <button
                                v-if="selectedCatalogFormat"
                                type="button"
                                class="text-[0.65rem] text-muted-foreground underline"
                                @click="selectedCatalogFormat = null"
                            >
                                Clear
                            </button>
                        </div>

                        <div v-if="selectedCatalogSpec" class="rounded-lg border border-amber-300/60 bg-amber-50/60 px-3 py-2 text-xs">
                            <span class="font-semibold text-amber-900">{{ selectedCatalogSpec.platform_label }} · {{ selectedCatalogSpec.label }}</span>
                            <span class="text-amber-800/80"> — {{ videoFormatMeta(selectedCatalogSpec) }}</span>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="tab in videoCatalogPlatformTabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-md px-2 py-1 text-[0.65rem] font-medium transition-colors"
                                :class="catalogPlatformFilter === tab.key
                                    ? 'bg-amber-600 text-white'
                                    : 'bg-white border border-border/60 text-muted-foreground'"
                                @click="setCatalogPlatformFilter(tab.key)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="max-h-48 overflow-y-auto grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="fmt in filteredVideoCatalogFormats"
                                :key="fmt.key"
                                type="button"
                                class="rounded-lg border px-2.5 py-2 text-left transition-all"
                                :class="selectedCatalogFormat === fmt.key
                                    ? 'border-amber-500 bg-white ring-1 ring-amber-500/30'
                                    : 'border-border/60 bg-white hover:border-amber-200'"
                                @click="selectCatalogFormat(fmt.key)"
                            >
                                <p class="text-[0.7rem] font-semibold leading-snug">{{ fmt.label }}</p>
                                <p class="text-[0.6rem] text-muted-foreground">{{ videoFormatMeta(fmt) }}</p>
                            </button>
                        </div>
                    </div>

                    <!-- Post: platform format picker -->
                    <div v-if="selectedFormat === 'post' && socialCatalogFormats.length > 0" class="rounded-xl border bg-white p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-semibold">Platform format</p>
                                <p class="text-[0.65rem] text-muted-foreground">Pick the destination format — we'll match platform & generation automatically.</p>
                            </div>
                            <button
                                v-if="selectedCatalogFormat"
                                type="button"
                                class="text-[0.65rem] text-muted-foreground underline"
                                @click="selectedCatalogFormat = null"
                            >
                                Clear
                            </button>
                        </div>

                        <div v-if="selectedCatalogSpec" class="rounded-lg border border-blue-300/60 bg-blue-50/60 px-3 py-2 text-xs">
                            <span class="font-semibold text-blue-800">{{ selectedCatalogSpec.platform_label }} · {{ selectedCatalogSpec.label }}</span>
                            <span class="text-blue-700/80"> — {{ selectedCatalogSpec.generator }} generation</span>
                        </div>

                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="tab in catalogPlatformTabs"
                                :key="tab.key"
                                type="button"
                                class="rounded-md px-2 py-1 text-[0.65rem] font-medium transition-colors"
                                :class="catalogPlatformFilter === tab.key
                                    ? 'chip-brand-active'
                                    : 'bg-white border border-border/60 text-muted-foreground'"
                                @click="setCatalogPlatformFilter(tab.key)"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div class="max-h-40 overflow-y-auto grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="fmt in filteredCatalogFormats"
                                :key="fmt.key"
                                type="button"
                                class="rounded-lg border px-2.5 py-2 text-left transition-all"
                                :class="selectedCatalogFormat === fmt.key
                                    ? 'border-blue-500 bg-white ring-1 ring-blue-500/30'
                                    : 'border-border/60 bg-white hover:border-blue-200'"
                                @click="selectCatalogFormat(fmt.key)"
                            >
                                <p class="text-[0.7rem] font-semibold leading-snug">{{ fmt.label }}</p>
                                <p class="text-[0.6rem] text-muted-foreground">{{ formatCardMeta(fmt) }}</p>
                            </button>
                        </div>
                    </div>
                    <!-- Post: text + image toggles -->
                    <div v-if="selectedFormat === 'post'" class="rounded-xl border bg-white p-4 space-y-2.5">
                        <p class="text-xs font-semibold">{{ generationOptionsLabel() }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="flex items-center gap-2.5 rounded-lg border bg-white p-3 transition-all"
                                :class="includeText ? 'border-blue-500/50 ring-1 ring-blue-500/20' : 'border-border opacity-60'"
                                @click="toggleIncludeText"
                            >
                                <div class="size-8 rounded-lg flex items-center justify-center shrink-0" :class="includeText ? 'bg-blue-500/15 text-blue-500' : 'bg-muted text-muted-foreground'">
                                    <Icon icon="heroicons:document-text" class="size-4" />
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-semibold">{{ selectedCatalogSpec?.generator === 'carousel' ? 'Slide copy' : selectedCatalogSpec?.generator === 'thread' ? 'Thread text' : 'AI Text' }}</p>
                                    <p class="text-[0.6rem] text-muted-foreground">{{ selectedCatalogSpec?.generator === 'carousel' ? 'Caption + slide headlines' : 'Caption + hashtags' }}</p>
                                </div>
                                <span class="ml-auto size-4 rounded-full border-2 flex items-center justify-center shrink-0" :class="includeText ? 'border-blue-500 bg-blue-500' : 'border-muted-foreground'">
                                    <Icon v-if="includeText" icon="heroicons:check" class="size-2.5 text-white" />
                                </span>
                            </button>
                            <button
                                v-if="showImageGenerationToggle()"
                                type="button"
                                class="flex items-center gap-2.5 rounded-lg border bg-white p-3 transition-all"
                                :class="includeImage ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border opacity-60'"
                                @click="toggleIncludeImage"
                            >
                                <div class="size-8 rounded-lg flex items-center justify-center shrink-0" :class="includeImage ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                    <Icon icon="heroicons:photo" class="size-4" />
                                </div>
                                <div class="text-left">
                                    <p class="text-xs font-semibold">{{ selectedCatalogSpec?.generator === 'carousel' ? 'Cover visual' : 'AI Image' }}</p>
                                    <p class="text-[0.6rem] text-muted-foreground">{{ selectedCatalogSpec?.generator === 'carousel' ? 'Hero slide image' : 'GPT-generated visual' }}</p>
                                </div>
                                <span class="ml-auto size-4 rounded-full border-2 flex items-center justify-center shrink-0" :class="includeImage ? 'border-primary bg-primary' : 'border-muted-foreground'">
                                    <Icon v-if="includeImage" icon="heroicons:check" class="size-2.5 text-white" />
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Email options -->
                    <div v-if="selectedFormat === 'email'" class="rounded-xl border bg-white p-4 space-y-3">
                        <p class="text-xs font-semibold">Email type</p>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="opt in EMAIL_TYPE_OPTIONS"
                                :key="opt.key"
                                type="button"
                                class="rounded-lg border bg-white p-2.5 text-left transition-all"
                                :class="selectedEmailType === opt.key
                                    ? 'border-primary/50 ring-1 ring-primary/20 text-foreground'
                                    : 'border-border/60 text-muted-foreground hover:border-primary/30 hover:text-foreground'"
                                @click="selectedEmailType = opt.key"
                            >
                                <p class="text-xs font-semibold">{{ opt.label }}</p>
                                <p class="text-[0.65rem] mt-0.5 leading-snug opacity-80">{{ opt.description }}</p>
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">The full email body will be generated based on your topic, funnel context, and selected email type.</p>
                    </div>
                </div>
            </div>

            <!-- ── Step 3 (carousel): Template style ────────────────── -->
            <div
                v-else-if="wizardStep === 3 && needsCarouselTemplateStep"
                class="flex flex-1 min-h-0 flex-col overflow-hidden"
            >
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-5 pt-5 pb-4 space-y-4">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Carousel template style</h3>
                        <p class="text-xs text-muted-foreground">
                            Topic: <span class="font-medium text-foreground">{{ topicInput }}</span>
                            <span v-if="selectedCatalogSpec"> · {{ selectedCatalogSpec.platform_label }} {{ selectedCatalogSpec.label }}</span>
                        </p>
                        <p class="text-[0.65rem] text-muted-foreground">
                            AI picks a layout from our templates by default. Turn this off to choose the look yourself.
                        </p>
                    </div>

                    <div class="rounded-xl border bg-white p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold">AI template selection</p>
                                <p class="text-[0.65rem] text-muted-foreground">
                                    {{ carouselAiTemplate
                                        ? 'We’ll pick the best template for this topic.'
                                        : 'Choose one template below — every slide will use it.' }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                                :class="carouselAiTemplate ? 'bg-blue-600' : 'bg-muted'"
                                :aria-pressed="carouselAiTemplate"
                                @click="carouselAiTemplate = !carouselAiTemplate"
                            >
                                <span
                                    class="absolute top-0.5 size-5 rounded-full bg-white shadow transition-transform"
                                    :class="carouselAiTemplate ? 'left-5' : 'left-0.5'"
                                />
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="carouselAiTemplate"
                        class="rounded-xl border border-blue-200/70 bg-blue-50/50 px-4 py-3 text-xs text-blue-900"
                    >
                        <p class="font-semibold">AI will choose the style</p>
                        <p class="mt-0.5 text-blue-800/85">
                            Leave this on if you want a strong default. Turn it off only when you want a specific look.
                        </p>
                    </div>

                    <div v-else class="space-y-2">
                        <p class="text-xs font-semibold">Pick a template</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="layout in carouselLayoutTemplates"
                                :key="layout.key"
                                type="button"
                                class="rounded-xl border px-3 py-3 text-left transition-all"
                                :class="selectedCarouselLayout === layout.key
                                    ? 'border-blue-500 bg-blue-50/70 ring-1 ring-blue-500/30'
                                    : 'border-border/60 bg-white hover:border-blue-200'"
                                @click="selectedCarouselLayout = layout.key"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold leading-snug">{{ layout.label }}</p>
                                    <span
                                        v-if="layout.is_dark"
                                        class="rounded-full bg-zinc-900 px-1.5 py-px text-[0.55rem] font-medium text-zinc-100"
                                    >
                                        Dark
                                    </span>
                                </div>
                                <p class="text-[0.65rem] text-muted-foreground mt-1 leading-snug">{{ layout.description }}</p>
                            </button>
                        </div>
                        <p
                            v-if="!selectedCarouselLayout"
                            class="text-[0.65rem] text-amber-700"
                        >
                            Select a template to continue.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Step 3 (video): Script ───────────────────────────── -->
            <div v-else-if="wizardStep === 3 && isVideoFormat" class="flex flex-1 min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 px-5 pt-5 pb-3 space-y-2">
                    <h3 class="text-sm font-semibold">Video script</h3>
                    <p class="text-xs text-muted-foreground">
                        Write or generate the spoken script first. Next you’ll pick how to render it into an MP4.
                        <template v-if="selectedCatalogSpec"> Length target: {{ videoFormatMeta(selectedCatalogSpec) }}.</template>
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
                        placeholder="Generate a spoken script, or write your own…"
                        maxlength="800"
                    />
                    <p class="mt-2 text-[0.65rem] text-muted-foreground">
                        {{ videoScript.length }}/800 characters · aim for 45–60 seconds when spoken
                    </p>
                </div>
            </div>

            <!-- ── Step 4 (video): Render method ───────────────────────────── -->
            <div v-else-if="wizardStep === 4 && isVideoFormat" class="flex flex-1 min-h-0 flex-col overflow-hidden">
                <div class="shrink-0 px-5 pt-5 pb-3 space-y-3">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Avatar video (D-ID)</h3>
                        <p class="text-xs text-muted-foreground">Script is ready — pick presenter and voice. D-ID renders your MP4.</p>
                    </div>

                    <div v-if="videoRenderOptions.length > 1" class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="opt in videoRenderOptions"
                            :key="opt.key"
                            type="button"
                            class="relative rounded-xl border bg-white p-3.5 text-left transition-all"
                            :class="[
                                !opt.available ? 'opacity-55 cursor-not-allowed border-border/60' : '',
                                opt.available && selectedVideoRenderProvider === opt.key
                                    ? 'border-amber-500/70 ring-1 ring-amber-500/30'
                                    : opt.available ? 'border-border hover:border-amber-300/60' : '',
                            ]"
                            :disabled="!opt.available"
                            @click="selectVideoRenderProvider(opt.key)"
                        >
                            <span
                                v-if="opt.badge"
                                class="absolute -top-2 right-2 rounded-full bg-muted px-2 py-px text-[0.5rem] font-bold uppercase tracking-wide text-muted-foreground"
                            >
                                {{ opt.badge }}
                            </span>
                            <div class="flex items-start gap-2.5">
                                <div
                                    class="size-9 rounded-lg flex items-center justify-center shrink-0"
                                    :class="selectedVideoRenderProvider === opt.key && opt.available ? 'bg-amber-500/15 text-amber-600' : 'bg-muted text-muted-foreground'"
                                >
                                    <Icon :icon="opt.icon" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold">{{ opt.label }}</p>
                                    <p class="text-[0.6rem] text-muted-foreground mt-0.5 leading-snug">{{ opt.description }}</p>
                                </div>
                            </div>
                        </button>
                    </div>

                    <div v-if="selectedVideoRenderProvider === 'avatar_did' && videoSubStep === 'avatar'">
                        <p class="text-xs font-semibold">Choose your presenter</p>
                        <p class="text-xs text-muted-foreground">D-ID will render this avatar speaking your script.</p>
                    </div>
                    <div v-else-if="selectedVideoRenderProvider === 'avatar_did' && videoSubStep === 'voice'" class="flex items-center gap-3">
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
                        <p class="text-xs font-semibold">{{ AVATARS.find(a => a.id === selectedAvatarId)?.name ?? 'Presenter' }} · Choose a voice</p>
                    </div>
                </div>

                <div
                    v-if="selectedVideoRenderProvider === 'avatar_did'"
                    class="flex-1 min-h-0 overflow-y-auto px-5 pb-4"
                >
                    <div v-if="videoSubStep === 'avatar'" class="rounded-xl border bg-white p-4">
                        <div v-if="!videoEnabled" class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 text-xs text-amber-700 dark:text-amber-400 space-y-1">
                            <p class="font-semibold flex items-center gap-1.5"><Icon icon="heroicons:exclamation-triangle" class="size-3.5" /> D-ID not configured</p>
                            <p>Add <code class="font-mono bg-amber-500/10 px-1 rounded">DID_API_KEY</code> to enable avatar video.</p>
                        </div>
                        <div v-else-if="AVATARS.length === 0" class="text-xs text-muted-foreground italic py-2">No presenters loaded.</div>
                        <div v-else class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                            <button
                                v-for="av in AVATARS"
                                :key="av.id"
                                type="button"
                                class="rounded-xl border bg-white overflow-hidden text-center transition-all"
                                :class="selectedAvatarId === av.id ? 'border-primary/60 ring-2 ring-primary/30' : 'border-border hover:border-primary/30'"
                                @click="selectedAvatarId = av.id"
                            >
                                <div class="relative aspect-square overflow-hidden bg-muted">
                                    <img v-if="av.thumbnail_url" :src="av.thumbnail_url" :alt="av.name" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-xl font-bold text-muted-foreground/40">{{ av.name[0] }}</div>
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

                    <div v-else-if="videoSubStep === 'voice'" class="rounded-xl border bg-white p-4 space-y-1.5">
                        <div
                            v-for="v in VOICES"
                            :key="v.id"
                            role="button"
                            tabindex="0"
                            class="w-full flex items-center gap-3 rounded-lg border bg-white p-2.5 text-left transition-all cursor-pointer"
                            :class="selectedVoiceId === v.id ? 'border-primary/60 ring-1 ring-primary/20' : 'border-border hover:border-primary/30'"
                            @click="selectedVoiceId = v.id"
                            @keydown.enter="selectedVoiceId = v.id"
                        >
                            <button
                                type="button"
                                class="size-8 rounded-full border flex items-center justify-center shrink-0 transition-colors"
                                :class="playingVoiceId === v.id ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-muted hover:bg-muted/80 text-muted-foreground'"
                                :disabled="loadingVoicePreview !== null && loadingVoicePreview !== v.id"
                                @click="playVoicePreview(v, $event)"
                            >
                                <Icon v-if="loadingVoicePreview === v.id" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                                <Icon v-else-if="playingVoiceId === v.id" icon="heroicons:stop" class="size-3.5" />
                                <Icon v-else icon="heroicons:play" class="size-3.5 ml-0.5" />
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

                <div v-else-if="!selectedVideoRenderProvider" class="flex-1 flex items-center justify-center px-5 pb-6">
                    <p class="text-xs text-muted-foreground text-center">Select a render method above to continue.</p>
                </div>
            </div>

            <!-- ── Launch step ─────────────────────────────────────── -->
            <div v-else-if="wizardStep === launchWizardStep" class="flex-1 min-h-0 overflow-y-auto px-5 py-5 space-y-5">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold">Where & how to publish</h3>
                        <p class="text-xs text-muted-foreground">Topic: <span class="font-medium text-foreground">{{ topicInput }}</span></p>
                    </div>

                    <div
                        v-if="selectedCatalogSpec && selectedFormat === 'video'"
                        class="rounded-xl border border-amber-200/60 bg-amber-50/50 px-4 py-3 text-xs text-amber-900"
                    >
                        <p class="font-semibold">{{ selectedCatalogSpec.platform_label }} · {{ selectedCatalogSpec.label }}</p>
                        <p v-if="selectedVideoRenderOption" class="mt-0.5 text-amber-800/85">
                            Render: {{ selectedVideoRenderOption.label }} · format-matched caption & hashtags · publishes to {{ selectedCatalogSpec.platform_label }}.
                        </p>
                        <p v-else class="mt-0.5 text-amber-800/85">
                            Video post for {{ selectedCatalogSpec.platform_label }}.
                        </p>
                    </div>
                    <div
                        v-else-if="selectedCatalogSpec"
                        class="rounded-xl border border-blue-200/60 bg-blue-50/50 px-4 py-3 text-xs text-blue-900"
                    >
                        <p class="font-semibold">{{ selectedCatalogSpec.platform_label }} · {{ selectedCatalogSpec.label }}</p>
                        <p class="mt-0.5 text-blue-800/85">
                            {{ formatLockedToPlatform
                                ? `This format publishes to ${selectedCatalogSpec.platform_label} only — other platforms are hidden.`
                                : 'Format-specific copy and layout will be generated for this post type.' }}
                        </p>
                        <p v-if="needsCarouselTemplateStep" class="mt-1 text-blue-800/85">
                            Template:
                            <span class="font-medium">
                                {{
                                    carouselAiTemplate
                                        ? 'AI selects from our layouts'
                                        : (carouselLayoutTemplates.find((l) => l.key === selectedCarouselLayout)?.label ?? 'Manual pick')
                                }}
                            </span>
                        </p>
                    </div>

                    <!-- Email: copy/download only (replaces platform picker) -->
                    <div v-if="selectedFormat === 'email'" class="flex items-start gap-3 rounded-xl border border-purple-500/20 bg-purple-500/5 px-4 py-3">
                        <Icon icon="heroicons:envelope" class="size-4 text-purple-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-xs font-semibold text-purple-700 dark:text-purple-400">Email copy</p>
                            <p class="text-xs text-muted-foreground mt-1">AI writes subject and body for your funnel. Use <strong>Copy email</strong> or <strong>Download .txt</strong> on the post after generation.</p>
                        </div>
                    </div>

                    <!-- Platforms (social posts only) -->
                    <div v-else class="space-y-2">
                        <Label class="text-xs font-semibold">
                            {{ formatLockedToPlatform ? `Publish to (${selectedCatalogSpec?.platform_label})` : 'Publish to' }}
                        </Label>
                        <div
                            v-if="launchPlatformMissing"
                            class="rounded-xl border border-amber-500/25 bg-amber-500/5 px-4 py-3 text-xs text-muted-foreground"
                        >
                            Connect {{ selectedCatalogSpec?.platform_label }} in
                            <Link :href="socialTrafficUrl" class="font-semibold text-primary hover:underline">Settings → Social posting</Link>
                            to use this format.
                        </div>
                        <div
                            v-else-if="launchPlatformRows.length === 0"
                            class="rounded-xl border border-amber-500/25 bg-amber-500/5 px-4 py-3 text-xs text-muted-foreground"
                        >
                            No social accounts connected yet.
                            <Link :href="socialTrafficUrl" class="font-semibold text-primary hover:underline">Connect platforms in Settings</Link>
                            to schedule and publish posts.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button
                                v-for="row in launchPlatformRows"
                                :key="row.platform"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border bg-white px-3.5 py-2 text-xs font-medium transition-all"
                                :class="selectedPlatforms.includes(row.platform)
                                    ? 'border-primary/50 ring-1 ring-primary/20 text-primary'
                                    : 'border-border text-muted-foreground hover:border-primary/30'"
                                @click="togglePlatform(row.platform)"
                            >
                                <Icon :icon="promotionPlatformIcon(row.platform)" class="size-3.5" />
                                {{ platformButtonLabel(row.platform) }}
                                <span v-if="selectedPlatforms.includes(row.platform)" class="size-3.5 rounded-full bg-primary flex items-center justify-center">
                                    <Icon icon="heroicons:check" class="size-2 text-white" />
                                </span>
                            </button>
                        </div>
                        <p v-if="formatLockedToPlatform && !launchPlatformMissing" class="text-[0.65rem] text-muted-foreground">
                            Platform locked to match your selected format.
                        </p>
                    </div>

                    <!-- Publish mode -->
                    <div class="space-y-2">
                        <Label class="text-xs font-semibold">Publish mode</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button"
                                class="rounded-lg border bg-white p-3 text-left transition-all"
                                :class="publishMode === 'approve_first' ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border hover:border-primary/30'"
                                @click="publishMode = 'approve_first'"
                            >
                                <div class="flex items-center gap-1.5 mb-1">
                                    <Icon icon="heroicons:eye" class="size-3.5" :class="publishMode === 'approve_first' ? 'text-primary' : 'text-muted-foreground'" />
                                    <span class="text-xs font-semibold">Review first</span>
                                </div>
                                <p class="text-[0.6rem] text-muted-foreground">You approve before it goes live.</p>
                            </button>
                            <button type="button"
                                class="rounded-lg border bg-white p-3 text-left transition-all"
                                :class="publishMode === 'auto_publish' ? 'border-primary/50 ring-1 ring-primary/20' : 'border-border hover:border-primary/30'"
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
                    <div class="rounded-xl border border-dashed bg-white">
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
                                    <Input
                                        v-model="ctaLabelInput"
                                        class="h-9 text-sm"
                                        :placeholder="props.defaultCta?.label ?? 'Watch free webinar'"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-xs">CTA URL override</Label>
                                    <Input
                                        v-model="ctaUrlInput"
                                        class="h-9 text-sm"
                                        :placeholder="props.defaultCta?.url ?? 'https://…'"
                                    />
                                    <p v-if="props.defaultCta?.url && !ctaUrlInput" class="text-[0.65rem] text-muted-foreground">
                                        Default: your funnel opt-in page ({{ props.defaultCta.url }})
                                    </p>
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
    <TrafficFeatureShell :campaign-hub="campaignHub" active="promotion">

        <!-- Header -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div v-if="!campaignHub" class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors">Funnels</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors truncate max-w-[160px]">{{ funnel.name }}</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <span class="text-foreground font-medium">Promotion Posts</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight">{{ isStandaloneTraffic ? 'Social posts' : 'Promotion Posts' }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ isStandaloneTraffic
                        ? 'Your standalone workspace is ready — click New post, pick a topic & format, then generate and publish.'
                        : 'Create and schedule rich social campaigns for this funnel.' }}
                </p>
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

        <!-- Standalone getting started -->
        <div
            v-if="showStandaloneGettingStarted"
            class="rounded-xl border border-blue-200/60 bg-blue-50/40 p-4"
        >
            <p class="text-sm font-semibold text-blue-900">Get started in 3 steps</p>
            <ol class="mt-2 space-y-2 text-xs text-blue-800/90">
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">1</span>
                    <span>
                        <Link :href="socialTrafficUrl" class="font-semibold underline-offset-2 hover:underline">Connect social accounts</Link>
                        so posts can publish (skip if already connected below).
                    </span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">2</span>
                    <span>
                        Click
                        <button type="button" class="font-semibold underline-offset-2 hover:underline" @click="openDialog">New post</button>
                        — enter a topic, choose a format, and let AI generate content.
                    </span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-brand-gradient text-[0.65rem] font-bold text-white">3</span>
                    <span>
                        Publish now or
                        <Link :href="routes.calendar" class="font-semibold underline-offset-2 hover:underline">schedule on the calendar</Link>.
                        No campaign or offer setup needed.
                    </span>
                </li>
            </ol>
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
        <div v-if="isProcessing" class="rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3 space-y-2">
            <div class="flex items-center gap-3">
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
                        {{ generatingPosts.length }} post(s) processing — updates every few seconds.
                    </p>
                </div>
            </div>
            <ul v-if="generatingPosts.length > 0" class="space-y-1.5 border-t border-amber-500/20 pt-2">
                <li
                    v-for="post in generatingPosts"
                    :key="post.id"
                    class="text-xs text-amber-800/90 dark:text-amber-200/90"
                >
                    <span class="font-medium">{{ post.topic ?? `Post #${post.id}` }}</span>
                    <span class="text-amber-700/80 dark:text-amber-300/80"> — {{ postGenerationProgressLabel(post) ?? (post.status === 'publishing' ? 'Publishing…' : 'Generating content…') }}</span>
                    <div
                        v-if="postGenerationProgressPercent(post) !== null"
                        class="mt-1 h-1 w-full overflow-hidden rounded-full bg-amber-500/20"
                    >
                        <div
                            class="h-full rounded-full bg-amber-500 transition-all duration-500"
                            :style="{ width: `${postGenerationProgressPercent(post)}%` }"
                        />
                    </div>
                </li>
            </ul>
        </div>

        <!-- Connected platforms -->
        <div
            v-if="connectedPlatformKeys.length > 0"
            class="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-white px-3 py-2 text-xs text-muted-foreground shadow-sm"
        >
            <span class="font-semibold text-foreground">Connected:</span>
            <span
                v-for="row in connectedPlatformRows"
                :key="row.platform"
                class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-muted/30 px-2 py-0.5 text-foreground"
            >
                <Icon :icon="promotionPlatformIcon(row.platform)" class="size-3" />
                {{ platformButtonLabel(row.platform) }}
            </span>
            <Link :href="socialTrafficUrl" class="ml-auto text-primary hover:underline">Manage connections</Link>
        </div>
        <div
            v-else
            class="rounded-lg border border-amber-500/25 bg-amber-500/5 px-4 py-3 text-xs text-muted-foreground"
        >
            Connect Facebook, Instagram, X, and other platforms in
            <Link :href="socialTrafficUrl" class="font-semibold text-primary hover:underline">Settings → Social posting</Link>
            to publish and schedule promotion posts.
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-white px-3 py-2 shadow-sm">
            <select v-model="filterStatus" :class="filterControlClass">
                <option value="">All statuses</option>
                <option v-for="s in ['draft','generating','ready','scheduled','publishing','published','failed']" :key="s" :value="s" class="capitalize">{{ s }}</option>
            </select>
            <select v-model="filterType" :class="filterControlClass">
                <option value="">All types</option>
                <option v-for="t in ['text','image','video','email']" :key="t" :value="t" class="capitalize">{{ t }}</option>
            </select>
            <select v-model="filterPlatform" :class="filterControlClass">
                <option value="">All platforms</option>
                <option v-for="p in availablePlatforms" :key="p" :value="p">{{ promotionPlatformLabel(p) }}</option>
            </select>
            <div class="relative">
                <Icon icon="heroicons:magnifying-glass" class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                <Input v-model="filterSearch" class="h-8 pl-7 text-xs w-52 bg-white border-border/60 shadow-sm" placeholder="Search topic, text…" />
            </div>
            <div class="ml-auto flex items-center gap-1 rounded-lg border border-border/60 bg-muted/30 p-0.5">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[0.65rem] font-medium transition-colors"
                    :class="postsViewMode === 'card'
                        ? 'bg-primary/10 text-primary ring-1 ring-primary/25 shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'"
                    @click="postsViewMode = 'card'"
                >
                    <Icon icon="heroicons:squares-2x2" class="size-3.5" />
                    Cards
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-[0.65rem] font-medium transition-colors"
                    :class="postsViewMode === 'table'
                        ? 'bg-primary/10 text-primary ring-1 ring-primary/25 shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'"
                    @click="postsViewMode = 'table'"
                >
                    <Icon icon="heroicons:table-cells" class="size-3.5" />
                    Table
                </button>
            </div>
        </div>

        <!-- ── Bulk actions ────────────────────────────────────────────── -->
        <div v-if="posts.data.length > 0" class="flex flex-wrap items-center gap-2 rounded-lg border border-border/60 bg-white px-3 py-2 shadow-sm">
            <button type="button" class="text-xs text-muted-foreground hover:text-foreground" @click="toggleSelectAll">
                {{ posts.data.every(p => selectedIds.includes(p.id)) ? 'Deselect all' : 'Select page' }}
            </button>
            <span v-if="selectedIds.length > 0" class="text-xs font-medium text-primary">{{ selectedIds.length }} selected</span>
            <div v-if="selectedIds.length > 0" class="flex items-center gap-2 ml-auto">
                <select v-model="bulkAction" class="h-7 rounded-md border border-border/60 bg-white px-2.5 text-xs shadow-sm">
                    <option value="publish">Publish now</option>
                    <option value="schedule">Schedule</option>
                    <option value="duplicate">Copy</option>
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
                <p class="text-sm font-semibold">{{ isStandaloneTraffic ? 'No posts yet' : 'No promotion posts yet' }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">
                    {{ isStandaloneTraffic
                        ? 'Click below to create your first standalone social post — no campaign required.'
                        : 'Use the creator to generate your first campaign post.' }}
                </p>
            </div>
            <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground" @click="openDialog">
                <Icon icon="heroicons:plus" class="size-3.5" />
                {{ isStandaloneTraffic ? 'Create first post' : 'Create first post' }}
            </Button>
        </div>

        <!-- ── Post list (cards or table) ──────────────────────────────── -->
        <template v-else>
            <!-- Cards -->
            <div v-if="postsViewMode === 'card'" class="grid w-full min-w-0 grid-cols-1 gap-6 xl:grid-cols-2">
            <div
                v-for="post in posts.data"
                :key="post.id"
                class="rounded-2xl border bg-card shadow-sm overflow-hidden flex flex-col transition-shadow hover:shadow-md"
            >
                <!-- ── Format-native preview ───────────────────────────── -->
                <div class="relative shrink-0 overflow-hidden">
                    <div class="absolute top-0 left-0 right-0 h-[3px] z-10" :class="{
                        'bg-primary':    visualPreviewKind(post) === 'image' || visualPreviewKind(post) === 'carousel',
                        'bg-amber-500':  visualPreviewKind(post) === 'video',
                        'bg-purple-500': visualPreviewKind(post) === 'email',
                        'bg-sky-500':    visualPreviewKind(post) === 'thread',
                        'bg-blue-500':   visualPreviewKind(post) === 'text',
                    }" />

                    <PostFormatPreview
                        :kind="visualPreviewKind(post)"
                        :aspect-ratio="previewAspectRatio(post)"
                        :slides="postCarouselSlides(post)"
                        :thread-parts="postThreadParts(post)"
                        :image-url="post.primary_asset?.asset_type === 'image' ? post.primary_asset.url : null"
                        :video-url="post.primary_asset?.asset_type === 'video' ? post.primary_asset.url : null"
                        :video-poster="post.primary_asset?.thumbnail_url ?? null"
                        :text-body="post.text_body"
                        :email-subject="post.email_subject"
                        :generating="post.status === 'generating' || post.status === 'publishing'"
                        :progress-label="postGenerationProgressLabel(post) ?? (post.status === 'publishing' ? 'Publishing…' : post.status === 'generating' ? 'Generating content…' : null)"
                        :progress-percent="postGenerationProgressPercent(post)"
                        :format-label="postFormatLabel(post)"
                    />

                    <!-- Status badge top-right -->
                    <div class="absolute top-2.5 right-2.5 z-10 flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.65rem] font-semibold backdrop-blur-sm bg-background/80 shadow-sm" :class="statusMeta(displayStatus(post)).text">
                        <span class="size-1.5 rounded-full inline-block shrink-0" :class="statusMeta(displayStatus(post)).dot" />
                        {{ generatingStatusLabel(post) ?? statusMeta(displayStatus(post)).label }}
                    </div>

                    <!-- Checkbox top-left -->
                    <label class="absolute top-2.5 left-2.5 z-10 flex items-center justify-center size-6 rounded-lg bg-background/75 backdrop-blur-sm cursor-pointer hover:bg-background/90 transition-colors shadow-sm">
                        <input type="checkbox" :checked="selectedIds.includes(post.id)" class="rounded border-border" @change="toggleSelected(post.id)" />
                    </label>

                    <!-- Animated progress bar when processing -->
                    <div v-if="post.status === 'generating' || post.status === 'publishing'" class="absolute bottom-0 left-0 right-0 z-20 h-0.5 overflow-hidden bg-amber-500/20">
                        <div
                            v-if="postGenerationProgressPercent(post) !== null"
                            class="h-full bg-amber-500 transition-all duration-500"
                            :style="{ width: `${postGenerationProgressPercent(post)}%` }"
                        />
                        <div v-else class="h-full bg-amber-500" style="animation: progressBar 2s ease-in-out infinite;" />
                    </div>
                </div>

                <!-- ── Card body ────────────────────────────────────────── -->
                <div class="flex flex-col flex-1 px-4 pt-3.5 pb-3 gap-2.5">
                    <!-- Title -->
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-semibold leading-snug line-clamp-2 flex-1">
                            {{ post.title || post.topic || `Post #${post.id}` }}
                        </h3>
                        <span
                            v-if="postFormatLabel(post)"
                            class="shrink-0 rounded-md border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[0.6rem] font-semibold text-blue-800"
                        >
                            {{ postFormatLabel(post) }}
                        </span>
                    </div>

                    <!-- Carousel meta (compact — swipe preview above is primary) -->
                    <div
                        v-if="visualPreviewKind(post) === 'carousel' && postCarouselSlides(post).length > 0"
                        class="flex flex-wrap items-center gap-2 text-[0.65rem] text-blue-800/90"
                    >
                        <span class="rounded-md border border-blue-200/60 bg-blue-50/50 px-2 py-0.5 font-semibold">
                            {{ postCarouselSlides(post).length }} slides
                        </span>
                        <span v-if="postSlideImagesReady(post) > 0">
                            {{ postSlideImagesReady(post) }} images ready
                        </span>
                        <span v-else-if="post.status === 'generating' && postGenerationProgressLabel(post)" class="text-muted-foreground">
                            {{ postGenerationProgressLabel(post) }}
                        </span>
                    </div>

                    <!-- Error notice -->
                    <div
                        v-if="needsRepublish(post)"
                        class="rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-[0.65rem] text-amber-800 dark:text-amber-300"
                    >
                        Marked published here, but the platform did not confirm the post. Use <strong>Retry publish</strong> once caption and image are ready.
                    </div>

                    <p v-if="post.last_error" class="text-xs text-rose-500 flex items-start gap-1">
                        <Icon icon="heroicons:exclamation-triangle" class="size-3.5 shrink-0 mt-0.5" />
                        <span class="line-clamp-3">{{ formatPublishError(post.last_error) }}</span>
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
                        <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide">
                            {{ postRequiredPlatform(post) ? 'Platform (format locked)' : 'Publish to' }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="platform in platformsForPost(post)"
                                :key="platform"
                                type="button"
                                class="flex items-center gap-1.5 rounded-md border px-2 py-1 text-[0.65rem] font-medium transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="(post.platforms ?? []).includes(platform)
                                    ? 'border-primary/50 bg-primary/10 text-primary'
                                    : canTogglePlatform(platform, post)
                                        ? 'border-border bg-card text-muted-foreground hover:bg-muted/40 hover:text-foreground'
                                        : 'border-border/60 bg-muted/30 text-muted-foreground/70'"
                                :disabled="!canTogglePlatform(platform, post) || updatingPlatformsId === post.id || post.status === 'publishing' || post.status === 'published'"
                                :title="canTogglePlatform(platform, post)
                                    ? ((post.platforms ?? []).includes(platform) ? `Remove ${promotionPlatformLabel(platform)}` : `Add ${promotionPlatformLabel(platform)}`)
                                    : `${promotionPlatformLabel(platform)} does not match this post format`"
                                @click="togglePostPlatform(post, platform)"
                            >
                                <Icon
                                    :icon="updatingPlatformsId === post.id ? 'heroicons:arrow-path' : promotionPlatformIcon(platform)"
                                    class="size-3.5 shrink-0"
                                    :class="updatingPlatformsId === post.id ? 'animate-spin' : ''"
                                />
                                {{ promotionPlatformLabel(platform) }}
                            </button>
                        </div>
                    </div>

                    <!-- Email posts: no social publish -->
                    <div v-else class="flex items-start gap-2 rounded-lg border border-purple-500/20 bg-purple-500/5 px-3 py-2">
                        <Icon icon="heroicons:envelope" class="size-3.5 text-purple-500 shrink-0 mt-0.5" />
                        <div>
                            <p class="text-[0.65rem] font-semibold text-purple-700 dark:text-purple-400">Email copy</p>
                            <p class="text-[0.6rem] text-muted-foreground mt-0.5">Copy or download — use your own mail tool or campaign follow-ups for sends.</p>
                        </div>
                    </div>

                    <!-- Schedule / published date -->
                    <div v-if="post.scheduled_for || post.published_at" class="flex items-center justify-end">
                        <span v-if="post.scheduled_for" class="text-xs text-muted-foreground flex items-center gap-1">
                            <Icon icon="heroicons:calendar" class="size-3.5" />{{ fmtDate(post.scheduled_for) }}
                        </span>
                        <span v-else-if="post.published_at" class="text-xs text-blue-600 flex items-center gap-1">
                            <Icon icon="heroicons:check-circle" class="size-3.5" />{{ fmtDate(post.published_at) }}
                            <a
                                v-for="platform in (post.platforms ?? [])"
                                :key="platform"
                                v-show="platformPublishUrl(post, platform)"
                                :href="platformPublishUrl(post, platform) ?? '#'"
                                target="_blank"
                                rel="noopener"
                                class="ml-1 underline hover:opacity-80"
                            >View on {{ promotionPlatformLabel(platform) }}</a>
                        </span>
                    </div>
                </div>

                <!-- ── Action bar ───────────────────────────────────────── -->
                <div class="shrink-0 border-t border-border/50 bg-muted/10 px-4 py-2.5 space-y-2">
                    <!-- Schedule input -->
                    <input
                        v-if="post.content_type !== 'email'"
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
                            title="Edit"
                            @click="startEdit(post)"
                        >
                            <Icon icon="heroicons:pencil" class="size-3.5" />
                        </Button>
                        <!-- Copy -->
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 w-8 p-0"
                            title="Copy post"
                            @click="duplicatePost(post)"
                        >
                            <Icon icon="heroicons:document-duplicate" class="size-3.5" />
                        </Button>
                        <template v-if="post.content_type === 'email'">
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 px-2.5 text-xs gap-1.5"
                                :disabled="!post.email_body && !post.text_body"
                                as-child
                            >
                                <a :href="emailExportUrl(post)" download>
                                    <Icon icon="heroicons:arrow-down-tray" class="size-3.5 shrink-0" />
                                    .txt
                                </a>
                            </Button>
                            <Button
                                size="sm"
                                class="h-8 px-3 text-xs gap-1.5 bg-purple-600 text-white hover:opacity-90"
                                :disabled="!post.email_body && !post.text_body"
                                @click="copyEmailContent(post)"
                            >
                                <Icon icon="heroicons:clipboard-document" class="size-3.5 shrink-0" />
                                Copy email
                            </Button>
                        </template>
                        <!-- Publish (social) -->
                        <Button
                            v-else
                            size="sm"
                            class="h-8 px-3 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                            :disabled="isPublishingPost(post) || !canPublishPost(post)"
                            @click="publish(post)"
                        >
                            <Icon
                                :icon="isPublishingPost(post) ? 'heroicons:arrow-path' : 'heroicons:paper-airplane'"
                                class="size-3.5 shrink-0"
                                :class="{ 'animate-spin': isPublishingPost(post) }"
                            />
                            {{ isPublishingPost(post) ? 'Publishing…' : publishButtonLabel(post) }}
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
            </div>
            </div>

            <!-- Table -->
            <div v-else class="w-full min-w-0 overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
                <div class="w-full overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-b border-border/60 bg-muted/15 text-[0.62rem] uppercase tracking-wide text-muted-foreground">
                                <th class="w-9 px-3 py-2.5" />
                                <th class="px-3 py-2.5 text-left font-semibold">Post</th>
                                <th class="px-3 py-2.5 text-left font-semibold hidden md:table-cell">Preview</th>
                                <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Status</th>
                                <th class="px-3 py-2.5 text-left font-semibold hidden sm:table-cell whitespace-nowrap">Type</th>
                                <th class="px-3 py-2.5 text-left font-semibold hidden lg:table-cell whitespace-nowrap">Platforms</th>
                                <th class="px-3 py-2.5 text-left font-semibold hidden md:table-cell whitespace-nowrap">Date</th>
                                <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white">
                            <tr
                                v-for="post in posts.data"
                                :key="post.id"
                                class="border-b border-border/40 last:border-0 hover:bg-muted/10 align-top"
                            >
                                <td class="px-3 py-2.5 align-top">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(post.id)"
                                        class="rounded border-border"
                                        @change="toggleSelected(post.id)"
                                    />
                                </td>
                                <td class="px-3 py-2.5 align-top min-w-[160px] max-w-xs">
                                    <div class="space-y-0.5">
                                        <p class="font-semibold text-foreground line-clamp-2 leading-snug">
                                            {{ post.title || post.topic || `Post #${post.id}` }}
                                        </p>
                                        <p v-if="postFormatLabel(post)" class="text-[0.6rem] font-medium text-blue-700 truncate">
                                            {{ postFormatLabel(post) }}
                                        </p>
                                        <p class="text-[0.65rem] text-muted-foreground line-clamp-2 leading-snug md:hidden">
                                            {{ postTablePreview(post) }}
                                        </p>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 align-top hidden md:table-cell min-w-[200px]">
                                    <p class="text-muted-foreground line-clamp-2 leading-snug">
                                        {{ postTablePreview(post) }}
                                    </p>
                                    <p v-if="post.last_error" class="mt-0.5 text-[0.6rem] text-rose-500 line-clamp-2">
                                        {{ formatPublishError(post.last_error) }}
                                    </p>
                                </td>
                                <td class="px-3 py-2.5 align-top whitespace-nowrap">
                                <span
                                    class="inline-flex max-w-full items-center gap-1 rounded-full px-2 py-0.5 text-[0.6rem] font-semibold whitespace-nowrap"
                                    :class="statusMeta(displayStatus(post)).text"
                                >
                                    <span class="size-1.5 shrink-0 rounded-full" :class="statusMeta(displayStatus(post)).dot" />
                                    <span class="truncate">{{ generatingStatusLabel(post) ?? statusMeta(displayStatus(post)).label }}</span>
                                </span>
                                </td>
                                <td class="px-3 py-2.5 align-top hidden sm:table-cell whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[0.6rem] font-medium capitalize"
                                    :class="typeColorClass(post.content_type)"
                                >
                                    <Icon :icon="typeIcon(post.content_type)" class="size-3 shrink-0" />
                                    {{ post.content_type }}
                                </span>
                                </td>
                                <td class="px-3 py-2.5 align-top hidden lg:table-cell">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="platform in (post.platforms ?? []).slice(0, 4)"
                                        :key="platform"
                                        class="inline-flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted/20"
                                        :title="promotionPlatformLabel(platform)"
                                    >
                                        <Icon :icon="promotionPlatformIcon(platform)" class="size-3.5 text-muted-foreground" />
                                    </span>
                                    <span
                                        v-if="(post.platforms?.length ?? 0) > 4"
                                        class="inline-flex size-6 items-center justify-center rounded-md border border-border/60 bg-muted/20 text-[0.55rem] font-semibold text-muted-foreground"
                                    >
                                        +{{ (post.platforms?.length ?? 0) - 4 }}
                                    </span>
                                </div>
                                </td>
                                <td class="px-3 py-2.5 align-top hidden md:table-cell whitespace-nowrap">
                                <span class="text-[0.65rem] text-muted-foreground leading-snug line-clamp-2">
                                    {{ postTableDate(post) }}
                                </span>
                                </td>
                                <td class="px-3 py-2.5 align-top">
                                <div class="flex items-center justify-end gap-0.5 flex-wrap">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 w-7 p-0"
                                        title="Preview"
                                        :disabled="!post.text_body && !post.primary_asset?.url"
                                        @click="previewPost = post"
                                    >
                                        <Icon icon="heroicons:eye" class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 w-7 p-0"
                                        title="Edit"
                                        @click="startEdit(post)"
                                    >
                                        <Icon icon="heroicons:pencil" class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        class="h-7 px-2 text-[0.62rem] gap-1 bg-primary text-primary-foreground hover:opacity-90"
                                        :disabled="isPublishingPost(post) || !canPublishPost(post)"
                                        @click="publish(post)"
                                    >
                                        <Icon
                                            :icon="isPublishingPost(post) ? 'heroicons:arrow-path' : 'heroicons:paper-airplane'"
                                            class="size-3 shrink-0"
                                            :class="{ 'animate-spin': isPublishingPost(post) }"
                                        />
                                        {{ isPublishingPost(post) ? '…' : 'Go' }}
                                    </Button>
                                </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

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
    </TrafficFeatureShell>

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
                                        :icon="promotionPlatformIcon(p)"
                                        class="size-4 text-muted-foreground"
                                        :title="promotionPlatformLabel(p)"
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
                        <span v-else-if="previewPost.published_at" class="text-blue-600">Published {{ fmtDate(previewPost.published_at) }}</span>
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
                        variant="outline"
                        class="h-8 text-xs gap-1.5"
                        @click="duplicatePost(previewPost); previewPost = null"
                    >
                        <Icon icon="heroicons:document-duplicate" class="size-3.5" />
                        Copy
                    </Button>
                    <Button
                        size="sm"
                        class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 ml-auto"
                        :disabled="isPublishingPost(previewPost) || !canPublishPost(previewPost)"
                        @click="publish(previewPost); previewPost = null"
                    >
                        <Icon
                            :icon="isPublishingPost(previewPost) ? 'heroicons:arrow-path' : 'heroicons:paper-airplane'"
                            class="size-3.5"
                            :class="{ 'animate-spin': isPublishingPost(previewPost) }"
                        />
                        {{ isPublishingPost(previewPost) ? 'Publishing…' : 'Publish now' }}
                    </Button>
                </div>
            </template>
        </DialogContent>
    </Dialog>

    <!-- ── Post Edit Modal ───────────────────────────────────────────────── -->
    <Dialog :open="editingPost !== null" @update:open="(v) => { if (!v) closeEdit() }">
        <DialogContent class="max-w-2xl w-full p-0 overflow-hidden rounded-2xl gap-0 flex flex-col max-h-[90vh]">
            <DialogHeader class="px-6 pt-6 pb-2">
                <DialogTitle>Edit post</DialogTitle>
                <DialogDescription v-if="editingPost">
                    {{ editingPost.title || editingPost.topic || `Post #${editingPost.id}` }}
                    <span v-if="postFormatLabel(editingPost)" class="text-muted-foreground"> · {{ postFormatLabel(editingPost) }}</span>
                </DialogDescription>
            </DialogHeader>

            <template v-if="editingPost">
                <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4 space-y-4">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Post text</Label>
                        <Textarea v-model="editText" class="min-h-[160px] text-sm" placeholder="Edit your post content…" />
                    </div>

                    <div v-if="editingPost.content_type === 'email'" class="space-y-1.5">
                        <Label class="text-xs font-semibold">Email subject</Label>
                        <Input v-model="editSubject" class="h-9 text-sm" />
                    </div>

                    <div
                        v-if="postFormatSpec(editingPost)?.generator === 'carousel' && postCarouselSlides(editingPost).length > 0"
                        class="space-y-1.5"
                    >
                        <Label class="text-xs font-semibold">Carousel slides</Label>
                        <PostFormatPreview
                            kind="carousel"
                            :aspect-ratio="previewAspectRatio(editingPost)"
                            :slides="postCarouselSlides(editingPost)"
                            :generating="editingPost.status === 'generating'"
                            :progress-label="postGenerationProgressLabel(editingPost)"
                            :format-label="postFormatLabel(editingPost) ?? undefined"
                        />
                    </div>

                    <div
                        v-else-if="editingPost.primary_asset?.url && editingPost.primary_asset?.asset_type === 'image'"
                        class="space-y-1.5"
                    >
                        <Label class="text-xs font-semibold">Generated image</Label>
                        <img
                            :src="editingPost.primary_asset.url"
                            class="w-full rounded-xl object-cover border border-border/50"
                            style="max-height: 240px"
                            alt=""
                        />
                    </div>

                    <div
                        v-else-if="editingPost.primary_asset?.url && editingPost.primary_asset?.asset_type === 'video'"
                        class="space-y-1.5"
                    >
                        <Label class="text-xs font-semibold">Generated video</Label>
                        <video
                            :src="editingPost.primary_asset.url"
                            :poster="editingPost.primary_asset.thumbnail_url ?? undefined"
                            class="w-full rounded-xl border border-border/50"
                            style="max-height: 280px"
                            controls
                            preload="metadata"
                        />
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-2 px-6 py-3.5 border-t border-border/50 bg-background">
                    <Button
                        size="sm"
                        variant="outline"
                        class="h-8 text-xs gap-1.5"
                        :disabled="editingPost.status === 'generating'"
                        @click="generate(editingPost); closeEdit()"
                    >
                        <Icon icon="heroicons:arrow-path" class="size-3.5" />
                        Regenerate
                    </Button>
                    <div class="ml-auto flex gap-2">
                        <Button size="sm" variant="outline" class="h-8 text-xs" @click="closeEdit()">Cancel</Button>
                        <Button size="sm" class="h-8 text-xs bg-primary text-primary-foreground hover:opacity-90" @click="saveEdit()">
                            Save changes
                        </Button>
                    </div>
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
