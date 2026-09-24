<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the visa assistance counter to admins and to users holding the
 * 'visa_assistance' role or page permission.
 */
class VisaAssistanceAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || (! $user->isVisaAssistance() && ! $user->isAdmin() && ! $user->canAccessPage('visa_assistance'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized visa assistance counter access.'], 403);
            }

            return redirect()->route($user ? ($user->isStaff() ? $user->staffHomeRoute() : 'home') : 'login')
                ->with('error', 'You do not have permission to access the visa assistance counter.');
        }

        return $next($request);
    }
}
