<?php

namespace App\Ai\Tools;

use App\Models\CampaignLead;
use App\Models\Funnel;
use App\Models\Lead;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ListLeadsTool extends GatedTool
{
    public function toolName(): string
    {
        return 'list_leads';
    }

    public function permission(): string
    {
        return 'read';
    }

    public function description(): string
    {
        return 'List recent leads captured on funnels and campaigns.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->min(1)->max(50),
        ];
    }

    protected function run(Request $request): string
    {
        $limit = min(50, max(1, (int) ($request['limit'] ?? 15)));
        $funnelIds = Funnel::query()->where('user_id', $this->user->id)->pluck('id');

        $funnelLeads = Lead::query()
            ->whereIn('funnel_id', $funnelIds)
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'email', 'funnel_id', 'created_at']);

        $campaignLeads = CampaignLead::query()
            ->whereHas('campaign', fn ($q) => $q->where('user_id', $this->user->id))
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'email', 'campaign_id', 'created_at']);

        return $this->json([
            'funnel_leads' => $funnelLeads->toArray(),
            'campaign_leads' => $campaignLeads->toArray(),
        ]);
    }
}
