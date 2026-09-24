<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiEmployeeSetting extends Model
{
    public const AUTONOMY_COPILOT = 'copilot';

    public const AUTONOMY_ASSISTED = 'assisted';

    public const AUTONOMY_AUTOPILOT = 'autopilot';

    protected $fillable = [
        'user_id',
        'killed',
        'autonomy',
        'execute_allowlist',
        'whatsapp_phone',
        'pairing_code',
        'pairing_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'killed' => 'boolean',
            'execute_allowlist' => 'array',
            'pairing_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isKilled(): bool
    {
        return (bool) $this->killed;
    }

    /**
     * @return list<string>
     */
    public function allowlist(): array
    {
        $list = $this->execute_allowlist;

        return is_array($list) ? array_values(array_filter($list, fn ($item) => is_string($item) && $item !== '')) : [];
    }

    public function allowsExecute(string $toolName): bool
    {
        return in_array($toolName, $this->allowlist(), true);
    }
}
