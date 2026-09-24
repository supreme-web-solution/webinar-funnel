<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Ai\OpenRouterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PromotionTextGenerationService
{
    public function __construct(
        private readonly OpenRouterService $openRouter,
    ) {}

    /**
     * @return array{text_body: string, email_subject: string|null, email_body: string|null, hashtags: array<int, string>, source: string}
     */
    public function generate(Funnel $funnel, FunnelPromotionPost $post): array
    {
        if (! $this->openRouter->isConfigured()) {
            Log::info('[Promotion] PromotionTextGenerationService: no OpenRouter key configured, using fallback', [
                'post_id' => $post->id,
            ]);

            return $this->fallback($funnel, $post);
        }

        $model = $this->openRouter->promotionTextModel();
        $timeout = (int) config('promotion.openrouter.timeout', 90);

        Log::info('[Promotion] PromotionTextGenerationService: calling OpenRouter', [
            'post_id' => $post->id,
            'model' => $model,
            'topic' => $post->topic,
        ]);

        try {
            $result = $this->openRouter->chatJson(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->userPrompt($funnel, $post)],
                ],
                $model,
                $timeout,
                null,
                0.8,
                true,
            );

            if (! $result['ok'] || ! is_array($result['data'])) {
                Log::error('[Promotion] PromotionTextGenerationService: OpenRouter request failed', [
                    'post_id' => $post->id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                return $this->fallback($funnel, $post);
            }

            $decoded = $result['data'];

            Log::info('[Promotion] PromotionTextGenerationService: decoded response', [
                'post_id' => $post->id,
                'has_text_body' => ! empty($decoded['text_body']),
                'has_hashtags' => ! empty($decoded['hashtags']),
            ]);

            $textBody = trim((string) ($decoded['text_body'] ?? ''));
            $emailSubject = trim((string) ($decoded['email_subject'] ?? ''));
            $emailBody = trim((string) ($decoded['email_body'] ?? ''));
            $hashtags = collect($decoded['hashtags'] ?? [])
                ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
                ->map(fn (string $tag) => Str::startsWith($tag, '#') ? $tag : '#'.preg_replace('/\s+/', '', $tag))
                ->take(10)
                ->values()
                ->all();

            if ($textBody === '') {
                Log::warning('[Promotion] PromotionTextGenerationService: empty text_body from OpenRouter, using fallback', [
                    'post_id' => $post->id,
                ]);

                return $this->fallback($funnel, $post);
            }

            return [
                'text_body' => Str::limit($textBody, 20000, ''),
                'email_subject' => $emailSubject !== '' ? Str::limit($emailSubject, 255, '') : null,
                'email_body' => $emailBody !== '' ? Str::limit($emailBody, 20000, '') : null,
                'hashtags' => $hashtags,
                'source' => 'openrouter',
            ];
        } catch (\Throwable $e) {
            Log::error('[Promotion] PromotionTextGenerationService: exception', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return $this->fallback($funnel, $post);
        }
    }

    /**
     * Generate a spoken video script (~45–60 seconds) for D-ID avatar videos.
     */
    public function generateVideoScript(
        Funnel $funnel,
        string $topic,
        array $context = [],
        ?string $ctaUrl = null,
        ?string $ctaLabel = null,
    ): string {
        $ctaUrl = $ctaUrl ?: '#';
        $ctaLabel = $ctaLabel ?: 'Learn more';

        if (! $this->openRouter->isConfigured()) {
            return $this->fallbackVideoScript($funnel, $topic, $ctaUrl, $ctaLabel);
        }

        try {
            $settings = $funnel->settings;
            $result = $this->openRouter->chatJson(
                [
                    [
                        'role' => 'system',
                        'content' => 'You write spoken-word video scripts for AI avatar presenters. Return JSON only with key "script". The script must sound natural when read aloud, avoid markdown, and stay under 800 characters.',
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'funnel_name' => $funnel->name,
                            'topic' => $topic,
                            'cta_url' => $ctaUrl,
                            'cta_label' => $ctaLabel,
                            'webinar_title' => $settings?->webinar_title,
                            'webinar_description' => $settings?->webinar_description,
                            'extra_context' => $context['context'] ?? null,
                            'requirements' => [
                                '45-60 seconds when spoken (~120-180 words)',
                                'hook in the first sentence',
                                'one clear takeaway',
                                'end with a spoken CTA',
                                'no bullet points or hashtags',
                            ],
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ],
                ],
                $this->openRouter->promotionTextModel(),
                (int) config('promotion.openrouter.timeout', 90),
                null,
                0.75,
                true,
            );

            if (! $result['ok'] || ! is_array($result['data'])) {
                Log::warning('[Promotion] Video script OpenRouter request failed', [
                    'topic' => $topic,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                return $this->fallbackVideoScript($funnel, $topic, $ctaUrl, $ctaLabel);
            }

            $script = trim((string) ($result['data']['script'] ?? ''));

            if ($script === '') {
                return $this->fallbackVideoScript($funnel, $topic, $ctaUrl, $ctaLabel);
            }

            return mb_substr($script, 0, 800);
        } catch (\Throwable $e) {
            Log::error('[Promotion] Video script generation exception', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackVideoScript($funnel, $topic, $ctaUrl, $ctaLabel);
        }
    }

    private function fallbackVideoScript(Funnel $funnel, string $topic, string $ctaUrl, string $ctaLabel): string
    {
        return mb_substr(implode(' ', [
            "If you are struggling with {$topic}, this is for you.",
            'In the next minute, I will show you one clear strategy you can apply immediately.',
            'Use this framework to improve your results, avoid common mistakes, and move faster with confidence.',
            "When you are ready, click {$ctaLabel}".($ctaUrl !== '#' ? " at {$ctaUrl}" : '.'),
        ]), 0, 800);
    }

    private function systemPrompt(): string
    {
        return 'You are a senior direct-response marketer. Create rich, conversion-focused content. Return JSON only with keys text_body, email_subject, email_body, hashtags. Write practical, specific, high-volume copy with clear structure and CTA.';
    }

    private function userPrompt(Funnel $funnel, FunnelPromotionPost $post): string
    {
        $settings = $funnel->settings;

        $emailType = (string) ($post->generation_context['email_type'] ?? 'promotional');

        return json_encode([
            'funnel_name' => $funnel->name,
            'topic' => $post->topic,
            'content_type' => $post->content_type,
            'email_type' => $post->content_type === FunnelPromotionPost::TYPE_EMAIL ? $emailType : null,
            'publish_platforms' => $post->platforms ?? [],
            'cta_url' => $post->cta_url,
            'cta_label' => $post->cta_label,
            'webinar_title' => $settings?->webinar_title,
            'webinar_description' => $settings?->webinar_description,
            'extra_context' => $post->generation_context['context'] ?? null,
            'output_style' => 'rich',
            'requirements' => [
                'text_body: 180-400 words with hook, value, objection handling, CTA',
                'email_subject: concise and curiosity-driven',
                'email_body: optional unless email content type, then 250-500 words tailored to the email_type (promotional = direct offer, follow-up = nurture sequence tone, newsletter = educational value-first)',
                'hashtags: 5-10 relevant tags',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array{text_body: string, email_subject: string|null, email_body: string|null, hashtags: array<int, string>, source: string}
     */
    private function fallback(Funnel $funnel, FunnelPromotionPost $post): array
    {
        Log::info('[Promotion] PromotionTextGenerationService: using fallback content', ['post_id' => $post->id]);

        $topic = $post->topic ?: $funnel->name;
        $ctaUrl = $post->cta_url ?: '#';
        $ctaLabel = $post->cta_label ?: 'Learn more';

        $textBody = implode("\n\n", [
            "Most people struggle to turn attention into real funnel results around {$topic}.",
            'Here is the shift that changes outcomes: focus your message on one concrete pain point, then show proof with a clear before/after.',
            'In this campaign, lead with a bold hook, teach one practical move your audience can apply today, and end with a direct next step.',
            "If you want a done-for-you framework, click {$ctaLabel}: {$ctaUrl}",
        ]);

        $emailSubject = "Quick win for {$topic} this week";
        $emailBody = implode("\n\n", [
            'Hi there,',
            'If your audience is seeing your posts but not taking action, the issue is usually messaging clarity and CTA strength.',
            'This week, we are using a simple sequence: hook with a pain point, provide one tactical lesson, and invite the reader to the next step.',
            "Use this now: {$ctaUrl}",
            'Talk soon,',
            $funnel->name,
        ]);

        return [
            'text_body' => $textBody,
            'email_subject' => $post->content_type === FunnelPromotionPost::TYPE_EMAIL ? $emailSubject : null,
            'email_body' => $post->content_type === FunnelPromotionPost::TYPE_EMAIL ? $emailBody : null,
            'hashtags' => ['#marketing', '#growth', '#funnel', '#leadgeneration', '#digitalmarketing'],
            'source' => 'fallback',
        ];
    }
}
