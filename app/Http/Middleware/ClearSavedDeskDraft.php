<?php

namespace App\Http\Middleware;

use App\Models\DeskDraft;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A form continued from "Save as Pending" carries the pending form's id (_desk_draft). Once the
 * form's own Save goes through (a redirect with no errors), the real file exists, so the pending
 * form is removed. Saving it as pending again just updates it (DeskDraftController).
 */
class ClearSavedDeskDraft
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Errors flashed by this request mean the save did not go through.
        if ($request->filled('_desk_draft') && ! $request->isMethod('GET')
            && ! str_ends_with((string) $request->route()?->getName(), 'pending.store')
            && $response->isRedirection()
            && $request->hasSession() && array_intersect(['errors', 'error'], $request->session()->get('_flash.new', [])) === []) {
            DeskDraft::whereKey($request->integer('_desk_draft'))
                ->when(! $request->user()?->isAdmin(), fn ($mine) => $mine->where('created_by', $request->user()?->id))
                ->delete();
        }

        return $response;
    }
}
