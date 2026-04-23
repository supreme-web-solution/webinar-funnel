<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'chat_room_id',
        'conversation_key',
        'author_name',
        'participant_role',
        'attendee_name',
        'attendee_email',
        'message',
        'is_seeded',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'is_seeded' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }
}
