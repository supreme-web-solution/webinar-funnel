<?php

namespace Tests\Unit\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNewUserReservedUsernameTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_route_keyword_is_not_used_as_username(): void
    {
        $action = app(CreateNewUser::class);

        $user = $action->create([
            'name' => 'Dashboard',
            'email' => 'dash@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->assertNotEquals('dashboard', $user->username);
        $this->assertStringStartsWith('dashboard_', $user->username);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
