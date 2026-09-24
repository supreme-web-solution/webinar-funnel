<?php

namespace App\Services\Content;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Ai\OpenRouterService;
use App\Services\Promotion\CarouselTextSlideRenderer;
use App\Services\Promotion\PromotionFunnelContextBuilder;
use App\Services\Promotion\PromotionTextGenerationService;
use App\Services\Social\TwitterTextLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Format-aware content generation — carousel slides, threads, reel scripts, etc.
 */
final class FormatContentGenerationService
{
    public function __construct(
        private readonly PlatformFormatCatalog $catalog,
        private readonly OpenRouterService $openRouter,
        private readonly PromotionFunnelContextBuilder $funnelContextBuilder,
        private readonly TwitterTextLimiter $twitterTextLimiter,
    ) {}

    /**
     * @return array{text_body: string, email_subject: string|null, email_body: string|null, hashtags: array<int, string>, source: string, format_payload?: array<string, mixed>}
     */
    public function generate(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $formatKey = $this->resolveFormatKey($post);
        $spec = $formatKey ? $this->catalog->format($formatKey) : null;

        if ($spec === null) {
            return app(PromotionTextGenerationService::class)->generate($funnel, $post);
        }

        $generator = (string) ($spec['generator'] ?? 'text');

        return match ($generator) {
            'carousel' => $this->generateCarousel($funnel, $post, $spec),
            'thread' => $this->generateThread($funnel, $post, $spec),
            'reel_script' => $this->generateReelScript($funnel, $post, $spec),
            'poll' => $this->generatePoll($funnel, $post, $spec),
            'longform_text', 'longform_outline' => $this->generateLongform($funnel, $post, $spec),
            'reddit_discussion', 'reddit_ama' => $this->generateReddit($funnel, $post, $spec),
            'pin' => $this->generatePin($funnel, $post, $spec),
            'live_outline' => $this->generateOutline($funnel, $post, $spec),
            'story' => $this->generateStory($funnel, $post, $spec),
            'image' => $this->generateImageCaption($funnel, $post, $spec),
            default => $this->generateStandardText($funnel, $post, $spec),
        };
    }

    protected function resolveFormatKey(FunnelPromotionPost $post): ?string
    {
        $fromMeta = $post->metadata['format_key'] ?? null;
        if (is_string($fromMeta) && $fromMeta !== '') {
            return $fromMeta;
        }

        $fromCtx = $post->generation_context['content_format'] ?? null;

        return is_string($fromCtx) && $fromCtx !== '' ? $fromCtx : null;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{text_body: string, email_subject: null, email_body: null, hashtags: array<int, string>, source: string, format_payload: array<string, mixed>}
     */
    protected function generateCarousel(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $limits = $this->catalog->slideLimits($spec);
        $slideTarget = $limits['render_max'];
        $slidesMin = min(max(2, (int) ($spec['slides_min'] ?? 5)), $slideTarget);
        $hashtagRange = $this->hashtagRange($spec);
        $lockedLayout = $this->lockedCarouselLayout($post);

        $systemContent = 'You create high-save carousel content for social media. Return JSON with keys: caption, slides (array of {headline, body}), hashtags (array)'
            .($lockedLayout === null ? ', visual_design (object with background, accent, text, headline_font, body_font, layout_type, layout, style)' : '')
            .'. Write conversion-focused copy: specific hooks, proof, objections handled, clear CTA — not generic motivation. '
            ."Generate EXACTLY {$slideTarget} slides — no more, no fewer. Slides are typography-first (headline + short body); do NOT describe photos or AI imagery. "
            .($lockedLayout === null
                ? 'visual_design: hex colors + layout_type (left_editorial|centered_hook|accent_header|card_inset|bottom_stack|side_stripe|bold_statement|dark_spotlight|dark_editorial) for one cohesive template on every slide. '
                : '')
            .'First slide = scroll-stopping hook. Last slide = CTA.';

        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => $systemContent,
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'platform' => $spec['platform'] ?? '',
                'slide_count' => $slideTarget,
                'slides_min' => $slidesMin,
                'slides_max' => $slideTarget,
                'aspect_ratio' => $spec['aspect_ratio'] ?? '4:5',
                'cta_url' => $post->cta_url,
                'cta_label' => $post->cta_label,
                'signals' => $spec['signals'] ?? [],
                'hashtags_count' => $hashtagRange,
                'content_angle' => $post->generation_context['angle'] ?? null,
                'conversion_goal' => $post->generation_context['conversion_goal'] ?? 'Drive clicks, saves, and sign-ups with specific actionable copy.',
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'caption' => "Swipe through: {$post->topic}",
            'slides' => $this->fallbackSlides($post->topic, $slideTarget),
            'hashtags' => ['#tips', '#growth'],
        ], $funnel, $post);

        $caption = trim((string) ($payload['caption'] ?? ''));
        $slides = is_array($payload['slides'] ?? null) ? $payload['slides'] : [];
        $slides = array_slice($slides, 0, $slideTarget);
        $visualDesign = $lockedLayout !== null
            ? CarouselTextSlideRenderer::lockedDesignForLayout($lockedLayout, (string) ($post->topic ?? ''))
            : (is_array($payload['visual_design'] ?? null) ? $payload['visual_design'] : null);

        $formatPayload = [
            'generator' => 'carousel',
            'slides' => $slides,
            'slide_count' => count($slides),
            'render_mode' => config('promotion.carousel.render_mode', 'text_template'),
        ];
        if ($visualDesign !== null) {
            $formatPayload['visual_design'] = $visualDesign;
        }

        return [
            'text_body' => $caption !== '' ? $caption : "Carousel: {$post->topic}",
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => $this->normalizeHashtags($payload['hashtags'] ?? [], $hashtagRange[1]),
            'source' => 'openai_format',
            'format_payload' => $formatPayload,
        ];
    }

    private function lockedCarouselLayout(FunnelPromotionPost $post): ?string
    {
        $context = is_array($post->generation_context) ? $post->generation_context : [];
        if (($context['carousel_ai_template'] ?? true) !== false) {
            return null;
        }

        $layout = strtolower(trim((string) ($context['carousel_layout_type'] ?? '')));

        return in_array($layout, CarouselTextSlideRenderer::LAYOUT_TYPES, true) ? $layout : null;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateThread(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $partsMin = max(1, (int) ($spec['thread_parts_min'] ?? 2));
        $partsMax = max($partsMin, (int) ($spec['thread_parts_max'] ?? $partsMin));
        $charTarget = min(
            (int) ($spec['char_per_part'] ?? 240),
            $this->twitterTextLimiter->generationTarget(),
        );
        $hardLimit = $this->twitterTextLimiter->limit();
        $partsInstruction = $partsMin === $partsMax
            ? "Generate EXACTLY {$partsMin} tweets in thread_parts — no more, no fewer."
            : "Generate between {$partsMin} and {$partsMax} tweets in thread_parts.";

        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => implode(' ', [
                'You write concise viral X/Twitter threads.',
                'Return JSON: thread_parts (array of strings), hashtags.',
                $partsInstruction,
                "CRITICAL: Each thread_parts item MUST be {$charTarget} characters or fewer (hard max {$hardLimit}; URLs count as 23 chars).",
                'Plain text only — no markdown, no **bold**, no [links](url) in thread_parts.',
                'One clear point per tweet. Keep the message sharp and complete within the limit.',
                $partsMin === $partsMax && $partsMax === 2
                    ? 'Put any CTA in tweet 2 if needed — do not add extra tweets.'
                    : 'Put external links ONLY in link_reply, never in thread_parts.',
            ]),
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'parts_min' => $partsMin,
                'parts_max' => $partsMax,
                'parts_exact' => $partsMin === $partsMax ? $partsMin : null,
                'char_per_part' => $charTarget,
                'hard_char_limit' => $hardLimit,
                'cta_url' => $post->cta_url,
                'cta_label' => $post->cta_label,
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'thread_parts' => $this->fallbackThread($post->topic, $partsMin, $charTarget),
            'link_reply' => "{$post->cta_label}: {$post->cta_url}",
            'hashtags' => [],
        ], $funnel, $post);

        $parts = is_array($payload['thread_parts'] ?? null) ? $payload['thread_parts'] : [];
        $parts = $this->twitterTextLimiter->clampParts(array_map('strval', $parts));
        $parts = array_values(array_slice($parts, 0, $partsMax));
        $linkReply = $partsMax <= 2
            ? ''
            : $this->twitterTextLimiter->clamp((string) ($payload['link_reply'] ?? ''));

        $ctaUrl = is_string($post->cta_url) ? trim($post->cta_url) : '';
        if ($ctaUrl !== '' && ! $this->twitterTextLimiter->isPublicHttpUrl($ctaUrl)) {
            $linkReply = $this->twitterTextLimiter->sanitizeForPublish($linkReply);
        }

        if (count($parts) < $partsMin) {
            $parts = $this->twitterTextLimiter->clampParts(
                $this->fallbackThread($post->topic, $partsMin, $charTarget),
            );
            $parts = array_values(array_slice($parts, 0, $partsMax));
        }

        return [
            'text_body' => implode("\n\n---\n\n", $parts),
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => $this->normalizeHashtags($payload['hashtags'] ?? [], 2),
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => 'thread',
                'thread_parts' => $parts,
                'link_reply' => $linkReply,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateReelScript(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $duration = $spec['duration_seconds'] ?? [15, 30];
        $min = is_array($duration) ? (int) ($duration[0] ?? 15) : 15;
        $max = is_array($duration) ? (int) ($duration[1] ?? 30) : 30;

        $textService = app(PromotionTextGenerationService::class);
        $script = $textService->generateVideoScript(
            $funnel,
            $post->topic,
            (array) ($post->generation_context ?? []),
            $post->cta_url,
            $post->cta_label,
        );

        return [
            'text_body' => $script,
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => $this->normalizeHashtags([], ($spec['hashtags_max'] ?? 5)),
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => 'reel_script',
                'duration_seconds' => [$min, $max],
                'hook_seconds' => 2,
                'on_screen_text' => Str::limit($post->topic, 80, ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generatePoll(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => 'Create an engagement poll post. Return JSON: question, options (array of 2-4 strings), follow_up_comment.',
        ], [
            'role' => 'user',
            'content' => json_encode(['topic' => $post->topic], JSON_UNESCAPED_UNICODE),
        ], [
            'question' => "What's your biggest challenge with {$post->topic}?",
            'options' => ['Getting started', 'Staying consistent', 'Converting traffic'],
            'follow_up_comment' => 'Drop your answer below — I reply to every comment.',
        ], $funnel, $post);

        $question = (string) ($payload['question'] ?? $post->topic);
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];

        return [
            'text_body' => $question."\n\n".implode("\n", array_map(fn ($o, $i) => ($i + 1).'. '.$o, $options, array_keys($options))),
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => [],
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => 'poll',
                'question' => $question,
                'options' => $options,
                'follow_up_comment' => (string) ($payload['follow_up_comment'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateLongform(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $base = $this->generateStandardText($funnel, $post, $spec);
        $base['format_payload'] = [
            'generator' => $spec['generator'] ?? 'longform_text',
            'sections' => ['hook', 'context', 'value', 'proof', 'cta'],
        ];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateReddit(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $titleMax = (int) ($spec['title_max_chars'] ?? 300);

        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => 'Write value-first Reddit posts that avoid self-promo tone. Return JSON: title, body. No direct sales pitch in title.',
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'title_max_chars' => $titleMax,
                'format' => $spec['generator'] ?? 'reddit_discussion',
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'title' => Str::limit("Discussion: {$post->topic}", $titleMax, ''),
            'body' => "I've been exploring {$post->topic} and wanted to share what worked for me.\n\nWhat's your experience?",
        ], $funnel, $post);

        $title = (string) ($payload['title'] ?? $post->topic);
        $body = (string) ($payload['body'] ?? '');

        return [
            'text_body' => "**{$title}**\n\n{$body}",
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => [],
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => $spec['generator'] ?? 'reddit_discussion',
                'title' => $title,
                'body' => $body,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generatePin(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => 'Create Pinterest Pin copy optimized for search. Return JSON: pin_title, pin_description, board_suggestion, keywords (array).',
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'aspect_ratio' => $spec['aspect_ratio'] ?? '2:3',
                'cta_url' => $post->cta_url,
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'pin_title' => $post->topic,
            'pin_description' => "Learn more about {$post->topic}. Save for later.",
            'board_suggestion' => 'Marketing Tips',
            'keywords' => explode(' ', Str::slug($post->topic, ' ')),
        ], $funnel, $post);

        $title = (string) ($payload['pin_title'] ?? $post->topic);
        $desc = (string) ($payload['pin_description'] ?? '');

        return [
            'text_body' => "{$title}\n\n{$desc}",
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => [],
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => 'pin',
                'pin_title' => $title,
                'pin_description' => $desc,
                'board_suggestion' => (string) ($payload['board_suggestion'] ?? ''),
                'keywords' => is_array($payload['keywords'] ?? null) ? $payload['keywords'] : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateStory(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => 'Create Instagram/Facebook Story content. Return JSON: caption (short, optional), sticker_text (bold overlay text), poll_question (optional), hashtags (array).',
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'platform' => $spec['platform'] ?? 'instagram',
                'aspect_ratio' => $spec['aspect_ratio'] ?? '9:16',
                'cta_url' => $post->cta_url,
                'cta_label' => $post->cta_label,
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'caption' => '',
            'sticker_text' => Str::limit($post->topic, 60, ''),
            'hashtags' => [],
        ], $funnel, $post);

        $sticker = trim((string) ($payload['sticker_text'] ?? $post->topic));
        $caption = trim((string) ($payload['caption'] ?? ''));

        return [
            'text_body' => $caption !== '' ? $caption : $sticker,
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => $this->normalizeHashtags($payload['hashtags'] ?? [], (int) ($spec['hashtags_max'] ?? 3)),
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => 'story',
                'sticker_text' => $sticker,
                'poll_question' => (string) ($payload['poll_question'] ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateImageCaption(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $base = $this->generateStandardText($funnel, $post, $spec);
        $base['format_payload'] = [
            'generator' => 'image',
            'overlay_headline' => Str::limit($post->topic, 80, ''),
            'aspect_ratio' => $spec['aspect_ratio'] ?? null,
            'target_size' => $spec['size'] ?? null,
        ];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateOutline(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $base = $this->generateStandardText($funnel, $post, $spec);
        $base['format_payload'] = [
            'generator' => $spec['generator'] ?? 'live_outline',
            'outline_bullets' => [
                'Hook & promise',
                'Main teaching points',
                'Q&A prompts',
                'CTA',
            ],
        ];

        return $base;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    protected function generateStandardText(Funnel $funnel, FunnelPromotionPost $post, array $spec): array
    {
        $hashtagRange = $this->hashtagRange($spec);
        $hookChars = (int) ($spec['hook_chars'] ?? 0);
        $charIdeal = $spec['char_ideal'] ?? null;

        $requirements = ['Return JSON: text_body, hashtags'];
        if ($hookChars > 0) {
            $requirements[] = "First {$hookChars} characters must be a strong hook before 'see more' cutoff";
        }
        if (is_array($charIdeal)) {
            $requirements[] = 'Ideal length '.($charIdeal[0] ?? 71).'-'.($charIdeal[1] ?? 100).' characters for max engagement';
        }

        $payload = $this->callOpenAi([
            'role' => 'system',
            'content' => 'You are a senior social media copywriter focused on measurable growth (clicks, saves, sign-ups). '.implode('. ', $requirements),
        ], [
            'role' => 'user',
            'content' => json_encode([
                'topic' => $post->topic,
                'platform' => $spec['platform'] ?? '',
                'format' => $spec['label'] ?? '',
                'cta_url' => $post->cta_url,
                'cta_label' => $post->cta_label,
                'signals' => $spec['signals'] ?? [],
                'hashtags_count' => $hashtagRange,
                'extra_context' => $post->generation_context['context'] ?? null,
                'content_angle' => $post->generation_context['angle'] ?? null,
                'conversion_goal' => $post->generation_context['conversion_goal'] ?? null,
            ], JSON_UNESCAPED_UNICODE),
        ], [
            'text_body' => "Here's what most people miss about {$post->topic} — and the one shift that changes everything.",
            'hashtags' => ['#marketing'],
        ], $funnel, $post);

        $textBody = trim((string) ($payload['text_body'] ?? ''));

        return [
            'text_body' => $textBody !== '' ? $textBody : "Post about {$post->topic}",
            'email_subject' => null,
            'email_body' => null,
            'hashtags' => $this->normalizeHashtags($payload['hashtags'] ?? [], $hashtagRange[1]),
            'source' => 'openai_format',
            'format_payload' => [
                'generator' => $spec['generator'] ?? 'text',
            ],
        ];
    }

    /**
     * @param  array{role: string, content: string}  $system
     * @param  array{role: string, content: string}  $user
     * @param  array<string, mixed>  $fallback
     * @return array<string, mixed>
     */
    protected function callOpenAi(
        array $system,
        array $user,
        array $fallback,
        ?Funnel $funnel = null,
        ?FunnelPromotionPost $post = null,
    ): array {
        if ($funnel !== null && $post !== null) {
            $decoded = json_decode($user['content'], true);
            if (is_array($decoded)) {
                $user['content'] = json_encode(
                    $this->enrichUserPayload($funnel, $post, $decoded),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                );
            }
        }

        if (! $this->openRouter->isConfigured()) {
            return $fallback;
        }

        try {
            $result = $this->openRouter->chatJson(
                [$system, $user],
                $this->openRouter->promotionTextModel(),
                (int) config('promotion.openrouter.timeout', 90),
                null,
                0.8,
                true,
            );

            if (! $result['ok'] || ! is_array($result['data'])) {
                return $fallback;
            }

            return $result['data'];
        } catch (\Throwable $e) {
            Log::warning('[FormatContentGeneration] OpenRouter failed', ['error' => $e->getMessage()]);

            return $fallback;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function enrichUserPayload(Funnel $funnel, FunnelPromotionPost $post, array $payload): array
    {
        $campaignContext = $this->campaignContextBlock($funnel, $post);
        if ($campaignContext !== []) {
            $payload['campaign_context'] = $campaignContext;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function campaignContextBlock(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $funnel->loadMissing('campaign', 'settings');
        $ctx = $this->funnelContextBuilder->build($funnel);

        $block = [
            'product_name' => $ctx['product_name'] ?? null,
            'audience' => $ctx['audience'] ?? null,
            'value_proposition' => $ctx['optin_intro'] ?? null,
            'webinar_description' => $ctx['webinar_description'] ?? null,
            'key_bullets' => array_values(array_slice((array) ($ctx['bullet_points'] ?? []), 0, 6)),
            'content_angle' => $post->generation_context['angle'] ?? null,
            'conversion_goal' => $post->generation_context['conversion_goal'] ?? null,
            'topic_source' => $post->generation_context['topic_source'] ?? null,
        ];

        $pass1 = is_array($ctx['knowledge_pass1'] ?? null) ? $ctx['knowledge_pass1'] : [];
        if ($pass1 !== []) {
            $block['unique_mechanism'] = $pass1['unique_mechanism'] ?? null;
            $block['pain_points'] = array_values(array_slice((array) ($pass1['core_pain_points'] ?? []), 0, 4));
            $block['desired_outcomes'] = array_values(array_slice((array) ($pass1['desired_outcomes'] ?? []), 0, 4));

            $objections = [];
            foreach (array_slice((array) ($pass1['objections'] ?? []), 0, 3) as $objection) {
                $objections[] = is_array($objection)
                    ? trim((string) ($objection['objection'] ?? ''))
                    : trim((string) $objection);
            }
            $block['objections'] = array_values(array_filter($objections));
        }

        $pass2 = is_array($ctx['knowledge_pass2'] ?? null) ? $ctx['knowledge_pass2'] : [];
        if ($pass2 !== []) {
            $proof = [];
            foreach (['bullets', 'benefits', 'proof_points'] as $key) {
                foreach ((array) ($pass2[$key] ?? []) as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $proof[] = trim($item);
                    }
                }
            }
            $block['proof_points'] = array_values(array_slice(array_unique($proof), 0, 4));
        }

        if (! empty($post->generation_context['knowledge_brief'])) {
            $block['planning_brief'] = $post->generation_context['knowledge_brief'];
        }

        return array_filter(
            $block,
            fn ($value): bool => $value !== null && $value !== [] && $value !== '',
        );
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{0: int, 1: int}
     */
    protected function hashtagRange(array $spec): array
    {
        $min = (int) ($spec['hashtags_min'] ?? 0);
        $max = (int) ($spec['hashtags_max'] ?? 5);

        return [$min, max($min, $max)];
    }

    /**
     * @return list<string>
     */
    protected function normalizeHashtags(mixed $tags, int $max): array
    {
        return collect(is_array($tags) ? $tags : [])
            ->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')
            ->map(fn (string $tag) => Str::startsWith($tag, '#') ? $tag : '#'.preg_replace('/\s+/', '', $tag))
            ->take($max)
            ->values()
            ->all();
    }

    /**
     * @return list<array{headline: string, body: string}>
     */
    protected function fallbackSlides(string $topic, int $count): array
    {
        $slides = [];
        for ($i = 1; $i <= $count; $i++) {
            $slides[] = [
                'headline' => $i === 1 ? "Stop scrolling — {$topic}" : "Point {$i}",
                'body' => $i === $count
                    ? 'Save this + follow for more.'
                    : "Key insight #{$i} about {$topic}.",
            ];
        }

        return $slides;
    }

    /**
     * @return list<string>
     */
    protected function fallbackThread(string $topic, int $parts, int $charLimit): array
    {
        $limiter = $this->twitterTextLimiter;
        $chunks = [];
        for ($i = 1; $i <= $parts; $i++) {
            $text = $i === 1
                ? "Thread on {$topic} 🧵"
                : ($i === $parts ? 'If this helped, RT the first tweet.' : "Lesson {$i}: practical tip about {$topic}.");

            $chunks[] = $limiter->clamp($text, min($charLimit, $limiter->limit()));
        }

        return $chunks;
    }
}
