<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PageAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $page): Response
    {
        $user = Auth::user();

        if (! $user || ! $user->canAccessPage($page)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized page access.'], 403);
            }

            return redirect()->route($user ? $user->staffHomeRoute() : 'login')
                ->with('error', "You do not have permission to access the {$page} module.");
        }

        return $next($request);
    }
}
