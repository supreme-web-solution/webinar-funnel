<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEmployeeSession extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'channel',
        'zernio_conversation_id',
        'zernio_account_id',
        'processing_at',
        'progress',
    ];

    protected function casts(): array
    {
        return [
            'processing_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isProcessing(): bool
    {
        return $this->processing_at !== null;
    }
}
