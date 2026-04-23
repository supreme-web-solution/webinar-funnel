<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FunnelSetting extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'webinar_title',
        'webinar_description',
        'video_url',
        'chat_mode',
        'countdown_seconds',
        'allow_replay',
        'double_opt_in',
        'chat_seed_messages',
        'branding',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'allow_replay' => 'boolean',
            'double_opt_in' => 'boolean',
            'chat_seed_messages' => 'array',
            'branding' => 'array',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }
}
