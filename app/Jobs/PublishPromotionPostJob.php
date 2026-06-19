<?php

namespace App\Jobs;

use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use App\Services\Promotion\PromotionGenerationCoordinator;
use App\Services\Promotion\PromotionPublishGuard;
use App\Services\Promotion\PromotionPublisherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishPromotionPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $postId)
    {
        $this->onQueue((string) config('promotion.queues.publish', 'promotion-publish'));
    }

    public function handle(
        PromotionPublisherService $publisherService,
        PromotionPublishGuard $publishGuard,
        PromotionGenerationCoordinator $generation,
    ): void {
        $post = FunnelPromotionPost::query()->with(['funnel.settings', 'primaryAsset'])->find($this->postId);
        if (! $post) {
            return;
        }

        $republish = $generation->canRepublish($post);
        if ($post->status === FunnelPromotionPost::STATUS_PUBLISHED && ! $republish) {
            return;
        }

        $errors = $publishGuard->blockingErrors($post);
        if ($errors !== [] && ! $republish) {
            Log::warning('[Promotion] PublishPromotionPostJob blocked', [
                'post_id' => $post->id,
                'status' => $post->status,
                'errors' => $errors,
            ]);
            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => implode(' ', $errors),
            ]);

            return;
        }

        if ($republish) {
            Log::info('[Promotion] PublishPromotionPostJob retrying unconfirmed publish', [
                'post_id' => $post->id,
            ]);
            $post->update([
                'status' => FunnelPromotionPost::STATUS_READY,
                'last_error' => null,
            ]);
            $post->refresh();
        }

        $post->update(['status' => FunnelPromotionPost::STATUS_PUBLISHING]);

        Log::info('[Promotion] PublishPromotionPostJob publishing', [
            'post_id' => $post->id,
            'platforms' => $post->platforms,
            'has_text' => trim((string) $post->text_body) !== '',
            'has_media' => is_string($post->primaryAsset?->url) && $post->primaryAsset->url !== '',
        ]);

        $result = $publisherService->publish($post);

        $published = $result['published'] ?? [];
        $failures = $result['failures'] ?? [];

        if ($published === []) {
            $errors = collect($failures)
                ->map(fn (array $failure): string => ($failure['platform'] ?? 'platform').': '.($failure['error'] ?? 'failed'))
                ->implode('; ');

            Log::warning('[Promotion] PublishPromotionPostJob failed', [
                'post_id' => $post->id,
                'errors' => $errors,
                'result' => $result,
            ]);

            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => $errors !== '' ? $errors : 'Publishing failed',
                'metadata' => array_merge((array) $post->metadata, ['publish_result' => $result]),
            ]);

            return;
        }

        $partialErrors = collect($failures)
            ->map(fn (array $failure): string => ($failure['platform'] ?? 'platform').': '.($failure['error'] ?? 'failed'))
            ->implode('; ');

        Log::info('[Promotion] PublishPromotionPostJob succeeded', [
            'post_id' => $post->id,
            'published' => $published,
            'partial' => ($result['partial'] ?? false) === true,
            'failures' => $failures,
        ]);

        $post->update([
            'status' => FunnelPromotionPost::STATUS_PUBLISHED,
            'published_at' => now(),
            'scheduled_for' => null,
            'last_error' => $partialErrors !== '' ? $partialErrors : null,
            'metadata' => array_merge((array) $post->metadata, ['publish_result' => $result]),
        ]);

        FunnelPromotionScheduleEvent::query()->create([
            'post_id' => $post->id,
            'actor_id' => $post->user_id,
            'from_time' => null,
            'to_time' => now(),
            'action' => FunnelPromotionScheduleEvent::ACTION_PUBLISHED,
            'meta' => ['job' => self::class, 'republish' => $republish],
        ]);
    }
}
