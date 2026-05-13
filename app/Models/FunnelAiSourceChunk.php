<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunnelAiSourceChunk extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'funnel_ai_source_id',
        'chunk_index',
        'content',
        'embedding',
        'embedding_dimensions',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'chunk_index' => 'integer',
            'embedding' => 'array',
            'embedding_dimensions' => 'integer',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(FunnelAiSource::class, 'funnel_ai_source_id');
    }
}

