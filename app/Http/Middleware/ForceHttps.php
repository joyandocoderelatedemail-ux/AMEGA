<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Send plain-HTTP visitors to the HTTPS site in production.
 *
 * Every generated URL is already forced to https (AppServiceProvider), so a
 * page served over http posts its forms to https. The session cookie issued
 * on the http page does not reliably survive that hop, which surfaced as
 * "419 Page Expired" on the public inquiry form.
 */
class ForceHttps
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
