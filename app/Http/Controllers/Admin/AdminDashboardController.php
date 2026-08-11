<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Destination;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\TravelPackage;
use App\Models\User;

class AdminDashboardController extends Controller
{
    /**
     * Display the main administrator dashboard overview.
     */
    public function index()
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

        $recentBookings = Booking::with('travelPackage')->latest()->take(5)->get();
        $recentInquiries = Inquiry::latest()->take(5)->get();
        $recentPackages = TravelPackage::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentBookings', 'recentInquiries', 'recentPackages'));
    }
}
