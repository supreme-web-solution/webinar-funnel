<?php

namespace App\Console\Commands;

use App\Jobs\Campaigns\RefreshMarketplaceTrendingJob;
use Illuminate\Console\Command;

class RefreshMarketplaceTrendingCommand extends Command
{
    protected $signature = 'marketplace:refresh-trending';

    protected $description = 'Refresh cached trending offers for the Opportunity Finder';

    public function handle(): int
    {
        RefreshMarketplaceTrendingJob::dispatchSync();
        $this->info('Marketplace trending cache refreshed.');

        return self::SUCCESS;
    }
}
