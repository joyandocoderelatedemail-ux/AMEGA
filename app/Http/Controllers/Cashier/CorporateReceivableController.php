<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\CorporatePayment;
use App\Models\TicketBooking;
use App\Services\ActivityLogger;
use App\Support\CorporateReceivables;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Corporate accounts receivable at the cashier: charge an approved booking to its company's credit account
 * (so the ticket can be issued unpaid), see what each company owes and how late, record the company's
 * payments, and print its monthly statement.
 */
class CorporateReceivableController extends Controller
{
    /** The aging report: every company with a credit account or a balance. */
    public function index(): View
    {
        $rows = CorporateAccount::query()
            ->where(fn ($q) => $q->where('credit_enabled', true)
                ->orWhereIn('id', TicketBooking::withoutGlobalScopes()->onAccount()->select('corporate_account_id')))
            ->orderBy('company_name')
            ->get()
            ->map(fn (CorporateAccount $company) => ['company' => $company, 'aging' => CorporateReceivables::aging($company)]);

        $totals = collect([...array_keys(CorporateReceivables::BUCKETS), 'total'])
            ->mapWithKeys(fn (string $key) => [$key => round((float) $rows->sum(fn (array $row) => $row['aging'][$key]), 2)]);

        return view('cashier.receivables.index', compact('rows', 'totals'));
    }

    /** One company's account: what it owes, ticket by ticket, and the payments it has made. */
    public function show(CorporateAccount $corporate): View
    {
        return view('cashier.receivables.show', [
            'company' => $corporate,
            'items' => CorporateReceivables::openItems($corporate),
            'aging' => CorporateReceivables::aging($corporate),
            'available' => CorporateReceivables::available($corporate),
            'payments' => $corporate->payments()->with(['receivedBy:id,name', 'entries.booking'])->latest('received_at')->limit(20)->get(),
        ]);
    }

    /** A payment from the company, applied to its unpaid tickets oldest due first. */
    public function store(Request $request, CorporateAccount $corporate): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'method' => ['required', Rule::in(array_keys(CorporatePayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'received_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'acknowledged' => ['accepted'],
        ], [
            'amount.required' => 'Enter the amount received.',
            'method.required' => 'Choose how the company paid.',
            'acknowledged.accepted' => 'Tick the acknowledgement to confirm the amount received.',
        ]);

        try {
            $payment = CorporateReceivables::receive(
                $corporate,
                (float) $validated['amount'],
                $validated['method'],
                $request->user(),
                $validated['reference'] ?? null,
                filled($validated['received_at'] ?? null) ? Carbon::parse($validated['received_at'])->setTimeFrom(now()) : null,
                $validated['note'] ?? null,
            );
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $applied = $payment->entries()->with('booking')->get()->map(fn ($entry) => $entry->booking?->booking_reference)->filter()->implode(', ');
        ActivityLogger::log('Cashier', 'CORPORATE_PAYMENT', 'Received ₱'.number_format((float) $payment->amount, 2)." by {$payment->methodLabel()} from {$corporate->company_name} ({$payment->number()}), applied to {$applied}");

        return redirect()->route('cashier.receivables.show', $corporate)
            ->with('success', "{$payment->number()}: ₱".number_format((float) $payment->amount, 2)." received and applied to {$applied}.");
    }

    /** The account's whole history: every charge, payment and change of credit terms, with the running balance. */
    public function history(CorporateAccount $corporate): View
    {
        $history = CorporateReceivables::history($corporate);

        return view('cashier.receivables.history', [
            'company' => $corporate,
            'history' => $history->reverse()->values(),
            'charged' => round((float) $history->sum('charge'), 2),
            'paid' => round((float) $history->sum('payment'), 2),
            'outstanding' => CorporateReceivables::outstanding($corporate),
        ]);
    }

    /** The company's statement of account for a month (this month unless one is picked). */
    public function statement(Request $request, CorporateAccount $corporate): View
    {
        $picked = (string) $request->input('month');
        $month = preg_match('/^\d{4}-\d{2}$/', $picked) ? Carbon::createFromFormat('Y-m-d', $picked.'-01') : now();

        return view('cashier.receivables.statement', [
            'company' => $corporate,
            'statement' => CorporateReceivables::statement($corporate, $month),
            'items' => CorporateReceivables::openItems($corporate),
            'outstanding' => CorporateReceivables::outstanding($corporate),
        ]);
    }

    /**
     * Charge an approved booking to its company's account: the ticket can then be issued unpaid. Going over
     * the company's credit limit takes an admin, who must say so (and it is logged).
     */
    public function charge(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($reason = CorporateReceivables::chargeRefusal($ticket)) {
            return back()->with('error', $reason);
        }

        $company = $ticket->corporateAccount;
        $over = CorporateReceivables::overLimitBy($ticket);

        if ($over > 0) {
            if (! $request->user()->isAdmin()) {
                return back()->with('error', "This would put {$company->company_name} ₱".number_format($over, 2).' over its credit limit. Only an admin can approve going over the limit.');
            }
            if (! $request->boolean('override_limit')) {
                return back()->with('error', "This would put {$company->company_name} ₱".number_format($over, 2).' over its credit limit. Tick the override box to charge it anyway.');
            }
        }

        CorporateReceivables::charge($ticket, $request->user());

        ActivityLogger::log('Cashier', 'CHARGE_TO_ACCOUNT', 'Charged ₱'.number_format((float) $ticket->ar_amount, 2)." on {$ticket->booking_reference} to {$company->company_name}'s account, due {$ticket->ar_due_date->format('M j, Y')}"
            .($over > 0 ? ' (credit limit overridden by ₱'.number_format($over, 2).')' : ''), ['ticket_id' => $ticket->id]);

        return back()->with('success', "Charged to {$company->company_name}'s account, due {$ticket->ar_due_date->format('M j, Y')}. The agent can now issue the ticket.");
    }
}
