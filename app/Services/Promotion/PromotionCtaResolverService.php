<?php

namespace App\Services\Promotion;

use App\Models\Funnel;

class PromotionCtaResolverService
{
    /**
     * Default CTA for promotion posts: the funnel opt-in page so social content drives registrations.
     * Users may override cta_url when creating or editing a post.
     *
     * @return array{url: string|null, label: string|null}
     */
    public function resolve(Funnel $funnel): array
    {
        $funnel->loadMissing('user');
        $settings = $funnel->settings;

        $url = $funnel->publicOptinUrl();

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
}
