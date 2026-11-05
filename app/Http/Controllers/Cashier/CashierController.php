<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Models\VisaApplication;
use App\Services\ClientProfileService;
use App\Services\SrrvPaymentRecorder;
use App\Services\TicketPaymentRecorder;
use App\Services\VisaPaymentRecorder;
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
 * Visa Assistance files come here too once the admin approves them: the cashier
 * takes their down payment and their balance (the visa agent no longer does).
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

    /** Visa Assistance files: approved, with money to collect; or (?paid=1) paid in full. */
    public function visa(Request $request): View
    {
        $paid = $request->boolean('paid');
        $files = VisaApplication::withoutGlobalScope(OwnFilesScope::class)
            ->when($paid, fn ($q) => $q->where('total_amount', '>', 0)->whereColumn('amount_paid', '>=', 'total_amount'), fn ($q) => $q->awaitingPayment())
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('client_name', 'like', $like));
            })
            ->with('creator:id,name')
            ->orderByRaw('reviewed_at IS NULL')
            ->latest('reviewed_at')
            ->paginate(20)
            ->withQueryString();

        return view('cashier.visa-index', ['files' => $files, 'paid' => $paid]);
    }

    public function visaShow(int $file): View
    {
        $file = $this->visaFile($file);
        $file->load(['payments.receivedBy', 'creator', 'reviewedBy']);

        return view('cashier.visa-show', ['file' => $file, 'blockedReason' => VisaPaymentRecorder::blockedReason($file)]);
    }

    public function visaStore(Request $request, int $file, VisaPaymentRecorder $recorder): RedirectResponse
    {
        $file = $this->visaFile($file);

        if ($reason = VisaPaymentRecorder::blockedReason($file)) {
            return back()->with('error', $reason);
        }

        // The cashier confirms the money was received and the details are right, and is accountable for them.
        $validated = $request->validate(
            VisaPaymentRecorder::rules() + ['cashier_acknowledged' => ['accepted']],
            VisaPaymentRecorder::messages() + ['cashier_acknowledged.accepted' => 'Tick the acknowledgement to confirm the payment details are correct.'],
        );

        try {
            $recorder->record($file, $validated, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $file->refresh();

        return redirect()->route('cashier.visa.show', $file->id)->with('success', $file->outstandingBalance() > 0
            ? "Payment recorded on {$file->reference}. Balance: {$file->currency} ".number_format($file->outstandingBalance(), 2).'.'
            : "Payment recorded. {$file->reference} is paid in full.");
    }

    /** SRRV applications: approved, with money to collect; or (?paid=1) paid in full. */
    public function srrv(Request $request): View
    {
        $paid = $request->boolean('paid');
        $files = SrrvApplication::withoutGlobalScope(OwnFilesScope::class)
            ->when($paid, fn ($q) => $q->where('service_type', 'renewal_application')->where('service_fee', '>', 0)->whereColumn('amount_paid', '>=', 'service_fee'), fn ($q) => $q->awaitingPayment())
            ->when($request->filled('search'), function ($q) use ($request) {
                $like = '%'.addcslashes(trim((string) $request->input('search')), '%_\\').'%';
                $q->where(fn ($inner) => $inner->where('reference', 'like', $like)->orWhere('retiree_name', 'like', $like));
            })
            ->with('creator:id,name')
            ->orderByRaw('reviewed_at IS NULL')
            ->latest('reviewed_at')
            ->paginate(20)
            ->withQueryString();

        return view('cashier.srrv-index', ['files' => $files, 'paid' => $paid]);
    }

    public function srrvShow(int $file): View
    {
        $file = $this->srrvFile($file);
        $file->load(['payments.receivedBy', 'creator', 'reviewedBy']);

        return view('cashier.srrv-show', ['file' => $file, 'blockedReason' => SrrvPaymentRecorder::blockedReason($file)]);
    }

    public function srrvStore(Request $request, int $file, SrrvPaymentRecorder $recorder): RedirectResponse
    {
        $file = $this->srrvFile($file);

        if ($reason = SrrvPaymentRecorder::blockedReason($file)) {
            return back()->with('error', $reason);
        }

        // The cashier confirms the money was received and the details are right, and is accountable for them.
        $validated = $request->validate(
            SrrvPaymentRecorder::rules() + ['cashier_acknowledged' => ['accepted']],
            SrrvPaymentRecorder::messages() + ['cashier_acknowledged.accepted' => 'Tick the acknowledgement to confirm the payment details are correct.'],
        );

        try {
            $recorder->record($file, $validated, $request->user());
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $file->refresh();

        return redirect()->route('cashier.srrv.show', $file->id)->with('success', $file->outstandingBalance() > 0
            ? "Payment recorded on {$file->reference}. Balance: {$file->currency} ".number_format($file->outstandingBalance(), 2).'.'
            : "Payment recorded. {$file->reference} is paid in full.");
    }

    /** An SRRV application by id, whoever opened it (the cashier sees every desk's approved files). */
    private function srrvFile(int $id): SrrvApplication
    {
        return SrrvApplication::withoutGlobalScope(OwnFilesScope::class)->where('service_type', 'renewal_application')->findOrFail($id);
    }

    /** A visa file by id, whoever opened it (the cashier sees every desk's approved files). */
    private function visaFile(int $id): VisaApplication
    {
        return VisaApplication::withoutGlobalScope(OwnFilesScope::class)->findOrFail($id);
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
