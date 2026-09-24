<?php

namespace App\Ai\Tools;

use App\Jobs\PublishPromotionPostJob;
use App\Models\FunnelPromotionPost;
use App\Services\Promotion\PromotionPublishGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class PublishPromotionPostTool extends GatedTool
{
    public function toolName(): string
    {
        return 'publish_promotion_post';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Publish a ready promotion post to connected social (e.g. X/Twitter thread). Post must be status ready (not draft/generating). Use list_promotion_posts to find post_id. This is NOT the same as LAUNCH — LAUNCH only approves staged chat actions.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'post_id' => $schema->integer()->required()->description('Promotion post id from create_promotion_post or list_promotion_posts'),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Publish promotion post #'.$request['post_id'].' to social';
    }

    protected function run(Request $request): string
    {
        $post = FunnelPromotionPost::query()
            ->where('user_id', $this->user->id)
            ->where('id', (int) $request['post_id'])
            ->first();

        if ($post === null) {
            return $this->json(['error' => 'Promotion post not found. Use list_promotion_posts.']);
        }

        $guard = app(PromotionPublishGuard::class);
        $errors = $guard->blockingErrors($post);
        if ($errors !== []) {
            return $this->json([
                'error' => implode(' ', $errors),
                'post_id' => $post->id,
                'status' => $post->status,
                'href' => '/funnels/'.$post->funnel_id.'/promotion/posts',
            ]);
        }

        PublishPromotionPostJob::dispatch($post->id);

        return $this->json([
            'queued' => true,
            'post_id' => $post->id,
            'status' => 'publishing',
            'message' => 'Publish queued on promotion-publish. Connect X/Twitter in Integrations if not already.',
            'href' => '/funnels/'.$post->funnel_id.'/promotion/posts',
        ]);
    }
}
