<?php

namespace App\Services\Promotion;

use App\Models\FunnelPromotionPost;

final class PromotionPublishGuard
{
    public function __construct(
        private readonly PromotionGenerationCoordinator $generation,
    ) {}

    /**
     * @return list<string>
     */
    public function blockingErrors(FunnelPromotionPost $post): array
    {
        if ($post->content_type === FunnelPromotionPost::TYPE_EMAIL) {
            return [
                'Email posts are copy-only. Use Preview or Copy email, then send from your ESP or Campaign email sequences.',
            ];
        }

        if (in_array($post->status, [
            FunnelPromotionPost::STATUS_GENERATING,
            FunnelPromotionPost::STATUS_PUBLISHING,
        ], true)) {
            return ['Content is still generating. Wait until the post is ready before publishing.'];
        }

        if ($this->generation->canRepublish($post)) {
            return [];
        }

        if ($post->status === FunnelPromotionPost::STATUS_PUBLISHED) {
            return ['This post is already published.'];
        }

        if (! in_array($post->status, [
            FunnelPromotionPost::STATUS_READY,
            FunnelPromotionPost::STATUS_SCHEDULED,
            FunnelPromotionPost::STATUS_FAILED,
        ], true)) {
            return ['Post is not ready to publish yet.'];
        }

        return $this->generation->readinessErrors($post);
    }

    public function canPublish(FunnelPromotionPost $post): bool
    {
        return $this->blockingErrors($post) === [];
    }
}
