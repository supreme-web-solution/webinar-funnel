<?php

namespace App\Services\Campaigns;

use App\Services\ApifyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketplaceOfferSearchService
{
    protected ?string $lastApifyError = null;

    public function __construct(
        protected ApifyService $apify,
        protected MarketplaceHtmlSearchService $htmlSearch,
        protected OfferScoringService $scoring,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     results: array<int, array<string, mixed>>,
     *     search_links: array<int, array<string, mixed>>,
     *     error: string|null,
     *     sources: array<int, string>
     * }
     */
    public function search(string $keyword, ?string $marketplace = null): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['ok' => false, 'results' => [], 'search_links' => [], 'error' => 'Enter a keyword.', 'sources' => [], 'top_pick' => null];
        }

        $results = [];
        $sources = [];
        $errors = [];

        if ($this->shouldSearchClickBank($marketplace)) {
            $clickbank = $this->searchClickBankViaApify($keyword);
            if ($clickbank !== []) {
                $results = [...$results, ...$clickbank];
                $sources[] = 'clickbank_apify';
            } else {
                $cached = $this->searchTrendingCache($keyword, 'clickbank');
                if ($cached !== []) {
                    $results = $this->mergeResults($results, $cached);
                    $sources[] = 'clickbank_cache';
                } elseif ($this->lastApifyError) {
                    $errors[] = 'ClickBank live search unavailable — showing cached or other marketplace results. '.$this->lastApifyError;
                }
            }
        }

        if ($this->shouldSearchJvzoo($marketplace)) {
            $jvzoo = $this->htmlSearch->searchJvzoo($keyword);
            if ($jvzoo !== []) {
                $results = $this->mergeResults($results, $jvzoo);
                $sources[] = 'jvzoo_html';
            }
        }

        if ($this->shouldSearchWarriorPlus($marketplace)) {
            $wp = $this->htmlSearch->searchWarriorPlus($keyword);
            if ($wp !== []) {
                $results = $this->mergeResults($results, $wp);
                $sources[] = 'warriorplus_html';
            }
        }

        $searchLinks = $this->marketplaceSearchLinks($keyword, $marketplace);

        if ($results === []) {
            $error = $errors !== []
                ? implode(' ', $errors)
                : 'No live listings found for this keyword. Open a marketplace below and pick an offer manually.';

            return [
                'ok' => false,
                'results' => [],
                'search_links' => $searchLinks,
                'error' => $error,
                'sources' => array_values(array_unique($sources)),
                'top_pick' => null,
            ];
        }

        $scored = $this->scoring->scoreAndRank($results);

        return [
            'ok' => true,
            'results' => array_slice($scored['results'], 0, 20),
            'search_links' => $searchLinks,
            'error' => $errors !== [] ? implode(' ', $errors) : null,
            'sources' => array_values(array_unique($sources)),
            'top_pick' => $scored['top_pick'],
        ];
    }

    /**
     * Weekly promote pick from the daily trending cache (for dashboard, etc.).
     *
     * @return array{top_pick: array<string, mixed>|null, refreshed_at: string|null, results: array<int, array<string, mixed>>}
     */
    public function weeklyPromotePick(): array
    {
        $cached = Cache::get('marketplace.trending');
        if (! is_array($cached)) {
            return ['top_pick' => null, 'refreshed_at' => null, 'results' => []];
        }

        return [
            'top_pick' => $cached['top_pick'] ?? null,
            'refreshed_at' => $cached['refreshed_at'] ?? null,
            'results' => is_array($cached['results'] ?? null) ? $cached['results'] : [],
        ];
    }

    /**
     * Filter cached trending offers by keyword (fallback when live ClickBank fails).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function searchTrendingCache(string $keyword, ?string $marketplace = null): array
    {
        $cached = Cache::get('marketplace.trending');
        if (! is_array($cached) || ($cached['results'] ?? []) === []) {
            return [];
        }

        $needle = Str::lower(trim($keyword));
        if ($needle === '') {
            return [];
        }

        $matches = [];
        foreach ($cached['results'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($marketplace !== null && strtolower((string) ($row['marketplace'] ?? '')) !== $marketplace) {
                continue;
            }

            $haystack = Str::lower(implode(' ', array_filter([
                (string) ($row['title'] ?? ''),
                (string) ($row['why'] ?? ''),
                (string) ($row['search_keyword'] ?? ''),
                (string) ($row['promote_reason'] ?? ''),
            ])));

            if (str_contains($haystack, $needle)) {
                $row['source'] = 'clickbank_cache';
                $row['search_keyword'] = $row['search_keyword'] ?? $keyword;
                $matches[] = $row;
            }
        }

        return $matches;
    }

    public function trending(): array
    {
        $cached = Cache::get('marketplace.trending');
        if (is_array($cached) && ($cached['results'] ?? []) !== []) {
            return [
                'ok' => true,
                'results' => $cached['results'],
                'search_links' => [],
                'error' => null,
                'sources' => $cached['sources'] ?? ['daily_cache'],
                'refreshed_at' => $cached['refreshed_at'] ?? null,
                'top_pick' => $cached['top_pick'] ?? null,
            ];
        }

        return [
            'ok' => false,
            'results' => [],
            'search_links' => $this->marketplaceSearchLinks('weight loss', null),
            'error' => 'Trending scan not ready. Run `php artisan marketplace:refresh-trending` or search a keyword above.',
            'sources' => [],
            'refreshed_at' => null,
            'top_pick' => null,
        ];
    }

    protected function shouldSearchJvzoo(?string $marketplace): bool
    {
        if ($marketplace !== null && $marketplace !== '' && $marketplace !== 'jvzoo') {
            return false;
        }

        return (bool) config('services.marketplace.jvzoo_html_enabled', true);
    }

    protected function shouldSearchWarriorPlus(?string $marketplace): bool
    {
        if ($marketplace !== null && $marketplace !== '' && $marketplace !== 'warriorplus') {
            return false;
        }

        return (bool) config('services.marketplace.warriorplus_html_enabled', true);
    }

    protected function shouldSearchClickBank(?string $marketplace): bool
    {
        if ($marketplace !== null && $marketplace !== '' && $marketplace !== 'clickbank') {
            return false;
        }

        return config('services.marketplace.clickbank_apify_enabled', true)
            && $this->apify->isConfigured();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function searchClickBankViaApify(string $keyword): array
    {
        $this->lastApifyError = null;

        $actorId = (string) config(
            'services.marketplace.clickbank_apify_actor_id',
            'bovi/clickbank-marketplace-scraper'
        );
        $timeout = (int) config('services.marketplace.clickbank_apify_timeout', 180);
        $maxResults = (int) config('services.marketplace.max_results', 12);
        $nickname = trim((string) config('services.marketplace.clickbank_affiliate_nickname', ''));

        $input = [
            'keyword' => $keyword,
            'sortBy' => 'gravity',
            'sortDescending' => true,
            'maxResults' => min(50, max(3, $maxResults)),
        ];

        if ($nickname !== '') {
            $input['affiliateNickname'] = $nickname;
        }

        Log::info('[MarketplaceSearch] ClickBank Apify run', ['keyword' => $keyword, 'actor' => $actorId]);

        $run = $this->apify->runSyncWithMeta($actorId, $input, $timeout);

        if ($run['error']) {
            $this->lastApifyError = 'ClickBank: '.$run['error'];
        }

        $results = [];

        foreach ($run['items'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = (string) ($item['offer_title'] ?? $item['title'] ?? $item['productName'] ?? '');
            if ($title === '') {
                continue;
            }

            $url = (string) (
                $item['product_url']
                ?? $item['productUrl']
                ?? $item['pitch_page_url']
                ?? $item['affiliateUrl']
                ?? ''
            );

            if ($url === '' && isset($item['vendor'])) {
                $vendor = (string) $item['vendor'];
                $url = "https://accounts.clickbank.com/marketplace.htm?vendor={$vendor}&offer=details";
            }

            if ($url === '') {
                continue;
            }

            $gravity = $item['bi_gravity'] ?? $item['gravity'] ?? null;
            $epc = $item['avg_epc'] ?? $item['averageEPC'] ?? $item['net_epc'] ?? $item['netEPC'] ?? null;

            $results[] = [
                'title' => $title,
                'marketplace' => 'clickbank',
                'url' => $url,
                'gravity_hint' => $gravity !== null ? (string) $gravity : '',
                'epc_hint' => $epc !== null ? '$'.(string) $epc : '',
                'why' => $this->clickBankWhyLine($item),
                'source' => 'clickbank_apify',
                'vendor' => (string) ($item['vendor'] ?? ''),
                'refund_rate' => isset($item['return_rate']) ? (string) $item['return_rate'] : null,
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @param  array<int, array<string, mixed>>  $incoming
     * @return array<int, array<string, mixed>>
     */
    protected function mergeResults(array $existing, array $incoming): array
    {
        $seen = [];
        $merged = $existing;

        foreach ($incoming as $row) {
            $key = Str::lower((string) ($row['title'] ?? '')).'|'.($row['marketplace'] ?? '').'|'.($row['url'] ?? '');
            if ($key === '||' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $merged[] = $row;
        }

        return $merged;
    }

    /**
     * Manual marketplace search pages — not selectable product rows.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function marketplaceSearchLinks(string $keyword, ?string $marketplace): array
    {
        $all = [
            [
                'label' => 'Search ClickBank',
                'marketplace' => 'clickbank',
                'url' => $this->marketplaceSearchUrl('clickbank', $keyword),
            ],
            [
                'label' => 'Search JVZoo',
                'marketplace' => 'jvzoo',
                'url' => $this->marketplaceSearchUrl('jvzoo', $keyword),
            ],
            [
                'label' => 'Search WarriorPlus',
                'marketplace' => 'warriorplus',
                'url' => $this->marketplaceSearchUrl('warriorplus', $keyword),
            ],
        ];

        if ($marketplace && $marketplace !== '') {
            return array_values(array_filter($all, fn ($l) => $l['marketplace'] === $marketplace));
        }

        return $all;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function clickBankWhyLine(array $item): string
    {
        $parts = [];
        if (isset($item['bi_gravity']) || isset($item['gravity'])) {
            $parts[] = 'Gravity '.($item['bi_gravity'] ?? $item['gravity']);
        }
        if (isset($item['avg_sale_usd']) || isset($item['avgSale'])) {
            $parts[] = 'Avg sale $'.($item['avg_sale_usd'] ?? $item['avgSale']);
        }
        if (isset($item['return_rate']) || isset($item['refundRate'])) {
            $parts[] = 'Refund '.($item['return_rate'] ?? $item['refundRate']).'%';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'ClickBank marketplace listing';
    }

    public function marketplaceSearchUrl(string $marketplace, string $keyword): string
    {
        $q = urlencode($keyword);

        return match ($marketplace) {
            'warriorplus' => "https://warriorplus.com/marketplace/search?q={$q}",
            'clickbank' => "https://accounts.clickbank.com/master/dashboard/affiliate-marketplace?search={$q}",
            'digistore24' => "https://www.digistore24.com/en/marketplace/all?query={$q}",
            default => "https://jvzoomarket.com/?s={$q}",
        };
    }
}
