<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class YouTubeService
{
    public function __construct(protected ApifyService $apify) {}

    public function searchVideos(string $keyword): array
    {
        $actorId = config('services.apify.youtube_actor_id', 'bernardo/youtube-scraper');
        $maxItems = (int) config('services.apify.max_items_per_search', 25);

        Log::info('YouTubeService: Searching videos', ['keyword' => $keyword]);

        $results = $this->apify->runSync($actorId, [
            'searchTerms' => [$keyword],
            'maxResults' => $maxItems,
            'type' => 'video',
        ], 300);

        // Normalise Apify YouTube actor output into the snippet/id structure
        $videos = [];
        foreach ($results as $item) {
            if (isset($item['snippet'])) {
                $videos[] = $item;
                continue;
            }

            // Apify actor may return flat objects
            $videos[] = [
                'id' => [
                    'videoId' => $item['id'] ?? $item['videoId'] ?? null,
                ],
                'snippet' => [
                    'title' => $item['title'] ?? '',
                    'description' => $item['description'] ?? '',
                    'publishedAt' => $item['publishedAt'] ?? $item['date'] ?? null,
                    'channelTitle' => $item['channelName'] ?? '',
                ],
                'statistics' => $item['statistics'] ?? [
                    'viewCount' => $item['viewCount'] ?? 0,
                    'likeCount' => $item['likes'] ?? 0,
                    'commentCount' => $item['commentsCount'] ?? 0,
                ],
            ];
        }

        Log::info('YouTubeService: Videos found', ['count' => count($videos)]);

        return $videos;
    }
}
