<?php

namespace App\Services\Esp;

use App\Services\Esp\Concerns\FormatsEmailSequenceHtml;
use Illuminate\Support\Facades\Http;

class MailchimpEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

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

    /**
     * @param  array<int, array{subject: string, body: string, sequence_key?: string, sort_order?: int}>  $emails
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadSequence(array $emails, array $credentials, array $config): array
    {
        [$dc, $apiKey, $listId] = $this->extractCredentials($credentials);

        if (! $apiKey || ! $listId) {
            return ['ok' => false, 'message' => 'API key and Audience ID are required.'];
        }

        $campaignName = (string) ($config['campaign_name'] ?? config('app.name', 'App').' Campaign');
        $tag = trim((string) ($config['tag'] ?? ''));
        $uploaded = 0;
        $details = [];

        foreach ($emails as $index => $email) {
            $title = $tag !== ''
                ? "{$campaignName} — {$tag} — ".($index + 1)
                : "{$campaignName} — Email ".($index + 1);

            $create = Http::withBasicAuth('anystring', $apiKey)
                ->timeout(20)
                ->post("https://{$dc}.api.mailchimp.com/3.0/campaigns", [
                    'type' => 'regular',
                    'recipients' => ['list_id' => $listId],
                    'settings' => [
                        'subject_line' => (string) ($email['subject'] ?? 'Update'),
                        'title' => $title,
                        'from_name' => config('app.name', 'AffiliateOS'),
                    ],
                ]);

            if (! $create->successful()) {
                $details[] = 'Email '.($index + 1).': '.($create->json('detail') ?? 'create failed');

                continue;
            }

            $campaignId = (string) $create->json('id');
            $content = Http::withBasicAuth('anystring', $apiKey)
                ->timeout(20)
                ->put("https://{$dc}.api.mailchimp.com/3.0/campaigns/{$campaignId}/content", [
                    'html' => $this->bodyToHtml((string) ($email['body'] ?? '')),
                ]);

            if ($content->successful()) {
                $uploaded++;
                $details[] = 'Email '.($index + 1).': draft saved';
            } else {
                $details[] = 'Email '.($index + 1).': '.($content->json('detail') ?? 'content failed');
            }
        }

        if ($uploaded === 0) {
            return [
                'ok' => false,
                'message' => 'Could not upload any emails to Mailchimp.',
                'uploaded' => 0,
                'details' => $details,
            ];
        }

        return [
            'ok' => true,
            'message' => "{$uploaded} of ".count($emails).' swipes uploaded as Mailchimp draft campaigns.',
            'uploaded' => $uploaded,
            'details' => $details,
        ];
    }
}
