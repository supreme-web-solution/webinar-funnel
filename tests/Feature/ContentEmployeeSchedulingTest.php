<?php

namespace Tests\Feature;

use App\Models\ContentEmployeePlan;
use App\Models\ContentEmployeePlanItem;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Models\FunnelSetting;
use App\Models\SocialAccount;
use App\Models\Template;
use App\Models\User;
use App\Services\Content\ContentEmployeePlanService;
use App\Services\Content\PlatformFormatCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentEmployeeSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContentEmployeePlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['promotion.content_employee.generation_lead_minutes' => 60]);

        $this->user = User::factory()->create();
        SocialAccount::query()->create([
            'user_id' => $this->user->id,
            'platform' => 'twitter',
            'platform_username' => '@test',
            'zernio_account_id' => 'acct_twitter',
            'daily_post_limit' => 50,
            'posts_today' => 0,
        ]);

        $template = Template::query()->create([
            'name' => 'Promotion Template',
            'slug' => 'promotion-template-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $this->user->id,
            'template_id' => $template->id,
            'name' => 'Promo Funnel',
            'slug' => 'promo-funnel-'.uniqid(),
            'status' => 'draft',
        ]);
        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'webinar_cta_label' => 'Join now',
            'webinar_cta_url' => 'https://example.com/offer',
        ]);

        $this->plan = ContentEmployeePlan::query()->create([
            'user_id' => $this->user->id,
            'funnel_id' => $funnel->id,
            'week_start' => now()->startOfWeek(),
            'status' => ContentEmployeePlan::STATUS_APPROVED,
            'source' => 'ai_planner',
            'meta' => [],
        ]);
    }

    public function test_create_posts_only_schedules_and_skips_past_items(): void
    {
        $past = $this->item(now()->subHour());
        $soon = $this->item(now()->addMinutes(30));
        $later = $this->item(now()->addDays(2));

        $this->actingAs($this->user)
            ->post(route('growth.content-employee.plans.execute', $this->plan))
            ->assertRedirect();

        $this->assertSame(ContentEmployeePlan::STATUS_SCHEDULED, $this->plan->fresh()->status);
        $this->assertSame(ContentEmployeePlanItem::STATUS_MISSED, $past->fresh()->status);
        $this->assertNull($past->fresh()->promotion_post_id);

        $this->assertNotNull($soon->fresh()->promotion_post_id, 'Item inside the 1 hour lead window is created right away.');

        $this->assertSame(ContentEmployeePlanItem::STATUS_QUEUED, $later->fresh()->status);
        $this->assertNull($later->fresh()->promotion_post_id);

        $this->assertSame(1, FunnelPromotionPost::query()->count());
    }

    public function test_created_post_is_auto_publish_at_the_planned_time(): void
    {
        $item = $this->item(now()->addMinutes(30));

        app(ContentEmployeePlanService::class)->execute($this->plan->fresh(), $this->user);

        $post = FunnelPromotionPost::query()->findOrFail($item->fresh()->promotion_post_id);

        $this->assertSame(FunnelPromotionPost::MODE_AUTO_PUBLISH, $post->publish_mode);
        $this->assertTrue($post->scheduled_for->equalTo($item->scheduled_for));
        $this->assertSame(FunnelPromotionPost::STATUS_SCHEDULED, $post->generatedStatus());
    }

    public function test_processor_creates_items_when_they_enter_the_lead_window_and_completes_plan(): void
    {
        $item = $this->item(now()->addHours(3));
        $service = app(ContentEmployeePlanService::class);

        $service->execute($this->plan->fresh(), $this->user);
        $this->assertNull($item->fresh()->promotion_post_id);

        $this->travel(2)->hours();
        $this->assertSame(1, $service->processDueItems());

        $this->assertNotNull($item->fresh()->promotion_post_id);
        $this->assertSame(ContentEmployeePlan::STATUS_COMPLETED, $this->plan->fresh()->status);
        $this->assertSame(1, data_get($this->plan->fresh()->meta, 'execute_summary.created'));
    }

    public function test_processor_marks_long_overdue_items_missed(): void
    {
        $item = $this->item(now()->addHours(2));
        $service = app(ContentEmployeePlanService::class);
        $service->execute($this->plan->fresh(), $this->user);

        $this->travel(3)->hours();
        $this->assertSame(0, $service->processDueItems());

        $this->assertSame(ContentEmployeePlanItem::STATUS_MISSED, $item->fresh()->status);
        $this->assertSame(0, FunnelPromotionPost::query()->count());
    }

    private function item(mixed $scheduledFor): ContentEmployeePlanItem
    {
        $formatKey = app(PlatformFormatCatalog::class)->formatsForPlatform('twitter')[0];

        return ContentEmployeePlanItem::query()->create([
            'plan_id' => $this->plan->id,
            'format_key' => $formatKey,
            'platform' => 'twitter',
            'topic' => 'A topic about growth',
            'angle' => 'Angle',
            'scheduled_for' => $scheduledFor,
            'status' => ContentEmployeePlanItem::STATUS_PLANNED,
            'format_spec' => app(PlatformFormatCatalog::class)->format($formatKey),
            'metadata' => [],
        ]);
    }
}
