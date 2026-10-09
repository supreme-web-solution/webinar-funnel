<?php

namespace App\Console\Commands;

use App\Jobs\Campaigns\RefreshMarketplaceTrendingJob;
use App\Services\Campaigns\MarketplaceOfferSearchService;
use App\Services\Campaigns\MarketplaceTrendingStore;
use App\Services\Campaigns\OfferScoringService;
use Illuminate\Console\Command;

class RefreshMarketplaceTrendingCommand extends Command
{
    protected $signature = 'marketplace:refresh-trending';

    protected $description = 'Refresh cached trending offers for the Opportunity Finder';

    public function handle(MarketplaceOfferSearchService $search, OfferScoringService $scoring, MarketplaceTrendingStore $store): int
    {
        $this->info('Scanning marketplaces — this can take a few minutes…');

        $summary = (new RefreshMarketplaceTrendingJob)->refresh($search, $scoring, $store);

        $this->table(
            ['Marketplace', 'Offers listed'],
            collect($summary['marketplaces'])->map(fn (int $count, string $marketplace): array => [$marketplace, $count])->values()->all(),
        );

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        if (! $summary['saved']) {
            $this->error('No offers found — the previous trending results were kept.');

            return self::FAILURE;
        }

        $this->info("Saved {$summary['count']} marketplace offers — the top 20 show on Opportunities, all are searchable (sources: ".implode(', ', $summary['sources']).').');

        return self::SUCCESS;
    }
}
