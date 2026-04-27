<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class NewsService
{
    public function __construct(protected ApifyService $apify) {}

    public function searchArticles(string $keyword): array
    {
        $actorId = config('services.apify.news_actor_id', 'easyapi/google-news-scraper');
        $maxItems = (int) config('services.apify.max_items_per_search', 25);

        Log::info('NewsService: Searching articles', ['keyword' => $keyword]);

        $results = $this->apify->runSync($actorId, [
            'query' => $keyword,
            'maxItems' => $maxItems,
        ], 120);

        // Normalise Apify Google News actor output
        $articles = [];
        foreach ($results as $item) {
            $articles[] = [
                'title' => $item['title'] ?? '',
                'description' => $item['description'] ?? $item['snippet'] ?? '',
                'url' => $item['url'] ?? $item['link'] ?? null,
                'author' => $item['author'] ?? $item['source'] ?? 'Unknown',
                'publishedAt' => $item['publishedAt'] ?? $item['date'] ?? null,
                'source' => [
                    'name' => $item['source'] ?? $item['sourceName'] ?? 'Unknown',
                ],
            ];
        }

        Log::info('NewsService: Articles found', ['count' => count($articles)]);

        return $articles;
    }
}
