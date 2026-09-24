<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignIntegration;
use App\Models\FunnelIntegration;
use App\Models\IntegrationAccount;

class CampaignAutoresponderService
{
    public function __construct(
        protected CampaignLeadCaptureService $leadCapture,
    ) {}

    /**
     * @param  list<array{integration_account_id: int, provider_list_config?: array<string, mixed>|null, enabled?: bool}>  $rows
     */
    public function save(Campaign $campaign, array $rows): void
    {
        $accountIds = IntegrationAccount::query()
            ->where('user_id', $campaign->user_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        CampaignIntegration::query()->where('campaign_id', $campaign->id)->delete();

        foreach ($rows as $row) {
            $accountId = (int) ($row['integration_account_id'] ?? 0);
            if ($accountId <= 0 || ! in_array($accountId, $accountIds, true)) {
                continue;
            }

            CampaignIntegration::query()->create([
                'campaign_id' => $campaign->id,
                'integration_account_id' => $accountId,
                'provider_list_config' => is_array($row['provider_list_config'] ?? null)
                    ? $row['provider_list_config']
                    : [],
                'enabled' => (bool) ($row['enabled'] ?? true),
            ]);
        }

        $this->syncToLeadFunnel($campaign->fresh(['integrations.integrationAccount']));
    }

    public function syncToLeadFunnel(Campaign $campaign): void
    {
        $funnel = $this->leadCapture->resolveLeadFunnelForSync($campaign);
        if (! $funnel) {
            return;
        }

        $campaign->loadMissing(['integrations']);

        $funnel->integrations()->delete();

        foreach ($campaign->integrations as $integration) {
            if (! $integration->enabled) {
                continue;
            }

            FunnelIntegration::query()->create([
                'funnel_id' => $funnel->id,
                'integration_account_id' => $integration->integration_account_id,
                'provider_list_config' => $integration->provider_list_config ?? [],
                'enabled' => true,
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadForCampaign(Campaign $campaign): array
    {
        $campaign->loadMissing(['integrations.integrationAccount']);

        return $campaign->integrations->map(fn (CampaignIntegration $row) => [
            'id' => $row->id,
            'integration_account_id' => $row->integration_account_id,
            'provider_list_config' => $row->provider_list_config ?? [],
            'enabled' => $row->enabled,
            'account' => $row->integrationAccount ? [
                'id' => $row->integrationAccount->id,
                'name' => $row->integrationAccount->name,
                'provider' => $row->integrationAccount->provider,
            ] : null,
        ])->values()->all();
    }
}
