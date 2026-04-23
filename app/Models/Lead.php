<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'name',
        'email',
        'email_hash',
        'source',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'metadata' => 'array',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class);
    }
}
