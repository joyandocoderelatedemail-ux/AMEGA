<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Services\BookingAgreementDrafter;
use App\Support\BookingAgreementSheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BookingAgreementController extends Controller
{
    /**
     * Show form to generate/customize a Booking Agreement for a Ticket.
     */
    public function create(TicketBooking $ticket, BookingAgreementDrafter $drafter): View|RedirectResponse
    {
        $ticket->load('passengers', 'travelPackage');

        // If an agreement already exists, redirect directly to view/edit it
        if ($ticket->bookingAgreement) {
            return redirect()->route('ticketing.agreements.show', $ticket->bookingAgreement);
        }

        return view('ticketing.agreements.create', [
            'ticket' => $ticket,
            'currentUser' => Auth::user(),
        ] + $drafter->defaults($ticket));
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

        $agreementNumber = BookingAgreementDrafter::newNumber();

        $finalTotal = ! empty($validated['total_amount'])
            ? (float) $validated['total_amount']
            : self::pricingTotal($validated['pricing_items'] ?? []);

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

        $agreement->load('ticketBooking.passengers', 'ticketBooking.travelPackage', 'ticketBooking.client');

        return view('ticketing.agreements.show', ['agreement' => $agreement] + BookingAgreementSheet::data($agreement));
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
            // Follow the edited pricing lines, so a price change reaches the total.
            'total_amount' => ! empty($validated['total_amount'])
                ? (float) $validated['total_amount']
                : self::pricingTotal($validated['pricing_items'] ?? []),
            'payment_terms' => $validated['payment_terms'] ?? null,
            'agent_name' => $validated['agent_name'] ?? $agreement->agent_name,
            'passenger_client_name' => $validated['passenger_client_name'] ?? $agreement->passenger_client_name,
            'status' => $validated['status'] ?? $agreement->status,
        ]);

        return redirect()->route('ticketing.agreements.show', $agreement)
            ->with('success', 'Booking Agreement updated successfully!');
    }

    /**
     * Each pricing line's amount is already the total for all its passengers
     * (the form's "Total Amount" column), so the agreement total is their sum.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private static function pricingTotal(array $items): float
    {
        return round(collect($items)->sum(fn ($item): float => (float) ($item['amount'] ?? 0)), 2);
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
