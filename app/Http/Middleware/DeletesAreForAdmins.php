<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deleting for good is for admins only. Any other staff member cancels or returns a file instead, so
 * nothing disappears without a trace. Checked here once for every DELETE request, so a new delete
 * button can never slip past it.
 */
class DeletesAreForAdmins
{
    /**
     * Removals that are corrections, not deleting a record: unlinking a member from a company, taking
     * off a scan or an applicant added by mistake, discarding an unfinished ticket draft. Each is on a
     * file the person can already open (their own, under OwnFilesScope).
     */
    private const ALLOWED = [
        'admin.corporates.members.destroy',
        'admin.immigration.pending.destroy',
        'ticketing.tickets.pending.destroy',
        'ticketing.tickets.supporting-documents.destroy',
        'visa.applicants.destroy',
        'visa.pending.destroy',
        'visa.documents.destroy',
        'srrv.pending.destroy',
        'srrv.requests.documents.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($request->isMethod('DELETE') && $user?->isStaff() && ! $user->isAdmin()
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            $message = 'Only an admin can delete this. Cancel it instead, or ask an admin.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : redirect()->back(fallback: url('/'))->with('error', $message)->setStatusCode(303);
        }

        return $next($request);
    }
}
