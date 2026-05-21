<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
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
    private const PLATFORM_MAP = [
        'reddit' => 'reddit',
        'youtube' => 'youtube',
        'x' => 'twitter',
    ];

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
        ]);
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
            $profileId = app(ZernioProfileManager::class)->ensureForUser($request->user());
            $redirectUrl = $this->oauthCallbackUrl($platform);
            $connect = $zernio->getConnectUrl($zernioPlatform, $profileId, $redirectUrl);

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

            return $this->authError($platform, 'Could not start Zernio connection. '.$e->getMessage());
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
                $profileId = app(ZernioProfileManager::class)->ensureForUser($request->user());
                $result = app(ZernioClient::class)->completeOAuthConnection($zernioPlatform, $code, $state, $profileId);

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
            } catch (\Throwable $e) {
                Log::error('SocialTrafficController: Zernio OAuth exchange failed', [
                    'user_id' => $request->user()->id,
                    'zernio_platform' => $zernioPlatform,
                    'error' => $e->getMessage(),
                ]);

                return $this->authError($routeSlug ?? 'social', 'Could not complete connection with Zernio. '.$e->getMessage());
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

    public function redditRedirect(Request $request): RedirectResponse
    {
        return $this->connectRedirect($request, 'reddit');
    }

    public function redditCallback(Request $request): RedirectResponse
    {
        return $this->zernioCallback($request);
    }

    public function youtubeRedirect(Request $request): RedirectResponse
    {
        return $this->connectRedirect($request, 'youtube');
    }

    public function youtubeCallback(Request $request): RedirectResponse
    {
        return $this->zernioCallback($request);
    }

    public function xRedirect(Request $request): RedirectResponse
    {
        return $this->connectRedirect($request, 'x');
    }

    public function xCallback(Request $request): RedirectResponse
    {
        return $this->zernioCallback($request);
    }

    private function connectionSuccessRedirect(string $localPlatform): RedirectResponse
    {
        Log::info('SocialTrafficController: account connected via Zernio', [
            'platform' => $localPlatform,
        ]);

        $label = match ($localPlatform) {
            'twitter' => 'X',
            'reddit' => 'Reddit',
            'youtube' => 'YouTube',
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
        Log::warning('SocialTrafficController: Zernio connect blocked', [
            'platform' => $platform,
            'code' => $e->code,
            'status' => $e->httpStatus,
            'message' => $e->getMessage(),
        ]);

        Inertia::flash('zernioConnect', [
            'platform' => $platform,
            'code' => $e->code ?? 'connect_failed',
            'message' => $e->getMessage(),
            'dashboard_url' => $e->dashboardUrl ?? 'https://zernio.com/dashboard?tab=billing',
            'documentation_url' => $e->documentationUrl ?? 'https://docs.zernio.com/billing/payment-method-required',
        ]);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $e->getMessage(),
        ]);

        return redirect()->to($this->socialTrafficSettingsUrl())->withErrors([$platform => $e->getMessage()]);
    }

    private function oauthCallbackUrl(string $routeSlug): string
    {
        return match ($routeSlug) {
            'reddit' => url('/settings/social-traffic/reddit/callback'),
            'youtube' => url('/settings/social-traffic/youtube/callback'),
            'x' => url('/settings/social-traffic/x/callback'),
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
