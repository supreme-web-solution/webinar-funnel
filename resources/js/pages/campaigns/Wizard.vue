<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    useCampaignWizardFlow,
    useWizardSection,
    type WizardSectionId,
} from '@/composables/useCampaignWizardFlow';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CampaignGenerationModal from '@/components/campaigns/CampaignGenerationModal.vue';
import CampaignAffiliateLinkDialog from '@/components/campaigns/CampaignAffiliateLinkDialog.vue';
import CampaignCreateSetupDialog, { type CampaignIntakeMode } from '@/components/campaigns/CampaignCreateSetupDialog.vue';
import type { GenerationState } from '@/components/campaigns/GenerationProgressPanel.vue';
import CampaignFunnelPageEditor from '@/components/campaigns/CampaignFunnelPageEditor.vue';
import BonusCoverArt from '@/components/campaigns/BonusCoverArt.vue';
import BonusViewerShell from '@/components/campaigns/BonusViewerShell.vue';
import CampaignTrafficStepPanel from '@/components/campaign-traffic/CampaignTrafficStepPanel.vue';
import {
    bonusDetailLine,
    bonusOpenLabel,
    bonusTypeLabel,
    bonusValueLabel,
    bonusViewerUrl,
} from '@/composables/useBonusDisplay';
import { buildBonusViewerSlides } from '@/composables/useBonusViewerSlides';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type LibraryBonus = {
    id: number;
    uuid: string;
    title: string;
    bonus_type: string;
    campaign_id: number | null;
    campaign_name?: string | null;
    content: string | null;
    meta?: Record<string, unknown>;
    created_at?: string | null;
};

const LIBRARY_INITIAL_LIMIT = 4;

type OfferData = Record<string, string | undefined>;

type CampaignPayload = {
    id: number;
    uuid: string;
    name: string;
    slug: string;
    type: 'sales' | 'webinar';
    status: string;
    wizard_step: number;
    offer_url: string | null;
    affiliate_link: string | null;
    marketplace: string | null;
    offer_data: OfferData | null;
    analysis: Record<string, unknown> | null;
    knowledge: { status?: string; pass1?: Record<string, unknown>; pass2?: Record<string, unknown> } | null;
    bonus_suggestions: Array<Record<string, string>>;
    bonus_type: string | null;
    esp_upload?: { ok?: boolean; message?: string; uploaded?: number; provider?: string } | null;
    lead_magnet: Record<string, unknown> | null;
    pages: Array<{ id?: number; page_type: string; content: Record<string, unknown> }>;
    bonuses: Array<{ id: number; uuid: string; title: string; bonus_type: string; content: string | null; meta?: Record<string, unknown> | null }>;
    emails: Array<{ id: number; subject: string; body: string | null; sequence_key: string | null }>;
    funnels: Array<{ id: number; name: string; edit_url: string; public_url: string; optin_url?: string | null; webinar_room_url?: string | null; role?: string; role_label?: string }>;
    tracked_links: Array<{ id: number; label: string | null; public_url: string; destination_url: string; click_count: number }>;
    public_pages: Record<string, string>;
    traffic_hub_url?: string | null;
    generation: GenerationState | null;
    generation_state: Record<string, { generated?: boolean; at?: string; selected_id?: string }>;
    quick_start?: boolean;
};

const props = defineProps<{
    campaign: CampaignPayload | null;
    integrationAccounts: Array<{ id: number; name: string; provider: string }>;
    bonusLibrary?: LibraryBonus[];
    intakePrefill?: {
        mode: 'links' | 'keyword';
        keyword?: string;
        offer_url?: string | null;
        title?: string | null;
        marketplace?: string | null;
        type?: 'sales' | 'webinar';
    } | null;
}>();

const campaignRef = computed(() => props.campaign);
const flow = useCampaignWizardFlow(campaignRef);
const page = usePage();
const paidAdsEnabled = computed(() => page.props.paidAdsEnabled === true);
const section = ref<WizardSectionId>('offer');
const { goToSection: goToSectionBase } = useWizardSection(section, flow, toast);

const pinnedSection = ref<WizardSectionId | null>(null);

const generationStepToSection: Record<string, WizardSectionId> = {
    knowledge: 'knowledge',
    lead_magnet_suggest: 'lead_magnet',
    lead_magnet: 'lead_magnet',
    pages: 'pages',
    bonuses_suggest: 'bonuses',
    bonuses: 'bonuses',
    emails: 'emails',
    webinar: 'webinar',
    quick_start: 'knowledge',
};

const pathToSection: Partial<Record<string, WizardSectionId>> = {
    'build-knowledge': 'knowledge',
    'lead-magnet/suggest': 'lead_magnet',
    'lead-magnet/generate': 'lead_magnet',
    'build-pages': 'pages',
    'bonuses/suggest': 'bonuses',
    'bonuses/generate': 'bonuses',
    'generate-emails': 'emails',
    'generate-webinar-funnels': 'webinar',
};

const backgroundQueuePaths = new Set(Object.keys(pathToSection));

const generationStepLabels: Record<string, string> = {
    knowledge: 'Knowledge base',
    lead_magnet_suggest: 'Lead magnet ideas',
    lead_magnet: 'Lead magnet',
    pages: 'Funnel pages',
    bonuses_suggest: 'Bonus ideas',
    bonuses: 'Bonus deliverable',
    emails: 'Email swipes',
    webinar: 'Webinar funnels',
    quick_start: 'Full campaign build',
};

function pinSectionForGeneration(stepOrPath: string) {
    const sectionId = generationStepToSection[stepOrPath] ?? pathToSection[stepOrPath];
    if (sectionId) {
        pinnedSection.value = sectionId;
        section.value = sectionId;
    }
}

function goToSection(id: WizardSectionId) {
    if (isGenerationRunning.value && pinnedSection.value && id !== pinnedSection.value) {
        toast.message('Generation in progress — stay on this step until it finishes.');
        return;
    }
    goToSectionBase(id);
    if (!isGenerationRunning.value) {
        nextTick(() => runStepAutoAction(id));
    }
}

const processing = ref<string | null>(null);
const importPhase = ref<'idle' | 'extract' | 'save' | 'analyse'>('idle');
const autoRunLock = ref<string | null>(null);
const editingPage = ref<string | null>(null);
const extractError = ref<string | null>(null);
const extractMeta = ref<{ fetch_method?: string; extraction_quality?: string } | null>(null);
const selectedLeadMagnetId = ref('');
const leadMagnetSuggestTriggered = ref(false);
const selectedBonusType = ref<'ebook' | 'mini_course'>('ebook');
const selectedBonusId = ref('');
const showBonusBuilder = ref(true);
const bonusIdeasForType = ref(false);
const bonusTypeChosen = ref(false);

const bonusTypeOptions = [
    { id: 'ebook' as const, label: 'Ebook', description: 'PDF-ready professional guide', icon: 'heroicons:book-open', disabled: false },
    { id: 'mini_course' as const, label: 'Mini Course', description: 'Slide-based lessons with swipe navigation', icon: 'heroicons:academic-cap', disabled: false },
    { id: 'mini_app' as const, label: 'Mini App', description: 'Coming soon', icon: 'heroicons:cpu-chip', disabled: true },
];
const emailCount = ref(5);

const createSetupDone = ref(!!props.campaign);
const offerIntakeMode = ref<CampaignIntakeMode>('links');
const keywordQuery = ref('');
const keywordSearching = ref(false);
const keywordResults = ref<Array<{ title: string; marketplace: string; url: string; gravity_hint?: string; epc_hint?: string; why?: string; source?: string; score?: number; score_label?: string; promote_reason?: string }>>([]);
const keywordSources = ref<string[]>([]);
const keywordSearchLinks = ref<Array<{ label: string; marketplace: string; url: string }>>([]);
const keywordSearchError = ref<string | null>(null);
const selectedKeywordOffer = ref<{ title: string; marketplace: string; url: string } | null>(null);

const createForm = useForm({
    name: props.campaign?.name ?? '',
    type: (props.campaign?.type ?? 'sales') as 'sales' | 'webinar',
    offer_url: props.campaign?.offer_url ?? '',
    affiliate_link: props.campaign?.affiliate_link ?? '',
    marketplace: props.campaign?.marketplace ?? '',
    offer_data: (props.campaign?.offer_data ?? {}) as OfferData,
});

const sidebarSections = computed(() => {
    if (!props.campaign) {
        return [{ id: 'offer' as WizardSectionId, label: 'Import Offer', icon: 'heroicons:link', complete: false, unlocked: true, blockedReason: null }];
    }
    return flow.steps.value.map((s, i) => ({
        id: s.id,
        label: `${i + 1}. ${s.label}`,
        icon: s.icon,
        complete: s.complete,
        unlocked: s.unlocked,
        blockedReason: s.blockedReason,
    }));
});

function wizardProgressPercent(step: number): number {
    return Math.min(100, Math.round((step / 8) * 100));
}

const completedStepsCount = computed(() => sidebarSections.value.filter((s) => s.complete).length);

function campaignTypeIcon(type: string): string {
    return type === 'webinar' ? 'heroicons:video-camera' : 'heroicons:shopping-bag';
}

const knowledgeReady = flow.knowledgeReady;
const offerAnalysis = computed(() => props.campaign?.analysis ?? null);
const leadMagnetReady = flow.leadMagnetReady;
const leadMagnetSkipped = flow.leadMagnetSkipped;
const leadMagnetHasContent = flow.leadMagnetHasContent;
const leadMagnetGenerating = flow.leadMagnetGenerating;
const leadMagnetLog = computed(() => (props.campaign?.lead_magnet?.generation_log as Array<Record<string, unknown>>) ?? []);
const funnelPagesReady = flow.funnelPagesReady;
const bonusesReady = flow.bonusesReady;
const emailsReady = flow.emailsReady;
const webinarReady = flow.webinarReady;

const liveGeneration = ref<GenerationState | null>(props.campaign?.generation ?? null);
const affiliateLinkDialogOpen = ref(false);

function stepIsGenerated(step: string): boolean {
    return props.campaign?.generation_state?.[step]?.generated === true;
}

function generationStepStale(step: string | undefined): boolean {
    if (!step) return false;
    if (stepIsGenerated(step)) return true;
    if (step === 'lead_magnet_suggest' && flow.hasLeadMagnetSuggestions.value) return true;
    if ((step === 'lead_magnet') && leadMagnetHasContent.value) return true;
    if (step === 'pages' && funnelPagesReady.value) return true;
    if ((step === 'bonuses' || step === 'bonuses_suggest') && bonusesReady.value) return true;
    if (step === 'emails' && emailsReady.value) return true;
    if (step === 'webinar' && webinarReady.value) return true;
    if (step === 'knowledge' && knowledgeReady.value) return true;
    if (step === 'quick_start') {
        return emailsReady.value && funnelPagesReady.value
            && (props.campaign?.type !== 'webinar' || webinarReady.value);
    }

    return false;
}

const isGenerationRunning = computed(() => {
    if (liveGeneration.value?.status !== 'running') return false;

    return !generationStepStale(liveGeneration.value?.step);
});

const generationModalOpen = computed({
    get: () => isGenerationRunning.value || liveGeneration.value?.status === 'failed',
    set: (v: boolean) => {
        if (!v && liveGeneration.value?.status === 'failed') {
            liveGeneration.value = null;
        }
    },
});

const activeGenerationStepLabel = computed(() => {
    const step = liveGeneration.value?.step ?? '';
    return generationStepLabels[step] ?? 'AI generation';
});

const needsAffiliateLinkPrompt = computed(() => {
    const c = props.campaign;
    if (!c?.quick_start || c.affiliate_link) return false;
    if (isGenerationRunning.value) return false;

    return flow.emailsReady.value
        || liveGeneration.value?.status === 'completed'
        || c.generation_state?.emails?.generated === true;
});

function maybeOpenAffiliateLinkDialog() {
    if (needsAffiliateLinkPrompt.value) {
        affiliateLinkDialogOpen.value = true;
    }
}

function onAffiliateLinkSaved() {
    router.reload({ only: ['campaign'], preserveScroll: true });
}

watch(needsAffiliateLinkPrompt, (needs) => {
    if (needs) {
        affiliateLinkDialogOpen.value = true;
    }
}, { immediate: true });

watch(
    () => props.campaign?.affiliate_link,
    (link) => {
        if (link) {
            createForm.affiliate_link = link;
            affiliateLinkDialogOpen.value = false;
        }
    },
);

const lockedLeadMagnetId = computed(() =>
    String(liveGeneration.value?.selected_id ?? selectedLeadMagnetId.value ?? ''),
);

let generationPollTimer: ReturnType<typeof setInterval> | null = null;

async function pollGenerationStatus() {
    if (!props.campaign) return;
    try {
        const res = await fetch(`/campaigns/${props.campaign.id}/generation-status`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) return;
        liveGeneration.value = await res.json();
        if (liveGeneration.value?.status === 'completed') {
            stopGenerationPolling();
            pinnedSection.value = null;
            toast.success(liveGeneration.value.message ?? 'Generation complete');
            router.reload({ only: ['campaign'], preserveScroll: true, onSuccess: () => maybeOpenAffiliateLinkDialog() });
        } else if (liveGeneration.value?.status === 'failed') {
            stopGenerationPolling();
            pinnedSection.value = null;
            router.reload({ only: ['campaign'], preserveScroll: true });
        }
    } catch {
        /* ignore transient poll errors */
    }
}

function startGenerationPolling() {
    if (generationPollTimer || !props.campaign) return;
    void pollGenerationStatus();
    generationPollTimer = setInterval(pollGenerationStatus, 2000);
}

function stopGenerationPolling() {
    if (generationPollTimer) {
        clearInterval(generationPollTimer);
        generationPollTimer = null;
    }
}

watch(
    () => props.campaign?.generation,
    (g) => {
        if (g?.status === 'running' && generationStepStale(g?.step)) {
            liveGeneration.value = null;
            stopGenerationPolling();
            pinnedSection.value = null;

            return;
        }
        liveGeneration.value = g ?? null;
        if (g?.status === 'running') {
            if (g.step) pinSectionForGeneration(g.step);
            startGenerationPolling();
        }
        if (g?.status === 'completed' || g?.status === 'failed' || !g) {
            if (g?.status !== 'running') pinnedSection.value = null;
            stopGenerationPolling();
        }
    },
    { immediate: true, deep: true },
);

onUnmounted(() => stopGenerationPolling());

const syncAdvanceAfter: Partial<Record<string, WizardSectionId>> = {
    analyse: 'knowledge',
};

const salesFunnelPageMeta = computed(() => ({
    squeeze: { title: props.campaign?.type === 'webinar' ? 'Webinar registration' : 'Squeeze page' },
    thankyou: { title: 'Thank you page' },
    quiz: { title: 'Quiz page' },
    bonus: { title: 'Bonus page' },
}));

const funnelPageTypes = computed(() => {
    if (props.campaign?.type === 'webinar') {
        return ['squeeze', 'bonus'] as const;
    }

    return ['squeeze', 'thankyou', 'quiz', 'bonus'] as const;
});
const pageByType = (type: string) => props.campaign?.pages.find((p) => p.page_type === type)?.content ?? {};

const squeezeForm = ref({ ...pageByType('squeeze') });
const thankyouForm = ref({ ...pageByType('thankyou') });
const quizForm = ref({ ...pageByType('quiz') });
const bonusPageForm = ref({ ...pageByType('bonus') });
const featuredBonusUuids = ref<string[]>([]);
const librarySearch = ref('');
const libraryDateFilter = ref<'all' | '7d' | '30d' | '90d'>('all');
const libraryShowAll = ref(false);
const libraryBonuses = ref<LibraryBonus[]>(props.bonusLibrary ?? []);
const librarySearching = ref(false);
const expandedBonusPreview = ref<string | null>(null);

const selectableBonuses = computed(() => {
    const map = new Map<string, LibraryBonus>();
    for (const b of props.campaign?.bonuses ?? []) {
        map.set(b.uuid, {
            id: b.id,
            uuid: b.uuid,
            title: b.title,
            bonus_type: b.bonus_type,
            campaign_id: props.campaign?.id ?? null,
            campaign_name: props.campaign?.name ?? null,
            content: b.content,
            meta: b.meta ?? {},
            created_at: (b as { created_at?: string }).created_at ?? null,
        });
    }
    for (const b of libraryBonuses.value) {
        if (!map.has(b.uuid)) map.set(b.uuid, b);
    }
    return [...map.values()];
});

const filteredSelectableBonuses = computed(() => {
    let list = [...selectableBonuses.value];

    if (libraryDateFilter.value !== 'all') {
        const days = { '7d': 7, '30d': 30, '90d': 90 }[libraryDateFilter.value];
        const cutoff = Date.now() - days * 86_400_000;
        list = list.filter((b) => {
            if (!b.created_at) return true;
            return new Date(b.created_at).getTime() >= cutoff;
        });
    }

    return list.sort((a, b) => {
        const ta = a.created_at ? new Date(a.created_at).getTime() : 0;
        const tb = b.created_at ? new Date(b.created_at).getTime() : 0;
        return tb - ta;
    });
});

const visibleSelectableBonuses = computed(() => {
    const list = filteredSelectableBonuses.value;
    if (librarySearch.value.trim() !== '' || libraryShowAll.value) {
        return list;
    }
    return list.slice(0, LIBRARY_INITIAL_LIMIT);
});

const hiddenLibraryCount = computed(() => {
    if (librarySearch.value.trim() !== '' || libraryShowAll.value) return 0;
    return Math.max(0, filteredSelectableBonuses.value.length - LIBRARY_INITIAL_LIMIT);
});

function formatBonusDate(iso?: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
}

function bonusSlidesFor(b: { title: string; bonus_type: string; meta?: Record<string, unknown> | null }) {
    return buildBonusViewerSlides(b);
}

let librarySearchTimer: ReturnType<typeof setTimeout> | null = null;

watch(librarySearch, (q) => {
    if (librarySearchTimer) clearTimeout(librarySearchTimer);
    librarySearchTimer = setTimeout(() => void searchBonusLibrary(q), 300);
});

watch(libraryDateFilter, () => {
    libraryShowAll.value = false;
    void searchBonusLibrary(librarySearch.value);
});

async function searchBonusLibrary(q: string) {
    librarySearching.value = true;
    try {
        const params = new URLSearchParams();
        if (q.trim()) params.set('q', q.trim());
        if (libraryDateFilter.value !== 'all') params.set('since', libraryDateFilter.value);
        const query = params.toString();
        const url = query ? `/bonuses/search?${query}` : '/bonuses/search';
        const res = await fetch(url, { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            libraryBonuses.value = data.bonuses ?? [];
        }
    } finally {
        librarySearching.value = false;
    }
}

watch(() => props.campaign?.pages, () => {
    squeezeForm.value = { ...pageByType('squeeze') };
    thankyouForm.value = { ...pageByType('thankyou') };
    quizForm.value = { ...pageByType('quiz') };
    bonusPageForm.value = { ...pageByType('bonus') };
    syncFeaturedBonusSelection();
}, { deep: true });

watch(
    () => props.campaign?.bonuses,
    () => syncFeaturedBonusSelection(),
    { deep: true, immediate: true },
);

function syncFeaturedBonusSelection() {
    const saved = pageByType('bonus').featured_bonus_uuids;
    const campaignUuids = props.campaign?.bonuses?.map((b) => b.uuid) ?? [];

    if (Array.isArray(saved) && saved.length > 0) {
        featuredBonusUuids.value = [...saved as string[]];
        return;
    }

    featuredBonusUuids.value = campaignUuids.length ? [...campaignUuids] : [];
}

watch(
    () => props.campaign?.lead_magnet,
    (lm) => {
        if (!lm) return;
        const savedId = lm.selected_id as string | undefined;
        const hasGeneratedContent = lm.generated === true
            || String(lm.status ?? '') === 'ready'
            || (Array.isArray(lm.pages) && lm.pages.length > 0);
        if (savedId && hasGeneratedContent) {
            selectedLeadMagnetId.value = savedId;
        }
        const suggestions = lm.suggestions as Array<{ id: string }> | undefined;
        if (Array.isArray(suggestions) && suggestions.length > 0) {
            leadMagnetSuggestTriggered.value = true;
        }
    },
    { immediate: true, deep: true },
);

watch(
    () => props.campaign,
    (c) => {
        if (!c) return;
        if (c.bonus_type === 'ebook' || c.bonus_type === 'mini_course') {
            selectedBonusType.value = c.bonus_type;
        }
        const existing = c.bonuses[0];
        if (existing?.meta?.selected_id) {
            selectedBonusId.value = String(existing.meta.selected_id);
        }
        const rich = (Array.isArray(existing?.meta?.pages) && (existing.meta.pages as unknown[]).length > 0)
            || (Array.isArray(existing?.meta?.slides) && (existing.meta.slides as unknown[]).length > 0);
        showBonusBuilder.value = !rich;
        if (rich && (c.bonus_type === 'ebook' || c.bonus_type === 'mini_course')) {
            bonusTypeChosen.value = true;
        }
        bonusIdeasForType.value = (c.bonus_suggestions?.length ?? 0) >= 3
            && c.bonus_type === selectedBonusType.value;
    },
    { immediate: true },
);

const bonusHasRichContent = computed(() => {
    const b = props.campaign?.bonuses[0];
    if (!b?.meta) return false;

    return (Array.isArray(b.meta.pages) && b.meta.pages.length > 0)
        || (Array.isArray(b.meta.slides) && b.meta.slides.length > 0);
});

function bonusIsRich(b: { meta?: Record<string, unknown> | null }): boolean {
    const meta = b.meta ?? {};
    return (Array.isArray(meta.pages) && meta.pages.length > 0)
        || (Array.isArray(meta.slides) && meta.slides.length > 0);
}

const generatedBonuses = computed(() =>
    (props.campaign?.bonuses ?? []).filter((b) => bonusIsRich(b)),
);

watch(
    generatedBonuses,
    (list) => {
        if (list.length > 0 && !expandedBonusPreview.value) {
            expandedBonusPreview.value = list[0].uuid;
        }
    },
    { immediate: true },
);

async function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function runOfferExtract(): Promise<boolean> {
    if (!createForm.offer_url) return false;
    extractError.value = null;
    importPhase.value = 'extract';
    try {
        const res = await fetch('/campaigns/extract-offer', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': await csrfToken() },
            body: JSON.stringify({ url: createForm.offer_url }),
        });
        const data = await res.json();
        if (data.offer) {
            createForm.offer_data = data.offer;
            createForm.marketplace = data.offer.marketplace ?? createForm.marketplace;
            if (!createForm.name || /^https?:\/\//i.test(createForm.name)) {
                createForm.name = data.offer.product_name ?? createForm.name;
            }
            extractMeta.value = {
                fetch_method: data.fetch_method ?? data.offer.fetch_method,
                extraction_quality: data.offer.extraction_quality,
            };
        }
        if (data.error) {
            extractError.value = data.error;
            return false;
        }
        extractError.value = null;
        return true;
    } catch {
        extractError.value = 'Could not reach the sales page — check the URL and try again.';
        return false;
    } finally {
        if (importPhase.value === 'extract') importPhase.value = 'idle';
    }
}

function ensureCampaignName() {
    if (createForm.name && !/^https?:\/\//i.test(createForm.name)) return;
    const fromOffer = createForm.offer_data?.product_name;
    if (fromOffer) {
        createForm.name = fromOffer;
        return;
    }
    try {
        const host = new URL(createForm.offer_url).hostname.replace(/^www\./, '');
        createForm.name = host.split('.')[0].replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) || 'New Campaign';
    } catch {
        createForm.name = 'New Campaign';
    }
}

function runAnalyseForCampaign(campaignId: number) {
    importPhase.value = 'analyse';
    router.post(`/campaigns/${campaignId}/analyse`, {
        offer_url: createForm.offer_url || null,
        affiliate_link: createForm.affiliate_link || null,
        offer_data: createForm.offer_data,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            section.value = 'knowledge';
            nextTick(() => runStepAutoAction('knowledge'));
        },
        onFinish: () => {
            importPhase.value = 'idle';
            processing.value = null;
        },
    });
}

async function importOfferAndContinue() {
    if (offerIntakeMode.value === 'keyword') {
        if (!selectedKeywordOffer.value || !createForm.offer_url) {
            toast.error('Search and pick a product first.');
            return;
        }
    } else if (!createForm.offer_url) {
        toast.error('Paste the sales page URL first.');
        return;
    }
    if (!createForm.affiliate_link) {
        toast.error('Add your affiliate hop link first.');
        return;
    }

    processing.value = 'offer-flow';
    const extracted = await runOfferExtract();
    if (!extracted && !createForm.offer_data?.product_name) {
        toast.error(extractError.value ?? 'Could not extract offer details from that page.');
        processing.value = null;
        return;
    }

    ensureCampaignName();
    importPhase.value = 'save';

    if (!props.campaign) {
        createForm.post('/campaigns', {
            preserveScroll: true,
            onSuccess: (page) => {
                const c = (page.props as { campaign?: CampaignPayload | null }).campaign;
                if (c?.id) {
                    runAnalyseForCampaign(c.id);
                } else {
                    importPhase.value = 'idle';
                    processing.value = null;
                }
            },
            onError: () => {
                importPhase.value = 'idle';
                processing.value = null;
            },
        });
        return;
    }

    router.patch(`/campaigns/${props.campaign.id}`, {
        name: createForm.name,
        type: createForm.type,
        offer_url: createForm.offer_url || null,
        affiliate_link: createForm.affiliate_link || null,
        offer_data: createForm.offer_data,
    }, {
        preserveScroll: true,
        onSuccess: () => runAnalyseForCampaign(props.campaign!.id),
        onError: () => {
            importPhase.value = 'idle';
            processing.value = null;
        },
    });
}

const importButtonLabel = computed(() => {
    if (importPhase.value === 'extract') return 'Extracting offer…';
    if (importPhase.value === 'save') return props.campaign ? 'Saving…' : 'Creating campaign…';
    if (importPhase.value === 'analyse') return 'Analyzing offer…';
    return props.campaign ? 'Update & continue' : 'Extract & continue';
});

const importInProgress = computed(() => processing.value === 'offer-flow' || importPhase.value !== 'idle');

function onCreateSetupComplete(payload: { type: 'sales' | 'webinar'; mode: CampaignIntakeMode }) {
    createForm.type = payload.type;
    offerIntakeMode.value = payload.mode;
    createSetupDone.value = true;
}

function restartCreateSetup() {
    createSetupDone.value = false;
    keywordResults.value = [];
    selectedKeywordOffer.value = null;
    keywordQuery.value = '';
}

async function searchOffersByKeyword() {
    if (!keywordQuery.value.trim()) return;
    keywordSearching.value = true;
    try {
        const res = await fetch('/campaigns/search-offers', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': await csrfToken() },
            body: JSON.stringify({ keyword: keywordQuery.value.trim() }),
        });
        const data = await res.json();
        keywordResults.value = data.results ?? [];
        keywordSources.value = data.sources ?? [];
        keywordSearchLinks.value = data.search_links ?? [];
        keywordSearchError.value = data.error ?? null;
        if (data.error && !keywordResults.value.length) {
            toast.error(data.error);
        }
    } catch {
        toast.error('Product search failed — try again.');
    } finally {
        keywordSearching.value = false;
    }
}

function pickKeywordOffer(offer: { title: string; marketplace: string; url: string; why?: string }) {
    selectedKeywordOffer.value = offer;
    createForm.offer_url = offer.url;
    createForm.marketplace = offer.marketplace;
    createForm.offer_data = {
        ...createForm.offer_data,
        product_name: offer.title,
        marketplace: offer.marketplace,
        source_url: offer.url,
    };
    if (!createForm.name) createForm.name = offer.title;
}

function clearKeywordOfferSelection() {
    selectedKeywordOffer.value = null;
    createForm.offer_url = '';
    createForm.marketplace = '';
    createForm.offer_data = {
        ...createForm.offer_data,
        product_name: '',
        marketplace: '',
        source_url: '',
    };
}

const canImportOffer = computed(() => {
    if (!createForm.affiliate_link) return false;
    if (offerIntakeMode.value === 'keyword') {
        return Boolean(createForm.offer_url && selectedKeywordOffer.value);
    }
    return Boolean(createForm.offer_url);
});

const quickStartAwaitingAffiliate = computed(() =>
    Boolean(props.campaign?.quick_start && !props.campaign.affiliate_link && flow.emailsReady.value),
);

function post(path: string, data: Record<string, unknown> = {}, onSuccess?: () => void) {
    processing.value = path;
    router.post(`/campaigns/${props.campaign!.id}/${path}`, data, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = null;
            autoRunLock.value = null;
        },
        onSuccess: (page) => {
            onSuccess?.();
            const flash = page.props.flash as { toast?: { type?: string; message?: string } } | undefined;
            const skipped = flash?.toast?.type === 'info'
                && /already|saved version|no regeneration|showing saved|review below|edit below/i.test(flash.toast.message ?? '');
            if (skipped) {
                return;
            }
            if (backgroundQueuePaths.has(path)) {
                pinSectionForGeneration(path);
                startGenerationPolling();
                return;
            }
            const next = syncAdvanceAfter[path];
            if (next) {
                section.value = next;
            }
            nextTick(() => {
                if (!isGenerationRunning.value) runStepAutoAction(section.value);
            });
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            toast.error(typeof first === 'string' ? first : 'Request failed — check fields and try again.');
        },
    });
}

const knowledgeBuilding = computed(() =>
    isGenerationRunning.value && liveGeneration.value?.step === 'knowledge',
);

function canAutoBuildKnowledge(): boolean {
    if (!props.campaign || knowledgeReady.value) return false;
    if (!flow.offerComplete.value) return false;

    return Boolean(createForm.affiliate_link || props.campaign.affiliate_link);
}

function runBuildKnowledge() {
    if (knowledgeBuilding.value || processing.value === 'build-knowledge') return;
    post('build-knowledge');
}

function generateLeadMagnet(mode: 'auto' | 'enhance' | 'restart' = 'auto') {
    if (!selectedLeadMagnetId.value || isGenerationRunning.value) return;
    post('lead-magnet/generate', { selected_id: selectedLeadMagnetId.value, mode });
}

function refreshLeadMagnetSuggestions() {
    if (isGenerationRunning.value || leadMagnetReady.value) return;
    leadMagnetSuggestTriggered.value = true;
    post('lead-magnet/suggest', { force: true });
}

function refreshBonusSuggestions() {
    if (isGenerationRunning.value || processing.value || !selectedBonusType.value) return;
    post('bonuses/suggest', { bonus_type: selectedBonusType.value, force: true }, () => {
        bonusIdeasForType.value = true;
    });
}

function selectBonusType(type: 'ebook' | 'mini_course') {
    if (isGenerationRunning.value || processing.value) return;
    bonusTypeChosen.value = true;
    selectedBonusType.value = type;
    selectedBonusId.value = '';
    bonusIdeasForType.value = false;
    post('bonuses/suggest', { bonus_type: type, force: true }, () => {
        bonusIdeasForType.value = true;
    });
}

function onBonusPick(id: string) {
    if (isGenerationRunning.value) return;
    selectedBonusId.value = id;
}

function generateSelectedBonus() {
    if (!selectedBonusId.value || !selectedBonusType.value || isGenerationRunning.value) return;
    post('bonuses/generate', { selected_id: selectedBonusId.value, bonus_type: selectedBonusType.value }, () => {
        showBonusBuilder.value = false;
    });
}

function resetBonusFlow() {
    post('bonuses/reset', {}, () => {
        showBonusBuilder.value = true;
        selectedBonusId.value = '';
        bonusIdeasForType.value = false;
        bonusTypeChosen.value = false;
    });
}

function openPageEditor(pageType: string) {
    editingPage.value = pageType;
}

function closePageEditor() {
    editingPage.value = null;
}

function savePageFromEditor(pageType: string, content: Record<string, unknown>) {
    savePage(pageType, content);
    closePageEditor();
}

function runStepAutoAction(stepId: WizardSectionId) {
    if (!props.campaign || processing.value || importInProgress.value || isGenerationRunning.value) return;
    if (!flow.isStepUnlocked(stepId)) return;
    if (autoRunLock.value === stepId) return;

    switch (stepId) {
        case 'knowledge':
            if (!canAutoBuildKnowledge()) break;
            autoRunLock.value = stepId;
            runBuildKnowledge();
            break;
        case 'lead_magnet':
            if (props.campaign.type === 'webinar') break;
            if (leadMagnetHasContent.value || stepIsGenerated('lead_magnet')) break;
            if (flow.hasLeadMagnetSuggestions.value || stepIsGenerated('lead_magnet_suggest') || leadMagnetSuggestTriggered.value) {
                break;
            }
            leadMagnetSuggestTriggered.value = true;
            autoRunLock.value = stepId;
            post('lead-magnet/suggest');
            break;
        case 'pages':
            if (funnelPagesReady.value) break;
            autoRunLock.value = stepId;
            post('build-pages');
            break;
        case 'webinar':
            if (webinarReady.value) break;
            if (props.campaign.type === 'webinar' && funnelPagesReady.value) {
                autoRunLock.value = stepId;
                post('generate-webinar-funnels');
            }
            break;
        case 'bonuses':
            break;
        case 'emails':
            if (emailsReady.value) break;
            autoRunLock.value = stepId;
            post('generate-emails', { count: emailCount.value });
            break;
        default:
            break;
    }
}

function savePage(pageType: string, content: Record<string, unknown>) {
    router.patch(`/campaigns/${props.campaign!.id}/pages/${pageType}`, { content }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Design saved.'),
    });
}

function toggleFeaturedBonus(uuid: string) {
    const set = new Set(featuredBonusUuids.value);
    if (set.has(uuid)) set.delete(uuid);
    else set.add(uuid);
    featuredBonusUuids.value = [...set];
}

function saveFeaturedBonuses() {
    savePage('bonus', {
        ...bonusPageForm.value,
        featured_bonus_uuids: featuredBonusUuids.value,
    });
}

const publicPageLabels: Record<string, string> = {
    squeeze: 'Opt-in page',
    thankyou: 'Thank you page',
    quiz: 'Quiz page',
    bonus: 'Bonus page',
    webinar_room: 'Webinar room',
};

const trackedLinkTotalClicks = computed(() =>
    (props.campaign?.tracked_links ?? []).reduce((sum, l) => sum + l.click_count, 0),
);

const copiedTrackedLinkId = ref<number | null>(null);
const publishEspAccountId = ref<number | ''>('');
const publishEspTag = ref('');

async function copyTrackedLink(url: string, id: number) {
    try {
        await navigator.clipboard.writeText(url);
        copiedTrackedLinkId.value = id;
        toast.success('Link copied');
        setTimeout(() => {
            if (copiedTrackedLinkId.value === id) copiedTrackedLinkId.value = null;
        }, 2000);
    } catch {
        toast.error('Could not copy link');
    }
}

function publishCampaign() {
    post('publish', {
        integration_account_id: publishEspAccountId.value || undefined,
        esp_tag: publishEspTag.value.trim() || undefined,
    });
}

onMounted(() => {
    if (!props.campaign && props.intakePrefill) {
        createSetupDone.value = true;
        createForm.type = props.intakePrefill.type ?? 'webinar';
        offerIntakeMode.value = props.intakePrefill.mode ?? 'keyword';
        if (props.intakePrefill.keyword) {
            keywordQuery.value = props.intakePrefill.keyword;
        }
        if (props.intakePrefill.offer_url) {
            pickKeywordOffer({
                title: props.intakePrefill.title ?? props.intakePrefill.keyword ?? 'Selected offer',
                marketplace: props.intakePrefill.marketplace ?? 'clickbank',
                url: props.intakePrefill.offer_url,
            });
        } else if (props.intakePrefill.keyword) {
            void searchOffersByKeyword();
        }
        return;
    }

    if (!props.campaign) return;
    if (flow.hasLeadMagnetSuggestions.value) {
        leadMagnetSuggestTriggered.value = true;
    }
    if (props.campaign.generation?.status === 'running') {
        liveGeneration.value = props.campaign.generation;
        if (props.campaign.generation.step) {
            pinSectionForGeneration(props.campaign.generation.step);
        }
        startGenerationPolling();
        return;
    }
    section.value = flow.firstIncompleteStep();
    const isRevisit = leadMagnetHasContent.value && funnelPagesReady.value;
    if (isRevisit) return;
    nextTick(() => runStepAutoAction(section.value));
});

// Auto-run chains from post() onSuccess and first visit to a step — never re-generates lead magnet content without explicit click
</script>

<template>
    <Head :title="campaign ? campaign.name : 'New Campaign'" />

    <div class="mx-auto flex w-full max-w-7xl gap-3 p-3 md:gap-4 md:p-4">
        <!-- Sidebar -->
        <aside v-if="campaign" class="hidden w-56 shrink-0 lg:block">
            <div class="sticky top-4 space-y-3">
                <div class="space-y-1 rounded-xl border border-border/60 bg-white p-2 shadow-sm">
                    <p class="px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-muted-foreground">Steps</p>
                    <button
                        v-for="s in sidebarSections"
                        :key="s.id"
                        type="button"
                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors"
                        :class="[
                            section === s.id ? 'bg-teal-600 text-white shadow-sm' : '',
                            !s.unlocked && section !== s.id ? 'cursor-not-allowed opacity-45' : section !== s.id ? 'hover:bg-teal-50' : '',
                        ]"
                        @click="goToSection(s.id)"
                    >
                        <Icon :icon="s.unlocked ? s.icon : 'heroicons:lock-closed'" class="size-4 shrink-0" />
                        <span class="flex-1 truncate">{{ s.label.replace(/^\d+\.\s/, '') }}</span>
                        <Icon v-if="s.complete" icon="heroicons:check-circle" class="size-4 shrink-0 text-green-500" :class="section === s.id ? 'text-green-200' : ''" />
                    </button>
                </div>
                <div class="rounded-xl border border-border/60 bg-white p-3 shadow-sm">
                    <div class="flex items-center justify-between text-[0.65rem] font-medium uppercase tracking-wide text-muted-foreground">
                        <span>Progress</span>
                        <span class="tabular-nums">{{ completedStepsCount }}/{{ sidebarSections.length }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-linear-to-r from-teal-600 to-cyan-400 transition-all"
                            :style="{ width: `${wizardProgressPercent(campaign.wizard_step)}%` }"
                        />
                    </div>
                    <p class="mt-2 text-[0.65rem] text-muted-foreground">Step {{ campaign.wizard_step }} of 8</p>
                </div>
            </div>
        </aside>

        <div class="min-w-0 flex-1 space-y-3 md:space-y-4">
            <!-- Header -->
            <div v-if="campaign" class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex min-w-0 flex-1 items-start gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-xl border border-teal-500/15 bg-teal-500/10">
                            <Icon :icon="campaignTypeIcon(campaign.type)" class="size-5 text-teal-600" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="truncate text-xl font-bold tracking-tight md:text-2xl">{{ campaign.name }}</h1>
                                <Badge variant="outline" class="capitalize text-[0.65rem]" :class="campaign.type === 'webinar' ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-teal-200 bg-teal-50 text-teal-700'">
                                    {{ campaign.type }}
                                </Badge>
                                <Badge variant="outline" class="capitalize text-[0.65rem]" :class="campaign.status === 'published' ? 'border-teal-200 bg-teal-50 text-teal-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
                                    {{ campaign.status }}
                                </Badge>
                                <Badge v-if="isGenerationRunning" variant="outline" class="border-teal-200 bg-teal-50 text-[0.65rem] text-teal-700">Generating…</Badge>
                                <Badge v-else-if="processing" variant="outline" class="border-amber-200 bg-amber-50 text-[0.65rem] text-amber-700">{{ processing }}…</Badge>
                                <Badge v-if="knowledgeReady" variant="outline" class="border-cyan-200 bg-cyan-50 text-[0.65rem] text-cyan-700">Knowledge ready</Badge>
                            </div>
                            <p class="mt-0.5 truncate text-sm text-muted-foreground">{{ campaign.slug }}</p>
                            <div class="mt-2 flex max-w-md items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-linear-to-r from-teal-600 to-cyan-400 transition-all"
                                        :style="{ width: `${wizardProgressPercent(campaign.wizard_step)}%` }"
                                    />
                                </div>
                                <span class="shrink-0 text-[0.65rem] font-medium text-muted-foreground">Step {{ campaign.wizard_step }}/8</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:gift" class="size-3 text-teal-600" />
                                    {{ campaign.bonuses.length }} bonuses
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:envelope" class="size-3 text-teal-600" />
                                    {{ campaign.emails.length }} emails
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground">
                                    <Icon icon="heroicons:shield-check" class="size-3 text-teal-600" />
                                    {{ campaign.tracked_links.length }} links
                                </span>
                                <span
                                    v-if="campaign.type === 'webinar'"
                                    class="inline-flex items-center gap-1 rounded-md bg-muted/40 px-2 py-0.5 text-[0.6rem] text-muted-foreground"
                                >
                                    <Icon icon="heroicons:video-camera" class="size-3 text-teal-600" />
                                    {{ campaign.funnels.length }} funnels
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button as-child variant="brand-outline" size="sm">
                            <Link href="/campaigns">
                                <Icon icon="heroicons:arrow-left" class="size-3.5" />
                                All campaigns
                            </Link>
                        </Button>
                        <Button as-child variant="brand" size="sm">
                            <Link :href="`/campaigns/${campaign.id}/traffic`">
                                <Icon icon="heroicons:signal" class="size-3.5" />
                                Traffic
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl font-bold tracking-tight md:text-2xl">New Campaign</h1>
                    </div>
                    <p class="mt-0.5 text-sm text-muted-foreground">Paste your offer once — AI builds the rest.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button as-child variant="brand-outline" size="sm">
                        <Link href="/growth/opportunities">
                            <Icon icon="heroicons:light-bulb" class="size-3.5" />
                            Find offers
                        </Link>
                    </Button>
                </div>
            </div>

            <CampaignGenerationModal
                v-if="campaign"
                v-model:open="generationModalOpen"
                :generation="liveGeneration"
                :step-label="activeGenerationStepLabel"
            />

            <CampaignAffiliateLinkDialog
                v-if="campaign"
                v-model:open="affiliateLinkDialogOpen"
                :campaign-id="campaign.id"
                :campaign-name="campaign.name"
                :offer-url="campaign.offer_url"
                :marketplace="campaign.marketplace"
                @saved="onAffiliateLinkSaved"
            />

            <!-- Step stepper -->
            <div v-if="campaign" class="hidden flex-wrap gap-1.5 rounded-xl border border-border/60 bg-white p-2 shadow-sm lg:flex">
                <button
                    v-for="s in sidebarSections"
                    :key="s.id"
                    type="button"
                    class="flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs transition-colors"
                    :class="[
                        section === s.id ? 'bg-teal-600 font-medium text-white shadow-sm' : '',
                        !s.unlocked ? 'cursor-not-allowed opacity-40' : section !== s.id ? 'hover:bg-teal-50 text-foreground' : '',
                    ]"
                    @click="goToSection(s.id)"
                >
                    <Icon v-if="s.complete" icon="heroicons:check-circle" class="size-3.5 text-green-500" :class="section === s.id ? 'text-green-200' : ''" />
                    <Icon v-else-if="!s.unlocked" icon="heroicons:lock-closed" class="size-3.5" />
                    <span>{{ s.label.replace(/^\d+\.\s/, '') }}</span>
                </button>
            </div>

            <!-- Mobile section picker -->
            <select
                v-if="campaign"
                :value="section"
                class="h-9 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm lg:hidden"
                @change="goToSection(($event.target as HTMLSelectElement).value as WizardSectionId)"
            >
                <option v-for="s in sidebarSections" :key="s.id" :value="s.id" :disabled="!s.unlocked">
                    {{ s.label }}{{ !s.unlocked ? ' (locked)' : '' }}
                </option>
            </select>

            <CampaignCreateSetupDialog
                v-if="!campaign"
                :open="!createSetupDone"
                @complete="onCreateSetupComplete"
            />

            <Card v-if="!campaign && !createSetupDone" class="border border-dashed border-teal-200/60 bg-white shadow-sm">
                <CardContent class="py-16 text-center">
                    <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-teal-500/10">
                        <Icon icon="heroicons:rocket-launch" class="size-7 text-teal-600/60" />
                    </div>
                    <p class="font-semibold text-foreground">Choose your campaign type</p>
                    <p class="mt-1 text-sm text-muted-foreground">Sales funnel or webinar — pick in the dialog above.</p>
                </CardContent>
            </Card>

            <!-- OFFER -->
            <Card v-show="createSetupDone && (!campaign || section === 'offer')" class="border border-border/60 bg-white shadow-sm">
                <CardHeader>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <CardTitle>Step 1 — Import Offer</CardTitle>
                            <CardDescription>
                                {{ offerIntakeMode === 'keyword'
                                    ? 'Search for a product, pick one, add your hop link, then continue.'
                                    : 'Paste both links below, then click once — we extract the offer and start building.' }}
                            </CardDescription>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Badge variant="outline" class="capitalize border-teal-200 bg-teal-50 text-teal-700">{{ createForm.type }} funnel</Badge>
                            <Badge variant="outline" class="border-border/60">{{ offerIntakeMode === 'keyword' ? 'Keyword search' : 'Direct links' }}</Badge>
                            <Button v-if="!campaign" variant="ghost" size="sm" class="text-teal-700 hover:text-teal-800" @click="restartCreateSetup">Change</Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="campaign" class="flex gap-2">
                        <Button size="sm" :variant="offerIntakeMode === 'links' ? 'brand' : 'outline'" @click="offerIntakeMode = 'links'">Paste links</Button>
                        <Button size="sm" :variant="offerIntakeMode === 'keyword' ? 'brand' : 'outline'" @click="offerIntakeMode = 'keyword'">Find by keyword</Button>
                    </div>

                    <div class="space-y-2">
                        <Label>Campaign name <span class="text-muted-foreground font-normal">(optional — auto-filled)</span></Label>
                        <Input v-model="createForm.name" placeholder="Auto-filled from sales page" />
                    </div>

                    <template v-if="offerIntakeMode === 'keyword'">
                        <div class="space-y-2">
                            <Label>Search keyword</Label>
                            <div class="flex gap-2">
                                <Input
                                    v-model="keywordQuery"
                                    class="flex-1"
                                    placeholder="e.g. ai automation, keto, email marketing"
                                    @keyup.enter="searchOffersByKeyword"
                                />
                                <Button variant="brand" :disabled="keywordSearching || !keywordQuery.trim()" @click="searchOffersByKeyword">
                                    <Icon v-if="keywordSearching" icon="heroicons:arrow-path" class="size-4 animate-spin" />
                                    <span v-else>Search</span>
                                </Button>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                JVZoo & WarriorPlus: live scrape via Jina. ClickBank: Apify only (React app — cannot scrape like the others). Pick an offer, add your hop link, then Extract & continue.
                            </p>
                        </div>

                        <p v-if="keywordSearching" class="flex items-center gap-2 rounded-xl border border-teal-200 bg-teal-50/50 p-4 text-sm text-teal-800">
                            <Icon icon="heroicons:arrow-path" class="size-4 shrink-0 animate-spin" />
                            Searching marketplaces…
                        </p>

                        <p v-if="keywordSearchError && !keywordResults.length && !keywordSearching" class="rounded-md border border-amber-200 bg-amber-50/80 p-2 text-xs text-amber-800">
                            {{ keywordSearchError }}
                        </p>

                        <div v-if="keywordSources.length && !selectedKeywordOffer" class="flex flex-wrap gap-1.5 text-xs text-muted-foreground">
                            <span>Live sources:</span>
                            <Badge v-for="s in keywordSources" :key="s" variant="outline">{{ s }}</Badge>
                        </div>

                        <div v-if="keywordResults.length && !selectedKeywordOffer" class="space-y-2">
                            <Label>Pick a product</Label>
                            <button
                                v-for="(r, i) in keywordResults"
                                :key="i"
                                type="button"
                                class="flex w-full items-start gap-3 rounded-xl border border-border/60 bg-white p-3 text-left shadow-sm transition-colors hover:border-teal-300 hover:bg-teal-50/40"
                                @click="pickKeywordOffer(r)"
                            >
                                <Badge variant="secondary" class="shrink-0 uppercase text-[10px]">{{ r.marketplace }}</Badge>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-medium text-sm">{{ r.title }}</p>
                                        <Badge v-if="r.score != null" variant="outline" class="text-[10px]">{{ r.score }} · {{ r.score_label }}</Badge>
                                    </div>
                                    <p v-if="r.promote_reason" class="mt-1 text-xs text-muted-foreground">{{ r.promote_reason }}</p>
                                    <p v-else-if="r.why" class="mt-1 text-xs text-muted-foreground">{{ r.why }}</p>
                                    <p v-if="r.gravity_hint || r.epc_hint" class="mt-1 text-xs text-muted-foreground">
                                        <span v-if="r.gravity_hint">Gravity: {{ r.gravity_hint }}</span>
                                        <span v-if="r.epc_hint"> · EPC: {{ r.epc_hint }}</span>
                                    </p>
                                </div>
                            </button>
                        </div>

                        <div v-if="selectedKeywordOffer" class="rounded-lg border border-green-200 bg-green-50/50 p-4 text-sm space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1 space-y-1">
                                    <p class="text-xs font-medium uppercase tracking-wide text-green-700">Selected product</p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Badge variant="secondary" class="uppercase text-[10px]">{{ selectedKeywordOffer.marketplace }}</Badge>
                                        <p class="font-semibold text-green-900">{{ selectedKeywordOffer.title }}</p>
                                    </div>
                                    <p class="text-xs text-green-700 break-all">{{ selectedKeywordOffer.url }}</p>
                                </div>
                                <Button size="sm" variant="outline" class="shrink-0" @click="clearKeywordOfferSelection">
                                    Change
                                </Button>
                            </div>
                        </div>

                        <div v-if="keywordSearchLinks.length && !selectedKeywordOffer" class="space-y-2">
                            <p class="text-xs font-medium text-muted-foreground">Or open marketplace search manually</p>
                            <div class="flex flex-wrap gap-2">
                                <Button v-for="link in keywordSearchLinks" :key="link.url" as-child size="sm" variant="outline">
                                    <a :href="link.url" target="_blank" rel="noopener noreferrer">{{ link.label }}</a>
                                </Button>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <div class="space-y-2">
                            <Label>Sales page URL</Label>
                            <Input v-model="createForm.offer_url" placeholder="https://vendor.com/sales-page" />
                            <p v-if="extractMeta?.fetch_method" class="text-xs text-muted-foreground">
                                Fetched via {{ extractMeta.fetch_method }}
                                <span v-if="extractMeta.extraction_quality"> · quality: {{ extractMeta.extraction_quality }}</span>
                            </p>
                        </div>
                    </template>

                    <div class="space-y-2">
                        <Label>Affiliate link (required for CTAs)</Label>
                        <Input v-model="createForm.affiliate_link" placeholder="Your hop link" />
                        <Button
                            v-if="quickStartAwaitingAffiliate && !createForm.affiliate_link"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="affiliateLinkDialogOpen = true"
                        >
                            Add hop link in popup
                        </Button>
                    </div>
                    <div
                        v-if="quickStartAwaitingAffiliate"
                        class="rounded-lg border border-teal-200 bg-teal-50/60 p-4 text-sm text-teal-900"
                    >
                        <p class="font-medium">Your sales funnel is ready — one last step</p>
                        <p class="mt-1 text-teal-800/90">Add your affiliate hop link so thank-you bridge, bonus page, and tracked links use your commission URL.</p>
                        <Button class="mt-3" size="sm" variant="brand" @click="affiliateLinkDialogOpen = true">Add affiliate link</Button>
                    </div>
                    <p v-if="extractError" class="text-xs text-amber-600">{{ extractError }}</p>
                    <div v-if="createForm.offer_data.product_name" class="rounded-xl border border-border/60 bg-teal-50/30 p-3 text-sm">
                        <p class="font-medium">{{ createForm.offer_data.product_name }}</p>
                        <p class="text-muted-foreground">{{ createForm.offer_data.headline }}</p>
                    </div>
                    <Button
                        v-if="!quickStartAwaitingAffiliate"
                        variant="brand"
                        :disabled="importInProgress || !canImportOffer"
                        @click="importOfferAndContinue"
                    >
                        <Icon v-if="importInProgress" icon="heroicons:arrow-path" class="mr-2 size-4 animate-spin" />
                        {{ importButtonLabel }}
                    </Button>
                    <p v-if="!canImportOffer" class="text-xs text-amber-600">
                        <template v-if="offerIntakeMode === 'keyword'">Pick a product and add your affiliate hop link.</template>
                        <template v-else>Sales page URL and affiliate hop link are both required.</template>
                    </p>

                    <div v-if="offerAnalysis" class="rounded-lg border border-teal-200 bg-teal-50/50 p-4 text-sm space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-semibold">Quick analysis</span>
                            <Badge>Score {{ offerAnalysis.offer_score ?? '—' }}/100</Badge>
                            <Badge variant="outline">Recommend: {{ offerAnalysis.recommended_funnel ?? campaign?.type }}</Badge>
                        </div>
                        <p class="text-muted-foreground">{{ offerAnalysis.summary }}</p>
                        <p v-if="Array.isArray(offerAnalysis.angles)" class="text-xs">
                            <span class="font-medium">Angles:</span> {{ (offerAnalysis.angles as string[]).join(' · ') }}
                        </p>
                        <Button size="sm" variant="brand-outline" @click="goToSection('knowledge')">View knowledge step →</Button>
                    </div>
                </CardContent>
            </Card>

            <template v-if="campaign">
                <!-- KNOWLEDGE -->
                <Card v-show="section === 'knowledge'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Step 2 — Knowledge Base</CardTitle>
                        <CardDescription>Auto-builds when you arrive here. 2 AI passes create the dossier for all assets.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div
                            v-if="knowledgeBuilding || processing === 'build-knowledge'"
                            class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4"
                        >
                            <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                            <div>
                                <p class="font-medium">{{ liveGeneration?.message ?? 'Building knowledge base…' }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ liveGeneration?.detail ?? 'Pass 1: research · Pass 2: asset blueprint (1–3 min)' }}
                                </p>
                            </div>
                        </div>
                        <div v-else-if="knowledgeReady" class="space-y-3 rounded-lg border border-green-200 bg-green-50/50 p-4 text-sm">
                            <p class="font-medium text-green-700">✓ Knowledge ready — lead magnet and funnel pages use this dossier</p>
                            <Collapsible>
                                <CollapsibleTrigger class="text-teal-600 underline">View pass 1 research</CollapsibleTrigger>
                                <CollapsibleContent>
                                    <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap text-xs">{{ JSON.stringify(campaign.knowledge?.pass1, null, 2) }}</pre>
                                </CollapsibleContent>
                            </Collapsible>
                            <Collapsible>
                                <CollapsibleTrigger class="text-teal-600 underline">View pass 2 asset plan</CollapsibleTrigger>
                                <CollapsibleContent>
                                    <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap text-xs">{{ JSON.stringify(campaign.knowledge?.pass2, null, 2) }}</pre>
                                </CollapsibleContent>
                            </Collapsible>
                        </div>
                        <div v-else-if="!flow.offerComplete.value" class="rounded-lg border border-amber-200 bg-amber-50/50 p-4 text-sm text-amber-800">
                            Complete Step 1 first — add your affiliate link and save the offer.
                        </div>
                        <div v-else class="space-y-3 rounded-xl border border-dashed border-teal-200/60 bg-teal-50/20 p-4">
                            <p class="text-sm text-muted-foreground">
                                Knowledge builds automatically when you land here. If nothing started, click below.
                            </p>
                            <Button size="sm" variant="brand" :disabled="!!processing || isGenerationRunning" @click="runBuildKnowledge">
                                <Icon icon="heroicons:sparkles" class="mr-1 size-4" />
                                Build knowledge base
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- LEAD MAGNET -->
                <Card v-show="section === 'lead_magnet'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Step 3 — Lead Magnet</CardTitle>
                        <CardDescription>
                            <template v-if="campaign.type === 'webinar'">
                                Optional for webinar campaigns — skip if you only need registration → room → offer.
                            </template>
                            <template v-else>
                                {{ leadMagnetReady
                                    ? 'Your lead magnet is saved — generate once per campaign.'
                                    : 'Ideas come from your knowledge base — pick one, then generate the full guide.' }}
                            </template>
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="campaign.type === 'webinar' && leadMagnetSkipped && !leadMagnetHasContent" class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                            Lead magnet skipped — you can still generate one later if you want a downloadable bonus.
                        </div>
                        <div v-if="campaign.type === 'webinar' && !leadMagnetReady" class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" :disabled="!!processing || isGenerationRunning" @click="post('lead-magnet/suggest')">
                                Generate optional lead magnet
                            </Button>
                            <Button variant="ghost" size="sm" :disabled="!!processing || isGenerationRunning" @click="post('lead-magnet/skip')">
                                Skip — not needed for webinar
                            </Button>
                        </div>
                        <div v-if="leadMagnetReady && !isGenerationRunning" class="rounded-lg border border-green-200 bg-green-50/50 p-3 text-sm text-green-700">
                            ✓ Lead magnet ready — showing your generated document below
                        </div>
                        <div v-if="isGenerationRunning && liveGeneration?.step === 'lead_magnet'" class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4">
                            <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                            <p class="font-medium">{{ liveGeneration?.message ?? 'Generating lead magnet…' }}</p>
                        </div>
                        <div v-if="leadMagnetReady && !isGenerationRunning" class="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" :disabled="isGenerationRunning" @click="generateLeadMagnet('enhance')">Enhance depth</Button>
                            <Button variant="outline" size="sm" :disabled="isGenerationRunning" @click="generateLeadMagnet('restart')">Regenerate from scratch</Button>
                        </div>
                        <div v-if="leadMagnetLog.length && !leadMagnetReady" class="rounded-xl border border-border/60 bg-teal-50/20 p-3 text-xs space-y-1">
                            <p class="font-medium">AI passes completed</p>
                            <p v-for="(log, i) in leadMagnetLog" :key="i" class="text-muted-foreground">
                                {{ log.step }} <span v-if="log.model">· {{ log.model }}</span>
                                <span v-if="Array.isArray(log.pages)"> · pages {{ (log.pages as number[]).join(', ') }}</span>
                            </p>
                        </div>
                        <div v-if="!leadMagnetReady && (campaign.lead_magnet?.suggestions as Array<Record<string, unknown>>)?.length" class="space-y-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">Choose a concept</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">
                                        Each option shows exactly what AI will build. Select one, then click Generate.
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="isGenerationRunning"
                                    @click="refreshLeadMagnetSuggestions"
                                >
                                    Reload from knowledge
                                </Button>
                            </div>
                            <p v-if="campaign.offer_data?.product_name" class="rounded-md bg-muted/40 px-3 py-2 text-xs">
                                <span class="font-medium text-foreground">Offer:</span>
                                {{ campaign.offer_data.product_name }}
                            </p>
                            <p v-if="isGenerationRunning" class="text-xs text-teal-700">
                                Generating <strong>{{ lockedLeadMagnetId ? (campaign.lead_magnet?.suggestions as Array<Record<string, string>>).find(s => s.id === lockedLeadMagnetId)?.title : 'selected idea' }}</strong> — other options locked until complete.
                            </p>
                            <label
                                v-for="s in (campaign.lead_magnet?.suggestions as Array<Record<string, unknown>>)"
                                :key="String(s.id)"
                                class="flex gap-3 rounded-xl border border-border/60 bg-white p-4 shadow-sm transition-colors"
                                :class="[
                                    isGenerationRunning && s.id !== lockedLeadMagnetId ? 'opacity-40 pointer-events-none' : 'cursor-pointer hover:border-teal-200 hover:bg-teal-50/30',
                                    s.id === lockedLeadMagnetId && isGenerationRunning ? 'ring-2 ring-teal-500 bg-teal-50/40 border-teal-300' : '',
                                    selectedLeadMagnetId === s.id && !isGenerationRunning ? 'border-teal-300 bg-teal-50/20' : '',
                                ]"
                            >
                                <input
                                    v-model="selectedLeadMagnetId"
                                    type="radio"
                                    :value="String(s.id)"
                                    :disabled="isGenerationRunning"
                                    class="mt-1"
                                />
                                <div class="min-w-0 flex-1 space-y-2">
                                    <p v-if="s.offer_anchor" class="text-[10px] font-semibold uppercase tracking-wide text-teal-700">
                                        {{ s.offer_anchor }}
                                    </p>
                                    <p class="font-semibold text-sm leading-snug">{{ s.title }}</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge variant="secondary" class="text-[10px]">{{ s.format_label || s.format }}</Badge>
                                        <Badge variant="outline" class="text-[10px]">{{ s.deliverable || 'Downloadable asset' }}</Badge>
                                    </div>
                                    <p v-if="s.preview" class="text-xs font-medium text-teal-800">
                                        Will generate: {{ s.preview }}
                                    </p>
                                    <p v-if="s.description" class="text-xs text-muted-foreground">{{ s.description }}</p>
                                    <ul v-if="Array.isArray(s.outline_bullets) && s.outline_bullets.length" class="text-xs text-muted-foreground list-disc space-y-0.5 pl-4">
                                        <li v-for="b in (s.outline_bullets as string[]).slice(0, 5)" :key="b">{{ b }}</li>
                                    </ul>
                                    <p v-if="s.why_it_converts" class="text-[11px] text-teal-700/90 italic">
                                        Why it converts: {{ s.why_it_converts }}
                                    </p>
                                </div>
                            </label>
                            <Button
                                variant="brand"
                                :disabled="!selectedLeadMagnetId || isGenerationRunning"
                                @click="generateLeadMagnet('auto')"
                            >
                                <Icon v-if="isGenerationRunning" icon="heroicons:arrow-path" class="mr-2 size-4 animate-spin" />
                                Generate selected lead magnet
                            </Button>
                        </div>
                        <div v-if="leadMagnetReady || (leadMagnetGenerating && (campaign.lead_magnet?.pages as Array<unknown>)?.length)" class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                            <p class="font-medium">
                                {{ campaign.lead_magnet?.title }}
                                · {{ campaign.lead_magnet?.page_count }} pages
                                <Badge v-if="(campaign.lead_magnet?.enhance_pass as number) > 0" variant="secondary" class="ml-2">
                                    Enhanced ×{{ campaign.lead_magnet?.enhance_pass }}
                                </Badge>
                            </p>
                            <a v-if="campaign.lead_magnet?.download_url" :href="String(campaign.lead_magnet.download_url)" target="_blank" class="text-sm text-teal-600 underline">Open printable version / Save as PDF</a>
                            <div class="lead-magnet-preview mt-4 max-h-[32rem] space-y-6 overflow-auto rounded-lg border bg-white p-4">
                                <div v-for="p in (campaign.lead_magnet?.pages as Array<Record<string,string>>)" :key="p.page" class="border-b pb-6 last:border-0">
                                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-teal-600">Page {{ p.page }}</p>
                                    <h3 class="mb-3 text-lg font-bold text-slate-900">{{ p.title }}</h3>
                                    <div class="lm-render prose prose-sm max-w-none" v-html="p.body_html" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- PAGES -->
                <Card v-show="section === 'pages'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Step 4 — Funnel Pages</CardTitle>
                        <CardDescription>Visual page editor — click elements on the page to edit, with live preview.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="processing === 'build-pages' && !funnelPagesReady" class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4">
                            <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                            <p class="font-medium">
                                {{ campaign.type === 'webinar'
                                    ? 'Generating webinar registration & bonus pages…'
                                    : 'Generating squeeze, thank-you, quiz & bonus pages…' }}
                            </p>
                        </div>
                        <div v-else-if="!funnelPagesReady && !leadMagnetReady && campaign.type !== 'webinar'" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-amber-800">
                            Complete lead magnet first — thank-you page needs the download link.
                        </div>
                        <div v-else-if="!funnelPagesReady && campaign.type === 'webinar'" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-amber-800">
                            Complete knowledge base first — registration copy comes from your offer research.
                        </div>
                        <div v-else-if="funnelPagesReady" class="rounded-lg border border-green-200 bg-green-50/50 p-3 text-sm text-green-700">
                            ✓ Funnel pages saved — open the visual editor to customize
                        </div>
                        <Button
                            v-if="funnelPagesReady && (leadMagnetReady || campaign.type === 'webinar')"
                            variant="outline"
                            size="sm"
                            :disabled="!!processing || isGenerationRunning"
                            @click="post('build-pages', { force: true })"
                        >
                            Regenerate all pages
                        </Button>

                        <CampaignFunnelPageEditor
                            v-if="editingPage && funnelPagesReady"
                            :page-type="editingPage as 'squeeze' | 'thankyou' | 'quiz' | 'bonus'"
                            :model-value="pageByType(editingPage)"
                            :public-url="campaign.public_pages[editingPage]"
                            :title="salesFunnelPageMeta[editingPage]?.title"
                            :preview-bonuses="campaign.bonuses"
                            :featured-bonus-uuids="featuredBonusUuids"
                            @save="(c) => savePageFromEditor(editingPage!, c)"
                            @close="closePageEditor"
                        />

                        <div v-if="!editingPage" class="space-y-3">
                        <div v-for="pageType in funnelPageTypes" :key="pageType" class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/60 bg-teal-50/20 px-4 py-3">
                                <div>
                                    <p class="font-medium text-sm">{{ salesFunnelPageMeta[pageType]?.title }}</p>
                                    <p v-if="pageByType(pageType).headline || pageByType(pageType).title" class="text-xs text-muted-foreground truncate max-w-md">
                                        {{ pageByType(pageType).headline || pageByType(pageType).title }}
                                    </p>
                                    <p v-else class="text-xs text-muted-foreground italic">Not generated yet</p>
                                </div>
                                <div class="flex gap-2">
                                    <Button v-if="funnelPagesReady" as-child variant="outline" size="sm">
                                        <a :href="campaign.public_pages[pageType]" target="_blank">Live preview</a>
                                    </Button>
                                    <Button
                                        v-if="funnelPagesReady"
                                        variant="brand"
                                        size="sm"
                                        @click="openPageEditor(pageType)"
                                    >
                                        Open page editor
                                    </Button>
                                </div>
                            </div>
                            <div v-if="funnelPagesReady" class="p-4 text-sm space-y-2">
                                <template v-if="pageType === 'squeeze'">
                                    <p class="font-semibold">{{ squeezeForm.headline }}</p>
                                    <p class="text-muted-foreground">{{ squeezeForm.subheadline }}</p>
                                </template>
                                <template v-else-if="pageType === 'thankyou'">
                                    <p>{{ thankyouForm.headline }}</p>
                                    <Badge variant="secondary">{{ thankyouForm.download_cta || 'DOWNLOAD NOW' }}</Badge>
                                </template>
                                <template v-else-if="pageType === 'quiz'">
                                    <p class="font-medium">{{ quizForm.title }}</p>
                                    <p v-if="Array.isArray(quizForm.questions) && quizForm.questions.length" class="text-xs text-muted-foreground">
                                        {{ quizForm.questions.length }} qualification question{{ quizForm.questions.length === 1 ? '' : 's' }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="font-medium">{{ bonusPageForm.headline }}</p>
                                </template>
                            </div>
                        </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- WEBINAR -->
                <Card v-show="section === 'webinar' && campaign.type === 'webinar'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Webinar Funnels</CardTitle>
                        <CardDescription>
                            Two funnels: <strong>Registration</strong> captures email (ESP sync) → <strong>Pitch & replay</strong> is the webinar room with video, chat, CTA, and replay.
                            Campaign squeeze page is your branded entry; both funnels are also available as standalone URLs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="processing === 'generate-webinar-funnels'" class="mb-4 flex items-center gap-3 rounded-xl border border-teal-200 bg-teal-50/50 p-4">
                            <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                            <span>Creating registration + pitch/replay funnels…</span>
                        </div>
                        <div v-else-if="!webinarReady" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-amber-800 mb-4">
                            Complete funnel pages first — both webinar funnels are created automatically.
                        </div>
                        <div v-for="f in campaign.funnels" :key="f.id" class="mt-3 space-y-2 rounded-xl border border-border/60 bg-white p-4 text-sm shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-start gap-3">
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-teal-500/15 bg-teal-500/10">
                                        <Icon :icon="f.role === 'pitch' ? 'heroicons:video-camera' : 'heroicons:user-plus'" class="size-4 text-teal-600" />
                                    </div>
                                    <div>
                                        <Badge v-if="f.role_label" variant="outline" class="mb-1 border-teal-200 bg-teal-50 text-[10px] uppercase text-teal-700">{{ f.role_label }}</Badge>
                                        <p class="font-medium">{{ f.name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ f.role === 'pitch' ? 'Video · chat · CTA · replay' : 'Email capture → pitch room' }}
                                        </p>
                                    </div>
                                </div>
                                <Button as-child size="sm" variant="brand-outline">
                                    <Link :href="f.edit_url">
                                        {{ f.role === 'pitch' ? 'Edit pitch room' : 'Edit registration' }}
                                    </Link>
                                </Button>
                            </div>
                            <div class="text-xs space-y-1">
                                <p v-if="f.optin_url"><span class="font-medium">Registration URL:</span> <a :href="f.optin_url" target="_blank" class="text-teal-600 underline break-all">{{ f.optin_url }}</a></p>
                                <p v-if="f.webinar_room_url"><span class="font-medium">Pitch / replay room:</span> <a :href="f.webinar_room_url" target="_blank" class="text-teal-600 underline break-all">{{ f.webinar_room_url }}</a></p>
                            </div>
                        </div>
                        <Button
                            v-if="webinarReady"
                            variant="outline"
                            size="sm"
                            class="mt-4"
                            :disabled="!!processing || isGenerationRunning"
                            @click="post('generate-webinar-funnels')"
                        >
                            Refresh funnels from knowledge
                        </Button>
                    </CardContent>
                </Card>

                <!-- BONUSES -->
                <Card v-show="section === 'bonuses'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Step 5 — Bonuses</CardTitle>
                        <CardDescription>Pick a type → choose from 3 AI ideas → generate one deliverable. Nothing runs until you click.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <!-- Done state -->
                        <template v-if="bonusesReady && !showBonusBuilder">
                            <div class="rounded-lg border border-green-200 bg-green-50/50 p-3 text-sm text-green-700">
                                ✓ Bonus saved for this campaign
                            </div>
                            <div v-if="!bonusHasRichContent" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-amber-800">
                                This bonus was created with an older format. Choose a new type below to generate a proper ebook or mini course.
                            </div>

                            <div v-if="generatedBonuses.length" class="space-y-3">
                                <div>
                                    <p class="text-sm font-medium">Your generated bonus{{ generatedBonuses.length > 1 ? 'es' : '' }}</p>
                                    <p class="text-xs text-muted-foreground mt-0.5">Same clean viewer as your public bonus page — expand to preview.</p>
                                </div>
                                <Collapsible
                                    v-for="b in generatedBonuses"
                                    :key="b.uuid"
                                    :open="expandedBonusPreview === b.uuid"
                                        class="overflow-hidden rounded-xl border border-border/60 bg-white shadow-sm"
                                    @update:open="(open) => { if (open) expandedBonusPreview = b.uuid; else if (expandedBonusPreview === b.uuid) expandedBonusPreview = null; }"
                                >
                                    <CollapsibleTrigger class="flex w-full items-center gap-3 p-4 text-left hover:bg-slate-50/80 transition-colors">
                                        <BonusCoverArt
                                            :title="b.title"
                                            :subtitle="String(b.meta?.subtitle ?? '')"
                                            :bonus-type="b.bonus_type"
                                            :gradient="typeof b.meta?.cover_gradient === 'string' ? b.meta.cover_gradient : undefined"
                                            size="sm"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary">{{ bonusTypeLabel(b.bonus_type) }}</Badge>
                                                <Badge variant="outline">{{ bonusValueLabel(b.bonus_type, b.meta) }}</Badge>
                                            </div>
                                            <p class="mt-1 font-semibold text-sm leading-snug">{{ b.title }}</p>
                                            <p class="text-xs text-muted-foreground">{{ bonusDetailLine(b.bonus_type, b.meta) }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-2">
                                            <Button
                                                v-if="bonusViewerUrl(b.meta)"
                                                as-child
                                                size="sm"
                                                variant="outline"
                                                @click.stop
                                            >
                                                <a :href="bonusViewerUrl(b.meta)!" target="_blank" rel="noopener">
                                                    Open ↗
                                                </a>
                                            </Button>
                                            <Icon
                                                icon="heroicons:chevron-down"
                                                class="size-5 text-muted-foreground transition-transform"
                                                :class="expandedBonusPreview === b.uuid ? 'rotate-180' : ''"
                                            />
                                        </div>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent class="border-t p-4 pt-2">
                                        <BonusViewerShell
                                            :title="b.title"
                                            :bonus-type="b.bonus_type"
                                            :slides="bonusSlidesFor(b)"
                                            :download-url="bonusViewerUrl(b.meta)"
                                            embedded
                                        />
                                    </CollapsibleContent>
                                </Collapsible>
                            </div>

                            <div class="rounded-xl border border-border/60 bg-teal-50/20 p-4 space-y-3">
                                <div>
                                    <p class="text-sm font-medium">Bonuses on your public page</p>
                                    <p class="text-xs text-muted-foreground">Showing {{ visibleSelectableBonuses.length }} of {{ filteredSelectableBonuses.length }} — tap to include on <span class="font-mono text-[0.65rem]">/p/bonus</span>.</p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <div class="relative flex-1">
                                        <Icon icon="heroicons:magnifying-glass" class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            v-model="librarySearch"
                                            class="pl-9"
                                            placeholder="Search bonus library…"
                                            @focus="libraryShowAll = librarySearch.trim() !== ''"
                                        />
                                    </div>
                                    <Select v-model="libraryDateFilter">
                                        <SelectTrigger class="w-full sm:w-[160px]">
                                            <SelectValue placeholder="Date" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All time</SelectItem>
                                            <SelectItem value="7d">Last 7 days</SelectItem>
                                            <SelectItem value="30d">Last 30 days</SelectItem>
                                            <SelectItem value="90d">Last 90 days</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <p v-if="librarySearching" class="text-xs text-muted-foreground">Searching library…</p>
                                <div class="grid gap-3">
                                    <div
                                        v-for="b in visibleSelectableBonuses"
                                        :key="b.uuid"
                                        role="button"
                                        tabindex="0"
                                        class="group relative flex cursor-pointer gap-4 rounded-xl border-2 bg-white p-4 text-left transition-all outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                        :class="featuredBonusUuids.includes(b.uuid)
                                            ? 'border-teal-500 bg-teal-50/40 shadow-sm'
                                            : 'border-border hover:border-teal-300 hover:shadow-sm'"
                                        @click="toggleFeaturedBonus(b.uuid)"
                                        @keydown.enter.prevent="toggleFeaturedBonus(b.uuid)"
                                        @keydown.space.prevent="toggleFeaturedBonus(b.uuid)"
                                    >
                                        <div
                                            v-if="featuredBonusUuids.includes(b.uuid)"
                                            class="absolute right-3 top-3 flex size-6 items-center justify-center rounded-full bg-teal-600 text-white shadow"
                                        >
                                            <Icon icon="heroicons:check" class="size-4" />
                                        </div>
                                        <BonusCoverArt
                                            :title="b.title"
                                            :subtitle="bonusDetailLine(b.bonus_type, b.meta)"
                                            :bonus-type="b.bonus_type"
                                            :gradient="typeof b.meta?.cover_gradient === 'string' ? b.meta.cover_gradient : undefined"
                                            size="md"
                                        />
                                        <div class="min-w-0 flex-1 pr-8">
                                            <p class="font-semibold text-sm leading-snug">{{ b.title }}</p>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                                <Badge variant="secondary" class="text-[10px]">{{ bonusTypeLabel(b.bonus_type) }}</Badge>
                                                <span class="text-xs text-muted-foreground">{{ bonusValueLabel(b.bonus_type, b.meta) }}</span>
                                                <span v-if="b.created_at" class="text-xs text-muted-foreground">· {{ formatBonusDate(b.created_at) }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-muted-foreground">
                                                {{ bonusDetailLine(b.bonus_type, b.meta) }}
                                                <span v-if="b.campaign_name"> · from {{ b.campaign_name }}</span>
                                            </p>
                                            <Button
                                                v-if="bonusViewerUrl(b.meta)"
                                                as-child
                                                variant="link"
                                                size="sm"
                                                class="mt-2 h-auto p-0 text-teal-600"
                                                @click.stop
                                            >
                                                <a :href="bonusViewerUrl(b.meta)!" target="_blank" rel="noopener">
                                                    {{ bonusOpenLabel(b.bonus_type) }} ↗
                                                </a>
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                                <Button
                                    v-if="hiddenLibraryCount > 0"
                                    variant="outline"
                                    size="sm"
                                    class="w-full"
                                    @click="libraryShowAll = true"
                                >
                                    Show {{ hiddenLibraryCount }} more bonus{{ hiddenLibraryCount === 1 ? '' : 'es' }}
                                </Button>
                                <p v-if="!visibleSelectableBonuses.length" class="text-xs text-muted-foreground">
                                    No bonuses found. Generate one above or adjust search / date filter.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <Button size="sm" variant="brand" @click="saveFeaturedBonuses">Save bonus page</Button>
                                    <Button v-if="campaign.public_pages?.bonus" as-child size="sm" variant="outline">
                                        <a :href="campaign.public_pages.bonus" target="_blank">Preview bonus page</a>
                                    </Button>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" @click="resetBonusFlow">Create a different bonus</Button>
                        </template>

                        <!-- Builder flow -->
                        <template v-else>
                            <div v-if="campaign.bonuses.length > 0 && !bonusHasRichContent" class="rounded-lg border border-amber-200 bg-amber-50/50 p-3 text-sm text-amber-800">
                                Your saved bonus uses an older text format. Pick a type below to generate a proper ebook or mini course — nothing runs until you click Generate.
                            </div>
                            <div v-if="processing === 'bonuses/suggest'" class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4">
                                <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                                <p class="font-medium">Generating 3 ideas for {{ selectedBonusType === 'mini_course' ? 'mini course' : 'ebook' }}…</p>
                            </div>
                            <div v-else-if="processing === 'bonuses/generate' || (isGenerationRunning && ['bonuses'].includes(String(liveGeneration?.step)))" class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4">
                                <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                                <p class="font-medium">Building your {{ selectedBonusType === 'mini_course' ? 'mini course' : 'ebook' }}…</p>
                            </div>

                            <!-- Step 1: Type cards -->
                            <div class="space-y-2">
                                <p class="text-sm font-medium">1. Choose bonus type</p>
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <button
                                        v-for="t in bonusTypeOptions"
                                        :key="t.id"
                                        type="button"
                                        class="rounded-xl border-2 p-5 text-left transition-all"
                                        :class="[
                                            t.disabled ? 'cursor-not-allowed opacity-45 border-dashed' : 'hover:border-teal-400 hover:shadow-sm cursor-pointer',
                                            bonusTypeChosen && selectedBonusType === t.id && !t.disabled ? 'border-teal-500 bg-teal-50/50 shadow-sm' : 'border-border',
                                        ]"
                                        :disabled="t.disabled || isGenerationRunning || !!processing"
                                        @click="!t.disabled && t.id !== 'mini_app' && selectBonusType(t.id as 'ebook' | 'mini_course')"
                                    >
                                        <Icon :icon="t.icon" class="mb-3 size-8 text-teal-600" />
                                        <p class="font-semibold">{{ t.label }}</p>
                                        <p class="mt-1 text-xs text-muted-foreground">{{ t.description }}</p>
                                        <Badge v-if="t.disabled" variant="secondary" class="mt-2">Soon</Badge>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Pick one of 3 ideas -->
                            <div v-if="bonusIdeasForType && (campaign.bonus_suggestions?.length ?? 0) > 0" class="space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium">2. Pick one of 3 concepts</p>
                                        <p class="text-xs text-muted-foreground mt-0.5">Ideas from your knowledge base — tied to your imported offer.</p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        :disabled="!!processing || isGenerationRunning"
                                        @click="refreshBonusSuggestions"
                                    >
                                        Reload from knowledge
                                    </Button>
                                </div>
                                <p v-if="campaign.offer_data?.product_name" class="rounded-md bg-muted/40 px-3 py-2 text-xs">
                                    <span class="font-medium text-foreground">Offer:</span>
                                    {{ campaign.offer_data.product_name }}
                                </p>
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <button
                                        v-for="b in campaign.bonus_suggestions.slice(0, 3)"
                                        :key="b.id"
                                        type="button"
                                        class="group relative flex flex-col overflow-hidden rounded-xl border-2 text-left transition-all"
                                        :class="selectedBonusId === String(b.id)
                                            ? 'border-teal-500 bg-teal-50/30 shadow-md ring-2 ring-teal-200'
                                            : 'border-border bg-white hover:border-teal-300 hover:shadow-sm'"
                                        :disabled="!!processing || isGenerationRunning"
                                        @click="onBonusPick(String(b.id))"
                                    >
                                        <div
                                            v-if="selectedBonusId === String(b.id)"
                                            class="absolute right-3 top-3 z-10 flex size-7 items-center justify-center rounded-full bg-teal-600 text-white shadow-lg"
                                        >
                                            <Icon icon="heroicons:check" class="size-4" />
                                        </div>
                                        <div class="flex justify-center bg-linear-to-b from-teal-50/30 to-white px-4 pb-2 pt-5">
                                            <BonusCoverArt
                                                :title="b.title"
                                                :subtitle="String(b.promise || b.why_it_helps || '')"
                                                :bonus-type="selectedBonusType"
                                                size="lg"
                                            />
                                        </div>
                                        <div class="flex flex-1 flex-col gap-2 p-4 pt-2">
                                            <p v-if="b.offer_anchor" class="text-[10px] font-semibold uppercase tracking-wide text-teal-700">
                                                {{ b.offer_anchor }}
                                            </p>
                                            <p class="font-semibold text-sm leading-snug">{{ b.title }}</p>
                                            <p class="text-xs leading-relaxed text-muted-foreground line-clamp-3">
                                                {{ b.promise || b.why_it_helps }}
                                            </p>
                                            <div class="mt-auto flex flex-wrap gap-1.5 pt-2">
                                                <Badge variant="secondary" class="text-[10px]">{{ bonusTypeLabel(selectedBonusType) }}</Badge>
                                                <Badge variant="outline" class="text-[10px]">AI concept</Badge>
                                            </div>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 3: Generate -->
                            <div v-if="selectedBonusId && bonusIdeasForType" class="space-y-2">
                                <p class="text-sm font-medium">3. Generate your bonus</p>
                                <Button
                                    variant="brand"
                                    :disabled="!selectedBonusId || !!processing || isGenerationRunning"
                                    @click="generateSelectedBonus"
                                >
                                    Generate {{ selectedBonusType === 'mini_course' ? 'mini course' : 'ebook' }}
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>

                <!-- EMAILS -->
                <Card v-show="section === 'emails'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Step 6 — Email Swipes</CardTitle>
                        <CardDescription>Email swipes from your knowledge base — review and copy below.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="processing === 'generate-emails' && !emailsReady" class="flex items-center gap-3 rounded-lg border border-teal-200 bg-teal-50/50 p-4">
                            <Icon icon="heroicons:arrow-path" class="size-5 animate-spin text-teal-600" />
                            <p class="font-medium">Generating email swipes…</p>
                        </div>
                        <div v-else-if="emailsReady" class="rounded-lg border border-green-200 bg-green-50/50 p-3 text-sm text-green-700">
                            ✓ {{ campaign.emails.length }} emails saved
                        </div>
                        <Button
                            v-if="!emailsReady && funnelPagesReady"
                            variant="brand"
                            size="sm"
                            :disabled="!!processing || isGenerationRunning"
                            @click="post('generate-emails', { count: emailCount })"
                        >
                            Generate emails
                        </Button>
                        <Button
                            v-if="emailsReady"
                            variant="outline"
                            size="sm"
                            :disabled="!!processing || isGenerationRunning"
                            @click="post('generate-emails', { count: emailCount, force: true })"
                        >
                            Regenerate all emails
                        </Button>
                        <div v-for="e in campaign.emails" :key="e.id" class="rounded-xl border border-border/60 bg-white p-4 shadow-sm">
                            <div class="flex items-start gap-2">
                                <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-teal-500/10">
                                    <Icon icon="heroicons:envelope" class="size-4 text-teal-600" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-sm">{{ e.subject }}</p>
                                    <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded-lg border border-border/60 bg-muted/20 p-3 text-xs text-muted-foreground">{{ e.body }}</pre>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- TRAFFIC HUB -->
                <Card v-show="section === 'traffic'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Traffic Hub</CardTitle>
                        <CardDescription>
                            Free mention discovery, social posts, promo calendar & paid ads — each campaign gets its own traffic workspace (like the webinar room).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CampaignTrafficStepPanel
                            v-if="campaign?.traffic_hub_url"
                            :traffic-hub-url="campaign.traffic_hub_url"
                            :paid-ads-enabled="paidAdsEnabled"
                        />
                        <p v-else class="text-sm text-muted-foreground">Traffic hub will be available once funnel pages are ready.</p>
                    </CardContent>
                </Card>

                <!-- PUBLISH -->
                <Card v-show="section === 'publish'" class="border border-border/60 bg-white shadow-sm">
                    <CardHeader>
                        <CardTitle>Publish</CardTitle>
                        <CardDescription>All assets generated — review links and publish your campaign.</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm font-medium">Public pages</p>
                                <p class="text-xs text-muted-foreground">Live URLs for this campaign</p>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <a
                                    v-for="(url, key) in campaign.public_pages"
                                    :key="key"
                                    :href="url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group flex items-start gap-3 rounded-xl border bg-white p-3 transition-colors hover:border-teal-200 hover:bg-teal-50/30"
                                >
                                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                                        <Icon icon="heroicons:globe-alt" class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium capitalize">{{ publicPageLabels[key] ?? key.replace('_', ' ') }}</p>
                                        <p class="truncate text-xs text-teal-600 group-hover:underline">{{ url }}</p>
                                    </div>
                                    <Icon icon="heroicons:arrow-top-right-on-square" class="ml-auto size-4 shrink-0 text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
                                </a>
                            </div>
                        </div>

                        <div v-if="campaign.tracked_links.length" class="space-y-3">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium">Tracked link stats</p>
                                    <p class="text-xs text-muted-foreground">Cloaked affiliate & CTA links for this campaign</p>
                                </div>
                                <div class="rounded-lg border bg-muted/30 px-3 py-2 text-center">
                                    <p class="text-2xl font-bold leading-none tabular-nums">{{ trackedLinkTotalClicks }}</p>
                                    <p class="text-[0.65rem] uppercase tracking-wide text-muted-foreground mt-0.5">Total clicks</p>
                                </div>
                            </div>
                            <div class="grid gap-2">
                                <div
                                    v-for="l in campaign.tracked_links"
                                    :key="l.id"
                                    class="flex flex-col gap-2 rounded-xl border bg-white p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-sm">{{ l.label || 'Tracked link' }}</p>
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                                                :class="l.click_count > 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-muted text-muted-foreground'"
                                            >
                                                {{ l.click_count }} {{ l.click_count === 1 ? 'click' : 'clicks' }}
                                            </span>
                                        </div>
                                        <a :href="l.public_url" target="_blank" rel="noopener noreferrer" class="block truncate text-xs text-teal-600 hover:underline">{{ l.public_url }}</a>
                                        <p class="truncate text-xs text-muted-foreground">→ {{ l.destination_url }}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="shrink-0 gap-1.5"
                                        @click="copyTrackedLink(l.public_url, l.id)"
                                    >
                                        <Icon
                                            :icon="copiedTrackedLinkId === l.id ? 'heroicons:check' : 'heroicons:clipboard-document'"
                                            class="size-3.5"
                                        />
                                        {{ copiedTrackedLinkId === l.id ? 'Copied' : 'Copy link' }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-xl border border-border/60 bg-teal-50/20 p-4">
                            <div>
                                <p class="text-sm font-medium">Upload email swipes to ESP</p>
                                <p class="text-xs text-muted-foreground">Optional — pushes generated swipes as drafts when you publish. Connect accounts at <Link href="/integrations" class="text-teal-600 underline">Integrations</Link>.</p>
                            </div>
                            <div v-if="integrationAccounts.length" class="grid gap-3 sm:grid-cols-2">
                                <div class="space-y-1.5">
                                    <Label class="text-xs">ESP account</Label>
                                    <select v-model="publishEspAccountId" class="h-10 w-full rounded-xl border border-border/60 bg-white px-3 text-sm shadow-sm">
                                        <option value="">Don't upload on publish</option>
                                        <option v-for="acc in integrationAccounts" :key="acc.id" :value="acc.id">
                                            {{ acc.name }} ({{ acc.provider }})
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label class="text-xs">Tag / folder label (optional)</Label>
                                    <Input v-model="publishEspTag" placeholder="e.g. launch-week" />
                                </div>
                            </div>
                            <p v-else class="text-xs text-muted-foreground">No ESP connected — publish still works; swipes stay copy-only.</p>
                            <p v-if="campaign.esp_upload" class="text-xs" :class="campaign.esp_upload.ok ? 'text-emerald-700' : 'text-amber-700'">
                                Last upload: {{ campaign.esp_upload.message }}
                                <span v-if="campaign.esp_upload.uploaded"> ({{ campaign.esp_upload.uploaded }} emails)</span>
                            </p>
                        </div>

                        <Button variant="brand" :disabled="!!processing" @click="publishCampaign">Publish campaign</Button>
                    </CardContent>
                </Card>
            </template>
        </div>
    </div>
</template>

<style scoped>
.lead-magnet-preview :deep(.lm-intro) { font-size: 1rem; line-height: 1.7; margin-bottom: 1rem; }
.lead-magnet-preview :deep(.lm-card) {
    background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #0d9488;
    border-radius: 12px; padding: 1rem 1.25rem; margin: 1rem 0;
    box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
}
.lead-magnet-preview :deep(.lm-card-accent) { border-left-color: #f59e0b; background: linear-gradient(90deg, #fffbeb 0%, #fff 40%); }
.lead-magnet-preview :deep(.lm-checklist) { list-style: none; padding: 0; margin: 0.75rem 0; }
.lead-magnet-preview :deep(.lm-checklist li) { padding: 0.5rem 0 0.5rem 1.75rem; position: relative; border-bottom: 1px solid #f1f5f9; }
.lead-magnet-preview :deep(.lm-checklist li::before) { content: "✓"; position: absolute; left: 0; color: #0ea5e9; font-weight: 700; }
.lead-magnet-preview :deep(.lm-tip) { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 0.75rem 1rem; margin: 1rem 0; }
.lead-magnet-preview :deep(.lm-steps) { list-style: none; padding: 0; counter-reset: step; }
.lead-magnet-preview :deep(.lm-steps li) { counter-increment: step; padding: 0.75rem 0.75rem 0.75rem 2.5rem; position: relative; margin-bottom: 0.5rem; background: #f8fafc; border-radius: 8px; }
.lead-magnet-preview :deep(.lm-steps li::before) {
    content: counter(step); position: absolute; left: 0.75rem; top: 0.75rem;
    width: 1.5rem; height: 1.5rem; background: #0d9488; color: #fff; border-radius: 50%;
    text-align: center; line-height: 1.5rem; font-size: 0.75rem; font-weight: 700;
}
.lead-magnet-preview :deep(.lm-stat-grid) { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin: 1rem 0; }
.lead-magnet-preview :deep(.lm-stat) { background: #f8fafc; border-radius: 8px; padding: 0.75rem; text-align: center; border-top: 3px solid #f59e0b; }
.lead-magnet-preview :deep(.lm-stat strong) { display: block; font-size: 1.5rem; color: #0d9488; }
.lead-magnet-preview :deep(.lm-exercise) { background: #f0fdf4; border: 2px dashed #86efac; border-radius: 10px; padding: 1rem; margin: 1rem 0; }
.lead-magnet-preview :deep(.lm-table) { width: 100%; border-collapse: collapse; font-size: 0.875rem; margin: 1rem 0; }
.lead-magnet-preview :deep(.lm-table th) { background: #0d9488; color: #fff; padding: 0.5rem; text-align: left; }
.lead-magnet-preview :deep(.lm-table td) { padding: 0.5rem; border-bottom: 1px solid #e2e8f0; }
</style>
