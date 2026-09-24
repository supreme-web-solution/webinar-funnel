<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class PublishCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'publish_campaign';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Publish a campaign and its funnels so the public pages go live.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Publish campaign #'.$request['campaign_id'];
    }

    protected function run(Request $request): string
    {
        $campaign = app(CommandCenterDomainService::class)->publishCampaign(
            $this->user,
            (int) $request['campaign_id'],
        );

        return $this->json([
            'published' => true,
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status,
        ]);
    }
}
