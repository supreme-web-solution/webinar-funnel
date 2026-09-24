<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentEmployeePlanItem extends Model
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_CREATED = 'created';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'plan_id',
        'format_key',
        'platform',
        'topic',
        'angle',
        'scheduled_for',
        'promotion_post_id',
        'status',
        'format_spec',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'format_spec' => 'array',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ContentEmployeePlan::class, 'plan_id');
    }

    public function promotionPost(): BelongsTo
    {
        return $this->belongsTo(FunnelPromotionPost::class, 'promotion_post_id');
    }
}
