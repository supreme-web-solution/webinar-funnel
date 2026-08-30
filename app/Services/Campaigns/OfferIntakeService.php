<?php

namespace App\Services\Campaigns;

use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Str;

class OfferIntakeService
{
    public function __construct(
        protected OpenRouterService $openRouter,
        protected SalesPageFetcherService $pageFetcher,
    ) {}

    /**
     * @return array{ok: bool, offer: array<string, mixed>|null, error: string|null, fetch_method?: string|null}
     */
    public function extractFromUrl(string $url): array
    {
        $url = trim($url);
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'offer' => null, 'error' => 'Invalid URL.'];
        }

        $fetch = $this->pageFetcher->fetch($url);
        $pageText = $fetch['text'] ?? null;
        $fetchMethod = $fetch['method'] ?? null;

        if (! $fetch['ok'] || ! is_string($pageText) || strlen($pageText) < SalesPageFetcherService::MIN_TEXT_LENGTH) {
            return $this->extractFromUrlOnly($url, 'Page fetch returned little text — used AI URL inference. Review fields.');
        }

        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'You extract affiliate offer details from sales page text. Return ONLY JSON with keys: product_name, headline, subheadline, price, vendor_name, description (3-5 sentences), funnel_details, guarantee, marketplace (jvzoo|warriorplus|clickbank|other|unknown), target_audience, key_benefits (string array 5+), objections (string array), proof_elements (string array), niche, commission_hint.',
            ],
            [
                'role' => 'user',
                'content' => "Sales page URL: {$url}\n\nPage text (truncated):\n".Str::limit($pageText, 12000),
            ],
        ]);

        if (! $json['ok'] || ! is_array($json['data'])) {
            $offer = $this->fallbackOfferFromUrl($url);
            $offer['description'] = Str::limit($pageText, 800);
            $offer['page_excerpt'] = Str::limit($pageText, 2000);
            $offer['extraction_quality'] = 'partial';
            $offer['fetch_method'] = $fetchMethod;

            return [
                'ok' => true,
                'offer' => $offer,
                'error' => $json['error'] ?? 'AI extract failed; used scraped page text fallback.',
                'fetch_method' => $fetchMethod,
            ];
        }

        $data = $json['data'];
        $offer = [
            'product_name' => (string) ($data['product_name'] ?? 'Untitled Offer'),
            'headline' => (string) ($data['headline'] ?? ''),
            'subheadline' => (string) ($data['subheadline'] ?? ''),
            'price' => (string) ($data['price'] ?? ''),
            'vendor_name' => (string) ($data['vendor_name'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'funnel_details' => (string) ($data['funnel_details'] ?? ''),
            'guarantee' => (string) ($data['guarantee'] ?? ''),
            'marketplace' => (string) ($data['marketplace'] ?? 'unknown'),
            'target_audience' => (string) ($data['target_audience'] ?? ''),
            'key_benefits' => $data['key_benefits'] ?? [],
            'objections' => $data['objections'] ?? [],
            'proof_elements' => $data['proof_elements'] ?? [],
            'niche' => (string) ($data['niche'] ?? ''),
            'commission_hint' => (string) ($data['commission_hint'] ?? ''),
            'page_excerpt' => Str::limit($pageText, 2000),
            'extraction_quality' => 'full',
            'fetch_method' => $fetchMethod,
            'source_url' => $url,
        ];

        return ['ok' => true, 'offer' => $offer, 'error' => null, 'fetch_method' => $fetchMethod];
    }

    /**
     * Last resort when scraping fails: ask AI to infer from URL/path/domain.
     *
     * @return array{ok: bool, offer: array<string, mixed>|null, error: string|null, fetch_method?: null}
     */
    protected function extractFromUrlOnly(string $url, string $warning): array
    {
        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'You help affiliates set up offers when the page could not be scraped. From the URL alone (domain, path, marketplace patterns), infer likely offer fields. Return ONLY JSON with keys: product_name, headline, subheadline, price, vendor_name, description, funnel_details, guarantee, marketplace (jvzoo|warriorplus|clickbank|other|unknown). Be conservative; use empty strings if unknown.',
            ],
            [
                'role' => 'user',
                'content' => "Sales page URL (content unavailable): {$url}",
            ],
        ]);

        if ($json['ok'] && is_array($json['data'])) {
            $data = $json['data'];

            return [
                'ok' => true,
                'offer' => [
                    'product_name' => (string) ($data['product_name'] ?? $this->fallbackOfferFromUrl($url)['product_name']),
                    'headline' => (string) ($data['headline'] ?? ''),
                    'subheadline' => (string) ($data['subheadline'] ?? ''),
                    'price' => (string) ($data['price'] ?? ''),
                    'vendor_name' => (string) ($data['vendor_name'] ?? ''),
                    'description' => (string) ($data['description'] ?? ''),
                    'funnel_details' => (string) ($data['funnel_details'] ?? ''),
                    'guarantee' => (string) ($data['guarantee'] ?? ''),
                    'marketplace' => (string) ($data['marketplace'] ?? 'unknown'),
                    'source_url' => $url,
                ],
                'error' => $warning,
                'fetch_method' => null,
            ];
        }

        return [
            'ok' => true,
            'offer' => $this->fallbackOfferFromUrl($url),
            'error' => $warning.' Add OPENROUTER_API_KEY for smarter inference, or paste details manually.',
            'fetch_method' => null,
        ];
    }

    /**
     * Keyword search across marketplaces (shared with Opportunity Finder).
     *
     * @return array{ok: bool, results: array<int, array<string, mixed>>, error: string|null, sources?: array<int, string>}
     */
    public function searchMarketplaces(string $keyword, ?string $marketplace = null): array
    {
        return app(MarketplaceOfferSearchService::class)->search($keyword, $marketplace);
    }

    /**
     * @return array{ok: bool, analysis: array<string, mixed>|null, error: string|null}
     */
    public function analyseOffer(array $offer): array
    {
        $json = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'You are an affiliate strategist. Return ONLY JSON with keys: offer_score (0-100), summary, angles (string[]), objections (string[]), audience, recommended_funnel (sales|webinar), email_hooks (string[]).',
            ],
            [
                'role' => 'user',
                'content' => json_encode($offer, JSON_PRETTY_PRINT),
            ],
        ]);

        if ($json['ok'] && is_array($json['data'])) {
            return ['ok' => true, 'analysis' => $json['data'], 'error' => null];
        }

        return [
            'ok' => true,
            'analysis' => [
                'offer_score' => 70,
                'summary' => 'Baseline analysis (AI unavailable). Review offer claims and build a lead magnet that solves the top pain point.',
                'angles' => ['Problem/agitation', 'Bonus stack', 'Social proof'],
                'objections' => ['Price', 'Trust', 'Time'],
                'audience' => 'Affiliate marketers and digital product buyers',
                'recommended_funnel' => 'sales',
                'email_hooks' => ['Curiosity teaser', 'Case study', 'Deadline'],
            ],
            'error' => $json['error'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fallbackOfferFromUrl(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'offer';

        return [
            'product_name' => Str::title(str_replace(['www.', '-', '_'], ['', ' ', ' '], (string) $host)),
            'headline' => '',
            'subheadline' => '',
            'price' => '',
            'vendor_name' => '',
            'description' => '',
            'funnel_details' => '',
            'guarantee' => '',
            'marketplace' => 'unknown',
            'source_url' => $url,
        ];
    }
}
