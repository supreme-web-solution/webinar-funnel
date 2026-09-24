<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class DeleteCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'delete_campaign';
    }

    public function permission(): string
    {
        return 'destructive';
    }

    public function description(): string
    {
        return 'Permanently delete a campaign the user owns. Always stages Review & Launch; never runs on Autopilot.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Permanently delete campaign #'.$request['campaign_id'];
    }

    protected function run(Request $request): string
    {
        return $this->json(app(CommandCenterDomainService::class)->deleteCampaign(
            $this->user,
            (int) $request['campaign_id'],
        ));
    }
}
