<?php

namespace App\Services\AiEmployee;

use App\Models\AiEmployeeSession;
use App\Models\User;
use Laravel\Ai\Contracts\ConversationStore;

class AiEmployeeSessionService
{
    public function __construct(
        protected ConversationStore $conversations,
    ) {}

    public function for(User $user): AiEmployeeSession
    {
        $session = AiEmployeeSession::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['channel' => 'web']
        );

        if (! is_string($session->conversation_id) || $session->conversation_id === '') {
            $session->conversation_id = $this->conversations->storeConversation($user->id, 'Command Center');
            $session->save();
        }

        $this->recoverStaleProcessing($session);

        return $session->fresh() ?? $session;
    }

    /**
     * Redis/queue resets can drop jobs while processing_at stays set — unblock the UI.
     */
    public function recoverStaleProcessing(AiEmployeeSession $session): void
    {
        if ($session->processing_at === null) {
            return;
        }

        $timeout = max(60, (int) config('ai_employee.processing_timeout', 210));
        if ($session->processing_at->greaterThan(now()->subSeconds($timeout))) {
            return;
        }

        $session->forceFill([
            'processing_at' => null,
            'progress' => null,
        ])->save();
    }

    public function startTurn(AiEmployeeSession $session, string $channel, string $progress = 'Thinking…'): AiEmployeeSession
    {
        $session->forceFill([
            'channel' => $channel,
            'processing_at' => now(),
            'progress' => $progress,
        ])->save();

        $this->bind($session);

        return $session;
    }

    public function progress(string $message): void
    {
        $session = $this->current();
        if ($session === null) {
            return;
        }

        $session->forceFill(['progress' => $message])->save();
    }

    public function finishTurn(AiEmployeeSession $session): void
    {
        $session->forceFill([
            'processing_at' => null,
            'progress' => null,
        ])->save();
    }

    public function resetConversation(User $user): AiEmployeeSession
    {
        $session = $this->for($user);
        $session->conversation_id = $this->conversations->storeConversation($user->id, 'Command Center');
        $session->processing_at = null;
        $session->progress = null;
        $session->save();

        return $session;
    }

    public function bind(AiEmployeeSession $session): void
    {
        app()->instance(AiEmployeeSession::class, $session);
    }

    public function current(): ?AiEmployeeSession
    {
        if (! app()->bound(AiEmployeeSession::class)) {
            return null;
        }

        $session = app(AiEmployeeSession::class);

        return $session instanceof AiEmployeeSession ? $session : null;
    }
}
