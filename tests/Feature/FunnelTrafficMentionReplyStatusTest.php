<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Template;
use App\Models\TrafficReplyAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficMentionReplyStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_edit_includes_traffic_reply_attempt_on_mentions(): void
    {
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
        ]);

        $keyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'brand',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        $mention = Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'post_1',
            'title' => 'Need help with marketing',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'reddit',
            'status' => 'new',
            'permalink' => 'https://reddit.com/r/test/comments/abc',
            'posted_at' => now(),
        ]);

        TrafficReplyAttempt::query()->create([
            'mention_id' => $mention->id,
            'funnel_id' => $funnel->id,
            'user_id' => $user->id,
            'status' => TrafficReplyAttempt::STATUS_POSTED,
            'posted_at' => now()->subHour(),
            'external_comment_id' => 'reply_1',
        ]);

        $this->actingAs($user)
            ->get(route('funnels.edit', ['funnel' => $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('traffic.mentions.data', 1)
                ->where('traffic.mentions.data.0.id', $mention->id)
                ->where('traffic.mentions.data.0.permalink', 'https://reddit.com/r/test/comments/abc')
                ->where('traffic.mentions.data.0.traffic_reply_attempt.status', 'posted'));
    }
}
