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
use App\Services\StaffActivityService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    /**
     * A range up to this many days is charted day by day; longer ones by month.
     */
    private const DAILY_CHART_MAX_DAYS = 62;

    /**
     * The most months a long range charts, counting back from its end.
     */
    private const CHART_MAX_MONTHS = 24;

    /**
     * Display the main administrator dashboard overview.
     *
     * Every figure handed to the view is derived from the database and limited
     * to the date range picked at the top of the page (all time by default).
     * Where there is no data the honest answer is zero — the dashboard renders
     * an empty state rather than a plausible-looking placeholder, because an
     * operations screen that invents numbers is worse than one that admits it
     * has none.
     */
    public function index(Request $request, DepartmentReportService $departmentReports, StaffActivityService $staffActivity): View
    {
        $range = DateRange::fromRequest($request);

        $bookings = fn () => $range->apply(Booking::query());
        $tickets = fn () => $range->apply(TicketBooking::query());

        $stats = [
            'total_bookings' => $bookings()->count(),
            'total_inquiries' => $range->apply(Inquiry::query())->count(),
            'total_packages' => TravelPackage::count(),
            'total_destinations' => Destination::count(),
            'total_users' => User::count(),
            'total_services' => Service::count(),
            'total_testimonials' => Testimonial::count(),
        ];

        $bookingStatusCounts = [
            'confirmed' => $bookings()->where('status', 'confirmed')->count(),
            'pending' => $bookings()->where('status', 'pending')->count(),
            'completed' => $bookings()->where('status', 'completed')->count(),
            'cancelled' => $bookings()->where('status', 'cancelled')->count(),
        ];

        $paymentStatusCounts = [
            'fully_paid' => $bookings()->where('payment_status', 'fully_paid')->count(),
            'deposit_paid' => $bookings()->where('payment_status', 'deposit_paid')->count(),
            'unpaid' => $bookings()->where('payment_status', 'unpaid')->count(),
        ];

        $ticketBookingsCount = $tickets()->count();
        $totalBookingsCount = $stats['total_bookings'] + $ticketBookingsCount;
        $totalPassengers = (int) $bookings()->sum('number_of_passengers') + (int) $tickets()->sum('total_passengers');

        // Share of all enquiries that turned into a booking.
        $funnelTotal = $stats['total_bookings'] + $stats['total_inquiries'];
        $conversionRate = $funnelTotal > 0
            ? round(($stats['total_bookings'] / $funnelTotal) * 100, 1)
            : 0.0;

        $avgPartySize = $totalBookingsCount > 0
            ? round($totalPassengers / $totalBookingsCount, 1)
            : 0.0;

        [$trend, $trendLabel] = $this->trend($range);

        $analytics = [
            'booking_statuses' => $bookingStatusCounts,
            'payment_statuses' => $paymentStatusCounts,
            'payment_total' => array_sum($paymentStatusCounts),
            'ticket_bookings_count' => $ticketBookingsCount,
            'total_bookings_all' => $totalBookingsCount,
            'total_passengers' => $totalPassengers,
            'avg_party_size' => $avgPartySize,
            'conversion_rate' => $conversionRate,
            'monthly_trend' => $trend,
            'trend_label' => $trendLabel,
            'revenue_total' => (float) $bookings()->sum('amount_due') + (float) $tickets()->sum('total_amount'),
        ];

        $recentBookings = $bookings()->with('travelPackage')->latest()->take(6)->get();
        $recentInquiries = $range->apply(Inquiry::query())->latest()->take(6)->get();

        // One card per desk the viewer can open; agents see only their own files.
        $departments = $departmentReports->forUser($request->user(), $range);

        // Staff accounts and what each did in the period, for admins only.
        $staff = $request->user()->isAdmin()
            ? $staffActivity->summaries($range, $request->query('staff'), $request->query('staff_role'), 8)->withQueryString()
            : null;

        return view('admin.dashboard', compact('stats', 'analytics', 'recentBookings', 'recentInquiries', 'departments', 'range', 'staff'));
    }

    /**
     * Bookings charted over the period: the last six months for all time,
     * day by day for a short range, month by month for a long one.
     *
     * @return array{0: list<array{month: string, year: string, count: int, pax: int, revenue: float, revenue_k: float}>, 1: string}
     */
    private function trend(DateRange $range): array
    {
        if ($range->isAllTime()) {
            $from = now()->subMonths(5)->startOfMonth();
            $to = now()->endOfMonth();
            $label = 'Last six months';
        } else {
            [$from, $to, $label] = [$range->from->copy(), $range->to->copy(), $range->label()];
        }

        $daily = ! $range->isAllTime() && $from->diffInDays($to) < self::DAILY_CHART_MAX_DAYS;

        if (! $daily) {
            $from = $from->copy()->startOfMonth();
            $earliest = $to->copy()->startOfMonth()->subMonths(self::CHART_MAX_MONTHS - 1);
            $from = $from->lt($earliest) ? $earliest : $from;
        }

        $bucketOf = fn (Carbon $date): string => $date->format($daily ? 'Y-m-d' : 'Y-m');

        $buckets = [];
        for ($cursor = $from->copy(); $cursor->lte($to); $daily ? $cursor->addDay() : $cursor->addMonthNoOverflow()) {
            $buckets[$bucketOf($cursor)] = [
                'month' => $cursor->format($daily ? 'M j' : 'M'),
                'year' => $cursor->format('Y'),
                'count' => 0,
                'pax' => 0,
                'revenue' => 0.0,
            ];
        }

        $rows = Booking::whereBetween('created_at', [$from, $to])
            ->get(['created_at', 'number_of_passengers', 'amount_due'])
            ->map(fn (Booking $b): array => [$b->created_at, (int) $b->number_of_passengers, (float) $b->amount_due])
            ->concat(TicketBooking::whereBetween('created_at', [$from, $to])
                ->get(['created_at', 'total_passengers', 'total_amount'])
                ->map(fn (TicketBooking $t): array => [$t->created_at, (int) $t->total_passengers, (float) $t->total_amount]));

        foreach ($rows as [$createdAt, $pax, $revenue]) {
            $key = $bucketOf($createdAt);

            if (isset($buckets[$key])) {
                $buckets[$key]['count']++;
                $buckets[$key]['pax'] += $pax;
                $buckets[$key]['revenue'] += $revenue;
            }
        }

        $trend = array_map(fn (array $bucket): array => $bucket + ['revenue_k' => round($bucket['revenue'] / 1000, 1)], array_values($buckets));

        return [$trend, $label];
    }
}
