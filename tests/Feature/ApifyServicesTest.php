<?php

namespace Tests\Feature;

use App\Services\NewsService;
use App\Services\RedditService;
use App\Services\TwitterService;
use App\Services\YouTubeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApifyServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.apify.api_token' => 'test-token',
            'services.apify.enabled' => true,
            'services.apify.max_items_per_search' => 10,
        ]);
    }

    public function test_youtube_normalizes_item_with_url_but_no_title(): void
    {
        config(['services.apify.youtube_actor_id' => 'streamers/youtube-scraper']);

        Http::fake([
            'api.apify.com/v2/acts/streamers~youtube-scraper/run-sync-get-dataset-items*' => Http::response([
                [
                    'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    'channelName' => 'Test Channel',
                    'viewCount' => 100,
                ],
            ], 200),
        ]);

        $videos = app(YouTubeService::class)->searchVideos('brand');

        $this->assertCount(1, $videos);
        $this->assertSame('dQw4w9WgXcQ', $videos[0]['id']['videoId']);
    }

    public function test_youtube_streamers_actor_input_and_normalization(): void
    {
        config(['services.apify.youtube_actor_id' => 'streamers/youtube-scraper']);

        Http::fake([
            'api.apify.com/v2/acts/streamers~youtube-scraper/run-sync-get-dataset-items*' => Http::response([
                [
                    'id' => 'dQw4w9WgXcQ',
                    'title' => 'Best webinar tips',
                    'text' => 'Learn how to run webinars',
                    'channelName' => 'Marketing Pro',
                    'viewCount' => 1200,
                    'likes' => 40,
                    'commentsCount' => 5,
                    'date' => '2024-01-15',
                ],
            ], 200),
        ]);

        $videos = app(YouTubeService::class)->searchVideos('webinar');

        $this->assertCount(1, $videos);
        $this->assertSame('dQw4w9WgXcQ', $videos[0]['id']['videoId']);
        $this->assertSame('Best webinar tips', $videos[0]['snippet']['title']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str_contains($request->url(), 'streamers~youtube-scraper')
                && ($body['searchQueries'] ?? []) === ['webinar']
                && ($body['maxResults'] ?? 0) === 10;
        });
    }

    public function test_youtube_search_scraper_actor_input(): void
    {
        config(['services.apify.youtube_actor_id' => 'scraper_one/youtube-search-scraper']);

        Http::fake([
            'api.apify.com/v2/acts/scraper_one~youtube-search-scraper/run-sync-get-dataset-items*' => Http::response([
                [
                    'id' => 'abcdefghijk',
                    'title' => 'Webinar replay',
                    'descriptionSnippet' => 'Full replay',
                    'channelName' => 'Host Channel',
                    'viewCount' => 99,
                ],
            ], 200),
        ]);

        $videos = app(YouTubeService::class)->searchVideos('webinar');

        $this->assertCount(1, $videos);
        $this->assertSame('abcdefghijk', $videos[0]['id']['videoId']);

        Http::assertSent(fn ($request) => ($request->data()['query'] ?? '') === 'webinar');
    }

    public function test_reddit_actor_returns_posts(): void
    {
        config(['services.apify.actor_id' => 'practicaltools/apify-reddit-api']);

        Http::fake([
            'api.apify.com/v2/acts/practicaltools~apify-reddit-api/run-sync-get-dataset-items*' => Http::response([
                ['data' => ['id' => 'post1', 'title' => 'webinar thread', 'selftext' => 'body']],
            ], 200),
        ]);

        $posts = app(RedditService::class)->searchPosts('webinar');
        $this->assertCount(1, $posts);
        $this->assertSame('post1', $posts[0]['data']['id']);
    }

    public function test_news_actor_returns_articles(): void
    {
        config(['services.apify.news_actor_id' => 'easyapi/google-news-scraper']);

        Http::fake([
            'api.apify.com/v2/acts/easyapi~google-news-scraper/run-sync-get-dataset-items*' => Http::response([
                ['title' => 'Webinar news', 'url' => 'https://news.example/a', 'source' => 'Example'],
            ], 200),
        ]);

        $articles = app(NewsService::class)->searchArticles('webinar');
        $this->assertCount(1, $articles);
        $this->assertSame('Webinar news', $articles[0]['title']);
    }

    public function test_twitter_actor_returns_tweets(): void
    {
        config([
            'services.apify.twitter_enabled' => true,
            'services.apify.twitter_actor_id' => 'patient_discovery/twitter-search',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'patient_discovery') && str_contains($request->url(), 'run-sync-get-dataset-items')) {
                return Http::response([
                    ['tweet_id' => '99', 'screen_name' => 'user', 'text' => 'great webinar'],
                ], 200);
            }

            return Http::response([], 404);
        });

        $result = app(TwitterService::class)->searchTweets('webinar');
        $this->assertCount(1, $result['tweets']);
    }
}
