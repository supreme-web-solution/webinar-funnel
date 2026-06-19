<?php

namespace App\Services\Ads;

use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;

/**
 * Builds Zernio POST /v1/ads/create payloads per platform (docs-compliant).
 */
final class AdLaunchPayloadBuilder
{
    /**
     * @return array{goal: string, errors: list<string>}
     */
    public function resolveLaunchGoal(FunnelAdCampaign $campaign, string $platform, string $linkUrl): array
    {
        $errors = [];
        $goal = $this->mapAppGoal($campaign->goal);

        if (AdPlatformRules::isMeta($platform) && $linkUrl !== '') {
            $goal = match ($goal) {
                'engagement', 'lead_generation' => 'traffic',
                default => $goal,
            };
        }

        if ($platform === 'linkedin') {
            if (! in_array($goal, AdPlatformRules::LINKEDIN_GOALS, true)) {
                if ($linkUrl !== '' && in_array($goal, ['lead_generation', 'engagement', 'conversions'], true)) {
                    $goal = 'traffic';
                } else {
                    $errors[] = 'LinkedIn standalone ads support Engagement, Traffic, Awareness, and Video Views only. Use Drive Traffic for opt-in page ads.';
                }
            }
            if ($goal === 'traffic' && $linkUrl === '') {
                $errors[] = 'LinkedIn traffic ads require a destination URL.';
            }
        }

        if ($platform === 'tiktok' && $goal === 'conversions') {
            $pixelId = $this->pixelId($campaign);
            if ($pixelId === '') {
                $errors[] = 'TikTok conversion ads require a TikTok Pixel ID in campaign settings.';
            }
        }

        if (AdPlatformRules::isMeta($platform) && in_array($goal, ['conversions', 'lead_conversion'], true)) {
            if ($this->pixelId($campaign) === '') {
                $errors[] = 'Meta conversion ads require a Meta Pixel ID in campaign settings.';
            }
        }

        if (AdPlatformRules::isMeta($platform) && $goal === 'lead_generation' && $linkUrl !== '') {
            $errors[] = 'Meta Lead Generation uses instant forms, not a website URL. Use Drive Traffic for opt-in pages, or remove the destination URL.';
        }

        if (in_array($goal, ['conversions', 'lead_conversion'], true) && ! AdPlatformRules::isMeta($platform) && $platform !== 'tiktok') {
            if ($linkUrl !== '') {
                $goal = 'traffic';
            } else {
                $errors[] = AdPlatformRules::platformLabel($platform).' does not support conversion tracking via standalone create. Use Drive Traffic.';
            }
        }

        return ['goal' => $goal, 'errors' => $errors];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        FunnelAdCampaign $campaign,
        FunnelAdCreative $creative,
        string $platform,
        string $socialAccountId,
        string $adAccountId,
        string $linkUrl,
        string $launchGoal,
    ): array {
        $targeting = is_array($campaign->targeting) ? $campaign->targeting : [];
        $currency = AdBudgetRules::normalizeCurrency($campaign->budget_currency);

        $base = [
            'accountId' => $socialAccountId,
            'adAccountId' => $adAccountId,
            'name' => $campaign->name.' — '.($creative->headline ?: 'Creative '.$creative->id),
            'goal' => $launchGoal,
            'budgetAmount' => (float) $campaign->budget_amount,
            'budgetType' => $campaign->budget_type ?? 'daily',
            'currency' => $currency,
            'linkUrl' => $linkUrl !== '' ? $linkUrl : null,
            'countries' => $targeting['countries'] ?? ['US'],
            'ageMin' => $targeting['age_min'] ?? null,
            'ageMax' => $targeting['age_max'] ?? null,
            'startDate' => $campaign->start_date?->toIso8601String(),
            'endDate' => $this->endDate($campaign),
        ];

        $base = array_merge($base, $this->creativeFields($creative, $platform, $launchGoal));
        $base = array_merge($base, $this->platformExtras($campaign, $platform, $launchGoal, $creative, $linkUrl));

        return array_filter(
            $base,
            fn ($value) => $value !== null && $value !== '' && $value !== []
        );
    }

    /**
     * @return list<string>
     */
    public function validateCreativeForPlatform(FunnelAdCreative $creative, string $platform, string $launchGoal): array
    {
        $errors = [];
        $hasImage = trim((string) ($creative->asset_url ?? '')) !== '';

        if ($platform === 'linkedin' && ! $hasImage) {
            $errors[] = 'LinkedIn requires an image creative (or video). Generate an image first.';
        }

        if ($platform === 'pinterest' && ! $hasImage) {
            $errors[] = 'Pinterest requires an image creative.';
        }

        if ($platform === 'tiktok') {
            $errors[] = 'TikTok standalone ads require video. Image-only creatives are not supported yet — use Meta or Google for image ads.';
        }

        if ($platform === 'google' && ! $hasImage) {
            $errors[] = 'Google Display ads require image assets. Generate a square or landscape image first.';
        }

        if ($platform === 'x' && trim((string) ($creative->primary_text ?? '')) === '') {
            $errors[] = 'X / Twitter ads require post text (primary text).';
        }

        if (in_array($platform, ['facebook', 'instagram', 'google', 'pinterest', 'linkedin'], true)
            && trim((string) ($creative->headline ?? '')) === ''
            && $platform !== 'x') {
            $errors[] = 'A headline is required for '.AdPlatformRules::platformLabel($platform).' ads.';
        }

        if (trim((string) ($creative->primary_text ?? '')) === '' && ! in_array($platform, ['google'], true)) {
            $errors[] = 'Primary text / body is required.';
        }

        return $errors;
    }

    private function mapAppGoal(string $goal): string
    {
        return match ($goal) {
            'lead_generation' => 'lead_generation',
            'conversions' => 'conversions',
            'awareness' => 'awareness',
            'engagement' => 'engagement',
            default => 'traffic',
        };
    }

  /**
     * @return array<string, mixed>
     */
    private function creativeFields(FunnelAdCreative $creative, string $platform, string $launchGoal): array
    {
        $headline = $this->truncate((string) ($creative->headline ?? ''), match ($platform) {
            'google' => 30,
            'pinterest' => 100,
            'linkedin' => 400,
            default => 255,
        });

        $body = $this->truncate((string) ($creative->primary_text ?? ''), match ($platform) {
            'google' => 90,
            'pinterest' => 500,
            'x' => 256,
            default => 2000,
        });

        if ($platform === 'x') {
            return [
                'body' => $body,
            ];
        }

        $fields = [
            'headline' => $headline !== '' ? $headline : null,
            'body' => $body !== '' ? $body : null,
        ];

        if (AdPlatformRules::isMeta($platform)) {
            $fields['description'] = $this->truncate((string) ($creative->description ?? ''), 255) ?: null;
            $fields['callToAction'] = $this->mapCta((string) $creative->cta_button);
        }

        if (in_array($platform, ['tiktok', 'linkedin'], true)) {
            $fields['callToAction'] = $this->mapCta((string) $creative->cta_button);
        }

        $imageUrl = trim((string) ($creative->asset_url ?? ''));
        if ($imageUrl !== '' && $platform !== 'x') {
            if ($platform === 'google') {
                $fields['campaignType'] = 'display';
                $fields['images'] = [
                    'landscape' => $imageUrl,
                    'square' => $imageUrl,
                ];
            } else {
                $fields['imageUrl'] = $imageUrl;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function platformExtras(
        FunnelAdCampaign $campaign,
        string $platform,
        string $launchGoal,
        FunnelAdCreative $creative,
        string $linkUrl,
    ): array {
        $extras = [];

        if (AdPlatformRules::isMeta($platform)) {
            $extras['advantageAudience'] = 0;
            $optimization = match ($launchGoal) {
                'traffic' => 'LINK_CLICKS',
                'awareness' => 'REACH',
                'engagement' => 'POST_ENGAGEMENT',
                default => null,
            };
            if ($optimization !== null) {
                $extras['optimizationGoal'] = $optimization;
            }
            $promoted = $this->buildPromotedObject($campaign, $launchGoal, $platform);
            if ($promoted !== null) {
                $extras['promotedObject'] = $promoted;
            }
        }

        if ($platform === 'tiktok') {
            $promoted = $this->buildPromotedObject($campaign, $launchGoal, $platform);
            if ($promoted !== null) {
                $extras['promotedObject'] = $promoted;
            }
        }

        if ($platform === 'linkedin' && $linkUrl !== '') {
            $extras['callToAction'] = $extras['callToAction'] ?? $this->mapLinkedInCta((string) $creative->cta_button);
        }

        $targeting = is_array($campaign->targeting) ? $campaign->targeting : [];
        if (! empty($targeting['interests']) && is_array($targeting['interests'])) {
            $interests = array_values(array_filter(array_map(function ($interest) {
                if (is_array($interest) && isset($interest['id'], $interest['name'])) {
                    return ['id' => (string) $interest['id'], 'name' => (string) $interest['name']];
                }

                return null;
            }, $targeting['interests'])));

            if ($interests !== []) {
                $extras['interests'] = $interests;
            }
        }

        return $extras;
    }

    /**
     * @return array<string, string>|null
     */
    private function buildPromotedObject(FunnelAdCampaign $campaign, string $launchGoal, string $platform): ?array
    {
        if ($launchGoal !== 'conversions' && $launchGoal !== 'lead_conversion') {
            return null;
        }

        $pixelId = $this->pixelId($campaign);
        if ($pixelId === '') {
            return null;
        }

        if ($platform === 'tiktok') {
            $event = strtoupper(trim((string) (
                $campaign->meta_conversion_event ?? 'ON_WEB_REGISTER'
            )));

            return array_filter([
                'pixelId' => $pixelId,
                'customEventType' => $event !== '' ? $event : 'ON_WEB_REGISTER',
            ]);
        }

        if (! AdPlatformRules::isMeta($platform)) {
            return null;
        }

        $defaultEvent = $launchGoal === 'conversions' ? 'PURCHASE' : 'LEAD';
        $eventType = strtoupper(trim((string) (
            $campaign->meta_conversion_event
            ?? config('promotion.ads.default_meta_conversion_event', $defaultEvent)
        )));

        return [
            'pixelId' => $pixelId,
            'customEventType' => $eventType !== '' ? $eventType : $defaultEvent,
        ];
    }

    private function pixelId(FunnelAdCampaign $campaign): string
    {
        return trim((string) ($campaign->meta_pixel_id ?? config('promotion.ads.default_meta_pixel_id', '')));
    }

    private function endDate(FunnelAdCampaign $campaign): ?string
    {
        if ($campaign->budget_type === 'lifetime') {
            return $campaign->end_date?->toDateString() ?? $campaign->end_date?->toIso8601String();
        }

        return $campaign->end_date?->toDateString();
    }

    private function mapCta(string $cta): string
    {
        $cta = strtoupper(trim($cta));

        return match ($cta) {
            'BOOK_NOW' => 'LEARN_MORE',
            'WATCH_NOW' => 'WATCH_MORE',
            default => in_array($cta, array_keys(FunnelAdCreative::CTA_BUTTONS), true) ? $cta : 'LEARN_MORE',
        };
    }

    private function mapLinkedInCta(string $cta): string
    {
        $cta = $this->mapCta($cta);
        $allowed = ['LEARN_MORE', 'SIGN_UP', 'DOWNLOAD', 'SUBSCRIBE', 'REGISTER', 'JOIN', 'ATTEND', 'REQUEST_DEMO', 'VIEW_QUOTE', 'APPLY', 'SEE_MORE', 'SHOP_NOW', 'BUY_NOW'];

        return in_array($cta, $allowed, true) ? $cta : 'LEARN_MORE';
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, max(0, $max - 1)).'…';
    }
}
