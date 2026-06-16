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
use Illuminate\Support\Facades\Log;

class PollPromotionVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $postId,
        public int $attempt = 1,
    ) {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(PromotionVideoGenerationService $service): void
    {
        $post = FunnelPromotionPost::query()->find($this->postId);
        if (! $post || $post->status !== FunnelPromotionPost::STATUS_GENERATING) {
            return;
        }

        $videoAsset = $post->assets()
            ->where('asset_type', FunnelPromotionAsset::TYPE_VIDEO)
            ->first();

        if (! $videoAsset || $videoAsset->status === FunnelPromotionAsset::STATUS_READY) {
            return;
        }

        $clipId = (string) ($videoAsset->remote_id ?? '');
        if ($clipId === '') {
            return;
        }

        $poll = $service->pollClip($clipId);

        Log::info('[D-ID] Polling clip', [
            'post_id'         => $post->id,
            'clip_id'         => $clipId,
            'attempt'         => $this->attempt,
            'state'           => $poll['state'] ?? 'unknown',
            'remote_status'   => $poll['remote_status'] ?? null,
            'has_video_url'   => ! empty($poll['video_url']),
            'has_thumbnail'   => ! empty($poll['thumbnail_url']),
        ]);

        if (($poll['state'] ?? '') !== 'processing') {
            $service->applyClipPollResult($post, $videoAsset, $poll);

            return;
        }

        $maxAttempts = (int) config('promotion.did.poll_max_attempts', 60);
        if ($this->attempt >= $maxAttempts) {
            $timeoutError = 'Video generation timed out while waiting for D-ID.';

            $service->applyClipPollResult($post, $videoAsset, [
                'state'         => 'failed',
                'error'         => $timeoutError,
                'remote_status' => 'timeout',
            ]);

            Log::warning('[D-ID] Clip poll timed out', [
                'post_id'  => $post->id,
                'clip_id'  => $clipId,
                'attempts' => $this->attempt,
            ]);

            return;
        }

        $interval = (int) config('promotion.did.poll_interval_seconds', 15);

        Log::info('[D-ID] Clip still processing, scheduling next poll', [
            'post_id'      => $post->id,
            'clip_id'      => $clipId,
            'attempt'      => $this->attempt,
            'next_attempt' => $this->attempt + 1,
            'delay_secs'   => $interval,
        ]);

        self::dispatch($this->postId, $this->attempt + 1)
            ->delay(now()->addSeconds($interval));
    }
}
