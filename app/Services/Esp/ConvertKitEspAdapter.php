<?php

namespace App\Services\Esp;

use App\Services\Esp\Concerns\FormatsEmailSequenceHtml;
use Illuminate\Support\Facades\Http;

class ConvertKitEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

    private const BASE = 'https://api.convertkit.com/v2';

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $apiSecret = (string) ($credentials['api_secret'] ?? '');
        $formId    = (string) ($credentials['form_id'] ?? '');

        if (! $apiSecret || ! $formId) {
            return ['ok' => false, 'message' => 'API secret and Form ID are required.'];
        }

        $nameParts = explode(' ', $payload['name'] ?? '', 2);

        $response = Http::timeout(10)
            ->post(self::BASE."/forms/{$formId}/subscribe", [
                'api_secret' => $apiSecret,
                'email'      => $payload['email'],
                'first_name' => $nameParts[0] ?? '',
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Subscribed.' : ($response->json('message') ?? 'ConvertKit error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $apiSecret = (string) ($credentials['api_secret'] ?? '');

        if (! $apiSecret) {
            return ['ok' => false, 'message' => 'API secret is required.'];
        }

        $response = Http::timeout(8)
            ->get(self::BASE.'/account', ['api_secret' => $apiSecret]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? 'Connected: '.($response->json('name') ?? '✓')
                : ($response->json('message') ?? 'Could not connect to ConvertKit.'),
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
        $apiSecret = (string) ($credentials['api_secret'] ?? '');

        if ($apiSecret === '') {
            return ['ok' => false, 'message' => 'API secret is required.'];
        }

        $uploaded = 0;
        $details = [];

        foreach ($emails as $index => $email) {
            $response = Http::timeout(20)
                ->post('https://api.convertkit.com/v3/broadcasts', [
                    'api_secret' => $apiSecret,
                    'subject' => (string) ($email['subject'] ?? 'Update'),
                    'content' => $this->bodyToHtml((string) ($email['body'] ?? '')),
                ]);

            if ($response->successful()) {
                $uploaded++;
                $details[] = 'Email '.($index + 1).': broadcast draft created';
            } else {
                $details[] = 'Email '.($index + 1).': '.($response->json('message') ?? 'failed');
            }
        }

        return [
            'ok' => $uploaded > 0,
            'message' => $uploaded > 0
                ? "{$uploaded} swipes uploaded as ConvertKit broadcast drafts."
                : 'ConvertKit upload failed for all emails.',
            'uploaded' => $uploaded,
            'details' => $details,
        ];
    }
}
