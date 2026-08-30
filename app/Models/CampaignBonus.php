<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignBonus extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'user_id',
        'title',
        'bonus_type',
        'content',
        'meta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'meta' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
