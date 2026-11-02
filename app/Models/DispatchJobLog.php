<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Queued rows that already have a later success or failure for the same lead.
     *
     * @param  Builder<DispatchJobLog>  $query
     * @return Builder<DispatchJobLog>
     */
    public function scopeWithoutSettledQueueRows(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('status', '!=', 'queued')
                ->orWhere(fn (Builder $query) => $query->where('status', 'queued')->whereNotExists(function ($settled) {
                    $settled->selectRaw('1')
                        ->from('dispatch_job_logs as settled')
                        ->whereColumn('settled.lead_event_id', 'dispatch_job_logs.lead_event_id')
                        ->whereColumn('settled.provider', 'dispatch_job_logs.provider')
                        ->whereColumn('settled.id', '>', 'dispatch_job_logs.id')
                        ->whereIn('settled.status', ['success', 'failed']);
                }));
        });
    }
}
