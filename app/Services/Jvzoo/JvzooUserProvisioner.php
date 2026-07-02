<?php

namespace App\Services\Jvzoo;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class JvzooUserProvisioner
{
    private const RESERVED_USERNAMES = [
        'dashboard',
        'templates',
        'funnels',
        'integrations',
        'settings',
        'login',
        'register',
        'password',
        'verification',
        'confirm-password',
        'logout',
        'sanctum',
        'api',
        'storage',
        'up',
        'leads',
        'ipn',
    ];

    /**
     * @return array{user: User, password: string|null, created: bool}
     */
    public function provision(string $email, string $roleName): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $user->syncRoles([$roleName]);

            return [
                'user' => $user,
                'password' => null,
                'created' => false,
            ];
        }

        $password = Str::random(12);
        $name = Str::before($email, '@');

        $user = User::create([
            'name' => $name !== '' ? $name : 'User',
            'username' => $this->uniqueUsername($name !== '' ? $name : 'user'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $user->assignRole($roleName);

        return [
            'user' => $user,
            'password' => $password,
            'created' => true,
        ];
    }

    public function revokeAccess(User $user): void
    {
        $user->syncRoles([]);
    }

    private function uniqueUsername(string $name): string
    {
        $baseUsername = Str::slug($name, separator: '_');
        $username = $baseUsername !== '' ? $baseUsername : 'user';
        $counter = 1;

        while (
            in_array($username, self::RESERVED_USERNAMES, true)
            || User::query()->where('username', $username)->exists()
        ) {
            $counter++;
            $username = "{$baseUsername}_{$counter}";
        }

        return $username;
    }
}
