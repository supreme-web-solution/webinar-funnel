<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContentEmployeePlan;
use App\Models\CustomDomain;
use App\Services\Campaigns\CampaignKnowledgeContextService;
use App\Services\Campaigns\MarketplaceOfferSearchService;
use App\Services\Content\ContentEmployeePlanService;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Domains\CustomDomainVerificationService;
use App\Services\Traffic\UserTrafficProfileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Growth tools: domain mapping + AI Opportunity Finder.
 */
class GrowthToolsController extends Controller
{
    public function __construct(
        protected MarketplaceOfferSearchService $marketplaceSearch,
        protected CustomDomainVerificationService $domainVerification,
    ) {}

    public function domains(): Response
    {
        $userId = auth()->id();

        $domains = CustomDomain::query()
            ->where('user_id', $userId)
            ->with('campaign:id,name,slug,status')
            ->latest()
            ->get()
            ->map(fn (CustomDomain $domain) => [
                ...$domain->toArray(),
                'dns' => $this->domainVerification->instructions($domain),
            ]);

        $campaigns = Campaign::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'slug', 'status']);

        return Inertia::render('growth/Domains', [
            'domains' => $domains,
            'campaigns' => $campaigns,
            'app_host' => $this->domainVerification->appHost(),
        ]);
    }

    public function storeDomain(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:custom_domains,domain'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        if (! empty($validated['campaign_id'])) {
            abort_unless(
                Campaign::query()
                    ->where('id', $validated['campaign_id'])
                    ->where('user_id', $request->user()->id)
                    ->exists(),
                403
            );
        }

        $domain = strtolower(trim(preg_replace('#^https?://#', '', $validated['domain']) ?? $validated['domain']));
        $domain = rtrim($domain, '/');

        $record = CustomDomain::query()->create([
            'user_id' => $request->user()->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'domain' => $domain,
            'status' => 'pending',
            'meta' => ['source' => 'manual'],
        ]);

        $this->domainVerification->tokenFor($record);

        return back()->with('success', 'Domain added. Point DNS to this app, add the TXT record, then verify.');
    }

    public function verifyDomain(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        abort_unless((int) $customDomain->user_id === (int) $request->user()->id, 403);

        if ($customDomain->campaign_id === null) {
            return back()->with('error', 'Link this domain to a published campaign before verifying.');
        }

        if ($this->domainVerification->verify($customDomain)) {
            $this->domainVerification->markVerified($customDomain);

            return back()->with('success', 'Domain verified. Campaign pages are now live on https://'.$customDomain->domain);
        }

        $this->domainVerification->markFailed($customDomain, 'TXT record not found yet. DNS can take up to 24 hours to propagate.');

        return back()->with('error', 'Verification failed — TXT record not detected yet. Wait a few minutes and try again.');
    }

    public function updateDomain(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        abort_unless((int) $customDomain->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        if (! empty($validated['campaign_id'])) {
            abort_unless(
                Campaign::query()
                    ->where('id', $validated['campaign_id'])
                    ->where('user_id', $request->user()->id)
                    ->exists(),
                403
            );
        }

        $customDomain->update([
            'campaign_id' => $validated['campaign_id'] ?? null,
            'status' => $customDomain->status === 'active' && empty($validated['campaign_id']) ? 'pending' : $customDomain->status,
            'verified_at' => $customDomain->status === 'active' && empty($validated['campaign_id']) ? null : $customDomain->verified_at,
        ]);

        return back()->with('success', 'Domain mapping updated.');
    }

    public function destroyDomain(Request $request, CustomDomain $customDomain): RedirectResponse
    {
        abort_unless((int) $customDomain->user_id === (int) $request->user()->id, 403);

        $customDomain->delete();

        return back()->with('success', 'Domain removed.');
    }

    public function opportunities(Request $request): Response
    {
        $keyword = trim((string) $request->query('q', ''));
        $marketplace = $request->query('marketplace');

        $payload = $keyword !== ''
            ? $this->marketplaceSearch->search($keyword, is_string($marketplace) ? $marketplace : null)
            : $this->marketplaceSearch->trending();

        return Inertia::render('growth/Opportunities', [
            'keyword' => $keyword,
            'marketplace' => is_string($marketplace) ? $marketplace : '',
            'results' => $payload['results'] ?? [],
            'top_pick' => $payload['top_pick'] ?? null,
            'error' => $payload['error'] ?? null,
            'sources' => $payload['sources'] ?? [],
            'search_links' => $payload['search_links'] ?? [],
            'refreshed_at' => $payload['refreshed_at'] ?? null,
            'integrations' => [
                'apify_configured' => (bool) config('services.apify.api_token'),
                'clickbank_live' => (bool) config('services.apify.api_token') && config('services.marketplace.clickbank_apify_enabled', true),
                'scrapingbee_configured' => (bool) config('services.scrapingbee.api_key'),
            ],
        ]);
    }

    public function searchOpportunities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:120'],
            'marketplace' => ['nullable', 'string', 'in:clickbank,jvzoo,warriorplus,digistore24'],
        ]);

        return response()->json(
            $this->marketplaceSearch->search(
                $validated['keyword'],
                $validated['marketplace'] ?? null,
            )
        );
    }

    public function contentEmployee(
        Request $request,
        UserTrafficProfileService $profileService,
        ContentEmployeePlanService $planService,
        PlatformFormatCatalog $formatCatalog,
        CampaignKnowledgeContextService $knowledgeContext,
    ): Response {
        $user = $request->user();
        $weekStart = Carbon::now()->startOfWeek();

        $campaigns = Campaign::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get(['id', 'name', 'type', 'status', 'offer_data', 'knowledge'])
            ->map(fn (Campaign $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'status' => $c->status,
                'product_name' => $c->offer_data['product_name'] ?? null,
                'has_knowledge' => $knowledgeContext->hasKnowledge($c),
            ]);

        $plans = ContentEmployeePlan::query()
            ->where('user_id', $user->id)
            ->where('week_start', '>=', $weekStart->copy()->subWeeks(2)->toDateString())
            ->with('items')
            ->orderByDesc('week_start')
            ->limit(6)
            ->get()
            ->map(fn (ContentEmployeePlan $plan): array => $planService->planPayload($plan));

        $profilePayload = $profileService->payloadForUser($user);

        return Inertia::render('growth/ContentEmployee', [
            'week_start' => $weekStart->toDateString(),
            'campaigns' => $campaigns,
            'plans' => $plans,
            'profile' => $profilePayload,
            'format_count' => count($formatCatalog->allFormatKeys()),
            'routes' => [
                'generate_plan' => route('growth.content-employee.plans.generate'),
                'traffic_setup' => route('traffic.index'),
                'social_settings' => route('settings.social-traffic.edit'),
                'profile_update' => route('traffic.profile.update'),
            ],
        ]);
    }
}
