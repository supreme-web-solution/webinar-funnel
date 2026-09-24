<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class QuickStartCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'quick_start_campaign';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Create a campaign from an offer URL and queue the full AI build (pages, bonuses, emails).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'offer_url' => $schema->string()->required(),
            'name' => $schema->string(),
            'type' => $schema->string(),
            'affiliate_link' => $schema->string(),
            'marketplace' => $schema->string(),
            'keyword' => $schema->string(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Quick-start campaign from '.$request['offer_url'];
    }

    protected function run(Request $request): string
    {
        $campaign = app(CommandCenterDomainService::class)->quickStartCampaign(
            $this->user,
            (string) $request['offer_url'],
            isset($request['name']) ? (string) $request['name'] : null,
            (string) ($request['type'] ?? 'sales'),
            isset($request['affiliate_link']) ? (string) $request['affiliate_link'] : null,
            isset($request['marketplace']) ? (string) $request['marketplace'] : null,
            isset($request['keyword']) ? (string) $request['keyword'] : null,
        );

        return $this->json([
            'created' => true,
            'queued' => true,
            'id' => $campaign->id,
            'name' => $campaign->name,
            'edit_url' => '/campaigns/'.$campaign->id.'/edit',
            'message' => 'Campaign created and AI generation is running in the background.',
        ]);
    }
}
