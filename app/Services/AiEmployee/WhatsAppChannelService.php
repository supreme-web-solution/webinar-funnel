<?php

namespace App\Services\AiEmployee;

use App\Jobs\PulseWhatsAppTypingJob;
use App\Models\AiEmployeeSession;
use App\Models\AiEmployeeSetting;
use App\Models\User;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppChannelService
{
    public function __construct(
        protected AgentOrchestrator $orchestrator,
        protected AiEmployeeSessionService $sessions,
        protected WhatsAppPairingService $pairing,
        protected ZernioClient $zernio,
        protected AiEmployeeSettingsService $settings,
    ) {}

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

        $this->startTypingPulse(
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

        $this->stopTypingPulse($conversationId);
        $user = $session->relationLoaded('user') ? $session->user : $session->user()->first();
        $this->send($accountId, $conversationId, null, $text);
        if (! $user instanceof User) {
            return;
        }

        foreach ($this->whatsappActionMessages($user) as $card) {
            $this->send($accountId, $conversationId, null, $card['text'], ['buttons' => $card['buttons']]);
        }
    }

    /**
     * One WhatsApp message per pending action, each with Approve and Reject.
     * Reply buttons are capped at 3, so each approval is its own message.
     *
     * @return list<array{text: string, buttons: list<array{type: string, title: string, payload: string}>}>
     */
    public function whatsappActionMessages(User $user): array
    {
        $pending = app(ActionApprovalService::class)->pendingPayload($user);
        $messages = [];

        foreach (array_slice($pending, 0, 5) as $approval) {
            $id = (int) $approval['id'];
            $summary = trim((string) ($approval['summary'] ?? ''));
            $messages[] = [
                'text' => $summary !== '' ? $summary : 'LAUNCH '.$id,
                'buttons' => [
                    ['type' => 'postback', 'title' => 'Approve', 'payload' => 'LAUNCH-'.$id],
                    ['type' => 'postback', 'title' => 'Reject', 'payload' => 'REJECT-'.$id],
                ],
            ];
        }

        return $messages;
    }

    public function showTyping(string $accountId, string $conversationId): void
    {
        $this->zernio->sendTypingIndicator($accountId, $conversationId);
    }

    /**
     * Keep WhatsApp "typing…" visible while a reply is generated.
     * The indicator expires after about 25 seconds, so a fast-queue job
     * refreshes it every 12 seconds until the reply is sent.
     */
    public function startTypingPulse(string $accountId, string $conversationId): void
    {
        if ($accountId === '' || $conversationId === '') {
            return;
        }

        $this->showTyping($accountId, $conversationId);

        if (app()->runningUnitTests()) {
            return;
        }

        $key = $this->typingCacheKey($conversationId);
        $alreadyRunning = Cache::has($key);
        Cache::put($key, [
            'account_id' => $accountId,
            'conversation_id' => $conversationId,
        ], now()->addMinutes(4));

        if (! $alreadyRunning) {
            PulseWhatsAppTypingJob::dispatch($conversationId)->delay(now()->addSeconds(12));
        }
    }

    public function stopTypingPulse(?string $conversationId = null): void
    {
        if ($conversationId === null || $conversationId === '') {
            return;
        }

        Cache::forget($this->typingCacheKey($conversationId));
    }

    protected function typingCacheKey(string $conversationId): string
    {
        return 'whatsapp-typing:'.$conversationId;
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
            if (preg_match('/^(LAUNCH|REJECT)[\s:#-]+#?(\d+)$/i', $value, $match) === 1) {
                return strtoupper($match[1]).' '.$match[2];
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
