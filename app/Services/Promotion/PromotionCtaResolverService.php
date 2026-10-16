<?php

namespace App\Services\Promotion;

use App\Models\Campaign;
use App\Models\Funnel;
use App\Models\FunnelPromotionPost;
use App\Services\Campaigns\CampaignBuilderService;
use App\Services\Campaigns\CampaignPublicUrlService;

class PromotionCtaResolverService
{
    public function __construct(
        protected CampaignPublicUrlService $publicUrls,
        protected CampaignBuilderService $campaignBuilder,
    ) {}

    /**
     * Default CTA for promotion posts: a real public landing page that drives opt-ins.
     * Campaign traffic-hub funnels are draft workspaces — never use their /{user}/{slug}-traffic URL.
     *
     * @return array{url: string|null, label: string|null}
     */
    public function resolve(Funnel $funnel): array
    {
        $funnel->loadMissing(['user', 'campaign', 'settings']);
        $settings = $funnel->settings;

        $url = $this->resolveLandingUrl($funnel);

        if ($url === null && $settings) {
            $url = $settings->effectiveTrafficAffiliateLink();
        }

        $label = trim((string) ($settings?->webinar_cta_label ?? ''));

        if ($label === '' && $url !== null) {
            $label = 'Sign up';
        }

        return [
            'url' => $url,
            'label' => $label !== '' ? $label : null,
        ];
    }

    /**
     * Refresh a post's CTA when it still points at the internal traffic-hub URL.
     */
    public function ensurePostCta(FunnelPromotionPost $post): FunnelPromotionPost
    {
        $post->loadMissing(['funnel.user', 'funnel.campaign', 'funnel.settings']);
        $funnel = $post->funnel;

        if (! $funnel) {
            return $post;
        }

        if (! $this->ctaNeedsRepair($post, $funnel)) {
            return $post;
        }

        $cta = $this->resolve($funnel);

        if ($cta['url'] === null || $cta['url'] === $post->cta_url) {
            return $post;
        }

        $post->forceFill([
            'cta_url' => $cta['url'],
            'cta_label' => $post->cta_label ?: $cta['label'],
        ])->save();

        return $post;
    }

    protected function resolveLandingUrl(Funnel $funnel): ?string
    {
        $username = $funnel->user?->username;
        if (! is_string($username) || $username === '') {
            return null;
        }

        $campaign = $funnel->campaign;
        $variant = (string) ($funnel->meta['campaign_variant'] ?? '');

        if ($campaign && ($variant === 'traffic' || $funnel->campaign_id)) {
            $campaignLanding = $this->campaignLandingUrl($campaign, $username, $variant);
            if ($campaignLanding !== null) {
                return $campaignLanding;
            }
        }

        return $funnel->publicOptinUrl();
    }

    protected function campaignLandingUrl(Campaign $campaign, string $username, string $variant): ?string
    {
        if ($variant === 'traffic' || $variant === '') {
            if ($campaign->type === Campaign::TYPE_WEBINAR) {
                $registration = $this->campaignBuilder->primaryOptinFunnel($campaign);
                if ($registration && is_string($registration->slug) && $registration->slug !== '') {
                    $registration->loadMissing('user');

                    return $registration->publicOptinUrl()
                        ?? $this->publicUrls->pageUrl($campaign, $username, 'squeeze');
                }
            }

            return $this->publicUrls->pageUrl($campaign, $username, 'squeeze');
        }

        if (in_array($variant, ['webinar', 'webinar_optin'], true)) {
            return $this->publicUrls->pageUrl($campaign, $username, 'squeeze');
        }

        return null;
    }

    protected function ctaNeedsRepair(FunnelPromotionPost $post, Funnel $funnel): bool
    {
        $cta = trim((string) ($post->cta_url ?? ''));
        $variant = (string) ($funnel->meta['campaign_variant'] ?? '');

        if ($cta === '') {
            return $variant === 'traffic' || $funnel->campaign_id !== null;
        }

        if ($variant === 'traffic' && is_string($funnel->slug) && str_contains($cta, '/'.$funnel->slug)) {
            return true;
        }

        return str_ends_with(rtrim(parse_url($cta, PHP_URL_PATH) ?: '', '/'), '-traffic');
    }
}
