<?php

namespace Tests\Feature\Funnels;

use App\Jobs\DispatchDuePromotionPostsJob;
use App\Jobs\PublishPromotionPostJob;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PromotionSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_does_not_publish_now(): void
    {
        Queue::fake();
        [$user, $funnel, $post] = $this->makePost(FunnelPromotionPost::STATUS_READY);

        $this->actingAs($user)
            ->patch(route('funnels.promotion.posts.schedule', [$funnel, $post]), [
                'scheduled_for' => now()->addDay()->toIso8601String(),
                'timezone' => 'UTC',
            ])
            ->assertRedirect();

        $this->assertSame(FunnelPromotionPost::STATUS_SCHEDULED, $post->fresh()->status);
        Queue::assertNotPushed(PublishPromotionPostJob::class);

        (new DispatchDuePromotionPostsJob)->handle();
        Queue::assertNotPushed(PublishPromotionPostJob::class);
    }

    public function test_due_scheduled_post_is_dispatched_for_publishing(): void
    {
        Queue::fake();
        [, , $post] = $this->makePost(FunnelPromotionPost::STATUS_SCHEDULED, now()->subMinute());

        (new DispatchDuePromotionPostsJob)->handle();

        Queue::assertPushed(PublishPromotionPostJob::class, fn (PublishPromotionPostJob $job): bool => $job->postId === $post->id);
    }

    public function test_scheduling_a_generating_post_keeps_it_generating(): void
    {
        [$user, $funnel, $post] = $this->makePost(FunnelPromotionPost::STATUS_GENERATING);

        $this->actingAs($user)
            ->patch(route('funnels.promotion.posts.schedule', [$funnel, $post]), [
                'scheduled_for' => now()->addDay()->toIso8601String(),
            ])
            ->assertRedirect();

        $post->refresh();
        $this->assertSame(FunnelPromotionPost::STATUS_GENERATING, $post->status);
        $this->assertNotNull($post->scheduled_for);
        $this->assertSame(FunnelPromotionPost::STATUS_SCHEDULED, $post->generatedStatus());
    }

    public function test_cannot_schedule_in_the_past_or_a_published_post(): void
    {
        [$user, $funnel, $post] = $this->makePost(FunnelPromotionPost::STATUS_READY);

        $this->actingAs($user)
            ->patch(route('funnels.promotion.posts.schedule', [$funnel, $post]), [
                'scheduled_for' => now()->subHour()->toIso8601String(),
            ])
            ->assertSessionHasErrors('scheduled_for');

        $post->update(['status' => FunnelPromotionPost::STATUS_PUBLISHED]);

        $this->actingAs($user)
            ->patch(route('funnels.promotion.posts.schedule', [$funnel, $post]), [
                'scheduled_for' => now()->addDay()->toIso8601String(),
            ])
            ->assertSessionHasErrors('schedule');
    }

    public function test_unschedule_returns_post_to_ready(): void
    {
        [$user, $funnel, $post] = $this->makePost(FunnelPromotionPost::STATUS_SCHEDULED, now()->addDay());

        $this->actingAs($user)
            ->delete(route('funnels.promotion.posts.unschedule', [$funnel, $post]))
            ->assertRedirect();

        $post->refresh();
        $this->assertNull($post->scheduled_for);
        $this->assertSame(FunnelPromotionPost::STATUS_READY, $post->status);
    }

    public function test_generated_status_rules(): void
    {
        $post = new FunnelPromotionPost(['publish_mode' => FunnelPromotionPost::MODE_APPROVE_FIRST]);
        $this->assertSame(FunnelPromotionPost::STATUS_READY, $post->generatedStatus());

        $post->scheduled_for = now()->addHour();
        $this->assertSame(FunnelPromotionPost::STATUS_SCHEDULED, $post->generatedStatus());

        $post->scheduled_for = now()->subHour();
        $this->assertSame(FunnelPromotionPost::STATUS_READY, $post->generatedStatus());

        $post->publish_mode = FunnelPromotionPost::MODE_AUTO_PUBLISH;
        $this->assertSame(FunnelPromotionPost::STATUS_SCHEDULED, $post->generatedStatus());
    }

    /**
     * @return array{User, Funnel, FunnelPromotionPost}
     */
    private function makePost(string $status, mixed $scheduledFor = null): array
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'Promotion Template',
            'slug' => 'promotion-template-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Promo Funnel',
            'slug' => 'promo-funnel-'.uniqid(),
            'status' => 'draft',
        ]);

        $post = FunnelPromotionPost::factory()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'content_type' => FunnelPromotionPost::TYPE_TEXT,
            'platforms' => ['twitter'],
            'status' => $status,
            'scheduled_for' => $scheduledFor,
        ]);

        return [$user, $funnel, $post];
    }
}
