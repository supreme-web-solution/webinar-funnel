<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationCoachingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_coaching_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('integrations.coaching'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('integrations/Coaching')
                ->has('videoUrl')
                ->has('checkoutUrl'));
    }

    public function test_guest_is_redirected_from_coaching_page(): void
    {
        $this->get(route('integrations.coaching'))->assertRedirect();
    }
}
