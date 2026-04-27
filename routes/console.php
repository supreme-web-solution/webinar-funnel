<?php

use App\Console\Commands\FetchMentionsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fetch mentions from all platforms every 15 minutes
Schedule::command(FetchMentionsCommand::class)->everyFifteenMinutes();
