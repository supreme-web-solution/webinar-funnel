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

        return $adapter->subscribe(
            $leadPayload,
            $integrationAccount->credentials ?? [],
            $providerConfig
        );
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
            'mailchimp'       => app(MailchimpEspAdapter::class),
            'getresponse'     => app(GetResponseEspAdapter::class),
            'convertkit'      => app(ConvertKitEspAdapter::class),
            'activecampaign'  => app(ActiveCampaignEspAdapter::class),
            'sendinblue',
            'brevo'           => app(BrevoEspAdapter::class),
            default           => app(GenericWebhookEspAdapter::class),
        };
    }
}
