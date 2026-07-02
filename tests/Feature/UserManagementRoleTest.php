<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        putenv('ADMIN_EMAILS=admin@example.com');
        $_ENV['ADMIN_EMAILS'] = 'admin@example.com';
        $_SERVER['ADMIN_EMAILS'] = 'admin@example.com';
    }

    public function test_admin_can_assign_bundle_role_when_updating_user(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user']);
        $user = User::factory()->create(['email' => 'member@example.com', 'username' => 'member_user']);
        $user->assignRole('FE');

        $this->actingAs($admin)
            ->patch(route('users.update', $user), [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => 'Bundle',
            ])
            ->assertRedirect();

        $user->refresh();

        $this->assertTrue($user->hasRole('Bundle'));
        $this->assertTrue($user->can('view_extra_features'));
    }

    public function test_admin_created_user_gets_default_fe_role(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'username' => 'admin_user2']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'Managed User',
                'username' => 'managed_user',
                'email' => 'managed@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role' => 'FE',
            ])
            ->assertRedirect();

        $user = User::query()->where('email', 'managed@example.com')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('FE'));
    }
}
