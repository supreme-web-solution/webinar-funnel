<?php

namespace App\Services\AiEmployee;

use App\Models\AiActionLog;
use App\Models\User;

class AiActionLogService
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function record(
        User $user,
        string $toolName,
        string $permission,
        string $status,
        array $arguments = [],
        ?string $result = null,
        ?string $conversationId = null,
        string $channel = 'web',
    ): AiActionLog {
        return AiActionLog::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'permission' => $permission,
            'channel' => $channel,
            'status' => $status,
            'arguments' => $arguments,
            'result' => $result !== null ? mb_substr($result, 0, 5000) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(User $user, int $limit = 12): array
    {
        return AiActionLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AiActionLog $log): array => [
                'id' => $log->id,
                'tool_name' => $log->tool_name,
                'permission' => $log->permission,
                'status' => $log->status,
                'result' => $log->result,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
