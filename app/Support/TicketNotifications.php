<?php

namespace App\Support;

use App\Models\LocalTourBooking;
use App\Models\Scopes\OwnFilesScope;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Database\Eloquent\Builder;

/**
 * The ticket handoffs on the notification bells: an admin is told a booking is waiting for approval,
 * the cashier that an approved booking is waiting for payment, and the agent that their booking was
 * returned or is paid in full and ready to issue. Each one stops counting once that person opens the
 * booking (TicketBooking::markSeen) or it moves on; the next handoff makes it count again.
 */
class TicketNotifications
{
    /** How many recent items a bell lists. */
    private const LATEST = 5;

    /** Bookings waiting for an admin that no admin has opened yet. */
    public static function forApprovers(): Builder
    {
        return TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->where('approval_status', TicketBooking::APPROVAL_PENDING)
            ->whereNull('approval_seen_at');
    }

    /**
     * @return list<array{type: string, at: mixed, title: string, detail: string, url: string}>
     */
    public static function approvalItems(): array
    {
        return self::forApprovers()->with('createdBy:id,name')->latest('approval_requested_at')->limit(self::LATEST)->get()
            ->map(fn (TicketBooking $ticket): array => [
                'type' => 'approval',
                'at' => $ticket->approval_requested_at ?? $ticket->created_at,
                'title' => "{$ticket->booking_reference} is waiting for approval",
                'detail' => collect([$ticket->contact_name, $ticket->createdBy?->name ? 'by '.$ticket->createdBy->name : null])->filter()->implode(' · '),
                'url' => route('admin.ticket-approvals.show', $ticket),
            ])->all();
    }

    /**
     * How many approved files wait for the cashier, by service: the badges on the cashier's tabs.
     *
     * @return array{ticketing: int, visa: int, srrv: int, local_tours: int}
     */
    public static function waitingForCashier(): array
    {
        return [
            'ticketing' => TicketBooking::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment()->count(),
            'visa' => VisaApplication::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment()->count(),
            'srrv' => SrrvApplication::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment()->count(),
            'local_tours' => LocalTourBooking::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment()->count(),
        ];
    }

    /**
     * The cashier's bell: approved bookings with money to collect that the cashier has not opened.
     *
     * @return array{total: int, latest: list<array{type: string, title: string, detail: string, when: string, url: string}>}
     */
    public static function forCashier(): array
    {
        $query = TicketBooking::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment()->whereNull('cashier_seen_at');

        $items = (clone $query)->latest('reviewed_at')->limit(self::LATEST)->get()
            ->map(fn (TicketBooking $ticket): array => [
                'type' => 'payment',
                'at' => $ticket->reviewed_at ?? $ticket->updated_at,
                'title' => "{$ticket->booking_reference} is approved for payment",
                'detail' => collect(['Ticketing', $ticket->contact_name, '₱'.number_format($ticket->balanceDue(), 2).' to collect'])->filter()->implode(' · '),
                'url' => route('cashier.payments.show', $ticket),
            ]);

        // Visa Assistance files the admin approved, with money to collect, wait on the cashier too.
        $visa = VisaApplication::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment();
        $visaItems = (clone $visa)->latest('reviewed_at')->limit(self::LATEST)->get()
            ->map(fn (VisaApplication $file): array => [
                'type' => 'payment',
                'at' => $file->reviewed_at ?? $file->updated_at,
                'title' => "{$file->reference} (visa) is approved for payment",
                'detail' => collect(['Visa Assistance', $file->client_name, ($file->currency ?: 'PHP').' '.number_format($file->outstandingBalance(), 2).' to collect'])->filter()->implode(' · '),
                'url' => route('cashier.visa.show', $file->id),
            ]);

        // New SRRV applications the admin approved, with money to collect.
        $srrv = SrrvApplication::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment();
        $srrvItems = (clone $srrv)->latest('reviewed_at')->limit(self::LATEST)->get()
            ->map(fn (SrrvApplication $file): array => [
                'type' => 'payment',
                'at' => $file->reviewed_at ?? $file->updated_at,
                'title' => "{$file->reference} (SRRV) is approved for payment",
                'detail' => collect(['PRA-SRRV', $file->retiree_name, ($file->currency ?: 'PHP').' '.number_format($file->outstandingBalance(), 2).' to collect'])->filter()->implode(' · '),
                'url' => route('cashier.srrv.show', $file->id),
            ]);

        // Local tour bookings an approver approved, with money to collect.
        $tours = LocalTourBooking::withoutGlobalScope(OwnFilesScope::class)->awaitingPayment();
        $tourItems = (clone $tours)->latest('reviewed_at')->limit(self::LATEST)->get()
            ->map(fn (LocalTourBooking $booking): array => [
                'type' => 'payment',
                'at' => $booking->reviewed_at ?? $booking->updated_at,
                'title' => "{$booking->reference} (local tour) is approved for payment",
                'detail' => collect(['Local Tours', $booking->lead_guest_name, '₱'.number_format($booking->outstandingBalance(), 2).' to collect'])->filter()->implode(' · '),
                'url' => route('cashier.local-tours.show', $booking->id),
            ]);

        return self::payload($query->count() + $visa->count() + $srrv->count() + $tours->count(), [...$items->all(), ...$visaItems->all(), ...$srrvItems->all(), ...$tourItems->all()]);
    }

    /**
     * The agent's bell: their own bookings that were returned, or are paid in full and ready to issue.
     *
     * @return array{total: int, latest: list<array{type: string, title: string, detail: string, when: string, url: string}>}
     */
    public static function forAgent(User $user): array
    {
        $query = TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->where('created_by', $user->id)
            ->whereNull('agent_seen_at')
            ->whereNotIn('status', [TicketBooking::STATUS_ISSUED, TicketBooking::STATUS_CANCELLED])
            ->where(fn (Builder $inner) => $inner
                ->where('approval_status', TicketBooking::APPROVAL_REJECTED)
                ->orWhere(fn (Builder $paid) => $paid
                    // Paid in full, or charged to the company's account: either way it is ready to issue.
                    ->where(fn (Builder $money) => $money->where('payment_status', TicketBooking::PAYMENT_FULL)->orWhereNotNull('charged_to_account_at'))
                    ->where(fn (Builder $approved) => $approved->whereNull('approval_status')->orWhere('approval_status', TicketBooking::APPROVAL_APPROVED))));

        $items = (clone $query)->with('reviewedBy:id,name')->latest('updated_at')->limit(self::LATEST)->get()
            ->map(fn (TicketBooking $ticket): array => $ticket->isRejected()
                ? [
                    'type' => 'returned',
                    'at' => $ticket->reviewed_at ?? $ticket->updated_at,
                    'title' => "{$ticket->booking_reference} was returned to you",
                    'detail' => $ticket->review_note ?: ($ticket->reviewedBy?->name ? 'by '.$ticket->reviewedBy->name : ''),
                    'url' => route('ticketing.tickets.show', $ticket),
                ]
                : [
                    'type' => 'paid',
                    'at' => $ticket->paid_at ?? $ticket->updated_at,
                    'title' => $ticket->isFullyPaid() ? "{$ticket->booking_reference} is paid in full" : "{$ticket->booking_reference} was charged to the company's account",
                    'detail' => collect([$ticket->contact_name, 'ready to issue'])->filter()->implode(' · '),
                    'url' => route('ticketing.tickets.show', $ticket),
                ]);

        // Their quotations ending today or tomorrow (or already ended), until they open them.
        $quotes = TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->where('created_by', $user->id)
            ->quoteEnding()
            ->whereNull('quote_expiry_seen_at');

        $quoteItems = (clone $quotes)->orderBy('quote_valid_until')->limit(self::LATEST)->get()
            ->map(fn (TicketBooking $ticket): array => [
                'type' => 'quote',
                // From the morning before it ends, when the reminder starts.
                'at' => $ticket->quote_valid_until->copy()->subDay()->setTime(8, 0),
                'title' => "Quotation {$ticket->booking_reference} {$ticket->quoteEndingLabel()}",
                'detail' => collect([$ticket->contact_name, $ticket->destination])->filter()->implode(' · '),
                'url' => route('ticketing.tickets.show', $ticket),
            ]);

        return self::payload($query->count() + $quotes->count(), [...$items->all(), ...$quoteItems->all()]);
    }

    /**
     * @param  list<array{type: string, at: mixed, title: string, detail: string, url: string}>  $items
     * @return array{total: int, latest: list<array{type: string, title: string, detail: string, when: string, url: string}>}
     */
    private static function payload(int $total, array $items): array
    {
        return [
            'total' => $total,
            'latest' => collect($items)->sortByDesc('at')->take(self::LATEST)
                ->map(fn (array $item): array => ['when' => $item['at']?->diffForHumans() ?? ''] + collect($item)->except('at')->all())
                ->values()->all(),
        ];
    }
}
