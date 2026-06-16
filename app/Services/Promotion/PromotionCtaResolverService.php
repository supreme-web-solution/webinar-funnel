<?php

namespace App\Services\Promotion;

use App\Models\Funnel;

class PromotionCtaResolverService
{
    /**
     * @return array{url: string|null, label: string|null}
     */
    public function resolve(Funnel $funnel): array
    {
        $settings = $funnel->settings;
        if (! $settings) {
            return ['url' => null, 'label' => null];
        }

        $url = $settings->effectiveTrafficAffiliateLink();
        $label = trim((string) ($settings->webinar_cta_label ?? ''));

        if ($label === '' && $url !== null) {
            $label = 'Learn more';
        }

        return [
            'url' => $url,
            'label' => $label !== '' ? $label : null,
        ];
    }
}
