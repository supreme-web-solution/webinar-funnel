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

class SyncAdPerformanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 90;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue((string) config('promotion.queues.generate', 'promotion-generate'));
    }

    public function handle(AdCampaignService $service): void
    {
        $campaign = FunnelAdCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        if (! in_array($campaign->status, [FunnelAdCampaign::STATUS_ACTIVE, FunnelAdCampaign::STATUS_PAUSED], true)) {
            return;
        }

        Log::info('[Ads] Syncing performance', ['campaign_id' => $campaign->id]);

        try {
            $service->syncPerformance($campaign);
        } catch (\Throwable $e) {
            Log::warning('[Ads] Performance sync exception', ['campaign_id' => $campaign->id, 'error' => $e->getMessage()]);
        }

        // Schedule next sync in 4 hours while campaign is active
        if ($campaign->fresh()?->status === FunnelAdCampaign::STATUS_ACTIVE) {
            self::dispatch($this->campaignId)->delay(now()->addHours(4));
        }
    }

}
