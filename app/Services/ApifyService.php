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

        $url = "https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items";

        Log::info('ApifyService: Starting actor run', [
            'actor_id' => $actorId,
            'timeout' => $timeoutSecs,
        ]);

        try {
            $response = Http::withToken($this->token)
                ->timeout($timeoutSecs + 10)
                ->post($url, array_merge($input, [
                    'token' => $this->token,
                    'timeout' => $timeoutSecs,
                ]));

            if ($response->failed()) {
                Log::error('ApifyService: Actor run failed', [
                    'actor_id' => $actorId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $data = $response->json();

            if (!is_array($data)) {
                return [];
            }

            Log::info('ApifyService: Actor run completed', [
                'actor_id' => $actorId,
                'items_count' => count($data),
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('ApifyService: Exception during actor run', [
                'actor_id' => $actorId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
