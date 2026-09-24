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

            if ($user && $user->isTicketingStaff()) {
                return redirect()->route('ticketing.dashboard')
                    ->with('error', 'Ticketing officers do not have access to the main admin dashboard.');
            }

            if ($user && $user->isVisaAssistanceStaff()) {
                return redirect()->route('visa.dashboard')
                    ->with('error', 'Visa assistance officers do not have access to the main admin dashboard.');
            }

            if ($user && $user->isSrrvStaff()) {
                return redirect()->route('srrv.dashboard')
                    ->with('error', 'SRRV officers do not have access to the main admin dashboard.');
            }

            return redirect()->route('login')
                ->with('error', 'Please log in with staff credentials (Agent or Admin) to access this area.');
        }

        // Dedicated ticketing staff (role 'ticketing' or ticketing-only agent)
        if ($user->isTicketingStaff()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ticketing officers do not have access to the main admin dashboard.'], 403);
            }

            return redirect()->route('ticketing.dashboard')
                ->with('error', 'Ticketing officers do not have access to the main admin dashboard.');
        }

        // Dedicated visa assistance staff (role 'visa_assistance' or visa-only agent)
        if ($user->isVisaAssistanceStaff()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Visa assistance officers do not have access to the main admin dashboard.'], 403);
            }

            return redirect()->route('visa.dashboard')
                ->with('error', 'Visa assistance officers do not have access to the main admin dashboard.');
        }

        // Dedicated SRRV staff (role 'srrv' or SRRV-only agent)
        if ($user->isSrrvStaff()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'SRRV officers do not have access to the main admin dashboard.'], 403);
            }

            return redirect()->route('srrv.dashboard')
                ->with('error', 'SRRV officers do not have access to the main admin dashboard.');
        }

        // Dedicated immigration agents cannot access the main admin dashboard/pages outside the counter
        if ($user->isImmigrationAgent() && ! $request->is('admin/immigration*') && ! $request->is('admin/client-sheets*') && ! $request->is('admin/immigration-pricing*') && ! $request->is('admin/immigration-categories*')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Immigration officers do not have access to the main admin dashboard.'], 403);
            }

            return redirect()->route('admin.immigration.dashboard')
                ->with('error', 'Immigration officers do not have access to the main admin dashboard.');
        }

        // Agents who do not have access to any main admin modules
        if ($user->isAgent() && ! $user->hasAdminAccess() && ! $request->is('admin/immigration*') && ! $request->is('admin/client-sheets*') && ! $request->is('admin/immigration-pricing*') && ! $request->is('admin/immigration-categories*')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized staff portal access.'], 403);
            }

            return redirect()->route($user->staffHomeRoute())
                ->with('error', 'You do not have access to the main admin dashboard.');
        }

        return $next($request);
    }
}
