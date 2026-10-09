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
        'reseller',
    ];

    /**
     * Grants the role on top of any roles the buyer already has, so add-on purchases keep earlier access.
     *
     * @return array{user: User, password: string|null, created: bool}
     */
    public function provision(string $email, string $roleName): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }

            return [
                'user' => $user,
                'password' => null,
                'created' => false,
            ];
        }

        return [
            ...$this->createUser($email, $roleName),
            'created' => true,
        ];
    }

    /**
     * @return array{user: User, password: string}
     */
    public function createUser(string $email, string $roleName, ?string $name = null, ?User $reseller = null): array
    {
        $password = Str::random(12);
        $name = $name !== null && trim($name) !== '' ? trim($name) : Str::before($email, '@');

        $user = new User([
            'name' => $name !== '' ? $name : 'User',
            'username' => $this->uniqueUsername($name !== '' ? $name : 'user'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->reseller_id = $reseller?->id;
        $user->save();

        $user->assignRole($roleName);

        return [
            'user' => $user,
            'password' => $password,
        ];
    }

    public function revokeRole(User $user, string $roleName): void
    {
        $user->removeRole($roleName);
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
