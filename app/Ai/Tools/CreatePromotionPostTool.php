<?php

namespace App\Ai\Tools;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Promotion\PromotionCtaResolverService;
use App\Services\Promotion\PromotionGenerationDispatcher;
use App\Services\Traffic\StandaloneTrafficWorkspaceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class CreatePromotionPostTool extends GatedTool
{
    public function toolName(): string
    {
        return 'create_promotion_post';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Create a promotion post in Traffic Hub / Promotion Posts and queue AI generation (same as the UI). For an X/Twitter thread, call ONCE with twitter_thread true — one post with multiple tweets inside, not three separate posts. Omit funnel_id for Traffic Workspace.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->required()->description('What the post or thread is about'),
            'funnel_id' => $schema->integer()->description('Optional funnel id from list_funnels'),
            'campaign_id' => $schema->integer()->description('Optional campaign — uses its traffic funnel'),
            'platforms' => $schema->string()->description('Default twitter'),
            'content_format' => $schema->string()->description('x_thread, x_text_post, etc. Prefer twitter_thread for threads'),
            'twitter_thread' => $schema->boolean()->description('true = one X thread (x_thread format)'),
            'thread_parts' => $schema->integer()->description('Number of tweets when twitter_thread (2–8, default 2 per catalog)'),
            'auto_generate' => $schema->boolean()->description('Queue AI copy generation (default true)'),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        $target = isset($request['campaign_id'])
            ? 'campaign #'.$request['campaign_id']
            : (isset($request['funnel_id']) ? 'funnel #'.$request['funnel_id'] : 'Traffic Workspace');

        $kind = $this->wantsTwitterThread($request) ? 'Twitter thread' : 'promotion post';

        return 'Create & generate '.$kind.' “'.$request['topic'].'” on '.$target;
    }

    protected function run(Request $request): string
    {
        $resolved = $this->resolveFunnel($request);
        if ($resolved['funnel'] === null) {
            return $this->json(['error' => $resolved['error'] ?? 'No funnel available for promotion posts.']);
        }

        $funnel = $resolved['funnel'];

        $platforms = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($request['platforms'] ?? 'twitter'))
        )));
        if ($platforms === []) {
            $platforms = ['twitter'];
        }

        $cta = app(PromotionCtaResolverService::class)->resolve($funnel);
        $metadata = ['created_from' => 'command_center'];
        $generationContext = [];

        $formatKey = $this->resolveFormatKey($request);
        if ($formatKey !== null) {
            $this->applyFormat($formatKey, $request, $metadata, $generationContext);
        }

        $contentType = FunnelPromotionPost::TYPE_TEXT;
        if ($formatKey !== null) {
            $mapped = app(PlatformFormatCatalog::class)->mapToPromotionContentType($formatKey);
            if ($mapped !== '') {
                $contentType = $mapped;
            }
        }

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $this->user->id,
            'funnel_id' => $funnel->id,
            'topic' => (string) $request['topic'],
            'content_type' => $contentType,
            'platforms' => $platforms,
            'publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST,
            'status' => FunnelPromotionPost::STATUS_DRAFT,
            'cta_url' => $cta['url'] ?? null,
            'cta_label' => $cta['label'] ?? null,
            'timezone' => (string) config('promotion.default_timezone', 'UTC'),
            'generation_context' => $generationContext !== [] ? $generationContext : null,
            'metadata' => $metadata,
        ]);

        $autoGenerate = ! array_key_exists('auto_generate', $request->all())
            || filter_var($request['auto_generate'], FILTER_VALIDATE_BOOLEAN);

        if ($autoGenerate) {
            $dispatcher = app(PromotionGenerationDispatcher::class);
            $post = $post->fresh();
            $types = $dispatcher->generationTypesForPost($post);

            if ($types === []) {
                $post->update([
                    'status' => FunnelPromotionPost::STATUS_FAILED,
                    'last_error' => 'Nothing to generate — enable slide copy/text or pick another format.',
                ]);
            } else {
                $post->update(['status' => FunnelPromotionPost::STATUS_GENERATING]);
                $dispatcher->dispatch($post, $types, false);
            }
        }

        return $this->json([
            'id' => $post->id,
            'topic' => $post->topic,
            'funnel_id' => $funnel->id,
            'funnel_name' => $funnel->name,
            'status' => $post->fresh()->status,
            'format' => $formatKey,
            'generating' => $autoGenerate,
            'href' => '/funnels/'.$funnel->id.'/promotion/posts',
            'message' => $autoGenerate
                ? 'Post created — AI generation is running on the promotion-generate queue. Open Promotion Posts when status is ready.'
                : 'Draft post created. Generate copy from Promotion Posts or call again with auto_generate true.',
        ]);
    }

    protected function wantsTwitterThread(Request $request): bool
    {
        if (filter_var($request['twitter_thread'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return ($this->resolveFormatKey($request) ?? '') === 'x_thread';
    }

    protected function resolveFormatKey(Request $request): ?string
    {
        if (filter_var($request['twitter_thread'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'x_thread';
        }

        $key = isset($request['content_format']) ? trim((string) $request['content_format']) : '';

        return $key !== '' ? $key : null;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $generationContext
     */
    protected function applyFormat(string $formatKey, Request $request, array &$metadata, array &$generationContext): void
    {
        $catalog = app(PlatformFormatCatalog::class);
        $spec = $catalog->format($formatKey);
        if ($spec === null) {
            return;
        }

        if ($formatKey === 'x_thread' && isset($request['thread_parts'])) {
            $parts = max(2, min(8, (int) $request['thread_parts']));
            $spec['thread_parts_min'] = $parts;
            $spec['thread_parts_max'] = $parts;
        }

        $metadata['format_key'] = $formatKey;
        $metadata['format_spec'] = $spec;
        $metadata['media_spec'] = $catalog->mediaSpec($spec);
        $generationContext['content_format'] = $formatKey;

        if (! array_key_exists('include_text', $generationContext)) {
            $generationContext['include_text'] = true;
        }
        if (! array_key_exists('include_image', $generationContext)) {
            $generationContext['include_image'] = $catalog->requiresImageAsset($spec);
        }
    }

    /**
     * @return array{funnel: ?Funnel, error: ?string}
     */
    protected function resolveFunnel(Request $request): array
    {
        if (isset($request['funnel_id'])) {
            $funnel = Funnel::query()
                ->where('user_id', $this->user->id)
                ->where('id', (int) $request['funnel_id'])
                ->first();

            if ($funnel !== null) {
                return ['funnel' => $funnel, 'error' => null];
            }

            $ids = Funnel::query()
                ->where('user_id', $this->user->id)
                ->orderByDesc('id')
                ->limit(5)
                ->pluck('id')
                ->all();

            $hint = $ids === []
                ? 'You have no funnels yet — omit funnel_id to use Traffic Workspace, or create a campaign first.'
                : 'Invalid funnel_id. Your funnel ids include: '.implode(', ', $ids).'. Or omit funnel_id for Traffic Workspace.';

            return ['funnel' => null, 'error' => 'Funnel not found. '.$hint];
        }

        if (isset($request['campaign_id'])) {
            $campaign = Campaign::query()
                ->where('user_id', $this->user->id)
                ->where('id', (int) $request['campaign_id'])
                ->first();

            if ($campaign === null) {
                return ['funnel' => null, 'error' => 'Campaign not found.'];
            }

            return [
                'funnel' => app(CampaignTrafficHubService::class)->ensureTrafficFunnel($campaign),
                'error' => null,
            ];
        }

        return [
            'funnel' => app(StandaloneTrafficWorkspaceService::class)->funnelForUser($this->user),
            'error' => null,
        ];
    }
}
