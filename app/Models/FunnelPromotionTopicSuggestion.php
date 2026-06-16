<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelPromotionTopicSuggestion extends Model
{
    use HasFactory, HasPublicUuid;

    public const STATUS_SUGGESTED = 'suggested';

    public const STATUS_USED = 'used';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'uuid',
        'funnel_id',
        'topic',
        'angle',
        'status',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'score' => 'integer',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }
}
