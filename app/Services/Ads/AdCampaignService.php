<?php

namespace App\Services\Ads;

use App\Models\FunnelAdCampaign;
use App\Models\FunnelAdCreative;
use App\Services\Zernio\ZernioApiException;
use App\Services\Zernio\ZernioClient;
use Illuminate\Support\Facades\Log;

final class AdCampaignService
{
    public function __construct(
        private readonly ZernioClient $zernio,
        private readonly AdAccountResolver $accounts,
        private readonly AdLaunchPayloadBuilder $payloads,
    ) {}

    /**
     * Launch all draft creatives as paid ads via Zernio POST /v1/ads/create.
     */
    public function launchCampaign(FunnelAdCampaign $campaign): void
    {
        if (! $this->zernio->isConfigured()) {
            $campaign->update(['status' => FunnelAdCampaign::STATUS_FAILED, 'last_error' => 'Zernio API not configured.']);

            return;
        }

        $platforms = is_array($campaign->platforms) ? $campaign->platforms : [];
        $launchable = AdPlatformRules::launchableFromSelection($platforms);
        $unsupported = AdPlatformRules::unsupportedInSelection($platforms);

        if ($launchable === []) {
            $labels = array_map(fn (string $p) => AdPlatformRules::platformLabel($p), $unsupported ?: $platforms);
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => 'No launchable platforms selected. Zernio standalone ads support: Facebook, Instagram, TikTok, Google, X, LinkedIn, Pinterest. Reddit and YouTube are not supported via this API.',
            ]);

            return;
        }

        $platformIds = is_array($campaign->platform_ad_account_ids) ? $campaign->platform_ad_account_ids : [];
        $missing = [];
        foreach ($launchable as $platform) {
            if (trim((string) ($platformIds[$platform] ?? '')) === '') {
                $missing[] = AdPlatformRules::platformLabel($platform);
            }
        }
        if ($missing !== []) {
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => 'Missing ad account IDs for: '.implode(', ', $missing).'.',
            ]);

            return;
        }

        $creatives = $campaign->creatives()->where('status', FunnelAdCreative::STATUS_DRAFT)->get();
        if ($creatives->isEmpty()) {
            $campaign->update(['status' => FunnelAdCampaign::STATUS_FAILED, 'last_error' => 'No creatives to launch.']);

            return;
        }

        $linkUrl = $this->resolveDestinationUrl($campaign);
        if ($linkUrl === null) {
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => 'A destination link is required. Add a product URL in the campaign wizard, or ensure your funnel has a public opt-in page.',
            ]);

            return;
        }

        $primaryPlatform = $launchable[0];
        $socialAccountId = $this->accounts->resolveSocialAccountId($campaign, $primaryPlatform);
        if ($socialAccountId === null) {
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => 'No '.AdPlatformRules::platformLabel($primaryPlatform).' account connected. Connect it under Settings → Social posting.',
            ]);

            return;
        }

        $currency = $this->resolveBudgetCurrency($campaign, $primaryPlatform, $socialAccountId);
        $budgetAmount = (float) $campaign->budget_amount;
        if (! AdBudgetRules::isValid($budgetAmount, $currency)) {
            $minimum = AdBudgetRules::formatAmount(AdBudgetRules::minAmount($currency), $currency);
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'budget_currency' => $currency,
                'last_error' => "Daily budget must be at least {$minimum} in your ad account currency ({$currency}).",
                'launch_errors' => AdLaunchErrorFormatter::summarizeFailures([[
                    'headline' => null,
                    'raw' => "Daily budget must be at least {$minimum} ({$currency}).",
                ]]),
            ]);

            return;
        }

        if ($campaign->budget_type === 'lifetime' && $campaign->end_date === null) {
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => 'Lifetime budgets require an end date (per Zernio / Meta API rules).',
            ]);

            return;
        }

        $campaign->update(['status' => FunnelAdCampaign::STATUS_LAUNCHING, 'last_error' => null, 'launch_errors' => null, 'budget_currency' => $currency]);

        $launchedCount = 0;
        $failures = [];

        foreach ($launchable as $platform) {
            $platformSocialId = $this->accounts->resolveSocialAccountId($campaign, $platform);
            if ($platformSocialId === null) {
                foreach ($creatives as $creative) {
                    $failures[] = [
                        'headline' => ($creative->headline ?? 'Creative').' ('.AdPlatformRules::platformLabel($platform).')',
                        'raw' => 'No connected '.AdPlatformRules::platformLabel($platform).' account in Zernio.',
                    ];
                }

                continue;
            }

            $goalResult = $this->payloads->resolveLaunchGoal($campaign, $platform, $linkUrl);
            if ($goalResult['errors'] !== []) {
                foreach ($creatives as $creative) {
                    $failures[] = [
                        'headline' => ($creative->headline ?? 'Creative').' ('.AdPlatformRules::platformLabel($platform).')',
                        'raw' => implode(' ', $goalResult['errors']),
                    ];
                }

                continue;
            }

            foreach ($creatives as $creative) {
                $creativeErrors = $this->payloads->validateCreativeForPlatform($creative, $platform, $goalResult['goal']);
                if ($creativeErrors !== []) {
                    $failures[] = [
                        'headline' => ($creative->headline ?? 'Creative').' ('.AdPlatformRules::platformLabel($platform).')',
                        'raw' => implode(' ', $creativeErrors),
                    ];

                    continue;
                }

                try {
                    $adId = $this->launchCreative(
                        $campaign,
                        $creative,
                        $platform,
                        $platformSocialId,
                        $linkUrl,
                        $goalResult['goal'],
                    );
                    $this->recordPlatformAdId($creative, $platform, $adId);
                    $launchedCount++;
                } catch (\Throwable $e) {
                    $message = $e->getMessage();
                    $platformIds = is_array($campaign->platform_ad_account_ids) ? $campaign->platform_ad_account_ids : [];
                    $adAccountId = $this->accounts->normalizeAdAccountId(
                        $platform,
                        trim((string) ($platformIds[$platform] ?? ''))
                    );
                    $failures[] = [
                        'headline' => ($creative->headline ?? 'Creative').' ('.AdPlatformRules::platformLabel($platform).')',
                        'raw' => $message,
                    ];
                    Log::warning('[Ads] Creative launch failed', [
                        'creative_id' => $creative->id,
                        'platform' => $platform,
                        'zernio_account_id' => $platformSocialId,
                        'ad_account_id' => $adAccountId,
                        'error' => $message,
                    ]);
                }
            }
        }

        if ($unsupported !== []) {
            $labels = implode(', ', array_map(fn (string $p) => AdPlatformRules::platformLabel($p), $unsupported));
            $failures[] = [
                'headline' => 'Skipped platforms',
                'raw' => "{$labels} cannot be launched via Zernio standalone ads API. Use a supported platform or boost posts separately.",
            ];
        }

        if ($launchedCount > 0) {
            $partial = $failures !== [] ? AdLaunchErrorFormatter::summarizeFailures($failures) : null;
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_ACTIVE,
                'last_error' => $partial['summary'] ?? null,
                'launch_errors' => $partial,
            ]);
        } else {
            $summary = AdLaunchErrorFormatter::summarizeFailures($failures);
            $campaign->update([
                'status' => FunnelAdCampaign::STATUS_FAILED,
                'last_error' => $summary['summary'],
                'launch_errors' => $summary,
            ]);
        }
    }

    private function launchCreative(
        FunnelAdCampaign $campaign,
        FunnelAdCreative $creative,
        string $platform,
        string $socialAccountId,
        string $linkUrl,
        string $launchGoal,
    ): string {
        $platformIds = is_array($campaign->platform_ad_account_ids) ? $campaign->platform_ad_account_ids : [];
        $adAccountId = $this->accounts->normalizeAdAccountId(
            $platform,
            trim((string) ($platformIds[$platform] ?? ($campaign->zernio_ad_account_id ?? '')))
        );

        if ($adAccountId === '') {
            throw new \RuntimeException('Missing ad account ID for '.AdPlatformRules::platformLabel($platform).'.');
        }

        $payload = $this->payloads->build(
            $campaign,
            $creative,
            $platform,
            $socialAccountId,
            $adAccountId,
            $linkUrl,
            $launchGoal,
        );

        try {
            $result = $this->zernio->createStandaloneAd($payload);
        } catch (ZernioApiException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $adId = $result['ad']['_id'] ?? $result['_id'] ?? $result['adId'] ?? null;

        if (! is_string($adId) || $adId === '') {
            throw new \RuntimeException('Zernio did not return an ad ID.');
        }

        Log::info('[Ads] Creative launched', [
            'creative_id' => $creative->id,
            'platform' => $platform,
            'zernio_account_id' => $socialAccountId,
            'ad_account_id' => $adAccountId,
            'goal' => $launchGoal,
            'zernio_ad_id' => $adId,
        ]);

        return $adId;
    }

    private function recordPlatformAdId(FunnelAdCreative $creative, string $platform, string $adId): void
    {
        $map = is_array($creative->platform_ad_ids) ? $creative->platform_ad_ids : [];
        $map[$platform] = $adId;

        $creative->update([
            'platform_ad_ids' => $map,
            'zernio_ad_id' => $creative->zernio_ad_id ?? $adId,
            'status' => FunnelAdCreative::STATUS_ACTIVE,
        ]);
    }

    public function syncPerformance(FunnelAdCampaign $campaign): void
    {
        if (! $this->zernio->isConfigured()) {
            return;
        }

        $creatives = $campaign->creatives()
            ->where(function ($query): void {
                $query->whereNotNull('zernio_ad_id')
                    ->orWhereNotNull('platform_ad_ids');
            })
            ->whereIn('status', [FunnelAdCreative::STATUS_ACTIVE, FunnelAdCreative::STATUS_PAUSED, FunnelAdCreative::STATUS_WINNER])
            ->get();

        $aggregated = [
            'spend' => 0.0,
            'impressions' => 0,
            'clicks' => 0,
            'ctr' => 0.0,
            'cpc' => 0.0,
            'cpm' => 0.0,
            'conversions' => 0,
            'roas' => 0.0,
        ];

        foreach ($creatives as $creative) {
            $adIds = $this->creativeAdIds($creative);

            foreach ($adIds as $adId) {
                try {
                    $data = $this->zernio->getAdAnalytics($adId);
                    $metrics = $data['metrics'] ?? $data;

                    if (! is_array($metrics)) {
                        continue;
                    }

                    $aggregated['spend'] += (float) ($metrics['spend'] ?? 0);
                    $aggregated['impressions'] += (int) ($metrics['impressions'] ?? 0);
                    $aggregated['clicks'] += (int) ($metrics['clicks'] ?? 0);
                    $aggregated['conversions'] += (int) ($metrics['conversions'] ?? 0);
                } catch (ZernioApiException $e) {
                    Log::warning('[Ads] Analytics sync failed for creative', [
                        'creative_id' => $creative->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($adIds !== []) {
                try {
                    $data = $this->zernio->getAdAnalytics((string) $adIds[0]);
                    $metrics = $data['metrics'] ?? $data;
                    if (is_array($metrics)) {
                        $creative->update(['performance' => $metrics]);
                    }
                } catch (ZernioApiException) {
                    // logged above
                }
            }
        }

        if ($aggregated['impressions'] > 0) {
            $aggregated['ctr'] = round($aggregated['clicks'] / $aggregated['impressions'] * 100, 2);
            $aggregated['cpm'] = round($aggregated['spend'] / $aggregated['impressions'] * 1000, 2);
        }
        if ($aggregated['clicks'] > 0) {
            $aggregated['cpc'] = round($aggregated['spend'] / $aggregated['clicks'], 4);
        }

        $campaign->update([
            'performance' => $aggregated,
            'last_synced_at' => now(),
        ]);

        $this->flagWinners($campaign);
    }

    public function pauseCreative(FunnelAdCreative $creative): void
    {
        foreach ($this->creativeAdIds($creative) as $adId) {
            try {
                $this->zernio->pauseAd($adId);
            } catch (\Throwable $e) {
                Log::warning('[Ads] Pause failed', ['error' => $e->getMessage()]);
            }
        }
        $creative->update(['status' => FunnelAdCreative::STATUS_PAUSED]);
    }

    public function resumeCreative(FunnelAdCreative $creative): void
    {
        foreach ($this->creativeAdIds($creative) as $adId) {
            try {
                $this->zernio->resumeAd($adId);
            } catch (\Throwable $e) {
                Log::warning('[Ads] Resume failed', ['error' => $e->getMessage()]);
            }
        }
        $creative->update(['status' => FunnelAdCreative::STATUS_ACTIVE]);
    }

    /**
     * @return list<string>
     */
    private function creativeAdIds(FunnelAdCreative $creative): array
    {
        $map = is_array($creative->platform_ad_ids) ? $creative->platform_ad_ids : [];
        $ids = array_values(array_filter(array_map('strval', $map)));

        if ($ids === [] && is_string($creative->zernio_ad_id) && $creative->zernio_ad_id !== '') {
            return [$creative->zernio_ad_id];
        }

        return $ids;
    }

    private function flagWinners(FunnelAdCampaign $campaign): void
    {
        $actives = $campaign->creatives()
            ->whereIn('status', [FunnelAdCreative::STATUS_ACTIVE, FunnelAdCreative::STATUS_WINNER])
            ->get()
            ->filter(fn ($c) => ($c->performance['impressions'] ?? 0) >= 100);

        if ($actives->count() < 2) {
            return;
        }

        $ctrs = $actives->map(fn ($c) => $c->performance['ctr'] ?? 0);
        $median = $ctrs->median();
        $topThreshold = $median * 1.5;

        foreach ($actives as $creative) {
            $ctr = $creative->performance['ctr'] ?? 0;
            if ($ctr >= $topThreshold) {
                $creative->update(['status' => FunnelAdCreative::STATUS_WINNER, 'is_winner' => true]);
            } elseif ($ctr < $median * 0.5) {
                $creative->update(['status' => FunnelAdCreative::STATUS_LOSER]);
            }
        }
    }

    public function resolveDestinationUrl(FunnelAdCampaign $campaign): ?string
    {
        $campaign->loadMissing('funnel.user');

        $productUrl = trim((string) ($campaign->product_url ?? ''));
        if ($productUrl !== '') {
            return $productUrl;
        }

        return $campaign->funnel?->publicOptinUrl();
    }

    private function resolveBudgetCurrency(FunnelAdCampaign $campaign, string $platform, string $socialAccountId): string
    {
        $stored = trim((string) ($campaign->budget_currency ?? ''));
        if ($stored !== '') {
            return AdBudgetRules::normalizeCurrency($stored);
        }

        $platformIds = is_array($campaign->platform_ad_account_ids) ? $campaign->platform_ad_account_ids : [];
        $adAccountId = $this->accounts->normalizeAdAccountId(
            $platform,
            trim((string) ($platformIds[$platform] ?? ($campaign->zernio_ad_account_id ?? '')))
        );

        $resolved = $this->accounts->resolveAdAccountCurrency($socialAccountId, $adAccountId);

        return $resolved !== null
            ? AdBudgetRules::normalizeCurrency($resolved)
            : AdBudgetRules::normalizeCurrency(null);
    }
}
