<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\CorporateAccount;
use App\Models\CorporatePayment;
use App\Models\Scopes\OwnFilesScope;
use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Corporate accounts receivable (ticketing): a company with a credit account has its tickets charged to the
 * account and issued unpaid, each falling due after the company's net days. The company pays the account, and
 * a payment clears its unpaid tickets oldest due first.
 */
class CorporateReceivables
{
    /** Aging buckets, by days past the due date. */
    public const BUCKETS = ['current' => 'Current', 'd30' => '1-30 days', 'd60' => '31-60 days', 'd90' => '61-90 days', 'over' => 'Over 90 days'];

    /** Every booking of the company, whoever opened it. */
    private static function bookings(CorporateAccount $company): Builder
    {
        return TicketBooking::withoutGlobalScope(OwnFilesScope::class)->where('corporate_account_id', $company->id);
    }

    /**
     * The company's unpaid tickets on account, oldest due first.
     *
     * @return Collection<int, TicketBooking>
     */
    public static function openItems(CorporateAccount $company): Collection
    {
        return self::bookings($company)->onAccount()->orderBy('ar_due_date')->orderBy('id')->get();
    }

    public static function outstanding(CorporateAccount $company): float
    {
        return round((float) self::openItems($company)->sum(fn (TicketBooking $ticket) => $ticket->balanceDue()), 2);
    }

    /** What is left of the credit limit; null when the company has no limit. */
    public static function available(CorporateAccount $company): ?float
    {
        return $company->credit_limit === null ? null : round((float) $company->credit_limit - self::outstanding($company), 2);
    }

    /**
     * Why the booking cannot go on its company's account, or null. Going over the limit is not refused here:
     * see overLimitBy().
     */
    public static function chargeRefusal(TicketBooking $ticket): ?string
    {
        $company = $ticket->corporateAccount;

        return match (true) {
            $company === null => 'This booking is not under a company.',
            $company->isGroup() => "{$company->company_name} is a group, not a company: a group pays as it books.",
            ! $company->hasCredit() => "{$company->company_name} has no credit account. An admin can turn one on from the company's page.",
            $ticket->isChargedToAccount() => 'This booking is already on the company\'s account.',
            $ticket->isQuotation() => 'A quotation cannot be charged: convert it to a booking first.',
            $ticket->isCancelled() => 'A cancelled booking cannot be charged.',
            $ticket->isIssued() => 'This ticket has already been issued.',
            $ticket->isAwaitingApproval() || $ticket->isRejected() => 'The booking must be approved by an admin before it is charged.',
            $ticket->balanceDue() <= 0 => 'This booking is already paid in full.',
            default => null,
        };
    }

    /** By how much charging the booking would pass the company's credit limit (0 when within it). */
    public static function overLimitBy(TicketBooking $ticket): float
    {
        $available = $ticket->corporateAccount ? self::available($ticket->corporateAccount) : null;

        return $available === null ? 0.0 : max(0, round($ticket->balanceDue() - $available, 2));
    }

    /** Put the booking's balance on the company's account; it falls due after the company's net days. */
    public static function charge(TicketBooking $ticket, User $by): void
    {
        $ticket->forceFill([
            'charged_to_account_at' => now(),
            'charged_to_account_by' => $by->id,
            'ar_amount' => $ticket->balanceDue(),
            'ar_due_date' => today()->addDays((int) $ticket->corporateAccount->credit_terms_days),
            // The agent's bell: it is ready to issue.
            'agent_seen_at' => null,
        ])->save();
    }

    /**
     * Record a payment from the company and apply it to its unpaid tickets, oldest due first.
     *
     * @throws DomainException when there is nothing to pay or the amount is more than is owed
     */
    public static function receive(CorporateAccount $company, float $amount, string $method, User $by, ?string $reference = null, ?Carbon $receivedAt = null, ?string $note = null): CorporatePayment
    {
        $amount = round($amount, 2);

        return DB::transaction(function () use ($company, $amount, $method, $by, $reference, $receivedAt, $note): CorporatePayment {
            $open = self::openItems($company);
            $owed = round((float) $open->sum(fn (TicketBooking $ticket) => $ticket->balanceDue()), 2);

            if ($owed <= 0) {
                throw new DomainException("{$company->company_name} has nothing outstanding on its account.");
            }
            if ($amount - $owed > 0.004) {
                throw new DomainException('The amount is more than the ₱'.number_format($owed, 2).' the company owes.');
            }

            $payment = CorporatePayment::create([
                'corporate_account_id' => $company->id,
                'amount' => $amount,
                'method' => $method,
                'reference' => filled($reference) ? trim($reference) : null,
                'note' => filled($note) ? trim($note) : null,
                'received_at' => $receivedAt ?? now(),
                'received_by' => $by->id,
            ]);

            $left = $amount;
            foreach ($open as $ticket) {
                if ($left <= 0.004) {
                    break;
                }
                $part = min($left, $ticket->balanceDue());
                // A cheque is not one of the counter's methods: it goes on the ticket's ledger as "other".
                $entry = $ticket->receivePayment($part, isset(TicketPayment::METHODS[$method]) ? $method : 'other', $by, $payment->reference, $payment->received_at, "Company payment {$payment->number()}");
                $entry->forceFill(['corporate_payment_id' => $payment->id])->save();
                $left = round($left - $part, 2);
            }

            return $payment;
        });
    }

    /**
     * The company's unpaid amounts by how late they are.
     *
     * @return array{current: float, d30: float, d60: float, d90: float, over: float, total: float}
     */
    public static function aging(CorporateAccount $company): array
    {
        $row = array_fill_keys(array_keys(self::BUCKETS), 0.0);

        foreach (self::openItems($company) as $ticket) {
            $days = $ticket->daysOverdue();
            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => 'd30',
                $days <= 60 => 'd60',
                $days <= 90 => 'd90',
                default => 'over',
            };
            $row[$bucket] = round($row[$bucket] + $ticket->balanceDue(), 2);
        }

        return $row + ['total' => round(array_sum($row), 2)];
    }

    /**
     * Everything that has gone through the company's account: the tickets charged to it (cancelled ones are off
     * the account) and the money received on them after they were charged (a deposit taken before that is not
     * A/R). Shared by the statement and the history, so the two always agree.
     *
     * @return array{0: Collection<int, TicketBooking>, 1: Collection<int, TicketPayment>}
     */
    private static function activity(CorporateAccount $company): array
    {
        $charged = self::bookings($company)->whereNotNull('charged_to_account_at')->where('status', '!=', TicketBooking::STATUS_CANCELLED)
            ->with('chargedToAccountBy:id,name')
            ->orderBy('charged_to_account_at')->get();

        $byId = $charged->keyBy('id');
        $payments = TicketPayment::whereIn('ticket_booking_id', $byId->keys())
            ->where('type', TicketPayment::TYPE_PAYMENT)
            ->with('receivedBy:id,name')
            ->orderBy('received_at')->get()
            ->each(fn (TicketPayment $entry) => $entry->setRelation('booking', $byId[$entry->ticket_booking_id]))
            ->filter(fn (TicketPayment $entry) => $entry->created_at->gte($entry->booking->charged_to_account_at))
            ->values();

        return [$charged, $payments];
    }

    /**
     * The account's whole history, oldest first, with the balance after each line: every ticket charged, every
     * payment (a company payment is one line however many tickets it cleared), and every change an admin made
     * to the credit terms.
     *
     * @return Collection<int, array{date: Carbon, kind: string, reference: string, detail: string, applied: array<int, array{reference: string, amount: float, ticket: TicketBooking}>, by: ?string, charge: ?float, payment: ?float, balance: ?float, ticket: ?TicketBooking}>
     */
    public static function history(CorporateAccount $company): Collection
    {
        [$charged, $payments] = self::activity($company);
        $line = fn (array $row) => $row + ['applied' => [], 'by' => null, 'charge' => null, 'payment' => null, 'balance' => null, 'ticket' => null];
        $rows = collect();

        foreach ($charged as $ticket) {
            $rows->push($line([
                'date' => $ticket->charged_to_account_at,
                'kind' => 'charge',
                'reference' => $ticket->booking_reference,
                'detail' => trim("{$ticket->contact_name} · {$ticket->destination}", ' ·').($ticket->ar_due_date ? ' · due '.$ticket->ar_due_date->format('M j, Y') : ''),
                'by' => $ticket->chargedToAccountBy?->name,
                'charge' => (float) $ticket->ar_amount,
                'ticket' => $ticket,
            ]));
        }

        $companyPayments = CorporatePayment::with('receivedBy:id,name')
            ->whereIn('id', $payments->pluck('corporate_payment_id')->filter()->unique())->get()->keyBy('id');

        foreach ($payments->groupBy(fn (TicketPayment $entry) => $entry->corporate_payment_id ? 'cp'.$entry->corporate_payment_id : 'tp'.$entry->id) as $entries) {
            $first = $entries->first();
            $payment = $first->corporate_payment_id ? $companyPayments->get($first->corporate_payment_id) : null;

            $rows->push($line([
                'date' => $payment?->received_at ?? $first->received_at,
                'kind' => 'payment',
                'reference' => $payment?->number() ?? $first->booking->booking_reference,
                'detail' => $payment
                    ? $payment->methodLabel().($payment->reference ? ' · '.$payment->reference : '').($payment->note ? ' · '.$payment->note : '')
                    : 'Paid at the counter · '.$first->methodLabel().($first->reference ? ' · '.$first->reference : ''),
                'applied' => $payment ? $entries->map(fn (TicketPayment $entry) => [
                    'reference' => $entry->booking->booking_reference, 'amount' => (float) $entry->amount, 'ticket' => $entry->booking,
                ])->values()->all() : [],
                'by' => ($payment?->receivedBy ?? $first->receivedBy)?->name,
                'payment' => round((float) $entries->sum('amount'), 2),
                'ticket' => $payment ? null : $first->booking,
            ]));
        }

        $changes = ActivityLog::where('module', 'Corporate')->where('action', 'CREDIT')
            ->where('properties->corporate_id', $company->id)->orderBy('id')->get();

        foreach ($changes as $log) {
            $rows->push($line([
                'date' => $log->created_at,
                'kind' => 'terms',
                'reference' => 'Credit terms',
                'detail' => self::termsChange($log->properties['before'] ?? [], $log->properties['after'] ?? []),
                'by' => $log->user_name,
            ]));
        }

        // Oldest first; at the same moment a terms change goes first, then a charge, then the payment against it.
        $order = ['terms' => 0, 'charge' => 1, 'payment' => 2];
        $balance = 0.0;

        return $rows->sortBy([fn ($a, $b) => $a['date'] <=> $b['date'], fn ($a, $b) => $order[$a['kind']] <=> $order[$b['kind']]])
            ->values()
            ->map(function (array $row) use (&$balance) {
                if ($row['kind'] !== 'terms') {
                    $balance = round($balance + ($row['charge'] ?? 0) - ($row['payment'] ?? 0), 2);
                    $row['balance'] = $balance;
                }

                return $row;
            });
    }

    /** A credit-terms change in words, e.g. "Credit turned on · Net 30 days → Net 45 days · limit none → ₱200,000.00". */
    private static function termsChange(array $before, array $after): string
    {
        $money = fn ($v) => $v === null || $v === '' ? 'none' : '₱'.number_format((float) $v, 2);
        $fields = [
            'credit_terms_days' => fn ($v) => 'Net '.(int) $v.' days',
            'credit_limit' => fn ($v) => 'limit '.$money($v),
            'credit_interest_rate' => fn ($v) => 'interest '.($v === null || $v === '' ? 'none' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%/month'),
            'credit_grace_days' => fn ($v) => (int) $v.' grace days',
        ];

        $parts = [];
        if ((bool) ($before['credit_enabled'] ?? false) !== (bool) ($after['credit_enabled'] ?? false)) {
            $parts[] = ($after['credit_enabled'] ?? false) ? 'Credit turned on' : 'Credit turned off';
        }
        foreach ($fields as $key => $say) {
            $was = $before[$key] ?? null;
            $now = $after[$key] ?? null;
            if ((string) $was !== (string) $now) {
                $parts[] = $say($was).' → '.$say($now);
            }
        }

        return $parts ? ucfirst(implode(' · ', $parts)) : 'Saved with no change';
    }

    /**
     * A month's statement: what was owed going in, the tickets charged, the payments received, what is owed
     * going out. Cancelled bookings are off the account.
     *
     * @return array{start: Carbon, end: Carbon, opening: float, charges: Collection, payments: Collection, closing: float}
     */
    public static function statement(CorporateAccount $company, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        [$charged, $payments] = self::activity($company);

        $before = fn ($date) => $date->lt($start);
        $within = fn ($date) => $date->between($start, $end);

        $opening = (float) $charged->filter(fn ($t) => $before($t->charged_to_account_at))->sum('ar_amount')
            - (float) $payments->filter(fn ($p) => $before($p->received_at))->sum('amount');
        $charges = $charged->filter(fn ($t) => $within($t->charged_to_account_at))->values();
        $received = $payments->filter(fn ($p) => $within($p->received_at))->values();

        return [
            'start' => $start,
            'end' => $end,
            'opening' => round($opening, 2),
            'charges' => $charges,
            'payments' => $received,
            'closing' => round($opening + (float) $charges->sum('ar_amount') - (float) $received->sum('amount'), 2),
        ];
    }
}
