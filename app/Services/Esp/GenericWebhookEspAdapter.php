<?php

namespace App\Services\Esp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GenericWebhookEspAdapter implements EspProviderAdapter
{
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
                'lead'   => $payload,
                'config' => $config,
            ]);

        return [
            'ok'      => $response->successful(),
            'message' => $response->successful() ? 'Delivered to webhook.' : "Webhook returned HTTP {$response->status()}.",
            'status'  => $response->status(),
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
        $response = Http::timeout(8)
            ->connectTimeout(5)
            ->withoutRedirecting()
            ->post($url, ['test' => true, 'source' => 'dfy-webinar-forge']);

        return [
            'ok'      => $response->status() < 500,
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

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
