<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionTopicSuggestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PromotionTopicSuggestionService
{
    public function __construct(
        private readonly PromotionFunnelContextBuilder $contextBuilder,
    ) {}

    /**
     * @return array<int, array{topic: string, angle: string|null, score: int}>
     */
    public function generate(Funnel $funnel, int $count = 12, ?string $extraContext = null): array
    {
        $count = max(5, min($count, (int) config('promotion.max_sequence_size', 30)));
        $context = $this->contextBuilder->build($funnel);
        $apiKey = (string) config('services.openai.api_key', '');

        if ($apiKey === '') {
            return $this->fallbackTopics($context, $count, $extraContext);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('promotion.openai.timeout', 90))
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('promotion.openai.text_model', 'gpt-4o-mini'),
                    'temperature' => 0.85,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You generate social media post topics for promoting a specific webinar funnel and affiliate offer. '
                                .'Each topic must be a standalone, specific post idea tied to the product benefits — never paste the product name into a generic template. '
                                .'Do NOT use boilerplate webinar titles like "Watch this training completely to be our next success story". '
                                .'Output strict JSON: {"topics":[{"topic":"...","angle":"problem|proof|how-to|objection|cta","score":0-100}]}.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->topicPrompt($context, $count, $extraContext),
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                return $this->fallbackTopics($context, $count, $extraContext);
            }

            $json = $response->json();
            $items = $json['choices'][0]['message']['content'] ?? null;
            if (! is_string($items) || $items === '') {
                return $this->fallbackTopics($context, $count, $extraContext);
            }

            $decoded = json_decode($items, true);
            $topics = is_array($decoded['topics'] ?? null) ? $decoded['topics'] : [];
            $normalized = [];
            foreach ($topics as $topic) {
                $label = trim((string) ($topic['topic'] ?? ''));
                if ($label === '' || $this->isLowQualityTopic($label, $context)) {
                    continue;
                }
                $normalized[] = [
                    'topic' => Str::limit($label, 255, ''),
                    'angle' => Str::limit((string) ($topic['angle'] ?? ''), 255, ''),
                    'score' => max(1, min(100, (int) ($topic['score'] ?? 60))),
                ];
            }

            if ($normalized === []) {
                return $this->fallbackTopics($context, $count, $extraContext);
            }

            return array_slice($this->uniqueByTopic($normalized), 0, $count);
        } catch (\Throwable) {
            return $this->fallbackTopics($context, $count, $extraContext);
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

    /**
     * @param  array{
     *   product_name: string,
     *   category: string|null,
     *   conversion_style: string|null,
     *   optin_intro: string|null,
     *   webinar_description: string|null,
     *   bullet_points: list<string>,
     *   template_keywords: list<string>,
     *   traffic_keywords: list<string>,
     *   audience: string|null,
     *   cta_label: string|null,
     * }  $context
     */
    private function topicPrompt(array $context, int $count, ?string $extraContext): string
    {
        return json_encode([
            'product_or_offer' => $context['product_name'],
            'category' => $context['category'],
            'conversion_style' => $context['conversion_style'],
            'target_audience' => $context['audience'],
            'offer_summary' => $context['optin_intro'],
            'training_benefits' => array_slice($context['bullet_points'], 0, 12),
            'full_description_excerpt' => Str::limit((string) ($context['webinar_description'] ?? ''), 1200, ''),
            'keywords' => array_values(array_unique(array_merge(
                $context['template_keywords'],
                $context['traffic_keywords'],
            ))),
            'cta_label' => $context['cta_label'],
            'additional_context' => $extraContext,
            'required_count' => $count,
            'rules' => [
                'Each topic must be a specific social post angle about THIS product/offer and its benefits.',
                'Turn training bullets into hooks, myths, mistakes, proof angles, or how-to posts — do not repeat bullets verbatim unless they already read like a post title.',
                'Never start topics with generic webinar CTA copy.',
                'Never concatenate the product name into filler patterns like "quick win most people miss".',
                'Mix awareness, consideration, objection-handling, proof, and conversion posts.',
                'Topics should be short enough to use as a video/image post headline (under 120 characters when possible).',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array{
     *   product_name: string,
     *   category: string|null,
     *   conversion_style: string|null,
     *   optin_intro: string|null,
     *   webinar_description: string|null,
     *   bullet_points: list<string>,
     *   template_keywords: list<string>,
     *   traffic_keywords: list<string>,
     *   audience: string|null,
     *   cta_label: string|null,
     * }  $context
     * @return array<int, array{topic: string, angle: string|null, score: int}>
     */
    private function fallbackTopics(array $context, int $count, ?string $extraContext): array
    {
        $product = $context['product_name'];
        $angles = ['problem', 'proof', 'how-to', 'objection', 'cta', 'benefit'];
        $topics = [];
        $score = 95;

        foreach ($context['bullet_points'] as $index => $bullet) {
            $topic = $this->bulletToTopic($bullet, $product);
            if ($topic === null || $this->isLowQualityTopic($topic, $context)) {
                continue;
            }

            $topics[] = [
                'topic' => Str::limit($topic, 255, ''),
                'angle' => $angles[$index % count($angles)],
                'score' => max(55, $score - ($index * 2)),
            ];
        }

        if ($context['optin_intro'] !== null) {
            $topics[] = [
                'topic' => Str::limit('Why '.$product.' matters for '.$this->audienceLabel($context['audience']), 255, ''),
                'angle' => 'awareness',
                'score' => 72,
            ];
            $topics[] = [
                'topic' => Str::limit('3 reasons beginners choose '.$product.' over doing it manually', 255, ''),
                'angle' => 'proof',
                'score' => 70,
            ];
        }

        $topics = array_merge($topics, [
            [
                'topic' => Str::limit('Biggest mistake people make before trying '.$product, 255, ''),
                'angle' => 'objection',
                'score' => 68,
            ],
            [
                'topic' => Str::limit('What to expect inside the free '.$product.' training', 255, ''),
                'angle' => 'cta',
                'score' => 66,
            ],
            [
                'topic' => 'Common questions before joining the free training',
                'angle' => 'objection',
                'score' => 64,
            ],
        ]);

        if (trim((string) $extraContext) !== '') {
            $topics[] = [
                'topic' => Str::limit('Angle: '.trim($extraContext), 255, ''),
                'angle' => 'custom',
                'score' => 80,
            ];
        }

        return array_slice($this->uniqueByTopic($topics), 0, $count);
    }

    private function bulletToTopic(string $bullet, string $product): ?string
    {
        $bullet = trim($bullet);
        if ($bullet === '') {
            return null;
        }

        if (mb_strlen($bullet) <= 120) {
            return $bullet;
        }

        if (preg_match('/^(How to|Why|What|The)\s+/i', $bullet)) {
            return Str::limit($bullet, 120, '…');
        }

        return Str::limit($bullet, 110, '…').' ('.$product.')';
    }

    private function audienceLabel(?string $audience): string
    {
        return $audience ?: 'online entrepreneurs';
    }

    /**
     * @param  array{product_name: string}  $context
     */
    private function isLowQualityTopic(string $topic, array $context): bool
    {
        if ($this->contextBuilder->isGenericWebinarTitle($topic)) {
            return true;
        }

        $lower = mb_strtolower($topic);

        return str_contains($lower, 'watch this training completely')
            || str_contains($lower, 'quick win most people miss')
            || (
                str_contains($lower, 'mistakes that kill conversions')
                && str_contains($lower, mb_strtolower($context['product_name']))
            );
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
