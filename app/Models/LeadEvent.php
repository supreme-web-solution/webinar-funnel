<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LeadEvent extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'lead_id',
        'event_type',
        'status',
        'payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'payload' => 'array',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchJobLog::class);
    }
}
