<?php

namespace Tests\Unit\Services;

use App\Services\Esp\GenericWebhookEspAdapter;
use Tests\TestCase;

class GenericWebhookEspAdapterTest extends TestCase
{
    public function test_rejects_private_webhook_url_before_request_is_sent(): void
    {
        $adapter = new GenericWebhookEspAdapter();

        $result = $adapter->subscribe(
            ['email' => 'lead@example.com'],
            ['webhook_url' => 'http://127.0.0.1:9000/hook'],
            []
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not allowed', strtolower($result['message']));
    }
}
