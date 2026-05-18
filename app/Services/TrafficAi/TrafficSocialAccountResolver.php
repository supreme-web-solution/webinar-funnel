<?php

namespace App\Services\TrafficAi;

use App\Models\SocialAccount;
use Illuminate\Support\Collection;

final class TrafficSocialAccountResolver
{
    /** @var list<string> */
    public const PLATFORMS = ['reddit', 'youtube', 'twitter'];

    /**
     * @param  array<string, mixed>|null  $map
     * @return array<string, int|null>
     */
    public function normalizeMapForUser(int $userId, ?array $map): array
    {
        $normalized = [
            'reddit' => null,
            'youtube' => null,
            'twitter' => null,
        ];

        foreach (self::PLATFORMS as $platform) {
            $mappedId = isset($map[$platform]) ? (int) $map[$platform] : 0;
            $account = $mappedId > 0
                ? $this->findPostingAccount($userId, $mappedId)
                : null;

            if ($account === null) {
                $account = $this->defaultAccountForPlatform($userId, $platform);
            }

            $normalized[$platform] = $account?->id;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $map
     */
    public function resolveForPlatform(int $userId, string $platform, ?array $map): ?SocialAccount
    {
        $mappedId = is_array($map) && isset($map[$platform]) ? (int) $map[$platform] : 0;

        if ($mappedId > 0) {
            $account = $this->findPostingAccount($userId, $mappedId);
            if ($account !== null) {
                return $account;
            }
        }

        return $this->defaultAccountForPlatform($userId, $platform);
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function postingAccountsForUser(int $userId): Collection
    {
        return SocialAccount::query()
            ->where('user_id', $userId)
            ->whereNotNull('zernio_account_id')
            ->where('zernio_account_id', '!=', '')
            ->orderBy('platform')
            ->get();
    }

    private function findPostingAccount(int $userId, int $accountId): ?SocialAccount
    {
        $account = SocialAccount::query()
            ->where('user_id', $userId)
            ->whereKey($accountId)
            ->first();

        return $account?->isConnectedForPosting() ? $account : null;
    }

    private function defaultAccountForPlatform(int $userId, string $platform): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('user_id', $userId)
            ->where('platform', $platform)
            ->whereNotNull('zernio_account_id')
            ->where('zernio_account_id', '!=', '')
            ->orderBy('id')
            ->first();
    }
}
