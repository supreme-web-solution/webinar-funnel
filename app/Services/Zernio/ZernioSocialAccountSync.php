<?php

namespace App\Services\Zernio;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class ZernioSocialAccountSync
{
    /** @var array<string, string> Zernio platform => local platform */
    private const PLATFORM_MAP = [
        'twitter' => 'twitter',
        'x' => 'twitter',
        'reddit' => 'reddit',
        'youtube' => 'youtube',
    ];

    public function __construct(
        private readonly ZernioClient $zernio,
        private readonly ZernioProfileManager $profiles,
    ) {}

    /**
     * @return list<string> Local platform keys synced (e.g. twitter, reddit)
     */
    public function syncForUser(User $user): array
    {
        if (! $this->zernio->isConfigured()) {
            return [];
        }

        try {
            $profileId = $this->profiles->ensureForUser($user);
        } catch (\Throwable $e) {
            Log::warning('ZernioSocialAccountSync: no profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $accounts = $this->zernio->listAccountsForProfile($profileId);
        $synced = [];

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                continue;
            }

            $localPlatform = $this->mapPlatform((string) ($account['platform'] ?? ''));
            if ($localPlatform === null) {
                continue;
            }

            $accountId = (string) ($account['_id'] ?? $account['id'] ?? '');
            if ($accountId === '') {
                continue;
            }

            $username = $account['username'] ?? $account['displayName'] ?? $account['name'] ?? null;
            $displayName = is_string($username) && $username !== ''
                ? ($localPlatform === 'twitter' && ! Str::startsWith($username, '@') ? '@'.$username : $username)
                : null;

            SocialAccount::query()->updateOrCreate(
                ['user_id' => $user->id, 'platform' => $localPlatform],
                [
                    'zernio_account_id' => $accountId,
                    'platform_username' => $displayName,
                    'access_token' => null,
                    'refresh_token' => null,
                    'expires_at' => null,
                    'daily_post_limit' => (int) config('traffic_ai.max_replies_per_day_per_account', 20),
                ]
            );

            $synced[] = $localPlatform;
        }

        $dailyCap = (int) config('traffic_ai.max_replies_per_day_per_account', 20);
        SocialAccount::query()
            ->where('user_id', $user->id)
            ->update(['daily_post_limit' => $dailyCap]);

        if ($synced !== []) {
            Log::info('ZernioSocialAccountSync: synced accounts', [
                'user_id' => $user->id,
                'platforms' => $synced,
            ]);
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistFromOAuthPayload(User $user, string $zernioPlatform, array $payload): ?string
    {
        $localPlatform = $this->mapPlatform($zernioPlatform);
        if ($localPlatform === null) {
            return null;
        }

        $account = is_array($payload['account'] ?? null) ? $payload['account'] : $payload;
        $accountId = (string) ($account['accountId'] ?? $account['_id'] ?? $account['id'] ?? $payload['accountId'] ?? '');
        if ($accountId === '') {
            return null;
        }

        $username = $account['username'] ?? $account['displayName'] ?? $payload['username'] ?? null;
        $displayName = is_string($username) && $username !== ''
            ? ($localPlatform === 'twitter' && ! Str::startsWith($username, '@') ? '@'.$username : $username)
            : null;

        SocialAccount::query()->updateOrCreate(
            ['user_id' => $user->id, 'platform' => $localPlatform],
            [
                'zernio_account_id' => $accountId,
                'platform_username' => $displayName,
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
                'daily_post_limit' => (int) config('traffic_ai.max_replies_per_day_per_account', 20),
            ]
        );

        return $localPlatform;
    }

    public function mapPlatform(string $zernioPlatform): ?string
    {
        $key = strtolower(trim($zernioPlatform));

        return self::PLATFORM_MAP[$key] ?? null;
    }
}
