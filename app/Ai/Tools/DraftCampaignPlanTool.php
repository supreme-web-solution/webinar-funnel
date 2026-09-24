<?php

namespace App\Ai\Tools;

use App\Services\AiEmployee\ActionApprovalService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class DraftCampaignPlanTool extends GatedTool
{
    public function toolName(): string
    {
        return 'draft_campaign_plan';
    }

    public function permission(): string
    {
        return 'prepare';
    }

    public function description(): string
    {
        return 'Stage a plan to create or quick-start a campaign. Does not mutate until the user says LAUNCH.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required()->description('create or quick_start'),
            'name' => $schema->string(),
            'type' => $schema->string()->description('sales or webinar'),
            'offer_url' => $schema->string(),
            'affiliate_link' => $schema->string(),
            'marketplace' => $schema->string(),
            'keyword' => $schema->string(),
        ];
    }

    protected function run(Request $request): string
    {
        $action = in_array($request['action'] ?? '', ['create', 'quick_start'], true)
            ? $request['action']
            : 'create';
        $toolName = $action === 'quick_start' ? 'quick_start_campaign' : 'create_campaign';
        $summary = $action === 'quick_start'
            ? 'Quick-start a campaign from '.($request['offer_url'] ?? 'the offer URL')
            : 'Create draft campaign “'.($request['name'] ?? 'Untitled').'”';

        $approval = app(ActionApprovalService::class)->stageNamed(
            $this->user,
            $toolName,
            'execute',
            $request->all(),
            $summary,
            $this->conversationId(),
        );

        return 'Staged **'.$approval->launchLabel().'**: '.$summary."\nIt has not run yet. Reply `LAUNCH {$approval->id}`.";
    }
}
