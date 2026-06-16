<?php

namespace App\Jobs;

use App\Models\FunnelPromotionPost;
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

    public function handle(PromotionTextGenerationService $service): void
    {
        Log::info('[Promotion] GeneratePromotionTextJob started', ['post_id' => $this->postId]);

        $post = FunnelPromotionPost::query()->with(['funnel'])->find($this->postId);

        if (! $post) {
            Log::warning('[Promotion] GeneratePromotionTextJob: post not found', ['post_id' => $this->postId]);
            return;
        }

        if (! $post->funnel) {
            Log::warning('[Promotion] GeneratePromotionTextJob: funnel not found', [
                'post_id'   => $this->postId,
                'funnel_id' => $post->funnel_id,
            ]);
            return;
        }

        Log::info('[Promotion] GeneratePromotionTextJob: generating text', [
            'post_id'      => $this->postId,
            'topic'        => $post->topic,
            'content_type' => $post->content_type,
            'funnel_id'    => $post->funnel_id,
        ]);

        try {
            $result = $service->generate($post->funnel, $post);

            Log::info('[Promotion] GeneratePromotionTextJob: text generated', [
                'post_id'          => $this->postId,
                'source'           => $result['source'] ?? 'unknown',
                'text_body_length' => strlen($result['text_body'] ?? ''),
                'hashtag_count'    => count($result['hashtags'] ?? []),
            ]);

            // For image posts the image job controls final STATUS_READY.
            // For all other types this job is the last step — mark ready.
            $isImagePost = $post->content_type === FunnelPromotionPost::TYPE_IMAGE;

            $post->fill([
                'text_body'     => $result['text_body'],
                'email_subject' => $result['email_subject'],
                'email_body'    => $result['email_body'],
                'hashtags'      => $result['hashtags'],
                'last_error'    => null,
            ]);

            if (! $isImagePost) {
                $post->status = FunnelPromotionPost::STATUS_READY;
            }

            $post->save();

            Log::info('[Promotion] GeneratePromotionTextJob: post updated', [
                'post_id' => $this->postId,
                'status'  => $post->status,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Promotion] GeneratePromotionTextJob: exception', [
                'post_id' => $this->postId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            $post->update([
                'status'     => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Text generation failed: '.$e->getMessage(),
            ]);

            throw $e;
        }
    }
}
