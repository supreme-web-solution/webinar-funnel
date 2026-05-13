<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'access_token',
        'refresh_token',
        'expires_at',
        'platform_username',
        'platform_user_id',
        'daily_post_limit',
        'posts_today',
        'posts_today_reset_on',
        'last_post_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_post_at' => 'datetime',
            'posts_today_reset_on' => 'date',
            'meta' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trafficReplyAttempts(): HasMany
    {
        return $this->hasMany(TrafficReplyAttempt::class);
    }

    public function hasValidAccessToken(): bool
    {
        return $this->access_token !== null && $this->access_token !== '';
    }
}
