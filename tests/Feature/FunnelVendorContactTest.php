<?php

namespace Tests\Feature;

use App\Models\Funnel;
use App\Models\FunnelSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FunnelVendorContactTest extends TestCase
{
    use RefreshDatabase;

    public function test_funnel_edit_shows_vendor_contact_from_template_when_settings_missing(): void
    {
        $user = User::factory()->create();
        $template = Template::query()->create([
            'name' => 'GuruOS Offer',
            'slug' => 'guruos-offer',
            'category' => 'education',
            'conversion_style' => 'evergreen',
            'is_active' => true,
            'sort_order' => 1,
            'vendor_contact' => [
                'heading' => 'Contact the vendor for any assistance',
                'body' => 'Ben Murray: murrayb1893@gmail.com',
                'lines' => ['Ben Murray: murrayb1893@gmail.com'],
                'emails' => ['murrayb1893@gmail.com'],
                'urls' => [],
            ],
        ]);

        $funnel = Funnel::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'name' => 'Guru Funnel',
            'slug' => 'guru-funnel',
            'status' => 'draft',
        ]);

        FunnelSetting::query()->create([
            'funnel_id' => $funnel->id,
            'chat_mode' => 'simulated',
            'allow_replay' => true,
            'vendor_contact' => null,
        ]);

        $this->actingAs($user)
            ->get(route('funnels.edit', $funnel))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('funnels/Edit')
                ->where('funnel.settings.vendor_contact.emails', ['murrayb1893@gmail.com']));

        $this->assertSame(
            ['murrayb1893@gmail.com'],
            $funnel->fresh()->settings?->vendor_contact['emails'] ?? null,
        );
    }
}
