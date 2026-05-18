<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YouTubeService
{
    public function __construct(protected ApifyService $apify) {}

    public function searchVideos(string $keyword): array
    {
        if (! config('services.apify.youtube_enabled', true)) {
            return [];
        }

        $actorId = (string) config(
            'services.apify.youtube_actor_id',
            'streamers/youtube-scraper'
        );
        $maxItems = (int) config('services.apify.max_items_per_search', 25);
        $timeout = (int) config('services.apify.youtube_timeout', 300);

        Log::info('YouTubeService: Searching videos via Apify', [
            'keyword' => $keyword,
            'actor_id' => $actorId,
        ]);

        $results = $this->apify->runSync(
            $actorId,
            $this->buildActorInput($actorId, $keyword, $maxItems),
            $timeout
        );

        $videos = [];
        $skipped = 0;

        foreach ($results as $item) {
            if (! is_array($item)) {
                $skipped++;

                continue;
            }

            $normalized = $this->normalizeVideo($item);
            if ($normalized !== null) {
                $videos[] = $normalized;

                continue;
            }

            $skipped++;
            if ($skipped <= 3) {
                Log::debug('YouTubeService: skipped dataset item', [
                    'keys' => array_keys($item),
                    'id' => $item['id'] ?? null,
                    'url' => $item['url'] ?? null,
                    'title' => $item['title'] ?? null,
                ]);
            }
        }

        Log::info('YouTubeService: Videos found', [
            'count' => count($videos),
            'raw_items' => count($results),
            'skipped' => $skipped,
        ]);

        return $videos;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActorInput(string $actorId, string $keyword, int $maxItems): array
    {
        $slug = Str::lower($actorId);

        if (str_contains($slug, 'youtube-search')) {
            return [
                'query' => $keyword,
                'resultsCount' => min(100, max(1, $maxItems)),
            ];
        }

        if (str_contains($slug, 'streamers')) {
            return [
                'searchQueries' => [$keyword],
                'maxResults' => $maxItems,
                'maxResultsShorts' => 0,
                'maxResultStreams' => 0,
            ];
        }

        return [
            'searchTerms' => [$keyword],
            'maxResults' => $maxItems,
            'type' => 'video',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{id: array{videoId: string|null}, snippet: array<string, mixed>, statistics: array<string, int>}|null
     */
    private function normalizeVideo(array $item): ?array
    {
        if (isset($item['snippet']) && is_array($item['id'] ?? null)) {
            return $item;
        }

        if (isset($item['snippet']) && isset($item['id']['videoId'])) {
            return $item;
        }

        $videoId = $this->extractVideoId($item);
        if ($videoId === null) {
            return null;
        }

        $title = trim((string) (
            $item['title']
            ?? $item['name']
            ?? $item['headline']
            ?? ''
        ));

        $description = trim((string) (
            $item['text']
            ?? $item['description']
            ?? $item['descriptionSnippet']
            ?? ''
        ));

        if ($title === '') {
            $title = $description !== '' ? Str::limit($description, 120, '') : 'YouTube video '.$videoId;
        }

        $channel = (string) ($item['channelName'] ?? $item['channelTitle'] ?? $item['channel'] ?? '');
        $publishedAt = $item['publishedAt'] ?? $item['date'] ?? $item['uploadDate'] ?? $item['publishedTimeText'] ?? null;

        return [
            'id' => ['videoId' => $videoId],
            'snippet' => [
                'title' => $title,
                'description' => $description,
                'publishedAt' => is_string($publishedAt) ? $publishedAt : null,
                'channelTitle' => $channel,
            ],
            'statistics' => [
                'viewCount' => (int) ($item['viewCount'] ?? $item['views'] ?? 0),
                'likeCount' => (int) ($item['likes'] ?? $item['likeCount'] ?? 0),
                'commentCount' => (int) ($item['commentsCount'] ?? $item['commentCount'] ?? $item['comments'] ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function extractVideoId(array $item): ?string
    {
        $candidates = [
            $item['id'] ?? null,
            $item['videoId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $id = $this->normalizeVideoIdValue($candidate);
            if ($id !== null) {
                return $id;
            }
        }

        foreach ([$item['url'] ?? null, $item['permalink'] ?? null] as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (preg_match('/(?:[?&]v=|\/embed\/|\/vi\/|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function normalizeVideoIdValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $id = trim((string) $value);
        if ($id === '') {
            return null;
        }

        if (str_contains($id, 'watch?v=')) {
            parse_str(parse_url($id, PHP_URL_QUERY) ?: '', $params);

            return $this->normalizeVideoIdValue($params['v'] ?? null);
        }

        // Playlists / channels are not valid video targets for replies.
        if (str_starts_with($id, 'PL') || str_starts_with($id, 'UC') || str_starts_with($id, 'LL')) {
            return null;
        }

        if (strlen($id) === 11 && preg_match('/^[a-zA-Z0-9_-]{11}$/', $id) === 1) {
            return $id;
        }

        return null;
    }
}
