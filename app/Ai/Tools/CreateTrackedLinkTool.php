<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class CreateTrackedLinkTool extends GatedTool
{
    public function toolName(): string
    {
        return 'create_tracked_link';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Create a cloaked tracked link pointing at a destination URL.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'destination_url' => $schema->string()->required(),
            'label' => $schema->string(),
            'campaign_id' => $schema->integer(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Create tracked link to '.$request['destination_url'];
    }

    protected function run(Request $request): string
    {
        $link = app(CommandCenterDomainService::class)->createTrackedLink(
            $this->user,
            (string) $request['destination_url'],
            isset($request['label']) ? (string) $request['label'] : null,
            isset($request['campaign_id']) ? (int) $request['campaign_id'] : null,
        );

        return $this->json($link);
    }
}
