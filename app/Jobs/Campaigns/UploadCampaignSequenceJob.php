<?php

namespace App\Jobs\Campaigns;

use App\Models\Campaign;
use App\Models\IntegrationAccount;
use App\Services\Esp\EspSequenceUploader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UploadCampaignSequenceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $campaignId,
        public int $integrationAccountId,
        public ?string $tag = null,
    ) {
        $this->onQueue('esp-dispatch');
    }

    public function handle(EspSequenceUploader $uploader): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        $account = IntegrationAccount::query()->find($this->integrationAccountId);

        if (! $campaign || ! $account || (int) $account->user_id !== (int) $campaign->user_id) {
            return;
        }

        $result = $uploader->uploadForCampaign($campaign, $account, $this->tag);

        $meta = $campaign->meta ?? [];
        $meta['esp_upload'] = [
            'integration_account_id' => $account->id,
            'provider' => $account->provider,
            'tag' => $this->tag,
            'ok' => $result['ok'],
            'message' => $result['message'],
            'uploaded' => $result['uploaded'] ?? 0,
            'details' => $result['details'] ?? [],
            'at' => now()->toIso8601String(),
        ];
        $campaign->update(['meta' => $meta]);

        Log::info('[CampaignESP] Sequence upload', [
            'campaign_id' => $campaign->id,
            'provider' => $account->provider,
            'ok' => $result['ok'],
            'uploaded' => $result['uploaded'] ?? 0,
        ]);
    }
}
