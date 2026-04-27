<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

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
    ];

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $baseUsername = Str::slug($input['name'] ?? 'user', separator: '_');
        $username = $baseUsername !== '' ? $baseUsername : 'user';
        $counter = 1;

        while (
            in_array($username, self::RESERVED_USERNAMES, true)
            || User::query()->where('username', $username)->exists()
        ) {
            $counter++;
            $username = "{$baseUsername}_{$counter}";
        }

        return User::create([
            'name' => $input['name'],
            'username' => $username,
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
