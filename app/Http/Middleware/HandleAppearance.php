<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('appearance.dark_mode_enabled')) {
            View::share('appearance', 'light');
            View::share('appearanceDarkModeEnabled', false);

            return $next($request);
        }

        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('appearanceDarkModeEnabled', true);

        return $next($request);
    }
}
