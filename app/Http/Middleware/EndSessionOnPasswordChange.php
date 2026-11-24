<?php

namespace App\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\Middleware\AuthenticateSession;

/** Laravel's AuthenticateSession, but the sign-in page then says the password was changed, and when. */
class EndSessionOnPasswordChange extends AuthenticateSession
{
    public function handle($request, \Closure $next)
    {
        // The stored hash belongs to whoever was signed in when it was saved: a session now signed in as
        // someone else starts afresh rather than being judged against another person's password.
        if ($request->hasSession() && $request->user()
            && $request->session()->get('password_hash_owner') !== $request->user()->getAuthIdentifier()) {
            $request->session()->forget('password_hash_'.$this->auth->getDefaultDriver());
            $request->session()->put('password_hash_owner', $request->user()->getAuthIdentifier());
        }

        return parent::handle($request, $next);
    }

    protected function logout($request)
    {
        $changedAt = $request->user()?->password_changed_at;

        $this->guard()->logoutCurrentDevice();
        $request->session()->flush();
        $request->session()->flash('warning', $changedAt
            ? 'Your password was changed '.$changedAt->diffForHumans().'. Please sign in again with your new password.'
            : 'Your password was changed. Please sign in again with your new password.');

        throw new AuthenticationException('Unauthenticated.', [$this->auth->getDefaultDriver()], route('admin.login'));
    }
}
