<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignPage;
use App\Models\TrackedLink;

class CampaignLinkResolverService
{
    public const AFFILIATE_LABEL = 'Affiliate offer';

    /**
     * Tracked /r/ URL for the campaign affiliate hop link — updates when campaign.affiliate_link changes.
     */
    public function affiliatePublicUrl(Campaign $campaign): ?string
    {
        $destination = trim((string) ($campaign->affiliate_link ?? ''));

        if ($destination === '') {
            return null;
        }

        $link = TrackedLink::query()->where('campaign_id', $campaign->id)->where('label', self::AFFILIATE_LABEL)->first();

        if (! $link) {
            $link = app(TrackedLinkService::class)->createForCampaign($campaign, $destination, self::AFFILIATE_LABEL);

            return $link->publicUrl();
        }

        if ($link->destination_url !== $destination) {
            $link->update(['destination_url' => $destination, 'is_active' => true]);
        }

        return $link->publicUrl();
    }

    /**
     * Sync all campaign affiliate tracked links after hop link is edited.
     */
    public function syncAffiliateDestination(Campaign $campaign): void
    {
        $destination = trim((string) ($campaign->affiliate_link ?? ''));

        if ($destination === '') {
            return;
        }

        TrackedLink::query()
            ->where('campaign_id', $campaign->id)
            ->where('label', self::AFFILIATE_LABEL)
            ->update(['destination_url' => $destination, 'is_active' => true]);

        $publicUrl = $this->affiliatePublicUrl($campaign->fresh());

        if ($publicUrl === null) {
            return;
        }

        $this->syncPageAffiliateUrls($campaign, $publicUrl);
    }

    protected function syncPageAffiliateUrls(Campaign $campaign, string $publicUrl): void
    {
        $updates = [
            'thankyou' => 'bridge_url',
            'bonus' => 'affiliate_url',
        ];

        foreach ($updates as $pageType => $contentKey) {
            $page = CampaignPage::query()
                ->where('campaign_id', $campaign->id)
                ->where('page_type', $pageType)
                ->first();

            if (! $page || ! is_array($page->content)) {
                continue;
            }

            $content = $page->content;
            if (($content[$contentKey] ?? null) === $publicUrl) {
                continue;
            }

            $content[$contentKey] = $publicUrl;
            $page->update(['content' => $content]);
        }
    }
}
