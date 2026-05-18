<?php

namespace App\Jobs;

use App\Mail\KeywordMentionAlert;
use App\Models\Keyword;
use App\Models\KeywordFetchState;
use App\Models\Mention;
use App\Services\TwitterService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FetchTwitterMentions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 360;

    public int $tries = 2;

    public function __construct(
        protected Keyword $keyword,
        protected ?Carbon $fromDate = null,
        protected ?Carbon $toDate = null,
    ) {}

    public function handle(TwitterService $twitterService): void
    {
        $this->keyword->refresh();

        if (! $this->keyword->is_active) {
            return;
        }

        if (! config('services.apify.enabled', true) || ! config('services.apify.twitter_enabled', true)) {
            return;
        }

        if ($this->isOnCooldown()) {
            return;
        }

        if ($this->isRecentlyFetched()) {
            return;
        }

        Log::info('FetchTwitterMentions: Starting Apify global search', [
            'keyword_id' => $this->keyword->id,
            'keyword_name' => $this->keyword->name,
        ]);

        $result = $twitterService->searchTweets($this->keyword->name);
        $rateLimited = $result['rate_limited'] ?? false;
        $tweets = $result['tweets'] ?? [];

        if ($rateLimited) {
            $cooldownSeconds = (int) config('services.apify.twitter_cooldown_seconds', 900);
            $retryAt = Carbon::now()->addSeconds($cooldownSeconds);
            Cache::put($this->cooldownKey(), $retryAt->toIso8601String(), $retryAt);
            KeywordFetchState::setCooldown($this->keyword->id, 'twitter', $retryAt);

            Log::warning('FetchTwitterMentions: Rate limited', [
                'keyword_id' => $this->keyword->id,
                'retry_at' => $retryAt->toDateTimeString(),
            ]);

            return;
        }

        if (empty($tweets)) {
            KeywordFetchState::recordFetch($this->keyword->id, 'twitter');

            return;
        }

        $savedMentions = [];

        foreach ($tweets as $tweet) {
            $tweetId = $tweet['id'] ?? null;

            if (! $tweetId) {
                continue;
            }

            $text = $tweet['text'] ?? '';
            $authorId = $tweet['author_id'] ?? null;
            $username = $tweet['username'] ?? null;
            $metrics = $tweet['public_metrics'] ?? [];
            $createdAt = $tweet['created_at'] ?? null;
            $postedAt = $createdAt ? Carbon::parse($createdAt) : null;

            if ($this->outsideDateRange($postedAt)) {
                continue;
            }

            $exists = Mention::where('post_id', $tweetId)
                ->where('keyword_id', $this->keyword->id)
                ->exists();

            if ($exists) {
                continue;
            }

            $tweetUrl = $username
                ? "https://x.com/{$username}/status/{$tweetId}"
                : "https://x.com/i/web/status/{$tweetId}";

            try {
                $mention = Mention::create([
                    'keyword_id' => $this->keyword->id,
                    'user_id' => $this->keyword->user_id,
                    'post_id' => $tweetId,
                    'title' => Str::limit($text, 500, ''),
                    'content' => $text,
                    'source' => 'Twitter',
                    'source_type' => 'Twitter',
                    'author_id' => is_string($authorId) || is_int($authorId) ? (string) $authorId : null,
                    'username' => $username,
                    'like_count' => (int) ($metrics['like_count'] ?? 0),
                    'retweet_count' => (int) ($metrics['retweet_count'] ?? 0),
                    'comments_count' => (int) ($metrics['reply_count'] ?? 0),
                    'views' => (int) ($metrics['impression_count'] ?? 0),
                    'permalink' => $tweetUrl,
                    'posted_at' => $postedAt,
                ]);

                $savedMentions[] = $mention;
            } catch (\Exception $e) {
                Log::error('FetchTwitterMentions: Failed to save', [
                    'keyword_id' => $this->keyword->id,
                    'tweet_id' => $tweetId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        KeywordFetchState::recordFetch($this->keyword->id, 'twitter');

        Log::info('FetchTwitterMentions: Done', [
            'keyword_id' => $this->keyword->id,
            'saved' => count($savedMentions),
        ]);

        $this->maybeSendEmail($savedMentions, 'twitter');
    }

    protected function cooldownKey(): string
    {
        return 'twitter_cooldown_keyword_'.$this->keyword->id;
    }

    protected function isOnCooldown(): bool
    {
        $cached = Cache::get($this->cooldownKey());

        if (! $cached) {
            return false;
        }

        return Carbon::parse($cached)->isFuture();
    }

    protected function isRecentlyFetched(): bool
    {
        $state = KeywordFetchState::where('keyword_id', $this->keyword->id)
            ->where('platform', 'twitter')
            ->whereNotNull('last_fetch_at')
            ->first();

        if (! $state) {
            return false;
        }

        $interval = config('limits.fetch.platform_intervals.twitter', 10);

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
            Log::error('FetchTwitterMentions: Email failed', ['error' => $e->getMessage()]);
        }
    }
}
