<?php

namespace App\Services\Funnel;

use App\Models\Funnel;

class FunnelPaidTrafficAssetsService
{
    private const PREVIEW_COUNT = 3;

    /**
     * @return array<string, mixed>|null
     */
    public function resolveForFunnel(Funnel $funnel): ?array
    {
        $funnel->loadMissing('template');

        $sortOrder = (int) ($funnel->template?->sort_order ?? 0);
        if ($sortOrder < 1 || $sortOrder > 51) {
            return null;
        }

        $previewIndex = (($sortOrder - 1) % self::PREVIEW_COUNT) + 1;
        $posterUrl = $this->previewUrl($previewIndex);

        if ($posterUrl === null) {
            return null;
        }

        return [
            'template_index' => $sortOrder,
            'drive_url' => $funnel->template?->paid_traffic_drive_url,
            'poster_url' => $posterUrl,
        ];
    }

    private function previewUrl(int $index): ?string
    {
        if ($index < 1 || $index > self::PREVIEW_COUNT) {
            return null;
        }

        $path = public_path('funnel-files/previews/preview-'.$index.'.png');

        return is_file($path) ? '/funnel-files/previews/preview-'.$index.'.png' : null;
    }
}
