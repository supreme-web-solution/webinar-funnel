<?php

namespace App\Services\Promotion;

use App\Jobs\GeneratePromotionCarouselImagesJob;
use App\Jobs\GeneratePromotionImageJob;
use App\Jobs\GeneratePromotionTextJob;
use App\Jobs\GeneratePromotionVideoJob;
use App\Models\FunnelPromotionPost;
use App\Services\Content\PlatformFormatCatalog;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches promotion asset generation jobs for a post (shared by UI + Content Employee).
 */
final class PromotionGenerationDispatcher
{
    public function __construct(
        private readonly PlatformFormatCatalog $formatCatalog,
    ) {}

    /**
     * @param  list<string>  $types  text|image|video|email content types to generate
     */
    public function dispatch(FunnelPromotionPost $post, array $types, bool $waitForVideo = false): void
    {
        $types = array_values(array_unique($types));

        if (in_array(FunnelPromotionPost::TYPE_VIDEO, $types, true)) {
            $renderProvider = (string) (
                data_get($post->metadata, 'video_render_provider')
                ?? data_get($post->generation_context, 'video_render_provider')
                ?? 'avatar_did'
            );

            if ($renderProvider !== 'avatar_did') {
                Log::warning('[Promotion] dispatch: video provider not implemented', [
                    'post_id' => $post->id,
                    'video_render_provider' => $renderProvider,
                ]);

                $post->update([
                    'status' => FunnelPromotionPost::STATUS_FAILED,
                    'last_error' => 'Only D-ID avatar video is supported. Re-create this post with the video wizard or set render mode to avatar_did.',
                ]);

                return;
            }
        }

        if ($this->usesMultiSlideImages($post)) {
            $types = array_values(array_filter(
                $types,
                fn (string $type): bool => $type !== FunnelPromotionPost::TYPE_IMAGE,
            ));
        }

        if (in_array(FunnelPromotionPost::TYPE_TEXT, $types, true)
            || in_array(FunnelPromotionPost::TYPE_EMAIL, $types, true)) {
            Log::info('[Promotion] dispatch: text job', ['post_id' => $post->id]);
            GeneratePromotionTextJob::dispatch($post->id);
        }

        if (in_array(FunnelPromotionPost::TYPE_IMAGE, $types, true)) {
            if ($this->usesMultiSlideImages($post)) {
                $slides = data_get($post->metadata, 'format_payload.slides', []);
                if (is_array($slides) && $slides !== []) {
                    Log::info('[Promotion] dispatch: carousel slide images', ['post_id' => $post->id]);
                    GeneratePromotionCarouselImagesJob::dispatch($post->id);
                } elseif (! in_array(FunnelPromotionPost::TYPE_TEXT, $types, true)) {
                    Log::info('[Promotion] dispatch: carousel needs slide copy first', ['post_id' => $post->id]);
                    GeneratePromotionTextJob::dispatch($post->id);
                }
            } else {
                Log::info('[Promotion] dispatch: image job', ['post_id' => $post->id]);
                GeneratePromotionImageJob::dispatch($post->id);
            }
        }

        if (in_array(FunnelPromotionPost::TYPE_VIDEO, $types, true)) {
            Log::info('[Promotion] dispatch: video job', ['post_id' => $post->id, 'sync' => $waitForVideo]);
            if ($waitForVideo) {
                GeneratePromotionVideoJob::dispatchSync($post->id);
            } else {
                GeneratePromotionVideoJob::dispatch($post->id);
            }
        }
    }

    /**
     * Job types to dispatch — matches Promotion Posts UI (text + image, carousels text-first).
     *
     * @return list<string>
     */
    public function generationTypesForPost(FunnelPromotionPost $post): array
    {
        $types = $this->defaultTypesForPost($post);

        if ($this->usesMultiSlideImages($post)) {
            $types = array_values(array_filter(
                $types,
                fn (string $type): bool => $type !== FunnelPromotionPost::TYPE_IMAGE,
            ));
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    public function defaultTypesForPost(FunnelPromotionPost $post): array
    {
        $types = [$post->content_type];
        $ctx = (array) ($post->generation_context ?? []);

        if ($post->content_type === FunnelPromotionPost::TYPE_IMAGE) {
            if (($ctx['include_text'] ?? true) !== false) {
                $types[] = FunnelPromotionPost::TYPE_TEXT;
            }
        }

        return array_values(array_unique($types));
    }

    private function usesMultiSlideImages(FunnelPromotionPost $post): bool
    {
        $formatKey = data_get($post->metadata, 'format_key')
            ?? data_get($post->generation_context, 'content_format');

        if (! is_string($formatKey) || $formatKey === '') {
            return false;
        }

        return $this->formatCatalog->usesMultiSlideImages(
            $this->formatCatalog->format($formatKey),
        );
    }
}
