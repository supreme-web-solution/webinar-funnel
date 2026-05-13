<?php

namespace App\Services\TrafficAi;

use App\Models\SocialAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Per-account spacing using a row lock so many workers can coordinate without busy release() loops.
 */
final class SocialAccountPostingLimiter
{
    public function secondsUntilCanPost(SocialAccount $account): int
    {
        $minGap = (int) config('traffic_ai.min_seconds_between_posts', 120);
        $jMin = (int) config('traffic_ai.post_jitter_seconds.min', 30);
        $jMax = (int) config('traffic_ai.post_jitter_seconds.max', 90);
        $jitter = random_int($jMin, max($jMin, $jMax));

        return DB::transaction(function () use ($account, $minGap, $jitter): int {
            /** @var SocialAccount $locked */
            $locked = SocialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $today = CarbonImmutable::today();
            if ($locked->posts_today_reset_on === null || ! $locked->posts_today_reset_on->isSameDay($today)) {
                $locked->forceFill([
                    'posts_today' => 0,
                    'posts_today_reset_on' => $today,
                ])->save();
                $locked->refresh();
            }

            if ((int) $locked->posts_today >= (int) $locked->daily_post_limit) {
                $nextMidnight = $today->addDay()->startOfDay();
                $wait = max(1, now()->diffInSeconds($nextMidnight));

                return min($wait, 86400);
            }

            if ($locked->last_post_at === null) {
                return 0;
            }

            $elapsed = $locked->last_post_at->diffInSeconds(now());
            $required = $minGap + $jitter;

            return $elapsed >= $required ? 0 : (int) max(1, $required - $elapsed);
        });
    }

    public function recordSuccessfulPost(SocialAccount $account): void
    {
        DB::transaction(function () use ($account): void {
            /** @var SocialAccount $locked */
            $locked = SocialAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $today = CarbonImmutable::today();
            if ($locked->posts_today_reset_on === null || ! $locked->posts_today_reset_on->isSameDay($today)) {
                $locked->posts_today = 0;
                $locked->posts_today_reset_on = $today;
            }

            $locked->increment('posts_today');
            $locked->forceFill(['last_post_at' => now()])->save();
        });
    }
}
