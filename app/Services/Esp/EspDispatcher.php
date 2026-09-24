<?php

namespace App\Services\Esp;

use App\Models\IntegrationAccount;

class EspDispatcher
{
    /**
     * Subscribe a lead via the integration account's ESP adapter.
     *
     * @param  array<string, mixed>  $leadPayload
     * @param  array<string, mixed>  $providerConfig
     * @return array{ok: bool, message: string, status?: int}
     */
    public function dispatch(
        IntegrationAccount $integrationAccount,
        array $leadPayload,
        array $providerConfig = []
    ): array {
        $adapter = $this->adapterFor($integrationAccount->provider);
        $credentials = is_array($integrationAccount->credentials) ? $integrationAccount->credentials : [];
        $credentials = $this->mergeListOverride($credentials, $providerConfig);

        return $adapter->subscribe(
            $leadPayload,
            $credentials,
            $providerConfig
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $providerConfig
     * @return array<string, mixed>
     */
    protected function mergeListOverride(array $credentials, array $providerConfig): array
    {
        $listId = trim((string) ($providerConfig['list_id'] ?? $providerConfig['audience_id'] ?? ''));
        if ($listId === '') {
            return $credentials;
        }

        $credentials['list_id'] = $listId;
        $credentials['audience_id'] = $listId;

        return $credentials;
    }

    /**
     * Ping the provider to verify the stored credentials are still valid.
     *
     * @return array{ok: bool, message: string}
     */
    public function testConnection(IntegrationAccount $integrationAccount): array
    {
        $adapter = $this->adapterFor($integrationAccount->provider);

        return $adapter->testConnection($integrationAccount->credentials ?? []);
    }

    private function adapterFor(string $provider): EspProviderAdapter
    {
        return match ($provider) {
            'mailchimp' => app(MailchimpEspAdapter::class),
            'getresponse' => app(GetResponseEspAdapter::class),
            'convertkit' => app(ConvertKitEspAdapter::class),
            'activecampaign' => app(ActiveCampaignEspAdapter::class),
            'sendinblue',
            'brevo' => app(BrevoEspAdapter::class),
            'generic_webhook' => app(GenericWebhookEspAdapter::class),
            default => new UnsupportedEspAdapter($provider),
        };
    }
}
