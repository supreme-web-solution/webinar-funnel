<?php

namespace Tests\Feature;

use App\Jobs\Traffic\EvaluateTrafficAutoReplyJob;
use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\SocialAccount;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TrafficAutoReplyDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluate_job_is_dispatched_when_traffic_ai_enabled_and_mention_created(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'T',
            'slug' => 't-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'F',
            'slug' => 'f-'.uniqid(),
            'status' => 'draft',
        ]);
        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'traffic_ai_reply_enabled' => true,
            'traffic_ai_max_replies_per_day' => 10,
            'traffic_ai_social_account_ids' => ['reddit' => null],
        ]);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'testkw',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        $account = SocialAccount::query()->create([
            'user_id' => $user->id,
            'platform' => 'reddit',
            'zernio_account_id' => 'zernio_acct_test',
            'daily_post_limit' => 50,
        ]);

        $funnel->settings()->update([
            'traffic_ai_social_account_ids' => ['reddit' => $account->id],
        ]);

        Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'abc123',
            'title' => 'How do I get started with this niche?',
            'content' => 'Looking for recommendations.',
            'source' => 'test',
            'source_type' => 'Reddit',
            'status' => 'new',
        ]);

        Queue::assertPushed(EvaluateTrafficAutoReplyJob::class);
    }

    public function test_evaluate_job_is_not_dispatched_when_traffic_ai_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'T2',
            'slug' => 't2-'.uniqid(),
            'category' => 'business',
            'conversion_style' => 'standard',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'F2',
            'slug' => 'f2-'.uniqid(),
            'status' => 'draft',
        ]);
        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'traffic_ai_reply_enabled' => false,
        ]);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'kw2',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'x1',
            'title' => 'T',
            'content' => 'Body',
            'source_type' => 'Reddit',
        ]);

        Queue::assertNotPushed(EvaluateTrafficAutoReplyJob::class);
    }
}
