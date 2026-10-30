<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Services\ClientProfileService;
use App\Services\TicketPaymentRecorder;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The cashier's desk: every ticket booking an admin approved, waiting for its
 * payment to be recorded. Once it is paid in full, the agent issues the ticket.
 */
class CashierController extends Controller
{
    /** Approved bookings with money still to collect, the latest approval first. */
    public function index(Request $request): View
    {
        $tickets = $this->search(TicketBooking::awaitingPayment(), $request)
            // Approved within the date range picked on the page.
            ->when(true, fn ($query) => DateRange::fromRequest($request)->apply($query, 'reviewed_at'))
            ->with('createdBy:id,name')
            // Just approved first; bookings approved before approvals were recorded go last.
            ->orderByRaw('reviewed_at IS NULL')
            ->orderByDesc('reviewed_at')
            ->paginate(20)
            ->withQueryString();

        return view('cashier.index', [
            'tickets' => $tickets,
            'title' => 'To collect',
            'empty' => 'No approved bookings are waiting for payment.',
        ]);
    }

    /** Bookings recently paid in full. */
    public function paid(Request $request): View
    {
        $tickets = $this->search(TicketBooking::where('payment_status', TicketBooking::PAYMENT_FULL), $request)
            // Paid in full within the date range picked on the page.
            ->when(true, fn ($query) => DateRange::fromRequest($request)->apply($query, 'paid_at'))
            ->with('createdBy:id,name')
            ->latest('paid_at')
            ->paginate(20)
            ->withQueryString();

        return view('cashier.index', [
            'tickets' => $tickets,
            'title' => 'Paid',
            'empty' => 'No paid bookings yet.',
        ]);
    }

    public function show(TicketBooking $ticket): View
    {
        $ticket->markSeen('cashier_seen_at');

        $ticket->load(['passengers.documents', 'travelPackage', 'airline', 'createdBy', 'reviewedBy', 'payments.receivedBy']);

        return view('cashier.show', [
            'ticket' => $ticket,
            'blockedReason' => TicketPaymentRecorder::blockedReason($ticket),
        ]);
    }

    public function store(Request $request, TicketBooking $ticket, TicketPaymentRecorder $recorder): RedirectResponse
    {
        if ($reason = TicketPaymentRecorder::blockedReason($ticket)) {
            return back()->with('error', $reason);
        }

        // The cashier confirms the money was received and the details are right, and is accountable for them.
        $validated = $request->validate(
            TicketPaymentRecorder::rules() + ['cashier_acknowledged' => ['accepted']],
            TicketPaymentRecorder::messages() + ['cashier_acknowledged.accepted' => 'Tick the acknowledgement to confirm the payment details are correct.'],
        );

        try {
            $payment = $recorder->record($ticket, $validated, $request->user(), $request->file('receipt_file'));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('cashier.payments.show', $ticket)->with('success', $ticket->isFullyPaid()
            ? "Payment recorded ({$payment->receiptNumber()}). {$ticket->booking_reference} is paid in full: the agent can now issue the ticket."
            : "Payment recorded ({$payment->receiptNumber()}). Balance on {$ticket->booking_reference}: ₱".number_format($ticket->balanceDue(), 2).'.');
    }

    /** Attach (or replace) the actual receipt on a payment already recorded. */
    public function attachReceipt(Request $request, TicketBooking $ticket, TicketPayment $payment): RedirectResponse
    {
        abort_unless($payment->ticket_booking_id === $ticket->id, 404);

        $request->validate(['receipt_file' => 'required|'.ClientProfileService::SCAN_RULE], [
            'receipt_file.required' => 'Choose the receipt file to attach.',
        ] + TicketPaymentRecorder::messages());

        TicketPaymentRecorder::attachReceiptFile($ticket, $payment, $request->file('receipt_file'));

        return back()->with('success', "Receipt attached to {$payment->receiptNumber()}.");
    }

    /** The actual receipt attached to a payment, from the private disk. */
    public function receiptFile(TicketBooking $ticket, TicketPayment $payment): StreamedResponse|RedirectResponse
    {
        return self::streamReceiptFile($ticket, $payment);
    }

    /** Shared with the ticketing desk: the receipt file of an entry on this booking. */
    public static function streamReceiptFile(TicketBooking $ticket, TicketPayment $payment): StreamedResponse|RedirectResponse
    {
        abort_unless($payment->ticket_booking_id === $ticket->id && $payment->hasReceiptFile(), 404);

        if (! DocumentStorage::disk()->exists($payment->receipt_file_path)) {
            return back()->with('error', 'The receipt file could not be found.');
        }

        return DocumentStorage::disk()->response($payment->receipt_file_path, $payment->receipt_file_name, [
            'Content-Type' => $payment->receipt_file_mime ?: 'application/octet-stream',
        ]);
    }

    public function receipt(TicketBooking $ticket, TicketPayment $payment): View
    {
        abort_unless($payment->ticket_booking_id === $ticket->id, 404);

        $payment->load('receivedBy');
        [$receivedToDate, $balanceAfter] = TicketPaymentRecorder::receiptFigures($ticket, $payment);

        return view('ticketing.tickets.receipt', [
            'ticket' => $ticket,
            'payment' => $payment,
            'receivedToDate' => $receivedToDate,
            'balanceAfter' => $balanceAfter,
            'backUrl' => route('cashier.payments.show', $ticket),
        ]);
    }

    /**
     * @param  Builder<TicketBooking>  $query
     * @return Builder<TicketBooking>
     */
    private function search($query, Request $request)
    {
        if ($request->filled('search')) {
            $like = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
            $query->where(fn ($inner) => $inner
                ->where('booking_reference', 'like', $like)
                ->orWhere('contact_name', 'like', $like));
        }

        return $query;
    }
}
