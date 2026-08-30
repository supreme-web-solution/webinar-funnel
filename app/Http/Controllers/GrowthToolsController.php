<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Services\Campaigns\MarketplaceOfferSearchService;
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
    ) {}
    public function domains(): Response
    {
        $domains = CustomDomain::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return Inertia::render('growth/Domains', [
            'domains' => $domains,
            'phase' => 2,
            'note' => 'Domain mapping is scaffolded for Phase 2. Add a domain to reserve it; DNS verification ships next.',
        ]);
    }

    public function storeDomain(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'unique:custom_domains,domain'],
            'campaign_id' => ['nullable', 'integer', 'exists:campaigns,id'],
        ]);

        CustomDomain::query()->create([
            'user_id' => $request->user()->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'domain' => strtolower(trim($validated['domain'])),
            'status' => 'pending',
            'meta' => ['source' => 'manual'],
        ]);

        return back()->with('success', 'Domain reserved (pending verification).');
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

    public function contentEmployee(): Response
    {
        return Inertia::render('growth/ContentEmployee', [
            'phase' => 2,
            'links' => [
                ['label' => 'Promo Calendar', 'href' => '/promotion/calendar'],
                ['label' => 'Funnel Traffic Settings', 'href' => '/funnels'],
                ['label' => 'Integrations', 'href' => '/integrations'],
            ],
            'note' => 'Phase 2 expands existing Promotion + Traffic AI into a unified Content Employee. Use the links below meanwhile.',
        ]);
    }
}
