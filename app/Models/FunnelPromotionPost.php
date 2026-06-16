<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FunnelPromotionPost extends Model
{
    use HasFactory, HasPublicUuid;

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_EMAIL = 'email';

    public const MODE_APPROVE_FIRST = 'approve_first';

    public const MODE_AUTO_PUBLISH = 'auto_publish';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_READY = 'ready';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHING = 'publishing';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'user_id',
        'funnel_id',
        'title',
        'topic',
        'content_type',
        'platforms',
        'publish_mode',
        'status',
        'cta_url',
        'cta_label',
        'text_body',
        'email_subject',
        'email_body',
        'hashtags',
        'primary_asset_id',
        'scheduled_for',
        'published_at',
        'timezone',
        'last_error',
        'generation_context',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'platforms' => 'array',
            'hashtags' => 'array',
            'generation_context' => 'array',
            'metadata' => 'array',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(FunnelPromotionAsset::class, 'promotion_post_id');
    }

    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(FunnelPromotionScheduleEvent::class, 'post_id');
    }

    public function primaryAsset(): BelongsTo
    {
        return $this->belongsTo(FunnelPromotionAsset::class, 'primary_asset_id');
    }
}
