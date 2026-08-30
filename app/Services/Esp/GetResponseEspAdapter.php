<?php

namespace App\Services\Esp;

use App\Services\Esp\Concerns\FormatsEmailSequenceHtml;
use Illuminate\Support\Facades\Http;

class GetResponseEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

    private const BASE = 'https://api.getresponse.com/v3';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiKey     = (string) ($credentials['api_key'] ?? '');
        $campaignId = (string) ($credentials['campaign_id'] ?? '');

        if (! $apiKey || ! $campaignId) {
            return ['ok' => false, 'message' => 'API key and Campaign ID are required.'];
        }

        $response = Http::withHeaders(['X-Auth-Token' => "api-key {$apiKey}"])
            ->timeout(10)
            ->post(self::BASE.'/contacts', [
                'email'    => $payload['email'],
                'name'     => $payload['name'] ?? '',
                'campaign' => ['campaignId' => $campaignId],
            ]);

        // 409 = already exists — still a success
        $ok = $response->successful() || $response->status() === 409;

        return [
            'ok'      => $ok,
            'message' => $ok ? 'Subscribed.' : ($response->json('message') ?? 'GetResponse error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');

        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $response = Http::withHeaders(['X-Auth-Token' => "api-key {$apiKey}"])
            ->timeout(8)
            ->get(self::BASE.'/accounts');

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected to GetResponse account: '.($response->json('email') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to GetResponse.'),
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
        $campaignId = (string) ($credentials['campaign_id'] ?? '');

        if (! $apiKey || ! $campaignId) {
            return ['ok' => false, 'message' => 'API key and Campaign ID are required.'];
        }

        $uploaded = 0;
        $details = [];
        $prefix = (string) ($config['campaign_name'] ?? 'Campaign');

        foreach ($emails as $index => $email) {
            $response = Http::withHeaders(['X-Auth-Token' => "api-key {$apiKey}"])
                ->timeout(20)
                ->post(self::BASE.'/newsletters', [
                    'name' => "{$prefix} — Email ".($index + 1),
                    'subject' => (string) ($email['subject'] ?? 'Update'),
                    'campaign' => ['campaignId' => $campaignId],
                    'content' => [
                        'html' => $this->bodyToHtml((string) ($email['body'] ?? '')),
                    ],
                    'flags' => ['openrate', 'clicktrack'],
                ]);

            if ($response->successful()) {
                $uploaded++;
                $details[] = 'Email '.($index + 1).': newsletter draft created';
            } else {
                $details[] = 'Email '.($index + 1).': '.($response->json('message') ?? 'failed');
            }
        }

        return [
            'ok' => $uploaded > 0,
            'message' => $uploaded > 0
                ? "{$uploaded} swipes uploaded as GetResponse newsletter drafts."
                : 'GetResponse upload failed for all emails.',
            'uploaded' => $uploaded,
            'details' => $details,
        ];
    }
}
