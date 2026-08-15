<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('travelPackage', 'user');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'payment_status' => 'required|in:unpaid,deposit_paid,fully_paid',
        ]);

        $oldStatus = $booking->status;
        $booking->update($validated);

        ActivityLogger::log(
            'Bookings',
            'UPDATE_STATUS',
            "Updated booking {$booking->booking_reference} status from '{$oldStatus}' to '{$validated['status']}' ({$validated['payment_status']})",
            ['booking_reference' => $booking->booking_reference, 'status' => $validated['status']]
        );

        return back()->with('success', "Booking {$booking->booking_reference} status updated!");
    }

    public function destroy(Booking $booking)
    {
        $ref = $booking->booking_reference;
        $booking->delete();

        ActivityLogger::log('Bookings', 'DELETE', "Deleted booking record {$ref}");

        return redirect()->route('admin.bookings.index')
            ->with('success', 'Booking deleted successfully!');
    }
}
