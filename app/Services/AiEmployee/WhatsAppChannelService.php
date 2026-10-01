<?php

namespace App\Services\AiEmployee;

use App\Models\AiEmployeeSession;
use App\Models\AiEmployeeSetting;
use App\Models\User;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class WhatsAppChannelService
{
    public function __construct(
        protected AgentOrchestrator $orchestrator,
        protected AiEmployeeSessionService $sessions,
        protected WhatsAppPairingService $pairing,
        protected ZernioClient $zernio,
        protected AiEmployeeSettingsService $settings,
    ) {}

    protected ?Process $typingPulse = null;

    protected ?string $typingToken = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleInbound(array $payload): void
    {
        $event = (string) ($payload['event'] ?? '');
        if ($event !== 'message.received') {
            return;
        }

        $platform = strtolower((string) data_get($payload, 'account.platform', data_get($payload, 'account.platformName', '')));
        if ($platform !== '' && $platform !== 'whatsapp') {
            return;
        }

        $configuredAccount = (string) config('ai_employee.whatsapp.account_id', '');
        $accountId = (string) data_get($payload, 'account.accountId', data_get($payload, 'account.id', ''));
        if ($configuredAccount !== '' && $accountId !== '' && $accountId !== $configuredAccount) {
            return;
        }

        if ((bool) data_get($payload, 'metadata.standby', false)) {
            return;
        }

        $text = $this->inboundText($payload);
        $from = $this->senderPhone($payload);
        $conversationId = (string) data_get($payload, 'conversation.id', data_get($payload, 'conversation.conversationId', ''));

        if ($from === '' || $text === '') {
            return;
        }

        $businessPhone = $this->normalizePhone((string) config('ai_employee.whatsapp.phone', ''));
        if ($businessPhone !== '' && $from === $businessPhone) {
            return;
        }

        if (! $this->claimInbound($conversationId, $from, $text, $payload)) {
            return;
        }

        $user = $this->userFromPhone($from);
        if ($user === null) {
            $linked = $this->tryPair($text, $from);
            $reply = $linked
                ? 'Linked. You can talk to '.(string) config('ai_employee.name', 'Alex').' from this number — same brain as Command Center.'
                : 'This WhatsApp is not linked yet. Open Command Center → WhatsApp, generate a code, then send it here (example: LINK-AB12CD).';
            $this->send($accountId, $conversationId, $from, $reply);

            return;
        }

        $session = $this->sessions->for($user);
        $session->forceFill([
            'channel' => 'whatsapp',
            'zernio_conversation_id' => $conversationId !== '' ? $conversationId : $session->zernio_conversation_id,
            'zernio_account_id' => $accountId !== '' ? $accountId : $session->zernio_account_id,
        ])->save();

        $this->showTyping(
            $accountId !== '' ? $accountId : (string) $session->zernio_account_id,
            $conversationId !== '' ? $conversationId : (string) $session->zernio_conversation_id,
        );

        $result = $this->orchestrator->handle($user, $text, 'whatsapp');
        if (! ($result['processing'] ?? false) && is_string($result['message'] ?? null)) {
            $this->reply($session->fresh() ?? $session, $result['message']);
        }
    }

    public function reply(AiEmployeeSession $session, string $text): void
    {
        $accountId = (string) ($session->zernio_account_id ?: config('ai_employee.whatsapp.account_id', ''));
        $conversationId = (string) ($session->zernio_conversation_id ?? '');
        if ($accountId === '' || $conversationId === '') {
            return;
        }

        $this->stopTypingPulse();
        $user = $session->relationLoaded('user') ? $session->user : $session->user()->first();
        $actions = $user instanceof User ? $this->whatsappActions($user) : [];
        $this->send($accountId, $conversationId, null, $text, $actions);
    }

    /**
     * WhatsApp reply buttons (up to 3) or a list when more approvals are waiting.
     *
     * @return array{buttons?: list<array<string, string>>, interactive?: array<string, mixed>}
     */
    public function whatsappActions(User $user): array
    {
        $pending = app(ActionApprovalService::class)->pendingPayload($user);
        if ($pending === []) {
            return [];
        }

        if (count($pending) === 1) {
            $id = (int) $pending[0]['id'];

            return [
                'buttons' => [
                    ['type' => 'postback', 'title' => 'Approve', 'payload' => 'LAUNCH '.$id],
                    ['type' => 'postback', 'title' => 'Reject', 'payload' => 'REJECT '.$id],
                ],
            ];
        }

        $rows = [];
        foreach (array_slice($pending, 0, 5) as $approval) {
            $id = (int) $approval['id'];
            $summary = Str::limit(trim((string) ($approval['summary'] ?? '')), 72, '');
            $rows[] = [
                'id' => 'LAUNCH '.$id,
                'title' => Str::limit('Approve #'.$id, 24, ''),
                'description' => $summary,
            ];
            $rows[] = [
                'id' => 'REJECT '.$id,
                'title' => Str::limit('Reject #'.$id, 24, ''),
                'description' => $summary,
            ];
        }

        return [
            'interactive' => [
                'type' => 'list',
                'action' => [
                    'button' => 'Review actions',
                    'sections' => [[
                        'title' => 'Pending',
                        'rows' => $rows,
                    ]],
                ],
            ],
        ];
    }

    public function showTyping(string $accountId, string $conversationId): void
    {
        $this->zernio->sendTypingIndicator($accountId, $conversationId);
    }

    /**
     * Keep WhatsApp "typing…" visible while a reply is generated.
     * The platform indicator expires after about 25 seconds.
     */
    public function startTypingPulse(string $accountId, string $conversationId): void
    {
        $this->showTyping($accountId, $conversationId);

        if (app()->runningUnitTests() || $accountId === '' || $conversationId === '') {
            return;
        }

        $this->stopTypingPulse();

        $token = (string) Str::uuid();
        Cache::put($this->typingCacheKey($token), [
            'account_id' => $accountId,
            'conversation_id' => $conversationId,
        ], now()->addMinutes(4));

        $process = new Process([PHP_BINARY, base_path('artisan'), 'ai-employee:typing-pulse', $token]);
        $process->setTimeout(null);
        $process->disableOutput();

        try {
            $process->start();
            $this->typingPulse = $process;
            $this->typingToken = $token;
        } catch (\Throwable $e) {
            Cache::forget($this->typingCacheKey($token));
            Log::warning('WhatsApp typing pulse failed to start', ['error' => $e->getMessage()]);
        }
    }

    public function stopTypingPulse(): void
    {
        if ($this->typingToken !== null) {
            Cache::forget($this->typingCacheKey($this->typingToken));
            $this->typingToken = null;
        }

        if ($this->typingPulse === null) {
            return;
        }

        try {
            if ($this->typingPulse->isRunning()) {
                $this->typingPulse->stop(1);
            }
        } catch (\Throwable $e) {
            Log::debug('WhatsApp typing pulse stop failed', ['error' => $e->getMessage()]);
        }

        $this->typingPulse = null;
    }

    protected function typingCacheKey(string $token): string
    {
        return 'whatsapp-typing:'.$token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function claimInbound(string $conversationId, string $from, string $text, array $payload): bool
    {
        $messageId = (string) (
            data_get($payload, 'message.id')
            ?? data_get($payload, 'message.messageId')
            ?? data_get($payload, 'message.platformMessageId')
            ?? ''
        );

        $key = $messageId !== ''
            ? 'whatsapp-inbound-id:'.$messageId
            : 'whatsapp-inbound:'.md5($conversationId.'|'.$from.'|'.trim($text));

        return Cache::add($key, 1, now()->addMinutes(2));
    }

    protected function tryPair(string $text, string $phone): bool
    {
        if (preg_match('/LINK[- ]?([A-Z0-9]{6})/i', $text, $match) !== 1) {
            return false;
        }

        $setting = $this->pairing->consumeCode('LINK-'.strtoupper($match[1]));
        if ($setting === null) {
            return false;
        }

        $setting->forceFill(['whatsapp_phone' => $phone])->save();

        return true;
    }

    protected function userFromPhone(string $phone): ?User
    {
        $setting = AiEmployeeSetting::query()->where('whatsapp_phone', $phone)->first();

        return $setting?->user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function senderPhone(array $payload): string
    {
        $raw = data_get($payload, 'message.sender.phone')
            ?? data_get($payload, 'message.sender.participantId')
            ?? data_get($payload, 'message.from')
            ?? data_get($payload, 'conversation.participantId')
            ?? '';

        return $this->normalizePhone((string) $raw);
    }

    public function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function inboundText(array $payload): string
    {
        $command = $this->buttonCommand($payload);
        if ($command !== '') {
            return $command;
        }

        return trim((string) data_get($payload, 'message.text', data_get($payload, 'message.body', '')));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function buttonCommand(array $payload): string
    {
        $candidates = [
            data_get($payload, 'metadata.interactiveId'),
            data_get($payload, 'metadata.buttonPayload'),
            data_get($payload, 'message.payload'),
            data_get($payload, 'message.postback.payload'),
            data_get($payload, 'message.button.payload'),
        ];

        foreach ($candidates as $value) {
            $value = trim((string) $value);
            if (preg_match('/^(LAUNCH|REJECT)\s+#?\d+$/i', $value) === 1) {
                return strtoupper(strtok($value, ' ') ?: '').' '.preg_replace('/\D+/', '', $value);
            }
        }

        return '';
    }

    /**
     * @param  array{buttons?: list<array<string, string>>, interactive?: array<string, mixed>}  $actions
     */
    protected function send(string $accountId, string $conversationId, ?string $participantId, string $text, array $actions = []): void
    {
        if (! $this->zernio->isConfigured()) {
            return;
        }

        $text = Str::limit($text, 1000, '…');

        try {
            $this->zernio->sendInboxMessage($accountId, $conversationId, $text, $participantId, $actions);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);
        }
    }
}
