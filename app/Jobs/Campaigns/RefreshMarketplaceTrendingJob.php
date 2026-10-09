<?php

namespace App\Jobs\Campaigns;

use App\Services\Campaigns\MarketplaceOfferSearchService;
use App\Services\Campaigns\MarketplaceTrendingStore;
use App\Services\Campaigns\OfferScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RefreshMarketplaceTrendingJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function handle(MarketplaceOfferSearchService $search, OfferScoringService $scoring, MarketplaceTrendingStore $store): void
    {
        $this->refresh($search, $scoring, $store);
    }

    /**
     * JVZoo / WarriorPlus are fetched once (their listing ignores search terms);
     * ClickBank is searched per trending keyword because its Apify actor supports real search.
     *
     * @return array{saved: bool, count: int, marketplaces: array<string, int>, sources: array<int, string>, errors: array<int, string>}
     */
    public function refresh(MarketplaceOfferSearchService $search, OfferScoringService $scoring, MarketplaceTrendingStore $store): array
    {
        $listings = $search->fetchMarketplaceListings();
        $merged = $listings['results'];
        $sources = $listings['sources'];
        $errors = [];

        $keywords = config('services.marketplace.trending_keywords', ['ai software', 'weight loss', 'email marketing']);

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || trim($keyword) === '') {
                continue;
            }

            $clickbank = $search->searchClickBank(trim($keyword));
            if (! $clickbank['enabled']) {
                break;
            }

            foreach ($clickbank['results'] as $row) {
                $row['search_keyword'] = trim($keyword);
                $merged[] = $row;
                $sources[] = 'clickbank_apify';
            }

            if (is_string($clickbank['error']) && $clickbank['error'] !== '') {
                $errors[] = $clickbank['error'];

                break;
            }
        }

        $merged = array_map(fn (array $row): array => $row + ['trend' => 'rising'], $this->dedupe($merged));
        $summary = [
            'saved' => false,
            'count' => count($merged),
            'marketplaces' => array_count_values(array_map(fn (array $row): string => (string) ($row['marketplace'] ?? 'other'), $merged)),
            'sources' => array_values(array_unique($sources)),
            'errors' => array_values(array_unique($errors)),
        ];

        if ($merged === []) {
            Log::warning('[MarketplaceTrending] Scan returned no offers — keeping previous results', $summary);

            return $summary;
        }

        $scored = $scoring->scoreAndRank($merged);

        $store->put([
            'results' => array_slice($scored['results'], 0, 20),
            'listings' => $scored['results'],
            'top_pick' => $scored['top_pick'],
            'sources' => $summary['sources'],
            'refreshed_at' => now()->toIso8601String(),
        ]);

        $summary['saved'] = true;
        Log::info('[MarketplaceTrending] Refreshed', $summary);

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function dedupe(array $rows): array
    {
        $seen = [];
        $unique = [];

        foreach ($rows as $row) {
            $key = Str::lower((string) ($row['title'] ?? '')).'|'.($row['marketplace'] ?? '');
            if ($key === '|' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $row;
        }

        return $unique;
    }
}
