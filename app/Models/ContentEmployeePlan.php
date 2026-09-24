<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentEmployeePlan extends Model
{
    use HasPublicUuid;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'user_id',
        'campaign_id',
        'funnel_id',
        'week_start',
        'status',
        'source',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'week_start' => 'date',
            'meta' => 'array',
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

    public function items(): HasMany
    {
        return $this->hasMany(ContentEmployeePlanItem::class, 'plan_id');
    }
}
