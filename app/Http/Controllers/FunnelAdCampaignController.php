<?php

namespace App\Http\Controllers;

use App\Jobs\LaunchAdCampaignJob;
use App\Jobs\SyncAdPerformanceJob;
use App\Models\Funnel;
use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;
use App\Services\Ads\AdBudgetRules;
use App\Services\Ads\AdCampaignService;
use App\Services\Ads\AdLaunchErrorFormatter;
use App\Services\Ads\AdPlatformRules;
use App\Services\Ads\AdGenerationService;
use App\Services\Zernio\ZernioClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FunnelAdCampaignController extends Controller
{
    public function __construct(
        private readonly AdGenerationService $generator,
        private readonly AdCampaignService   $campaignService,
        private readonly ZernioClient        $zernio,
    ) {}

    // ─── Page ────────────────────────────────────────────────────────────────

    public function index(Request $request, Funnel $funnel): Response
    {
        $this->authorize($funnel);

        $campaigns = FunnelAdCampaign::query()
            ->where('funnel_id', $funnel->id)
            ->with(['creatives' => fn ($q) => $q->orderByDesc('created_at')])
            ->latest()
            ->get();

        $funnel->loadMissing('user');

        return Inertia::render('funnels/ads/Campaigns', [
            'funnel'           => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'status' => $funnel->status,
                'default_destination_url' => $funnel->publicOptinUrl(),
            ],
            'campaigns'        => $campaigns->map(fn ($c) => $this->campaignResource($c))->values(),
            'adPlatforms'      => FunnelAdCampaign::AD_PLATFORMS,
            'launchableAdPlatforms' => FunnelAdCampaign::launchableAdPlatforms(),
            'unsupportedAdPlatforms' => FunnelAdCampaign::unsupportedAdPlatforms(),
            'adGoals'          => FunnelAdCampaign::GOALS,
            'ctaButtons'       => FunnelAdCreative::CTA_BUTTONS,
            'adsEnabled'       => $this->zernio->isConfigured(),
            'routes'           => [
                'store'   => route('funnels.ads.store', $funnel),
                'posts'   => route('funnels.promotion.posts.index', $funnel),
            ],
            'savedAdAccountIds' => $request->user()->resolvedPlatformAdAccountIds(),
            'adAccountsSettingsUrl' => route('settings.ad-accounts.edit'),
            'minBudgetAmount' => AdBudgetRules::minAmount('USD'),
            'minBudgetByCurrency' => config('promotion.ads.min_budget_by_currency', []),
            'budgetCurrencies' => AdBudgetRules::supportedCurrencies(),
            'defaultBudgetCurrency' => AdBudgetRules::normalizeCurrency(null),
        ]);
    }

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function store(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorize($funnel);

        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:120'],
            'goal'             => ['required', 'string', 'in:'.implode(',', array_keys(FunnelAdCampaign::GOALS))],
            'platforms'        => ['required', 'array', 'min:1'],
            'platforms.*'      => ['string', 'in:'.implode(',', array_keys(FunnelAdCampaign::AD_PLATFORMS))],
            'platform_ad_account_ids' => ['required', 'array'],
            'platform_ad_account_ids.*' => ['nullable', 'string', 'max:120'],
            'zernio_social_account_id' => ['nullable', 'string', 'max:120'],
            'budget_amount'    => ['required', 'numeric', 'min:0.01'],
            'budget_currency'  => ['nullable', 'string', 'size:3'],
            'budget_type'      => ['required', 'in:daily,lifetime'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'product_url'      => ['nullable', 'url', 'max:2048'],
            'industry'         => ['nullable', 'string', 'max:120'],
            'goal_description' => ['nullable', 'string', 'max:500'],
            'targeting'        => ['nullable', 'array'],
            'meta_pixel_id'    => ['nullable', 'string', 'max:120'],
            'meta_conversion_event' => ['nullable', 'string', 'max:60'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $validatedIds = is_array($validated['platform_ad_account_ids'] ?? null) ? $validated['platform_ad_account_ids'] : [];
        $savedDefaults = $user->resolvedPlatformAdAccountIds();
        $platformIds = [];
        $missing = [];
        foreach ($validated['platforms'] as $platform) {
            $id = trim((string) ($validatedIds[$platform] ?? $savedDefaults[$platform] ?? ''));
            if ($id === '') {
                $missing[] = $platform;
            } else {
                $platformIds[$platform] = $id;
            }
        }
        if ($missing !== []) {
            return back()->withErrors([
                'platform_ad_account_ids' => 'Provide an ad account ID for each selected platform: '.implode(', ', $missing).'. You can save defaults under Settings → Ad accounts.',
            ]);
        }

        $currency = AdBudgetRules::normalizeCurrency($validated['budget_currency'] ?? null);
        $budgetAmount = (float) $validated['budget_amount'];
        if (! AdBudgetRules::isValid($budgetAmount, $currency)) {
            $minimum = AdBudgetRules::formatAmount(AdBudgetRules::minAmount($currency), $currency);

            return back()->withErrors([
                'budget_amount' => "Daily budget must be at least {$minimum} in {$currency} (your ad account billing currency).",
            ]);
        }

        $campaign = FunnelAdCampaign::create(array_merge($validated, [
            'funnel_id'            => $funnel->id,
            'user_id'              => $user->id,
            'status'               => FunnelAdCampaign::STATUS_DRAFT,
            'platform_ad_account_ids' => $platformIds,
            'budget_currency'      => $currency,
        ]));

        $user->savePlatformAdAccountIds($platformIds);

        return redirect()->route('funnels.ads.index', $funnel)
            ->with('success', 'Campaign created. Now add creatives.');
    }

    public function update(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): RedirectResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        if (in_array($campaign->status, [FunnelAdCampaign::STATUS_ACTIVE, FunnelAdCampaign::STATUS_LAUNCHING], true)) {
            return back()->with('error', 'Cannot edit a campaign while it is running or launching.');
        }

        $validated = $request->validate([
            'name'             => ['sometimes', 'string', 'max:120'],
            'goal'             => ['sometimes', 'string', 'in:'.implode(',', array_keys(FunnelAdCampaign::GOALS))],
            'platforms'        => ['sometimes', 'array'],
            'platform_ad_account_ids' => ['sometimes', 'array'],
            'platform_ad_account_ids.*' => ['nullable', 'string', 'max:120'],
            'zernio_social_account_id' => ['sometimes', 'nullable', 'string', 'max:120'],
            'budget_amount'    => ['sometimes', 'numeric', 'min:0.01'],
            'budget_currency'  => ['sometimes', 'nullable', 'string', 'size:3'],
            'budget_type'      => ['sometimes', 'in:daily,lifetime'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date'],
            'product_url'      => ['nullable', 'url', 'max:2048'],
            'industry'         => ['nullable', 'string', 'max:120'],
            'goal_description' => ['nullable', 'string', 'max:500'],
            'targeting'        => ['nullable', 'array'],
            'meta_pixel_id'    => ['nullable', 'string', 'max:120'],
            'meta_conversion_event' => ['nullable', 'string', 'max:60'],
            'status'           => ['sometimes', 'string', 'in:draft,ready,paused'],
        ]);

        $platforms = is_array($validated['platforms'] ?? null) ? $validated['platforms'] : ($campaign->platforms ?? []);
        $incomingIds = is_array($validated['platform_ad_account_ids'] ?? null) ? $validated['platform_ad_account_ids'] : [];
        $existingIds = is_array($campaign->platform_ad_account_ids ?? null) ? $campaign->platform_ad_account_ids : [];
        $mergedIds = array_merge($existingIds, $incomingIds);

        $sanitisedIds = [];
        $missing = [];
        foreach ($platforms as $platform) {
            $id = trim((string) ($mergedIds[$platform] ?? ''));
            if ($id === '') {
                $missing[] = $platform;
            } else {
                $sanitisedIds[$platform] = $id;
            }
        }
        if ($missing !== []) {
            return back()->withErrors([
                'platform_ad_account_ids' => 'Provide an ad account ID for each selected platform: '.implode(', ', $missing).'.',
            ]);
        }

        $validated['platform_ad_account_ids'] = $sanitisedIds;

        $currency = AdBudgetRules::normalizeCurrency(
            $validated['budget_currency'] ?? $campaign->budget_currency
        );
        $budgetAmount = isset($validated['budget_amount'])
            ? (float) $validated['budget_amount']
            : (float) $campaign->budget_amount;

        if (! AdBudgetRules::isValid($budgetAmount, $currency)) {
            $minimum = AdBudgetRules::formatAmount(AdBudgetRules::minAmount($currency), $currency);

            return back()->withErrors([
                'budget_amount' => "Daily budget must be at least {$minimum} in {$currency} (your ad account billing currency).",
            ]);
        }

        $validated['budget_currency'] = $currency;

        if ($campaign->status === FunnelAdCampaign::STATUS_FAILED) {
            $validated['last_error'] = null;
            $validated['launch_errors'] = null;
            $validated['status'] = $campaign->creatives()->where('status', FunnelAdCreative::STATUS_DRAFT)->exists()
                ? FunnelAdCampaign::STATUS_READY
                : FunnelAdCampaign::STATUS_DRAFT;
        }

        $campaign->update($validated);

        if ($sanitisedIds !== []) {
            $request->user()->savePlatformAdAccountIds($sanitisedIds);
        }

        return back()->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): RedirectResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $campaign->creatives()->delete();
        $campaign->delete();

        return back()->with('success', 'Campaign deleted.');
    }

    public function duplicate(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): RedirectResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $copy = $campaign->replicate([
            'zernio_campaign_id',
            'performance',
            'last_synced_at',
            'last_error',
            'launch_errors',
        ]);
        $copy->name = $campaign->name.' (copy)';
        $copy->status = $campaign->creatives()->exists()
            ? FunnelAdCampaign::STATUS_READY
            : FunnelAdCampaign::STATUS_DRAFT;
        $copy->user_id = $user->id;
        $copy->funnel_id = $funnel->id;
        $copy->save();

        foreach ($campaign->creatives as $creative) {
            $newCreative = $creative->replicate([
                'zernio_ad_id',
                'zernio_post_id',
                'performance',
                'is_winner',
            ]);
            $newCreative->campaign_id = $copy->id;
            $newCreative->funnel_id = $funnel->id;
            $newCreative->user_id = $user->id;
            $newCreative->status = FunnelAdCreative::STATUS_DRAFT;
            $newCreative->save();
        }

        return redirect()->route('funnels.ads.index', $funnel)
            ->with('success', 'Campaign duplicated. Edit settings or regenerate creatives, then launch.')
            ->with('duplicated_campaign_id', $copy->id);
    }

    // ─── AI Research & Generation ────────────────────────────────────────────

    public function research(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $campaign->update(['status' => FunnelAdCampaign::STATUS_GENERATING]);

        $research = $this->generator->research($campaign, $funnel);
        $campaign->update(['ai_research' => $research, 'status' => FunnelAdCampaign::STATUS_DRAFT]);

        return response()->json(['research' => $research]);
    }

    public function generateCreatives(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $validated = $request->validate([
            'hooks'          => ['required', 'array', 'min:1', 'max:5'],
            'hooks.*'        => ['string', 'max:255'],
            'generate_images' => ['boolean'],
            'format'         => ['nullable', 'string', 'in:square,story,landscape,reel'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $maxCreatives = max(1, (int) config('promotion.ads.max_generated_creatives', 5));
        $hooks    = array_values(array_slice($validated['hooks'], 0, $maxCreatives));
        $format   = $validated['format'] ?? 'square';
        $genImages = (bool) ($validated['generate_images'] ?? true);
        $variants = array_values(array_slice(
            $this->generator->generateCopyVariants($campaign, $funnel, $hooks),
            0,
            $maxCreatives
        ));

        $creatives = [];
        foreach ($variants as $i => $variant) {
            $imageUrl = null;
            if ($genImages) {
                $imageUrl = $this->generator->generateAdImage(
                    $campaign,
                    $hooks[$i] ?? $hooks[0],
                    $variant['headline'] ?? '',
                    $format
                );
            }

            $creative = FunnelAdCreative::create([
                'campaign_id'  => $campaign->id,
                'funnel_id'    => $funnel->id,
                'user_id'      => $user->id,
                'headline'     => $variant['headline'] ?? '',
                'primary_text' => $variant['primary_text'] ?? '',
                'description'  => $variant['description'] ?? '',
                'cta_button'   => $variant['cta_button'] ?? 'LEARN_MORE',
                'asset_url'    => $imageUrl,
                'asset_type'   => $imageUrl ? 'image' : null,
                'format'       => $format,
                'status'       => FunnelAdCreative::STATUS_DRAFT,
            ]);

            $creatives[] = $creative->toArray();
        }

        $campaign->update(['status' => FunnelAdCampaign::STATUS_READY]);

        return response()->json(['creatives' => $creatives]);
    }

    public function generateImage(Request $request, Funnel $funnel, FunnelAdCampaign $campaign, FunnelAdCreative $creative): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $validated = $request->validate([
            'format' => ['nullable', 'string', 'in:square,story,landscape,reel'],
        ]);

        $format   = $validated['format'] ?? $creative->format ?? 'square';
        $imageUrl = $this->generator->generateAdImage(
            $campaign,
            $creative->headline ?? '',
            $creative->headline ?? '',
            $format
        );

        if ($imageUrl) {
            $creative->update(['asset_url' => $imageUrl, 'asset_type' => 'image', 'format' => $format]);
        }

        return response()->json(['asset_url' => $imageUrl]);
    }

    // ─── Creative CRUD ───────────────────────────────────────────────────────

    public function storeCreative(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $validated = $request->validate([
            'headline'     => ['nullable', 'string', 'max:255'],
            'primary_text' => ['nullable', 'string', 'max:2000'],
            'description'  => ['nullable', 'string', 'max:255'],
            'cta_button'   => ['nullable', 'string', 'in:'.implode(',', array_keys(FunnelAdCreative::CTA_BUTTONS))],
            'format'       => ['nullable', 'string', 'in:square,story,landscape,reel'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $creative = FunnelAdCreative::create(array_merge($validated, [
            'campaign_id' => $campaign->id,
            'funnel_id'   => $funnel->id,
            'user_id'     => $user->id,
            'status'      => FunnelAdCreative::STATUS_DRAFT,
        ]));

        return response()->json(['creative' => $creative->toArray()], 201);
    }

    public function updateCreative(Request $request, Funnel $funnel, FunnelAdCampaign $campaign, FunnelAdCreative $creative): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        $validated = $request->validate([
            'headline'     => ['nullable', 'string', 'max:255'],
            'primary_text' => ['nullable', 'string', 'max:2000'],
            'description'  => ['nullable', 'string', 'max:255'],
            'cta_button'   => ['nullable', 'string', 'in:'.implode(',', array_keys(FunnelAdCreative::CTA_BUTTONS))],
            'format'       => ['nullable', 'string', 'in:square,story,landscape,reel'],
            'status'       => ['nullable', 'string', 'in:draft,active,paused'],
        ]);

        $creative->update($validated);

        return response()->json(['creative' => $creative->fresh()->toArray()]);
    }

    public function destroyCreative(Request $request, Funnel $funnel, FunnelAdCampaign $campaign, FunnelAdCreative $creative): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);
        $creative->delete();

        return response()->json(['success' => true]);
    }

    // ─── Launch & Sync ───────────────────────────────────────────────────────

    public function launch(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): RedirectResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        if (! $this->zernio->isConfigured()) {
            return back()->with('error', 'Zernio API is not configured. Add your API key in settings.');
        }

        if ($campaign->creatives()->where('status', FunnelAdCreative::STATUS_DRAFT)->count() === 0) {
            return back()->with('error', 'No draft creatives to launch.');
        }

        $platforms = is_array($campaign->platforms) ? $campaign->platforms : [];
        $platformIds = is_array($campaign->platform_ad_account_ids) ? $campaign->platform_ad_account_ids : [];
        $missing = [];
        foreach ($platforms as $platform) {
            if (trim((string) ($platformIds[$platform] ?? '')) === '') {
                $missing[] = $platform;
            }
        }
        if ($missing !== []) {
            return back()->with('error', 'Missing ad account IDs for: '.implode(', ', $missing).'.');
        }

        if ($this->campaignService->resolveDestinationUrl($campaign) === null) {
            return back()->with('error', 'A destination link is required. Add a product URL in the campaign, or publish your funnel so it has a public opt-in page.');
        }

        $currency = AdBudgetRules::normalizeCurrency($campaign->budget_currency);
        if (! AdBudgetRules::isValid((float) $campaign->budget_amount, $currency)) {
            $minimum = AdBudgetRules::formatAmount(AdBudgetRules::minAmount($currency), $currency);

            return back()->with('error', "Daily budget must be at least {$minimum} in {$currency} before launching.");
        }

        if ($campaign->goal === FunnelAdCampaign::GOAL_CONVERSIONS) {
            $pixelId = trim((string) ($campaign->meta_pixel_id ?? config('promotion.ads.default_meta_pixel_id', '')));
            if ($pixelId === '') {
                return back()->with('error', 'Conversions campaigns need a Meta/TikTok Pixel ID. Edit the campaign to add one, or switch the goal to Drive Traffic.');
            }
        }

        $platforms = is_array($campaign->platforms) ? $campaign->platforms : [];
        $launchable = AdPlatformRules::launchableFromSelection($platforms);
        if ($launchable === []) {
            return back()->with('error', 'No launchable platforms selected. Supported: Facebook, Instagram, TikTok, Google, X, LinkedIn, Pinterest.');
        }

        if ($campaign->budget_type === 'lifetime' && $campaign->end_date === null) {
            return back()->with('error', 'Lifetime budgets require an end date.');
        }

        $campaign->update(['status' => FunnelAdCampaign::STATUS_LAUNCHING, 'last_error' => null, 'launch_errors' => null]);

        LaunchAdCampaignJob::dispatch($campaign->id);

        return back()->with('success', 'Campaign is launching. This page will update automatically.');
    }

    public function syncPerformance(Request $request, Funnel $funnel, FunnelAdCampaign $campaign): RedirectResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        SyncAdPerformanceJob::dispatch($campaign->id);

        return back()->with('success', 'Performance sync queued.');
    }

    public function toggleCreativeStatus(Request $request, Funnel $funnel, FunnelAdCampaign $campaign, FunnelAdCreative $creative): JsonResponse
    {
        $this->authorize($funnel);
        $this->authorizeOwns($campaign);

        if ($creative->status === FunnelAdCreative::STATUS_ACTIVE) {
            $this->campaignService->pauseCreative($creative);
        } elseif ($creative->status === FunnelAdCreative::STATUS_PAUSED) {
            $this->campaignService->resumeCreative($creative);
        }

        return response()->json(['creative' => $creative->fresh()->toArray()]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function campaignResource(FunnelAdCampaign $campaign): array
    {
        return [
            'id'                   => $campaign->id,
            'name'                 => $campaign->name,
            'goal'                 => $campaign->goal,
            'platforms'            => $campaign->platforms,
            'status'               => $campaign->status,
            'budget_amount'        => $campaign->budget_amount,
            'budget_type'          => $campaign->budget_type,
            'budget_currency'      => AdBudgetRules::normalizeCurrency($campaign->budget_currency),
            'start_date'           => $campaign->start_date?->toDateString(),
            'end_date'             => $campaign->end_date?->toDateString(),
            'product_url'          => $campaign->product_url,
            'industry'             => $campaign->industry,
            'goal_description'     => $campaign->goal_description,
            'targeting'            => $campaign->targeting,
            'platform_ad_account_ids' => $campaign->platform_ad_account_ids,
            'zernio_social_account_id' => $campaign->zernio_social_account_id,
            'meta_pixel_id'          => $campaign->meta_pixel_id,
            'meta_conversion_event'  => $campaign->meta_conversion_event,
            'ai_research'          => $campaign->ai_research,
            'performance'          => $campaign->performance,
            'last_synced_at'       => $campaign->last_synced_at?->toISOString(),
            'last_error'           => $campaign->last_error,
            'launch_errors'        => $this->resolvedLaunchErrors($campaign->launch_errors),
            'created_at'           => $campaign->created_at?->toISOString(),
            'creatives'            => $campaign->creatives->map(fn ($c) => [
                'id'           => $c->id,
                'headline'     => $c->headline,
                'primary_text' => $c->primary_text,
                'description'  => $c->description,
                'cta_button'   => $c->cta_button,
                'asset_url'    => $c->asset_url,
                'asset_type'   => $c->asset_type,
                'format'       => $c->format,
                'status'       => $c->status,
                'is_winner'    => $c->is_winner,
                'performance'  => $c->performance,
                'zernio_ad_id' => $c->zernio_ad_id,
                'platform_ad_ids' => $c->platform_ad_ids,
            ])->values()->all(),
        ];
    }

    private function authorize(Funnel $funnel): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        abort_unless((int) $user->id === (int) $funnel->user_id, 403);
    }

    private function authorizeOwns(FunnelAdCampaign $campaign): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        abort_unless((int) $user->id === (int) $campaign->user_id, 403);
    }

    /**
     * Re-format stored launch errors when raw API messages are available (e.g. after formatter fixes).
     *
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>|null
     */
    private function resolvedLaunchErrors(?array $stored): ?array
    {
        if (! is_array($stored)) {
            return null;
        }

        $items = $stored['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return $stored;
        }

        $failures = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $raw = trim((string) ($item['raw'] ?? ''));
            if ($raw === '') {
                return $stored;
            }
            $failures[] = [
                'headline' => $item['headline'] ?? null,
                'raw' => $raw,
            ];
        }

        if ($failures === []) {
            return $stored;
        }

        return AdLaunchErrorFormatter::summarizeFailures($failures);
    }
}
