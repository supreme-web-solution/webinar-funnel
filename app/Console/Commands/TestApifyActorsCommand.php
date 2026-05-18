<?php

namespace App\Console\Commands;

use App\Services\ApifyService;
use App\Services\NewsService;
use App\Services\RedditService;
use App\Services\TwitterService;
use App\Services\YouTubeService;
use Illuminate\Console\Command;

class TestApifyActorsCommand extends Command
{
    protected $signature = 'apify:test-actors {--keyword=webinar : Search keyword for all platforms}';

    protected $description = 'Run a live Apify fetch for Reddit, YouTube, Twitter, and News (requires APIFY_API_TOKEN)';

    public function handle(
        ApifyService $apify,
        RedditService $reddit,
        YouTubeService $youtube,
        TwitterService $twitter,
        NewsService $news,
    ): int {
        if (! config('services.apify.enabled', true)) {
            $this->error('APIFY_ENABLED is false.');

            return self::FAILURE;
        }

        if (! $apify->isConfigured()) {
            $this->error('Set APIFY_API_TOKEN in .env first.');

            return self::FAILURE;
        }

        $keyword = (string) $this->option('keyword');
        $this->info("Testing Apify actors with keyword: {$keyword}");
        $this->newLine();

        $ok = true;

        $ok = $this->runPlatform('Reddit', (string) config('services.apify.actor_id'), fn () => count($reddit->searchPosts($keyword))) && $ok;

        $ok = $this->runPlatform('YouTube', (string) config('services.apify.youtube_actor_id'), fn () => count($youtube->searchVideos($keyword))) && $ok;

        $ok = $this->runPlatform('Twitter/X', (string) config('services.apify.twitter_actor_id'), function () use ($twitter, $keyword) {
            $result = $twitter->searchTweets($keyword);

            return count($result['tweets'] ?? []);
        }) && $ok;

        $ok = $this->runPlatform('News', (string) config('services.apify.news_actor_id'), fn () => count($news->searchArticles($keyword))) && $ok;

        $this->newLine();

        if ($ok) {
            $this->info('All Apify actors returned results.');

            return self::SUCCESS;
        }

        $this->warn('One or more actors failed or returned zero items. Check storage/logs/laravel.log');

        return self::FAILURE;
    }

    /**
     * @param  callable(): int  $countFn
     */
    private function runPlatform(string $label, string $actorId, callable $countFn): bool
    {
        $apiId = ApifyService::normalizeActorIdForApi($actorId);
        $this->line("<fg=cyan>{$label}</> — <fg=yellow>{$actorId}</> → API: <fg=gray>{$apiId}</>");

        try {
            $count = $countFn();
            if ($count > 0) {
                $this->line("  <fg=green>✓</> {$count} item(s)");

                return true;
            }

            $this->line('  <fg=yellow>⚠</> 0 items (actor ran but nothing matched)');

            return false;
        } catch (\Throwable $e) {
            $this->line('  <fg=red>✗</> '.$e->getMessage());

            return false;
        }
    }
}
