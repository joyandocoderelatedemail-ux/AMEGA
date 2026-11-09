<?php

namespace App\Support;

use App\Models\Scopes\OwnFilesScope;
use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ticket bookings in which a passenger "Needs Visa Assistance" or "Needs Passport Assistance": the work the
 * Ticketing desk hands to the Visa Assistance desk. They show in that desk's notification bell until an
 * officer opens the referrals page, and stay listed there until the ticket is issued or the booking cancelled.
 */
class VisaReferrals
{
    private const LATEST = 5;

    /** Active bookings with at least one passenger who needs visa or passport help (quotations are not bookings yet). */
    public static function query(): Builder
    {
        return TicketBooking::withoutGlobalScope(OwnFilesScope::class)
            ->where(fn (Builder $q) => $q->whereNull('is_quotation')->orWhere('is_quotation', false))
            ->whereNotIn('status', [TicketBooking::STATUS_ISSUED, TicketBooking::STATUS_CANCELLED])
            ->whereHas('passengers', fn (Builder $p) => $p
                ->where('visa_status', 'needs_assistance')
                ->orWhere('needs_passport_assistance', true));
    }

    /** What help a booking's passengers need: 'visa', 'passport' or both. */
    public static function needs(TicketBooking $ticket): array
    {
        return array_values(array_filter([
            $ticket->passengers->contains('visa_status', 'needs_assistance') ? 'visa' : null,
            $ticket->passengers->contains('needs_passport_assistance', true) ? 'passport' : null,
        ]));
    }

    /** "visa assistance", "passport assistance" or "visa and passport assistance". */
    public static function label(TicketBooking $ticket): string
    {
        return implode(' and ', self::needs($ticket)).' assistance';
    }

    /**
     * The Visa Assistance desk's bell: new referrals first, then (for approvers and admins) the usual admin
     * notifications.
     *
     * @return array{total: int, latest: list<array<string, string>>}
     */
    public static function bell(User $user): array
    {
        $unseen = self::query()->whereNull('visa_referral_seen_at');
        $total = (clone $unseen)->count();

        $items = (clone $unseen)->with('passengers')->latest()->limit(self::LATEST)->get()
            ->map(fn (TicketBooking $ticket): array => [
                'type' => 'referral',
                'title' => "{$ticket->booking_reference}: client needs ".self::label($ticket),
                'detail' => collect([$ticket->contact_name, $ticket->destination])->filter()->implode(' · '),
                'when' => $ticket->created_at?->diffForHumans() ?? '',
                'url' => route('visa.referrals'),
            ])->all();

        if (AdminNotifications::shown($user)) {
            $admin = AdminNotifications::for($user);
            $total += $admin['total'];
            $items = [...$items, ...$admin['latest']];
        }

        return ['total' => $total, 'latest' => array_slice($items, 0, self::LATEST)];
    }

    /** Referrals the desk has not opened yet. */
    public static function unseenCount(): int
    {
        return self::query()->whereNull('visa_referral_seen_at')->count();
    }

    /** The officer has opened the list: clear the bell. */
    public static function markSeen(): void
    {
        self::query()->whereNull('visa_referral_seen_at')->update(['visa_referral_seen_at' => now()]);
    }

    /** @return list<TicketPassenger> */
    public static function passengers(TicketBooking $ticket, string $need): array
    {
        return $ticket->passengers->filter(fn (TicketPassenger $p) => $need === 'visa'
            ? $p->visa_status === 'needs_assistance'
            : (bool) $p->needs_passport_assistance)->values()->all();
    }
}
