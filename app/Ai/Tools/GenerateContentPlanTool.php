<?php

namespace App\Ai\Tools;

use App\Models\Campaign;
use App\Services\Content\ContentEmployeePlanService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class GenerateContentPlanTool extends GatedTool
{
    public function toolName(): string
    {
        return 'generate_content_plan';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Generate this week’s content-employee plan for a campaign or standalone traffic workspace.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'campaign_id' => $schema->integer(),
            'planning_brief' => $schema->string(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Generate weekly content plan'.(isset($request['campaign_id']) ? ' for campaign #'.$request['campaign_id'] : '');
    }

    protected function run(Request $request): string
    {
        $campaign = null;
        if (isset($request['campaign_id'])) {
            $campaign = Campaign::query()
                ->where('user_id', $this->user->id)
                ->where('id', (int) $request['campaign_id'])
                ->first();
            if ($campaign === null) {
                return $this->json(['error' => 'Campaign not found.']);
            }
        }

        $plan = app(ContentEmployeePlanService::class)->generateWeeklyPlan(
            $this->user,
            $campaign,
            null,
            null,
            isset($request['planning_brief']) ? (string) $request['planning_brief'] : null,
        );

        return $this->json([
            'plan_id' => $plan->id,
            'status' => $plan->status,
            'week_start' => $plan->week_start,
            'item_count' => $plan->items()->count(),
            'href' => '/growth/content-employee',
        ]);
    }
}
