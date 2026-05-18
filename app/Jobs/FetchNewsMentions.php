<?php

namespace App\Jobs;

use App\Jobs\Concerns\EnforcesKeywordMentionCap;
use App\Mail\KeywordMentionAlert;
use App\Models\Keyword;
use App\Models\KeywordFetchState;
use App\Models\Mention;
use App\Services\NewsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FetchNewsMentions implements ShouldQueue
{
    use Dispatchable, EnforcesKeywordMentionCap, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public function __construct(
        protected Keyword $keyword,
        protected ?Carbon $fromDate = null,
        protected ?Carbon $toDate = null,
    ) {}

    public function handle(NewsService $newsService): void
    {
        $this->keyword->refresh();

        if ($this->abortFetchIfKeywordAtMentionCap($this->keyword)) {
            return;
        }

        if (! $this->keyword->is_active) {
            return;
        }

        if (! config('services.apify.enabled', true)) {
            return;
        }

        if ($this->isRecentlyFetched()) {
            return;
        }

        Log::info('FetchNewsMentions: Starting fetch', [
            'keyword_id' => $this->keyword->id,
            'keyword_name' => $this->keyword->name,
        ]);

        $articles = $newsService->searchArticles($this->keyword->name);

        if (empty($articles)) {
            KeywordFetchState::recordFetch($this->keyword->id, 'news');

            return;
        }

        $savedMentions = [];

        foreach ($articles as $article) {
            $url = $article['url'] ?? null;
            $publishedAt = $article['publishedAt'] ?? null;
            $postedAt = $publishedAt ? Carbon::parse($publishedAt) : null;

            if ($this->outsideDateRange($postedAt)) {
                continue;
            }

            $exists = Mention::where('permalink', $url)
                ->where('source_type', 'News')
                ->where('keyword_id', $this->keyword->id)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $mention = Mention::create([
                    'keyword_id' => $this->keyword->id,
                    'user_id' => $this->keyword->user_id,
                    'post_id' => Str::uuid()->toString(),
                    'title' => Str::limit($article['title'] ?? '', 500, ''),
                    'content' => $article['description'] ?? $article['title'] ?? null,
                    'source' => $article['source']['name'] ?? 'News',
                    'source_type' => 'News',
                    'username' => $article['author'] ?? null,
                    'permalink' => $url,
                    'posted_at' => $postedAt,
                ]);

                $savedMentions[] = $mention;
            } catch (\Exception $e) {
                Log::error('FetchNewsMentions: Failed to save', [
                    'keyword_id' => $this->keyword->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        KeywordFetchState::recordFetch($this->keyword->id, 'news');

        $this->enforceKeywordMentionCapAfterFetch($this->keyword);

        Log::info('FetchNewsMentions: Done', [
            'keyword_id' => $this->keyword->id,
            'saved' => count($savedMentions),
        ]);

        $this->maybeSendEmail($savedMentions, 'news');
    }

    protected function isRecentlyFetched(): bool
    {
        $state = KeywordFetchState::where('keyword_id', $this->keyword->id)
            ->where('platform', 'news')
            ->whereNotNull('last_fetch_at')
            ->first();

        if (! $state) {
            return false;
        }

        $interval = config('limits.fetch.platform_intervals.news', 60);

        return $state->last_fetch_at->copy()->addMinutes($interval)->isFuture();
    }

    protected function outsideDateRange(?Carbon $date): bool
    {
        if (! $this->fromDate || ! $this->toDate || ! $date) {
            return false;
        }

        return $date->isBefore($this->fromDate) || $date->isAfter($this->toDate);
    }

    protected function maybeSendEmail(array $savedMentions, string $platform): void
    {
        if (! $this->keyword->email_notifications || empty($savedMentions)) {
            return;
        }

        $user = $this->keyword->user;

        if (! $user || ! $user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(
                new KeywordMentionAlert($this->keyword, collect($savedMentions), $platform)
            );
        } catch (\Exception $e) {
            Log::error('FetchNewsMentions: Email failed', ['error' => $e->getMessage()]);
        }
    }
}
