<?php

namespace App\Services\TrafficAi;

use App\Models\FunnelSetting;
use App\Models\Mention;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TrafficReplyGate
{
    /**
     * @return array{reply: bool, details: array<string, mixed>}
     */
    public function evaluate(Mention $mention, FunnelSetting $settings): array
    {
        $text = trim(Str::limit((string) ($mention->title.' '.$mention->content), 6000, ''));

        if (strlen($text) < 15) {
            return [
                'reply' => false,
                'details' => ['reason' => 'content_too_short'],
            ];
        }

        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return $this->heuristicGate($mention, $text);
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('traffic_ai.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'max_tokens' => 120,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You decide if a public social post is an appropriate place for a helpful, non-spammy reply that may include one relevant link to a webinar or resource. Reply JSON only: {"should_reply":boolean,"confidence":0-1,"reason":"short string"}. Reject: hate, illegal topics, medical claims, harassment, pure ads, bot-like threads, or where a promotional reply would be inappropriate.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'platform' => $mention->source_type,
                                'title' => $mention->title,
                                'content_preview' => Str::limit((string) $mention->content, 2000, ''),
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('TrafficReplyGate: OpenAI classify failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 500, ''),
                ]);

                return $this->heuristicGate($mention, $text);
            }

            $parsed = json_decode((string) $response->body(), true, 512, JSON_THROW_ON_ERROR);
            $content = $parsed['choices'][0]['message']['content'] ?? '{}';
            $obj = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);

            $should = (bool) ($obj['should_reply'] ?? false);
            $confidence = (float) ($obj['confidence'] ?? 0);

            if ($should && $confidence < 0.55) {
                $should = false;
            }

            return [
                'reply' => $should,
                'details' => [
                    'model' => $model,
                    'llm' => $obj,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('TrafficReplyGate: exception, falling back', ['error' => $e->getMessage()]);

            return $this->heuristicGate($mention, $text);
        }
    }

    /**
     * @return array{reply: bool, details: array<string, mixed>}
     */
    private function heuristicGate(Mention $mention, string $text): array
    {
        $lower = Str::lower($text);
        $blocked = ['nsfw', 'suicide', 'kill yourself', 'terror', 'cp ', 'child porn'];

        foreach ($blocked as $needle) {
            if (str_contains($lower, $needle)) {
                return ['reply' => false, 'details' => ['reason' => 'blocked_term', 'term' => $needle]];
            }
        }

        $signals = str_contains($lower, '?')
            || str_contains($lower, 'how do i')
            || str_contains($lower, 'how to')
            || str_contains($lower, 'recommend')
            || str_contains($lower, 'looking for')
            || str_contains($lower, 'anyone know');

        return [
            'reply' => $signals,
            'details' => ['reason' => 'heuristic', 'signals' => $signals],
        ];
    }
}
