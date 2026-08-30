<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignPage extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'campaign_id',
        'page_type',
        'slug',
        'content',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'content' => 'array',
            'version' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
