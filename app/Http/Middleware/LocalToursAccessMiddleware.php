<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the Local Tours desk to admins and to staff who may open it (the
 * local_tours.view permission).
 */
class LocalToursAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->canAccessPage('local_tours')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized Local Tours desk access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? $user->staffHomeRoute() : 'home') : 'login')
                ->with('error', 'You do not have permission to access the Local Tours desk.');
        }

        return $next($request);
    }
}
