<?php

namespace App\Services\TrafficAi;

use App\Models\FunnelSetting;
use App\Models\Mention;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class TrafficReplyGenerator
{
    public function generate(Mention $mention, FunnelSetting $settings, string $affiliateUrl, string $platformKey): ?string
    {
        $maxChars = (int) (config('traffic_ai.openai.max_reply_chars')[$platformKey] ?? config('traffic_ai.openai.max_reply_chars.default'));

        $context = trim((string) $settings->traffic_ai_extra_context);
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            return $this->fallbackTemplate($mention, $affiliateUrl, $maxChars);
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('traffic_ai.openai.timeout', 45))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.65,
                    'max_tokens' => 400,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You write concise, human replies on '.$platformKey.'. Include exactly one natural mention of the provided link (plain URL). No hashtags spam. Stay under '.$maxChars.' characters. Sound like a real participant, not marketing boilerplate.',
                        ],
                        [
                            'role' => 'user',
                            'content' => json_encode([
                                'link' => $affiliateUrl,
                                'owner_context' => $context !== '' ? $context : null,
                                'platform' => $mention->source_type,
                                'post_title' => $mention->title,
                                'post_body' => Str::limit((string) $mention->content, 3500, ''),
                            ], JSON_THROW_ON_ERROR),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('TrafficReplyGenerator: OpenAI failed', ['status' => $response->status()]);

                return $this->fallbackTemplate($mention, $affiliateUrl, $maxChars);
            }

            $parsed = json_decode((string) $response->body(), true, 512, JSON_THROW_ON_ERROR);
            $text = trim((string) ($parsed['choices'][0]['message']['content'] ?? ''));

            if ($text === '') {
                return $this->fallbackTemplate($mention, $affiliateUrl, $maxChars);
            }

            if (! str_contains($text, $affiliateUrl)) {
                $text = rtrim($text, " \n").' '.$affiliateUrl;
            }

            return Str::limit($text, $maxChars, '');
        } catch (\Throwable $e) {
            Log::warning('TrafficReplyGenerator: exception', ['error' => $e->getMessage()]);

            return $this->fallbackTemplate($mention, $affiliateUrl, $maxChars);
        }
    }

    private function fallbackTemplate(Mention $mention, string $affiliateUrl, int $maxChars): string
    {
        $title = Str::limit((string) ($mention->title ?? 'this'), 80, '…');
        $base = "Saw your post about {$title} — this helped me wrap my head around it: {$affiliateUrl}";

        return Str::limit($base, $maxChars, '');
    }
}
