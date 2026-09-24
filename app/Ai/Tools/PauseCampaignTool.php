<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class PauseCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'pause_campaign';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Unpublish a campaign and its funnels (pause public pages).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Pause / unpublish campaign #'.$request['campaign_id'];
    }

    protected function run(Request $request): string
    {
        $campaign = app(CommandCenterDomainService::class)->pauseCampaign(
            $this->user,
            (int) $request['campaign_id'],
        );

        return $this->json([
            'paused' => true,
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
        ]);
    }
}
