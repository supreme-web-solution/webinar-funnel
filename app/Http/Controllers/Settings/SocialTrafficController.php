<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Zernio\ZernioApiException;
use App\Services\Zernio\ZernioClient;
use App\Services\Zernio\ZernioProfileManager;
use App\Services\Zernio\ZernioSocialAccountSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialTrafficController extends Controller
{
    /** @var array<string, string> Route slug => Zernio connect platform */
    public const PLATFORM_MAP = [
        'reddit' => 'reddit',
        'youtube' => 'youtube',
        'x' => 'twitter',
        'facebook' => 'facebook',
        'instagram' => 'instagram',
        'tiktok' => 'tiktok',
        'linkedin' => 'linkedin',
        'pinterest' => 'pinterest',
    ];

    /** @var list<string> */
    public const TRAFFIC_REPLY_PLATFORMS = ['reddit', 'youtube', 'x'];

    /** @var list<string> */
    public const POSTING_ADS_PLATFORMS = ['facebook', 'instagram', 'tiktok', 'linkedin', 'pinterest'];

    public function edit(Request $request): Response
    {
        if (app(ZernioClient::class)->isConfigured()) {
            app(ZernioSocialAccountSync::class)->syncForUser($request->user());
        }

        $accounts = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->get(['id', 'platform', 'platform_username', 'zernio_account_id', 'created_at']);

        $appUrl = rtrim((string) config('app.url'), '/');
        $requestOrigin = $request->getSchemeAndHttpHost();

        return Inertia::render('settings/SocialTraffic', [
            'socialAccounts' => $accounts,
            'zernioConfigured' => app(ZernioClient::class)->isConfigured(),
            'oauthCallbackUrl' => $this->oauthCallbackUrl('reddit'),
            'appUrl' => $appUrl,
            'appUrlMismatch' => $appUrl !== '' && $appUrl !== $requestOrigin,
            'requestOrigin' => $requestOrigin,
            'trafficPlatforms' => self::TRAFFIC_REPLY_PLATFORMS,
            'postingPlatforms' => self::POSTING_ADS_PLATFORMS,
            'facebookAdsDiagnostics' => $this->facebookAdsDiagnostics((int) $request->user()->id),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function facebookAdsDiagnostics(int $userId): ?array
    {
        $zernio = app(ZernioClient::class);
        if (! $zernio->isConfigured()) {
            return null;
        }

        $account = SocialAccount::query()
            ->where('user_id', $userId)
            ->where('platform', 'facebook')
            ->whereNotNull('zernio_account_id')
            ->first();

        if (! $account?->zernio_account_id) {
            return null;
        }

        $metaAdsId = null;
        $user = User::query()->find($userId);
        if ($user) {
            try {
                $accounts = app(ZernioProfileManager::class)->withProfile(
                    $user,
                    fn (string $profileId): array => $zernio->listAccountsForProfile($profileId),
                );

                foreach ($accounts as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    if (strtolower(trim((string) ($row['platform'] ?? ''))) === 'metaads') {
                        $metaAdsId = (string) ($row['_id'] ?? $row['id'] ?? '');
                        break;
                    }
                }
            } catch (\Throwable) {
                // optional
            }
        }

        $mapped = [];
        $error = null;

        try {
            foreach ($zernio->listAdAccounts((string) $account->zernio_account_id, null, 15) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? $row['ad_account_id'] ?? $row['account_id'] ?? $row['platformAdAccountId'] ?? '');
                if ($id === '') {
                    continue;
                }
                $name = $row['name'] ?? $row['account_name'] ?? $row['platformAdAccountName'] ?? null;
                $currency = $row['currency'] ?? $row['currencyCode'] ?? null;
                $mapped[] = [
                    'id' => $id,
                    'name' => is_string($name) && $name !== '' ? $name : null,
                    'currency' => is_string($currency) && $currency !== '' ? strtoupper($currency) : null,
                ];
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return [
            'page_name' => $account->platform_username,
            'zernio_page_account_id' => $account->zernio_account_id,
            'has_metaads_token' => is_string($metaAdsId) && $metaAdsId !== '',
            'zernio_metaads_account_id' => $metaAdsId ?: null,
            'billing_ad_accounts' => $mapped,
            'list_error' => $error,
        ];
    }

    public function disconnect(Request $request, SocialAccount $socialAccount): RedirectResponse
    {
        abort_unless((int) $socialAccount->user_id === (int) $request->user()->id, 403);

        $platform = ucfirst($socialAccount->platform === 'twitter' ? 'X' : $socialAccount->platform);
        $zernioAccountId = $socialAccount->zernio_account_id;

        if (is_string($zernioAccountId) && $zernioAccountId !== '') {
            try {
                app(ZernioClient::class)->disconnectAccount($zernioAccountId);
            } catch (\Throwable $e) {
                Log::warning('SocialTrafficController: Zernio disconnect failed', [
                    'account_id' => $zernioAccountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $socialAccount->delete();

        return back()->with('success', "{$platform} disconnected.");
    }

    public function connectRedirect(Request $request, string $platform): RedirectResponse
    {
        $zernioPlatform = self::PLATFORM_MAP[$platform] ?? null;

        if ($zernioPlatform === null) {
            abort(404);
        }

        $zernio = app(ZernioClient::class);

        if (! $zernio->isConfigured()) {
            return back()->withErrors([$platform => 'Zernio is not configured. Set ZERNIO_API_KEY in your environment.']);
        }

        try {
            $redirectUrl = $this->oauthCallbackUrl($platform);
            $connect = app(ZernioProfileManager::class)->withProfile(
                $request->user(),
                fn (string $profileId): array => $zernio->getConnectUrl($zernioPlatform, $profileId, $redirectUrl),
            );

            Log::info('SocialTrafficController: starting Zernio OAuth', [
                'platform' => $platform,
                'zernio_platform' => $zernioPlatform,
                'user_id' => $request->user()->id,
                'callback_url' => $redirectUrl,
            ]);

            return $this->redirectExternal($request, $connect['authUrl']);
        } catch (ZernioApiException $e) {
            return $this->handleConnectFailure($platform, $e);
        } catch (\Throwable $e) {
            Log::error('SocialTrafficController: connect redirect failed', [
                'platform' => $platform,
                'error' => $e->getMessage(),
            ]);

            return $this->authError($platform, 'Could not start the connection right now. Please try again later or contact support.');
        }
    }

    public function zernioCallback(Request $request): RedirectResponse
    {
        Log::info('SocialTrafficController: Zernio OAuth callback', [
            'user_id' => $request->user()?->id,
            'path' => $request->path(),
            'query_keys' => array_keys($request->query()),
            'has_error' => $request->has('error'),
            'has_code' => $request->has('code'),
        ]);

        if ($request->user() === null) {
            Log::warning('SocialTrafficController: Zernio callback without session — use the same host as APP_URL', [
                'app_url' => config('app.url'),
                'request_host' => $request->getSchemeAndHttpHost(),
                'query' => $request->query(),
            ]);

            session(['url.intended' => $this->socialTrafficSettingsUrl()]);

            return redirect()->guest(route('login'));
        }

        if ($request->query('error')) {
            $platform = (string) ($request->query('connected') ?? $request->query('platform') ?? $this->routeSlugFromCallback($request) ?? 'social');

            return $this->authError(
                $platform,
                (string) ($request->query('error_description') ?? $request->query('error') ?? 'Authorization was denied.')
            );
        }

        $routeSlug = $this->routeSlugFromCallback($request);
        $zernioPlatform = $routeSlug !== null ? (self::PLATFORM_MAP[$routeSlug] ?? null) : null;

        $code = $request->query('code');
        $state = $request->query('state');

        if (is_string($code) && $code !== '' && is_string($state) && $state !== '' && is_string($zernioPlatform)) {
            try {
                $result = app(ZernioProfileManager::class)->withProfile(
                    $request->user(),
                    fn (string $profileId): array => app(ZernioClient::class)->completeOAuthConnection(
                        $zernioPlatform,
                        $code,
                        $state,
                        $profileId,
                    ),
                );

                Log::info('SocialTrafficController: Zernio OAuth exchange completed', [
                    'user_id' => $request->user()->id,
                    'zernio_platform' => $zernioPlatform,
                    'response_keys' => array_keys($result),
                ]);

                $localPlatform = app(ZernioSocialAccountSync::class)->persistFromOAuthPayload(
                    $request->user(),
                    $zernioPlatform,
                    $result,
                );

                if ($localPlatform === null) {
                    app(ZernioSocialAccountSync::class)->syncForUser($request->user());
                    $localPlatform = app(ZernioSocialAccountSync::class)->mapPlatform($zernioPlatform);
                }

                if ($localPlatform !== null) {
                    return $this->connectionSuccessRedirect($localPlatform);
                }
            } catch (ZernioApiException $e) {
                Log::warning('SocialTrafficController: Zernio OAuth exchange blocked', [
                    'user_id' => $request->user()->id,
                    'zernio_platform' => $zernioPlatform,
                    'code' => $e->errorCode,
                    'status' => $e->httpStatus,
                    'error' => $e->getMessage(),
                ]);

                return $this->handleConnectFailure($routeSlug ?? 'social', $e);
            } catch (\Throwable $e) {
                Log::error('SocialTrafficController: Zernio OAuth exchange failed', [
                    'user_id' => $request->user()->id,
                    'zernio_platform' => $zernioPlatform,
                    'error' => $e->getMessage(),
                ]);

                return $this->authError($routeSlug ?? 'social', 'Could not complete the connection right now. Please try again later or contact support.');
            }
        }

        $connected = (string) ($request->query('connected') ?? $request->query('platform') ?? '');
        $accountId = (string) ($request->query('accountId') ?? $request->query('account_id') ?? $request->query('account') ?? '');
        $username = $request->query('username') ?? $request->query('user');

        if ($connected !== '' && $accountId !== '') {
            $localPlatform = app(ZernioSocialAccountSync::class)->mapPlatform($connected);

            if ($localPlatform !== null) {
                $displayName = is_string($username) && $username !== ''
                    ? ($localPlatform === 'twitter' && ! Str::startsWith($username, '@') ? '@'.$username : $username)
                    : null;

                SocialAccount::query()->updateOrCreate(
                    ['user_id' => $request->user()->id, 'platform' => $localPlatform],
                    [
                        'zernio_account_id' => $accountId,
                        'platform_username' => $displayName,
                        'access_token' => null,
                        'refresh_token' => null,
                        'expires_at' => null,
                        'daily_post_limit' => (int) config('traffic_ai.max_replies_per_day_per_account', 20),
                    ]
                );

                return $this->connectionSuccessRedirect($localPlatform);
            }
        }

        $synced = app(ZernioSocialAccountSync::class)->syncForUser($request->user());

        if ($zernioPlatform !== null) {
            $localPlatform = app(ZernioSocialAccountSync::class)->mapPlatform($zernioPlatform);
            if ($localPlatform !== null && in_array($localPlatform, $synced, true)) {
                return $this->connectionSuccessRedirect($localPlatform);
            }
        }

        if ($synced !== []) {
            return $this->connectionSuccessRedirect($synced[0]);
        }

        Log::warning('SocialTrafficController: incomplete Zernio callback', [
            'user_id' => $request->user()->id,
            'query' => $request->query(),
        ]);

        return $this->authError(
            $routeSlug ?? 'social',
            'Could not confirm the connection. Open Social posting again — accounts sync from Zernio when the page loads.'
        );
    }

    public function platformRedirect(Request $request, string $platform): RedirectResponse
    {
        return $this->connectRedirect($request, $platform);
    }

    private function connectionSuccessRedirect(string $localPlatform): RedirectResponse
    {
        Log::info('SocialTrafficController: account connected', [
            'platform' => $localPlatform,
        ]);

        $label = match ($localPlatform) {
            'twitter' => 'X',
            'reddit' => 'Reddit',
            'youtube' => 'YouTube',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'linkedin' => 'LinkedIn',
            'pinterest' => 'Pinterest',
            default => ucfirst($localPlatform),
        };

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$label} connected successfully.",
        ]);

        return redirect()->to($this->socialTrafficSettingsUrl());
    }

    private function authError(string $platform, string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $message,
        ]);

        return redirect()->to($this->socialTrafficSettingsUrl())->withErrors([$platform => $message]);
    }

    private function handleConnectFailure(string $platform, ZernioApiException $e): RedirectResponse
    {
        $userMessage = $e->userMessage();

        Log::warning('SocialTrafficController: Zernio connect blocked', [
            'platform' => $platform,
            'code' => $e->errorCode,
            'status' => $e->httpStatus,
            'message' => $e->getMessage(),
        ]);

        Inertia::flash('zernioConnect', [
            'platform' => $platform,
            'message' => $userMessage,
        ]);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $userMessage,
        ]);

        return redirect()->to($this->socialTrafficSettingsUrl())->withErrors([$platform => $userMessage]);
    }

    private function oauthCallbackUrl(string $routeSlug): string
    {
        return match ($routeSlug) {
            'reddit' => url('/settings/social-traffic/reddit/callback'),
            'youtube' => url('/settings/social-traffic/youtube/callback'),
            'x' => url('/settings/social-traffic/x/callback'),
            'facebook' => url('/settings/social-traffic/facebook/callback'),
            'instagram' => url('/settings/social-traffic/instagram/callback'),
            'tiktok' => url('/settings/social-traffic/tiktok/callback'),
            'linkedin' => url('/settings/social-traffic/linkedin/callback'),
            'pinterest' => url('/settings/social-traffic/pinterest/callback'),
            default => route('settings.social-traffic.zernio.callback'),
        };
    }

    private function routeSlugFromCallback(Request $request): ?string
    {
        $path = $request->path();

        return match (true) {
            str_ends_with($path, 'reddit/callback') => 'reddit',
            str_ends_with($path, 'youtube/callback') => 'youtube',
            str_ends_with($path, 'x/callback') => 'x',
            str_ends_with($path, 'facebook/callback') => 'facebook',
            str_ends_with($path, 'instagram/callback') => 'instagram',
            str_ends_with($path, 'tiktok/callback') => 'tiktok',
            str_ends_with($path, 'linkedin/callback') => 'linkedin',
            str_ends_with($path, 'pinterest/callback') => 'pinterest',
            default => null,
        };
    }

    private function socialTrafficSettingsUrl(): string
    {
        return route('settings.social-traffic.edit');
    }

    private function redirectExternal(Request $request, string $url): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }

        return redirect()->away($url);
    }
}
