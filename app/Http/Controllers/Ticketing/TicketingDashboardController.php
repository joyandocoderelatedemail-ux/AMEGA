<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
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
        $statusCounts = TicketBooking::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $settled = (int) $statusCounts->only(['confirmed', 'issued'])->sum();
        $totalTickets = TicketBooking::count();
        $totalPassengers = (int) TicketBooking::sum('total_passengers');

        // Departures still ahead of us, and the subset that needs attention now.
        $upcoming = TicketBooking::whereDate('departure_date', '>=', now()->toDateString())->count();
        $departingSoon = TicketBooking::whereDate('departure_date', '>=', now()->toDateString())
            ->whereDate('departure_date', '<=', now()->addDays(7)->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $stats = [
            'totalTickets' => $totalTickets,
            'domesticTickets' => TicketBooking::where('travel_type', 'domestic')->count(),
            'internationalTickets' => TicketBooking::where('travel_type', 'international')->count(),
            'totalPassengers' => $totalPassengers,
            'pendingTickets' => (int) ($statusCounts['pending'] ?? 0),
            'settledTickets' => $settled,
            'cancelledTickets' => (int) ($statusCounts['cancelled'] ?? 0),
            'upcomingDepartures' => $upcoming,
            'departingSoon' => $departingSoon,
            'bookedValue' => (float) TicketBooking::whereNot('status', 'cancelled')->sum('total_amount'),
            'avgPartySize' => $totalTickets > 0 ? round($totalPassengers / $totalTickets, 1) : 0.0,
        ];

        // The listing renders columns straight off the ticket row, so no
        // relations are eager-loaded here — the previous `passengers.documents`
        // and `createdBy` loads were never read by the view.
        $recentTickets = TicketBooking::latest()->limit(6)->get();

        // Flights in the coming week, soonest first, for staff to remind.
        $departingSoonTickets = TicketBooking::whereDate('departure_date', '>=', now()->toDateString())
            ->whereDate('departure_date', '<=', now()->addDays(7)->toDateString())
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('departure_date')
            ->limit(10)
            ->get();

        return view('ticketing.dashboard', compact('stats', 'recentTickets', 'departingSoonTickets'));
    }
}
