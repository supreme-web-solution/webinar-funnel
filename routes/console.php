<?php

use App\Console\Commands\FetchMentionsCommand;
use App\Console\Commands\ProcessCampaignEmailSequencesCommand;
use App\Console\Commands\RefreshMarketplaceTrendingCommand;
use App\Jobs\DispatchDuePromotionPostsJob;
use App\Jobs\ProcessDueContentEmployeeItemsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fetch mentions from all platforms every 15 minutes
Schedule::command(FetchMentionsCommand::class)->everyFifteenMinutes();

// Dispatch scheduled promotion posts every minute.
Schedule::job(new DispatchDuePromotionPostsJob)->everyMinute();

// Create Content Employee posts shortly before their planned time (they auto-publish when due).
Schedule::job(new ProcessDueContentEmployeeItemsJob)->everyMinute();

// Send due in-app campaign email swipes every minute.
Schedule::command(ProcessCampaignEmailSequencesCommand::class)->everyMinute();

// Refresh Opportunity Finder trending cache daily (ClickBank + marketplace keywords).
Schedule::command(RefreshMarketplaceTrendingCommand::class)->dailyAt('06:00');
