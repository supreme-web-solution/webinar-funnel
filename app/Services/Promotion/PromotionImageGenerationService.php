<?php

namespace App\Services\Promotion;

use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Ai\OpenRouterService;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Content\PlatformFormatCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionImageGenerationService
{
    public function __construct(
        private readonly OpenRouterService $openRouter,
        private readonly PlatformFormatCatalog $formatCatalog,
        private readonly CarouselVisualDesignService $carouselDesign,
    ) {}

    /**
     * @return array{success: bool, url?: string, prompt: string, error?: string}
     */
    public function generate(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $built = $this->buildPrompt($funnel, $post);
        $prompt = $built['prompt'];
        $imageOptions = $built['options'];
        $model = $this->openRouter->promotionImageModel();

        if (! $this->openRouter->isConfigured()) {
            Log::warning('[Promotion] PromotionImageGenerationService: OpenRouter not configured', [
                'post_id' => $post->id,
            ]);

            return [
                'success' => false,
                'prompt' => $prompt,
                'error' => 'Image generation requires OpenRouter (OPENROUTER_API_KEY). Add a key in .env and retry.',
            ];
        }

        Log::info('[Promotion] PromotionImageGenerationService: calling OpenRouter Images', [
            'post_id' => $post->id,
            'model' => $model,
            'prompt' => substr($prompt, 0, 200),
        ]);

        try {
            $result = $this->openRouter->generateImage($prompt, $model, $imageOptions);

            Log::info('[Promotion] PromotionImageGenerationService: OpenRouter response', [
                'post_id' => $post->id,
                'ok' => $result['ok'],
                'has_url' => ! empty($result['url']),
                'has_b64' => ! empty($result['b64_json']),
            ]);

            if (! $result['ok']) {
                return [
                    'success' => false,
                    'prompt' => $prompt,
                    'error' => $result['error'] ?? 'Image generation failed.',
                ];
            }

            if (is_string($result['url'] ?? null) && $result['url'] !== '') {
                Log::info('[Promotion] PromotionImageGenerationService: got direct URL', ['post_id' => $post->id]);

                return ['success' => true, 'url' => $result['url'], 'prompt' => $prompt];
            }

            if (is_string($result['b64_json'] ?? null) && $result['b64_json'] !== '') {
                $storedUrl = $this->storeBinaryImage(base64_decode($result['b64_json'], true), $post->id);
                if ($storedUrl !== null) {
                    return ['success' => true, 'url' => $storedUrl, 'prompt' => $prompt];
                }

                Log::error('[Promotion] PromotionImageGenerationService: b64 decode or store failed', ['post_id' => $post->id]);
            }

            Log::error('[Promotion] PromotionImageGenerationService: no image payload in response', [
                'post_id' => $post->id,
            ]);

            return ['success' => false, 'prompt' => $prompt, 'error' => 'No image payload returned by OpenRouter'];
        } catch (\Throwable $e) {
            Log::error('[Promotion] PromotionImageGenerationService: exception', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'prompt' => $prompt, 'error' => $e->getMessage()];
        }
    }

    private function storeBinaryImage(mixed $binary, int $postId): ?string
    {
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        $folder = 'promotion-assets/'.date('Y/m');
        $publicId = Str::uuid()->toString();

        $cloudinary = app(CloudinaryService::class);
        if ($cloudinary->isConfigured()) {
            $url = $cloudinary->uploadBinary($binary, $folder, $publicId);
            if ($url) {
                Log::info('[Promotion] PromotionImageGenerationService: uploaded to Cloudinary', [
                    'post_id' => $postId,
                    'url' => $url,
                ]);

                return $url;
            }

            Log::warning('[Promotion] Cloudinary upload failed — falling back to local storage.', ['post_id' => $postId]);
        }

        $path = "{$folder}/{$publicId}.png";
        Storage::disk('public')->put($path, $binary);
        $url = Storage::disk('public')->url($path);

        Log::info('[Promotion] PromotionImageGenerationService: saved b64 image locally', [
            'post_id' => $postId,
            'path' => $path,
            'url' => $url,
        ]);

        return $url;
    }

    private function buildPrompt(Funnel $funnel, FunnelPromotionPost $post): array
    {
        $topic = $post->topic ?: $funnel->name;
        $cta = $post->cta_label ?: 'Learn More';
        $formatSpec = $this->formatSpecForPost($post);
        $payload = is_array($post->metadata['format_payload'] ?? null) ? $post->metadata['format_payload'] : [];

        $formatHint = $this->formatCatalog->imagePromptHint($formatSpec);
        $overlay = trim((string) ($payload['sticker_text'] ?? $payload['overlay_headline'] ?? $payload['pin_title'] ?? ''));
        if ($overlay !== '') {
            $formatHint .= "Include readable text overlay: \"{$overlay}\". ";
        }

        $prompt = 'Create a professional social media marketing image for the topic "'.$topic.'". '
            .$formatHint
            .'Design: editorial template aesthetic — generous whitespace (60%+), one clear focal point, minimal elements. '
            .'NOT generic AI art — no neon glow, no lens flare, no busy collages, no photorealistic faces, no watermarks. '
            .'Typography-led if text overlay is needed. Tone: professional, outcome-driven. CTA theme: '.$cta.'.';

        $options = [
            'n' => 1,
            'output_format' => 'png',
            'timeout' => (int) config('promotion.openrouter.timeout', 90),
            'size' => $this->formatCatalog->openRouterImageSize($formatSpec),
        ];

        return ['prompt' => $prompt, 'options' => $options];
    }

    /**
     * @param  array<string, mixed>  $slide
     * @param  array<string, mixed>|null  $formatSpec
     * @param  array<string, mixed>|null  $designSystem
     * @return array{success: bool, url?: string, prompt: string, error?: string}
     */
    public function generateCarouselSlide(
        Funnel $funnel,
        FunnelPromotionPost $post,
        array $slide,
        int $slideIndex,
        int $totalSlides,
        ?array $formatSpec,
        ?array $designSystem = null,
    ): array {
        $size = $this->formatCatalog->openRouterImageSize($formatSpec);

        if ($designSystem === null) {
            $payload = is_array($post->metadata['format_payload'] ?? null) ? $post->metadata['format_payload'] : [];
            $designSystem = is_array($payload['visual_design'] ?? null) ? $payload['visual_design'] : null;
        }

        if ($designSystem === null) {
            $slides = is_array($post->metadata['format_payload']['slides'] ?? null)
                ? $post->metadata['format_payload']['slides']
                : [];
            $designSystem = $this->carouselDesign->ensureDesignSystem($funnel, $post, $formatSpec, $slides);
        }

        $prompt = $this->carouselDesign->buildSlideImagePrompt(
            $funnel,
            $post,
            $slide,
            $slideIndex,
            $totalSlides,
            $formatSpec,
            $designSystem,
        );

        $options = [
            'n' => 1,
            'output_format' => 'png',
            'timeout' => (int) config('promotion.openrouter.timeout', 90),
            'size' => $size,
        ];

        if (! $this->openRouter->isConfigured()) {
            return [
                'success' => false,
                'prompt' => $prompt,
                'error' => 'Carousel slide images require OpenRouter (OPENROUTER_API_KEY).',
            ];
        }

        try {
            $result = $this->openRouter->generateImage($prompt, $this->openRouter->promotionImageModel(), $options);

            if (! ($result['ok'] ?? false)) {
                return [
                    'success' => false,
                    'prompt' => $prompt,
                    'error' => $result['error'] ?? 'Slide image generation failed.',
                ];
            }

            if (is_string($result['url'] ?? null) && $result['url'] !== '') {
                return ['success' => true, 'url' => $result['url'], 'prompt' => $prompt];
            }

            if (is_string($result['b64_json'] ?? null) && $result['b64_json'] !== '') {
                $storedUrl = $this->storeBinaryImage(base64_decode($result['b64_json'], true), $post->id);
                if ($storedUrl !== null) {
                    return ['success' => true, 'url' => $storedUrl, 'prompt' => $prompt];
                }
            }

            return ['success' => false, 'prompt' => $prompt, 'error' => 'No image payload returned for carousel slide'];
        } catch (\Throwable $e) {
            return ['success' => false, 'prompt' => $prompt, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatSpecForPost(FunnelPromotionPost $post): ?array
    {
        $formatKey = $post->metadata['format_key'] ?? $post->generation_context['content_format'] ?? null;

        return is_string($formatKey) && $formatKey !== ''
            ? $this->formatCatalog->format($formatKey)
            : null;
    }
}
