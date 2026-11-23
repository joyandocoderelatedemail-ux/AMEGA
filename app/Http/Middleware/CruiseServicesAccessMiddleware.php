<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the Cruise Services desk to admins and to staff who may open it (the
 * cruise_services.view permission).
 */
class CruiseServicesAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->canAccessPage('cruise_services')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized Cruise Services desk access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? $user->staffHomeRoute() : 'home') : 'login')
                ->with('error', 'You do not have permission to access the Cruise Services desk.');
        }

        return $next($request);
    }
}
