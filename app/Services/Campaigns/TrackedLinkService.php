<?php

namespace App\Services\Campaigns;

use App\Models\Campaign;
use App\Models\TrackedLink;
use App\Models\TrackedLinkClick;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrackedLinkService
{
    public function createForCampaign(
        Campaign $campaign,
        string $destinationUrl,
        ?string $label = null,
        ?int $funnelId = null,
        ?array $geoRules = null,
        ?array $deviceRules = null,
    ): TrackedLink {
        return TrackedLink::query()->create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'funnel_id' => $funnelId,
            'code' => $this->uniqueCode(),
            'label' => $label,
            'destination_url' => $destinationUrl,
            'geo_rules' => $geoRules,
            'device_rules' => $deviceRules,
            'is_active' => true,
        ]);
    }

    public function createForUser(
        int $userId,
        string $destinationUrl,
        ?string $label = null,
        ?array $geoRules = null,
        ?array $deviceRules = null,
        ?int $campaignId = null,
    ): TrackedLink {
        return TrackedLink::query()->create([
            'user_id' => $userId,
            'campaign_id' => $campaignId,
            'code' => $this->uniqueCode(),
            'label' => $label,
            'destination_url' => $destinationUrl,
            'geo_rules' => $geoRules,
            'device_rules' => $deviceRules,
            'is_active' => true,
        ]);
    }

    public function resolveRedirect(TrackedLink $link, Request $request): string
    {
        $destination = $link->destination_url;
        $device = $this->detectDevice((string) $request->userAgent());
        $country = strtoupper((string) ($request->header('CF-IPCountry') ?: $request->input('country') ?: ''));

        $geoRules = is_array($link->geo_rules) ? $link->geo_rules : [];
        if ($country !== '' && $geoRules !== [] && isset($geoRules[$country]) && is_string($geoRules[$country]) && $geoRules[$country] !== '') {
            $destination = $geoRules[$country];
        }

        $deviceRules = is_array($link->device_rules) ? $link->device_rules : [];
        if ($deviceRules !== [] && isset($deviceRules[$device]) && is_string($deviceRules[$device]) && $deviceRules[$device] !== '') {
            $destination = $deviceRules[$device];
        }

        TrackedLinkClick::query()->create([
            'tracked_link_id' => $link->id,
            'ip' => $request->ip(),
            'country' => $country !== '' ? substr($country, 0, 2) : null,
            'device' => $device,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'referrer' => Str::limit((string) $request->headers->get('referer'), 500, ''),
            'created_at' => now(),
        ]);

        $link->increment('click_count');

        return $destination;
    }

    protected function uniqueCode(): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (TrackedLink::query()->where('code', $code)->exists());

        return $code;
    }

    protected function detectDevice(string $ua): string
    {
        $ua = strtolower($ua);
        if ($ua === '') {
            return 'desktop';
        }
        if (str_contains($ua, 'bot') || str_contains($ua, 'spider') || str_contains($ua, 'crawl')) {
            return 'bot';
        }
        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }
}
