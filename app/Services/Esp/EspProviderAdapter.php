<?php

namespace App\Services\Esp;

interface EspProviderAdapter
{
    /**
     * Subscribe a lead to the ESP list.
     *
     * @param  array<string, mixed>  $payload      Lead data: email, name
     * @param  array<string, mixed>  $credentials  Provider credentials (api_key, list_id, …)
     * @param  array<string, mixed>  $config       Extra per-funnel config (unused for now)
     * @return array{ok: bool, message: string, status?: int}
     */
    public function subscribe(array $payload, array $credentials, array $config): array;

    /**
     * Verify credentials are valid (lightweight ping / account fetch).
     *
     * @param  array<string, mixed>  $credentials
     * @return array{ok: bool, message: string}
     */
    public function testConnection(array $credentials): array;
}
