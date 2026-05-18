<?php

namespace App\Jobs;

use App\Jobs\Concerns\EnforcesKeywordMentionCap;
use App\Mail\KeywordMentionAlert;
use App\Models\Keyword;
use App\Models\KeywordFetchState;
use App\Models\Mention;
use App\Services\RedditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FetchRedditMentions implements ShouldQueue
{
    use Dispatchable, EnforcesKeywordMentionCap, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public function __construct(
        protected Keyword $keyword,
        protected ?Carbon $fromDate = null,
        protected ?Carbon $toDate = null,
    ) {}

    public function handle(RedditService $redditService): void
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

        Log::info('FetchRedditMentions: Starting fetch', [
            'keyword_id' => $this->keyword->id,
            'keyword_name' => $this->keyword->name,
        ]);

        $posts = $redditService->searchPosts($this->keyword->name);

        if (empty($posts)) {
            KeywordFetchState::recordFetch($this->keyword->id, 'reddit');
            $this->enforceKeywordMentionCapAfterFetch($this->keyword);

            return;
        }

        $savedMentions = [];

        foreach ($posts as $post) {
            $data = $post['data'] ?? [];

            if (! isset($data['id'])) {
                continue;
            }

            if ($this->outsideDateRange($data['created_utc'] ?? null)) {
                continue;
            }

            if ($this->olderThanConfiguredMaxAge($data['created_utc'] ?? null)) {
                continue;
            }

            $exists = Mention::where('post_id', $data['id'])
                ->where('keyword_id', $this->keyword->id)
                ->exists();

            if ($exists) {
                continue;
            }

            try {
                $mention = Mention::create([
                    'keyword_id' => $this->keyword->id,
                    'user_id' => $this->keyword->user_id,
                    'post_id' => $data['id'],
                    'title' => Str::limit($data['title'] ?? 'Untitled', 500, ''),
                    'content' => $data['selftext'] ?? null,
                    'source' => $data['subreddit'] ?? 'Reddit',
                    'source_type' => 'Reddit',
                    'username' => $data['author'] ?? null,
                    'votes' => ($data['ups'] ?? 0) - ($data['downs'] ?? 0),
                    'comments_count' => $data['num_comments'] ?? 0,
                    'category' => $data['subreddit'] ?? null,
                    'permalink' => isset($data['permalink'])
                        ? 'https://reddit.com'.$data['permalink']
                        : null,
                    'posted_at' => isset($data['created_utc'])
                        ? Carbon::createFromTimestampUTC($data['created_utc'])
                        : null,
                ]);

                $savedMentions[] = $mention;
            } catch (\Exception $e) {
                Log::error('FetchRedditMentions: Failed to save', [
                    'keyword_id' => $this->keyword->id,
                    'post_id' => $data['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        KeywordFetchState::recordFetch($this->keyword->id, 'reddit');

        $this->enforceKeywordMentionCapAfterFetch($this->keyword);

        Log::info('FetchRedditMentions: Done', [
            'keyword_id' => $this->keyword->id,
            'saved' => count($savedMentions),
        ]);

        $this->maybeSendEmail($savedMentions, 'reddit');
    }

    protected function isRecentlyFetched(): bool
    {
        $state = KeywordFetchState::where('keyword_id', $this->keyword->id)
            ->where('platform', 'reddit')
            ->whereNotNull('last_fetch_at')
            ->first();

        if (! $state) {
            return false;
        }

        $interval = config('limits.fetch.platform_intervals.reddit', 15);

        return $state->last_fetch_at->copy()->addMinutes($interval)->isFuture();
    }

    protected function outsideDateRange(mixed $createdUtc): bool
    {
        if (! $this->fromDate || ! $this->toDate || ! $createdUtc) {
            return false;
        }

        $date = Carbon::createFromTimestampUTC($createdUtc);

        return $date->isBefore($this->fromDate) || $date->isAfter($this->toDate);
    }

    /**
     * Apify/Reddit only expose fixed windows (hour…year), not exactly 90 days — drop older rows if any slip through.
     */
    protected function olderThanConfiguredMaxAge(mixed $createdUtc): bool
    {
        $maxDays = (int) config('services.apify.max_post_age_days', 90);
        if ($maxDays <= 0 || ! $createdUtc) {
            return false;
        }

        $postedAt = Carbon::createFromTimestampUTC((int) $createdUtc);

        return $postedAt->lt(now()->subDays($maxDays));
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
            Log::error('FetchRedditMentions: Email failed', ['error' => $e->getMessage()]);
        }
    }
}
