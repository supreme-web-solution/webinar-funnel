<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class IntegrationAccount extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'name',
        'credentials',
        'config',
        'status',
        'last_connected_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'last_connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function funnelIntegrations(): HasMany
    {
        return $this->hasMany(FunnelIntegration::class);
    }
}
