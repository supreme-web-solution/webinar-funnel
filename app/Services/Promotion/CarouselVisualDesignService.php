<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Ai\OpenRouterService;
use App\Services\Content\PlatformFormatCatalog;
use Illuminate\Support\Facades\Log;

/**
 * Builds a shared visual design system for multi-slide carousels so every slide
 * in ONE post shares the same AI-chosen template — not random one-off art per slide.
 */
class CarouselVisualDesignService
{
    public function __construct(
        private readonly OpenRouterService $openRouter,
        private readonly PlatformFormatCatalog $formatCatalog,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $slides
     * @return array<string, mixed>
     */
    public function ensureDesignSystem(
        Funnel $funnel,
        FunnelPromotionPost $post,
        ?array $formatSpec,
        array $slides,
    ): array {
        $payload = is_array($post->metadata['format_payload'] ?? null)
            ? $post->metadata['format_payload']
            : [];
        $existing = $payload['visual_design'] ?? null;

        $lockedLayout = $this->lockedLayoutType($post);

        if (is_array($existing) && trim((string) ($existing['background'] ?? '')) !== '') {
            if ($lockedLayout !== null) {
                $existing['layout_type'] = $lockedLayout;
                $existing['source'] = 'user_locked';
            }

            return $this->normalizeDesignSystem($existing);
        }

        $topic = $post->topic ?: $funnel->name;

        if ($lockedLayout !== null) {
            return $this->normalizeDesignSystem(
                CarouselTextSlideRenderer::lockedDesignForLayout($lockedLayout, $topic),
            );
        }

        // AI art-directs a unique template for this carousel; slides inherit it.
        $design = $this->generateViaAi($topic, $formatSpec, $slides)
            ?? $this->minimalFallbackDesign($topic, $formatSpec);

        return $this->normalizeDesignSystem($design);
    }

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>  $designSystem
     */
    public function buildSlideImagePrompt(
        Funnel $funnel,
        FunnelPromotionPost $post,
        array $slide,
        int $slideIndex,
        int $totalSlides,
        ?array $formatSpec,
        array $designSystem,
    ): string {
        $headline = trim((string) ($slide['headline'] ?? ''));
        $body = trim((string) ($slide['body'] ?? ''));
        $topic = $post->topic ?: $funnel->name;
        $slideNumber = $slideIndex + 1;
        $platform = is_array($formatSpec) ? (string) ($formatSpec['platform'] ?? 'social') : 'social';
        $label = is_array($formatSpec) ? (string) ($formatSpec['label'] ?? 'carousel') : 'carousel';

        $formatBlock = $this->formatCatalog->carouselImageFormatBlock($formatSpec);
        $designBlock = $this->designSystemPromptBlock($designSystem);
        $roleHint = match (true) {
            $slideIndex === 0 => 'Cover hook slide — strong headline, minimal supporting text.',
            $slideIndex === $totalSlides - 1 => 'Final slide — same layout with clear CTA emphasis.',
            default => 'Value slide — same layout, one clear idea per slide.',
        };

        return implode(' ', array_filter([
            "Slide {$slideNumber} of {$totalSlides} for a {$platform} {$label} about \"{$topic}\".",
            $formatBlock,
            'CRITICAL — this slide is part of a swipe carousel series. Every slide MUST look like the same designed template:',
            $designBlock,
            'Only the headline/body text changes per slide — background, colors, fonts, alignment, margins, and layout grid stay identical.',
            $roleHint,
            $headline !== '' ? "Headline text (exact): \"{$headline}\"." : '',
            $body !== '' ? "Supporting text (exact): \"{$body}\"." : '',
            $this->qualityGuardrails(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $designSystem
     */
    public function designSystemPromptBlock(array $designSystem): string
    {
        $parts = [
            'Background: '.($designSystem['background'] ?? 'solid off-white'),
            'Accent color: '.($designSystem['accent'] ?? 'single brand accent'),
            'Text color: '.($designSystem['text'] ?? 'dark neutral'),
            'Headline typography: '.($designSystem['headline_font'] ?? 'bold modern sans-serif'),
            'Body typography: '.($designSystem['body_font'] ?? 'regular clean sans-serif'),
            'Visual style: '.($designSystem['style'] ?? 'editorial minimal'),
        ];

        if (! empty($designSystem['layout_type'])) {
            $parts[] = 'Layout type: '.$designSystem['layout_type'];
        }
        if (! empty($designSystem['layout'])) {
            $parts[] = 'Layout notes: '.$designSystem['layout'];
        }

        return implode('. ', $parts).'.';
    }

    /**
     * @param  list<array<string, mixed>>  $slides
     * @return array<string, mixed>|null
     */
    private function generateViaAi(string $topic, ?array $formatSpec, array $slides): ?array
    {
        if (! $this->openRouter->isConfigured()) {
            return null;
        }

        $platform = is_array($formatSpec) ? (string) ($formatSpec['platform'] ?? '') : '';
        $headlines = collect($slides)
            ->take(4)
            ->map(fn ($s) => is_array($s) ? trim((string) ($s['headline'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();

        $layoutOptions = implode(', ', CarouselTextSlideRenderer::availableLayoutTypes());

        $result = $this->openRouter->chatJson([
            [
                'role' => 'system',
                'content' => 'You are a social media art director for typography carousel slides. Return ONLY JSON with keys: background, accent, text, headline_font, body_font, layout_type, layout, style. '
                    ."layout_type must be exactly one of: {$layoutOptions}. Pick the layout that best fits the topic and tone. "
                    .'Most carousels use light backgrounds (background "#F7F5F0", accent "#0F766E", text "#18181B"). '
                    .'Use dark_spotlight or dark_editorial only occasionally (~15%) for bold/edgy topics — then use dark background "#121214", accent "#38BDBA", text "#F5F5F4". '
                    .'Layouts must look structurally different — do not pick the same layout every time. '
                    .'One template per carousel — all slides share the same layout_type and colors.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'topic' => $topic,
                    'platform' => $platform,
                    'aspect_ratio' => $formatSpec['aspect_ratio'] ?? '4:5',
                    'slide_headlines' => $headlines,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], $this->openRouter->promotionTextModel(), 30, 400, 0.2, true);

        if (! ($result['ok'] ?? false) || ! is_array($result['data'] ?? null)) {
            Log::info('[Promotion] CarouselVisualDesignService: AI design fallback', [
                'error' => $result['error'] ?? 'no data',
            ]);

            return null;
        }

        return $result['data'];
    }

    /**
     * Meta-instructions for the image model when AI art direction is unavailable.
     *
     * @return array<string, mixed>
     */
    private function minimalFallbackDesign(string $topic, ?array $formatSpec): array
    {
        return [
            'background' => '#F7F5F0',
            'accent' => '#0F766E',
            'text' => '#18181B',
            'headline_font' => 'Bold sans-serif',
            'body_font' => 'Clean sans-serif',
            'layout_type' => 'left_editorial',
            'layout' => 'Left editorial with accent bar',
            'style' => 'Typography-first carousel for "'.$topic.'"',
            'source' => 'minimal_fallback',
        ];
    }

    /**
     * @param  array<string, mixed>  $design
     * @return array<string, mixed>
     */
    private function normalizeDesignSystem(array $design): array
    {
        return [
            'background' => $this->stringField($design, 'background', '#F7F5F0'),
            'accent' => $this->stringField($design, 'accent', '#0F766E'),
            'text' => $this->stringField($design, 'text', '#18181B'),
            'headline_font' => $this->stringField($design, 'headline_font', 'bold modern sans-serif'),
            'body_font' => $this->stringField($design, 'body_font', 'clean sans-serif'),
            'layout_type' => app(CarouselTextSlideRenderer::class)->resolveLayoutType($design),
            'layout' => $this->stringField($design, 'layout', 'left editorial'),
            'style' => $this->stringField($design, 'style', 'editorial minimal'),
            'source' => $this->stringField($design, 'source', 'generated'),
        ];
    }

    /**
     * @param  array<string, mixed>  $design
     */
    private function stringField(array $design, string $key, string $default): string
    {
        $value = $design[$key] ?? $default;

        return trim(is_string($value) ? $value : $default);
    }

    /**
     * When the user turns off AI template selection, use their chosen layout.
     */
    private function lockedLayoutType(FunnelPromotionPost $post): ?string
    {
        $context = is_array($post->generation_context) ? $post->generation_context : [];
        $aiPicks = ($context['carousel_ai_template'] ?? true) !== false;
        if ($aiPicks) {
            return null;
        }

        $layout = strtolower(trim((string) ($context['carousel_layout_type'] ?? '')));

        return in_array($layout, CarouselTextSlideRenderer::LAYOUT_TYPES, true) ? $layout : null;
    }

    private function qualityGuardrails(): string
    {
        return 'Quality rules: looks like a professional Canva carousel template — NOT generic AI art. '
            .'60%+ whitespace. Max one small visual besides text. No watermarks, no neon glow, no lens flare, '
            .'no photorealistic faces, no cluttered stock montages, no random background changes between slides.';
    }
}
