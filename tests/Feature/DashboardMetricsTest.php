<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelPageView;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_view_metrics(): void
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
            'status' => 'published',
        ]);

        FunnelPageView::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'optin',
            'session_key' => 'sess-1',
            'viewed_at' => now(),
        ]);

        FunnelPageView::query()->create([
            'funnel_id' => $funnel->id,
            'page_type' => 'webinar',
            'session_key' => 'sess-2',
            'viewed_at' => now()->subDays(10),
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/Index')
                ->where('metrics.publishedCount', 1)
                ->where('metrics.totalViewCount', 2)
                ->where('metrics.recentViewCount', 1));
    }
}
