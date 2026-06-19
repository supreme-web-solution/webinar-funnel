<?php

namespace App\Services\Promotion;

use App\Jobs\PublishPromotionPostJob;
use App\Models\FunnelPromotionPost;
use Illuminate\Support\Facades\Log;

final class PromotionGenerationCoordinator
{
    /**
     * @return list<string>
     */
    public function readinessErrors(FunnelPromotionPost $post): array
    {
        $errors = [];

        if ($post->content_type === FunnelPromotionPost::TYPE_EMAIL) {
            if (trim((string) $post->email_body) === '' && trim((string) $post->text_body) === '') {
                $errors[] = 'Email body is not ready yet';
            }

            return $errors;
        }

        $platforms = array_values(array_filter($post->platforms ?? []));
        if ($platforms === []) {
            $errors[] = 'No platforms selected';
        }

        $ctx = (array) ($post->generation_context ?? []);
        $needsText = ($ctx['include_text'] ?? true) !== false;
        $hasText = trim((string) $post->text_body) !== '';

        if ($post->content_type === FunnelPromotionPost::TYPE_TEXT && ! $hasText) {
            $errors[] = 'Post text is not ready yet';
        }

        if ($post->content_type === FunnelPromotionPost::TYPE_IMAGE) {
            $hasImage = is_string($post->primaryAsset?->url) && $post->primaryAsset->url !== '';
            if (! $hasImage) {
                $errors[] = 'Image is not ready yet';
            }
            if ($needsText && ! $hasText) {
                $errors[] = 'Caption text is not ready yet';
            }
        }

        if ($post->content_type === FunnelPromotionPost::TYPE_VIDEO) {
            $hasVideo = is_string($post->primaryAsset?->url) && $post->primaryAsset->url !== '';
            if (! $hasVideo) {
                $errors[] = 'Video is not ready yet';
            }
            if (! $hasText) {
                $errors[] = 'Video script/caption is not ready yet';
            }
        }

        return $errors;
    }

    public function isReadyToPublish(FunnelPromotionPost $post): bool
    {
        return $this->readinessErrors($post) === [];
    }

    public function isGenerationComplete(FunnelPromotionPost $post): bool
    {
        return $this->isReadyToPublish($post);
    }

    public function maybeFinalize(FunnelPromotionPost $post): void
    {
        $post->refresh()->loadMissing('primaryAsset');

        if (! $this->isGenerationComplete($post)) {
            return;
        }

        if (in_array($post->status, [
            FunnelPromotionPost::STATUS_GENERATING,
            FunnelPromotionPost::STATUS_DRAFT,
        ], true)) {
            $post->update([
                'status' => FunnelPromotionPost::STATUS_READY,
                'last_error' => null,
            ]);
            $post->refresh();
        }

        if ($post->publish_mode === FunnelPromotionPost::MODE_AUTO_PUBLISH
            && $post->status === FunnelPromotionPost::STATUS_READY) {
            Log::info('[Promotion] auto_publish: dispatching publish job', ['post_id' => $post->id]);
            PublishPromotionPostJob::dispatch($post->id);
        }
    }

    public function canRepublish(FunnelPromotionPost $post): bool
    {
        if ($post->status !== FunnelPromotionPost::STATUS_PUBLISHED) {
            return false;
        }

        $published = (array) data_get($post->metadata, 'publish_result.published', []);

        foreach ($published as $row) {
            if (! is_array($row)) {
                continue;
            }
            $externalId = $row['external_id'] ?? null;
            if (is_string($externalId) && $externalId !== '') {
                return false;
            }
        }

        return $published !== [] || data_get($post->metadata, 'publish_result.success') === true;
    }
}
