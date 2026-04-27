<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsMentions;
use App\Jobs\FetchRedditMentions;
use App\Jobs\FetchTwitterMentions;
use App\Jobs\FetchYouTubeMentions;
use App\Models\Keyword;
use App\Models\KeywordFetchState;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchMentionsCommand extends Command
{
    protected $signature = 'mentions:fetch {platform?} {--days=}';
    protected $description = 'Dispatch mention-fetch jobs for all active keywords';

    public function handle(): void
    {
        $platform = $this->argument('platform');
        $days = $this->option('days');

        $fromDate = $days ? now()->subDays((int) $days)->startOfDay() : null;
        $toDate = $days ? now()->endOfDay() : null;

        $platforms = ['reddit', 'youtube', 'twitter', 'news'];

        foreach ($platforms as $target) {
            if ($platform && $platform !== $target) {
                continue;
            }

            $keywords = $this->eligibleKeywords($target);

            if ($keywords->isEmpty()) {
                $this->line("  No eligible keywords for <info>{$target}</info>");
                continue;
            }

            Log::info('FetchMentionsCommand: Dispatching', [
                'platform' => $target,
                'keywords' => $keywords->count(),
            ]);

            $this->info("Dispatching {$keywords->count()} keyword(s) for {$target}…");

            foreach ($keywords as $keyword) {
                $this->dispatchForPlatform($target, $keyword, $fromDate, $toDate);
                $this->markScheduled($keyword, $target);
            }
        }

        $this->info('Done — all fetch jobs queued.');
    }

    protected function dispatchForPlatform(
        string $platform,
        Keyword $keyword,
        ?Carbon $from,
        ?Carbon $to,
    ): void {
        match ($platform) {
            'reddit'  => FetchRedditMentions::dispatch($keyword, $from, $to),
            'youtube' => FetchYouTubeMentions::dispatch($keyword, $from, $to),
            'twitter' => FetchTwitterMentions::dispatch($keyword, $from, $to),
            'news'    => FetchNewsMentions::dispatch($keyword, $from, $to),
            default   => null,
        };
    }

    protected function eligibleKeywords(string $platform)
    {
        $chunkSize = config('limits.fetch.keywords_per_cycle');

        return Keyword::query()
            ->where('is_active', true)
            ->whereJsonContains('platforms', $platform)
            ->where(function ($q) use ($platform) {
                $q->whereDoesntHave('fetchStates', fn ($s) => $s->where('platform', $platform))
                    ->orWhereHas('fetchStates', function ($s) use ($platform) {
                        $s->where('platform', $platform)
                            ->where(function ($d) {
                                $d->whereNull('next_fetch_at')
                                    ->orWhere('next_fetch_at', '<=', now());
                            })
                            ->where(function ($c) {
                                $c->whereNull('cooldown_until')
                                    ->orWhere('cooldown_until', '<=', now());
                            });
                    });
            })
            ->with(['fetchStates' => fn ($q) => $q->where('platform', $platform)])
            ->orderByRaw(
                'COALESCE((SELECT last_fetch_at FROM keyword_fetch_states'
                . ' WHERE keyword_fetch_states.keyword_id = keywords.id'
                . ' AND keyword_fetch_states.platform = ?), "1970-01-01") ASC',
                [$platform]
            )
            ->orderBy('id')
            ->when($chunkSize, fn ($q) => $q->limit((int) $chunkSize))
            ->get();
    }

    protected function markScheduled(Keyword $keyword, string $platform): void
    {
        $interval = (int) max(
            config("limits.fetch.platform_intervals.{$platform}", 15),
            1
        );

        KeywordFetchState::updateOrCreate(
            ['keyword_id' => $keyword->id, 'platform' => $platform],
            ['next_fetch_at' => now()->addMinutes($interval)]
        );
    }
}
