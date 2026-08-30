<?php

use App\Console\Commands\FetchMentionsCommand;
use App\Console\Commands\RefreshMarketplaceTrendingCommand;
use App\Jobs\DispatchDuePromotionPostsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fetch mentions from all platforms every 15 minutes
Schedule::command(FetchMentionsCommand::class)->everyFifteenMinutes();

// Dispatch scheduled promotion posts every minute.
Schedule::job(new DispatchDuePromotionPostsJob())->everyMinute();

// Refresh Opportunity Finder trending cache daily (ClickBank + marketplace keywords).
Schedule::command(RefreshMarketplaceTrendingCommand::class)->dailyAt('06:00');
