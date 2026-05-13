<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialTrafficController extends Controller
{
    /* ──────────────────────────────────────────────────────────
     | Edit / index
     ─────────────────────────────────────────────────────────── */

    public function edit(Request $request): Response
    {
        $accounts = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->get(['id', 'platform', 'platform_username', 'created_at']);

        return Inertia::render('settings/SocialTraffic', [
            'socialAccounts' => $accounts,
            'redditConfigured' => $this->redditOAuthConfigured(),
            'youtubeConfigured' => $this->youtubeOAuthConfigured(),
            'xConfigured' => $this->xOAuthConfigured(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────
     | Disconnect (shared)
     ─────────────────────────────────────────────────────────── */

    public function disconnect(Request $request, SocialAccount $socialAccount): RedirectResponse
    {
        abort_unless((int) $socialAccount->user_id === (int) $request->user()->id, 403);

        $platform = ucfirst($socialAccount->platform);
        $socialAccount->delete();

        return back()->with('success', "{$platform} disconnected.");
    }

    /* ──────────────────────────────────────────────────────────
     | REDDIT
     ─────────────────────────────────────────────────────────── */

    public function redditRedirect(Request $request): RedirectResponse
    {
        if (! $this->redditOAuthConfigured()) {
            return back()->withErrors(['reddit' => 'Reddit OAuth is not configured.']);
        }

        $state = Str::random(40);
        $request->session()->put('reddit_oauth_state', $state);

        return redirect()->away('https://www.reddit.com/api/v1/authorize?'.http_build_query([
            'client_id' => config('services.reddit.client_id'),
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => config('services.reddit.redirect'),
            'duration' => 'permanent',
            'scope' => implode(' ', config('services.reddit.scopes', ['identity', 'read', 'submit'])),
        ]));
    }

    public function redditCallback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->authError('reddit', (string) $request->query('error_description', 'Reddit authorization was denied.'));
        }

        if (! $this->validateState($request, 'reddit_oauth_state')) {
            return $this->authError('reddit', 'Invalid OAuth state. Please try again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->authError('reddit', 'Missing authorization code.');
        }

        try {
            $basic = base64_encode(config('services.reddit.client_id').':'.config('services.reddit.client_secret'));
            $ua = $this->redditUserAgent();

            $tokenRes = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.$basic, 'User-Agent' => $ua])
                ->post('https://www.reddit.com/api/v1/access_token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('services.reddit.redirect'),
                ]);

            if (! $tokenRes->successful()) {
                return $this->authError('reddit', 'Could not exchange Reddit authorization code.');
            }

            $tokens = $tokenRes->json();
            $access = $tokens['access_token'] ?? null;
            $refresh = $tokens['refresh_token'] ?? null;

            if (! is_string($access) || $access === '') {
                return $this->authError('reddit', 'Reddit did not return an access token.');
            }

            $meRes = Http::withToken($access)->withHeaders(['User-Agent' => $ua])->get('https://oauth.reddit.com/api/v1/me');
            $me = $meRes->successful() ? $meRes->json() : [];
            $username = is_array($me) ? ($me['name'] ?? null) : null;

            SocialAccount::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'platform' => 'reddit'],
                [
                    'access_token' => $access,
                    'refresh_token' => is_string($refresh) ? $refresh : null,
                    'platform_username' => is_string($username) ? $username : null,
                    'expires_at' => null,
                ]
            );

            return redirect()->route('settings.social-traffic.edit')->with('success', 'Reddit connected successfully!');
        } catch (\Throwable $e) {
            Log::error('Reddit OAuth callback exception', ['error' => $e->getMessage()]);

            return $this->authError('reddit', 'Unexpected error while connecting Reddit.');
        }
    }

    /* ──────────────────────────────────────────────────────────
     | YOUTUBE (Google OAuth)
     ─────────────────────────────────────────────────────────── */

    public function youtubeRedirect(Request $request): RedirectResponse
    {
        if (! $this->youtubeOAuthConfigured()) {
            return back()->withErrors(['youtube' => 'YouTube OAuth is not configured (set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET).']);
        }

        $state = Str::random(40);
        $request->session()->put('youtube_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', (array) config('services.google.scopes')),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ]));
    }

    public function youtubeCallback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->authError('youtube', (string) $request->query('error_description', 'YouTube authorization was denied.'));
        }

        if (! $this->validateState($request, 'youtube_oauth_state')) {
            return $this->authError('youtube', 'Invalid OAuth state. Please try again.');
        }

        $code = (string) $request->query('code', '');
        if ($code === '') {
            return $this->authError('youtube', 'Missing authorization code.');
        }

        try {
            $tokenRes = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => config('services.google.redirect'),
                'grant_type' => 'authorization_code',
            ]);

            if (! $tokenRes->successful()) {
                Log::warning('YouTube OAuth token exchange failed', ['body' => Str::limit($tokenRes->body(), 400, '')]);

                return $this->authError('youtube', 'Could not exchange YouTube authorization code.');
            }

            $tokens = $tokenRes->json();
            $access = $tokens['access_token'] ?? null;
            $refresh = $tokens['refresh_token'] ?? null;
            $expiresIn = isset($tokens['expires_in']) ? (int) $tokens['expires_in'] : null;

            if (! is_string($access) || $access === '') {
                return $this->authError('youtube', 'Google did not return an access token.');
            }

            $meRes = Http::withToken($access)->get('https://www.googleapis.com/oauth2/v2/userinfo');
            $me = $meRes->successful() ? $meRes->json() : [];
            $email = is_array($me) ? ($me['email'] ?? null) : null;
            $name = is_array($me) ? ($me['name'] ?? $email) : $email;

            SocialAccount::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'platform' => 'youtube'],
                [
                    'access_token' => $access,
                    'refresh_token' => is_string($refresh) ? $refresh : null,
                    'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                    'platform_username' => is_string($name) ? $name : null,
                ]
            );

            return redirect()->route('settings.social-traffic.edit')->with('success', 'YouTube account connected successfully!');
        } catch (\Throwable $e) {
            Log::error('YouTube OAuth callback exception', ['error' => $e->getMessage()]);

            return $this->authError('youtube', 'Unexpected error while connecting YouTube.');
        }
    }

    /* ──────────────────────────────────────────────────────────
     | X (Twitter OAuth 2.0 PKCE)
     ─────────────────────────────────────────────────────────── */

    public function xRedirect(Request $request): RedirectResponse
    {
        if (! $this->xOAuthConfigured()) {
            return back()->withErrors(['x' => 'X OAuth is not configured (set X_CLIENT_ID and X_CLIENT_SECRET).']);
        }

        $state = Str::random(40);
        $codeVerifier = Str::random(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $request->session()->put('x_oauth_state', $state);
        $request->session()->put('x_code_verifier', $codeVerifier);

        return redirect()->away(config('services.x.authorization_endpoint', 'https://twitter.com/i/oauth2/authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.x.client_id'),
            'redirect_uri' => config('services.x.redirect'),
            'scope' => implode(' ', (array) config('services.x.scopes')),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]));
    }

    public function xCallback(Request $request): RedirectResponse
    {
        if ($request->query('error')) {
            return $this->authError('x', (string) $request->query('error_description', 'X authorization was denied.'));
        }

        if (! $this->validateState($request, 'x_oauth_state')) {
            return $this->authError('x', 'Invalid OAuth state. Please try again.');
        }

        $code = (string) $request->query('code', '');
        $codeVerifier = (string) $request->session()->pull('x_code_verifier', '');

        if ($code === '' || $codeVerifier === '') {
            return $this->authError('x', 'Missing authorization code or PKCE verifier.');
        }

        try {
            $basic = base64_encode(config('services.x.client_id').':'.config('services.x.client_secret'));

            $tokenRes = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.$basic])
                ->post(config('services.x.token_endpoint', 'https://api.twitter.com/2/oauth2/token'), [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => config('services.x.redirect'),
                    'code_verifier' => $codeVerifier,
                ]);

            if (! $tokenRes->successful()) {
                Log::warning('X OAuth token exchange failed', ['body' => Str::limit($tokenRes->body(), 400, '')]);

                return $this->authError('x', 'Could not exchange X authorization code.');
            }

            $tokens = $tokenRes->json();
            $access = $tokens['access_token'] ?? null;
            $refresh = $tokens['refresh_token'] ?? null;
            $expiresIn = isset($tokens['expires_in']) ? (int) $tokens['expires_in'] : null;

            if (! is_string($access) || $access === '') {
                return $this->authError('x', 'X did not return an access token.');
            }

            $meRes = Http::withToken($access)->get(config('services.x.me_endpoint', 'https://api.twitter.com/2/users/me'), ['user.fields' => 'username,name']);
            $meData = $meRes->successful() ? ($meRes->json()['data'] ?? []) : [];
            $username = is_array($meData) ? ($meData['username'] ?? null) : null;

            SocialAccount::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'platform' => 'twitter'],
                [
                    'access_token' => $access,
                    'refresh_token' => is_string($refresh) ? $refresh : null,
                    'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                    'platform_username' => is_string($username) ? '@'.$username : null,
                ]
            );

            return redirect()->route('settings.social-traffic.edit')->with('success', 'X account connected successfully!');
        } catch (\Throwable $e) {
            Log::error('X OAuth callback exception', ['error' => $e->getMessage()]);

            return $this->authError('x', 'Unexpected error while connecting X.');
        }
    }

    /* ──────────────────────────────────────────────────────────
     | Helpers
     ─────────────────────────────────────────────────────────── */

    private function authError(string $platform, string $message): RedirectResponse
    {
        return redirect()->route('settings.social-traffic.edit')->withErrors([$platform => $message]);
    }

    private function validateState(Request $request, string $sessionKey): bool
    {
        $incoming = (string) $request->query('state', '');
        $expected = (string) $request->session()->pull($sessionKey, '');

        return $incoming !== '' && hash_equals($expected, $incoming);
    }

    private function redditUserAgent(): string
    {
        return sprintf(
            '%s:%s:%s',
            config('services.reddit.platform', 'web'),
            config('services.reddit.app_id', config('app.name')),
            config('services.reddit.version_string', '1.0')
        );
    }

    private function redditOAuthConfigured(): bool
    {
        return $this->isConfigured('services.reddit.client_id') && $this->isConfigured('services.reddit.client_secret');
    }

    private function youtubeOAuthConfigured(): bool
    {
        return $this->isConfigured('services.google.client_id') && $this->isConfigured('services.google.client_secret');
    }

    private function xOAuthConfigured(): bool
    {
        return $this->isConfigured('services.x.client_id') && $this->isConfigured('services.x.client_secret');
    }

    private function isConfigured(string $key): bool
    {
        $val = config($key);

        return is_string($val) && $val !== '';
    }
}
