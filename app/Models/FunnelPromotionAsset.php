<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelPromotionAsset extends Model
{
    use HasFactory, HasPublicUuid;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_SCRIPT = 'script';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'promotion_post_id',
        'asset_type',
        'provider',
        'status',
        'source_prompt',
        'remote_id',
        'url',
        'thumbnail_url',
        'duration_seconds',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'duration_seconds' => 'integer',
            'meta' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(FunnelPromotionPost::class, 'promotion_post_id');
    }
}
