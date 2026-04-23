<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class DispatchJobLog extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'lead_event_id',
        'provider',
        'status',
        'attempt',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function leadEvent(): BelongsTo
    {
        return $this->belongsTo(LeadEvent::class);
    }
}
