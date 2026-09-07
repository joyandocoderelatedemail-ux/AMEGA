<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use Illuminate\Contracts\View\View;

class TicketingDashboardController extends Controller
{
    /**
     * Display the ticketing portal dashboard with stats and recent tickets.
     */
    public function index(): View
    {
        $stats = [
            'totalTickets' => TicketBooking::count(),
            'domesticTickets' => TicketBooking::where('travel_type', 'domestic')->count(),
            'totalPassengers' => (int) TicketBooking::sum('total_passengers'),
            'pendingTickets' => TicketBooking::where('status', 'pending')->count(),
        ];

        $recentTickets = TicketBooking::with(['passengers.documents', 'createdBy'])
            ->latest()
            ->limit(6)
            ->get();

        return view('ticketing.dashboard', compact('stats', 'recentTickets'));
    }
}
