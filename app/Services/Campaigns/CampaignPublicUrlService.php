<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\CustomDomain;

class CampaignPublicUrlService
{
    public function activeDomain(Campaign $campaign): ?CustomDomain
    {
        return CustomDomain::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', 'active')
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->first();
    }

    public function baseUrl(Campaign $campaign): string
    {
        $domain = $this->activeDomain($campaign);

        if ($domain) {
            return 'https://'.$domain->domain;
        }

        return rtrim((string) config('app.url'), '/');
    }

    public function usesCustomDomain(Campaign $campaign): bool
    {
        return $this->activeDomain($campaign) !== null;
    }

    public function pageUrl(Campaign $campaign, string $username, string $page): string
    {
        $domain = $this->activeDomain($campaign);

        if ($domain) {
            if ($page === 'squeeze') {
                return $this->baseUrl($campaign).'/';
            }

            return $this->baseUrl($campaign).'/p/'.$page;
        }

        return route('public.campaign.page', [
            'username' => $username,
            'slug' => $campaign->slug,
            'page' => $page,
        ]);
    }

    public function optinUrl(Campaign $campaign, string $username): string
    {
        $domain = $this->activeDomain($campaign);

        if ($domain) {
            return $this->baseUrl($campaign).'/campaign-optin';
        }

        return route('public.campaign.optin', [
            'username' => $username,
            'slug' => $campaign->slug,
        ]);
    }

    public function bonusViewerUrl(Campaign $campaign, string $username, string $bonusUuid): string
    {
        $domain = $this->activeDomain($campaign);

        if ($domain) {
            return $this->baseUrl($campaign).'/bonus/'.$bonusUuid;
        }

        return route('public.campaign.bonus.viewer', [
            'username' => $username,
            'slug' => $campaign->slug,
            'bonusUuid' => $bonusUuid,
        ]);
    }
}
