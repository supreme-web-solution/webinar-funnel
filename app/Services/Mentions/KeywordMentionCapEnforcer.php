<?php

namespace App\Services\Mentions;

use App\Models\Keyword;
use Illuminate\Support\Facades\Log;

final class KeywordMentionCapEnforcer
{
    public static function maxKeywordsPerFunnel(): int
    {
        return max((int) config('limits.mentions.max_keywords_per_funnel', 5), 1);
    }

    public static function maxMentionsPerKeyword(): int
    {
        $cap = (int) config('limits.mentions.max_mentions_per_keyword', 500);

        return $cap > 0 ? $cap : PHP_INT_MAX;
    }

    public function hasReachedCap(Keyword $keyword): bool
    {
        return $keyword->mentions()->count() >= self::maxMentionsPerKeyword();
    }

    /**
     * Pause the keyword when at or over the mention cap. Returns true if cap is reached.
     */
    public function enforceCap(Keyword $keyword): bool
    {
        if (! $this->hasReachedCap($keyword)) {
            return false;
        }

        if ($keyword->is_active) {
            $keyword->update(['is_active' => false]);

            Log::info('Keyword auto-paused: mention cap reached', [
                'keyword_id' => $keyword->id,
                'funnel_id' => $keyword->funnel_id,
                'mentions_count' => $keyword->mentions()->count(),
                'cap' => self::maxMentionsPerKeyword(),
            ]);
        }

        return true;
    }
}
