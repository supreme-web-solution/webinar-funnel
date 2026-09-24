<?php

namespace App\Jobs;

use App\Models\AiEmployeeSession;
use App\Models\User;
use App\Services\AiEmployee\AgentOrchestrator;
use App\Services\AiEmployee\AiEmployeeSessionService;
use App\Services\AiEmployee\WhatsAppChannelService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAiChatTurnJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public int $userId,
        public string $conversationId,
        public string $text,
        public string $channel = 'web',
    ) {}

    public function uniqueId(): string
    {
        return 'ai-employee-'.$this->userId;
    }

    public function handle(
        AgentOrchestrator $orchestrator,
        AiEmployeeSessionService $sessions,
        WhatsAppChannelService $whatsApp,
    ): void {
        $user = User::query()->find($this->userId);
        $session = AiEmployeeSession::query()
            ->where('user_id', $this->userId)
            ->where('conversation_id', $this->conversationId)
            ->first();

        if ($user === null || $session === null) {
            return;
        }

        try {
            $reply = $orchestrator->runTurn($user, $session, $this->text);
            if ($this->channel === 'whatsapp') {
                $whatsApp->reply($session, $reply);
            }
        } catch (Throwable $e) {
            report($e);
            $reply = 'Something went wrong on my side. Try again in a moment, or type `status` to confirm I can reach your workspace.';
            $orchestrator->recordTurnFailure($user, $session, $this->text, $reply);
            if ($this->channel === 'whatsapp') {
                $whatsApp->reply($session, $reply);
            }
        } finally {
            $sessions->finishTurn($session->fresh() ?? $session);
        }
    }
}
