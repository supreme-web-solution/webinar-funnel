<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenericWebhookEspAdapter implements EspProviderAdapter, EspSequenceAdapter
{
    use FormatsEmailSequenceHtml;

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        $url = (string) ($credentials['webhook_url'] ?? '');

        if (! $url) {
            return ['ok' => false, 'message' => 'Missing webhook_url credential.'];
        }
        $urlCheck = $this->validateWebhookUrl($url);
        if (! $urlCheck['ok']) {
            return ['ok' => false, 'message' => $urlCheck['message']];
        }

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

        if (! empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$credentials['api_key'];
        }

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->post($url, [
                'lead' => $payload,
                'config' => $config,
            ]);

        return [
            'ok' => $response->successful(),
            'message' => $response->successful() ? 'Delivered to webhook.' : "Webhook returned HTTP {$response->status()}.",
            'status' => $response->status(),
        ];
    }

    public function testConnection(array $credentials): array
    {
        $url = (string) ($credentials['webhook_url'] ?? '');

        if (! $url) {
            return ['ok' => false, 'message' => 'Webhook URL is required.'];
        }
        $urlCheck = $this->validateWebhookUrl($url);
        if (! $urlCheck['ok']) {
            return ['ok' => false, 'message' => $urlCheck['message']];
        }

        // Send a test ping payload
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

        if (! empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$credentials['api_key'];
        }

        $response = Http::withHeaders($headers)
            ->timeout(8)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->post($url, ['test' => true, 'source' => 'dfy-webinar-forge']);

        return [
            'ok' => $response->status() < 500,
            'message' => $response->status() < 500
                ? "Webhook reachable (HTTP {$response->status()})."
                : "Webhook returned HTTP {$response->status()}.",
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function validateWebhookUrl(string $url): array
    {
        $url = trim($url);

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'message' => 'Webhook URL must be a valid absolute URL.'];
        }

        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'message' => 'Webhook URL must use http or https.'];
        }

        if ($host === '') {
            return ['ok' => false, 'message' => 'Webhook URL host is required.'];
        }

        if ($host === 'localhost' || Str::endsWith($host, '.local')) {
            return ['ok' => false, 'message' => 'Localhost/private webhook URLs are not allowed.'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->isPublicIp($host)) {
                return ['ok' => false, 'message' => 'Private/reserved IP webhook URLs are not allowed.'];
            }

            return ['ok' => true, 'message' => 'ok'];
        }

        $resolvedIps = gethostbynamel($host) ?: [];
        if ($resolvedIps === []) {
            return ['ok' => false, 'message' => 'Webhook host could not be resolved.'];
        }

        foreach ($resolvedIps as $ip) {
            if (! $this->isPublicIp($ip)) {
                return ['ok' => false, 'message' => 'Webhook host resolves to a private/reserved IP, which is blocked.'];
            }
        }

        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * @param  array<int, array{subject: string, body: string, sequence_key?: string, sort_order?: int}>  $emails
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $config
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadSequence(array $emails, array $credentials, array $config): array
    {
        $url = (string) ($credentials['webhook_url'] ?? '');

        if ($url === '') {
            return ['ok' => false, 'message' => 'Webhook URL is required.'];
        }

        $urlCheck = $this->validateWebhookUrl($url);
        if (! $urlCheck['ok']) {
            return ['ok' => false, 'message' => $urlCheck['message']];
        }

        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if (! empty($credentials['api_key'])) {
            $headers['Authorization'] = 'Bearer '.$credentials['api_key'];
        }

        $payload = [
            'type' => 'email_sequence',
            'campaign' => [
                'name' => $config['campaign_name'] ?? null,
                'uuid' => $config['campaign_uuid'] ?? null,
                'affiliate_link' => $config['affiliate_link'] ?? null,
                'tag' => $config['tag'] ?? null,
            ],
            'emails' => array_map(fn (array $email, int $i) => [
                'index' => $i + 1,
                'sequence_key' => $email['sequence_key'] ?? null,
                'subject' => $email['subject'] ?? '',
                'body' => $email['body'] ?? '',
                'body_html' => $this->bodyToHtml((string) ($email['body'] ?? '')),
            ], $emails, array_keys($emails)),
        ];

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->post($url, $payload);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => "Webhook returned HTTP {$response->status()}.",
                'uploaded' => 0,
            ];
        }

        return [
            'ok' => true,
            'message' => count($emails).' swipes delivered to webhook.',
            'uploaded' => count($emails),
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
