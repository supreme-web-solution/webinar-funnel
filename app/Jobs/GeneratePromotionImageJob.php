<?php

namespace App\Jobs;

use App\Models\FunnelPromotionAsset;
use App\Models\FunnelPromotionPost;
use App\Services\Promotion\PromotionImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePromotionImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(PromotionImageGenerationService $service): void
    {
        Log::info('[Promotion] GeneratePromotionImageJob started', ['post_id' => $this->postId]);

        $post = FunnelPromotionPost::query()->with('funnel')->find($this->postId);

        if (! $post) {
            Log::warning('[Promotion] GeneratePromotionImageJob: post not found', ['post_id' => $this->postId]);
            return;
        }

        if (! $post->funnel) {
            Log::warning('[Promotion] GeneratePromotionImageJob: funnel not found', [
                'post_id'   => $this->postId,
                'funnel_id' => $post->funnel_id,
            ]);
            return;
        }

        Log::info('[Promotion] GeneratePromotionImageJob: generating image', [
            'post_id'   => $this->postId,
            'topic'     => $post->topic,
            'funnel_id' => $post->funnel_id,
        ]);

        $asset = $post->assets()->firstOrCreate(
            ['asset_type' => FunnelPromotionAsset::TYPE_IMAGE],
            ['status' => FunnelPromotionAsset::STATUS_PENDING, 'provider' => 'openai_image']
        );

        $asset->update(['status' => FunnelPromotionAsset::STATUS_PROCESSING]);

        try {
            $result = $service->generate($post->funnel, $post);

            Log::info('[Promotion] GeneratePromotionImageJob: generation result', [
                'post_id' => $this->postId,
                'success' => $result['success'] ?? false,
                'has_url' => ! empty($result['url']),
                'error'   => $result['error'] ?? null,
            ]);

            if (! ($result['success'] ?? false)) {
                $errorMsg = (string) ($result['error'] ?? 'Image generation failed');

                $asset->update([
                    'status'        => FunnelPromotionAsset::STATUS_FAILED,
                    'source_prompt' => $result['prompt'] ?? null,
                    'meta'          => ['error' => $errorMsg],
                ]);
                $post->update([
                    'status'     => FunnelPromotionPost::STATUS_FAILED,
                    'last_error' => $errorMsg,
                ]);

                Log::error('[Promotion] GeneratePromotionImageJob: failed', [
                    'post_id' => $this->postId,
                    'error'   => $errorMsg,
                ]);

                return;
            }

            $asset->update([
                'status'        => FunnelPromotionAsset::STATUS_READY,
                'source_prompt' => $result['prompt'] ?? null,
                'url'           => $result['url'] ?? null,
                'thumbnail_url' => $result['url'] ?? null,
                'meta'          => ['provider' => 'openai_image'],
            ]);

            $post->update([
                'primary_asset_id' => $asset->id,
                'status'           => FunnelPromotionPost::STATUS_READY,
                'last_error'       => null,
            ]);

            Log::info('[Promotion] GeneratePromotionImageJob: done', [
                'post_id'   => $this->postId,
                'asset_id'  => $asset->id,
                'image_url' => $result['url'] ?? '(no url — api key missing or b64 decode failed)',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Promotion] GeneratePromotionImageJob: exception', [
                'post_id' => $this->postId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            $asset->update(['status' => FunnelPromotionAsset::STATUS_FAILED]);
            $post->update([
                'status'     => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Image job exception: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }
}
