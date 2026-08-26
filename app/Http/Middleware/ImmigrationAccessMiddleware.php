<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the immigration counter to admins and to agents granted the
 * "immigration" page permission.
 */
class ImmigrationAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->canAccessPage('immigration')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have access to the immigration counter.'], 403);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'You do not have access to the immigration counter.');
        }

        return $next($request);
    }
}
