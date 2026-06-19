<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPromotionGenerateAssetsRequest;
use App\Http\Requests\FunnelPromotionScheduleRequest;
use App\Http\Requests\FunnelPromotionStoreRequest;
use App\Http\Requests\FunnelPromotionScriptGenerateRequest;
use App\Http\Requests\FunnelPromotionUpdateRequest;
use App\Jobs\GeneratePromotionImageJob;
use App\Jobs\GeneratePromotionTextJob;
use App\Jobs\GeneratePromotionVideoJob;
use App\Jobs\PublishPromotionPostJob;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use App\Models\FunnelPromotionTopicSuggestion;
use App\Services\DID\DIDClient;
use App\Services\Promotion\PromotionCtaResolverService;
use App\Services\Promotion\PromotionGenerationCoordinator;
use App\Services\Promotion\PromotionPlatformCatalog;
use App\Services\Promotion\PromotionPublishGuard;
use App\Services\Promotion\PromotionTextGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FunnelPromotionController extends Controller
{
    public function index(Request $request, Funnel $funnel, DIDClient $did, PromotionPlatformCatalog $platformCatalog): Response
    {
        $this->authorizeFunnel($funnel);

        $status = trim((string) $request->query('status', ''));
        $type = trim((string) $request->query('type', ''));
        $platform = trim((string) $request->query('platform', ''));
        $search = trim((string) $request->query('search', ''));

        $query = FunnelPromotionPost::query()
            ->where('funnel_id', $funnel->id)
            ->with(['primaryAsset:id,promotion_post_id,asset_type,url,thumbnail_url,status'])
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($type !== '') {
            $query->where('content_type', $type);
        }
        if ($platform !== '') {
            $query->whereJsonContains('platforms', $platform);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('topic', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('text_body', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(15)->withQueryString();

        $statsBase = FunnelPromotionPost::query()->where('funnel_id', $funnel->id);
        $suggestedTopics = FunnelPromotionTopicSuggestion::query()
            ->where('funnel_id', $funnel->id)
            ->where('status', FunnelPromotionTopicSuggestion::STATUS_SUGGESTED)
            ->orderByDesc('score')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'topic', 'angle', 'score']);

        $connectedPlatforms = $platformCatalog->connectedForUser((int) $request->user()->id);
        $postPlatformKeys = FunnelPromotionPost::query()
            ->where('funnel_id', $funnel->id)
            ->where('content_type', '!=', FunnelPromotionPost::TYPE_EMAIL)
            ->pluck('platforms')
            ->flatten()
            ->filter(fn ($p) => is_string($p) && $p !== '')
            ->unique()
            ->values()
            ->all();

        return Inertia::render('funnels/promotion/Posts', [
            'funnel' => [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'status' => $funnel->status,
            ],
            'posts' => $posts,
            'stats' => [
                'total' => (clone $statsBase)->count(),
                'draft' => (clone $statsBase)->where('status', FunnelPromotionPost::STATUS_DRAFT)->count(),
                'scheduled' => (clone $statsBase)->where('status', FunnelPromotionPost::STATUS_SCHEDULED)->count(),
                'published' => (clone $statsBase)->where('status', FunnelPromotionPost::STATUS_PUBLISHED)->count(),
                'failed' => (clone $statsBase)->where('status', FunnelPromotionPost::STATUS_FAILED)->count(),
            ],
            'suggestedTopics' => $suggestedTopics,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'platform' => $platform,
                'search' => $search,
            ],
            'connectedPlatforms' => $connectedPlatforms,
            'availablePlatforms' => $platformCatalog->platformKeysForSelection(
                (int) $request->user()->id,
                is_array($postPlatformKeys) ? $postPlatformKeys : []
            ),
            'videoEnabled'       => $did->isEnabled(),
            'availableAvatars'   => $did->isEnabled() ? $this->buildAvatarList($did->getPresenters()) : [],
            'availableVoices'    => DIDClient::VOICES,
            'socialTrafficUrl'   => route('settings.social-traffic.edit'),
            'routes' => [
                'store' => route('funnels.promotion.posts.store', $funnel),
                'bulk' => route('funnels.promotion.posts.bulk', $funnel),
                'calendar' => route('funnels.promotion.calendar.index', $funnel),
                'topicsGenerate' => route('funnels.promotion.topics.generate', $funnel),
                'scriptGenerate' => route('funnels.promotion.scripts.generate', $funnel),
            ],
        ]);
    }

    public function generateScript(
        FunnelPromotionScriptGenerateRequest $request,
        Funnel $funnel,
        PromotionTextGenerationService $textService,
        PromotionCtaResolverService $ctaResolver,
    ): JsonResponse {
        $this->authorizeFunnel($funnel);

        $validated = $request->validated();
        $cta = $ctaResolver->resolve($funnel);

        $script = $textService->generateVideoScript(
            $funnel,
            $validated['topic'],
            (array) ($validated['generation_context'] ?? []),
            $validated['cta_url'] ?? $cta['url'],
            $validated['cta_label'] ?? $cta['label'],
        );

        return response()->json(['script' => $script]);
    }

    public function show(Request $request, Funnel $funnel, FunnelPromotionPost $post): JsonResponse
    {
        $this->authorizePost($request, $funnel, $post);

        $post->load(['assets', 'primaryAsset']);

        return response()->json([
            'post' => $post,
        ]);
    }

    public function bulk(Request $request, Funnel $funnel): RedirectResponse
    {
        $this->authorizeFunnel($funnel);
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:publish,delete,duplicate,schedule'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        $posts = FunnelPromotionPost::query()
            ->where('funnel_id', $funnel->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        $action = $validated['action'];

        if ($action === 'publish') {
            foreach ($posts as $post) {
                PublishPromotionPostJob::dispatch($post->id);
            }

            return back()->with('success', 'Bulk publish queued for selected posts.');
        }

        if ($action === 'delete') {
            foreach ($posts as $post) {
                $post->delete();
            }

            return back()->with('success', 'Selected posts deleted.');
        }

        if ($action === 'duplicate') {
            foreach ($posts as $post) {
                $this->duplicatePost($post);
            }

            return back()->with('success', 'Selected posts duplicated — ready to edit and publish.');
        }

        if ($action === 'schedule') {
            $scheduledFor = $validated['scheduled_for'] ?? null;
            if (! $scheduledFor) {
                return back()->withErrors(['scheduled_for' => 'A schedule datetime is required for bulk schedule.']);
            }

            foreach ($posts as $post) {
                $previous = $post->scheduled_for;
                $post->update([
                    'scheduled_for' => $scheduledFor,
                    'timezone' => $validated['timezone'] ?? (string) config('promotion.default_timezone', 'UTC'),
                    'status' => FunnelPromotionPost::STATUS_SCHEDULED,
                    'last_error' => null,
                ]);

                FunnelPromotionScheduleEvent::query()->create([
                    'post_id' => $post->id,
                    'actor_id' => $request->user()->id,
                    'from_time' => $previous,
                    'to_time' => $post->scheduled_for,
                    'action' => $previous
                        ? FunnelPromotionScheduleEvent::ACTION_RESCHEDULED
                        : FunnelPromotionScheduleEvent::ACTION_SCHEDULED,
                    'meta' => ['source' => 'bulk_action'],
                ]);
            }

            return back()->with('success', 'Selected posts scheduled.');
        }

        return back();
    }

    public function store(
        FunnelPromotionStoreRequest $request,
        Funnel $funnel,
        PromotionCtaResolverService $ctaResolver,
    ): RedirectResponse {
        $this->authorizeFunnel($funnel);
        $validated = $request->validated();

        $cta = $ctaResolver->resolve($funnel);
        $post = FunnelPromotionPost::query()->create([
            'user_id' => $request->user()->id,
            'funnel_id' => $funnel->id,
            'title' => $validated['title'] ?? null,
            'topic' => $validated['topic'],
            'content_type' => $validated['content_type'],
            'platforms' => $validated['platforms'],
            'publish_mode' => $validated['publish_mode'],
            'status' => FunnelPromotionPost::STATUS_DRAFT,
            'cta_url' => $validated['cta_url'] ?? $cta['url'],
            'cta_label' => $validated['cta_label'] ?? $cta['label'],
            'text_body' => $validated['text_body'] ?? null,
            'email_subject' => $validated['email_subject'] ?? null,
            'email_body' => $validated['email_body'] ?? null,
            'hashtags' => $validated['hashtags'] ?? null,
            'timezone' => (string) config('promotion.default_timezone', 'UTC'),
            'generation_context' => $validated['generation_context'] ?? null,
            'metadata' => ['created_from' => 'promotion_ui'],
        ]);

        if (($validated['auto_generate'] ?? false) === true) {
            // Determine which generation jobs to dispatch.
            // For image posts: generate both the text caption AND the image.
            // For text/email/video: only dispatch the matching job.
            $types = [$post->content_type];

            if ($post->content_type === FunnelPromotionPost::TYPE_IMAGE) {
                $ctx = (array) ($post->generation_context ?? []);
                if (($ctx['include_text'] ?? true) !== false) {
                    $types[] = FunnelPromotionPost::TYPE_TEXT;
                }
            }

            Log::info('[Promotion] store: dispatching generation jobs', [
                'post_id' => $post->id,
                'types'   => $types,
            ]);

            $post->update(['status' => FunnelPromotionPost::STATUS_GENERATING]);
            $this->dispatchGeneration($post, $types, false);
        }

        Log::info('[Promotion] store: post created', [
            'post_id'      => $post->id,
            'content_type' => $post->content_type,
            'auto_generate' => $validated['auto_generate'] ?? false,
        ]);

        return back()->with('success', 'Promotion post created.');
    }

    public function update(
        FunnelPromotionUpdateRequest $request,
        Funnel $funnel,
        FunnelPromotionPost $post,
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);

        $post->fill($request->validated())->save();

        return back()->with('success', 'Promotion post updated.');
    }

    public function destroy(Request $request, Funnel $funnel, FunnelPromotionPost $post): RedirectResponse
    {
        $this->authorizePost($request, $funnel, $post);
        $post->delete();

        return back()->with('success', 'Promotion post deleted.');
    }

    public function generateAssets(
        FunnelPromotionGenerateAssetsRequest $request,
        Funnel $funnel,
        FunnelPromotionPost $post,
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);
        $validated = $request->validated();

        Log::info('[Promotion] generateAssets: dispatching generation jobs', [
            'post_id'  => $post->id,
            'types'    => $validated['types'],
            'platform' => $post->platforms,
        ]);

        $post->update([
            'status'     => FunnelPromotionPost::STATUS_GENERATING,
            'last_error' => null,
        ]);

        $this->dispatchGeneration($post, $validated['types'], (bool) ($validated['wait_for_video'] ?? false));

        return back()->with('success', 'Generation started.');
    }

    public function schedule(
        FunnelPromotionScheduleRequest $request,
        Funnel $funnel,
        FunnelPromotionPost $post,
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);
        $validated = $request->validated();
        $from = $post->scheduled_for;

        $post->update([
            'scheduled_for' => $validated['scheduled_for'],
            'timezone' => $validated['timezone'] ?? (string) config('promotion.default_timezone', 'UTC'),
            'status' => FunnelPromotionPost::STATUS_SCHEDULED,
            'last_error' => null,
        ]);

        FunnelPromotionScheduleEvent::query()->create([
            'post_id' => $post->id,
            'actor_id' => $request->user()->id,
            'from_time' => $from,
            'to_time' => $post->scheduled_for,
            'action' => $from ? FunnelPromotionScheduleEvent::ACTION_RESCHEDULED : FunnelPromotionScheduleEvent::ACTION_SCHEDULED,
            'meta' => ['timezone' => $post->timezone],
        ]);

        return back()->with('success', 'Post scheduled.');
    }

    public function duplicate(
        Request $request,
        Funnel $funnel,
        FunnelPromotionPost $post,
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);

        $this->duplicatePost($post->load(['assets', 'primaryAsset']));

        return back()->with('success', 'Post copied — caption adjusted slightly so you can republish within 24 hours.');
    }

    public function publish(Request $request, Funnel $funnel, FunnelPromotionPost $post, PromotionPublishGuard $publishGuard): RedirectResponse
    {
        $this->authorizePost($request, $funnel, $post);
        $post->loadMissing('primaryAsset');

        $errors = $publishGuard->blockingErrors($post);
        if ($errors !== []) {
            return back()->withErrors(['publish' => implode(' ', $errors)]);
        }

        $sync = filter_var($request->input('sync', true), FILTER_VALIDATE_BOOLEAN);
        if ($sync) {
            PublishPromotionPostJob::dispatchSync($post->id);
        } else {
            PublishPromotionPostJob::dispatch($post->id);
        }

        return back()->with('success', 'Publish triggered.');
    }

    /**
     * @param  array<int, string>  $types
     */
    private function dispatchGeneration(FunnelPromotionPost $post, array $types, bool $waitForVideo): void
    {
        $types = array_values(array_unique($types));

        if (in_array(FunnelPromotionPost::TYPE_TEXT, $types, true) || in_array(FunnelPromotionPost::TYPE_EMAIL, $types, true)) {
            Log::info('[Promotion] dispatchGeneration: dispatching text job', ['post_id' => $post->id]);
            GeneratePromotionTextJob::dispatch($post->id);
        }
        if (in_array(FunnelPromotionPost::TYPE_IMAGE, $types, true)) {
            Log::info('[Promotion] dispatchGeneration: dispatching image job', ['post_id' => $post->id]);
            GeneratePromotionImageJob::dispatch($post->id);
        }
        if (in_array(FunnelPromotionPost::TYPE_VIDEO, $types, true)) {
            Log::info('[Promotion] dispatchGeneration: dispatching video job', ['post_id' => $post->id, 'sync' => $waitForVideo]);
            if ($waitForVideo) {
                GeneratePromotionVideoJob::dispatchSync($post->id);
            } else {
                GeneratePromotionVideoJob::dispatch($post->id);
            }
        }
    }

    /**
     * Map raw D-ID presenter objects into the simpler shape the frontend expects.
     *
     * @param  array<int, array<string, mixed>>  $presenters
     * @return array<int, array<string, mixed>>
     */
    private function buildAvatarList(array $presenters): array
    {
        return array_values(array_map(function (array $p): array {
            return [
                'id'                  => (string) ($p['presenter_id'] ?? ''),
                'name'                => (string) ($p['name'] ?? 'Presenter'),
                'thumbnail_url'       => (string) ($p['thumbnail_url'] ?? $p['image_url'] ?? ''),
                'talking_preview_url' => (string) ($p['talking_preview_url'] ?? $p['preview_url'] ?? ''),
                'image_url'           => (string) ($p['image_url'] ?? $p['thumbnail_url'] ?? ''),
            ];
        }, array_filter($presenters, fn ($p) => is_array($p) && ! empty($p['presenter_id']))));
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        abort_unless((int) $user->id === (int) $funnel->user_id, 403);
    }

    private function authorizePost(Request $request, Funnel $funnel, FunnelPromotionPost $post): void
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        abort_unless(
            (int) $user->id === (int) $post->user_id && (int) $post->funnel_id === (int) $funnel->id,
            403
        );
    }

    private function duplicatePost(FunnelPromotionPost $post): FunnelPromotionPost
    {
        $copy = $post->replicate([
            'status',
            'scheduled_for',
            'published_at',
            'last_error',
            'metadata',
            'primary_asset_id',
            'uuid',
        ]);
        $copy->status = FunnelPromotionPost::STATUS_DRAFT;
        $copy->scheduled_for = null;
        $copy->published_at = null;
        $copy->last_error = null;
        $copy->metadata = [
            'duplicated_from' => $post->id,
            'created_from' => 'duplicate',
        ];
        $copy->save();

        $newPrimaryId = null;
        foreach ($post->assets as $asset) {
            $assetCopy = $asset->replicate(['uuid', 'promotion_post_id']);
            $assetCopy->promotion_post_id = $copy->id;
            $assetCopy->save();

            if ((int) $post->primary_asset_id === (int) $asset->id) {
                $newPrimaryId = (int) $assetCopy->id;
            }
        }

        if ($newPrimaryId !== null) {
            $copy->update(['primary_asset_id' => $newPrimaryId]);
        }

        $this->varyDuplicatedContentForRepublish($copy);

        $copy->load('primaryAsset');

        if (app(PromotionGenerationCoordinator::class)->isGenerationComplete($copy)) {
            $copy->update(['status' => FunnelPromotionPost::STATUS_READY]);
        }

        return $copy->fresh(['primaryAsset']);
    }

    /**
     * Zernio rejects identical caption + media to the same account within 24 hours.
     * Nudge duplicated posts so they can be published again without waiting.
     */
    private function varyDuplicatedContentForRepublish(FunnelPromotionPost $copy): void
    {
        $body = trim((string) $copy->text_body);
        $emailBody = trim((string) $copy->email_body);
        $stamp = now()->format('M j, Y g:i A');

        if ($body !== '') {
            $copy->text_body = $body."\n\n— ".$stamp;
        }

        if ($emailBody !== '') {
            $copy->email_body = $emailBody."\n\n— ".$stamp;
        }

        if (is_string($copy->topic) && $copy->topic !== '' && ! str_ends_with($copy->topic, ' (copy)')) {
            $copy->topic = $copy->topic.' (copy)';
        }

        $copy->save();
    }
}
