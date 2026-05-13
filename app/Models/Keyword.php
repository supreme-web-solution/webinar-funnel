<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $attributes = [
        'is_active' => true,
        'email_notifications' => false,
        'platforms' => '["reddit","youtube","twitter","news"]',
    ];

    protected $fillable = [
        'user_id',
        'funnel_id',
        'name',
        'is_active',
        'email_notifications',
        'platforms',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_notifications' => 'boolean',
        'platforms' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }

    public function fetchStates(): HasMany
    {
        return $this->hasMany(KeywordFetchState::class);
    }
}
