<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\ContentEmployeePlan;
use App\Models\FunnelPromotionPost;
use App\Services\Campaigns\CampaignTrafficHubService;
use App\Services\Content\ContentEmployeePlanService;
use App\Services\Content\PlatformFormatCatalog;
use App\Services\Traffic\StandaloneTrafficWorkspaceService;
use App\Services\Traffic\UserTrafficProfileService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrafficHubController extends Controller
{
    public function __construct(
        protected CampaignTrafficHubService $hub,
        protected UserTrafficProfileService $profileService,
        protected StandaloneTrafficWorkspaceService $workspace,
        protected ContentEmployeePlanService $planService,
        protected PlatformFormatCatalog $formatCatalog,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $profilePayload = $this->profileService->payloadForUser($user);
        $workspace = $this->workspace->workspacePayload($user);

        $campaigns = Campaign::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get(['id', 'name', 'slug', 'type', 'status', 'meta', 'offer_data'])
            ->map(function (Campaign $campaign): array {
                $trafficFunnel = $this->hub->primaryTrafficFunnel($campaign);

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'type' => $campaign->type,
                    'status' => $campaign->status,
                    'product_name' => $campaign->offer_data['product_name'] ?? null,
                    'has_traffic_hub' => $trafficFunnel !== null,
                    'traffic_hub_url' => route('campaigns.traffic.index', $campaign),
                    'campaign_edit_url' => route('campaigns.edit', $campaign),
                ];
            });

        $funnelId = $workspace['traffic_funnel']['id'];
        $postStats = [
            'total' => FunnelPromotionPost::query()->where('funnel_id', $funnelId)->count(),
            'scheduled' => FunnelPromotionPost::query()->where('funnel_id', $funnelId)->where('status', FunnelPromotionPost::STATUS_SCHEDULED)->count(),
            'draft' => FunnelPromotionPost::query()->where('funnel_id', $funnelId)->where('status', FunnelPromotionPost::STATUS_DRAFT)->count(),
        ];

        $weekStart = Carbon::now()->startOfWeek();
        $currentPlan = ContentEmployeePlan::query()
            ->where('user_id', $user->id)
            ->whereNull('campaign_id')
            ->where('week_start', $weekStart->toDateString())
            ->with('items')
            ->first();

        return Inertia::render('traffic/Index', [
            'campaigns' => $campaigns,
            'profile' => $profilePayload,
            'workspace' => $workspace,
            'post_stats' => $postStats,
            'current_plan' => $currentPlan ? $this->planService->planPayload($currentPlan) : null,
            'routes' => [
                'profile_update' => route('traffic.profile.update'),
                'social_settings' => route('settings.social-traffic.edit'),
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $validFormatKeys = $this->formatCatalog->allFormatKeys();
        $validPlatforms = array_keys($this->formatCatalog->platforms());
        $validIntensities = array_keys($this->formatCatalog->intensityPresets());

        $validated = $request->validate([
            'intensity' => ['nullable', 'string', 'in:'.implode(',', $validIntensities)],
            'enabled_platforms' => ['nullable', 'array'],
            'enabled_platforms.*' => ['string', 'in:'.implode(',', $validPlatforms)],
            'enabled_formats' => ['nullable', 'array', 'min:1'],
            'enabled_formats.*' => ['string'],
            'frequency_overrides' => ['nullable', 'array'],
            'auto_select_formats' => ['nullable', 'boolean'],
            'plan_platforms' => ['nullable', 'array'],
            'plan_platforms.*' => ['string', 'in:'.implode(',', $validPlatforms)],
            'planning_brief' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['enabled_formats'])) {
            $validated['enabled_formats'] = array_values(array_intersect(
                $validated['enabled_formats'],
                $validFormatKeys,
            ));
        }

        $this->profileService->update($request->user(), $validated);

        return back()->with('success', 'Traffic profile saved.');
    }
}
