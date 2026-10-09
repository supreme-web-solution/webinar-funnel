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
     * @return array{saved: bool, count: int, keywords: array<string, int>, sources: array<int, string>, errors: array<int, string>}
     */
    public function refresh(MarketplaceOfferSearchService $search, OfferScoringService $scoring, MarketplaceTrendingStore $store): array
    {
        $keywords = config('services.marketplace.trending_keywords', [
            'ai software',
            'weight loss',
            'email marketing',
        ]);

        $merged = [];
        $sources = [];
        $errors = [];
        $perKeyword = [];

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || trim($keyword) === '') {
                continue;
            }

            $keyword = trim($keyword);
            $payload = $search->search($keyword);
            $perKeyword[$keyword] = count($payload['results'] ?? []);

            foreach ($payload['results'] ?? [] as $row) {
                $row['search_keyword'] = $keyword;
                if (! isset($row['trend'])) {
                    $row['trend'] = 'rising';
                }
                $merged[] = $row;
            }

            foreach ($payload['sources'] ?? [] as $source) {
                $sources[] = $source;
            }

            if (is_string($payload['error'] ?? null) && $payload['error'] !== '') {
                $errors[] = $payload['error'];
            }
        }

        $merged = $this->dedupe($merged);
        $summary = [
            'saved' => false,
            'count' => count($merged),
            'keywords' => $perKeyword,
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
