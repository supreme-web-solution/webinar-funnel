<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelPromotionScheduleEvent extends Model
{
    use HasFactory, HasPublicUuid;

    public const ACTION_SCHEDULED = 'scheduled';

    public const ACTION_RESCHEDULED = 'rescheduled';

    public const ACTION_PUBLISHED = 'published';

    public const ACTION_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'post_id',
        'actor_id',
        'from_time',
        'to_time',
        'action',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'from_time' => 'datetime',
            'to_time' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FunnelPromotionPost::class, 'post_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
