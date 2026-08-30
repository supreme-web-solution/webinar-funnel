<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Services\Campaigns\CampaignTrafficHubService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrafficHubController extends Controller
{
    public function __construct(
        protected CampaignTrafficHubService $hub,
    ) {}

    public function index(Request $request): Response
    {
        $campaigns = Campaign::query()
            ->where('user_id', $request->user()->id)
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

        return Inertia::render('traffic/Index', [
            'campaigns' => $campaigns,
        ]);
    }
}
