<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the SRRV desk to admins and to users holding the 'srrv' role
 * or page permission.
 */
class SrrvAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isSrrv() && ! $user->isAdmin() && ! $user->canAccessPage('srrv'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized SRRV desk access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? $user->staffHomeRoute() : 'home') : 'login')
                ->with('error', 'You do not have permission to access the SRRV desk.');
        }

        return $next($request);
    }
}
