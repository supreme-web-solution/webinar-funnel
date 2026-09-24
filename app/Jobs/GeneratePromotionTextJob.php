<?php

namespace App\Jobs;

use App\Models\FunnelPromotionPost;
use App\Services\Content\FormatContentGenerationService;
use App\Services\Promotion\PromotionGenerationCoordinator;
use App\Services\Promotion\PromotionTextGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePromotionTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(
        PromotionTextGenerationService $service,
        FormatContentGenerationService $formatService,
        PromotionGenerationCoordinator $coordinator,
    ): void {
        Log::info('[Promotion] GeneratePromotionTextJob started', ['post_id' => $this->postId]);

        $post = FunnelPromotionPost::query()->with(['funnel'])->find($this->postId);

        if (! $post) {
            Log::warning('[Promotion] GeneratePromotionTextJob: post not found', ['post_id' => $this->postId]);

            return;
        }

        if (! $post->funnel) {
            Log::warning('[Promotion] GeneratePromotionTextJob: funnel not found', [
                'post_id' => $this->postId,
                'funnel_id' => $post->funnel_id,
            ]);

            return;
        }

        Log::info('[Promotion] GeneratePromotionTextJob: generating text', [
            'post_id' => $this->postId,
            'topic' => $post->topic,
            'content_type' => $post->content_type,
            'funnel_id' => $post->funnel_id,
        ]);

        try {
            $hasFormat = ! empty($post->metadata['format_key'] ?? null)
                || ! empty($post->generation_context['content_format'] ?? null);

            $result = $hasFormat
                ? $formatService->generate($post->funnel, $post)
                : $service->generate($post->funnel, $post);

            Log::info('[Promotion] GeneratePromotionTextJob: text generated', [
                'post_id' => $this->postId,
                'source' => $result['source'] ?? 'unknown',
                'text_body_length' => strlen($result['text_body'] ?? ''),
                'hashtag_count' => count($result['hashtags'] ?? []),
            ]);

            // Image posts wait for image + text; video posts wait for D-ID render.
            $isImagePost = $post->content_type === FunnelPromotionPost::TYPE_IMAGE;
            $isVideoPost = $post->content_type === FunnelPromotionPost::TYPE_VIDEO;

            $metadata = $post->metadata ?? [];
            if (! empty($result['format_payload'] ?? null)) {
                $metadata['format_payload'] = $result['format_payload'];
            }

            $isMultiSlide = ($result['format_payload']['generator'] ?? null) === 'carousel';
            if ($isMultiSlide) {
                $slides = is_array($result['format_payload']['slides'] ?? null)
                    ? $result['format_payload']['slides']
                    : [];
                if ($slides !== []) {
                    $metadata['generation_progress'] = [
                        'phase' => 'slide_images',
                        'current' => 0,
                        'total' => count($slides),
                        'message' => 'Slide copy ready — starting images…',
                        'updated_at' => now()->toIso8601String(),
                    ];
                }
            }

            $post->fill([
                'text_body' => $result['text_body'],
                'email_subject' => $result['email_subject'],
                'email_body' => $result['email_body'],
                'hashtags' => $result['hashtags'],
                'metadata' => $metadata,
                'last_error' => null,
            ]);

            if (! $isImagePost && ! $isVideoPost) {
                $post->status = FunnelPromotionPost::STATUS_READY;
            }

            $post->save();

            if ($isMultiSlide) {
                Log::info('[Promotion] GeneratePromotionTextJob: dispatching multi-slide images', ['post_id' => $this->postId]);
                GeneratePromotionCarouselImagesJob::dispatch($this->postId);
            } elseif ($isImagePost || $isVideoPost) {
                $coordinator->maybeFinalize($post);
            } elseif ($post->publish_mode === FunnelPromotionPost::MODE_AUTO_PUBLISH) {
                $coordinator->maybeFinalize($post);
            }

            Log::info('[Promotion] GeneratePromotionTextJob: post updated', [
                'post_id' => $this->postId,
                'status' => $post->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Promotion] GeneratePromotionTextJob: exception', [
                'post_id' => $this->postId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Text generation failed: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }
}
