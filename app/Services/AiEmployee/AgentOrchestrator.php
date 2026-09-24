<?php

namespace App\Services\AiEmployee;

use App\Ai\Agents\AppEmployeeAgent;
use App\Ai\Tools\GatedTool;
use App\Jobs\ProcessAiChatTurnJob;
use App\Models\AiActionApproval;
use App\Models\AiEmployeeSession;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\ConversationMessage;
use Laravel\Ai\Tools\Request;
use Throwable;

class AgentOrchestrator
{
    public function __construct(
        protected AiEmployeeSettingsService $settings,
        protected AiEmployeeSessionService $sessions,
        protected CommandCenterSnapshotService $snapshot,
        protected ActionApprovalService $approvals,
        protected AiProviderFailoverService $failover,
        protected AiActionLogService $logs,
    ) {}

    /**
     * @return array{
     *     conversation_id: string,
     *     status: string,
     *     message: ?string,
     *     processing: bool
     * }
     */
    public function handle(User $user, string $text, string $channel = 'web'): array
    {
        $text = trim($text);
        $session = $this->sessions->for($user);
        $this->sessions->bind($session);
        $setting = $this->settings->for($user);

        if ($setting->isKilled() && ! $this->isWakeCommand($text)) {
            $reply = 'I am paused (kill switch). Turn me back on in Command Center settings, or say `RESUME`.';
            $this->appendExchange($user, $session, $text, $reply);

            return $this->result($session, 'complete', $reply);
        }

        $command = $this->controlCommand($user, $session, $text);
        if ($command !== null) {
            $this->appendExchange($user, $session, $text, $command);

            return $this->result($session, 'complete', $command);
        }

        if (! $this->failover->hasAny() && ! AppEmployeeAgent::isFaked()) {
            $reply = 'No AI provider is configured. Add OPENROUTER_API_KEY (or OPENAI / GEMINI / ANTHROPIC) and retry.';
            $this->appendExchange($user, $session, $text, $reply);

            return $this->result($session, 'complete', $reply);
        }

        $this->recordUserMessage($user, $session, $text);
        $this->sessions->startTurn($session, $channel);
        ProcessAiChatTurnJob::dispatch($user->id, $session->conversation_id, $text, $channel);

        return $this->result($session, 'processing', null, true);
    }

    public function runTurn(User $user, AiEmployeeSession $session, string $text): string
    {
        $this->sessions->bind($session);
        $agent = new AppEmployeeAgent($user);
        $providers = $this->failover->providers();

        $response = $agent
            ->continue($session->conversation_id, $user)
            ->prompt(
                $text,
                provider: $providers !== [] ? $providers : null,
            );

        return (string) $response->text;
    }

    public function recordTurnFailure(User $user, AiEmployeeSession $session, string $userText, string $assistantText): void
    {
        $this->sessions->bind($session);
        $this->appendAssistant($user, $session, $assistantText);
    }

    public function recordUserMessage(User $user, AiEmployeeSession $session, string $userText): void
    {
        $this->sessions->bind($session);
        $this->appendMessage($user, $session, 'user', $userText);
    }

    public function recordTurnSuccess(User $user, AiEmployeeSession $session, string $assistantText): void
    {
        $this->sessions->bind($session);
        $this->appendAssistant($user, $session, $assistantText);
    }

    public function launch(User $user, int $approvalId): string
    {
        $session = $this->sessions->for($user);
        $this->sessions->bind($session);
        $approval = $this->approvals->pendingFor($user, $approvalId);
        if ($approval === null) {
            return 'No pending action '.$approvalId.' to launch.';
        }

        $tool = (new AppEmployeeAgent($user))->gatedTool($approval->tool_name);
        if ($tool === null) {
            $approval->update([
                'status' => AiActionApproval::STATUS_FAILED,
                'result' => 'Unknown tool '.$approval->tool_name,
                'resolved_at' => now(),
            ]);

            return 'I cannot run tool '.$approval->tool_name.'.';
        }

        try {
            $result = $tool->executeApproved(new Request($approval->payload ?? []));
            $approval->update([
                'status' => AiActionApproval::STATUS_EXECUTED,
                'result' => mb_substr($result, 0, 5000),
                'resolved_at' => now(),
            ]);
            $this->logs->record($user, $approval->tool_name, 'execute', 'ok', $approval->payload ?? [], $result, $session->conversation_id);

            $formatted = app(ChatPresentationService::class)->formatLaunchResult($approval->tool_name, $result);
            $headline = str_starts_with($formatted, '**Could not complete:**') ? '**Launch failed**' : '**Done**';
            $reply = $headline.' — '.$approval->summary."\n\n".$formatted;
            $this->appendAssistant($user, $session, $reply);

            return $reply;
        } catch (Throwable $e) {
            $approval->update([
                'status' => AiActionApproval::STATUS_FAILED,
                'result' => $e->getMessage(),
                'resolved_at' => now(),
            ]);

            return 'LAUNCH '.$approvalId.' failed: '.$e->getMessage();
        }
    }

    public function reject(User $user, int $approvalId, ?string $reason = null): string
    {
        $session = $this->sessions->for($user);
        $approval = $this->approvals->pendingFor($user, $approvalId);
        if ($approval === null) {
            return 'No pending action '.$approvalId.' to reject.';
        }

        $approval->update([
            'status' => AiActionApproval::STATUS_REJECTED,
            'result' => $reason,
            'resolved_at' => now(),
        ]);

        $reply = 'Rejected '.$approval->launchLabel().($reason ? ': '.$reason : '.');
        $this->appendAssistant($user, $session, $reply);

        return $reply;
    }

    protected function controlCommand(User $user, AiEmployeeSession $session, string $text): ?string
    {
        $normalized = strtolower(trim($text));

        if ($normalized === 'help' || $normalized === '/help') {
            $name = (string) config('ai_employee.name', 'Alex');

            return $name." can run this app from chat.\n\n"
                ."Commands: `status`, `attention`, `LAUNCH {id}`, `REJECT {id}`, `PAUSE {id}`, `RESUME`, `help`.\n\n"
                .'Examples: “create a tracked link to https://example.com”, “find keto offers”, “plan this week’s content”, “publish campaign 12”, “delete campaign 12” (needs Approve).';
        }

        if ($normalized === 'status' || $normalized === '/status') {
            return "Workspace snapshot:\n".$this->pretty(app(CommandCenterSnapshotService::class)->status($user));
        }

        if ($normalized === 'attention' || $normalized === '/attention') {
            $items = $this->snapshot->attention($user);
            if ($items === []) {
                return 'Nothing needs you right now.';
            }

            return "Needs attention:\n".$this->pretty($items);
        }

        if (preg_match('/^(resume|unpause)$/i', $normalized) === 1) {
            $this->settings->update($user, ['killed' => false]);

            return 'Kill switch off. I am back.';
        }

        if (preg_match('/^launch\s+#?(\d+)$/i', $text, $match) === 1) {
            return $this->launch($user, (int) $match[1]);
        }

        if (preg_match('/^reject\s+#?(\d+)(?:\s+(.+))?$/i', $text, $match) === 1) {
            return $this->reject($user, (int) $match[1], $match[2] ?? null);
        }

        if (preg_match('/^pause\s+#?(\d+)$/i', $text, $match) === 1) {
            $tool = (new AppEmployeeAgent($user))->gatedTool('pause_campaign');
            if ($tool instanceof GatedTool) {
                return (string) $tool->handle(new Request(['campaign_id' => (int) $match[1]]));
            }
        }

        return null;
    }

    protected function isWakeCommand(string $text): bool
    {
        return (bool) preg_match('/^(resume|unpause|help|status)$/i', trim($text));
    }

    protected function appendExchange(User $user, AiEmployeeSession $session, string $userText, string $assistantText): void
    {
        $this->appendMessage($user, $session, 'user', $userText);
        $this->appendAssistant($user, $session, $assistantText);
    }

    protected function appendAssistant(User $user, AiEmployeeSession $session, string $text): void
    {
        $this->appendMessage($user, $session, 'assistant', $text);
    }

    protected function appendMessage(User $user, AiEmployeeSession $session, string $role, string $content): void
    {
        $content = trim($content);
        if ($content === '') {
            return;
        }

        $last = ConversationMessage::query()
            ->where('conversation_id', $session->conversation_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($last !== null && $last->role === $role && trim((string) $last->content) === $content) {
            return;
        }

        ConversationMessage::query()->create([
            'id' => (string) Str::uuid7(),
            'conversation_id' => $session->conversation_id,
            'user_id' => $user->id,
            'agent' => AppEmployeeAgent::class,
            'role' => $role,
            'content' => $content,
            'attachments' => [],
            'tool_calls' => [],
            'tool_results' => [],
            'usage' => [],
            'meta' => ['channel' => $session->channel],
        ]);
    }

    /**
     * @return array{conversation_id: string, status: string, message: ?string, processing: bool}
     */
    protected function result(AiEmployeeSession $session, string $status, ?string $message, bool $processing = false): array
    {
        return [
            'conversation_id' => (string) $session->conversation_id,
            'status' => $status,
            'message' => $message,
            'processing' => $processing,
        ];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    protected function pretty(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
