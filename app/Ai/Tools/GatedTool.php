<?php

namespace App\Ai\Tools;

use App\Models\AiEmployeeSession;
use App\Models\User;
use App\Services\AiEmployee\ActionApprovalService;
use App\Services\AiEmployee\AiActionLogService;
use App\Services\AiEmployee\AiEmployeeSessionService;
use App\Services\AiEmployee\AiEmployeeSettingsService;
use App\Services\AiEmployee\ToolPolicyGateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

abstract class GatedTool implements Tool
{
    public function __construct(protected User $user) {}

    abstract public function toolName(): string;

    abstract public function permission(): string;

    abstract protected function run(Request $request): Stringable|string;

    public function executeApproved(Request $request): string
    {
        return (string) $this->run($request);
    }

    public function name(): string
    {
        return $this->toolName();
    }

    public function handle(Request $request): Stringable|string
    {
        $settings = app(AiEmployeeSettingsService::class)->for($this->user);
        $gate = app(ToolPolicyGateService::class)->inspect($this, $settings);

        if (! ($gate['ok'] ?? false)) {
            $this->log('blocked', $request->all(), $gate['message'] ?? 'Blocked');

            return $gate['message'] ?? 'This action is not allowed.';
        }

        app(AiEmployeeSessionService::class)->progress('Using '.$this->toolName().'…');

        if (($gate['stage_approval'] ?? false) === true) {
            $approval = app(ActionApprovalService::class)->stage(
                $this->user,
                $this,
                $request->all(),
                $this->approvalSummary($request),
                $this->conversationId(),
            );

            $message = 'Staged **'.$approval->launchLabel().'**: '.$approval->summary
                ."\n\nIt has not run yet. Reply `LAUNCH {$approval->id}` or click Approve.";

            $this->log('staged', $request->all(), $message);

            return $message;
        }

        try {
            $result = $this->run($request);
            $text = (string) $result;
            $this->log('ok', $request->all(), $text);

            return $text;
        } catch (Throwable $e) {
            $this->log('failed', $request->all(), $e->getMessage());

            return 'Tool '.$this->toolName().' failed: '.$e->getMessage();
        }
    }

    abstract public function schema(JsonSchema $schema): array;

    protected function approvalSummary(Request $request): string
    {
        return 'Run '.$this->toolName().' with the staged arguments.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    protected function conversationId(): ?string
    {
        $session = app(AiEmployeeSessionService::class)->current()
            ?? AiEmployeeSession::query()->where('user_id', $this->user->id)->first();

        return is_string($session?->conversation_id) ? $session->conversation_id : null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function log(string $status, array $arguments, string $result): void
    {
        $session = app(AiEmployeeSessionService::class)->current();

        app(AiActionLogService::class)->record(
            $this->user,
            $this->toolName(),
            $this->permission(),
            $status,
            $arguments,
            $result,
            $this->conversationId(),
            is_string($session?->channel) ? $session->channel : 'web',
        );
    }
}
