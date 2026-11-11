<?php

use App\Http\Middleware\ActionPermissions;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\CashierAccessMiddleware;
use App\Http\Middleware\ClearSavedDeskDraft;
use App\Http\Middleware\DeletesAreForAdmins;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\ImmigrationAccessMiddleware;
use App\Http\Middleware\LocalToursAccessMiddleware;
use App\Http\Middleware\PageAccessMiddleware;
use App\Http\Middleware\SrrvAccessMiddleware;
use App\Http\Middleware\TicketingAccessMiddleware;
use App\Http\Middleware\VisaAssistanceAccessMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\HttpException;

$sessionEnded = 'Your session has ended and you were signed out. Please sign in again to continue.';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) use ($sessionEnded): void {
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [ForceHttps::class]);
        // Deleting for good is for admins only (see the middleware).
        $middleware->web(append: [DeletesAreForAdmins::class, ActionPermissions::class, ClearSavedDeskDraft::class]);

        // A signed-out visitor to a staff page is almost always someone whose session ran out,
        // so the sign-in page says so instead of appearing without a word. Going straight to
        // the staff sign-in (not through /login) keeps the flashed notice alive.
        $middleware->redirectGuestsTo(function (Request $request) use ($sessionEnded): string {
            if ($request->hasSession()) {
                $request->session()->flash('warning', $sessionEnded);
            }

            return route('admin.login');
        });

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin.only' => AdminOnlyMiddleware::class,
            'cashier' => CashierAccessMiddleware::class,
            'immigration' => ImmigrationAccessMiddleware::class,
            'local_tours' => LocalToursAccessMiddleware::class,
            'page.access' => PageAccessMiddleware::class,
            'srrv' => SrrvAccessMiddleware::class,
            'ticketing' => TicketingAccessMiddleware::class,
            'visa' => VisaAssistanceAccessMiddleware::class,
        ]);

        // Desk access is checked before route model binding: desk files are
        // private to the staff member who opened them (OwnFilesScope), so
        // binding first would answer another desk's officer with a 404
        // instead of sending them back to their own desk.
        foreach ([TicketingAccessMiddleware::class, VisaAssistanceAccessMiddleware::class, SrrvAccessMiddleware::class, LocalToursAccessMiddleware::class] as $deskAccess) {
            $middleware->prependToPriorityList(SubstituteBindings::class, $deskAccess);
        }
    })
    ->withExceptions(function (Exceptions $exceptions) use ($sessionEnded): void {
        // A staff form sent after the session ran out (the sign-out button included) fails the
        // CSRF check. Send the person to the sign-in page with the notice, not a bare 419 page.
        $exceptions->render(function (HttpException $e, Request $request) use ($sessionEnded) {
            $staffArea = $request->is('logout', 'admin', 'admin/*', 'ticketing', 'ticketing/*', 'visa-assistance', 'visa-assistance/*', 'srrv', 'srrv/*', 'local-tours', 'local-tours/*', 'cashier', 'cashier/*');

            if ($e->getStatusCode() !== 419 || ! $staffArea || $request->expectsJson() || auth()->check()) {
                return null;
            }

            return redirect()->route('admin.login')->with('warning', $sessionEnded);
        });
    })->create();
