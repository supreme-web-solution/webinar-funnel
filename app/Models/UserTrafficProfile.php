<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTrafficProfile extends Model
{
    protected $fillable = [
        'user_id',
        'standalone_funnel_id',
        'intensity',
        'enabled_platforms',
        'enabled_formats',
        'frequency_overrides',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled_platforms' => 'array',
            'enabled_formats' => 'array',
            'frequency_overrides' => 'array',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function standaloneFunnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class, 'standalone_funnel_id');
    }
}
