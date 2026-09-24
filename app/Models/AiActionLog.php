<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionLog extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'tool_name',
        'permission',
        'channel',
        'status',
        'arguments',
        'result',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
