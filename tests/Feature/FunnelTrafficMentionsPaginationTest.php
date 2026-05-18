<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Mention;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelTrafficMentionsPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_edit_paginates_traffic_mentions(): void
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

        for ($i = 0; $i < 12; $i++) {
            Mention::query()->create([
                'keyword_id' => $keyword->id,
                'user_id' => $user->id,
                'post_id' => 'post_'.$i,
                'title' => "Mention {$i}",
                'content' => 'Body',
                'source' => 'test',
                'source_type' => 'reddit',
                'status' => 'new',
                'posted_at' => now()->subMinutes($i),
            ]);
        }

        $this->actingAs($user)
            ->get(route('funnels.edit', ['funnel' => $funnel, 'page' => 2]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('traffic.mentions.data', 2)
                ->where('traffic.mentions.current_page', 2)
                ->where('traffic.mentions.last_page', 2)
                ->where('traffic.mentions.total', 12));
    }

    public function test_funnel_edit_filters_mentions_by_keyword_when_cap_reached(): void
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

        $cappedKeyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'capped',
            'is_active' => false,
            'email_notifications' => false,
            'platforms' => ['youtube'],
        ]);

        $otherKeyword = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'other',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['youtube'],
        ]);

        Mention::query()->create([
            'keyword_id' => $cappedKeyword->id,
            'user_id' => $user->id,
            'post_id' => 'yt_capped',
            'title' => 'Capped mention',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'youtube',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        Mention::query()->create([
            'keyword_id' => $otherKeyword->id,
            'user_id' => $user->id,
            'post_id' => 'yt_other',
            'title' => 'Other mention',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'youtube',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        config(['limits.mentions.max_results_per_keyword' => 1]);

        $this->actingAs($user)
            ->get(route('funnels.edit', [
                'funnel' => $funnel,
                'traffic_platform' => 'youtube',
                'traffic_keyword_id' => $cappedKeyword->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('traffic.mentions.data', 1)
                ->where('traffic.mentions.data.0.title', 'Capped mention')
                ->where('traffic.filters.keyword_id', (string) $cappedKeyword->id));
    }

    public function test_platform_tab_counts_are_scoped_to_selected_keyword(): void
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

        $keywordA = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'alpha',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['twitter', 'reddit'],
        ]);

        $keywordB = Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'beta',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['reddit'],
        ]);

        Mention::query()->create([
            'keyword_id' => $keywordA->id,
            'user_id' => $user->id,
            'post_id' => 'tw_1',
            'title' => 'Tweet',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'Twitter',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        Mention::query()->create([
            'keyword_id' => $keywordA->id,
            'user_id' => $user->id,
            'post_id' => 'rd_1',
            'title' => 'Reddit',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'Reddit',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        Mention::query()->create([
            'keyword_id' => $keywordB->id,
            'user_id' => $user->id,
            'post_id' => 'rd_2',
            'title' => 'Other reddit',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'Reddit',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('funnels.edit', [
                'funnel' => $funnel,
                'traffic_keyword_id' => $keywordA->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('traffic.stats.total', 2)
                ->where('traffic.stats.platforms.twitter', 1)
                ->where('traffic.stats.platforms.reddit', 1)
                ->missing('traffic.stats.platforms.Reddit'));
    }

    public function test_keywords_include_mention_counts_by_platform(): void
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
            'name' => 'only-yt',
            'is_active' => true,
            'email_notifications' => false,
            'platforms' => ['youtube'],
        ]);

        Mention::query()->create([
            'keyword_id' => $keyword->id,
            'user_id' => $user->id,
            'post_id' => 'yt_1',
            'title' => 'Video',
            'content' => 'Body',
            'source' => 'test',
            'source_type' => 'YouTube',
            'status' => 'new',
            'posted_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('funnels.edit', ['funnel' => $funnel]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('traffic.keywords.0.mention_counts_by_platform.youtube', 1));
    }
}
