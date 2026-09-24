<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomDomainRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->attributes->get('resolved_campaign')) {
            abort(404);
        }

        return $next($request);
    }
}
