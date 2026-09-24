<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\CommandCenterDomainService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class DeleteContentPlanTool extends GatedTool
{
    public function toolName(): string
    {
        return 'delete_content_plan';
    }

    public function permission(): string
    {
        return 'destructive';
    }

    public function description(): string
    {
        return 'Permanently delete a content-employee plan that is not currently executing. Always stages Review & Launch; never runs on Autopilot.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Permanently delete content plan #'.$request['plan_id'];
    }

    protected function run(Request $request): string
    {
        return $this->json(app(CommandCenterDomainService::class)->deleteContentPlan(
            $this->user,
            (int) $request['plan_id'],
        ));
    }
}
