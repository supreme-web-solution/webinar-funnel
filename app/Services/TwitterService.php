<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitterService
{
    protected string $bearerToken;

    public function __construct()
    {
        $this->bearerToken = config('services.twitter.bearer_token', '');
    }

    public function searchTweets(string $keyword): array
    {
        if (empty($this->bearerToken)) {
            Log::warning('TwitterService: No bearer token configured');
            return ['tweets' => [], 'rate_limited' => false];
        }

        $maxResults = (int) config('services.twitter.max_results', 10);
        // Clamp to Twitter API v2 limits (10–100 for recent search)
        $maxResults = max(10, min(100, $maxResults));

        Log::info('TwitterService: Searching tweets', ['keyword' => $keyword]);

        try {
            $response = Http::withToken($this->bearerToken)
                ->timeout(30)
                ->get('https://api.twitter.com/2/tweets/search/recent', [
                    'query' => $keyword . ' -is:retweet lang:en',
                    'max_results' => $maxResults,
                    'tweet.fields' => 'created_at,public_metrics,author_id',
                    'expansions' => 'author_id',
                    'user.fields' => 'username,name',
                ]);

            if ($response->status() === 429) {
                Log::warning('TwitterService: Rate limited');
                return ['tweets' => [], 'rate_limited' => true];
            }

            if ($response->failed()) {
                Log::error('TwitterService: API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return ['tweets' => [], 'rate_limited' => false];
            }

            $data = $response->json();
            $tweets = $data['data'] ?? [];
            $users = collect($data['includes']['users'] ?? [])->keyBy('id');

            // Attach username to each tweet
            foreach ($tweets as &$tweet) {
                $authorId = $tweet['author_id'] ?? null;
                $tweet['username'] = $authorId && isset($users[$authorId])
                    ? $users[$authorId]['username']
                    : null;
            }
            unset($tweet);

            Log::info('TwitterService: Tweets found', ['count' => count($tweets)]);

            return ['tweets' => $tweets, 'rate_limited' => false];
        } catch (\Exception $e) {
            Log::error('TwitterService: Exception', ['error' => $e->getMessage()]);
            return ['tweets' => [], 'rate_limited' => false];
        }
    }
}
