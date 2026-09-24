<?php

namespace App\Http\Controllers;

use App\Http\Requests\FunnelPromotionGenerateAssetsRequest;
use App\Http\Requests\FunnelPromotionScheduleRequest;
use App\Http\Requests\FunnelPromotionScriptGenerateRequest;
use App\Http\Requests\FunnelPromotionStoreRequest;
use App\Http\Requests\FunnelPromotionUpdateRequest;
use App\Jobs\PublishPromotionPostJob;
use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionScheduleEvent;
use App\Models\FunnelPromotionTopicSuggestion;
use App\Models\User;
use App\Services\Campaigns\CampaignKnowledgeContextService;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\DID\DIDClient;
use App\Services\Promotion\CarouselTextSlideRenderer;
use App\Services\Promotion\PromotionCtaResolverService;
use App\Services\Promotion\PromotionGenerationCoordinator;
use App\Services\Promotion\PromotionGenerationDispatcher;
use App\Services\Promotion\PromotionPlatformCatalog;
use App\Services\Promotion\PromotionPublishGuard;
use App\Services\Promotion\PromotionTextGenerationService;
use App\Services\Promotion\PromotionTopicSuggestionService;
use App\Services\Traffic\TrafficHubResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class FunnelPromotionController extends Controller
{
    public function index(Request $request, Funnel $funnel, DIDClient $did, PromotionPlatformCatalog $platformCatalog, ?Campaign $campaign = null): Response
    {
        $this->authorizeFunnel($funnel);
        $hubResolver = app(TrafficHubResolver::class);
        $trafficHub = $hubResolver->payload($funnel, $campaign);

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

        $this->refreshStaleCampaignTopicSuggestions($funnel, app(PromotionTopicSuggestionService::class));

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
            'defaultCta' => app(PromotionCtaResolverService::class)->resolve($funnel->loadMissing('user')),
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
            'videoEnabled' => $did->isEnabled(),
            'availableAvatars' => $did->isEnabled() ? $this->buildAvatarList($did->getPresenters()) : [],
            'availableVoices' => DIDClient::VOICES,
            'socialTrafficUrl' => route('settings.social-traffic.edit'),
            'routes' => [
                'store' => route('funnels.promotion.posts.store', $funnel),
                'bulk' => route('funnels.promotion.posts.bulk', $funnel),
                'calendar' => $campaign
                    ? route('campaigns.traffic.promotion.calendar', $campaign)
                    : ($hubResolver->isStandaloneContext($funnel, $campaign)
                        ? route('traffic.workspace.promotion.calendar')
                        : route('funnels.promotion.calendar.index', $funnel)),
                'topicsGenerate' => route('funnels.promotion.topics.generate', $funnel),
                'scriptGenerate' => route('funnels.promotion.scripts.generate', $funnel),
            ],
            'campaignHub' => $trafficHub,
            'contentFormatCatalog' => app(PlatformFormatCatalog::class)->catalogPayload(),
            'carouselLayoutTemplates' => CarouselTextSlideRenderer::layoutCatalog(),
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
            $guard = app(PromotionPublishGuard::class);
            $queued = 0;
            $skipped = 0;

            foreach ($posts as $post) {
                if ($guard->blockingErrors($post->fresh()) !== []) {
                    $skipped++;

                    continue;
                }
                PublishPromotionPostJob::dispatch($post->id);
                $queued++;
            }

            $message = $queued > 0
                ? "Bulk publish queued for {$queued} post(s)."
                : 'No posts were queued for publish.';
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} (not ready, email copy-only, or already published).";
            }

            return back()->with('success', $message);
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

            $scheduled = 0;
            foreach ($posts as $post) {
                if ($post->content_type === FunnelPromotionPost::TYPE_EMAIL) {
                    continue;
                }

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
                $scheduled++;
            }

            return back()->with('success', $scheduled > 0
                ? "Scheduled {$scheduled} post(s)."
                : 'No posts scheduled (email copy-only posts were skipped).');
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
        $formatCatalog = app(PlatformFormatCatalog::class);
        $generationContext = $validated['generation_context'] ?? [];
        $contentFormat = $validated['content_format']
            ?? (is_string($generationContext['content_format'] ?? null) ? $generationContext['content_format'] : null);
        $formatSpec = is_string($contentFormat) && $contentFormat !== ''
            ? $formatCatalog->format($contentFormat)
            : null;

        $contentType = $validated['content_type'];

        if ($formatSpec !== null) {
            $contentType = $formatCatalog->mapToPromotionContentType($contentFormat);
        }

        if (is_string($contentFormat) && $contentFormat !== '') {
            $generationContext['content_format'] = $contentFormat;
        }

        if ($formatSpec !== null) {
            if (! array_key_exists('include_text', $generationContext)) {
                $generationContext['include_text'] = true;
            }
            if (! array_key_exists('include_image', $generationContext)) {
                $generationContext['include_image'] = $formatCatalog->requiresImageAsset($formatSpec);
            }
        }

        $renderProvider = is_string($generationContext['video_render_provider'] ?? null)
            ? (string) $generationContext['video_render_provider']
            : null;
        if ($contentType === FunnelPromotionPost::TYPE_VIDEO && $renderProvider) {
            $generationContext['video_render_provider'] = $renderProvider;
        }

        $metadata = ['created_from' => 'promotion_ui'];
        if ($formatSpec !== null) {
            $metadata['format_key'] = $contentFormat;
            $metadata['format_spec'] = $formatSpec;
            $metadata['media_spec'] = $formatCatalog->mediaSpec($formatSpec);
        }
        if ($renderProvider) {
            $metadata['video_render_provider'] = $renderProvider;
        }

        $post = FunnelPromotionPost::query()->create([
            'user_id' => $request->user()->id,
            'funnel_id' => $funnel->id,
            'title' => $validated['title'] ?? null,
            'topic' => $validated['topic'],
            'content_type' => $contentType,
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
            'generation_context' => $generationContext !== [] ? $generationContext : null,
            'metadata' => $metadata,
        ]);

        if (($validated['auto_generate'] ?? false) === true) {
            $dispatcher = app(PromotionGenerationDispatcher::class);
            $types = $dispatcher->generationTypesForPost($post);
            $usesMultiSlide = $formatCatalog->usesMultiSlideImages($formatSpec);

            Log::info('[Promotion] store: dispatching generation jobs', [
                'post_id' => $post->id,
                'types' => $types,
                'content_format' => $contentFormat,
                'uses_multi_slide' => $usesMultiSlide,
            ]);

            if ($types === []) {
                Log::warning('[Promotion] store: no generation jobs to dispatch', [
                    'post_id' => $post->id,
                    'content_format' => $contentFormat,
                ]);
                $post->update([
                    'status' => FunnelPromotionPost::STATUS_FAILED,
                    'last_error' => 'Nothing to generate — enable slide copy/text or pick another format.',
                ]);
            } else {
                $this->dispatchGeneration($post, $types, false);
            }
        }

        Log::info('[Promotion] store: post created', [
            'post_id' => $post->id,
            'content_type' => $post->content_type,
            'content_format' => $contentFormat,
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
        $types = $validated['types'] ?? [];
        if ($types === []) {
            $types = app(PromotionGenerationDispatcher::class)->generationTypesForPost($post);
        }

        Log::info('[Promotion] generateAssets: dispatching generation jobs', [
            'post_id' => $post->id,
            'types' => $types,
            'platform' => $post->platforms,
        ]);

        $post->update([
            'status' => FunnelPromotionPost::STATUS_GENERATING,
            'last_error' => null,
        ]);

        if ($types === []) {
            $post->update([
                'status' => FunnelPromotionPost::STATUS_FAILED,
                'last_error' => 'Nothing to generate for this post.',
            ]);

            return back()->withErrors(['generate' => 'Nothing to generate for this post.']);
        }

        $this->dispatchGeneration($post, $types, (bool) ($validated['wait_for_video'] ?? false));

        return back()->with('success', 'Generation started.');
    }

    public function schedule(
        FunnelPromotionScheduleRequest $request,
        Funnel $funnel,
        FunnelPromotionPost $post,
    ): RedirectResponse {
        $this->authorizePost($request, $funnel, $post);

        if ($post->content_type === FunnelPromotionPost::TYPE_EMAIL) {
            return back()->withErrors([
                'schedule' => 'Email posts cannot be scheduled for social publish. Copy the content and send via your ESP.',
            ]);
        }

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

    public function exportEmail(Request $request, Funnel $funnel, FunnelPromotionPost $post): StreamedResponse|RedirectResponse
    {
        $this->authorizePost($request, $funnel, $post);

        if ($post->content_type !== FunnelPromotionPost::TYPE_EMAIL) {
            abort(404);
        }

        $subject = trim((string) ($post->email_subject ?? ''));
        $body = trim((string) ($post->email_body ?? $post->text_body ?? ''));

        if ($subject === '' && $body === '') {
            return back()->withErrors(['export' => 'Generate email content before exporting.']);
        }

        $filename = Str::slug($post->topic ?: 'promotion-email').'-'.now()->format('Y-m-d').'.txt';
        $contents = $subject !== ''
            ? "Subject: {$subject}\n\n{$body}"
            : $body;

        return response()->streamDownload(
            function () use ($contents): void {
                echo $contents;
            },
            $filename,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

    /**
     * @param  array<int, string>  $types
     */
    private function dispatchGeneration(FunnelPromotionPost $post, array $types, bool $waitForVideo): void
    {
        app(PromotionGenerationDispatcher::class)->dispatch($post, $types, $waitForVideo);
    }

    private function usesMultiSlideImages(FunnelPromotionPost $post): bool
    {
        $spec = $this->formatSpecForPost($post);

        return app(PlatformFormatCatalog::class)->usesMultiSlideImages($spec);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatSpecForPost(FunnelPromotionPost $post): ?array
    {
        $formatKey = data_get($post->metadata, 'format_key') ?? data_get($post->generation_context, 'content_format');
        if (! is_string($formatKey) || $formatKey === '') {
            return null;
        }

        return app(PlatformFormatCatalog::class)->format($formatKey);
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
                'id' => (string) ($p['presenter_id'] ?? ''),
                'name' => (string) ($p['name'] ?? 'Presenter'),
                'thumbnail_url' => (string) ($p['thumbnail_url'] ?? $p['image_url'] ?? ''),
                'talking_preview_url' => (string) ($p['talking_preview_url'] ?? $p['preview_url'] ?? ''),
                'image_url' => (string) ($p['image_url'] ?? $p['thumbnail_url'] ?? ''),
            ];
        }, array_filter($presenters, fn ($p) => is_array($p) && ! empty($p['presenter_id']))));
    }

    private function authorizeFunnel(Funnel $funnel): void
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless((int) $user->id === (int) $funnel->user_id, 403);
    }

    private function authorizePost(Request $request, Funnel $funnel, FunnelPromotionPost $post): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            (int) $user->id === (int) $post->user_id && (int) $post->funnel_id === (int) $funnel->id,
            403
        );
    }

    private function duplicatePost(FunnelPromotionPost $post): FunnelPromotionPost
    {
        $originalMetadata = (array) ($post->metadata ?? []);

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
        $copy->metadata = array_merge(
            Arr::except($originalMetadata, ['generation_progress', 'publish_result']),
            [
                'duplicated_from' => $post->id,
                'created_from' => 'duplicate',
            ],
        );
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

        $copy->load(['primaryAsset', 'assets']);

        if (app(PromotionGenerationCoordinator::class)->isGenerationComplete($copy)) {
            $copy->update(['status' => FunnelPromotionPost::STATUS_READY]);
        }

        return $copy->fresh(['primaryAsset', 'assets']);
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

    protected function refreshStaleCampaignTopicSuggestions(Funnel $funnel, PromotionTopicSuggestionService $service): void
    {
        $funnel->loadMissing(['campaign', 'settings', 'template.versions', 'keywords']);
        $campaign = $funnel->campaign;
        if ($campaign === null) {
            return;
        }

        $knowledge = app(CampaignKnowledgeContextService::class);
        if (! $knowledge->hasKnowledge($campaign)) {
            return;
        }

        $existing = FunnelPromotionTopicSuggestion::query()
            ->where('funnel_id', $funnel->id)
            ->where('status', FunnelPromotionTopicSuggestion::STATUS_SUGGESTED)
            ->pluck('topic');

        if ($existing->isEmpty()) {
            return;
        }

        $product = mb_strtolower($knowledge->productName($campaign));
        $joined = mb_strtolower($existing->join(' '));

        if ($product !== '' && str_contains(mb_strtolower($product), 'affiliateos')) {
            return;
        }

        $looksStale = str_contains($joined, 'affiliateos blank')
            || str_contains($joined, 'affiliateos')
            || ($product !== '' && ! str_contains($joined, $product));

        if (! $looksStale) {
            return;
        }

        $topics = $service->generate($funnel, 12, null);
        $service->persist($funnel, $topics);
    }
}
