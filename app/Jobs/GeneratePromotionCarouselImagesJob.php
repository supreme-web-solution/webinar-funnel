<?php

namespace App\Jobs;

use App\Models\FunnelPromotionAsset;
use App\Models\FunnelPromotionPost;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Promotion\CarouselTextSlideRenderer;
use App\Services\Promotion\CarouselVisualDesignService;
use App\Services\Promotion\PromotionGenerationCoordinator;
use App\Services\Promotion\PromotionImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePromotionCarouselImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(
        PromotionImageGenerationService $imageService,
        PromotionGenerationCoordinator $coordinator,
        PlatformFormatCatalog $catalog,
        CarouselVisualDesignService $designService,
        CarouselTextSlideRenderer $textSlideRenderer,
    ): void {
        Log::info('[Promotion] GeneratePromotionCarouselImagesJob started', ['post_id' => $this->postId]);

        $post = FunnelPromotionPost::query()->with('funnel')->find($this->postId);
        if (! $post || ! $post->funnel) {
            Log::warning('[Promotion] GeneratePromotionCarouselImagesJob: post or funnel missing', ['post_id' => $this->postId]);

            return;
        }

        $metadata = (array) ($post->metadata ?? []);
        $payload = is_array($metadata['format_payload'] ?? null) ? $metadata['format_payload'] : [];
        $slides = is_array($payload['slides'] ?? null) ? $payload['slides'] : [];

        if ($slides === []) {
            Log::warning('[Promotion] GeneratePromotionCarouselImagesJob: no slides on post', ['post_id' => $this->postId]);
            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Carousel slide copy was not generated.',
            ]);

            return;
        }

        $formatKey = $metadata['format_key'] ?? $post->generation_context['content_format'] ?? null;
        $formatSpec = is_string($formatKey) && $formatKey !== '' ? $catalog->format($formatKey) : null;
        $limits = $catalog->slideLimits($formatSpec);
        $slides = array_slice($slides, 0, $limits['render_max']);
        $totalSlides = count($slides);
        $renderMode = (string) config('promotion.carousel.render_mode', 'text_template');
        $useAiPhotos = $renderMode === 'ai_image';

        $visualDesign = $designService->ensureDesignSystem($post->funnel, $post, $formatSpec, $slides);
        $layoutType = $visualDesign['layout_type'] ?? 'left_editorial';
        $payload['visual_design'] = $visualDesign;
        $payload['slides'] = $slides;
        $payload['slide_count'] = $totalSlides;
        $payload['render_mode'] = $useAiPhotos ? 'ai_image' : 'text_template';
        $metadata['format_payload'] = $payload;
        $post->update(['metadata' => $metadata]);

        $this->persistSlideProgress($post, $metadata, $payload, $slides, 0, $totalSlides, null, 'Starting slide images…');

        $updatedSlides = $slides;
        $firstAssetId = null;
        $failures = 0;

        foreach ($slides as $index => $slide) {
            if (! is_array($slide)) {
                continue;
            }

            $asset = $post->assets()
                ->where('asset_type', FunnelPromotionAsset::TYPE_IMAGE)
                ->where('meta->slide_index', $index)
                ->first();

            if (! $asset) {
                $asset = $post->assets()->create([
                    'asset_type' => FunnelPromotionAsset::TYPE_IMAGE,
                    'provider' => $useAiPhotos ? 'ai_image' : 'text_template',
                    'status' => FunnelPromotionAsset::STATUS_PENDING,
                    'meta' => ['slide_index' => $index],
                ]);
            }

            $asset->update(['status' => FunnelPromotionAsset::STATUS_PROCESSING]);

            if ($useAiPhotos) {
                $result = $imageService->generateCarouselSlide(
                    $post->funnel,
                    $post,
                    $slide,
                    $index,
                    $totalSlides,
                    $formatSpec,
                    $visualDesign,
                );
            } else {
                $result = $textSlideRenderer->renderAndStore($post, $slide, $index, $totalSlides, $formatSpec, $visualDesign);
                $result['prompt'] = 'text_template:'.$layoutType.' slide '.($index + 1);
            }

            if (! ($result['success'] ?? false) || empty($result['url'])) {
                $failures++;
                $asset->update([
                    'status' => FunnelPromotionAsset::STATUS_FAILED,
                    'source_prompt' => $result['prompt'] ?? null,
                    'meta' => ['slide_index' => $index, 'error' => $result['error'] ?? 'Slide image failed'],
                ]);

                continue;
            }

            $asset->update([
                'status' => FunnelPromotionAsset::STATUS_READY,
                'source_prompt' => $result['prompt'] ?? null,
                'url' => $result['url'],
                'thumbnail_url' => $result['url'],
                'meta' => [
                    'slide_index' => $index,
                    'provider' => $useAiPhotos ? 'ai_image' : 'text_template',
                    'layout_type' => $layoutType,
                ],
            ]);

            if ($firstAssetId === null) {
                $firstAssetId = $asset->id;
            }

            $updatedSlides[$index] = array_merge($slide, [
                'image_url' => $result['url'],
            ]);

            $metadata = (array) ($post->fresh()->metadata ?? []);
            $payload = is_array($metadata['format_payload'] ?? null) ? $metadata['format_payload'] : [];

            $this->persistSlideProgress(
                $post,
                $metadata,
                $payload,
                $updatedSlides,
                $index + 1,
                $totalSlides,
                $firstAssetId,
            );
        }

        $successfulImages = collect($updatedSlides)
            ->filter(fn ($slide) => is_array($slide) && is_string($slide['image_url'] ?? null) && $slide['image_url'] !== '')
            ->count();

        if ($successfulImages < min(2, $limits['min'])) {
            $metadata = (array) ($post->fresh()->metadata ?? []);
            unset($metadata['generation_progress']);
            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Carousel needs at least '.min(2, $limits['min']).' slide images — only '.$successfulImages.' generated.',
                'metadata' => $metadata,
            ]);

            return;
        }

        $metadata = (array) ($post->fresh()->metadata ?? []);
        $payload = is_array($metadata['format_payload'] ?? null) ? $metadata['format_payload'] : [];
        $metadata['format_payload'] = array_merge($payload, [
            'generator' => 'carousel',
            'slides' => array_slice($updatedSlides, 0, $totalSlides),
            'slide_count' => $totalSlides,
            'slide_images_generated' => $successfulImages,
            'render_mode' => $useAiPhotos ? 'ai_image' : 'text_template',
        ]);
        unset($metadata['generation_progress']);

        $post->update([
            'metadata' => $metadata,
            'primary_asset_id' => $firstAssetId,
            'last_error' => $failures > 0 ? "{$failures} carousel slide image(s) failed — post still usable." : null,
        ]);

        $coordinator->maybeFinalize($post->fresh(['primaryAsset']));

        Log::info('[Promotion] GeneratePromotionCarouselImagesJob done', [
            'post_id' => $this->postId,
            'slide_images' => $successfulImages,
            'layout_type' => $layoutType,
            'failures' => $failures,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $slides
     */
    private function persistSlideProgress(
        FunnelPromotionPost $post,
        array $metadata,
        array $payload,
        array $slides,
        int $current,
        int $total,
        ?int $primaryAssetId,
        ?string $message = null,
    ): void {
        $metadata['format_payload'] = array_merge($payload, [
            'generator' => 'carousel',
            'slides' => $slides,
        ]);

        if ($total > 0) {
            $metadata['generation_progress'] = [
                'phase' => 'slide_images',
                'current' => $current,
                'total' => $total,
                'message' => $message ?? ($current >= $total
                    ? 'Finalizing carousel…'
                    : "Generating slide {$current} of {$total}…"),
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $update = ['metadata' => $metadata];
        if ($primaryAssetId !== null) {
            $update['primary_asset_id'] = $primaryAssetId;
        }

        $post->update($update);
    }
}
