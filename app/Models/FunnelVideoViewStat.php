<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelVideoViewStat extends Model
{
    protected $fillable = [
        'funnel_id',
        'session_key',
        'watched_seconds',
        'reached_60s',
        'reached_50_percent',
        'reached_100_percent',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'integer',
            'reached_60s' => 'boolean',
            'reached_50_percent' => 'boolean',
            'reached_100_percent' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }
}
