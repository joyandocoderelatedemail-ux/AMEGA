<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the ticketing portal to admins and users with the 'ticketing' role.
 */
class TicketingAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isTicketing() && ! $user->isAdmin() && ! $user->canAccessPage('ticketing'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized ticketing portal access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? 'admin.dashboard' : 'home') : 'login')
                ->with('error', 'You do not have permission to access the ticketing portal.');
        }

        return $next($request);
    }
}
