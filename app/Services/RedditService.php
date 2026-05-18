<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class RedditService
{
    public function __construct(protected ApifyService $apify) {}

    /**
     * @return array<string, mixed>
     */
    public static function searchInputForKeyword(string $keyword): array
    {
        $allowedTime = ['hour', 'day', 'week', 'month', 'year', 'all'];
        $time = strtolower((string) config('services.apify.default_time', 'year'));
        if (! in_array($time, $allowedTime, true)) {
            $time = 'year';
        }

        $allowedSort = ['relevance', 'hot', 'top', 'new', 'comments'];
        $sort = strtolower((string) config('services.apify.default_sort', 'new'));
        if (! in_array($sort, $allowedSort, true)) {
            $sort = 'new';
        }

        return [
            'searches' => [$keyword],
            'sort' => $sort,
            'time' => $time,
            'maxItems' => (int) config('services.apify.max_items_per_search', 25),
            'searchPosts' => true,
            'searchComments' => false,
        ];
    }

    public function searchPosts(string $keyword): array
    {
        $actorId = config('services.apify.actor_id', 'practicaltools/apify-reddit-api');
        $input = self::searchInputForKeyword($keyword);

        Log::info('RedditService: Searching posts', [
            'keyword' => $keyword,
            'sort' => $input['sort'],
            'time' => $input['time'],
            'maxItems' => $input['maxItems'],
        ]);

        $results = $this->apify->runSync($actorId, $input, 120);

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
