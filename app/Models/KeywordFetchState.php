<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class KeywordFetchState extends Model
{
    protected $fillable = [
        'keyword_id',
        'platform',
        'last_fetch_at',
        'next_fetch_at',
        'cooldown_until',
    ];

    protected $casts = [
        'last_fetch_at' => 'datetime',
        'next_fetch_at' => 'datetime',
        'cooldown_until' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public static function recordFetch(int $keywordId, string $platform): void
    {
        $intervalMinutes = (int) max(
            config("limits.fetch.platform_intervals.{$platform}", 15),
            1
        );

        static::updateOrCreate(
            ['keyword_id' => $keywordId, 'platform' => $platform],
            [
                'last_fetch_at' => now(),
                'next_fetch_at' => now()->addMinutes($intervalMinutes),
                'cooldown_until' => null,
            ]
        );
    }

    public static function setCooldown(int $keywordId, string $platform, Carbon $until): void
    {
        static::updateOrCreate(
            ['keyword_id' => $keywordId, 'platform' => $platform],
            ['cooldown_until' => $until]
        );
    }
}
