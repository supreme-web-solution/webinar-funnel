<?php

namespace App\Services\Esp;

use App\Services\Esp\Concerns\FormatsEmailSequenceHtml;
use Illuminate\Support\Facades\Http;

/**
 * Sendinblue / Brevo adapter (same API, rebranded).
 */
class BrevoEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

    private const BASE = 'https://api.brevo.com/v3';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $listId = (int) ($credentials['list_id'] ?? 0);

        if (! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API key and List ID are required.'];
        }

        $nameParts = explode(' ', $payload['name'] ?? '', 2);

        $response = Http::withHeaders(['api-key' => $apiKey])
            ->timeout(10)
            ->post(self::BASE.'/contacts', [
                'email'         => $payload['email'],
                'attributes'    => [
                    'FIRSTNAME' => $nameParts[0] ?? '',
                    'LASTNAME'  => $nameParts[1] ?? '',
                ],
                'listIds'       => [$listId],
                'updateEnabled' => true,
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Subscribed.' : ($response->json('message') ?? 'Brevo error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $response = Http::withHeaders(['api-key' => $apiKey])
            ->timeout(8)
            ->get(self::BASE.'/account');

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected: '.($response->json('email') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to Brevo.'),
        ];
    }

    /**
     * @param  array<int, array{subject: string, body: string, sequence_key?: string, sort_order?: int}>  $emails
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadSequence(array $emails, array $credentials, array $config): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $listId = (int) ($credentials['list_id'] ?? 0);

        if (! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API key and List ID are required.'];
        }

        $account = Http::withHeaders(['api-key' => $apiKey])->timeout(8)->get(self::BASE.'/account');
        $senderEmail = (string) ($account->json('email') ?? '');
        if ($senderEmail === '') {
            return ['ok' => false, 'message' => 'Could not resolve Brevo sender email from account.'];
        }

        $uploaded = 0;
        $details = [];
        $prefix = (string) ($config['campaign_name'] ?? 'Campaign');

        foreach ($emails as $index => $email) {
            $response = Http::withHeaders(['api-key' => $apiKey])
                ->timeout(20)
                ->post(self::BASE.'/emailCampaigns', [
                    'name' => "{$prefix} — Email ".($index + 1),
                    'subject' => (string) ($email['subject'] ?? 'Update'),
                    'sender' => ['name' => config('app.name', 'AffiliateOS'), 'email' => $senderEmail],
                    'htmlContent' => $this->bodyToHtml((string) ($email['body'] ?? '')),
                    'recipients' => ['listIds' => [$listId]],
                ]);

            if ($response->successful()) {
                $uploaded++;
                $details[] = 'Email '.($index + 1).': draft campaign created';
            } else {
                $details[] = 'Email '.($index + 1).': '.($response->json('message') ?? 'failed');
            }
        }

        return [
            'ok' => $uploaded > 0,
            'message' => $uploaded > 0
                ? "{$uploaded} swipes uploaded as Brevo draft campaigns."
                : 'Brevo upload failed for all emails.',
            'uploaded' => $uploaded,
            'details' => $details,
        ];
    }
}
