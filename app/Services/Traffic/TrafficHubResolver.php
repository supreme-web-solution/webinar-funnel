<?php

namespace App\Services\Traffic;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Services\Campaigns\CampaignTrafficHubService;

final class TrafficHubResolver
{
    public function __construct(
        private readonly CampaignTrafficHubService $campaignHub,
        private readonly StandaloneTrafficWorkspaceService $standaloneHub,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function payload(Funnel $funnel, ?Campaign $campaign = null): ?array
    {
        if ($campaign !== null) {
            return $this->campaignHub->hubPayload($campaign);
        }

        return $this->standaloneHub->hubPayloadForFunnel($funnel);
    }

    public function isStandaloneContext(Funnel $funnel, ?Campaign $campaign = null): bool
    {
        return $campaign === null && $this->standaloneHub->isStandaloneFunnel($funnel);
    }
}
