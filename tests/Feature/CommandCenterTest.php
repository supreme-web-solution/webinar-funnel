<?php

namespace Tests\Feature;

use App\Ai\Agents\AppEmployeeAgent;
use App\Ai\Tools\DeleteCampaignTool;
use App\Models\AiActionApproval;
use App\Models\AiEmployeeSession;
use App\Models\AiEmployeeSetting;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use App\Services\AiEmployee\AiEmployeeSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_command_center(): void
    {
        $this->get(route('command-center.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_command_center(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('command-center.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('command-center/Index')
                ->has('employee')
                ->has('messages')
                ->has('execute_tools')
                ->where('settings.autonomy', 'assisted')
            );
    }

    public function test_help_command_does_not_call_the_model(): void
    {
        $user = User::factory()->create();
        AppEmployeeAgent::fake(['should not be used']);

        $this->actingAs($user)
            ->postJson(route('command-center.chat'), ['message' => 'help'])
            ->assertOk()
            ->assertJsonPath('status', 'complete')
            ->assertJsonFragment(['processing' => false]);

        AppEmployeeAgent::assertNeverPrompted();
    }

    public function test_status_command_returns_workspace_snapshot(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('command-center.chat'), ['message' => 'status'])
            ->assertOk()
            ->assertJsonPath('status', 'complete');
    }

    public function test_kill_switch_blocks_chat_until_resume(): void
    {
        $user = User::factory()->create();
        AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => true,
            'autonomy' => 'assisted',
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('command-center.chat'), ['message' => 'list my campaigns']);

        $response->assertOk()->assertJsonPath('status', 'complete');
        $this->assertStringContainsStringIgnoringCase('paused', (string) $response->json('message'));

        $response = $this->actingAs($user)
            ->postJson(route('command-center.chat'), ['message' => 'RESUME']);

        $response->assertOk();
        $this->assertFalse(AiEmployeeSetting::query()->where('user_id', $user->id)->value('killed'));
    }

    public function test_launch_executes_a_pending_approval(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('command-center.index'))->assertOk();

        $approval = AiActionApproval::query()->create([
            'user_id' => $user->id,
            'tool_name' => 'create_tracked_link',
            'permission' => 'execute',
            'summary' => 'Create tracked link to https://example.com',
            'payload' => [
                'destination_url' => 'https://example.com/offer',
                'label' => 'Offer',
            ],
            'status' => AiActionApproval::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->postJson(route('command-center.chat'), ['message' => 'LAUNCH '.$approval->id])
            ->assertOk();

        $this->assertDatabaseHas('tracked_links', [
            'user_id' => $user->id,
            'destination_url' => 'https://example.com/offer',
        ]);
        $this->assertDatabaseHas('ai_action_approvals', [
            'id' => $approval->id,
            'status' => AiActionApproval::STATUS_EXECUTED,
        ]);
    }

    public function test_whatsapp_webhook_pairs_a_phone_with_the_user(): void
    {
        Http::fake();
        config(['ai_employee.whatsapp.webhook_secret' => 'test-secret']);
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('command-center.whatsapp.pairing'))->assertOk();

        $code = AiEmployeeSetting::query()->where('user_id', $user->id)->value('pairing_code');
        $this->assertNotNull($code);

        $payload = [
            'id' => 'evt_1',
            'event' => 'message.received',
            'account' => ['platform' => 'whatsapp', 'accountId' => 'acc_1'],
            'conversation' => ['id' => 'conv_1'],
            'message' => [
                'text' => $code,
                'sender' => ['phone' => '+15551231234'],
            ],
        ];
        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, 'test-secret');

        $this->call(
            'POST',
            route('webhooks.zernio.inbox'),
            [],
            [],
            [],
            [
                'HTTP_X-Zernio-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $raw,
        )->assertOk();

        $this->assertSame(
            '15551231234',
            AiEmployeeSetting::query()->where('user_id', $user->id)->value('whatsapp_phone')
        );
    }

    public function test_whatsapp_webhook_rejects_bad_signature(): void
    {
        config(['ai_employee.whatsapp.webhook_secret' => 'test-secret']);

        $this->postJson(route('webhooks.zernio.inbox'), ['event' => 'message.received'], [
            'X-Zernio-Signature' => 'nope',
        ])->assertStatus(400);
    }

    public function test_explicit_launch_still_runs_when_autonomy_is_copilot(): void
    {
        $user = User::factory()->create();
        AiEmployeeSetting::query()->create([
            'user_id' => $user->id,
            'killed' => false,
            'autonomy' => AiEmployeeSetting::AUTONOMY_COPILOT,
        ]);

        $this->actingAs($user)->get(route('command-center.index'))->assertOk();

        $approval = AiActionApproval::query()->create([
            'user_id' => $user->id,
            'tool_name' => 'create_campaign',
            'permission' => 'execute',
            'summary' => 'Create campaign',
            'payload' => ['name' => 'From chat', 'type' => 'sales'],
            'status' => AiActionApproval::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->postJson(route('command-center.approvals.launch', $approval))
            ->assertOk();

        $this->assertDatabaseHas('campaigns', [
            'user_id' => $user->id,
            'name' => 'From chat',
        ]);
    }

    public function test_delete_campaign_stages_then_launch_deletes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('command-center.index'))->assertOk();

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'Remove Me',
            'slug' => 'remove-me',
            'type' => Campaign::TYPE_SALES,
            'status' => 'draft',
        ]);

        $result = (string) (new DeleteCampaignTool($user))->handle(new Request([
            'campaign_id' => $campaign->id,
        ]));

        $this->assertStringContainsString('LAUNCH', $result);
        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);

        $approval = AiActionApproval::query()
            ->where('user_id', $user->id)
            ->where('tool_name', 'delete_campaign')
            ->first();
        $this->assertNotNull($approval);

        $this->actingAs($user)
            ->postJson(route('command-center.approvals.launch', $approval))
            ->assertOk();

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
    }

    public function test_command_center_loads_recent_messages_only(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('command-center.index'))->assertOk();

        $session = AiEmployeeSession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($session?->conversation_id);

        for ($i = 0; $i < 35; $i++) {
            ConversationMessage::query()->create([
                'id' => (string) Str::uuid7(),
                'conversation_id' => $session->conversation_id,
                'user_id' => $user->id,
                'agent' => AppEmployeeAgent::class,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => 'Message '.$i,
                'attachments' => [],
                'tool_calls' => [],
                'tool_results' => [],
                'usage' => [],
                'meta' => [],
                'created_at' => now()->subMinutes(35 - $i),
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('command-center.state'))
            ->assertOk()
            ->assertJsonCount(30, 'messages')
            ->assertJsonPath('messages_meta.has_older', true);

        $oldestId = $this->getJson(route('command-center.state'))->json('messages_meta.oldest_id');
        $this->assertNotNull($oldestId);

        $this->actingAs($user)
            ->getJson(route('command-center.messages', ['before' => $oldestId]))
            ->assertOk()
            ->assertJsonPath('has_older', false)
            ->assertJsonCount(5, 'data');
    }

    public function test_settings_strip_delete_tools_from_autopilot_allowlist(): void
    {
        $user = User::factory()->create();

        app(AiEmployeeSettingsService::class)->update($user, [
            'autonomy' => 'autopilot',
            'execute_allowlist' => ['create_campaign', 'delete_campaign', 'delete_tracked_link'],
        ]);

        $allowlist = AiEmployeeSetting::query()->where('user_id', $user->id)->value('execute_allowlist');
        $this->assertSame(['create_campaign'], $allowlist);
    }
}
