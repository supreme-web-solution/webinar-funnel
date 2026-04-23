<?php

namespace App\Services\Funnels;

use App\Models\Funnel;
use Illuminate\Support\Facades\Cache;

class PublicFunnelResolver
{
    private const CACHE_VERSION = 'v2';

    /**
     * Resolve published funnel by username + slug.
     */
    public function resolve(string $username, string $slug): ?Funnel
    {
        $cacheKey = "funnel:public:".self::CACHE_VERSION.":{$username}:{$slug}";

        $funnelId = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($username, $slug) {
            return Funnel::query()
                ->where('slug', $slug)
                ->where('status', 'published')
                ->whereHas('user', function ($query) use ($username) {
                    $query->where('username', $username);
                })
                ->value('id');
        });

        if (! $funnelId) {
            return null;
        }

        return Funnel::query()
            ->whereKey($funnelId)
            ->with([
                'user:id,username,name',
                'template:id,name,slug',
                'settings',
                'pages',
                'chatRoom.messages' => fn ($query) => $query->orderBy('published_at')->limit(100),
            ])
            ->first();
    }

    public function forget(string $username, string $slug): void
    {
        // Clear old and current key formats.
        Cache::forget("funnel:public:{$username}:{$slug}");
        Cache::forget("funnel:public:".self::CACHE_VERSION.":{$username}:{$slug}");
    }
}
