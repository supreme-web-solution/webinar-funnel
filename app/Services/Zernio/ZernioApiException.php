<?php

namespace App\Services\Zernio;

use Illuminate\Http\Client\Response;

final class ZernioApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $code = null,
        public readonly ?string $dashboardUrl = null,
        public readonly ?string $documentationUrl = null,
        public readonly int $httpStatus = 0,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $json = $response->json();
        if (! is_array($json)) {
            return new self(
                'Zernio API error ('.$response->status().').',
                httpStatus: $response->status(),
            );
        }

        $error = $json['error'] ?? null;
        $message = is_string($error)
            ? $error
            : (is_array($error) ? ($error['message'] ?? null) : null);

        if (! is_string($message) || $message === '') {
            $message = 'Zernio API error ('.$response->status().').';
        }

        $code = $json['code'] ?? (is_array($error) ? ($error['code'] ?? null) : null);

        return new self(
            $message,
            is_string($code) ? $code : null,
            is_string($json['dashboard_url'] ?? null) ? $json['dashboard_url'] : null,
            is_string($json['documentation_url'] ?? null) ? $json['documentation_url'] : null,
            $response->status(),
        );
    }

    public function isPaymentRequired(): bool
    {
        return $this->code === 'PAYMENT_REQUIRED' || $this->httpStatus === 402;
    }
}
