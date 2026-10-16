<?php

namespace Tests\Feature;

use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['admin@example.com']]);
    }

    public function test_admin_can_impersonate_another_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user']);
        $member = User::factory()->create(['email' => 'member@example.com', 'username' => 'member_user']);

        $this->actingAs($admin)
            ->post(route('users.impersonate', $member))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($member);
        $this->assertSame($admin->id, session(ImpersonationController::SESSION_KEY));
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $member = User::factory()->create(['email' => 'member@example.com', 'username' => 'member_user']);
        $other = User::factory()->create(['email' => 'other@example.com', 'username' => 'other_user']);

        $this->actingAs($member)
            ->post(route('users.impersonate', $other))
            ->assertForbidden();

        $this->assertAuthenticatedAs($member);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_admin_cannot_impersonate_themselves(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user']);

        $this->actingAs($admin)
            ->from(route('users.index'))
            ->post(route('users.impersonate', $admin))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_admin_can_leave_impersonation(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user']);
        $member = User::factory()->create(['email' => 'member@example.com', 'username' => 'member_user']);

        $this->actingAs($admin)
            ->post(route('users.impersonate', $member))
            ->assertRedirect(route('dashboard'));

        $this->post(route('impersonation.leave'))
            ->assertRedirect(route('users.index'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_leave_without_impersonation_is_forbidden(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user']);

        $this->actingAs($admin)
            ->post(route('impersonation.leave'))
            ->assertForbidden();
    }
}
