<?php

namespace App\Services\AiEmployee;

use App\Ai\Tools\GatedTool;
use App\Models\AiActionApproval;
use App\Models\User;

class ActionApprovalService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function stage(
        User $user,
        GatedTool $tool,
        array $payload,
        string $summary,
        ?string $conversationId = null,
    ): AiActionApproval {
        return $this->stageNamed(
            $user,
            $tool->toolName(),
            $tool->permission(),
            $payload,
            $summary,
            $conversationId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function stageNamed(
        User $user,
        string $toolName,
        string $permission,
        array $payload,
        string $summary,
        ?string $conversationId = null,
    ): AiActionApproval {
        return AiActionApproval::query()->create([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'permission' => $permission,
            'summary' => $summary,
            'payload' => $payload,
            'status' => AiActionApproval::STATUS_PENDING,
        ]);
    }

    public function pendingFor(User $user, int $id): ?AiActionApproval
    {
        return AiActionApproval::query()
            ->where('user_id', $user->id)
            ->where('id', $id)
            ->where('status', AiActionApproval::STATUS_PENDING)
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingPayload(User $user): array
    {
        return AiActionApproval::query()
            ->where('user_id', $user->id)
            ->where('status', AiActionApproval::STATUS_PENDING)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AiActionApproval $approval): array => $this->toArray($approval))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(AiActionApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'launch_label' => $approval->launchLabel(),
            'tool_name' => $approval->tool_name,
            'permission' => $approval->permission,
            'summary' => $approval->summary,
            'payload' => $approval->payload,
            'status' => $approval->status,
            'created_at' => $approval->created_at?->toIso8601String(),
        ];
    }
}
