<?php

namespace App\Jobs;

use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use App\Services\Promotion\PromotionPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishPromotionPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.publish', 'promotion-publish'));
    }

    public function handle(PromotionPublisherService $publisherService): void
    {
        $post = FunnelPromotionPost::query()->with(['funnel.settings', 'primaryAsset'])->find($this->postId);
        if (! $post) {
            return;
        }

        if ($post->status === FunnelPromotionPost::STATUS_PUBLISHED) {
            return;
        }

        $post->update(['status' => FunnelPromotionPost::STATUS_PUBLISHING]);
        $result = $publisherService->publish($post);

        if (! ($result['success'] ?? false)) {
            $errors = collect($result['failures'] ?? [])
                ->map(fn (array $failure): string => ($failure['platform'] ?? 'platform').': '.($failure['error'] ?? 'failed'))
                ->implode('; ');

            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => $errors !== '' ? $errors : 'Publishing failed',
                'metadata' => array_merge((array) $post->metadata, ['publish_result' => $result]),
            ]);

            return;
        }

        $post->update([
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_for' => null,
            'last_error' => null,
            'metadata' => array_merge((array) $post->metadata, ['publish_result' => $result]),
        ]);

        FunnelPromotionScheduleEvent::query()->create([
            'post_id' => $post->id,
            'actor_id' => $post->user_id,
            'from_time' => null,
            'to_time' => now(),
            'action' => FunnelPromotionScheduleEvent::ACTION_PUBLISHED,
            'meta' => ['job' => self::class],
        ]);
    }
}
