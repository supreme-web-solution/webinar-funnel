<?php

namespace Tests\Unit\Services;

use App\Services\Esp\ConvertKitEspAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConvertKitEspAdapterTest extends TestCase
{
    public function test_v4_key_creates_subscriber_then_adds_them_to_the_form(): void
    {
        Http::fake([
            'https://api.kit.com/v4/subscribers' => Http::response(['subscriber' => ['id' => 1]], 201),
            'https://api.kit.com/v4/forms/42/subscribers/1' => Http::response(['subscriber' => ['id' => 1]], 201),
        ]);

        $result = (new ConvertKitEspAdapter())->subscribe(
            ['email' => 'lead@example.com', 'name' => 'Ada Lovelace'],
            ['api_key' => 'kit_live_test_key', 'form_id' => '42'],
            [],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('Subscribed to Kit form 42.', $result['message']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.kit.com/v4/subscribers'
                && $request->hasHeader('X-Kit-Api-Key', 'kit_live_test_key')
                && $request['email_address'] === 'lead@example.com'
                && $request['first_name'] === 'Ada'
                && $request['state'] === 'active';
        });

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.kit.com/v4/forms/42/subscribers/1'
                && $request->method() === 'POST';
        });

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api.convertkit.com/v2'));
    }

    public function test_legacy_api_secret_uses_v3_subscribe(): void
    {
        Http::fake([
            'https://api.convertkit.com/v3/forms/7/subscribe' => Http::response(['subscription' => ['id' => 9]], 200),
        ]);

        $result = (new ConvertKitEspAdapter())->subscribe(
            ['email' => 'lead@example.com', 'name' => 'Grace'],
            ['api_secret' => 'v3-secret-value', 'form_id' => '7'],
            [],
        );

        $this->assertTrue($result['ok']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.convertkit.com/v3/forms/7/subscribe'
                && $request['api_secret'] === 'v3-secret-value'
                && $request['email'] === 'lead@example.com';
        });
    }

    public function test_v4_connection_reads_the_account_name(): void
    {
        Http::fake([
            'https://api.kit.com/v4/account' => Http::response(['account' => ['name' => 'MoreMito']], 200),
        ]);

        $result = (new ConvertKitEspAdapter())->testConnection([
            'api_key' => 'kit_live_test_key',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('Connected: MoreMito', $result['message']);
    }

    public function test_v4_error_message_is_returned(): void
    {
        Http::fake([
            'https://api.kit.com/v4/subscribers' => Http::response(['errors' => ['The access token is invalid']], 401),
        ]);

        $result = (new ConvertKitEspAdapter())->subscribe(
            ['email' => 'lead@example.com'],
            ['api_secret' => 'kit_wrong_key', 'form_id' => '42'],
            [],
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('The access token is invalid', $result['message']);
        $this->assertSame(401, $result['status']);
    }
}
