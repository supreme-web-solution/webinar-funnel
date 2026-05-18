<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApifyService
{
    protected string $token;

    protected int $maxItems;

    public function __construct()
    {
        $this->token = config('services.apify.api_token', '');
        $this->maxItems = (int) config('services.apify.max_items_per_search', 25);
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    /**
     * Apify REST API expects `username~actor-name`, not `username/actor-name`.
     *
     * @see https://docs.apify.com/api/v2/act-run-sync-get-dataset-items-post
     */
    public static function normalizeActorIdForApi(string $actorId): string
    {
        $actorId = trim($actorId);

        if (str_contains($actorId, '/')) {
            return str_replace('/', '~', $actorId);
        }

        return $actorId;
    }

    /**
     * Run an Apify actor synchronously and return the dataset items.
     * Uses the run-sync-get-dataset-items endpoint (blocks until actor finishes).
     */
    public function runSync(string $actorId, array $input, int $timeoutSecs = 120): array
    {
        if (empty($this->token)) {
            Log::warning('ApifyService: No API token configured');

            return [];
        }

        $apiActorId = self::normalizeActorIdForApi($actorId);
        $url = 'https://api.apify.com/v2/acts/'.rawurlencode($apiActorId).'/run-sync-get-dataset-items';

        Log::info('ApifyService: Starting actor run', [
            'actor_id' => $actorId,
            'api_actor_id' => $apiActorId,
            'timeout' => $timeoutSecs,
        ]);

        try {
            $requestUrl = $url.'?'.http_build_query(['timeout' => $timeoutSecs]);

            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout($timeoutSecs + 30)
                ->post($requestUrl, $input);

            if ($response->failed()) {
                Log::error('ApifyService: Actor run failed', [
                    'actor_id' => $actorId,
                    'api_actor_id' => $apiActorId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $items = self::flattenDatasetItems($response->json());

            Log::info('ApifyService: Actor run completed', [
                'actor_id' => $actorId,
                'items_count' => count($items),
            ]);

            return $items;
        } catch (\Exception $e) {
            Log::error('ApifyService: Exception during actor run', [
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function flattenDatasetItems(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if ($data === []) {
            return [];
        }

        if (array_is_list($data)) {
            /** @var list<array<string, mixed>> $data */
            return array_values(array_filter($data, 'is_array'));
        }

        foreach (['data', 'items', 'results', 'videos', 'posts', 'articles', 'tweets'] as $key) {
            $nested = $data[$key] ?? null;
            if (is_array($nested) && $nested !== [] && array_is_list($nested)) {
                /** @var list<array<string, mixed>> $nested */
                return array_values(array_filter($nested, 'is_array'));
            }
        }

        /** @var array<string, mixed> $data */
        return [$data];
    }
}
