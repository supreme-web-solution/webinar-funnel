<?php

namespace Tests\Unit;

use App\Ai\Tools\CreatePromotionPostTool;
use App\Models\FunnelPromotionPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Jobs\GeneratePromotionImageJob;
use App\Jobs\GeneratePromotionTextJob;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CreatePromotionPostToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_traffic_workspace_when_funnel_id_omitted(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $result = (string) (new CreatePromotionPostTool($user))->executeApproved(new Request([
            'topic' => 'Vibe coding tip',
            'platforms' => 'twitter',
        ]));

        $data = json_decode($result, true);
        $this->assertIsArray($data);
        $this->assertArrayNotHasKey('error', $data);
        $this->assertNotEmpty($data['id']);
        $this->assertDatabaseHas('funnel_promotion_posts', [
            'user_id' => $user->id,
            'topic' => 'Vibe coding tip',
            'status' => FunnelPromotionPost::STATUS_GENERATING,
        ]);
    }

    public function test_twitter_thread_sets_x_thread_format_and_three_parts(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $result = (string) (new CreatePromotionPostTool($user))->executeApproved(new Request([
            'topic' => 'Vibe coding',
            'twitter_thread' => true,
            'thread_parts' => 3,
        ]));

        $data = json_decode($result, true);
        $this->assertSame('x_thread', $data['format']);
        $post = FunnelPromotionPost::query()->find($data['id']);
        $this->assertNotNull($post);
        $this->assertSame('x_thread', $post->metadata['format_key'] ?? null);
        $this->assertSame(3, $post->metadata['format_spec']['thread_parts_min'] ?? null);
    }

    public function test_image_format_dispatches_text_and_image_jobs(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $result = (string) (new CreatePromotionPostTool($user))->executeApproved(new Request([
            'topic' => 'Pin headline test',
            'content_format' => 'pinterest_static_pin',
            'platforms' => 'pinterest',
        ]));

        $data = json_decode($result, true);
        $this->assertSame('pinterest_static_pin', $data['format']);

        Queue::assertPushed(GeneratePromotionTextJob::class);
        Queue::assertPushed(GeneratePromotionImageJob::class);
    }

    public function test_rejects_invalid_funnel_id_with_hint(): void
    {
        $user = User::factory()->create();

        $result = (string) (new CreatePromotionPostTool($user))->executeApproved(new Request([
            'funnel_id' => 1,
            'topic' => 'Test',
        ]));

        $data = json_decode($result, true);
        $this->assertSame('Funnel not found. You have no funnels yet — omit funnel_id to use Traffic Workspace, or create a campaign first.', $data['error']);
    }
}
