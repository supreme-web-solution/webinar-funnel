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
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

// ─── Types ──────────────────────────────────────────────────────────────────
type AdPerformance = {
    spend?: number; impressions?: number; clicks?: number;
    ctr?: number; cpc?: number; cpm?: number; conversions?: number; roas?: number;
};

type AdCreative = {
    id: number;
    headline: string | null;
    primary_text: string | null;
    description: string | null;
    cta_button: string;
    asset_url: string | null;
    asset_type: string | null;
    format: string;
    status: string;
    is_winner: boolean;
    performance: AdPerformance | null;
    zernio_ad_id: string | null;
};

type Campaign = {
    id: number;
    name: string;
    goal: string;
    platforms: string[];
    status: string;
    budget_amount: string;
    budget_type: string;
    budget_currency: string;
    start_date: string | null;
    end_date: string | null;
    product_url: string | null;
    industry: string | null;
    goal_description: string | null;
    targeting: { age_min?: number; age_max?: number; countries?: string[]; interests?: string[] } | null;
    ai_research: { hooks: string[]; angles: string[]; personas: string[]; value_props: string[]; pain_points: string[] } | null;
    performance: AdPerformance | null;
    last_synced_at: string | null;
    last_error: string | null;
    launch_errors: {
        summary?: string;
        primary?: { title: string; message: string; action: string | null };
        items?: Array<{ headline: string; title: string; message: string; action: string | null; raw?: string }>;
    } | null;
    platform_ad_account_ids: Record<string, string> | null;
    zernio_social_account_id: string | null;
    meta_pixel_id: string | null;
    meta_conversion_event: string | null;
    created_at: string;
    creatives: AdCreative[];
};

const props = defineProps<{
    funnel: { id: number; name: string; status: string; default_destination_url?: string | null };
    campaigns: Campaign[];
    adPlatforms: Record<string, { label: string; icon: string }>;
    launchableAdPlatforms?: Record<string, { label: string; icon: string }>;
    unsupportedAdPlatforms?: Record<string, { label: string; icon: string }>;
    adGoals: Record<string, string>;
    ctaButtons: Record<string, string>;
    adsEnabled: boolean;
    routes: { store: string; posts: string };
    savedAdAccountIds?: Record<string, string>;
    adAccountsSettingsUrl?: string;
    minBudgetAmount?: number;
    minBudgetByCurrency?: Record<string, number>;
    budgetCurrencies?: string[];
    defaultBudgetCurrency?: string;
}>();

// ─── Wizard state ────────────────────────────────────────────────────────────
type WizardStep = 1 | 2 | 3 | 4 | 5;
const wizardOpen  = ref(false);
const wizardStep  = ref<WizardStep>(1);
const creatingCampaignId = ref<number | null>(null); // campaign being built in wizard

// Step 1 — Product setup
const wizardName        = ref('');
const wizardProductUrl  = ref('');
const wizardIndustry    = ref('');
const wizardGoal        = ref<string>('traffic');
const wizardGoalDesc    = ref('');
const wizardPlatforms   = ref<string[]>(['facebook', 'instagram']);
const wizardAdAccountIds = ref<Record<string, string>>({});
const wizardBudget      = ref('10');
const wizardBudgetCurrency = ref(props.defaultBudgetCurrency ?? 'USD');
const BUDGET_SYMBOLS: Record<string, string> = {
    USD: '$', NGN: '₦', EUR: '€', GBP: '£', CAD: 'CA$', AUD: 'A$', INR: '₹', ZAR: 'R',
};
function budgetSymbol(code: string): string {
    return BUDGET_SYMBOLS[code] ?? `${code} `;
}
function minBudgetForCurrency(code: string): number {
    return props.minBudgetByCurrency?.[code] ?? props.minBudgetAmount ?? 2;
}
const effectiveMinBudget = computed(() => minBudgetForCurrency(wizardBudgetCurrency.value));
const wizardBudgetInvalid = computed(() => {
    const raw = wizardBudget.value.trim();
    if (raw === '') return false;
    const n = parseFloat(raw);
    return !Number.isFinite(n) || n < effectiveMinBudget.value;
});
const wizardBudgetType  = ref<'daily' | 'lifetime'>('daily');
const wizardStartDate   = ref('');
const wizardEndDate     = ref('');
const wizardTargeting   = ref({ age_min: 25, age_max: 55, countries: ['US'], interests: '' });
const wizardMetaPixelId = ref('');
const wizardMetaConversionEvent = ref('LEAD');

// Step 2 — AI Research
const aiResearch       = ref<Campaign['ai_research'] | null>(null);
const researchLoading  = ref(false);
const selectedHooks    = ref<string[]>([]);

// Step 3 — Creatives
const generatingCreatives = ref(false);
const generateImages      = ref(true);
const creativeFormat      = ref<'square' | 'story' | 'landscape' | 'reel'>('square');
const wizardCreatives     = ref<AdCreative[]>([]);

// Step 4 — Review (done inline)
const launchingCampaignId = ref<number | null>(null);

// Edit existing campaign (draft / failed / ready)
const editOpen = ref(false);
const editingCampaignId = ref<number | null>(null);
const editSaving = ref(false);
const editName = ref('');
const editProductUrl = ref('');
const editIndustry = ref('');
const editGoal = ref('traffic');
const editGoalDesc = ref('');
const editPlatforms = ref<string[]>(['facebook']);
const editAdAccountIds = ref<Record<string, string>>({});
const editBudget = ref('10');
const editBudgetCurrency = ref(props.defaultBudgetCurrency ?? 'USD');
const editBudgetType = ref<'daily' | 'lifetime'>('daily');
const editStartDate = ref('');
const editEndDate = ref('');
const editTargeting = ref({ age_min: 25, age_max: 55, countries: ['US'], interests: '' });
const editMetaPixelId = ref('');
const editMetaConversionEvent = ref('LEAD');
const editEffectiveMinBudget = computed(() => minBudgetForCurrency(editBudgetCurrency.value));
const editBudgetInvalid = computed(() => {
    const raw = editBudget.value.trim();
    if (raw === '') return false;
    const n = parseFloat(raw);
    return !Number.isFinite(n) || n < editEffectiveMinBudget.value;
});

const WIZARD_STEP_LABELS: Record<WizardStep, string> = {
    1: 'Campaign Setup',
    2: 'AI Research',
    3: 'Generate Creatives',
    4: 'Review & Launch',
    5: 'Done',
};

function openWizard(): void {
    wizardOpen.value = true;
    wizardStep.value = 1;
    creatingCampaignId.value = null;
    wizardName.value = '';
    wizardProductUrl.value = props.funnel.default_destination_url ?? '';
    wizardIndustry.value = '';
    wizardGoal.value = 'traffic';
    wizardGoalDesc.value = '';
    wizardPlatforms.value = ['facebook', 'instagram'];
    wizardAdAccountIds.value = { ...(props.savedAdAccountIds ?? {}) };
    wizardBudget.value = '10';
    wizardBudgetCurrency.value = props.defaultBudgetCurrency ?? 'USD';
    wizardBudgetType.value = 'daily';
    wizardStartDate.value = '';
    wizardEndDate.value = '';
    wizardTargeting.value = { age_min: 25, age_max: 55, countries: ['US'], interests: '' };
    wizardMetaPixelId.value = '';
    wizardMetaConversionEvent.value = 'LEAD';
    aiResearch.value = null;
    selectedHooks.value = [];
    wizardCreatives.value = [];
}

function canEditCampaign(campaign: Campaign): boolean {
    return ['draft', 'failed', 'ready', 'generating'].includes(campaign.status);
}

function formatCampaignBudget(campaign: Campaign): string {
    const code = campaign.budget_currency || props.defaultBudgetCurrency || 'USD';
    const amount = parseFloat(campaign.budget_amount);
    const symbol = budgetSymbol(code);
    if (!Number.isFinite(amount)) return `${symbol}${campaign.budget_amount}`;
    return `${symbol}${amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
}

function openEditCampaign(campaign: Campaign): void {
    editingCampaignId.value = campaign.id;
    editName.value = campaign.name;
    editProductUrl.value = campaign.product_url ?? props.funnel.default_destination_url ?? '';
    editIndustry.value = campaign.industry ?? '';
    editGoal.value = campaign.goal;
    editGoalDesc.value = campaign.goal_description ?? '';
    editPlatforms.value = [...(campaign.platforms ?? ['facebook'])];
    editAdAccountIds.value = { ...(campaign.platform_ad_account_ids ?? props.savedAdAccountIds ?? {}) };
    editBudget.value = String(campaign.budget_amount);
    editBudgetCurrency.value = campaign.budget_currency || props.defaultBudgetCurrency || 'USD';
    editBudgetType.value = (campaign.budget_type as 'daily' | 'lifetime') || 'daily';
    editStartDate.value = campaign.start_date ?? '';
    editEndDate.value = campaign.end_date ?? '';
    editTargeting.value = {
        age_min: campaign.targeting?.age_min ?? 25,
        age_max: campaign.targeting?.age_max ?? 55,
        countries: campaign.targeting?.countries ?? ['US'],
        interests: (campaign.targeting?.interests ?? []).join(', '),
    };
    editMetaPixelId.value = campaign.meta_pixel_id ?? '';
    editMetaConversionEvent.value = campaign.meta_conversion_event ?? 'LEAD';
    editOpen.value = true;
}

function toggleEditPlatform(platform: string): void {
    const idx = editPlatforms.value.indexOf(platform);
    if (idx === -1) {
        editPlatforms.value.push(platform);
        if (!editAdAccountIds.value[platform] && props.savedAdAccountIds?.[platform]) {
            editAdAccountIds.value[platform] = props.savedAdAccountIds[platform];
        }
    } else if (editPlatforms.value.length > 1) {
        editPlatforms.value.splice(idx, 1);
    }
}

function saveEditCampaign(): void {
    if (!editingCampaignId.value) return;
    if (!editName.value.trim()) { toast.error('Campaign name is required.'); return; }
    const budget = parseFloat(editBudget.value);
    if (!Number.isFinite(budget) || budget < editEffectiveMinBudget.value) {
        toast.error(`Budget must be at least ${budgetSymbol(editBudgetCurrency.value)}${editEffectiveMinBudget.value} ${editBudgetCurrency.value} per day.`);
        return;
    }
    const missingAccountIds = editPlatforms.value.filter((p) => !String(editAdAccountIds.value[p] ?? '').trim());
    if (missingAccountIds.length > 0) {
        toast.error(`Provide ad account IDs for: ${missingAccountIds.join(', ')}`);
        return;
    }

    editSaving.value = true;
    router.patch(`/funnels/${props.funnel.id}/ads/${editingCampaignId.value}`, {
        name: editName.value.trim(),
        goal: editGoal.value,
        platforms: editPlatforms.value,
        platform_ad_account_ids: Object.fromEntries(
            editPlatforms.value.map((p) => [p, String(editAdAccountIds.value[p] ?? '').trim()])
        ),
        budget_amount: budget,
        budget_currency: editBudgetCurrency.value,
        budget_type: editBudgetType.value,
        start_date: editStartDate.value || null,
        end_date: editEndDate.value || null,
        product_url: editProductUrl.value || null,
        industry: editIndustry.value || null,
        goal_description: editGoalDesc.value || null,
        targeting: {
            age_min: editTargeting.value.age_min,
            age_max: editTargeting.value.age_max,
            countries: editTargeting.value.countries,
            interests: editTargeting.value.interests ? editTargeting.value.interests.split(',').map((s) => s.trim()).filter(Boolean) : [],
        },
        meta_pixel_id: editMetaPixelId.value.trim() || null,
        meta_conversion_event: editMetaConversionEvent.value.trim() || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editOpen.value = false;
            toast.success('Campaign updated.');
        },
        onError: (e) => toast.error(Object.values(e)[0] as string || 'Update failed.'),
        onFinish: () => { editSaving.value = false; },
    });
}

function duplicateCampaign(campaign: Campaign): void {
    const copyName = `${campaign.name} (copy)`;
    router.post(`/funnels/${props.funnel.id}/ads/${campaign.id}/duplicate`, {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            toast.success('Campaign duplicated.');
            const list = ((page.props as { campaigns?: Campaign[] }).campaigns ?? props.campaigns) as Campaign[];
            const copy = list.find((c) => c.name === copyName) ?? list[0];
            if (copy) openEditCampaign(copy);
        },
        onError: () => toast.error('Duplicate failed.'),
    });
}

function openWizardForCampaign(campaign: Campaign, startStep: WizardStep = 2): void {
    wizardOpen.value = true;
    wizardStep.value = startStep;
    creatingCampaignId.value = campaign.id;
    wizardName.value = campaign.name;
    wizardProductUrl.value = campaign.product_url ?? props.funnel.default_destination_url ?? '';
    wizardIndustry.value = campaign.industry ?? '';
    wizardGoal.value = campaign.goal;
    wizardGoalDesc.value = campaign.goal_description ?? '';
    wizardPlatforms.value = [...(campaign.platforms ?? ['facebook'])];
    wizardAdAccountIds.value = { ...(campaign.platform_ad_account_ids ?? {}) };
    wizardBudget.value = String(campaign.budget_amount);
    wizardBudgetCurrency.value = campaign.budget_currency || props.defaultBudgetCurrency || 'USD';
    wizardBudgetType.value = (campaign.budget_type as 'daily' | 'lifetime') || 'daily';
    wizardStartDate.value = campaign.start_date ?? '';
    wizardEndDate.value = campaign.end_date ?? '';
    wizardTargeting.value = {
        age_min: campaign.targeting?.age_min ?? 25,
        age_max: campaign.targeting?.age_max ?? 55,
        countries: campaign.targeting?.countries ?? ['US'],
        interests: (campaign.targeting?.interests ?? []).join(', '),
    };
    aiResearch.value = campaign.ai_research;
    selectedHooks.value = (campaign.ai_research?.hooks ?? []).slice(0, 3);
    wizardCreatives.value = campaign.creatives as AdCreative[];
}

function isUnsupportedPlatform(platform: string): boolean {
    return Boolean(props.unsupportedAdPlatforms?.[platform]);
}

function platformLaunchHint(platform: string): string | null {
    if (isUnsupportedPlatform(platform)) {
        return 'Not available via Zernio standalone ads — use boost separately';
    }
    if (platform === 'tiktok') {
        return 'Requires video creatives (image ads not supported yet)';
    }
    if (platform === 'x') {
        return 'Uses primary text as the post; headline is ignored';
    }
    if (platform === 'google') {
        return 'Display ads need generated images';
    }
    return null;
}

function toggleWizardPlatform(platform: string): void {
    const idx = wizardPlatforms.value.indexOf(platform);
    if (idx === -1) {
        wizardPlatforms.value.push(platform);
        if (!wizardAdAccountIds.value[platform] && props.savedAdAccountIds?.[platform]) {
            wizardAdAccountIds.value[platform] = props.savedAdAccountIds[platform];
        }
    } else if (wizardPlatforms.value.length > 1) {
        wizardPlatforms.value.splice(idx, 1);
    }
}

function toggleHook(hook: string): void {
    const idx = selectedHooks.value.indexOf(hook);
    if (idx === -1 && selectedHooks.value.length < 5) selectedHooks.value.push(hook);
    else if (idx !== -1) selectedHooks.value.splice(idx, 1);
}

function adAccountHint(platform: string): string {
    return ({
        facebook: 'Meta ad account id, e.g. act_123456789',
        instagram: 'Meta ad account id, e.g. act_123456789',
        tiktok: 'TikTok advertiser id',
        google: 'Google Ads customer id',
        linkedin: 'LinkedIn ad account id',
        x: 'X ad account id',
        pinterest: 'Pinterest ad account id',
        reddit: 'Reddit ads account id',
        youtube: 'Google Ads customer id',
    } as Record<string, string>)[platform] ?? 'Platform ad account id';
}

function updateManualHooks(event: Event): void {
    const target = event.target as HTMLTextAreaElement | null;
    const value = target?.value ?? '';
    selectedHooks.value = value.split('\n').map((line) => line.trim()).filter(Boolean);
}

// ─── Wizard navigation ───────────────────────────────────────────────────────
async function nextStep(): Promise<void> {
    if (wizardStep.value === 1) {
        await saveCampaignAndAdvance();
    } else if (wizardStep.value === 2) {
        if (selectedHooks.value.length === 0) { toast.error('Select at least one hook.'); return; }
        wizardStep.value = 3;
    } else if (wizardStep.value === 3) {
        await generateCreativesStep();
    } else if (wizardStep.value === 4) {
        wizardOpen.value = false;
    }
}

function prevStep(): void {
    if (wizardStep.value > 1) wizardStep.value = (wizardStep.value - 1) as WizardStep;
}

async function saveCampaignAndAdvance(): Promise<void> {
    if (!wizardName.value.trim()) { toast.error('Campaign name is required.'); return; }
    if (wizardPlatforms.value.length === 0) { toast.error('Select at least one platform.'); return; }
    const missingAccountIds = wizardPlatforms.value.filter((platform) => !String(wizardAdAccountIds.value[platform] ?? '').trim());
    if (missingAccountIds.length > 0) {
        toast.error(`Provide ad account IDs for: ${missingAccountIds.join(', ')}`);
        return;
    }
    const budget = parseFloat(wizardBudget.value);
    if (!Number.isFinite(budget) || budget < effectiveMinBudget.value) {
        toast.error(`Daily budget must be at least ${budgetSymbol(wizardBudgetCurrency.value)}${effectiveMinBudget.value} ${wizardBudgetCurrency.value}.`);
        return;
    }

    const payload = {
        name: wizardName.value.trim(),
        goal: wizardGoal.value,
        platforms: wizardPlatforms.value,
        platform_ad_account_ids: Object.fromEntries(
            wizardPlatforms.value.map((platform) => [platform, String(wizardAdAccountIds.value[platform] ?? '').trim()])
        ),
        budget_amount: budget,
        budget_currency: wizardBudgetCurrency.value,
        budget_type: wizardBudgetType.value,
        start_date: wizardStartDate.value || null,
        end_date: wizardEndDate.value || null,
        product_url: wizardProductUrl.value || null,
        industry: wizardIndustry.value || null,
        goal_description: wizardGoalDesc.value || null,
        targeting: {
            age_min: wizardTargeting.value.age_min,
            age_max: wizardTargeting.value.age_max,
            countries: wizardTargeting.value.countries,
            interests: wizardTargeting.value.interests ? wizardTargeting.value.interests.split(',').map(s => s.trim()).filter(Boolean) : [],
        },
        meta_pixel_id: wizardMetaPixelId.value.trim() || null,
        meta_conversion_event: wizardMetaConversionEvent.value.trim() || null,
    };

    researchLoading.value = true;
    router.post(props.routes.store, payload, {
        preserveScroll: true,
        onSuccess: (page) => {
            const newCampaigns = (page.props as any).campaigns as Campaign[];
            const newest = newCampaigns?.[0];
            if (newest) {
                creatingCampaignId.value = newest.id;
                runResearch(newest);
            } else {
                researchLoading.value = false;
                toast.error('Campaign saved but could not start research.');
            }
        },
        onError: (e) => {
            researchLoading.value = false;
            toast.error(Object.values(e)[0] as string || 'Failed to save campaign.');
        },
    });
}

async function runResearch(campaign: Campaign): Promise<void> {
    researchLoading.value = true;
    try {
        const url = `/funnels/${props.funnel.id}/ads/${campaign.id}/research`;
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
        });
        const data = await resp.json();
        aiResearch.value = data.research;
        selectedHooks.value = (data.research?.hooks ?? []).slice(0, 3);
        wizardStep.value = 2;
    } catch (e) {
        toast.error('Research failed. You can proceed with manual hooks.');
        aiResearch.value = null;
        wizardStep.value = 2;
    } finally {
        researchLoading.value = false;
    }
}

async function generateCreativesStep(): Promise<void> {
    if (!creatingCampaignId.value) return;
    generatingCreatives.value = true;
    try {
        const url = `/funnels/${props.funnel.id}/ads/${creatingCampaignId.value}/creatives/generate`;
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '' },
            body: JSON.stringify({ hooks: selectedHooks.value, generate_images: generateImages.value, format: creativeFormat.value }),
        });
        const data = await resp.json();
        wizardCreatives.value = data.creatives ?? [];
        wizardStep.value = 4;
        router.reload({ only: ['campaigns'] });
    } catch {
        toast.error('Failed to generate creatives.');
    } finally {
        generatingCreatives.value = false;
    }
}

// ─── Campaign actions ────────────────────────────────────────────────────────
function launchCampaign(campaign: Campaign): void {
    if (launchingCampaignId.value) return;
    launchingCampaignId.value = campaign.id;
    router.post(`/funnels/${props.funnel.id}/ads/${campaign.id}/launch`, {}, {
        preserveScroll: true,
        onSuccess: () => { toast.success('Campaign is launching!'); },
        onError: (e) => { toast.error(Object.values(e)[0] as string || 'Launch failed.'); },
        onFinish: () => { launchingCampaignId.value = null; },
    });
}

function syncCampaign(campaign: Campaign): void {
    router.post(`/funnels/${props.funnel.id}/ads/${campaign.id}/sync`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Performance sync queued.'),
    });
}

function deleteCampaign(campaign: Campaign): void {
    if (!confirm(`Delete "${campaign.name}"?`)) return;
    router.delete(`/funnels/${props.funnel.id}/ads/${campaign.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Campaign deleted.'),
    });
}

// ─── Expanded campaign detail ─────────────────────────────────────────────────
const expandedCampaignId = ref<number | null>(null);
function toggleExpand(id: number): void {
    expandedCampaignId.value = expandedCampaignId.value === id ? null : id;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
const STATUS_META: Record<string, { label: string; dot: string; text: string; bg: string }> = {
    draft:      { label: 'Draft',      dot: 'bg-muted-foreground', text: 'text-muted-foreground', bg: 'bg-muted/30' },
    generating: { label: 'Generating', dot: 'bg-amber-500 animate-pulse', text: 'text-amber-600', bg: 'bg-amber-50 dark:bg-amber-950/20' },
    ready:      { label: 'Ready',      dot: 'bg-blue-500', text: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-950/20' },
    launching:  { label: 'Launching',  dot: 'bg-primary animate-pulse', text: 'text-primary', bg: 'bg-primary/5' },
    active:     { label: 'Active',     dot: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-50 dark:bg-emerald-950/20' },
    paused:     { label: 'Paused',     dot: 'bg-orange-500', text: 'text-orange-600', bg: 'bg-orange-50 dark:bg-orange-950/20' },
    completed:  { label: 'Completed',  dot: 'bg-slate-500', text: 'text-slate-600', bg: 'bg-muted/30' },
    failed:     { label: 'Failed',     dot: 'bg-rose-500', text: 'text-rose-600', bg: 'bg-rose-50 dark:bg-rose-950/20' },
};
function sm(s: string) { return STATUS_META[s] ?? STATUS_META['draft']; }

const CREATIVE_STATUS: Record<string, string> = {
    draft: 'text-muted-foreground', active: 'text-emerald-600', paused: 'text-orange-500',
    winner: 'text-yellow-600', loser: 'text-rose-500',
};

function fmtMoney(v: number | undefined): string {
    if (!v) return '$0.00';
    return '$' + v.toFixed(2);
}
function fmtNum(v: number | undefined): string {
    if (!v) return '0';
    return v >= 1000 ? (v / 1000).toFixed(1) + 'K' : String(v);
}
function fmtPct(v: number | undefined): string {
    return (v ?? 0).toFixed(2) + '%';
}
function fmtDate(v: string | null | undefined): string {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function launchErrorDisplay(campaign: Campaign): { title: string; message: string; action: string | null } {
    const rawSources = [
        campaign.last_error ?? '',
        ...(campaign.launch_errors?.items ?? []).map((i) => i.raw ?? i.message ?? ''),
    ].join(' ');

    if (/tried to access your account without permission|authenticate your account in ads manager/i.test(rawSources)) {
        return {
            title: 'Meta API security check (not an app bug)',
            message: 'Meta blocks API ad creation until the Facebook profile that authorized Zernio completes verification. Browsing Ads Manager can still work normally.',
            action: 'In Ads Manager: edit any ad → Publish → complete "Start Authentication" if prompted. Use the same personal Facebook account that owns ad account 403632037933686. Then reconnect Facebook in Settings and Retry.',
        };
    }

    const primary = campaign.launch_errors?.primary;
    if (primary?.title && primary?.message) {
        return primary;
    }
    if (campaign.last_error) {
        return {
            title: 'Ad launch failed',
            message: campaign.last_error.replace(/^(Meta Ads API error \(\d+\):\s*|Zernio API error \(\d+\):\s*)/i, ''),
            action: null,
        };
    }
    return { title: '', message: '', action: null };
}
function totalSpend(campaign: Campaign): number {
    return (campaign.performance?.spend ?? 0);
}
function hasPerformance(campaign: Campaign): boolean {
    return (campaign.performance?.impressions ?? 0) > 0;
}

const activeCampaigns   = computed(() => props.campaigns.filter(c => c.status === 'active'));
const totalAdSpend      = computed(() => activeCampaigns.value.reduce((s, c) => s + totalSpend(c), 0));
const totalImpressions  = computed(() => activeCampaigns.value.reduce((s, c) => s + (c.performance?.impressions ?? 0), 0));
const totalConversions  = computed(() => activeCampaigns.value.reduce((s, c) => s + (c.performance?.conversions ?? 0), 0));

const hasLaunchingCampaigns = computed(() => props.campaigns.some((c) => c.status === 'launching'));

let launchPollTimer: ReturnType<typeof setInterval> | null = null;

function startLaunchPolling(): void {
    if (launchPollTimer) return;
    launchPollTimer = setInterval(() => {
        router.reload({ only: ['campaigns'], preserveScroll: true });
    }, 5000);
}

function stopLaunchPolling(): void {
    if (!launchPollTimer) return;
    clearInterval(launchPollTimer);
    launchPollTimer = null;
}

watch(hasLaunchingCampaigns, (launching) => {
    if (launching) startLaunchPolling();
    else stopLaunchPolling();
}, { immediate: true });

onUnmounted(() => stopLaunchPolling());

function launchStats(campaign: Campaign): { total: number; live: number } {
    const total = campaign.creatives.length;
    const live = campaign.creatives.filter((c) => c.status === 'active' && c.zernio_ad_id).length;
    return { total, live };
}
</script>

<template>
    <Head :title="`Paid Ads – ${funnel.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 px-4 py-6 md:px-6">

        <!-- ── Header ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors">Funnels</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <Link :href="`/funnels/${funnel.id}/edit`" class="hover:text-foreground transition-colors truncate max-w-[160px]">{{ funnel.name }}</Link>
                    <Icon icon="heroicons:chevron-right" class="size-3" />
                    <span class="text-foreground font-medium">Paid Ads</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight">Paid Ad Campaigns</h1>
                <p class="text-sm text-muted-foreground">AI generates hooks, copy, and images — launches via Zernio to Meta, Google, TikTok, X, LinkedIn, and Pinterest.</p>
            </div>
            <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 shrink-0" @click="openWizard">
                <Icon icon="heroicons:plus" class="size-3.5" />
                New Campaign
            </Button>
        </div>

        <!-- ── Ads not configured warning ─────────────────────────────── -->
        <div v-if="!adsEnabled" class="flex items-start gap-3 rounded-xl border border-amber-500/30 bg-amber-500/5 px-4 py-3">
            <Icon icon="heroicons:exclamation-triangle" class="size-4 text-amber-500 shrink-0 mt-0.5" />
            <div>
                <p class="text-xs font-semibold text-amber-700 dark:text-amber-400">Ad publishing not available</p>
                <p class="text-xs text-muted-foreground mt-0.5">Contact support to enable paid ad publishing. You can still generate and preview creatives.</p>
            </div>
        </div>

        <!-- ── Performance summary ─────────────────────────────────────── -->
        <div v-if="activeCampaigns.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <Card class="border shadow-sm"><CardContent class="p-4">
                <p class="text-xs text-muted-foreground">Active Campaigns</p>
                <p class="text-2xl font-bold mt-1 text-primary">{{ activeCampaigns.length }}</p>
            </CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4">
                <p class="text-xs text-muted-foreground">Total Spend</p>
                <p class="text-2xl font-bold mt-1">{{ fmtMoney(totalAdSpend) }}</p>
            </CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4">
                <p class="text-xs text-muted-foreground">Impressions</p>
                <p class="text-2xl font-bold mt-1 text-blue-600">{{ fmtNum(totalImpressions) }}</p>
            </CardContent></Card>
            <Card class="border shadow-sm"><CardContent class="p-4">
                <p class="text-xs text-muted-foreground">Conversions</p>
                <p class="text-2xl font-bold mt-1 text-emerald-600">{{ fmtNum(totalConversions) }}</p>
            </CardContent></Card>
        </div>

        <!-- ── Campaign list ───────────────────────────────────────────── -->
        <div v-if="campaigns.length > 0" class="flex flex-col gap-3">
            <div v-for="campaign in campaigns" :key="campaign.id" class="rounded-xl border bg-card shadow-sm overflow-hidden">

                <!-- Campaign header row -->
                <div class="flex items-center gap-3 px-4 py-3.5">
                    <!-- Status dot -->
                    <span class="size-2 rounded-full shrink-0" :class="sm(campaign.status).dot" />

                    <!-- Name + meta -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold truncate">{{ campaign.name }}</h3>
                            <span class="text-[0.65rem] px-1.5 py-0.5 rounded-full border" :class="sm(campaign.status).bg + ' ' + sm(campaign.status).text">
                                {{ sm(campaign.status).label }}
                            </span>
                            <span class="text-[0.65rem] text-muted-foreground">{{ adGoals[campaign.goal] ?? campaign.goal }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-0.5 text-[0.65rem] text-muted-foreground flex-wrap">
                            <span class="flex items-center gap-1">
                                <Icon icon="heroicons:currency-dollar" class="size-3" />
                                {{ formatCampaignBudget(campaign) }}/{{ campaign.budget_type }}
                                <span v-if="campaign.budget_currency && campaign.budget_currency !== 'USD'" class="text-muted-foreground">({{ campaign.budget_currency }})</span>
                            </span>
                            <span class="flex items-center gap-1">
                                <Icon v-for="p in (campaign.platforms ?? []).slice(0,4)" :key="p" :icon="adPlatforms[p]?.icon ?? 'heroicons:share'" class="size-3" :title="adPlatforms[p]?.label ?? p" />
                            </span>
                            <span v-if="campaign.creatives.length">{{ campaign.creatives.length }} creative{{ campaign.creatives.length !== 1 ? 's' : '' }}</span>
                            <span v-if="campaign.last_synced_at">Synced {{ fmtDate(campaign.last_synced_at) }}</span>
                        </div>
                    </div>

                    <!-- Performance quick stats (active only) -->
                    <div v-if="hasPerformance(campaign)" class="hidden sm:flex items-center gap-4 text-xs mr-2">
                        <div class="text-center">
                            <p class="text-[0.6rem] text-muted-foreground uppercase tracking-wide">Spend</p>
                            <p class="font-semibold">{{ fmtMoney(campaign.performance?.spend) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[0.6rem] text-muted-foreground uppercase tracking-wide">CTR</p>
                            <p class="font-semibold">{{ fmtPct(campaign.performance?.ctr) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[0.6rem] text-muted-foreground uppercase tracking-wide">CPC</p>
                            <p class="font-semibold">{{ fmtMoney(campaign.performance?.cpc) }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[0.6rem] text-muted-foreground uppercase tracking-wide">Conv.</p>
                            <p class="font-semibold">{{ campaign.performance?.conversions ?? 0 }}</p>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1.5 shrink-0">
                        <Button
                            v-if="canEditCampaign(campaign)"
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs gap-1"
                            @click="openEditCampaign(campaign)"
                        >
                            <Icon icon="heroicons:pencil-square" class="size-3" />
                            Edit
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs gap-1"
                            @click="duplicateCampaign(campaign)"
                        >
                            <Icon icon="heroicons:document-duplicate" class="size-3" />
                            Duplicate
                        </Button>
                        <Button
                            v-if="canEditCampaign(campaign) && campaign.creatives.length > 0"
                            size="sm"
                            variant="outline"
                            class="h-7 text-xs gap-1 hidden sm:inline-flex"
                            @click="openWizardForCampaign(campaign, campaign.ai_research ? 3 : 2)"
                        >
                            <Icon icon="heroicons:sparkles" class="size-3" />
                            Regenerate
                        </Button>
                        <!-- Launch / Retry -->
                        <Button
                            v-if="campaign.status === 'ready' || campaign.status === 'draft' || campaign.status === 'failed' || campaign.status === 'launching'"
                            size="sm"
                            class="h-7 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90"
                            :disabled="launchingCampaignId === campaign.id || !adsEnabled || campaign.creatives.length === 0"
                            @click="launchCampaign(campaign)"
                        >
                            <Icon :icon="launchingCampaignId === campaign.id ? 'heroicons:arrow-path' : 'heroicons:rocket-launch'" class="size-3" :class="launchingCampaignId === campaign.id ? 'animate-spin' : ''" />
                            {{ campaign.status === 'failed' || campaign.status === 'launching' ? 'Retry' : 'Launch' }}
                        </Button>
                        <!-- Sync -->
                        <Button v-if="campaign.status === 'active'" size="sm" variant="outline" class="h-7 text-xs gap-1" @click="syncCampaign(campaign)">
                            <Icon icon="heroicons:arrow-path" class="size-3" />
                            Sync
                        </Button>
                        <!-- Expand -->
                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0" @click="toggleExpand(campaign.id)">
                            <Icon :icon="expandedCampaignId === campaign.id ? 'heroicons:chevron-up' : 'heroicons:chevron-down'" class="size-3.5" />
                        </Button>
                        <!-- Delete -->
                        <Button size="sm" variant="ghost" class="h-7 w-7 p-0 text-rose-500 hover:text-rose-600" @click="deleteCampaign(campaign)">
                            <Icon icon="heroicons:trash" class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- Launching progress -->
                <div v-if="campaign.status === 'launching'" class="border-t border-primary/20 bg-primary/5 px-4 py-2.5 text-xs">
                    <div class="flex items-center gap-2 font-medium text-primary">
                        <Icon icon="heroicons:arrow-path" class="size-3.5 animate-spin shrink-0" />
                        Publishing creatives to {{ adPlatforms[campaign.platforms?.[0] ?? 'facebook']?.label ?? 'ad platform' }}…
                    </div>
                    <p class="mt-1 text-muted-foreground">
                        {{ launchStats(campaign).live }} of {{ launchStats(campaign).total }} creatives live.
                        Status becomes <span class="font-medium text-emerald-600">Active</span> when at least one creative is published.
                        This page refreshes every few seconds.
                    </p>
                </div>

                <!-- Error bar -->
                <div v-if="campaign.last_error || campaign.launch_errors?.primary" class="border-t border-rose-500/20 bg-rose-500/5 px-4 py-3 text-xs">
                    <div class="flex items-start gap-2.5">
                        <Icon icon="heroicons:exclamation-triangle" class="size-4 shrink-0 text-rose-500 mt-0.5" />
                        <div class="space-y-2 min-w-0">
                            <div>
                                <p class="font-semibold text-rose-700 dark:text-rose-400">{{ launchErrorDisplay(campaign).title }}</p>
                                <p class="mt-1 text-rose-600/90 dark:text-rose-300/90 leading-relaxed">{{ launchErrorDisplay(campaign).message }}</p>
                            </div>
                            <p v-if="launchErrorDisplay(campaign).action" class="rounded-md border border-rose-500/15 bg-background/60 px-2.5 py-2 text-muted-foreground leading-relaxed">
                                <span class="font-medium text-foreground">What to do:</span> {{ launchErrorDisplay(campaign).action }}
                            </p>
                            <p v-if="launchErrorDisplay(campaign).title.includes('Meta API security')" class="text-muted-foreground space-y-1">
                                <a
                                    href="https://business.facebook.com/adsmanager"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-primary underline underline-offset-2"
                                >Open Ads Manager</a>
                                — try editing an ad and clicking <strong>Publish</strong> to trigger "Start Authentication".
                                Also check
                                <a href="https://business.facebook.com/settings/people" target="_blank" rel="noopener noreferrer" class="font-medium text-primary underline underline-offset-2">Business Settings → People</a>
                                that your Facebook profile is Admin on the ad account.
                            </p>
                            <p v-else-if="campaign.last_error?.includes('Facebook account connected')" class="text-muted-foreground">
                                Go to
                                <Link href="/settings/social-traffic" class="font-medium text-primary underline underline-offset-2">Settings → Social posting</Link>
                                and click <strong>Connect Facebook</strong>, then retry.
                            </p>
                            <details v-if="(campaign.launch_errors?.items?.length ?? 0) > 1" class="text-muted-foreground">
                                <summary class="cursor-pointer font-medium text-foreground/80">Show all {{ campaign.launch_errors?.items?.length }} creative errors</summary>
                                <ul class="mt-2 space-y-1.5 list-disc pl-4">
                                    <li v-for="(item, idx) in campaign.launch_errors?.items" :key="idx">
                                        <span class="font-medium text-foreground/80">{{ item.headline }}:</span> {{ item.message }}
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- Expanded: creatives grid + detailed performance -->
                <div v-if="expandedCampaignId === campaign.id" class="border-t bg-muted/10">

                    <!-- Hooks & research preview -->
                    <div v-if="campaign.ai_research" class="px-4 py-3 border-b">
                        <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide mb-2">AI-generated hooks</p>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="hook in campaign.ai_research.hooks" :key="hook" class="inline-flex items-center gap-1 rounded-full border border-primary/20 bg-primary/5 px-2.5 py-1 text-xs text-primary font-medium">
                                <Icon icon="heroicons:sparkles" class="size-3" />
                                {{ hook }}
                            </span>
                        </div>
                    </div>

                    <!-- Creatives grid -->
                    <div class="p-4">
                        <div v-if="campaign.creatives.length === 0" class="flex flex-col items-center gap-2 py-6 text-center text-xs text-muted-foreground">
                            <Icon icon="heroicons:photo" class="size-6 text-muted-foreground/40" />
                            <p>No creatives yet. Use the campaign wizard to generate them.</p>
                        </div>
                        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="creative in campaign.creatives"
                                :key="creative.id"
                                class="rounded-lg border bg-card overflow-hidden"
                                :class="creative.is_winner ? 'border-yellow-400/50 ring-1 ring-yellow-400/30' : ''"
                            >
                                <!-- Image -->
                                <div class="h-32 bg-muted relative">
                                    <img v-if="creative.asset_url" :src="creative.asset_url" class="w-full h-full object-cover" alt="" />
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <Icon icon="heroicons:photo" class="size-8 text-muted-foreground/30" />
                                    </div>
                                    <!-- Winner badge -->
                                    <span v-if="creative.is_winner" class="absolute top-1.5 right-1.5 flex items-center gap-1 rounded-full bg-yellow-400 text-yellow-900 text-[0.6rem] font-bold px-1.5 py-0.5">
                                        <Icon icon="heroicons:trophy" class="size-2.5" />WINNER
                                    </span>
                                    <!-- Status badge -->
                                    <span class="absolute top-1.5 left-1.5 rounded-full border px-1.5 py-0.5 text-[0.6rem] font-medium"
                                        :class="creative.status === 'active' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600' : creative.status === 'paused' ? 'bg-orange-500/10 border-orange-500/20 text-orange-600' : 'bg-muted text-muted-foreground border-border'">
                                        {{ creative.status }}
                                    </span>
                                    <!-- Format badge -->
                                    <span class="absolute bottom-1.5 right-1.5 rounded bg-black/40 text-white text-[0.55rem] px-1">{{ creative.format }}</span>
                                </div>
                                <!-- Copy -->
                                <div class="p-2.5 space-y-1.5">
                                    <p class="text-xs font-semibold leading-snug line-clamp-2">{{ creative.headline || '—' }}</p>
                                    <p class="text-[0.65rem] text-muted-foreground leading-relaxed line-clamp-2">{{ creative.primary_text }}</p>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[0.6rem] rounded bg-muted px-1.5 py-0.5 font-medium text-muted-foreground">{{ ctaButtons[creative.cta_button] ?? creative.cta_button }}</span>
                                        <!-- Performance -->
                                        <div v-if="creative.performance?.impressions" class="flex gap-2 text-[0.6rem] text-muted-foreground">
                                            <span>{{ fmtNum(creative.performance.impressions) }} impr.</span>
                                            <span>{{ fmtPct(creative.performance.ctr) }} CTR</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed performance table -->
                    <div v-if="hasPerformance(campaign)" class="border-t px-4 py-3">
                        <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide mb-2">Campaign performance</p>
                        <div class="grid grid-cols-4 sm:grid-cols-8 gap-3 text-center">
                            <div v-for="[label, value] in [
                                ['Spend', fmtMoney(campaign.performance?.spend)],
                                ['Impressions', fmtNum(campaign.performance?.impressions)],
                                ['Clicks', fmtNum(campaign.performance?.clicks)],
                                ['CTR', fmtPct(campaign.performance?.ctr)],
                                ['CPC', fmtMoney(campaign.performance?.cpc)],
                                ['CPM', fmtMoney(campaign.performance?.cpm)],
                                ['Conversions', String(campaign.performance?.conversions ?? 0)],
                                ['ROAS', (campaign.performance?.roas ?? 0).toFixed(2) + 'x'],
                            ]" :key="label" class="space-y-0.5">
                                <p class="text-[0.55rem] uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                                <p class="text-xs font-bold">{{ value }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Empty state ─────────────────────────────────────────────── -->
        <div v-else class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-14 text-center">
            <div class="size-14 rounded-full bg-muted flex items-center justify-center">
                <Icon icon="heroicons:rocket-launch" class="size-7 text-muted-foreground" />
            </div>
            <div>
                <p class="text-sm font-semibold">No ad campaigns yet</p>
                <p class="text-xs text-muted-foreground mt-1 max-w-xs">Let AI research your audience, generate hooks and creatives, then launch across Facebook, Instagram, TikTok and more.</p>
            </div>
            <Button size="sm" class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground mt-1" @click="openWizard">
                <Icon icon="heroicons:plus" class="size-3.5" />
                Create first campaign
            </Button>
        </div>
    </div>

    <!-- ── Campaign Creation Wizard ──────────────────────────────────────────── -->
    <Dialog :open="wizardOpen" @update:open="(v) => { if (!v) wizardOpen = false; }">
        <DialogContent
            class="max-w-2xl max-h-[90vh] flex flex-col gap-0 p-0 overflow-hidden"
            @pointer-down-outside="(e) => e.preventDefault()"
            @interact-outside="(e) => e.preventDefault()"
        >
            <!-- Header -->
            <div class="shrink-0 px-5 pt-5 pb-4 border-b">
                <DialogHeader>
                    <DialogTitle class="text-base">New Ad Campaign</DialogTitle>
                    <DialogDescription class="text-xs">
                        Step {{ wizardStep }} of 4 — {{ WIZARD_STEP_LABELS[wizardStep] }}
                    </DialogDescription>
                </DialogHeader>
                <!-- Step progress -->
                <div class="flex items-center gap-1.5 mt-3">
                    <div v-for="s in 4" :key="s" class="flex-1 h-1 rounded-full transition-colors" :class="s <= wizardStep ? 'bg-primary' : 'bg-muted'" />
                </div>
            </div>

            <!-- Body -->
            <div class="flex-1 min-h-0 overflow-y-auto">

                <!-- ── Step 1: Campaign Setup ── -->
                <div v-if="wizardStep === 1" class="px-5 py-5 space-y-5">
                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Campaign name *</Label>
                            <Input v-model="wizardName" placeholder="e.g. Organic Shampoo — Awareness" class="h-9 text-sm" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Industry</Label>
                                <Input v-model="wizardIndustry" placeholder="e.g. Health & Wellness" class="h-9 text-sm" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Destination URL</Label>
                                <Input v-model="wizardProductUrl" placeholder="https://…" class="h-9 text-sm" />
                                <p class="text-[0.65rem] text-muted-foreground">
                                    Where ad clicks go. Defaults to your funnel opt-in page if left blank.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Campaign goal *</Label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <button
                                    v-for="(label, key) in adGoals" :key="key" type="button"
                                    class="flex flex-col items-start px-3 py-2 rounded-lg border text-xs font-medium transition-all text-left"
                                    :class="wizardGoal === key ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                                    @click="wizardGoal = key"
                                >{{ label }}</button>
                            </div>
                        </div>

                        <p v-if="wizardGoal === 'engagement'" class="text-[0.65rem] text-amber-700 dark:text-amber-400 rounded-md border border-amber-500/20 bg-amber-500/5 px-2.5 py-2">
                            Sending people to a website? Use <strong>Drive Traffic</strong> instead. Engagement is for post likes/comments; we auto-use Traffic when a destination URL is set.
                        </p>
                        <p v-else-if="wizardGoal === 'conversions'" class="text-[0.65rem] text-muted-foreground rounded-md border px-2.5 py-2">
                            Requires a Meta Pixel on your ad account (Events Manager). Add the pixel ID below.
                        </p>

                        <div v-if="wizardGoal === 'conversions'" class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Pixel ID (Meta / TikTok) *</Label>
                                <Input v-model="wizardMetaPixelId" placeholder="e.g. 123456789012345" class="h-9 text-sm" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Conversion event</Label>
                                <Input v-model="wizardMetaConversionEvent" placeholder="LEAD or PURCHASE" class="h-9 text-sm" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Goal description <span class="font-normal text-muted-foreground">(optional — helps AI)</span></Label>
                            <Textarea v-model="wizardGoalDesc" placeholder="e.g. Drive sign-ups to the webinar from cold audiences aged 25-45 in the US" class="text-sm min-h-[64px]" />
                        </div>

                        <!-- Platforms -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold">Platforms *</Label>
                            <p class="text-[0.65rem] text-muted-foreground">
                                Standalone launch: Facebook, Instagram, Google, X, LinkedIn, Pinterest.
                                TikTok needs video. Reddit &amp; YouTube are not supported here.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="(meta, key) in adPlatforms" :key="key" type="button"
                                    class="flex flex-col items-start gap-0.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                                    :class="[
                                        wizardPlatforms.includes(key) ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted',
                                        isUnsupportedPlatform(key) ? 'opacity-60' : '',
                                    ]"
                                    @click="toggleWizardPlatform(key)"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <Icon :icon="meta.icon" class="size-3.5" />
                                        {{ meta.label }}
                                        <span v-if="isUnsupportedPlatform(key)" class="text-[0.55rem] uppercase tracking-wide text-amber-600">N/A</span>
                                    </span>
                                    <span v-if="platformLaunchHint(key)" class="text-[0.55rem] font-normal leading-tight text-muted-foreground max-w-[140px]">{{ platformLaunchHint(key) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Budget -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold" :class="wizardBudgetInvalid ? 'text-destructive' : ''">Daily budget *</Label>
                                <div class="flex gap-2">
                                    <select
                                        v-model="wizardBudgetCurrency"
                                        class="h-9 rounded-md border border-input bg-background px-2 text-xs font-medium shrink-0"
                                    >
                                        <option v-for="code in (budgetCurrencies ?? ['USD', 'NGN', 'EUR', 'GBP'])" :key="code" :value="code">{{ code }}</option>
                                    </select>
                                    <Input
                                        v-model="wizardBudget"
                                        type="number"
                                        :min="effectiveMinBudget"
                                        step="0.01"
                                        class="h-9 text-sm flex-1"
                                        :class="wizardBudgetInvalid ? 'border-destructive ring-2 ring-destructive/20 focus-visible:ring-destructive/30' : ''"
                                    />
                                </div>
                                <p v-if="wizardBudgetInvalid" class="text-[0.65rem] font-medium text-destructive">
                                    Enter at least {{ budgetSymbol(wizardBudgetCurrency) }}{{ effectiveMinBudget }} {{ wizardBudgetCurrency }} per day.
                                </p>
                                <p v-else class="text-[0.65rem] text-muted-foreground">
                                    Amount is in your Meta ad account billing currency — not USD unless your account bills in USD. Nigerian accounts need at least ₦1,762/day.
                                </p>
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Budget type</Label>
                                <div class="flex gap-2 mt-1">
                                    <button type="button" class="flex-1 h-9 rounded-lg border text-xs font-medium transition-all" :class="wizardBudgetType === 'daily' ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'" @click="wizardBudgetType = 'daily'">Daily</button>
                                    <button type="button" class="flex-1 h-9 rounded-lg border text-xs font-medium transition-all" :class="wizardBudgetType === 'lifetime' ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'" @click="wizardBudgetType = 'lifetime'">Lifetime</button>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">Start date</Label>
                                <Input v-model="wizardStartDate" type="date" class="h-9 text-sm" />
                            </div>
                            <div class="space-y-1.5">
                                <Label class="text-xs font-semibold">End date</Label>
                                <Input v-model="wizardEndDate" type="date" class="h-9 text-sm" />
                            </div>
                        </div>

                        <!-- Targeting -->
                        <div class="space-y-3">
                            <Label class="text-xs font-semibold">Audience targeting</Label>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="space-y-1">
                                    <Label class="text-[0.65rem] text-muted-foreground">Min age</Label>
                                    <Input v-model.number="wizardTargeting.age_min" type="number" min="18" max="65" class="h-8 text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-[0.65rem] text-muted-foreground">Max age</Label>
                                    <Input v-model.number="wizardTargeting.age_max" type="number" min="18" max="65" class="h-8 text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-[0.65rem] text-muted-foreground">Countries (ISO)</Label>
                                    <Input v-model="wizardTargeting.countries[0]" placeholder="US" class="h-8 text-xs" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-[0.65rem] text-muted-foreground">Interests (comma-separated)</Label>
                                <Input v-model="wizardTargeting.interests" placeholder="e.g. affiliate marketing, online business, make money online" class="h-8 text-xs" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label class="text-xs font-semibold">Platform ad account ID (where spend is billed) *</Label>
                                <Link
                                    v-if="adAccountsSettingsUrl"
                                    :href="adAccountsSettingsUrl"
                                    class="text-[0.65rem] font-medium text-primary underline underline-offset-2 shrink-0"
                                >
                                    Manage saved IDs
                                </Link>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div v-for="platform in wizardPlatforms" :key="`ad-account-${platform}`" class="space-y-1">
                                    <Label class="text-[0.65rem] text-muted-foreground">{{ adPlatforms[platform]?.label ?? platform }}</Label>
                                    <Input
                                        v-model="wizardAdAccountIds[platform]"
                                        :placeholder="adAccountHint(platform)"
                                        class="h-8 text-xs"
                                    />
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ── Step 2: AI Research ── -->
                <div v-else-if="wizardStep === 2" class="px-5 py-5 space-y-5">
                    <div v-if="researchLoading" class="flex flex-col items-center gap-3 py-10 text-center">
                        <Icon icon="heroicons:sparkles" class="size-8 text-primary animate-pulse" />
                        <p class="text-sm font-medium">AI is researching your audience…</p>
                        <p class="text-xs text-muted-foreground">Generating hooks, personas, and angles based on your funnel</p>
                    </div>
                    <div v-else-if="aiResearch" class="space-y-5">
                        <!-- Hooks to select -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-semibold">Select hooks for your ads <span class="text-muted-foreground font-normal">(pick up to 5)</span></Label>
                                <span class="text-[0.65rem] text-muted-foreground">{{ selectedHooks.length }} selected</span>
                            </div>
                            <div class="space-y-2">
                                <button
                                    v-for="hook in aiResearch.hooks" :key="hook" type="button"
                                    class="w-full flex items-start gap-2.5 rounded-lg border px-3.5 py-2.5 text-left text-xs transition-all"
                                    :class="selectedHooks.includes(hook) ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border hover:bg-muted'"
                                    @click="toggleHook(hook)"
                                >
                                    <span class="size-4 rounded border flex items-center justify-center shrink-0 mt-0.5" :class="selectedHooks.includes(hook) ? 'bg-primary border-primary' : 'border-border'">
                                        <Icon v-if="selectedHooks.includes(hook)" icon="heroicons:check" class="size-2.5 text-white" />
                                    </span>
                                    <span class="font-medium leading-snug">{{ hook }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Angles, personas (collapsed details) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="rounded-lg border p-3 space-y-1.5">
                                <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide">Pain points</p>
                                <ul class="space-y-1">
                                    <li v-for="pt in aiResearch.pain_points" :key="pt" class="text-xs text-muted-foreground flex items-start gap-1.5">
                                        <Icon icon="heroicons:x-mark" class="size-3 text-rose-400 shrink-0 mt-0.5" />
                                        {{ pt }}
                                    </li>
                                </ul>
                            </div>
                            <div class="rounded-lg border p-3 space-y-1.5">
                                <p class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-wide">Value props</p>
                                <ul class="space-y-1">
                                    <li v-for="vp in aiResearch.value_props" :key="vp" class="text-xs text-muted-foreground flex items-start gap-1.5">
                                        <Icon icon="heroicons:check" class="size-3 text-emerald-500 shrink-0 mt-0.5" />
                                        {{ vp }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div v-else class="space-y-3 py-4 text-center">
                        <Icon icon="heroicons:exclamation-circle" class="size-8 text-muted-foreground mx-auto" />
                        <p class="text-sm text-muted-foreground">AI research unavailable (OpenAI not configured). You can still generate creatives with manual hooks below.</p>
                        <div class="text-left space-y-2">
                            <Label class="text-xs font-semibold">Enter hooks manually (one per line)</Label>
                            <Textarea
                                placeholder="Still using shampoo loaded with sulfates?&#10;Your hair isn't damaged. Your shampoo is.&#10;The organic shampoo thousands switched to."
                                class="text-sm min-h-[100px]"
                                @input="updateManualHooks"
                            />
                        </div>
                    </div>
                </div>

                <!-- ── Step 3: Generate Creatives ── -->
                <div v-else-if="wizardStep === 3" class="px-5 py-5 space-y-5">
                    <div v-if="generatingCreatives" class="flex flex-col items-center gap-3 py-10 text-center">
                        <Icon icon="heroicons:photo" class="size-8 text-primary animate-pulse" />
                        <p class="text-sm font-medium">AI is writing copy and generating images…</p>
                        <p class="text-xs text-muted-foreground">Creating {{ selectedHooks.length }} ad creative{{ selectedHooks.length !== 1 ? 's' : '' }}. This may take 30–60 seconds.</p>
                    </div>
                    <div v-else class="space-y-4">
                        <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-primary space-y-1">
                            <p class="font-semibold">AI will generate:</p>
                            <ul class="space-y-0.5 text-primary/80">
                                <li>• {{ selectedHooks.length }} ad variant{{ selectedHooks.length !== 1 ? 's' : '' }} (one per selected hook)</li>
                                <li>• Headline, primary text, and description for each</li>
                                <li v-if="generateImages">• AI-generated banner image for each variant (gpt-image-1)</li>
                            </ul>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-xs font-semibold">Image format</Label>
                            <div class="grid grid-cols-4 gap-2">
                                <button v-for="fmt in ['square', 'story', 'landscape', 'reel']" :key="fmt" type="button"
                                    class="h-9 rounded-lg border text-xs font-medium transition-all capitalize"
                                    :class="creativeFormat === fmt ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                                    @click="creativeFormat = fmt as any"
                                >{{ fmt }}</button>
                            </div>
                            <p class="text-[0.65rem] text-muted-foreground">Square (1:1) works best for feeds. Story/Reel (9:16) for Instagram & TikTok. Landscape (16:9) for YouTube & Google.</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="size-4 rounded border flex items-center justify-center"
                                :class="generateImages ? 'bg-primary border-primary' : 'border-border'"
                                @click="generateImages = !generateImages"
                            >
                                <Icon v-if="generateImages" icon="heroicons:check" class="size-2.5 text-white" />
                            </button>
                            <Label class="text-xs cursor-pointer" @click="generateImages = !generateImages">Generate banner images</Label>
                        </div>

                        <!-- Selected hooks preview -->
                        <div class="space-y-1.5">
                            <Label class="text-xs font-semibold text-muted-foreground">Creating ads for these hooks:</Label>
                            <div class="space-y-1">
                                <div v-for="hook in selectedHooks" :key="hook" class="flex items-center gap-2 text-xs py-1.5 px-2.5 rounded-md bg-muted">
                                    <Icon icon="heroicons:sparkles" class="size-3 text-primary shrink-0" />
                                    {{ hook }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Step 4: Review & Launch ── -->
                <div v-else-if="wizardStep === 4" class="px-5 py-5 space-y-5">
                    <div class="flex items-center gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                        <Icon icon="heroicons:check-circle" class="size-5 text-emerald-500 shrink-0" />
                        <div>
                            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ wizardCreatives.length }} creative{{ wizardCreatives.length !== 1 ? 's' : '' }} generated!</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Review them in the campaign card, then hit Launch to deploy.</p>
                        </div>
                    </div>

                    <!-- Creatives preview -->
                    <div class="grid grid-cols-2 gap-3">
                        <div v-for="c in wizardCreatives" :key="c.id" class="rounded-lg border overflow-hidden">
                            <div class="h-24 bg-muted">
                                <img v-if="c.asset_url" :src="c.asset_url" class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center text-xs text-muted-foreground/50">No image</div>
                            </div>
                            <div class="p-2">
                                <p class="text-xs font-semibold line-clamp-1">{{ c.headline }}</p>
                                <p class="text-[0.65rem] text-muted-foreground line-clamp-2 mt-0.5">{{ c.primary_text }}</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">You can edit creatives, change copy, and swap images before launching. The campaign is in <strong>Draft</strong> status until you hit Launch.</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="shrink-0 border-t px-5 py-3.5 flex items-center gap-2 bg-muted/10">
                <Button v-if="wizardStep > 1 && wizardStep < 4" size="sm" variant="outline" class="h-8 text-xs" @click="prevStep">Back</Button>
                <div class="flex-1" />
                <Button size="sm" variant="ghost" class="h-8 text-xs text-muted-foreground" @click="wizardOpen = false">Cancel</Button>
                <Button
                    size="sm"
                    class="h-8 text-xs gap-1.5 bg-primary text-primary-foreground hover:opacity-90 min-w-[90px]"
                    :disabled="researchLoading || generatingCreatives"
                    @click="nextStep"
                >
                    <Icon v-if="researchLoading || generatingCreatives" icon="heroicons:arrow-path" class="size-3.5 animate-spin" />
                    <template v-else>
                        <span v-if="wizardStep === 1">Save & Research</span>
                        <span v-else-if="wizardStep === 2">Generate Creatives</span>
                        <span v-else-if="wizardStep === 3">Generate Now</span>
                        <span v-else-if="wizardStep === 4">Done</span>
                    </template>
                </Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- ── Edit Campaign ───────────────────────────────────────────────────── -->
    <Dialog :open="editOpen" @update:open="(v) => { if (!v) editOpen = false; }">
        <DialogContent class="max-w-2xl max-h-[90vh] flex flex-col gap-0 p-0 overflow-hidden">
            <div class="shrink-0 px-5 pt-5 pb-4 border-b">
                <DialogHeader>
                    <DialogTitle class="text-base">Edit Campaign</DialogTitle>
                    <DialogDescription class="text-xs">
                        Update settings before launching. Budget is in your ad account billing currency.
                    </DialogDescription>
                </DialogHeader>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto px-5 py-5 space-y-4">
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Campaign name *</Label>
                    <Input v-model="editName" class="h-9 text-sm" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Industry</Label>
                        <Input v-model="editIndustry" class="h-9 text-sm" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Destination URL</Label>
                        <Input v-model="editProductUrl" class="h-9 text-sm" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Campaign goal</Label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <button
                            v-for="(label, key) in adGoals" :key="key" type="button"
                            class="px-3 py-2 rounded-lg border text-xs font-medium transition-all text-left"
                            :class="editGoal === key ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                            @click="editGoal = key"
                        >{{ label }}</button>
                    </div>
                </div>
                            <div v-if="editGoal === 'conversions'" class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Pixel ID (Meta / TikTok) *</Label>
                        <Input v-model="editMetaPixelId" placeholder="e.g. 123456789012345" class="h-9 text-sm" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Conversion event</Label>
                        <Input v-model="editMetaConversionEvent" placeholder="LEAD or PURCHASE" class="h-9 text-sm" />
                    </div>
                </div>
                <p v-else-if="editGoal === 'engagement'" class="text-[0.65rem] text-amber-700 dark:text-amber-400 rounded-md border border-amber-500/20 bg-amber-500/5 px-2.5 py-2">
                    Ads with a destination URL launch as <strong>Drive Traffic</strong> on Meta (link clicks), not post engagement.
                </p>
                <div class="space-y-1.5">
                    <Label class="text-xs font-semibold">Platforms</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(meta, key) in adPlatforms" :key="key" type="button"
                            class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                            :class="editPlatforms.includes(key) ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'"
                            @click="toggleEditPlatform(key)"
                        >
                            <Icon :icon="meta.icon" class="size-3.5" />
                            {{ meta.label }}
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold" :class="editBudgetInvalid ? 'text-destructive' : ''">Daily budget *</Label>
                        <div class="flex gap-2">
                            <select v-model="editBudgetCurrency" class="h-9 rounded-md border border-input bg-background px-2 text-xs font-medium shrink-0">
                                <option v-for="code in (budgetCurrencies ?? ['USD', 'NGN', 'EUR', 'GBP'])" :key="code" :value="code">{{ code }}</option>
                            </select>
                            <Input
                                v-model="editBudget"
                                type="number"
                                :min="editEffectiveMinBudget"
                                step="0.01"
                                class="h-9 text-sm flex-1"
                                :class="editBudgetInvalid ? 'border-destructive ring-2 ring-destructive/20' : ''"
                            />
                        </div>
                        <p v-if="editBudgetInvalid" class="text-[0.65rem] font-medium text-destructive">
                            Minimum {{ budgetSymbol(editBudgetCurrency) }}{{ editEffectiveMinBudget }} {{ editBudgetCurrency }}/day
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Budget type</Label>
                        <div class="flex gap-2 mt-1">
                            <button type="button" class="flex-1 h-9 rounded-lg border text-xs font-medium" :class="editBudgetType === 'daily' ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground'" @click="editBudgetType = 'daily'">Daily</button>
                            <button type="button" class="flex-1 h-9 rounded-lg border text-xs font-medium" :class="editBudgetType === 'lifetime' ? 'border-primary/50 bg-primary/10 text-primary' : 'border-border text-muted-foreground'" @click="editBudgetType = 'lifetime'">Lifetime</button>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">Start date</Label>
                        <Input v-model="editStartDate" type="date" class="h-9 text-sm" />
                    </div>
                    <div class="space-y-1.5">
                        <Label class="text-xs font-semibold">End date</Label>
                        <Input v-model="editEndDate" type="date" class="h-9 text-sm" />
                    </div>
                </div>
                <div class="space-y-2">
                    <Label class="text-xs font-semibold">Platform ad account IDs *</Label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div v-for="platform in editPlatforms" :key="`edit-ad-${platform}`" class="space-y-1">
                            <Label class="text-[0.65rem] text-muted-foreground">{{ adPlatforms[platform]?.label ?? platform }}</Label>
                            <Input v-model="editAdAccountIds[platform]" :placeholder="adAccountHint(platform)" class="h-8 text-xs" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="shrink-0 flex items-center justify-end gap-2 px-5 py-4 border-t bg-muted/20">
                <Button variant="outline" size="sm" class="h-8 text-xs" @click="editOpen = false">Cancel</Button>
                <Button size="sm" class="h-8 text-xs" :disabled="editSaving || editBudgetInvalid" @click="saveEditCampaign">
                    <Icon v-if="editSaving" icon="heroicons:arrow-path" class="size-3.5 animate-spin mr-1" />
                    Save changes
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
