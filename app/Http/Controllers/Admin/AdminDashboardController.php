<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;
use App\Services\DepartmentReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * Display the main administrator dashboard overview.
     *
     * Every figure handed to the view is derived from the database. Where there
     * is no data yet the honest answer is zero — the dashboard renders an empty
     * state rather than a plausible-looking placeholder, because an operations
     * screen that invents numbers is worse than one that admits it has none.
     */
    public function index(Request $request, DepartmentReportService $departmentReports): View
    {
        $stats = [
            'total_bookings' => Booking::count(),
            'total_inquiries' => Inquiry::count(),
            'total_packages' => TravelPackage::count(),
            'total_destinations' => Destination::count(),
            'total_users' => User::count(),
            'total_services' => Service::count(),
            'total_testimonials' => Testimonial::count(),
        ];

        $bookingStatusCounts = [
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
        ];

        $paymentStatusCounts = [
            'fully_paid' => Booking::where('payment_status', 'fully_paid')->count(),
            'deposit_paid' => Booking::where('payment_status', 'deposit_paid')->count(),
            'unpaid' => Booking::where('payment_status', 'unpaid')->count(),
        ];

        $ticketBookingsCount = TicketBooking::count();
        $totalBookingsCount = $stats['total_bookings'] + $ticketBookingsCount;
        $totalPassengers = (int) Booking::sum('number_of_passengers') + (int) TicketBooking::sum('total_passengers');

        // Share of all enquiries that turned into a booking.
        $funnelTotal = $stats['total_bookings'] + $stats['total_inquiries'];
        $conversionRate = $funnelTotal > 0
            ? round(($stats['total_bookings'] / $funnelTotal) * 100, 1)
            : 0.0;

        $avgPartySize = $totalBookingsCount > 0
            ? round($totalPassengers / $totalBookingsCount, 1)
            : 0.0;

        // Six-month trajectory. Months with no activity report zero.
        $monthlyTrend = [];

        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            $packageBookings = Booking::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);
            $ticketBookings = TicketBooking::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);

            $packageCount = (clone $packageBookings)->count();
            $ticketCount = (clone $ticketBookings)->count();

            $revenue = (float) (clone $packageBookings)->sum('amount_due')
                + (float) (clone $ticketBookings)->sum('total_amount');

            $monthlyTrend[] = [
                'month' => $date->format('M'),
                'year' => $date->format('Y'),
                'count' => $packageCount + $ticketCount,
                'pax' => (int) (clone $packageBookings)->sum('number_of_passengers')
                    + (int) (clone $ticketBookings)->sum('total_passengers'),
                'revenue' => $revenue,
                'revenue_k' => round($revenue / 1000, 1),
            ];
        }

        $analytics = [
            'booking_statuses' => $bookingStatusCounts,
            'payment_statuses' => $paymentStatusCounts,
            'payment_total' => array_sum($paymentStatusCounts),
            'ticket_bookings_count' => $ticketBookingsCount,
            'total_bookings_all' => $totalBookingsCount,
            'total_passengers' => $totalPassengers,
            'avg_party_size' => $avgPartySize,
            'conversion_rate' => $conversionRate,
            'monthly_trend' => $monthlyTrend,
            'revenue_total' => (float) Booking::sum('amount_due') + (float) TicketBooking::sum('total_amount'),
        ];

        $recentBookings = Booking::with('travelPackage')->latest()->take(6)->get();
        $recentInquiries = Inquiry::latest()->take(6)->get();

        // One card per desk the viewer can open; agents see only their own files.
        $departments = $departmentReports->forUser($request->user());

        return view('admin.dashboard', compact('stats', 'analytics', 'recentBookings', 'recentInquiries', 'departments'));
    }
}
