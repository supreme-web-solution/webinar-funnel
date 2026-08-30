import type { ComputedRef, Ref } from 'vue';
import { computed } from 'vue';

export type WizardSectionId =
    | 'offer'
    | 'knowledge'
    | 'lead_magnet'
    | 'pages'
    | 'webinar'
    | 'bonuses'
    | 'emails'
    | 'traffic'
    | 'publish';

type GenerationStateEntry = { generated?: boolean; at?: string; selected_id?: string; bonus_type?: string };

type CampaignLike = {
    id: number;
    type: 'sales' | 'webinar';
    quick_start?: boolean;
    affiliate_link: string | null;
    offer_data: Record<string, unknown> | null;
    analysis: Record<string, unknown> | null;
    knowledge: { status?: string } | null;
    lead_magnet?: Record<string, unknown> | null;
    generation_state?: Record<string, GenerationStateEntry>;
    pages: Array<{ page_type: string; content: Record<string, unknown> }>;
    bonuses: Array<{ meta?: { pages?: unknown[]; slides?: unknown[] } | null }>;
    emails: Array<unknown>;
    funnels: Array<unknown>;
    traffic_hub_url?: string | null;
    status: string;
};

function isStepGenerated(campaign: CampaignLike | null, step: string): boolean {
    return campaign?.generation_state?.[step]?.generated === true;
}

export type WizardStep = {
    id: WizardSectionId;
    label: string;
    icon: string;
    complete: boolean;
    unlocked: boolean;
    blockedReason: string | null;
};

function hasPage(campaign: CampaignLike, type: string): boolean {
    const page = campaign.pages.find((p) => p.page_type === type);
    const c = page?.content ?? {};

    return Boolean(c.headline || c.title || c.subheadline);
}

export function useCampaignWizardFlow(campaign: ComputedRef<CampaignLike | null>) {
    const offerComplete = computed(() => {
        const c = campaign.value;
        if (!c) return false;

        return Boolean(c.affiliate_link && (c.offer_data?.product_name || c.analysis));
    });

    const knowledgeReady = computed(() => {
        const c = campaign.value;
        if (!c) return false;

        return isStepGenerated(c, 'knowledge') || c.knowledge?.status === 'ready';
    });

    const leadMagnetHasContent = computed(() => {
        const c = campaign.value;
        if (c && isStepGenerated(c, 'lead_magnet')) return true;

        const lm = c?.lead_magnet;
        if (!lm) return false;
        if (lm.generated === true) return true;
        if (String(lm.status ?? '') === 'ready') return true;
        const pages = lm.pages;
        const hasPages = Array.isArray(pages) && pages.length > 0;

        return hasPages && Boolean(lm.download_path ?? lm.download_url);
    });

    const leadMagnetSkipped = computed(() => {
        const c = campaign.value;
        if (!c || c.type !== 'webinar') return false;

        return isStepGenerated(c, 'lead_magnet_skip');
    });

    const leadMagnetReady = computed(() => leadMagnetHasContent.value || leadMagnetSkipped.value);

    const leadMagnetGenerating = computed(() => {
        if (leadMagnetHasContent.value) return false;
        if (isStepGenerated(campaign.value, 'lead_magnet')) return false;

        return String(campaign.value?.lead_magnet?.status ?? '') === 'generating';
    });

    const hasLeadMagnetSuggestions = computed(() => {
        if (isStepGenerated(campaign.value, 'lead_magnet_suggest')) return true;
        const s = campaign.value?.lead_magnet?.suggestions;

        return Array.isArray(s) && s.length > 0;
    });

    const funnelPagesReady = computed(() => {
        const c = campaign.value;
        if (!c) return false;
        if (isStepGenerated(c, 'pages')) return true;

        if (c.type === 'webinar') {
            return hasPage(c, 'squeeze');
        }

        return hasPage(c, 'squeeze') && hasPage(c, 'thankyou');
    });

    const bonusesReady = computed(() => {
        if (isStepGenerated(campaign.value, 'bonuses')) return true;
        const bonus = campaign.value?.bonuses[0];
        if (!bonus) return false;
        const meta = bonus.meta ?? {};

        return (Array.isArray(meta.pages) && meta.pages.length > 0)
            || (Array.isArray(meta.slides) && meta.slides.length > 0);
    });

    const emailsReady = computed(() => {
        if (isStepGenerated(campaign.value, 'emails')) return true;

        return (campaign.value?.emails.length ?? 0) > 0;
    });

    const webinarReady = computed(() => {
        const c = campaign.value;
        if (!c || c.type !== 'webinar') return true;
        if (isStepGenerated(c, 'webinar')) return true;

        return c.funnels.length >= 2;
    });

    const stepOrder = computed((): WizardSectionId[] => {
        const c = campaign.value;
        if (!c) return ['offer'];

        const base: WizardSectionId[] = ['offer', 'knowledge', 'lead_magnet', 'pages'];
        if (c.type === 'webinar') base.push('webinar');
        base.push('bonuses', 'emails', 'traffic', 'publish');

        return base;
    });

    function isStepComplete(id: WizardSectionId): boolean {
        const c = campaign.value;
        if (!c) return id === 'offer' ? false : false;

        switch (id) {
            case 'offer':
                return offerComplete.value;
            case 'knowledge':
                return knowledgeReady.value;
            case 'lead_magnet':
                return leadMagnetReady.value;
            case 'pages':
                return funnelPagesReady.value;
            case 'webinar':
                return webinarReady.value;
            case 'bonuses':
                return bonusesReady.value;
            case 'emails':
                return emailsReady.value;
            case 'traffic':
                return Boolean(c.traffic_hub_url);
            case 'publish':
                return c.status === 'published';
            default:
                return false;
        }
    }

    function isStepUnlocked(id: WizardSectionId): boolean {
        const c = campaign.value;
        if (!c) return id === 'offer';

        switch (id) {
            case 'offer':
                return true;
            case 'knowledge':
                return offerComplete.value || knowledgeReady.value;
            case 'lead_magnet':
                return knowledgeReady.value;
            case 'pages':
                return c.type === 'webinar' ? knowledgeReady.value : leadMagnetReady.value;
            case 'webinar':
                return funnelPagesReady.value && c.type === 'webinar';
            case 'bonuses':
            case 'emails':
            case 'traffic':
                return c.type === 'webinar' ? webinarReady.value : funnelPagesReady.value;
            case 'publish':
                return (c.type === 'webinar' ? webinarReady.value : funnelPagesReady.value) && emailsReady.value;
            default:
                return false;
        }
    }

    function blockedReason(id: WizardSectionId): string | null {
        if (isStepUnlocked(id)) return null;

        const c = campaign.value;

        switch (id) {
            case 'knowledge':
                return knowledgeReady.value
                    ? null
                    : 'Add your affiliate link and save the offer first.';
            case 'lead_magnet':
                return 'Build the knowledge base first.';
            case 'pages':
                return c?.type === 'webinar'
                    ? 'Build the knowledge base first.'
                    : 'Generate the lead magnet first (thank-you page needs the download).';
            case 'webinar':
                return 'Generate funnel pages first.';
            case 'bonuses':
            case 'emails':
            case 'traffic':
                return c?.type === 'webinar'
                    ? 'Create both webinar funnels first (registration + pitch/replay).'
                    : 'Generate funnel pages first.';
            case 'publish':
                return c?.type === 'webinar'
                    ? 'Finish webinar funnels and email swipes first.'
                    : 'Generate funnel pages and email swipes first.';
            default:
                return 'Complete the previous step first.';
        }
    }

    const steps = computed((): WizardStep[] => {
        return stepOrder.value.map((id, i) => ({
            id,
            label: stepLabels[id] ?? id,
            icon: stepIcons[id] ?? 'heroicons: circle',
            complete: isStepComplete(id),
            unlocked: isStepUnlocked(id),
            blockedReason: blockedReason(id),
            number: i + 1,
        })) as WizardStep[];
    });

    function firstIncompleteStep(): WizardSectionId {
        const incomplete = stepOrder.value.find((id) => !isStepComplete(id));

        return incomplete ?? 'publish';
    }

    function nextStepAfter(current: WizardSectionId): WizardSectionId | null {
        const order = stepOrder.value;
        const idx = order.indexOf(current);
        if (idx === -1 || idx >= order.length - 1) return null;

        return order[idx + 1];
    }

    return {
        steps,
        stepOrder,
        offerComplete,
        knowledgeReady,
        leadMagnetReady,
        leadMagnetSkipped,
        leadMagnetHasContent,
        leadMagnetGenerating,
        hasLeadMagnetSuggestions,
        funnelPagesReady,
        bonusesReady,
        emailsReady,
        webinarReady,
        isStepComplete,
        isStepUnlocked,
        blockedReason,
        firstIncompleteStep,
        nextStepAfter,
    };
}

const stepLabels: Record<WizardSectionId, string> = {
    offer: 'Import Offer',
    knowledge: 'Knowledge Base',
    lead_magnet: 'Lead Magnet',
    pages: 'Funnel Pages',
    webinar: 'Webinar Funnels',
    bonuses: 'Bonuses',
    emails: 'Email Swipes',
    traffic: 'Traffic Hub',
    publish: 'Publish',
};

const stepIcons: Record<WizardSectionId, string> = {
    offer: 'heroicons:link',
    knowledge: 'heroicons:book-open',
    lead_magnet: 'heroicons:gift',
    pages: 'heroicons:document-text',
    webinar: 'heroicons:video-camera',
    bonuses: 'heroicons:sparkles',
    emails: 'heroicons:envelope',
    traffic: 'heroicons:signal',
    publish: 'heroicons:rocket-launch',
};

export function useWizardSection(
    section: Ref<WizardSectionId>,
    flow: ReturnType<typeof useCampaignWizardFlow>,
    toast: { error: (msg: string) => void },
) {
    function goToSection(id: WizardSectionId) {
        if (!flow.isStepUnlocked(id)) {
            toast.error(flow.blockedReason(id) ?? 'Complete the previous step first.');

            return;
        }
        section.value = id;
    }

    return { goToSection };
}
