<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelPageView;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FunnelPageViewRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_optin_records_a_page_view(): void
    {
        $user = User::factory()->create(['username' => 'acme']);
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
            'slug' => 'demo',
            'status' => 'published',
        ]);
        $funnel->pages()->create([
            'page_type' => 'optin',
            'schema' => ['html' => '', 'css' => ''],
        ]);

        $this->get(route('public.optin', ['username' => 'acme', 'slug' => 'demo']))
            ->assertOk();

        $this->assertDatabaseCount('funnel_page_views', 1);
        $this->assertDatabaseHas('funnel_page_views', [
            'funnel_id' => $funnel->id,
            'page_type' => 'optin',
        ]);
    }
}
