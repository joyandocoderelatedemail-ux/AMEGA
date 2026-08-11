<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\TravelPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    /**
     * Store a new booking request in the database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'travel_package_id' => 'required|exists:travel_packages,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:255',
            'travel_date' => 'required|date|after_or_equal:today',
            'number_of_passengers' => 'required|integer|min:1|max:50',
            'special_requests' => 'nullable|string',
        ]);

        $package = TravelPackage::findOrFail($validated['travel_package_id']);

        $reference = Booking::generateReference();

        $booking = Booking::create([
            'booking_reference' => $reference,
            'user_id' => Auth::id(),
            'travel_package_id' => $package->id,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'travel_date' => $validated['travel_date'],
            'number_of_passengers' => $validated['number_of_passengers'],
            'special_requests' => $validated['special_requests'] ?? null,
            'total_amount' => $package->price,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        return redirect()->route('bookings.confirmation', $booking->booking_reference)
            ->with('success', 'Your booking request has been submitted successfully!');
    }

    /**
     * Display official booking confirmation and printable receipt.
     */
    public function confirmation($reference)
    {
        $booking = Booking::with('travelPackage')->where('booking_reference', $reference)->firstOrFail();

        return view('bookings.confirmation', compact('booking'));
    }
}
