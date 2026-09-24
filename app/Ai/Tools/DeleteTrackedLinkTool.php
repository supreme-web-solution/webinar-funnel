<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class DeleteTrackedLinkTool extends GatedTool
{
    public function toolName(): string
    {
        return 'delete_tracked_link';
    }

    public function permission(): string
    {
        return 'destructive';
    }

    public function description(): string
    {
        return 'Permanently delete a tracked link the user owns. Always stages Review & Launch; never runs on Autopilot.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tracked_link_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Permanently delete tracked link #'.$request['tracked_link_id'];
    }

    protected function run(Request $request): string
    {
        return $this->json(app(CommandCenterDomainService::class)->deleteTrackedLink(
            $this->user,
            (int) $request['tracked_link_id'],
        ));
    }
}
