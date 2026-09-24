<?php

namespace App\Ai\Tools;

use App\Models\ContentEmployeePlan;
use App\Services\Content\ContentEmployeePlanService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;

class ExecuteContentPlanTool extends GatedTool
{
    public function toolName(): string
    {
        return 'execute_content_plan';
    }

    public function permission(): string
    {
        return 'execute';
    }

    public function description(): string
    {
        return 'Approve and execute a content-employee plan, generating the weekly posts.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'plan_id' => $schema->integer()->required(),
        ];
    }

    protected function approvalSummary(Request $request): string
    {
        return 'Execute content plan #'.$request['plan_id'];
    }

    protected function run(Request $request): string
    {
        $plan = ContentEmployeePlan::query()
            ->where('user_id', $this->user->id)
            ->where('id', (int) $request['plan_id'])
            ->first();

        if ($plan === null) {
            return $this->json(['error' => 'Plan not found.']);
        }

        $service = app(ContentEmployeePlanService::class);
        $service->approve($plan);
        $plan = $service->execute($plan->fresh(), $this->user);

        return $this->json([
            'plan_id' => $plan->id,
            'status' => $plan->status,
            'href' => '/growth/content-employee',
        ]);
    }
}
