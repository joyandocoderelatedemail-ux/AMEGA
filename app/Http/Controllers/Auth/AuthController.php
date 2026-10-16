<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Display the dedicated Agent portal login view.
     */
    public function showAgentLogin()
    {
        if (Auth::check() && Auth::user()->isStaff()) {
            return redirect()->route(Auth::user()->staffHomeRoute());
        }

        return view('auth.agent-login');
    }

    /**
     * Handle Agent portal authentication request.
     */
    public function agentLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->isStaff()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Access denied. You do not have travel agent credentials.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            ActivityLogger::log('Auth', 'LOGIN', "Agent {$user->name} logged in via Agent Portal");

            return redirect()->intended(route($user->staffHomeRoute()))
                ->with('success', 'Agent portal access granted! Welcome back, '.$user->name.'.');
        }

        return back()->withErrors([
            'email' => 'Invalid travel agent credentials.',
        ])->onlyInput('email');
    }

    /**
     * Display the admin login view.
     */
    public function showAdminLogin()
    {
        if (Auth::check() && Auth::user()->isStaff()) {
            return redirect()->route(Auth::user()->staffHomeRoute());
        }

        return view('auth.admin-login');
    }

    /**
     * Handle admin & agent staff login authentication request.
     */
    public function adminLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();

            if (! $user->isStaff()) {
                Auth::logout();

                return back()->withErrors([
                    'email' => 'Access denied. Only Agents and Administrators may log in.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            ActivityLogger::log('Auth', 'LOGIN', "Staff member {$user->name} logged into Staff Portal");

            return redirect()->intended(route($user->staffHomeRoute()))
                ->with('success', 'Staff portal access granted.');
        }

        return back()->withErrors([
            'email' => 'Invalid administrator credentials.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        $isStaff = false;

        if (Auth::check()) {
            $user = Auth::user();
            $isStaff = $user->isStaff();
            ActivityLogger::log('Auth', 'LOGOUT', "User {$user->name} logged out");
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $referer = (string) $request->headers->get('referer', '');
        $isFromStaffArea = str_contains($referer, '/admin')
            || str_contains($referer, '/ticketing')
            || str_contains($referer, '/immigration')
            || str_contains($referer, '/visa-assistance')
            || str_contains($referer, '/srrv');

        if ($isStaff || $isFromStaffArea || $request->input('redirect_to') === 'admin') {
            return redirect()->route('admin.login')->with('info', 'You have been logged out.');
        }

        return redirect()->route('home')->with('info', 'You have been logged out.');
    }
}
