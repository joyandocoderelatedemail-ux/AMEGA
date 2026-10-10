<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the cashier portal to cashiers and admins.
 */
class CashierAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isCashier() && ! $user->isAdmin())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized cashier portal access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? $user->staffHomeRoute() : 'home') : 'login')
                ->with('error', 'You do not have permission to access the cashier portal.');
        }

        return $next($request);
    }
}
