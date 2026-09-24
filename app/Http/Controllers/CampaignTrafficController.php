<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;
use App\Models\FunnelPromotionPost;
use App\Models\Keyword;
use App\Models\Mention;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\DID\DIDClient;
use App\Services\Funnel\FunnelTrafficPayloadBuilder;
use App\Services\Promotion\PromotionPlatformCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CampaignTrafficController extends Controller
{
    public function __construct(
        protected CampaignTrafficHubService $hub,
        protected FunnelTrafficPayloadBuilder $payloads,
    ) {}

    public function index(Campaign $campaign): Response
    {
        $this->authorizeCampaign($campaign);
        $funnel = $this->hub->ensureTrafficFunnel($campaign);
        $hub = $this->hub->hubPayload($campaign);

        $promotionStats = FunnelPromotionPost::query()->where('funnel_id', $funnel->id);
        $adsStats = $this->adCampaignQuery($campaign, $funnel);

        return Inertia::render('campaigns/traffic/Hub', [
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

    public function freeTraffic(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaign($campaign);
        $funnel = $this->hub->ensureTrafficFunnel($campaign);
        $hub = $this->hub->hubPayload($campaign);

        $funnel->load(['settings', 'template']);

        return Inertia::render('campaigns/traffic/FreeTraffic', [
            ...$hub,
            'traffic' => $this->payloads->buildTrafficData($request, $funnel),
            'settings' => $funnel->settings,
            'routes' => array_merge($hub['routes'], $this->trafficMutationRoutes($campaign, $funnel)),
        ]);
    }

    public function promotionPosts(Request $request, Campaign $campaign, DIDClient $did, PromotionPlatformCatalog $platformCatalog): Response
    {
        $this->authorizeCampaign($campaign);
        $funnel = $this->hub->ensureTrafficFunnel($campaign);

        return app(FunnelPromotionController::class)->index($request, $funnel, $did, $platformCatalog, $campaign);
    }

    public function promotionCalendar(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaign($campaign);
        $funnel = $this->hub->ensureTrafficFunnel($campaign);

        return app(FunnelPromotionCalendarController::class)->index($request, $funnel, $campaign);
    }

    public function ads(Request $request, Campaign $campaign): Response
    {
        $this->authorizeCampaign($campaign);
        $funnel = $this->hub->ensureTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->index($request, $funnel, $campaign);
    }

    public function storeKeyword(Request $request, Campaign $campaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelTrafficController::class)->storeKeyword($request, $funnel);
    }

    public function updateKeyword(Request $request, Campaign $campaign, Keyword $keyword): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelTrafficController::class)->updateKeyword($request, $funnel, $keyword);
    }

    public function destroyKeyword(Request $request, Campaign $campaign, Keyword $keyword): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelTrafficController::class)->destroyKeyword($request, $funnel, $keyword);
    }

    public function fetchKeyword(Request $request, Campaign $campaign, Keyword $keyword): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelTrafficController::class)->fetchNow($request, $funnel, $keyword);
    }

    public function draftMentionReply(Request $request, Campaign $campaign, Mention $mention): RedirectResponse|JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelTrafficController::class)->draftMentionReply($request, $funnel, $mention);
    }

    public function updateSettings(Request $request, Campaign $campaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelController::class)->updateSettings($request, $funnel);
    }

    public function storeAd(Request $request, Campaign $campaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->store($request, $funnel, $campaign);
    }

    public function updateAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->update($request, $funnel, $adCampaign, $campaign);
    }

    public function destroyAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->destroy($request, $funnel, $adCampaign, $campaign);
    }

    public function duplicateAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->duplicate($request, $funnel, $adCampaign, $campaign);
    }

    public function researchAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->research($request, $funnel, $adCampaign);
    }

    public function generateAdCreatives(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->generateCreatives($request, $funnel, $adCampaign);
    }

    public function storeAdCreative(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->storeCreative($request, $funnel, $adCampaign);
    }

    public function updateAdCreative(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->updateCreative($request, $funnel, $adCampaign, $creative);
    }

    public function destroyAdCreative(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->destroyCreative($request, $funnel, $adCampaign, $creative);
    }

    public function generateAdCreativeImage(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->generateImage($request, $funnel, $adCampaign, $creative);
    }

    public function toggleAdCreative(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign, FunnelAdCreative $creative): JsonResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->toggleCreativeStatus($request, $funnel, $adCampaign, $creative);
    }

    public function launchAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->launch($request, $funnel, $adCampaign, $campaign);
    }

    public function syncAd(Request $request, Campaign $campaign, FunnelAdCampaign $adCampaign): RedirectResponse
    {
        $funnel = $this->resolveTrafficFunnel($campaign);

        return app(FunnelAdCampaignController::class)->syncPerformance($request, $funnel, $adCampaign, $campaign);
    }

    /**
     * @return array<string, string>
     */
    protected function trafficMutationRoutes(Campaign $campaign, Funnel $funnel): array
    {
        return [
            'settings_patch' => route('campaigns.traffic.settings.update', $campaign),
            'keywords_store' => route('campaigns.traffic.keywords.store', $campaign),
            'keywords_update' => route('campaigns.traffic.keywords.update', ['campaign' => $campaign, 'keyword' => '__ID__']),
            'keywords_destroy' => route('campaigns.traffic.keywords.destroy', ['campaign' => $campaign, 'keyword' => '__ID__']),
            'keywords_fetch' => route('campaigns.traffic.keywords.fetch', ['campaign' => $campaign, 'keyword' => '__ID__']),
            'draft_reply' => route('campaigns.traffic.mentions.draft-reply', ['campaign' => $campaign, 'mention' => '__ID__']),
        ];
    }

    protected function resolveTrafficFunnel(Campaign $campaign): Funnel
    {
        $this->authorizeCampaign($campaign);

        return $this->hub->ensureTrafficFunnel($campaign);
    }

    protected function adCampaignQuery(Campaign $campaign, Funnel $funnel)
    {
        return FunnelAdCampaign::query()
            ->where(function ($query) use ($campaign, $funnel) {
                $query->where('campaign_id', $campaign->id)
                    ->orWhere('funnel_id', $funnel->id);
            });
    }

    protected function authorizeCampaign(Campaign $campaign): void
    {
        abort_unless((int) $campaign->user_id === (int) auth()->id(), 403);
    }
}
