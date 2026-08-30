<?php

namespace App\Jobs\Campaigns;

use App\Services\Campaigns\MarketplaceOfferSearchService;
use App\Services\Campaigns\OfferScoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RefreshMarketplaceTrendingJob implements ShouldQueue
{
    use Queueable;

    public function handle(MarketplaceOfferSearchService $search, OfferScoringService $scoring): void
    {
        $keywords = config('services.marketplace.trending_keywords', [
            'ai software',
            'weight loss',
            'email marketing',
        ]);

        $merged = [];
        $sources = [];

        foreach ($keywords as $keyword) {
            if (! is_string($keyword) || trim($keyword) === '') {
                continue;
            }

            $payload = $search->search(trim($keyword));
            foreach ($payload['results'] ?? [] as $row) {
                $row['search_keyword'] = trim($keyword);
                if (! isset($row['trend'])) {
                    $row['trend'] = 'rising';
                }
                $merged[] = $row;
            }

            foreach ($payload['sources'] ?? [] as $source) {
                $sources[] = $source;
            }
        }

        $merged = $this->dedupe($merged);
        $scored = $scoring->scoreAndRank($merged);
        $ttl = (int) config('services.marketplace.trending_cache_ttl', 86400);

        Cache::put('marketplace.trending', [
            'results' => array_slice($scored['results'], 0, 20),
            'top_pick' => $scored['top_pick'],
            'sources' => array_values(array_unique($sources)),
            'refreshed_at' => now()->toIso8601String(),
        ], $ttl);

        Log::info('[MarketplaceTrending] Refreshed cache', ['count' => count($merged)]);
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
