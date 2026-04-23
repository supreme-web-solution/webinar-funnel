<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class FunnelPage extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'page_type',
        'schema',
        'version',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'schema' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }
}
