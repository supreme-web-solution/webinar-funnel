<?php

namespace App\Services\AiEmployee;

use App\Models\AiEmployeeSession;
use App\Models\AiEmployeeSetting;
use App\Models\User;
use App\Services\Zernio\ZernioClient;
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

        $text = trim((string) data_get($payload, 'message.text', data_get($payload, 'message.body', '')));
        $from = $this->senderPhone($payload);
        $conversationId = (string) data_get($payload, 'conversation.id', data_get($payload, 'conversation.conversationId', ''));

        if ($from === '' || $text === '') {
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

        $this->send($accountId, $conversationId, null, $text);
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

    protected function send(string $accountId, string $conversationId, ?string $participantId, string $text): void
    {
        if (! $this->zernio->isConfigured()) {
            return;
        }

        $text = Str::limit($text, 1500, '…');

        try {
            $this->zernio->sendInboxMessage($accountId, $conversationId, $text, $participantId);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp send failed', ['error' => $e->getMessage()]);
        }
    }
}
