<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admins check each ticket booking an agent submits before the cashier takes
 * payment: approve it (with their own acknowledgement) or return it to the
 * agent with a reason.
 */
class AdminTicketApprovalController extends Controller
{
    /** The three queues on the approvals page. */
    private const TABS = [
        'pending' => TicketBooking::APPROVAL_PENDING,
        'approved' => TicketBooking::APPROVAL_APPROVED,
        'returned' => TicketBooking::APPROVAL_REJECTED,
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->input('tab'), self::TABS) ? $request->input('tab') : 'pending';

        $query = TicketBooking::with(['createdBy:id,name', 'reviewedBy:id,name'])
            ->where('approval_status', self::TABS[$tab]);

        if ($request->filled('search')) {
            $like = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
            $query->where(fn ($inner) => $inner
                ->where('booking_reference', 'like', $like)
                ->orWhere('contact_name', 'like', $like)
                ->orWhere('destination', 'like', $like));
        }

        $tickets = ($tab === 'pending'
            ? $query->orderBy('approval_requested_at')
            : $query->latest('reviewed_at'))
            ->paginate(20)
            ->withQueryString();

        $counts = collect(self::TABS)->map(fn (string $status): int => TicketBooking::where('approval_status', $status)->count());

        return view('admin.ticket-approvals.index', compact('tickets', 'tab', 'counts'));
    }

    public function show(TicketBooking $ticket): View
    {
        abort_if($ticket->approval_status === null, 404);

        $ticket->load(['passengers.documents', 'travelPackage', 'airline', 'createdBy', 'acknowledgedBy', 'reviewedBy', 'bookingAgreement']);

        return view('admin.ticket-approvals.show', compact('ticket'));
    }

    public function approve(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if (! $ticket->isAwaitingApproval()) {
            return back()->with('error', 'This booking is not waiting for approval.');
        }

        $request->validate(
            ['admin_acknowledged' => ['accepted']],
            ['admin_acknowledged.accepted' => 'Tick the acknowledgement to confirm you reviewed the booking.'],
        );

        $ticket->forceFill([
            'approval_status' => TicketBooking::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('Ticketing', 'APPROVE', "Approved {$ticket->booking_reference} for payment (acknowledged as correct by {$request->user()->name})");

        return redirect()->route('admin.ticket-approvals.index')
            ->with('success', "{$ticket->booking_reference} approved. It is now with the cashier for payment.");
    }

    public function reject(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if (! $ticket->isAwaitingApproval()) {
            return back()->with('error', 'This booking is not waiting for approval.');
        }

        $validated = $request->validate(
            ['review_note' => ['required', 'string', 'max:2000']],
            ['review_note.required' => 'Say what the agent needs to correct.'],
        );

        $ticket->forceFill([
            'approval_status' => TicketBooking::APPROVAL_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => trim($validated['review_note']),
        ])->save();

        ActivityLogger::log('Ticketing', 'RETURN', "Returned {$ticket->booking_reference} to the agent: {$ticket->review_note}");

        return redirect()->route('admin.ticket-approvals.index')
            ->with('success', "{$ticket->booking_reference} returned to the agent to correct.");
    }
}
