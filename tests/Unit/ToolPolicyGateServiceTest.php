<?php

namespace Tests\Unit;

use App\Ai\Tools\CreateCampaignTool;
use App\Ai\Tools\DeleteCampaignTool;
use App\Models\AiEmployeeSetting;
use App\Models\User;
use App\Services\AiEmployee\ToolPolicyGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ToolPolicyGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_copilot_blocks_execute_tools(): void
    {
        $user = User::factory()->create();
        $setting = AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_COPILOT,
        ]);

        $tool = new CreateCampaignTool($user);
        $gate = app(ToolPolicyGateService::class)->inspect($tool, $setting);

        $this->assertFalse($gate['ok']);
        $this->assertStringContainsString('Copilot', $gate['message'] ?? '');
    }

    public function test_assisted_stages_execute_tools(): void
    {
        $user = User::factory()->create();
        $setting = AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_ASSISTED,
        ]);

        $gate = app(ToolPolicyGateService::class)->inspect(new CreateCampaignTool($user), $setting);

        $this->assertTrue($gate['ok']);
        $this->assertTrue($gate['stage_approval'] ?? false);
    }

    public function test_autopilot_allowlist_skips_approval(): void
    {
        $user = User::factory()->create();
        $setting = AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_AUTOPILOT,
            'execute_allowlist' => ['create_campaign'],
        ]);

        $gate = app(ToolPolicyGateService::class)->inspect(new CreateCampaignTool($user), $setting);

        $this->assertTrue($gate['ok']);
        $this->assertFalse($gate['stage_approval'] ?? true);
    }

    public function test_destructive_tools_always_stage_even_on_autopilot_allowlist(): void
    {
        $user = User::factory()->create();
        $setting = AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_AUTOPILOT,
            'execute_allowlist' => ['delete_campaign', 'create_campaign'],
        ]);

        $gate = app(ToolPolicyGateService::class)->inspect(new DeleteCampaignTool($user), $setting);

        $this->assertTrue($gate['ok']);
        $this->assertTrue($gate['stage_approval'] ?? false);
    }

    public function test_gated_tool_stages_instead_of_running_when_assisted(): void
    {
        $user = User::factory()->create();
        AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_ASSISTED,
        ]);

        $result = (string) (new CreateCampaignTool($user))->handle(new Request([
            'name' => 'Should Stage',
            'type' => 'sales',
        ]));

        $this->assertStringContainsString('LAUNCH', $result);
        $this->assertDatabaseMissing('campaigns', ['name' => 'Should Stage']);
        $this->assertDatabaseHas('ai_action_approvals', [
            'user_id' => $user->id,
            'tool_name' => 'create_campaign',
            'status' => 'pending',
        ]);
    }
}
