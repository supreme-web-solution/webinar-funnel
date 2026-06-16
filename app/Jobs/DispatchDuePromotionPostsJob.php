<?php

namespace App\Jobs;

use App\Models\FunnelPromotionPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchDuePromotionPostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue((string) config('promotion.queues.publish', 'promotion-publish'));
    }

    public function handle(): void
    {
        FunnelPromotionPost::query()
            ->where('status', FunnelPromotionPost::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(200)
            ->get(['id'])
            ->each(fn (FunnelPromotionPost $post) => PublishPromotionPostJob::dispatch($post->id));
    }
}
