<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Keyword;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FunnelTrafficSuggestedKeywordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_edit_exposes_template_suggested_keywords_minus_tracked(): void
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'GuruOS Offer',
            'slug' => 'guruos-offer',
            'category' => 'education',
            'conversion_style' => 'evergreen',
            'is_active' => true,
            'sort_order' => 1,
            'suggested_keywords' => [
                'AI course creation',
                'AI business automation',
                'AI agents for online business',
            ],
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'My Guru Funnel',
            'slug' => 'my-guru-funnel',
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
        ]);

        Keyword::query()->create([
            'user_id' => $user->id,
            'funnel_id' => $funnel->id,
            'name' => 'AI course creation',
            'is_active' => true,
            'platforms' => ['reddit'],
        ]);

        $this->actingAs($user)
            ->get(route('funnels.edit', $funnel))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('funnels/Edit')
                ->where('traffic.suggested_keywords', [
                    'AI business automation',
                    'AI agents for online business',
                ]));
    }
}
