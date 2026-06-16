<?php

namespace App\Jobs;

use App\Models\FunnelPromotionAsset;
use App\Models\FunnelPromotionPost;
use App\Services\Promotion\PromotionVideoGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GeneratePromotionVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(PromotionVideoGenerationService $service): void
    {
        $post = FunnelPromotionPost::query()->with('funnel.settings')->find($this->postId);
        if (! $post || ! $post->funnel) {
            return;
        }

        $scriptAsset = $post->assets()->firstOrCreate(
            ['asset_type' => FunnelPromotionAsset::TYPE_SCRIPT],
            ['status' => FunnelPromotionAsset::STATUS_PENDING, 'provider' => 'd-id']
        );

        $videoAsset = $post->assets()->firstOrCreate(
            ['asset_type' => FunnelPromotionAsset::TYPE_VIDEO],
            ['status' => FunnelPromotionAsset::STATUS_PENDING, 'provider' => 'd-id']
        );

        $scriptAsset->update(['status' => FunnelPromotionAsset::STATUS_PROCESSING]);
        $videoAsset->update(['status' => FunnelPromotionAsset::STATUS_PROCESSING]);

        $result = $service->generate($post->funnel, $post);
        if (! ($result['success'] ?? false)) {
            $scriptAsset->update([
                'status' => FunnelPromotionAsset::STATUS_FAILED,
                'source_prompt' => $result['script'] ?? null,
                'meta' => ['error' => $result['error'] ?? 'Video generation failed'],
            ]);
            $videoAsset->update([
                'status' => FunnelPromotionAsset::STATUS_FAILED,
                'meta' => ['error' => $result['error'] ?? 'Video generation failed'],
            ]);
            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => (string) ($result['error'] ?? 'Video generation failed'),
            ]);

            return;
        }

        $scriptAsset->update([
            'status' => FunnelPromotionAsset::STATUS_READY,
            'source_prompt' => $result['script'],
            'meta' => ['source' => 'd-id_script'],
        ]);

        $isReady = ($result['status'] ?? 'processing') === 'ready' || ($result['video_url'] ?? null) !== null;

        $videoAsset->update([
            'status' => $isReady ? FunnelPromotionAsset::STATUS_READY : FunnelPromotionAsset::STATUS_PROCESSING,
            'remote_id' => $result['remote_id'] ?? null,
            'url' => $result['video_url'] ?? null,
            'thumbnail_url' => $result['thumbnail_url'] ?? null,
            'duration_seconds' => $result['duration_seconds'] ?? null,
            'meta' => ['status' => $result['status'] ?? null],
        ]);

        $post->update([
            'primary_asset_id' => $videoAsset->id,
            'status' => $isReady ? FunnelPromotionPost::STATUS_READY : FunnelPromotionPost::STATUS_GENERATING,
            'last_error' => null,
            'text_body' => $post->text_body ?: ($result['script'] ?? null),
        ]);

        if (! $isReady && ! empty($result['remote_id'])) {
            $interval = (int) config('promotion.did.poll_interval_seconds', 15);
            PollPromotionVideoJob::dispatch($post->id)->delay(now()->addSeconds($interval));
        }
    }
}
