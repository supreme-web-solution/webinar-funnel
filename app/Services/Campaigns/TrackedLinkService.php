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
        $destinationUrl = $this->requireUsableDestination($destinationUrl);

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
        $destinationUrl = $this->requireUsableDestination($destinationUrl);

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

    public function requireUsableDestination(string $destinationUrl): string
    {
        $seen = [];
        $destinationUrl = $this->unwrapInternalTrackedUrl($destinationUrl, $seen);
        if (! $this->isUsableDestination($destinationUrl)) {
            throw new \InvalidArgumentException('Tracked links need a full https destination URL (not # or another /r/ short link).');
        }

        return $destinationUrl;
    }

    public function resolveRedirect(TrackedLink $link, Request $request): string
    {
        $destination = $this->effectiveDestination($link);
        $device = $this->detectDevice((string) $request->userAgent());
        $country = strtoupper((string) ($request->header('CF-IPCountry') ?: $request->input('country') ?: ''));

        $geoRules = is_array($link->geo_rules) ? $link->geo_rules : [];
        if ($country !== '' && $geoRules !== [] && isset($geoRules[$country]) && is_string($geoRules[$country]) && $geoRules[$country] !== '') {
            $destination = $this->usableOrKeep($geoRules[$country], $destination);
        }

        $deviceRules = is_array($link->device_rules) ? $link->device_rules : [];
        if ($deviceRules !== [] && isset($deviceRules[$device]) && is_string($deviceRules[$device]) && $deviceRules[$device] !== '') {
            $destination = $this->usableOrKeep($deviceRules[$device], $destination);
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

    /**
     * Resolve a destination that will not loop back onto /r/{code}.
     * Repairs broken rows whose destination was saved as "#" or another tracked URL.
     */
    public function effectiveDestination(TrackedLink $link): string
    {
        $seen = [$link->code];
        $destination = $this->unwrapInternalTrackedUrl((string) $link->destination_url, $seen);

        if (! $this->isUsableDestination($destination)) {
            $campaign = $link->relationLoaded('campaign')
                ? $link->campaign
                : ($link->campaign_id ? Campaign::query()->find($link->campaign_id) : null);
            $fallback = $this->unwrapInternalTrackedUrl((string) ($campaign?->affiliate_link ?? ''), $seen);
            if ($this->isUsableDestination($fallback)) {
                $destination = $fallback;
            }
        }

        if (! $this->isUsableDestination($destination)) {
            abort(404, 'This tracked link has no valid destination URL.');
        }

        if ($destination !== (string) $link->destination_url) {
            $link->forceFill(['destination_url' => $destination])->save();
        }

        return $destination;
    }

    public function isUsableDestination(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || $url === '#' || str_starts_with($url, '#')) {
            return false;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return false;
        }

        return $this->trackedCodeFromUrl($url) === null;
    }

    /**
     * @param  list<string>  $seenCodes
     */
    public function unwrapInternalTrackedUrl(string $url, array &$seenCodes = []): string
    {
        $url = trim($url);
        $guard = 0;

        while ($guard < 5) {
            $guard++;
            $code = $this->trackedCodeFromUrl($url);
            if ($code === null) {
                return $url;
            }
            if (in_array($code, $seenCodes, true)) {
                return '';
            }
            $seenCodes[] = $code;
            $nested = TrackedLink::query()->where('code', $code)->where('is_active', true)->first();
            if ($nested === null) {
                return '';
            }
            $url = trim((string) $nested->destination_url);
        }

        return '';
    }

    public function trackedCodeFromUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            if (preg_match('#^/r/([a-z0-9]+)/?$#i', $url, $m) === 1) {
                return strtolower($m[1]);
            }

            return null;
        }

        if (preg_match('#^/r/([a-z0-9]+)/?$#i', $path, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    protected function usableOrKeep(string $candidate, string $fallback): string
    {
        $candidate = trim($candidate);
        $seen = [];
        $unwrapped = $this->unwrapInternalTrackedUrl($candidate, $seen);

        return $this->isUsableDestination($unwrapped) ? $unwrapped : $fallback;
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
