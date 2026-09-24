<?php

namespace App\Http\Middleware;

use App\Models\CustomDomain;
use App\Services\Domains\CustomDomainVerificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomDomain
{
    public function __construct(
        protected CustomDomainVerificationService $verification,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $appHost = $this->verification->appHost();

        if ($this->isAppHost($host, $appHost)) {
            return $next($request);
        }

        $domain = CustomDomain::query()
            ->where('domain', $host)
            ->where('status', 'active')
            ->with(['campaign.user'])
            ->first();

        if (! $domain?->campaign || $domain->campaign->status !== 'published') {
            abort(404, 'This domain is not connected to a published campaign.');
        }

        $request->attributes->set('custom_domain', $domain);
        $request->attributes->set('resolved_campaign', $domain->campaign);
        $request->attributes->set('resolved_username', $domain->campaign->user?->username);

        return $next($request);
    }

    protected function isAppHost(string $host, string $appHost): bool
    {
        if ($host === $appHost) {
            return true;
        }

        if (in_array($host, ['localhost', '127.0.0.1'], true) && in_array($appHost, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        return false;
    }
}
