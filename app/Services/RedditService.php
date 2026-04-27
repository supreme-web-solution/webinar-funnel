<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RedditService
{
    public function __construct(protected ApifyService $apify) {}

    public function searchPosts(string $keyword): array
    {
        $actorId = config('services.apify.actor_id', 'practicaltools/apify-reddit-api');
        $maxItems = (int) config('services.apify.max_items_per_search', 25);
        $sort = config('services.apify.default_sort', 'top');

        Log::info('RedditService: Searching posts', ['keyword' => $keyword]);

        $results = $this->apify->runSync($actorId, [
            'searches' => [$keyword],
            'type' => 'posts',
            'sort' => $sort,
            'maxItems' => $maxItems,
        ], 120);

        // The Apify Reddit actor returns items with a 'data' wrapper or direct objects
        $posts = [];
        foreach ($results as $item) {
            if (isset($item['data'])) {
                $posts[] = $item;
            } elseif (isset($item['id'])) {
                $posts[] = ['data' => $item];
            }
        }

        Log::info('RedditService: Posts found', ['count' => count($posts)]);

        return $posts;
    }
}
