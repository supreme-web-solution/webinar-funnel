<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;

class MailchimpEspAdapter implements EspProviderAdapter
{
    public function subscribe(array $payload, array $credentials, array $config): array
    {
        [$dc, $apiKey, $listId] = $this->extractCredentials($credentials);

        if (! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API key and Audience ID are required.'];
        }

        $nameParts = explode(' ', $payload['name'] ?? '', 2);

        $response = Http::withBasicAuth('anystring', $apiKey)
            ->timeout(10)
            ->put("https://{$dc}.api.mailchimp.com/3.0/lists/{$listId}/members/".md5(strtolower($payload['email'])), [
                'email_address' => $payload['email'],
                'status_if_new' => 'subscribed',
                'merge_fields'  => [
                    'FNAME' => $nameParts[0] ?? '',
                    'LNAME' => $nameParts[1] ?? '',
                ],
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Subscribed.' : ($response->json('detail') ?? 'Mailchimp error.'),
            'status'  => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        [$dc, $apiKey] = $this->extractCredentials($credentials);

        if (! $apiKey) {
            return ['ok' => false, 'message' => 'API key is required.'];
        }

        $response = Http::withBasicAuth('anystring', $apiKey)
            ->timeout(8)
            ->get("https://{$dc}.api.mailchimp.com/3.0/ping");

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful()
                ? ($response->json('health_status') ?? 'Connected.')
                : ($response->json('detail') ?? 'Could not connect to Mailchimp.'),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{0: string, 1: string, 2: string}
     */
    private function extractCredentials(array $credentials): array
    {
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $listId = (string) ($credentials['audience_id'] ?? '');

        // Derive the data-centre prefix from the API key suffix, e.g. "abc-us6" → "us6"
        $parts = explode('-', $apiKey);
        $dc    = count($parts) > 1 ? end($parts) : 'us1';

        return [$dc, $apiKey, $listId];
    }
}
