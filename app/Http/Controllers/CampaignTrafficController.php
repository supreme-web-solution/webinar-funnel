<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelAdCampaign;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelPromotionTopicSuggestion;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\DID\DIDClient;
use App\Services\Funnel\FunnelPaidTrafficAssetsService;
use App\Services\Funnel\FunnelTrafficPayloadBuilder;
use App\Services\Promotion\PromotionCtaResolverService;
use App\Services\Promotion\PromotionPlatformCatalog;
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
        $adsStats = FunnelAdCampaign::query()->where('funnel_id', $funnel->id);

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
            'routes' => array_merge($hub['routes'], [
                'settings_patch' => route('funnels.settings.update', $funnel),
                'keywords_store' => route('funnels.traffic.keywords.store', $funnel),
                'keywords_update' => route('funnels.traffic.keywords.update', ['funnel' => $funnel, 'keyword' => '__ID__']),
                'keywords_destroy' => route('funnels.traffic.keywords.destroy', ['funnel' => $funnel, 'keyword' => '__ID__']),
                'keywords_fetch' => route('funnels.traffic.keywords.fetch', ['funnel' => $funnel, 'keyword' => '__ID__']),
                'draft_reply' => route('funnels.traffic.mentions.draft-reply', ['funnel' => $funnel, 'mention' => '__ID__']),
            ]),
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

    protected function authorizeCampaign(Campaign $campaign): void
    {
        abort_unless((int) $campaign->user_id === (int) auth()->id(), 403);
    }
}
