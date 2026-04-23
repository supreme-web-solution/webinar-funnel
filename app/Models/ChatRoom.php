<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'funnel_id',
        'mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class);
    }
}
