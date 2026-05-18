<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewsService
{
    public function __construct(protected ApifyService $apify) {}

    public function searchArticles(string $keyword): array
    {
        if (! config('services.apify.news_enabled', true)) {
            return [];
        }

        $actorId = (string) config('services.apify.news_actor_id', 'easyapi/google-news-scraper');
        $maxItems = max(100, (int) config('services.apify.news_max_items', 100));

        Log::info('NewsService: Searching articles', ['keyword' => $keyword]);

        $input = [
            'query' => $keyword,
            'maxItems' => $maxItems,
        ];

        $language = config('services.apify.news_language');
        if (is_string($language) && $language !== '') {
            $input['lr'] = $language;
        }

        $country = config('services.apify.news_country');
        if (is_string($country) && $country !== '') {
            $gl = strtolower(trim($country));
            if (strlen($gl) === 2) {
                $input['gl'] = $gl;
            }
        }

        $results = $this->runWithRetry($actorId, $input, (int) config('services.apify.news_timeout', 240));

        $articles = [];
        foreach ($results as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? $item['headline'] ?? ''));
            $url = $item['url'] ?? $item['link'] ?? $item['permalink'] ?? null;

            if ($title === '' && ! is_string($url)) {
                continue;
            }

            if ($title === '') {
                $title = Str::limit((string) ($item['description'] ?? $item['snippet'] ?? 'News article'), 120, '');
            }

            $articles[] = [
                'title' => $title,
                'description' => $item['description'] ?? $item['snippet'] ?? '',
                'url' => is_string($url) ? $url : null,
                'author' => $item['author'] ?? $item['source'] ?? 'Unknown',
                'publishedAt' => $item['publishedAt'] ?? $item['date'] ?? $item['published_at'] ?? null,
                'source' => [
                    'name' => is_string($item['source'] ?? null)
                        ? $item['source']
                        : ($item['sourceName'] ?? 'Unknown'),
                ],
            ];
        }

        Log::info('NewsService: Articles found', [
            'count' => count($articles),
            'raw_items' => count($results),
        ]);

        return $articles;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function runWithRetry(string $actorId, array $input, int $timeout): array
    {
        $results = $this->apify->runSync($actorId, $input, $timeout);

        if ($results !== []) {
            return $results;
        }

        Log::info('NewsService: retrying Apify news actor once', ['actor_id' => $actorId]);

        return $this->apify->runSync($actorId, $input, $timeout);
    }
}
