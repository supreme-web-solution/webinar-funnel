<?php

namespace Tests\Feature\Leads;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\Lead;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_leads_index_filters_by_campaign_metadata(): void
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
            'name' => 'Capture Funnel',
            'slug' => 'capture-'.uniqid(),
            'status' => 'published',
        ]);

        $campaign = Campaign::query()->create([
            'user_id' => $user->id,
            'name' => 'My Campaign',
            'slug' => 'my-campaign',
            'type' => 'sales',
            'status' => 'published',
        ]);

        Lead::query()->create([
            'funnel_id' => $funnel->id,
            'name' => 'Campaign Lead',
            'email' => 'campaign@example.com',
            'email_hash' => hash('sha256', 'campaign@example.com'),
            'source' => 'campaign_optin',
            'metadata' => ['campaign_id' => $campaign->id, 'campaign_name' => 'My Campaign'],
        ]);

        Lead::query()->create([
            'funnel_id' => $funnel->id,
            'name' => 'Funnel Lead',
            'email' => 'funnel@example.com',
            'email_hash' => hash('sha256', 'funnel@example.com'),
            'source' => 'optin',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->get(route('leads.index', ['campaign_id' => $campaign->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('leads/Index')
            ->has('leads.data', 1)
            ->where('leads.data.0.email', 'campaign@example.com'));
    }

    public function test_leads_csv_export_respects_filters(): void
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'T',
            'slug' => 't2-'.uniqid(),
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

        Lead::query()->create([
            'funnel_id' => $funnel->id,
            'name' => 'Export Me',
            'email' => 'export@example.com',
            'email_hash' => hash('sha256', 'export@example.com'),
            'source' => 'optin',
        ]);

        $response = $this->actingAs($user)->get(route('leads.index', ['export' => 'csv']));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('export@example.com', $response->streamedContent());
        $this->assertStringContainsString('Export Me', $response->streamedContent());
    }
}
