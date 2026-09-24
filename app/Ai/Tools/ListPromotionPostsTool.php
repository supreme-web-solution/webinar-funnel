<?php

namespace App\Ai\Tools;

use App\Models\FunnelPromotionPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListPromotionPostsTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_promotion_posts';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List recent promotion/social posts with id, status, topic, and funnel. Use before publish_promotion_post to pick the right post_id.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'funnel_id' => $schema->integer()->description('Optional funnel filter'),
            'status' => $schema->string()->description('Optional: draft, generating, ready, published, failed'),
            'limit' => $schema->integer()->min(1)->max(20),
        ];
    }

    protected function run(Request $request): string
    {
        $limit = min(20, max(1, (int) ($request['limit'] ?? 10)));

        $query = FunnelPromotionPost::query()
            ->where('user_id', $this->user->id)
            ->latest('id');

        if (isset($request['funnel_id'])) {
            $query->where('funnel_id', (int) $request['funnel_id']);
        }

        if (isset($request['status']) && (string) $request['status'] !== '') {
            $query->where('status', (string) $request['status']);
        }

        $rows = $query->limit($limit)->get(['id', 'funnel_id', 'topic', 'status', 'platforms', 'published_at']);

        $posts = $rows->map(fn (FunnelPromotionPost $post): array => [
            'id' => $post->id,
            'funnel_id' => $post->funnel_id,
            'status' => $post->status,
            'topic' => mb_substr((string) $post->topic, 0, 120),
            'platforms' => $post->platforms,
            'published_at' => $post->published_at?->toIso8601String(),
            'href' => '/funnels/'.$post->funnel_id.'/promotion/posts',
        ])->all();

        return $this->json(['posts' => $posts]);
    }
}
