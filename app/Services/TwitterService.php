<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TwitterService
{
    public function __construct(protected ApifyService $apify) {}

    /**
     * Global X/Twitter keyword search via Apify (no connected account required).
     *
     * @return array{tweets: list<array<string, mixed>>, rate_limited: bool}
     */
    public function searchTweets(string $keyword): array
    {
        if (! config('services.apify.enabled', true)) {
            return ['tweets' => [], 'rate_limited' => false];
        }

        if (! config('services.apify.twitter_enabled', true)) {
            return ['tweets' => [], 'rate_limited' => false];
        }

        $actorId = (string) config(
            'services.apify.twitter_actor_id',
            'patient_discovery/twitter-search'
        );
        $maxResults = (int) config('services.apify.max_items_per_search', 25);
        $query = $this->buildSearchQuery($keyword);

        Log::info('TwitterService: Searching tweets via Apify', [
            'keyword' => $keyword,
            'actor_id' => $actorId,
            'query' => $query,
        ]);

        $results = $this->apify->runSync($actorId, [
            'query' => $query,
            'maxResults' => $maxResults,
        ], (int) config('services.apify.twitter_timeout', 300));

        $tweets = [];
        foreach ($results as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeTweet($item);
            if ($normalized !== null) {
                $tweets[] = $normalized;
            }
        }

        Log::info('TwitterService: Tweets found', ['count' => count($tweets)]);

        return ['tweets' => $tweets, 'rate_limited' => false];
    }

    private function buildSearchQuery(string $keyword): string
    {
        $query = trim($keyword);
        if ($query === '') {
            return $query;
        }

        if (config('services.apify.twitter_exclude_retweets', true) && ! str_contains(Str::lower($query), 'is:retweet')) {
            $query .= ' -is:retweet';
        }

        $lang = config('services.apify.twitter_lang');
        if (is_string($lang) && $lang !== '' && ! str_contains(Str::lower($query), 'lang:')) {
            $query .= ' lang:'.$lang;
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function normalizeTweet(array $item): ?array
    {
        $tweetId = $item['tweet_id'] ?? $item['id'] ?? $item['tweetId'] ?? null;
        if (! is_string($tweetId) && ! is_int($tweetId)) {
            return null;
        }

        $tweetId = (string) $tweetId;
        $text = (string) ($item['text'] ?? $item['full_text'] ?? $item['content'] ?? '');
        if ($text === '') {
            return null;
        }

        $username = $item['screen_name']
            ?? $item['username']
            ?? (is_array($item['user_info'] ?? null) ? ($item['user_info']['screen_name'] ?? $item['user_info']['username'] ?? null) : null)
            ?? (is_array($item['author'] ?? null) ? ($item['author']['userName'] ?? $item['author']['username'] ?? null) : null);

        $username = is_string($username) ? ltrim($username, '@') : null;

        $createdAt = $item['created_at'] ?? $item['createdAt'] ?? $item['date'] ?? null;
        $postedAt = null;
        if (is_string($createdAt) && $createdAt !== '') {
            try {
                $postedAt = Carbon::parse($createdAt)->toIso8601String();
            } catch (\Throwable) {
                $postedAt = null;
            }
        }

        $likes = (int) ($item['favorites'] ?? $item['like_count'] ?? $item['likes'] ?? $item['favorite_count'] ?? 0);
        $retweets = (int) ($item['retweets'] ?? $item['retweet_count'] ?? 0);
        $replies = (int) ($item['replies'] ?? $item['reply_count'] ?? 0);
        $views = (int) ($item['views'] ?? $item['view_count'] ?? $item['impression_count'] ?? 0);

        return [
            'id' => $tweetId,
            'text' => $text,
            'username' => $username,
            'author_id' => $item['user_id'] ?? $item['author_id'] ?? null,
            'created_at' => $postedAt,
            'public_metrics' => [
                'like_count' => $likes,
                'retweet_count' => $retweets,
                'reply_count' => $replies,
                'impression_count' => $views,
            ],
        ];
    }
}
