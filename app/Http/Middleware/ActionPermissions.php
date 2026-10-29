<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * The actions behind a page's "Open": a staff member who may open a desk or page (its "view") still
 * needs "create" to start something new there (a new-file form, or saving one) and "edit" to change
 * what is already there. The cashier's saves take "record_payment". Checked here once for every
 * request on a desk or admin page, so a new button can never slip past it. Admins pass; deleting is
 * DeletesAreForAdmins; routes already guarded by a specific permission (prices, approvals) or for
 * admins only are left to that guard.
 */
class ActionPermissions
{
    /** Route middleware => the module (App\Support\Permissions) it opens. */
    private const MODULE_OF = [
        'ticketing' => 'ticketing',
        'immigration' => 'immigration',
        'visa' => 'visa_assistance',
        'srrv' => 'srrv',
        'cashier' => 'cashier',
    ];

    /** Route parameters that name a kind of thing, not a record (POST srrv/start/{service} starts a new file). */
    private const NOT_RECORDS = ['service', 'kind'];

    /** Requests that only read, though sent as POST (marking a chat read). */
    private const READS = ['admin.chats.read'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user?->isStaff() || $user->isAdmin() || ! $route instanceof Route || in_array($route->getName(), self::READS, true)) {
            return $next($request);
        }

        $module = $this->moduleOf($route);
        // No module, or the person cannot open it at all: the page's own middleware answers that.
        if ($module === null || ! $user->canAccessPage($module)) {
            return $next($request);
        }

        $action = $this->actionFor($request, $route, $module);
        if ($action === null || $user->hasPermission("{$module}.{$action}")) {
            return $next($request);
        }

        $message = match ($action) {
            'create' => 'You can open this page but not create anything here. Ask an admin for the Create permission.',
            'record_payment' => 'You can open the cashier but not record payments. Ask an admin for that permission.',
            default => 'You can open this page but not change anything here. Ask an admin for the Edit permission.',
        };

        return $request->expectsJson()
            ? response()->json(['message' => $message], 403)
            : redirect()->back(fallback: url('/'))->with('error', $message)->setStatusCode(303);
    }

    private function moduleOf(Route $route): ?string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }
            // A route with its own permission (prices, approvals) or for admins only is guarded there.
            if (str_starts_with($middleware, 'can:') || $middleware === 'admin.only') {
                return null;
            }
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'page.access:')) {
                return substr($middleware, strlen('page.access:'));
            }
            if (is_string($middleware) && isset(self::MODULE_OF[$middleware])) {
                return self::MODULE_OF[$middleware];
            }
        }

        return null;
    }

    /** The action a request needs: create, edit, record_payment, or null when it only reads. */
    private function actionFor(Request $request, Route $route, string $module): ?string
    {
        $name = (string) $route->getName();

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return match (true) {
                str_ends_with($name, '.create') => 'create',
                // A read-only preview of a file opens on its edit page.
                str_ends_with($name, '.edit') && ! $request->boolean('preview') => 'edit',
                default => null,
            };
        }

        if ($request->isMethod('DELETE')) {
            return null;
        }

        if ($module === 'cashier') {
            return 'record_payment';
        }

        // Saving something new (no existing record in the address) is creating; anything else edits.
        $records = array_diff(array_keys($route->parameters()), self::NOT_RECORDS);

        return $request->isMethod('POST') && $records === [] ? 'create' : 'edit';
    }
}
