<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class CreateCampaignTool extends GatedTool
{
    public function toolName(): string
    {
        return 'create_campaign';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Create a draft affiliate campaign. Use when the user wants a new campaign without auto-building assets.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'type' => $schema->string()->description('sales or webinar'),
            'offer_url' => $schema->string(),
            'affiliate_link' => $schema->string(),
            'marketplace' => $schema->string(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Create draft campaign “'.($request['name'] ?? 'Untitled').'”';
    }

    protected function run(Request $request): string
    {
        $campaign = app(CommandCenterDomainService::class)->createCampaign(
            $this->user,
            (string) $request['name'],
            (string) ($request['type'] ?? 'sales'),
            isset($request['offer_url']) ? (string) $request['offer_url'] : null,
            isset($request['affiliate_link']) ? (string) $request['affiliate_link'] : null,
            isset($request['marketplace']) ? (string) $request['marketplace'] : null,
        );

        return $this->json([
            'created' => true,
            'id' => $campaign->id,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'edit_url' => '/campaigns/'.$campaign->id.'/edit',
        ]);
    }
}
