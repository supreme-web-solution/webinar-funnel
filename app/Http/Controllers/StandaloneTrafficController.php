<?php

namespace App\Http\Controllers;

use App\Models\Funnel;
use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;
use App\Models\FunnelPromotionPost;
use App\Models\Keyword;
use App\Models\Mention;
use App\Services\DID\DIDClient;
use App\Services\Funnel\FunnelTrafficPayloadBuilder;
use App\Services\Promotion\PromotionPlatformCatalog;
use App\Services\Traffic\StandaloneTrafficWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StandaloneTrafficController extends Controller
{
    public function __construct(
        protected StandaloneTrafficWorkspaceService $workspace,
        protected FunnelTrafficPayloadBuilder $payloads,
    ) {}

    public function index(Request $request): Response
    {
        $funnel = $this->resolveFunnel($request);
        $hub = $this->workspace->hubPayload($request->user());

        $promotionStats = FunnelPromotionPost::query()->where('funnel_id', $funnel->id);
        $adsStats = FunnelAdCampaign::query()->where('funnel_id', $funnel->id);

        return Inertia::render('traffic/WorkspaceHub', [
            ...$hub,
            'stats' => [
                'keywords' => $funnel->keywords()->count(),
                'mentions' => $this->payloads->mentionCountForFunnel($funnel),
                'promotion_posts' => (clone $promotionStats)->count(),
                'scheduled_posts' => (clone $promotionStats)->where('status', FunnelPromotionPost::STATUS_SCHEDULED)->count(),
                'ad_campaigns' => (clone $adsStats)->count(),
                'active_ads' => (clone $adsStats)->where('status', FunnelAdCampaign::STATUS_ACTIVE)->count(),
            ],
            'paid_ads_enabled' => (bool) config('promotion.ads.enabled'),
        ]);
    }

    public function freeTraffic(Request $request): Response
    {
        $funnel = $this->resolveFunnel($request);
        $hub = $this->workspace->hubPayload($request->user());

        $funnel->load(['settings', 'template']);

        return Inertia::render('traffic/WorkspaceFreeTraffic', [
            ...$hub,
            'traffic' => $this->payloads->buildTrafficData($request, $funnel),
            'settings' => $funnel->settings,
            'routes' => array_merge($hub['routes'], $this->trafficMutationRoutes()),
        ]);
    }

    public function promotionPosts(Request $request, DIDClient $did, PromotionPlatformCatalog $platformCatalog): Response
    {
        $funnel = $this->resolveFunnel($request);

        return app(FunnelPromotionController::class)->index(
            $request,
            $funnel,
            $did,
            $platformCatalog,
            null,
        );
    }

    public function promotionCalendar(Request $request): Response
    {
        $funnel = $this->resolveFunnel($request);

        return app(FunnelPromotionCalendarController::class)->index($request, $funnel, null);
    }

    public function ads(Request $request): Response
    {
        $funnel = $this->resolveFunnel($request);

        return app(FunnelAdCampaignController::class)->index($request, $funnel, null);
    }

    public function storeKeyword(Request $request): RedirectResponse
    {
        return app(FunnelTrafficController::class)->storeKeyword($request, $this->resolveFunnel($request));
    }

    public function updateKeyword(Request $request, Keyword $keyword): RedirectResponse
    {
        return app(FunnelTrafficController::class)->updateKeyword($request, $this->resolveFunnel($request), $keyword);
    }

    public function destroyKeyword(Request $request, Keyword $keyword): RedirectResponse
    {
        return app(FunnelTrafficController::class)->destroyKeyword($request, $this->resolveFunnel($request), $keyword);
    }

    public function fetchKeyword(Request $request, Keyword $keyword): RedirectResponse
    {
        return app(FunnelTrafficController::class)->fetchNow($request, $this->resolveFunnel($request), $keyword);
    }

    public function draftMentionReply(Request $request, Mention $mention): RedirectResponse|JsonResponse
    {
        return app(FunnelTrafficController::class)->draftMentionReply($request, $this->resolveFunnel($request), $mention);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        return app(FunnelController::class)->updateSettings($request, $this->resolveFunnel($request));
    }

    public function storeAd(Request $request): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->store($request, $this->resolveFunnel($request));
    }

    public function updateAd(Request $request, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->update($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function destroyAd(Request $request, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->destroy($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function duplicateAd(Request $request, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->duplicate($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function researchAd(Request $request, FunnelAdCampaign $adCampaign): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->research($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function generateAdCreatives(Request $request, FunnelAdCampaign $adCampaign): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->generateCreatives($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function storeAdCreative(Request $request, FunnelAdCampaign $adCampaign): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->storeCreative($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function updateAdCreative(Request $request, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->updateCreative($request, $this->resolveFunnel($request), $adCampaign, $creative);
    }

    public function destroyAdCreative(Request $request, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->destroyCreative($request, $this->resolveFunnel($request), $adCampaign, $creative);
    }

    public function generateAdCreativeImage(Request $request, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->generateImage($request, $this->resolveFunnel($request), $adCampaign, $creative);
    }

    public function toggleAdCreative(Request $request, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        return app(FunnelAdCampaignController::class)->toggleCreativeStatus($request, $this->resolveFunnel($request), $adCampaign, $creative);
    }

    public function launchAd(Request $request, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->launch($request, $this->resolveFunnel($request), $adCampaign);
    }

    public function syncAd(Request $request, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        return app(FunnelAdCampaignController::class)->syncPerformance($request, $this->resolveFunnel($request), $adCampaign);
    }

    /**
     * @return array<string, string>
     */
    protected function trafficMutationRoutes(): array
    {
        return [
            'settings_patch' => route('traffic.workspace.settings.update'),
            'keywords_store' => route('traffic.workspace.keywords.store'),
            'keywords_update' => route('traffic.workspace.keywords.update', ['keyword' => '__ID__']),
            'keywords_destroy' => route('traffic.workspace.keywords.destroy', ['keyword' => '__ID__']),
            'keywords_fetch' => route('traffic.workspace.keywords.fetch', ['keyword' => '__ID__']),
            'draft_reply' => route('traffic.workspace.mentions.draft-reply', ['mention' => '__ID__']),
        ];
    }

    protected function resolveFunnel(Request $request): Funnel
    {
        $funnel = $this->workspace->funnelForUser($request->user());
        abort_unless((int) $funnel->user_id === (int) $request->user()->id, 403);

        return $funnel;
    }
}
