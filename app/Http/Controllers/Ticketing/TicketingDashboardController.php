<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Models\TicketFlightChange;
use Illuminate\Contracts\View\View;

class TicketingDashboardController extends Controller
{
    /**
     * Display the ticketing portal dashboard with stats and recent tickets.
     *
     * Every figure is derived from a query; where there is no data the view
     * renders an empty state rather than a placeholder. The domestic and
     * international counts are reported separately because the module issues
     * both — the dashboard previously only surfaced the domestic side.
     */
    public function index(): View
    {
        // Bookings only: quotations are priced trips, counted in their own card below.
        $bookings = fn () => TicketBooking::where('is_quotation', false);

        $statusCounts = $bookings()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $settled = (int) $statusCounts->only(['confirmed', 'issued'])->sum();
        $totalTickets = $bookings()->count();
        $totalPassengers = (int) $bookings()->sum('total_passengers');

        // Departures still ahead of us, and the subset that needs attention now.
        $upcoming = $bookings()->whereDate('departure_date', '>=', now()->toDateString())->count();
        $departingSoon = $bookings()->whereDate('departure_date', '>=', now()->toDateString())
            ->whereDate('departure_date', '<=', now()->addDays(7)->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $stats = [
            'totalTickets' => $totalTickets,
            'domesticTickets' => $bookings()->where('travel_type', 'domestic')->count(),
            'internationalTickets' => $bookings()->where('travel_type', 'international')->count(),
            'totalPassengers' => $totalPassengers,
            'pendingTickets' => (int) ($statusCounts['pending'] ?? 0),
            'settledTickets' => $settled,
            'cancelledTickets' => (int) ($statusCounts['cancelled'] ?? 0),
            'upcomingDepartures' => $upcoming,
            'departingSoon' => $departingSoon,
            'bookedValue' => (float) $bookings()->whereNot('status', 'cancelled')->sum('total_amount'),
            'collected' => (float) $bookings()->whereNot('status', 'cancelled')->sum('amount_paid'),
            'avgPartySize' => $totalTickets > 0 ? round($totalPassengers / $totalTickets, 1) : 0.0,
        ];

        // The listing renders columns straight off the ticket row, so no
        // relations are eager-loaded here — the previous `passengers.documents`
        // and `createdBy` loads were never read by the view.
        $recentTickets = $bookings()->latest()->limit(6)->get();

        // Flights in the coming week, soonest first, for staff to remind.
        $departingSoonTickets = $bookings()->whereDate('departure_date', '>=', now()->toDateString())
            ->whereDate('departure_date', '<=', now()->addDays(7)->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('departure_date')
            ->limit(10)
            ->get();

        // Flights the airline changed where the client has not been told yet. Tickets
        // that are not the officer's own never appear, whatever the change.
        $flightChangesToTell = TicketFlightChange::whereNull('client_notified_at')
            ->whereHas('ticket')
            ->with('ticket')
            ->latest()
            ->limit(10)
            ->get();

        // Bookings an admin returned to this agent, newest first, with the reason: they head the page.
        $returnedTickets = TicketBooking::where('created_by', auth()->id())
            ->where('approval_status', TicketBooking::APPROVAL_REJECTED)
            ->where('is_quotation', false)
            ->whereNot('status', TicketBooking::STATUS_CANCELLED)
            ->with('reviewedBy:id,name')
            ->latest('reviewed_at')
            ->get();

        // Airline holds to ticket within 24 hours, or already past their deadline.
        $deadlineTickets = TicketBooking::deadlineDue()->limit(10)->get();

        // Where every booking stands on its way agent → admin → cashier → agent (TicketBooking::STAGES).
        $flow = collect(['returned', 'approval', 'cashier', 'ready', 'issued'])
            ->mapWithKeys(fn (string $stage): array => [$stage => TicketBooking::inStage($stage)->count()])
            ->all();

        // Open quotations: how many, what they are worth, and the latest few.
        $quotes = [
            'count' => TicketBooking::inStage('quotation')->count(),
            'value' => (float) TicketBooking::inStage('quotation')->sum('total_amount'),
            'latest' => TicketBooking::inStage('quotation')->latest()->limit(3)->get(),
        ];

        return view('ticketing.dashboard', compact('stats', 'flow', 'quotes', 'recentTickets', 'departingSoonTickets', 'flightChangesToTell', 'returnedTickets', 'deadlineTickets'));
    }
}
