<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\ActionApprovalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class DraftContentPlanTool extends GatedTool
{
    public function toolName(): string
    {
        return 'draft_content_plan';
    }

    public function permission(): string
    {
        return 'prepare';
    }

    public function description(): string
    {
        return 'Stage a weekly content-employee plan. Does not generate posts until LAUNCH.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer()->description('Optional campaign to plan around'),
            'planning_brief' => $schema->string(),
        ];
    }

    protected function run(Request $request): string
    {
        $summary = 'Generate this week’s content plan'
            .(isset($request['campaign_id']) ? ' for campaign #'.$request['campaign_id'] : ' (standalone)');

        $approval = app(ActionApprovalService::class)->stageNamed(
            $this->user,
            'generate_content_plan',
            'execute',
            $request->all(),
            $summary,
            $this->conversationId(),
        );

        return 'Staged **'.$approval->launchLabel().'**: '.$summary."\nIt has not run yet. Reply `LAUNCH {$approval->id}`.";
    }
}
