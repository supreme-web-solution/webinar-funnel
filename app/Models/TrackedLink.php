<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedLink extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'campaign_id',
        'funnel_id',
        'code',
        'label',
        'destination_url',
        'geo_rules',
        'device_rules',
        'click_count',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'geo_rules' => 'array',
            'device_rules' => 'array',
            'click_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(TrackedLinkClick::class);
    }

    public function publicUrl(): string
    {
        return url('/r/'.$this->code);
    }
}
