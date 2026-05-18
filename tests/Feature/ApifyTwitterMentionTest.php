<?php

namespace Tests\Feature;

use App\Services\TwitterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApifyTwitterMentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_tweets_normalizes_apify_actor_output(): void
    {
        config([
            'services.apify.api_token' => 'test-token',
            'services.apify.enabled' => true,
            'services.apify.twitter_enabled' => true,
            'services.apify.twitter_actor_id' => 'patient_discovery/twitter-search',
            'services.apify.max_items_per_search' => 10,
            'services.apify.twitter_lang' => 'en',
            'services.apify.twitter_exclude_retweets' => true,
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'patient_discovery') && str_contains($request->url(), 'run-sync-get-dataset-items')) {
                return Http::response([
                    [
                        'tweet_id' => '12345',
                        'screen_name' => 'johndoe',
                        'text' => 'Love this webinar platform',
                        'created_at' => 'Mon Dec 16 14:23:15 +0000 2025',
                        'favorites' => 10,
                        'retweets' => 2,
                        'replies' => 1,
                        'views' => 500,
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $result = app(TwitterService::class)->searchTweets('webinar');

        $this->assertFalse($result['rate_limited']);
        $this->assertCount(1, $result['tweets']);
        $this->assertSame('12345', $result['tweets'][0]['id']);
        $this->assertSame('johndoe', $result['tweets'][0]['username']);
        $this->assertStringContainsString('webinar', $result['tweets'][0]['text']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'patient_discovery~twitter-search')
                && str_contains((string) ($body['query'] ?? ''), 'webinar')
                && str_contains((string) ($body['query'] ?? ''), '-is:retweet');
        });
    }
}
