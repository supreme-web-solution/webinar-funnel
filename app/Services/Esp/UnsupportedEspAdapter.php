<?php

namespace App\Services\Esp;

class UnsupportedEspAdapter implements EspProviderAdapter
{
    public function __construct(
        private readonly string $provider
    ) {}

    public function subscribe(array $payload, array $credentials, array $config): array
    {
        return [
            'ok' => false,
            'message' => "Provider '{$this->provider}' is not currently supported.",
        ];
    }

    public function testConnection(array $credentials): array
    {
        return [
            'ok' => false,
            'message' => "Provider '{$this->provider}' is not currently supported.",
        ];
    }
}
