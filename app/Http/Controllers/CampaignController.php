<?php

namespace App\Http\Controllers;

use App\Jobs\Campaigns\RunCampaignGenerationJob;
use App\Jobs\Campaigns\RunCampaignQuickStartJob;
use App\Jobs\Campaigns\UploadCampaignSequenceJob;
use App\Models\Campaign;
use App\Models\CampaignLead;
use App\Models\CampaignPage;
use App\Models\IntegrationAccount;
use App\Services\Campaigns\CampaignBonusPresenterService;
use App\Services\Campaigns\CampaignBuilderService;
use App\Services\Campaigns\CampaignGenerationProgressService;
use App\Services\Campaigns\CampaignLeadCaptureService;
use App\Services\Campaigns\CampaignGenerationStateService;
use App\Services\Campaigns\CampaignLinkResolverService;
use App\Services\Campaigns\LeadMagnetPdfService;
use App\Services\Campaigns\OfferIntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignController extends Controller
{
    public function __construct(
        protected CampaignGenerationProgressService $generationProgress,
        protected CampaignGenerationStateService $generationState,
        protected CampaignLinkResolverService $linkResolver,
        protected LeadMagnetPdfService $leadMagnetPdf,
        protected CampaignLeadCaptureService $leadCapture,
    ) {}

    public function index(): Response
    {
        $userId = auth()->id();

        $campaigns = Campaign::query()
            ->where('user_id', $userId)
            ->withCount(['bonuses', 'emails', 'funnels', 'trackedLinks'])
            ->latest()
            ->get()
            ->map(fn (Campaign $c) => [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name,
                'slug' => $c->slug,
                'type' => $c->type,
                'status' => $c->status,
                'wizard_step' => $c->wizard_step,
                'created_at' => $c->created_at,
                'published_at' => $c->published_at,
                'bonuses_count' => $c->bonuses_count,
                'emails_count' => $c->emails_count,
                'funnels_count' => $c->funnels_count,
                'tracked_links_count' => $c->tracked_links_count,
                'knowledge_ready' => ($c->knowledge['status'] ?? '') === 'ready',
            ]);

        return Inertia::render('campaigns/Index', [
            'campaigns' => $campaigns,
            'stats' => [
                'total' => $campaigns->count(),
                'published' => $campaigns->where('status', 'published')->count(),
                'draft' => $campaigns->where('status', 'draft')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('campaigns/Wizard', [
            'campaign' => null,
            'integrationAccounts' => IntegrationAccount::query()
                ->where('user_id', auth()->id())
                ->get(['id', 'name', 'provider']),
            'intakePrefill' => $this->intakePrefillFromRequest($request),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function intakePrefillFromRequest(Request $request): ?array
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $offerUrl = trim((string) $request->query('offer_url', ''));
        $title = trim((string) $request->query('title', ''));
        $marketplace = trim((string) $request->query('marketplace', ''));

        if ($keyword === '' && $offerUrl === '') {
            return null;
        }

        return [
            'mode' => $request->query('mode', 'keyword') === 'links' ? 'links' : 'keyword',
            'keyword' => $keyword,
            'offer_url' => $offerUrl !== '' ? $offerUrl : null,
            'title' => $title !== '' ? $title : null,
            'marketplace' => $marketplace !== '' ? $marketplace : null,
            'type' => in_array($request->query('type'), ['sales', 'webinar'], true)
                ? $request->query('type')
                : 'webinar',
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'type' => ['required', Rule::in(['sales', 'webinar'])],
            'offer_url' => ['nullable', 'url', 'max:2048'],
            'affiliate_link' => ['nullable', 'url', 'max:2048'],
            'marketplace' => ['nullable', 'string', 'max:40'],
            'offer_data' => ['nullable', 'array'],
        ]);

        $slug = Str::slug($validated['slug'] ?? $validated['name']);
        $slug = $this->uniqueCampaignSlug((int) $request->user()->id, $slug ?: 'campaign');

        $campaign = Campaign::query()->create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'type' => $validated['type'],
            'status' => 'draft',
            'wizard_step' => 1,
            'offer_url' => $validated['offer_url'] ?? null,
            'affiliate_link' => $validated['affiliate_link'] ?? null,
            'marketplace' => $validated['marketplace'] ?? null,
            'offer_data' => $validated['offer_data'] ?? null,
        ]);

        app(\App\Services\Campaigns\CampaignTrafficHubService::class)->ensureTrafficFunnel($campaign);

        return to_route('campaigns.edit', $campaign->id);
    }

    public function quickStart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'type' => ['nullable', Rule::in(['sales', 'webinar'])],
            'offer_url' => ['required', 'url', 'max:2048'],
            'affiliate_link' => ['nullable', 'url', 'max:2048'],
            'marketplace' => ['nullable', 'string', 'max:40'],
            'keyword' => ['nullable', 'string', 'max:160'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $title = trim((string) ($validated['title'] ?? $validated['keyword'] ?? 'New Campaign'));
        $slug = $this->uniqueCampaignSlug((int) $request->user()->id, Str::slug($title) ?: 'campaign');

        $offerData = [
            'product_name' => $title,
            'source' => 'opportunity_quick_start',
            'marketplace' => $validated['marketplace'] ?? null,
            'keyword' => $validated['keyword'] ?? null,
        ];

        $campaign = Campaign::query()->create([
            'user_id' => $request->user()->id,
            'name' => $title,
            'slug' => $slug,
            'type' => $validated['type'] ?? 'sales',
            'status' => 'draft',
            'wizard_step' => 1,
            'offer_url' => $validated['offer_url'],
            'affiliate_link' => $validated['affiliate_link'] ?? null,
            'marketplace' => $validated['marketplace'] ?? null,
            'offer_data' => $offerData,
            'meta' => ['quick_start' => true, 'source_keyword' => $validated['keyword'] ?? null],
        ]);

        app(\App\Services\Campaigns\CampaignTrafficHubService::class)->ensureTrafficFunnel($campaign);

        if ($this->generationProgress->isRunning($campaign)) {
            return to_route('campaigns.edit', $campaign->id);
        }

        $this->generationProgress->start($campaign, 'quick_start', 'Building full campaign from offer…');
        RunCampaignQuickStartJob::dispatch($campaign->id);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Campaign created — AI is building everything in the background.',
        ]);

        return to_route('campaigns.edit', $campaign->id);
    }

    public function edit(Campaign $campaign): Response
    {
        $this->authorizeCampaign($campaign);

        $campaign->load([
            'pages',
            'bonuses',
            'emails',
            'funnels:id,campaign_id,name,slug,status',
            'trackedLinks',
        ]);

        $username = $campaign->user->username ?? 'user-'.$campaign->user_id;
        $leadMagnet = $campaign->pages->firstWhere('page_type', 'lead_magnet');
        $this->generationState->syncFromContent($campaign);
        $this->repairThankYouDownloadUrl($campaign, $leadMagnet);
        $this->reconcileGenerationProgress($campaign, $leadMagnet);

        return Inertia::render('campaigns/Wizard', [
            'campaign' => $this->campaignPayload($campaign, $username, $leadMagnet),
            'integrationAccounts' => IntegrationAccount::query()
                ->where('user_id', auth()->id())
                ->get(['id', 'name', 'provider']),
            'bonusLibrary' => app(CampaignBonusPresenterService::class)->libraryForUser((int) auth()->id()),
        ]);
    }

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'type' => ['sometimes', Rule::in(['sales', 'webinar'])],
            'wizard_step' => ['sometimes', 'integer', 'min:1', 'max:8'],
            'offer_url' => ['nullable', 'url', 'max:2048'],
            'affiliate_link' => ['nullable', 'url', 'max:2048'],
            'marketplace' => ['nullable', 'string', 'max:40'],
            'offer_data' => ['nullable', 'array'],
            'analysis' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['draft', 'published', 'archived'])],
        ]);

        if (isset($validated['status']) && $validated['status'] === 'published') {
            $validated['published_at'] = now();
        }

        $oldAffiliate = $campaign->affiliate_link;

        $campaign->update($validated);

        if (array_key_exists('affiliate_link', $validated) && ($validated['affiliate_link'] ?? null) !== $oldAffiliate) {
            $this->linkResolver->syncAffiliateDestination($campaign->fresh());
        }

        return back();
    }

    public function updatePage(Request $request, Campaign $campaign, string $pageType): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        abort_unless(in_array($pageType, ['squeeze', 'thankyou', 'quiz', 'bonus'], true), 404);

        $validated = $request->validate([
            'content' => ['required', 'array'],
        ]);

        $content = $validated['content'];

        if ($pageType === 'bonus') {
            $username = $request->user()->username ?? 'user';
            $content['bonuses'] = app(CampaignBonusPresenterService::class)->featuredForPage($campaign, $content, $username);
            $total = 0;
            foreach ($content['bonuses'] as $bonus) {
                if (preg_match('/\$(\d+)/', (string) ($bonus['value_label'] ?? ''), $m)) {
                    $total += (int) $m[1];
                }
            }
            $count = count($content['bonuses']);
            $content['total_value_label'] = $total > 0
                ? "{$count} bonuses · \${$total} total value"
                : "{$count} bonus".($count === 1 ? '' : 'es');
        }

        CampaignPage::query()->updateOrCreate(
            ['campaign_id' => $campaign->id, 'page_type' => $pageType],
            [
                'slug' => $pageType === 'squeeze' ? $campaign->slug : $campaign->slug.'-'.$pageType,
                'content' => $content,
            ]
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => ucfirst($pageType).' page saved.']);

        return back();
    }

    public function extractOffer(Request $request, OfferIntakeService $intake): JsonResponse
    {
        $validated = $request->validate(['url' => ['required', 'url', 'max:2048']]);

        return response()->json($intake->extractFromUrl($validated['url']));
    }

    public function searchOffers(Request $request, OfferIntakeService $intake): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:160'],
            'marketplace' => ['nullable', 'string', 'in:clickbank,jvzoo,warriorplus,digistore24'],
        ]);

        return response()->json(
            $intake->searchMarketplaces($validated['keyword'], $validated['marketplace'] ?? null)
        );
    }

    public function analyse(Request $request, Campaign $campaign, OfferIntakeService $intake): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'offer_data' => ['nullable', 'array'],
            'offer_url' => ['nullable', 'string', 'max:2048'],
            'affiliate_link' => ['nullable', 'string', 'max:2048'],
        ]);

        $offer = $validated['offer_data'] ?? $campaign->offer_data ?? [];

        $result = $intake->analyseOffer($offer);
        $campaign->update([
            'offer_url' => $validated['offer_url'] ?? $campaign->offer_url,
            'affiliate_link' => $validated['affiliate_link'] ?? $campaign->affiliate_link,
            'offer_data' => $offer,
            'analysis' => $result['analysis'],
            'wizard_step' => max((int) $campaign->wizard_step, 2),
        ]);

        Inertia::flash('toast', [
            'type' => $result['error'] ? 'warning' : 'success',
            'message' => $result['error'] ?? 'Offer analysis complete — open Knowledge Base (step 2) to build full context.',
        ]);

        return back();
    }

    public function buildKnowledge(Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        if (empty($campaign->affiliate_link)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Add your affiliate link on Import Offer first.']);

            return back();
        }

        if (($campaign->knowledge['status'] ?? '') === 'ready' || $this->generationState->isGenerated($campaign, 'knowledge')) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Knowledge base already built — showing saved version.']);

            return back();
        }

        return $this->dispatchGeneration($campaign, 'knowledge', [], 'Queued — building knowledge base (2 AI passes)…');
    }

    public function suggestLeadMagnets(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $force = $request->boolean('force');
        $page = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $suggestions = $page?->content['suggestions'] ?? [];

        if (! $force && ((is_array($suggestions) && count($suggestions) > 0) || $this->generationState->isGenerated($campaign, 'lead_magnet_suggest'))) {
            if (is_array($suggestions) && count($suggestions) > 0) {
                $this->generationState->mark($campaign, 'lead_magnet_suggest');
            }
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Lead magnet ideas already loaded.']);

            return back();
        }

        if ($force) {
            $this->generationState->clear($campaign, 'lead_magnet_suggest');
        }

        return $this->dispatchGeneration($campaign, 'lead_magnet_suggest', [], $force
            ? 'Refreshing lead magnet ideas from knowledge…'
            : 'Loading lead magnet ideas from knowledge…');
    }

    public function generateLeadMagnet(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'selected_id' => ['required', 'string', 'max:80'],
            'mode' => ['nullable', Rule::in(['auto', 'enhance', 'restart'])],
        ]);

        $mode = $validated['mode'] ?? 'auto';
        $page = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $content = is_array($page?->content) ? $page->content : [];
        $sameSelection = ($content['selected_id'] ?? null) === $validated['selected_id'];
        $alreadyReady = (
            ($this->generationState->isGenerated($campaign, 'lead_magnet')
                && (($campaign->generation_state['lead_magnet']['selected_id'] ?? $content['selected_id'] ?? null) === $validated['selected_id']))
            || ($content['generated'] ?? false) === true
            || ($content['status'] ?? '') === 'ready'
            || (! empty($content['pages']) && ! empty($content['download_path']))
        );

        if ($mode === 'auto' && $alreadyReady && $sameSelection) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Lead magnet already generated — showing saved version.']);

            return back();
        }

        if (in_array($mode, ['enhance', 'restart'], true)) {
            $this->generationState->clear($campaign, 'lead_magnet');
        }

        $suggestions = $content['suggestions'] ?? [];
        $selected = collect($suggestions)->firstWhere('id', $validated['selected_id']);
        $title = is_array($selected) ? (string) ($selected['title'] ?? 'Lead magnet') : 'Lead magnet';

        return $this->dispatchGeneration($campaign, 'lead_magnet', [
            'selected_id' => $validated['selected_id'],
            'mode' => $mode,
        ], "Queued — generating \"{$title}\" (multi-pass pipeline)…", [
            'selected_id' => $validated['selected_id'],
            'selected_title' => $title,
        ]);
    }

    public function skipLeadMagnet(Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        if ($campaign->type !== Campaign::TYPE_WEBINAR) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Lead magnet is required for sales campaigns.']);

            return back();
        }

        $this->generationState->mark($campaign, 'lead_magnet_skip');
        $this->generationProgress->idle($campaign);
        Inertia::flash('toast', ['type' => 'info', 'message' => 'Lead magnet skipped — continue to funnel pages.']);

        return back();
    }

    public function buildPages(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        if (($campaign->knowledge['status'] ?? '') !== 'ready') {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Build the knowledge base first.']);

            return back();
        }

        $leadMagnet = $campaign->pages()->where('page_type', 'lead_magnet')->first();

        if ($campaign->type !== Campaign::TYPE_WEBINAR && ! $this->leadMagnetReady($leadMagnet)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Generate the lead magnet first — thank-you page needs the download link.']);

            return back();
        }

        $force = $request->boolean('force');

        if (! $force && ($this->generationState->isGenerated($campaign, 'pages') || $this->funnelPagesReady($campaign))) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Funnel pages already generated — edit below or preview live.']);

            return back();
        }

        if ($force) {
            $this->generationState->clear($campaign, 'pages');
        }

        return $this->dispatchGeneration($campaign, 'pages', ['force' => $force], 'Queued — generating funnel pages from knowledge…');
    }

    public function suggestBonuses(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'bonus_type' => ['required', 'string', Rule::in(['ebook', 'mini_course'])],
            'force' => ['nullable', 'boolean'],
        ]);

        $force = $request->boolean('force');
        $existingType = $campaign->meta['bonus_type'] ?? null;
        $suggestions = $campaign->meta['bonus_suggestions'] ?? [];

        if (! $force && $existingType === $validated['bonus_type'] && is_array($suggestions) && count($suggestions) >= 3) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Bonus ideas already loaded — pick one below.']);

            return back();
        }

        if ($force) {
            $this->generationState->clear($campaign, 'bonuses_suggest');
        }

        return $this->dispatchGeneration($campaign, 'bonuses_suggest', [
            'bonus_type' => $validated['bonus_type'],
        ], 'Generating 3 bonus ideas for '.str_replace('_', ' ', $validated['bonus_type']).'…');
    }

    public function resetBonuses(Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $campaign->bonuses()->delete();
        $meta = $campaign->meta ?? [];
        unset($meta['bonus_suggestions'], $meta['bonus_type']);
        $campaign->update(['meta' => $meta]);
        app(CampaignBonusPresenterService::class)->syncBonusPage($campaign);
        $this->generationState->clearMany($campaign, ['bonuses', 'bonuses_suggest']);
        $this->generationProgress->idle($campaign);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Bonus cleared — choose a type to start again.']);

        return back();
    }

    public function generateBonuses(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'selected_id' => ['required', 'string', 'max:80'],
            'bonus_type' => ['required', 'string', Rule::in(['ebook', 'mini_course'])],
        ]);

        $existing = $campaign->bonuses()
            ->where('meta->selected_id', $validated['selected_id'])
            ->where('status', 'ready')
            ->exists();

        $stateBonus = $campaign->generation_state['bonuses'] ?? [];
        if ($existing || (
            $this->generationState->isGenerated($campaign, 'bonuses')
            && ($stateBonus['selected_id'] ?? null) === $validated['selected_id']
        )) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Bonus already generated — showing saved version.']);

            return back();
        }

        return $this->dispatchGeneration($campaign, 'bonuses', [
            'selected_id' => $validated['selected_id'],
            'bonus_type' => $validated['bonus_type'],
        ], 'Queued — generating your bonus…');
    }

    public function generateEmails(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'sequence' => ['nullable', 'string', 'max:40'],
            'count' => ['nullable', 'integer', 'min:3', 'max:10'],
            'force' => ['nullable', 'boolean'],
        ]);

        $force = $request->boolean('force');

        if (! $force && $this->generationState->isGenerated($campaign, 'emails') && $campaign->emails()->exists()) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Email swipes already generated — review below.']);

            return back();
        }

        if ($force) {
            $this->generationState->clear($campaign, 'emails');
        }

        $count = $validated['count'] ?? 5;

        return $this->dispatchGeneration($campaign, 'emails', [
            'sequence' => $validated['sequence'] ?? 'full_launch',
            'count' => $count,
            'force' => $force,
        ], "Queued — generating {$count} email swipes…");
    }

    public function generateWebinarFunnels(Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        abort_unless($campaign->type === Campaign::TYPE_WEBINAR, 403);

        $offer = $campaign->offer_data ?? ['product_name' => $campaign->name];
        $builder = app(CampaignBuilderService::class);
        $needsBoth = $builder->primaryOptinFunnel($campaign) === null
            || $builder->primaryWebinarFunnel($campaign) === null;

        if ($campaign->funnels()->exists() && ! $needsBoth) {
            $builder->buildWebinarFunnels($campaign, $offer);

            if (! $this->generationState->isGenerated($campaign, 'webinar')) {
                $this->generationState->mark($campaign, 'webinar');
            }

            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Webinar funnels refreshed from knowledge — add your video URL on the pitch room.']);

            return back();
        }

        if ($this->generationState->isGenerated($campaign, 'webinar') && ! $needsBoth) {
            $this->generationProgress->idle($campaign);
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Both webinar funnels exist — open Edit to customize registration and pitch room.']);

            return back();
        }

        return $this->dispatchGeneration($campaign, 'webinar', [], 'Queued — creating registration + pitch/replay funnels…');
    }

    public function generationStatus(Campaign $campaign): JsonResponse
    {
        $this->authorizeCampaign($campaign);

        $leadMagnet = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $this->generationState->syncFromContent($campaign);
        $this->reconcileGenerationProgress($campaign, $leadMagnet);

        $generation = $this->generationProgress->get($campaign);

        return response()->json($generation ?? ['status' => 'idle']);
    }

    public function publish(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $validated = $request->validate([
            'integration_account_id' => ['nullable', 'integer', 'exists:integration_accounts,id'],
            'esp_tag' => ['nullable', 'string', 'max:120'],
        ]);

        app(\App\Services\Campaigns\CampaignTrafficHubService::class)->ensureTrafficFunnel($campaign);

        $campaign->update([
            'status' => 'published',
            'published_at' => now(),
            'wizard_step' => 8,
        ]);

        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $campaign->funnels()->update(['status' => 'published', 'published_at' => now()]);
        } else {
            $this->leadCapture->ensureOptinFunnel($campaign->fresh());
        }

        $toastMessage = 'Campaign published.';

        if (! empty($validated['integration_account_id'])) {
            $account = IntegrationAccount::query()
                ->where('id', $validated['integration_account_id'])
                ->where('user_id', auth()->id())
                ->first();

            if ($account && $campaign->emails()->exists()) {
                UploadCampaignSequenceJob::dispatch(
                    $campaign->id,
                    $account->id,
                    $validated['esp_tag'] ?? null,
                );
                $toastMessage = 'Campaign published — uploading email swipes to '.$account->name.'…';
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $toastMessage]);

        return back();
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorizeCampaign($campaign);
        $campaign->delete();

        return to_route('campaigns.index');
    }

    public function downloadLeadMagnet(string $token): StreamedResponse
    {
        $lead = CampaignLead::query()->where('download_token', $token)->firstOrFail();
        $campaign = $lead->campaign;
        $lm = $campaign->pages()->where('page_type', 'lead_magnet')->first();
        $content = is_array($lm?->content) ? $lm->content : [];
        $htmlPath = $content['download_path'] ?? null;

        if (! is_string($htmlPath) || ! Storage::disk('public')->exists($htmlPath)) {
            abort(404, 'Lead magnet file not found.');
        }

        $pdfPath = $content['pdf_path'] ?? null;
        $pdfPath = $this->leadMagnetPdf->ensurePdf($htmlPath, is_string($pdfPath) ? $pdfPath : null);

        if (! is_string($pdfPath) || ! Storage::disk('public')->exists($pdfPath)) {
            abort(500, 'Could not generate PDF. Try again in a moment.');
        }

        if (($content['pdf_path'] ?? null) !== $pdfPath) {
            $content['pdf_path'] = $pdfPath;
            $lm?->update(['content' => $content]);
        }

        $lead->update(['downloaded_at' => now()]);

        $filename = Str::slug((string) ($content['title'] ?? $campaign->name)).'-guide.pdf';

        return Storage::disk('public')->download($pdfPath, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function authorizeCampaign(Campaign $campaign): void
    {
        abort_unless((int) $campaign->user_id === (int) auth()->id(), 403);
    }

    protected function repairThankYouDownloadUrl(Campaign $campaign, ?CampaignPage $leadMagnet): void
    {
        $thankyou = $campaign->pages->firstWhere('page_type', 'thankyou');
        if (! $thankyou) {
            return;
        }

        $content = is_array($thankyou->content) ? $thankyou->content : [];
        $current = (string) ($content['download_url'] ?? '');

        // Thank-you download is always resolved via ?dl= token on the public page — clear stale static URLs.
        if ($current !== '' && ! str_contains($current, 'campaign-download')) {
            unset($content['download_url']);
            $thankyou->update(['content' => $content]);
        }
    }

    protected function reconcileGenerationProgress(Campaign $campaign, ?CampaignPage $leadMagnet): void
    {
        if (! $this->generationProgress->isRunning($campaign)) {
            return;
        }

        $gen = $this->generationProgress->get($campaign) ?? [];
        $step = (string) ($gen['step'] ?? '');

        if ($this->stepGenerationAlreadyComplete($campaign, $leadMagnet, $step)
            || $this->generationState->isGenerated($campaign, $step)) {
            $this->generationProgress->idle($campaign);

            return;
        }

        $startedAt = $gen['started_at'] ?? null;
        if (is_string($startedAt) && $startedAt !== '') {
            $minutes = now()->diffInMinutes(Carbon::parse($startedAt));
            if ($minutes >= 8) {
                $this->generationProgress->fail(
                    $campaign,
                    'Generation timed out — queue may be stopped. Run Horizon or retry.'
                );
            }
        }
    }

    /** @deprecated Use reconcileGenerationProgress */
    protected function clearStaleGenerationState(Campaign $campaign, ?CampaignPage $leadMagnet): void
    {
        $this->reconcileGenerationProgress($campaign, $leadMagnet);
    }

    protected function stepGenerationAlreadyComplete(Campaign $campaign, ?CampaignPage $leadMagnet, string $step): bool
    {
        if ($this->generationState->isGenerated($campaign, $step)) {
            return true;
        }

        return match ($step) {
            'knowledge' => ($campaign->knowledge['status'] ?? '') === 'ready',
            'lead_magnet_suggest' => is_array($leadMagnet?->content['suggestions'] ?? null)
                && count($leadMagnet->content['suggestions']) > 0,
            'lead_magnet' => $this->leadMagnetReady($leadMagnet),
            'pages' => $this->funnelPagesReady($campaign),
            'bonuses', 'bonuses_suggest' => $campaign->bonuses()->exists(),
            'emails' => $campaign->emails()->exists(),
            'webinar' => $campaign->funnels()->count() >= 2
                || ($campaign->funnels()->exists() && $this->generationState->isGenerated($campaign, 'webinar')),
            'quick_start' => $this->generationState->isGenerated($campaign, 'emails')
                && $this->funnelPagesReady($campaign)
                && ($campaign->type !== Campaign::TYPE_WEBINAR || $campaign->funnels()->count() >= 2),
            default => false,
        };
    }

    protected function leadMagnetReady(?CampaignPage $leadMagnet): bool
    {
        $content = is_array($leadMagnet?->content) ? $leadMagnet->content : [];

        return ($content['generated'] ?? false) === true
            || ($content['status'] ?? '') === 'ready'
            || (! empty($content['pages']) && ! empty($content['download_path']));
    }

    protected function funnelPagesReady(Campaign $campaign): bool
    {
        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $page = $campaign->pages->firstWhere('page_type', 'squeeze');
            $c = is_array($page?->content) ? $page->content : [];

            return ! empty($c['headline']) || ! empty($c['subheadline']) || ! empty($c['title']);
        }

        foreach (['squeeze', 'thankyou'] as $type) {
            $page = $campaign->pages->firstWhere('page_type', $type);
            $c = is_array($page?->content) ? $page->content : [];
            if (empty($c['headline']) && empty($c['title']) && empty($c['subheadline'])) {
                return false;
            }
        }

        return true;
    }

    protected function uniqueCampaignSlug(int $userId, string $base): string
    {
        $slug = $base;
        $i = 1;
        while (Campaign::query()->where('user_id', $userId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $startExtra
     */
    protected function dispatchGeneration(Campaign $campaign, string $step, array $payload, string $startMessage, array $startExtra = []): RedirectResponse
    {
        if ($this->generationProgress->isRunning($campaign)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Generation already running — see progress below.',
            ]);

            return back();
        }

        $this->generationProgress->start($campaign, $step, $startMessage, $startExtra);

        RunCampaignGenerationJob::dispatch($campaign->id, $step, $payload);

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => 'Started in background — watch live progress below.',
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignPayload(Campaign $campaign, string $username, ?CampaignPage $leadMagnet): array
    {
        return [
            'id' => $campaign->id,
            'uuid' => $campaign->uuid,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'type' => $campaign->type,
            'status' => $campaign->status,
            'wizard_step' => $campaign->wizard_step,
            'offer_url' => $campaign->offer_url,
            'affiliate_link' => $campaign->affiliate_link,
            'marketplace' => $campaign->marketplace,
            'offer_data' => $campaign->offer_data,
            'analysis' => $campaign->analysis,
            'knowledge' => $campaign->knowledge,
            'bonus_suggestions' => $campaign->meta['bonus_suggestions'] ?? [],
            'bonus_type' => $campaign->meta['bonus_type'] ?? null,
            'esp_upload' => $campaign->meta['esp_upload'] ?? null,
            'quick_start' => (bool) ($campaign->meta['quick_start'] ?? false),
            'generation' => $campaign->meta['generation'] ?? null,
            'generation_state' => $campaign->generation_state ?? [],
            'pages' => $campaign->pages,
            'lead_magnet' => $leadMagnet?->content,
            'bonuses' => $campaign->bonuses,
            'emails' => $campaign->emails,
            'funnels' => $this->funnelsForPayload($campaign, $username),
            'tracked_links' => $campaign->trackedLinks->map(fn ($l) => [
                'id' => $l->id,
                'code' => $l->code,
                'label' => $l->label,
                'destination_url' => $l->destination_url,
                'click_count' => $l->click_count,
                'public_url' => $l->publicUrl(),
            ]),
            'public_pages' => $this->publicPagesForPayload($campaign, $username),
            'traffic_hub_url' => route('campaigns.traffic.index', $campaign),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function funnelsForPayload(Campaign $campaign, string $username): array
    {
        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $builder = app(CampaignBuilderService::class);
            $optin = $builder->primaryOptinFunnel($campaign);
            $pitch = $builder->primaryWebinarFunnel($campaign);
            $rows = [];

            if ($optin) {
                $rows[] = $this->funnelPayloadRow($optin, $username, $campaign, 'optin', 'Registration funnel');
            }
            if ($pitch) {
                $rows[] = $this->funnelPayloadRow($pitch, $username, $campaign, 'pitch', 'Pitch & replay room');
            }

            return $rows;
        }

        return $campaign->funnels->map(fn ($f) => $this->funnelPayloadRow($f, $username, $campaign))->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function funnelPayloadRow(
        \App\Models\Funnel $f,
        string $username,
        Campaign $campaign,
        ?string $role = null,
        ?string $roleLabel = null,
    ): array {
        $f->loadMissing('campaign:id,slug,type');
        $editUrl = route('funnels.edit', $f->id);
        if ($campaign->type === Campaign::TYPE_WEBINAR && ($role === 'pitch' || $role === null)) {
            $editUrl .= '?tab=webinar';
        }

        $optinUrl = $f->publicOptinUrl() ?? route('public.optin', ['username' => $username, 'slug' => $f->slug]);
        $webinarRoomUrl = route('public.webinar', ['username' => $username, 'slug' => $f->slug]);

        return [
            'id' => $f->id,
            'name' => $f->name,
            'slug' => $f->slug,
            'status' => $f->status,
            'role' => $role,
            'role_label' => $roleLabel,
            'edit_url' => $editUrl,
            'optin_url' => $role === 'pitch' ? null : $optinUrl,
            'webinar_room_url' => $role === 'optin' ? null : $webinarRoomUrl,
            'public_url' => $role === 'pitch' ? $webinarRoomUrl : $optinUrl,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function publicPagesForPayload(Campaign $campaign, string $username): array
    {
        $base = [
            'squeeze' => route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'squeeze']),
            'bonus' => route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'bonus']),
        ];

        if ($campaign->type === Campaign::TYPE_WEBINAR) {
            $squeeze = $campaign->pages->firstWhere('page_type', 'squeeze');
            $roomUrl = is_array($squeeze?->content) ? ($squeeze->content['webinar_room_url'] ?? null) : null;
            if (is_string($roomUrl) && $roomUrl !== '') {
                $base['webinar_room'] = $roomUrl;
            }

            $builder = app(CampaignBuilderService::class);
            $optin = $builder->primaryOptinFunnel($campaign);
            if ($optin) {
                $base['registration_funnel'] = route('public.optin', ['username' => $username, 'slug' => $optin->slug]);
            }

            $primary = $builder->primaryWebinarFunnel($campaign);
            if ($primary && ! isset($base['webinar_room'])) {
                $base['webinar_room'] = route('public.webinar', ['username' => $username, 'slug' => $primary->slug]);
            }

            return $base;
        }

        return [
            ...$base,
            'thankyou' => route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'thankyou']),
            'quiz' => route('public.campaign.page', ['username' => $username, 'slug' => $campaign->slug, 'page' => 'quiz']),
        ];
    }
}
