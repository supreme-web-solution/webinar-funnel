<?php

namespace App\Services\Campaigns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Last good trending scan. Kept on disk as well as in cache so `cache:clear`, deploys or TTL expiry don't empty the Opportunity Finder.
 */
final class MarketplaceTrendingStore
{
    public const CACHE_KEY = 'marketplace.trending';

    private const FILE = 'marketplace/trending.json';

    /**
     * @return array{results: array<int, array<string, mixed>>, top_pick: array<string, mixed>|null, sources: array<int, string>, refreshed_at: string|null}|null
     */
    public function get(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($this->hasResults($cached)) {
            return $cached;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists(self::FILE)) {
            return null;
        }

        $stored = json_decode((string) $disk->get(self::FILE), true);
        if (! $this->hasResults($stored)) {
            return null;
        }

        Cache::forever(self::CACHE_KEY, $stored);

        return $stored;
    }

    /**
     * @param  array{results: array<int, array<string, mixed>>, top_pick: array<string, mixed>|null, sources: array<int, string>, refreshed_at: string|null}  $snapshot
     */
    public function put(array $snapshot): void
    {
        Storage::disk('local')->put(self::FILE, (string) json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        Cache::forever(self::CACHE_KEY, $snapshot);
    }

    private function hasResults(mixed $snapshot): bool
    {
        return is_array($snapshot) && is_array($snapshot['results'] ?? null) && $snapshot['results'] !== [];
    }
}
