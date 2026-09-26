<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BookingAgreementController extends Controller
{
    /**
     * Show form to generate/customize a Booking Agreement for a Ticket.
     */
    public function create(TicketBooking $ticket): View|RedirectResponse
    {
        $ticket->load('passengers', 'travelPackage');

        // If an agreement already exists, redirect directly to view/edit it
        if ($ticket->bookingAgreement) {
            return redirect()->route('ticketing.agreements.show', $ticket->bookingAgreement);
        }

        // Auto-compile passenger full names
        $passengerNames = $ticket->passengers->pluck('full_name')->filter()->implode(', ');
        if (empty($passengerNames)) {
            $passengerNames = $ticket->contact_name;
        }

        // Auto-generate initial flight segments from trip info
        $flightSegments = [];
        $flightSegments[] = [
            'carrier' => 'Cebu Pacific / PAL / AirAsia',
            'flight_number' => '',
            'flight_class' => 'Economy',
            'day' => $ticket->departure_date ? $ticket->departure_date->format('d (D)') : '',
            'month' => $ticket->departure_date ? strtoupper($ticket->departure_date->format('M')) : '',
            'from_location' => $ticket->origin,
            'to_location' => $ticket->destination,
            'departure_time' => 'TBA',
            'arrival_time' => 'TBA',
            'flight_status' => 'HK / Confirmed',
        ];

        if ($ticket->trip_type === 'round_trip' && $ticket->return_date) {
            $flightSegments[] = [
                'carrier' => 'Cebu Pacific / PAL / AirAsia',
                'flight_number' => '',
                'flight_class' => 'Economy',
                'day' => $ticket->return_date->format('d (D)'),
                'month' => strtoupper($ticket->return_date->format('M')),
                'from_location' => $ticket->destination,
                'to_location' => $ticket->origin,
                'departure_time' => 'TBA',
                'arrival_time' => 'TBA',
                'flight_status' => 'HK / Confirmed',
            ];
        }

        // Add 2 extra blank rows for additional legs/connections
        for ($i = count($flightSegments); $i < 4; $i++) {
            $flightSegments[] = [
                'carrier' => '',
                'flight_number' => '',
                'flight_class' => '',
                'day' => '',
                'month' => '',
                'from_location' => '',
                'to_location' => '',
                'departure_time' => '',
                'arrival_time' => '',
                'flight_status' => '',
            ];
        }

        // Initial default pricing row
        $defaultDescription = $ticket->package_name
            ? "Tour Package: {$ticket->package_name} ({$ticket->origin} - {$ticket->destination})"
            : "Airfare: {$ticket->origin} to {$ticket->destination} (".($ticket->trip_type === 'round_trip' ? 'Round Trip' : 'One Way').')';

        $pricingItems = [
            [
                'airfare_description' => $defaultDescription,
                'price_details' => 'Quoted Agent Rate (Incl. Taxes & Surcharges)',
                'pax_count' => $ticket->total_passengers,
                'amount' => 0.00,
            ],
        ];

        return view('ticketing.agreements.create', [
            'ticket' => $ticket,
            'passengerNames' => $passengerNames,
            'flightSegments' => $flightSegments,
            'pricingItems' => $pricingItems,
            'currentUser' => Auth::user(),
        ]);
    }

    /**
     * Store the newly generated Booking Agreement.
     */
    public function store(Request $request, TicketBooking $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'client_names' => ['required', 'string', 'max:1000'],
            'agreement_date' => ['required', 'date'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'home_hotel_address' => ['nullable', 'string', 'max:500'],
            'has_baggage' => ['nullable', 'boolean'],
            'is_non_refundable' => ['nullable', 'boolean'],
            'is_non_rebookable' => ['nullable', 'boolean'],
            'has_meals' => ['nullable', 'boolean'],
            'with_rebooking_charge' => ['nullable', 'boolean'],
            'with_airport_transfer' => ['nullable', 'boolean'],
            'flight_segments' => ['nullable', 'array'],
            'pricing_items' => ['nullable', 'array'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:2000'],
            'agent_name' => ['nullable', 'string', 'max:255'],
            'passenger_client_name' => ['nullable', 'string', 'max:255'],
        ]);

        // Generate Agreement Number: AGR-YYYYMM-XXXX
        $timestamp = date('Ym');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        $agreementNumber = "AGR-{$timestamp}-{$random}";

        // Calculate total if not explicitly provided
        $calculatedTotal = 0;
        if (! empty($validated['pricing_items'])) {
            foreach ($validated['pricing_items'] as $item) {
                $amount = (float) ($item['amount'] ?? 0);
                $pax = (int) ($item['pax_count'] ?? 1);
                $calculatedTotal += ($amount * ($pax > 0 ? $pax : 1));
            }
        }
        $finalTotal = ! empty($validated['total_amount']) ? (float) $validated['total_amount'] : $calculatedTotal;

        $agreement = BookingAgreement::create([
            'ticket_booking_id' => $ticket->id,
            'agreement_number' => $agreementNumber,
            'client_names' => $validated['client_names'],
            'agreement_date' => $validated['agreement_date'],
            'contact_phone' => $validated['contact_phone'] ?? $ticket->contact_phone,
            'contact_email' => $validated['contact_email'] ?? $ticket->contact_email,
            'home_hotel_address' => $validated['home_hotel_address'] ?? null,
            'flight_segments' => $validated['flight_segments'] ?? [],
            'has_baggage' => $request->has('has_baggage') ? (bool) $request->input('has_baggage') : true,
            'is_non_refundable' => (bool) ($request->has('is_non_refundable')),
            'is_non_rebookable' => (bool) ($request->has('is_non_rebookable')),
            'has_meals' => $request->has('has_meals') ? (bool) $request->input('has_meals') : false,
            'with_rebooking_charge' => (bool) ($request->has('with_rebooking_charge')),
            'with_airport_transfer' => (bool) ($request->has('with_airport_transfer')),
            'pricing_items' => $validated['pricing_items'] ?? [],
            'total_amount' => $finalTotal,
            'payment_terms' => $validated['payment_terms'] ?? null,
            'agent_name' => $validated['agent_name'] ?? Auth::user()?->name ?? 'Amega Travel Agent',
            'passenger_client_name' => $validated['passenger_client_name'] ?? $ticket->contact_name,
            'status' => 'generated',
        ]);

        return redirect()->route('ticketing.agreements.show', $agreement)
            ->with('success', "Booking Agreement {$agreement->agreement_number} generated successfully!");
    }

    /**
     * Display the official printable Booking Agreement.
     */
    public function show(BookingAgreement $agreement): View
    {
        $this->ensureTicketVisible($agreement);

        $agreement->load('ticketBooking.passengers', 'ticketBooking.travelPackage');

        return view('ticketing.agreements.show', compact('agreement'));
    }

    /**
     * Show form to edit an existing agreement.
     */
    public function edit(BookingAgreement $agreement): View
    {
        $this->ensureTicketVisible($agreement);

        $agreement->load('ticketBooking');

        return view('ticketing.agreements.edit', compact('agreement'));
    }

    /**
     * Update the Booking Agreement.
     */
    public function update(Request $request, BookingAgreement $agreement): RedirectResponse
    {
        $this->ensureTicketVisible($agreement);

        $validated = $request->validate([
            'client_names' => ['required', 'string', 'max:1000'],
            'agreement_date' => ['required', 'date'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'home_hotel_address' => ['nullable', 'string', 'max:500'],
            'flight_segments' => ['nullable', 'array'],
            'pricing_items' => ['nullable', 'array'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:2000'],
            'agent_name' => ['nullable', 'string', 'max:255'],
            'passenger_client_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:draft,generated,confirmed,signed'],
        ]);

        $agreement->update([
            'client_names' => $validated['client_names'],
            'agreement_date' => $validated['agreement_date'],
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'home_hotel_address' => $validated['home_hotel_address'] ?? null,
            'flight_segments' => $validated['flight_segments'] ?? [],
            'has_baggage' => $request->has('has_baggage') ? (bool) $request->input('has_baggage') : true,
            'is_non_refundable' => (bool) ($request->has('is_non_refundable')),
            'is_non_rebookable' => (bool) ($request->has('is_non_rebookable')),
            'has_meals' => $request->has('has_meals') ? (bool) $request->input('has_meals') : false,
            'with_rebooking_charge' => (bool) ($request->has('with_rebooking_charge')),
            'with_airport_transfer' => (bool) ($request->has('with_airport_transfer')),
            'pricing_items' => $validated['pricing_items'] ?? [],
            'total_amount' => $validated['total_amount'] ?? $agreement->total_amount,
            'payment_terms' => $validated['payment_terms'] ?? null,
            'agent_name' => $validated['agent_name'] ?? $agreement->agent_name,
            'passenger_client_name' => $validated['passenger_client_name'] ?? $agreement->passenger_client_name,
            'status' => $validated['status'] ?? $agreement->status,
        ]);

        return redirect()->route('ticketing.agreements.show', $agreement)
            ->with('success', 'Booking Agreement updated successfully!');
    }

    /**
     * An agreement is only as visible as its ticket: when the ticket belongs
     * to another officer (hidden by the own-files scope), the agreement is a 404.
     */
    private function ensureTicketVisible(BookingAgreement $agreement): void
    {
        abort_if($agreement->ticketBooking === null, 404);
    }
}
