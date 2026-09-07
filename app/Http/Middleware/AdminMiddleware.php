<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isAdmin() && ! $user->isAgent())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized staff portal access.'], 403);
            }

            if ($user && $user->isTicketing()) {
                return redirect()->route('ticketing.dashboard')
                    ->with('error', 'Ticketing officers do not have access to the main admin dashboard.');
            }

            return redirect()->route('login')
                ->with('error', 'Please log in with staff credentials (Agent or Admin) to access this area.');
        }

        return $next($request);
    }
}
