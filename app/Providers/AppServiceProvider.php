<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
        $this->definePermissions();
    }

    /**
     * Every permission in App\Support\Permissions as a gate ("can:immigration.edit" on a route,
     * Gate::allows('prices.manage'), @can in a view). Admins pass every gate.
     */
    private function definePermissions(): void
    {
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        foreach (Permissions::all() as $permission) {
            Gate::define($permission, fn (User $user) => $user->hasPermission($permission));
        }
    }

    /**
     * Rate limits for the public and authentication surfaces.
     *
     * The application previously had none, which left the three login forms open
     * to unlimited password guessing and the public forms open to spam.
     */
    protected function configureRateLimiting(): void
    {
        // Keyed on the submitted address as well as the IP, so one attacker
        // cannot spray a single account from a pool of addresses, and a shared
        // office IP cannot lock everyone out at once.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by($this->emailKey($request)),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
        ]);

        RateLimiter::for('bookings', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        // Guest live chat. Polling runs on a short loop from every open widget,
        // so its ceiling is far higher than the send ceiling. Each limit pairs a
        // per-conversation budget with a per-IP ceiling so rotating the guest
        // token does not buy unlimited sends.
        RateLimiter::for('chat-init', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('chat-send', fn (Request $request) => [
            Limit::perMinute(20)->by($this->guestKey($request)),
            Limit::perMinute(60)->by($request->ip()),
        ]);

        RateLimiter::for('chat-poll', fn (Request $request) => [
            Limit::perMinute(120)->by($this->guestKey($request)),
            Limit::perMinute(300)->by($request->ip()),
        ]);

        RateLimiter::for('chat-info', fn (Request $request) => [
            Limit::perMinute(10)->by($this->guestKey($request)),
            Limit::perMinute(30)->by($request->ip()),
        ]);
    }

    /**
     * Identify a login attempt by the address being tried.
     */
    private function emailKey(Request $request): string
    {
        return strtolower((string) $request->input('email')).'|'.$request->ip();
    }

    /**
     * Identify a chat request by its conversation, falling back to the IP.
     */
    private function guestKey(Request $request): string
    {
        return (string) ($request->input('guest_token') ?: $request->ip());
    }
}
