<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignEmail extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'sequence_key',
        'subject',
        'body',
        'sort_order',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'sort_order' => 'integer',
            'meta' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
