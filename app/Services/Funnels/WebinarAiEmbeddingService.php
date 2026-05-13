<?php

namespace App\Services\Funnels;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebinarAiEmbeddingService
{
    /**
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        $apiKey = trim((string) config('services.openai.api_key', ''));
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(35)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => (string) config('services.openai.embedding_model', 'text-embedding-3-small'),
                    'input' => $text,
                ]);

            if (! $response->successful()) {
                Log::warning('Webinar AI embedding failed', ['status' => $response->status()]);
                return null;
            }

            $vector = $response->json('data.0.embedding');
            if (! is_array($vector) || $vector === []) {
                return null;
            }

            return array_map(static fn ($v): float => (float) $v, $vector);
        } catch (\Throwable $e) {
            Log::warning('Webinar AI embedding exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public function cosineSimilarity(array $a, array $b): float
    {
        $len = min(count($a), count($b));
        if ($len === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}

