<?php

namespace App\Services\Ads;

/**
 * Turns Zernio / Meta API errors into messages users can act on.
 */
final class AdLaunchErrorFormatter
{
    /**
     * @return array{title: string, message: string, action: string|null}
     */
    public static function format(string $raw): array
    {
        $clean = self::stripApiNoise($raw);

        if (preg_match('/budget must be more than\s+(.+?)(?:\s+or your ads|\s*$)/i', $clean, $matches)) {
            $minimum = trim(rtrim($matches[1], '.'));
            $ngnHint = str_starts_with(strtoupper($minimum), 'NGN')
                ? 'Edit the campaign, set currency to NGN, and enter at least '.$minimum.' per day.'
                : 'Edit the campaign and increase the daily budget to at least '.$minimum.' (in your ad account currency), then click Retry.';

            return [
                'title' => 'Daily budget is too low',
                'message' => "Facebook requires a higher daily budget for this ad account. The minimum is {$minimum}.",
                'action' => $ngnHint,
            ];
        }

        if (str_contains(strtolower($clean), 'conversion tracking source')) {
            return [
                'title' => 'Meta Pixel required',
                'message' => 'This campaign objective needs a Meta Pixel (conversion tracking) on your ad account, but none was configured.',
                'action' => 'Edit the campaign: switch goal to Drive Traffic for opt-in page ads, or add your Meta Pixel ID under Conversions / Sales.',
            ];
        }

        if (str_contains(strtolower($clean), 'please select a pixel') || str_contains(strtolower($clean), 'tiktok pixel')) {
            return [
                'title' => 'TikTok Pixel required',
                'message' => 'TikTok conversion ads need a TikTok Pixel ID on the campaign.',
                'action' => 'Add your pixel ID in campaign settings, or use Drive Traffic on Meta/Google for image ads.',
            ];
        }

        if (str_contains(strtolower($clean), 'standalone ads') || str_contains(strtolower($clean), 'cannot be launched via zernio')) {
            return [
                'title' => 'Platform not supported',
                'message' => $clean,
                'action' => 'Use Facebook, Instagram, TikTok, Google, X, LinkedIn, or Pinterest. Reddit/YouTube need separate boost flows.',
            ];
        }

        if (str_contains(strtolower($clean), 'promoted object') || str_contains(strtolower($clean), 'pixel')) {
            return [
                'title' => 'Conversion setup incomplete',
                'message' => $clean,
                'action' => 'For webinar opt-in ads, use Drive Traffic. For sales/conversion ads, add a Meta Pixel ID in campaign settings.',
            ];
        }

        if (str_contains(strtolower($clean), 'linkurl is required')) {
            return [
                'title' => 'Missing destination link',
                'message' => 'Every ad needs a web page for people to visit when they click.',
                'action' => 'Add a destination URL in the campaign wizard, or make sure your funnel has a public opt-in page.',
            ];
        }

        if (str_contains(strtolower($clean), 'no facebook account connected')) {
            return [
                'title' => 'Facebook not connected',
                'message' => 'No Facebook page is connected in Zernio for your account.',
                'action' => 'Go to Settings → Social posting and connect Facebook, then retry.',
            ];
        }

        if (str_contains(strtolower($clean), 'payment') || str_contains(strtolower($clean), 'billing')) {
            return [
                'title' => 'Billing issue',
                'message' => $clean,
                'action' => 'Check that your Meta ad account has a valid payment method and your Zernio account is in good standing.',
            ];
        }

        if (self::isMetaAdsManagerVerification($clean)) {
            return [
                'title' => 'Meta API security check (not an app bug)',
                'message' => 'Meta is blocking ad creation via API (Zernio). Your Ads Manager may look fine when browsing, but programmatic creates stay blocked until Meta verifies the Facebook profile that authorized the connection.',
                'action' => '1) In Ads Manager, open any ad → make a tiny edit → click Publish until you see "Start Authentication" and complete email/SMS verification. 2) Confirm the person who connected Facebook in Settings is an Admin on the billing ad account in Business Manager. 3) Wait 5 minutes, disconnect/reconnect Facebook in Settings → Social posting, then Retry.',
            ];
        }

        if (self::isOAuthOrTokenError($clean)) {
            return [
                'title' => 'Account connection expired',
                'message' => 'The Facebook / Zernio connection token is invalid or expired.',
                'action' => 'Go to Settings → Social posting, disconnect and reconnect Facebook, then retry.',
            ];
        }

        return [
            'title' => 'Ad launch failed',
            'message' => $clean !== '' ? $clean : 'Something went wrong while publishing the ad.',
            'action' => 'Fix the issue below and click Retry. Contact support if this keeps happening.',
        ];
    }

    /**
     * @param  list<array{headline: string|null, raw: string}>  $failures
     * @return array{summary: string, primary: array{title: string, message: string, action: string|null}, items: list<array{headline: string, title: string, message: string, action: string|null}>}
     */
    public static function summarizeFailures(array $failures): array
    {
        $items = [];
        $seen = [];

        foreach ($failures as $failure) {
            $formatted = self::format($failure['raw']);
            $key = $formatted['title'].'|'.$formatted['message'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $items[] = [
                'headline' => $failure['headline'] ?: 'Creative',
                'title' => $formatted['title'],
                'message' => $formatted['message'],
                'action' => $formatted['action'],
                'raw' => $failure['raw'] ?? '',
            ];
        }

        $primary = $items[0] ?? self::format('All creative launches failed.');

        return [
            'summary' => $primary['message'],
            'primary' => [
                'title' => $primary['title'],
                'message' => $primary['message'],
                'action' => $primary['action'],
            ],
            'items' => $items,
        ];
    }

    private static function stripApiNoise(string $raw): string
    {
        $clean = trim($raw);

        $clean = preg_replace('/^(Meta Ads API error \(\d+\):\s*|Zernio API error \(\d+\):\s*)/i', '', $clean) ?? $clean;

        return trim($clean);
    }

    private static function isMetaAdsManagerVerification(string $clean): bool
    {
        $lower = strtolower($clean);

        return str_contains($lower, 'tried to access your account without permission')
            || str_contains($lower, 'authenticate your account in ads manager')
            || str_contains($lower, 'without permission. for your protection');
    }

    private static function isOAuthOrTokenError(string $clean): bool
    {
        $lower = strtolower($clean);

        if (self::isMetaAdsManagerVerification($clean)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(oauth|access token|token expired|invalid token|session has expired|error validating access token|revoked|not authorized)\b/i',
            $lower
        );
    }
}
