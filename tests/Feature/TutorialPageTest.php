<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_tutorial_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('tutorial'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tutorial/Index')
                ->has('intro')
                ->has('sections'));
    }

    public function test_guest_is_redirected_from_tutorial(): void
    {
        $this->get(route('tutorial'))->assertRedirect();
    }
}
