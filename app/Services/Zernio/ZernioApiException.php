<?php

namespace App\Services\Zernio;

use Illuminate\Http\Client\Response;

final class ZernioApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
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

    public function isStaleProfileError(): bool
    {
        if ($this->httpStatus !== 404) {
            return false;
        }

        $message = strtolower($this->getMessage());

        return str_contains($message, 'profile not found')
            || str_contains($message, 'access denied');
    }

    public function isPaymentRequired(): bool
    {
        if ($this->errorCode === 'PAYMENT_REQUIRED' || $this->httpStatus === 402) {
            return true;
        }

        $message = strtolower($this->getMessage());

        return str_contains($message, 'payment required')
            || str_contains($message, 'subscription is inactive')
            || str_contains($message, 'subscription inactive')
            || str_contains($message, 'payment method');
    }

    public function isDuplicateProfileError(): bool
    {
        if ($this->httpStatus !== 400) {
            return false;
        }

        $message = strtolower($this->getMessage());

        return str_contains($message, 'profile with this name already exists');
    }

    public function duplicateProfileUserMessage(): string
    {
        return 'This Zernio account already has a profile for you—usually because another app uses the same API key. Link this app to that profile to connect social accounts here. If you use multiple apps, refresh social settings in each app after linking so connection status stays in sync.';
    }

    public function userMessage(): string
    {
        if ($this->isPaymentRequired()) {
            return 'Social account connections are not available right now. Please try again later or contact support.';
        }

        if ($this->isDuplicateProfileError()) {
            return $this->duplicateProfileUserMessage();
        }

        return 'Could not connect this account right now. Please try again later or contact support.';
    }
}
