<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\ActivityLogger;
use App\Support\DateRange;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = Booking::with('travelPackage', 'user', 'ticketBooking', 'contactedBy');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        // The date range picked on the page (App\Support\DateRange).
        DateRange::fromRequest($request)->apply($query);
        $bookings = $query->latest()->paginate(15)->withQueryString();

        // Opening the list reads what it shows: those stop counting on the badges, and are marked
        // New on this visit.
        $newIds = $bookings->getCollection()->whereNull('read_at')->pluck('id')->all();
        if ($newIds !== []) {
            Booking::whereIn('id', $newIds)->update(['read_at' => now()]);
        }

        return view('admin.bookings.index', compact('bookings', 'newIds'));
    }

    /**
     * Follow-up on a website booking, as on inquiries: whether the customer has been contacted, and the remarks.
     */
    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'remarks' => 'nullable|string|max:2000',
        ]);

        $contacted = $request->boolean('contacted');

        $attributes = ['remarks' => filled($validated['remarks'] ?? null) ? trim($validated['remarks']) : null];

        if ($contacted && ! $booking->contacted_at) {
            $attributes += ['contacted_at' => now(), 'contacted_by' => $request->user()->id];
        } elseif (! $contacted) {
            $attributes += ['contacted_at' => null, 'contacted_by' => null];
        }

        $booking->update($attributes);

        ActivityLogger::log('Bookings', 'UPDATE', "Follow-up on booking {$booking->booking_reference}: ".($contacted ? 'contacted' : 'not yet contacted'), ['booking_reference' => $booking->booking_reference]);

        return back()->with('success', 'Follow-up saved.');
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
