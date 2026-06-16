<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionTopicSuggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PromotionTopicSuggestionService
{
    /**
     * @return array<int, array{topic: string, angle: string|null, score: int}>
     */
    public function generate(Funnel $funnel, int $count = 12, ?string $extraContext = null): array
    {
        $count = max(5, min($count, (int) config('promotion.max_sequence_size', 30)));
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            return $this->fallbackTopics($funnel, $count, $extraContext);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('promotion.openai.timeout', 90))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('promotion.openai.text_model', 'gpt-4o-mini'),
                    'temperature' => 0.9,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Generate high-converting social topic ideas for a marketing funnel. Output strict JSON: {"topics":[{"topic":"...","angle":"...","score":0-100}]}.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->topicPrompt($funnel, $count, $extraContext),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return $this->fallbackTopics($funnel, $count, $extraContext);
            }

            $json = $response->json();
            $items = $json['choices'][0]['message']['content'] ?? null;
            if (! is_string($items) || $items === '') {
                return $this->fallbackTopics($funnel, $count, $extraContext);
            }

            $decoded = json_decode($items, true);
            $topics = is_array($decoded['topics'] ?? null) ? $decoded['topics'] : [];
            $normalized = [];
            foreach ($topics as $topic) {
                $label = trim((string) ($topic['topic'] ?? ''));
                if ($label === '') {
                    continue;
                }
                $normalized[] = [
                    'topic' => Str::limit($label, 255, ''),
                    'angle' => Str::limit((string) ($topic['angle'] ?? ''), 255, ''),
                    'score' => max(1, min(100, (int) ($topic['score'] ?? 60))),
                ];
            }

            if ($normalized === []) {
                return $this->fallbackTopics($funnel, $count, $extraContext);
            }

            return array_slice($this->uniqueByTopic($normalized), 0, $count);
        } catch (\Throwable) {
            return $this->fallbackTopics($funnel, $count, $extraContext);
        }
    }

    /**
     * @param  array<int, array{topic: string, angle: string|null, score: int}>  $topics
     */
    public function persist(Funnel $funnel, array $topics): void
    {
        FunnelPromotionTopicSuggestion::query()
            ->where('funnel_id', $funnel->id)
            ->where('status', FunnelPromotionTopicSuggestion::STATUS_SUGGESTED)
            ->delete();

        foreach ($topics as $topic) {
            FunnelPromotionTopicSuggestion::query()->create([
                'funnel_id' => $funnel->id,
                'topic' => $topic['topic'],
                'angle' => $topic['angle'] ?: null,
                'score' => $topic['score'],
                'status' => FunnelPromotionTopicSuggestion::STATUS_SUGGESTED,
            ]);
        }
    }

    private function topicPrompt(Funnel $funnel, int $count, ?string $extraContext): string
    {
        $settings = $funnel->settings;
        $templateKeywords = $funnel->template?->suggested_keywords ?? [];
        $trafficKeywords = $funnel->keywords()->pluck('name')->all();

        return json_encode([
            'funnel_name' => $funnel->name,
            'webinar_title' => $settings?->webinar_title,
            'webinar_description' => $settings?->webinar_description,
            'cta_url' => $settings?->effectiveTrafficAffiliateLink(),
            'template_keywords' => $templateKeywords,
            'traffic_keywords' => $trafficKeywords,
            'additional_context' => $extraContext,
            'required_count' => $count,
            'requirements' => [
                'mix awareness, consideration, and conversion topics',
                'include hooks, objections, and proof content angles',
                'prioritize practical postable topics',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array{topic: string, angle: string|null, score: int}>
     */
    private function fallbackTopics(Funnel $funnel, int $count, ?string $extraContext): array
    {
        $settings = $funnel->settings;
        $base = trim((string) ($settings?->webinar_title ?: $funnel->name));
        $context = trim((string) $extraContext);

        $seed = array_filter(array_map('trim', [
            $base.' quick win most people miss',
            $base.' mistakes that kill conversions',
            $base.' case study breakdown',
            'How to start '.$base.' in 2026',
            'Objection handling for '.$base,
            'Myth vs reality: '.$base,
            'Step-by-step framework for '.$base,
            'Before and after story around '.$base,
            'CTA examples that turn viewers into leads',
            'Common questions buyers ask before joining',
            'Email follow-up angle for '.$base,
            'Weekly content sequence for '.$base,
        ]));

        if ($context !== '') {
            $seed[] = 'Context-led angle: '.Str::limit($context, 80);
        }

        $topics = [];
        foreach (array_values($seed) as $i => $topic) {
            $topics[] = [
                'topic' => Str::limit($topic, 255, ''),
                'angle' => ['problem', 'proof', 'objection', 'how-to'][$i % 4],
                'score' => max(50, 95 - ($i * 3)),
            ];
        }

        return array_slice($this->uniqueByTopic($topics), 0, $count);
    }

    /**
     * @param  array<int, array{topic: string, angle: string|null, score: int}>  $topics
     * @return array<int, array{topic: string, angle: string|null, score: int}>
     */
    private function uniqueByTopic(array $topics): array
    {
        $seen = [];
        $result = [];
        foreach ($topics as $topic) {
            $key = mb_strtolower(trim($topic['topic']));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = $topic;
        }

        return $result;
    }
}
