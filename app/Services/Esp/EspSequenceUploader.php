<?php

namespace App\Services\Esp;

use App\Models\Campaign;
use App\Models\CampaignEmail;
use App\Models\IntegrationAccount;

class EspSequenceUploader
{
    /**
     * @return array{ok: bool, message: string, uploaded?: int, details?: array<int, string>}
     */
    public function uploadForCampaign(Campaign $campaign, IntegrationAccount $account, ?string $tag = null): array
    {
        $emails = $campaign->emails()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (CampaignEmail $email) => [
                'subject' => $email->subject,
                'body' => $email->body,
                'sequence_key' => $email->sequence_key,
                'sort_order' => $email->sort_order,
            ])
            ->all();

        if ($emails === []) {
            return ['ok' => false, 'message' => 'No email swipes to upload — generate emails first.'];
        }

        $adapter = $this->sequenceAdapterFor($account->provider);

        if ($adapter === null) {
            return [
                'ok' => false,
                'message' => "Sequence upload is not supported for {$account->provider}. Use Mailchimp or Generic Webhook.",
            ];
        }

        return $adapter->uploadSequence(
            $emails,
            $account->credentials ?? [],
            [
                'tag' => $tag,
                'campaign_name' => $campaign->name,
                'campaign_uuid' => $campaign->uuid,
                'affiliate_link' => $campaign->affiliate_link,
                'provider' => $account->provider,
            ],
        );
    }

    protected function sequenceAdapterFor(string $provider): ?EspSequenceAdapter
    {
        return match ($provider) {
            'mailchimp' => app(MailchimpEspAdapter::class),
            'generic_webhook' => app(GenericWebhookEspAdapter::class),
            'getresponse' => app(GetResponseEspAdapter::class),
            'convertkit' => app(ConvertKitEspAdapter::class),
            'sendinblue', 'brevo' => app(BrevoEspAdapter::class),
            default => null,
        };
    }
}
