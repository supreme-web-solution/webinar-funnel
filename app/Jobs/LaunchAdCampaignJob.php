<?php

namespace App\Jobs;

use App\Models\FunnelAdCampaign;
use App\Services\Ads\AdCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LaunchAdCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(AdCampaignService $service): void
    {
        $campaign = FunnelAdCampaign::find($this->campaignId);

        if (! $campaign) {
            Log::warning('[Ads] LaunchAdCampaignJob: campaign not found', ['id' => $this->campaignId]);
            return;
        }

        Log::info('[Ads] Launching campaign', ['campaign_id' => $campaign->id, 'name' => $campaign->name]);

        try {
            $service->launchCampaign($campaign);
            Log::info('[Ads] Campaign launched', ['campaign_id' => $campaign->id, 'status' => $campaign->fresh()?->status]);
        } catch (\Throwable $e) {
            Log::error('[Ads] Campaign launch exception', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
            $campaign->update(['status' => FunnelAdCampaign::STATUS_FAILED, 'last_error' => $e->getMessage()]);
        }
    }

}
