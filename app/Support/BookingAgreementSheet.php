<?php

namespace App\Support;

use App\Models\BookingAgreement;
use App\Models\InsurancePlan;
use App\Models\TicketBooking;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The figures and wording on a printed Booking Agreement, shared by the
 * on-screen sheet and the PDF emailed to the client so the two never differ.
 */
class BookingAgreementSheet
{
    /**
     * What the booking holds beyond the price lines, as label => list of lines:
     * who is travelling, how to reach them, the trip, and the notes staff took.
     * Empty rows are left out.
     *
     * @return array<string, list<string>>
     */
    private static function details(BookingAgreement $agreement, TicketBooking $ticket): array
    {
        $party = collect([
            'adult' => (int) $ticket->adults_count,
            'child' => (int) $ticket->children_count,
            'infant' => (int) $ticket->infants_count,
        ])->filter()->map(fn (int $count, string $type): string => $count.' '.Str::plural(ucfirst($type), $count))->implode(', ');

        $passengers = $ticket->passengers->sortBy('passenger_number')->values()->map(function ($passenger): string {
            $facts = array_filter([
                ucfirst((string) $passenger->passenger_type),
                $passenger->isForeignNational() ? 'Foreign national' : null,
                $passenger->date_of_birth?->format('d M Y'),
                filled($passenger->passport_number) ? 'Passport '.$passenger->passport_number : null,
            ]);

            return $passenger->passenger_number.'. '.$passenger->full_name.($facts ? ' ('.implode(', ', $facts).')' : '');
        })->all();

        $trip = array_filter([
            $ticket->trip_type ? ucwords(str_replace('_', ' ', $ticket->trip_type)) : null,
            ucfirst((string) $ticket->travel_type),
            $ticket->travel_class ? ucwords(str_replace('_', ' ', $ticket->travel_class)) : null,
        ]);

        $contact = array_filter([
            $ticket->contact_name,
            $agreement->contact_phone ?: $ticket->contact_phone,
            $agreement->contact_email ?: $ticket->contact_email,
        ]);

        $emergency = filled($ticket->emergency_contact_name)
            ? [trim($ticket->emergency_contact_name.' ('.$ticket->emergency_contact_relationship.')').' - '.implode(' · ', array_filter([$ticket->emergency_contact_phone, $ticket->emergency_contact_email]))]
            : [];

        $rows = [
            'Travellers' => array_filter([$party]),
            'Passengers' => $passengers,
            'Booked by' => $contact ? [implode(' · ', $contact)] : [],
            'Trip' => $trip ? [implode(' · ', $trip)] : [],
            'Travel insurance' => $ticket->has_insurance ? [InsurancePlan::labelFor($ticket->insurance_plan)] : [],
            'Emergency contact' => $emergency,
            'Special instructions' => filled($ticket->special_requests) ? [trim($ticket->special_requests)] : [],
            'Airline restrictions' => array_values($ticket->airline_restrictions ?? []),
        ];

        return array_filter($rows, fn (array $lines): bool => $lines !== []);
    }

    /**
     * @return array{
     * @return array{
     *     ticket: TicketBooking,
     *     details: array<string, list<string>>,
     *     category: string,
     *     carriers: Collection<int, string>,
     *     segmentLines: Collection<int, string>,
     *     lines: Collection<int, array{description: string, details: ?string, quantity: int, unit_price: float, amount: float, free: bool}>,
     *     total: float,
     *     declarant: ?string,
     *     citizenship: ?string,
     *     packageSpecs: array<string, mixed>,
     *     conditions: list<array{0: string, 1: bool}>
     * }
     */
    public static function data(BookingAgreement $agreement): array
    {
        $agreement->loadMissing('ticketBooking.passengers', 'ticketBooking.client');
        $ticket = $agreement->ticketBooking;

        $category = $ticket->travel_type === 'international'
            ? "INT'L TKTG, INTERNATIONAL TICKETING AND PACKAGES"
            : 'DOM TKTG, DOMESTIC TICKETING AND PACKAGES';

        // Flight legs in the one-line airline format: flight, class, date, route, times.
        $segments = collect($agreement->flight_segments ?? [])
            ->filter(fn ($s): bool => is_array($s) && (filled($s['from_location'] ?? null) || filled($s['to_location'] ?? null) || filled($s['flight_number'] ?? null)));

        $carriers = $segments->pluck('carrier')->filter()->unique()->values();

        $segmentLines = $segments->map(function (array $s): string {
            $day = preg_replace('/\D/', '', (string) ($s['day'] ?? ''));

            return trim(preg_replace('/\s+/', ' ', implode(' ', [
                $s['flight_number'] ?? '',
                $s['flight_class'] ?? '',
                $day.strtoupper((string) ($s['month'] ?? '')),
                $s['from_location'] ?? '',
                $s['to_location'] ?? '',
                $s['departure_time'] ?? '',
                $s['arrival_time'] ?? '',
            ])));
        })->filter()->values();

        // Staff enter each line's total for all its passengers (the form's
        // "Total Amount" column); the unit price is that split per passenger.
        $lines = collect($agreement->pricing_items ?? [])->map(function (array $item) use ($ticket): array {
            $quantity = (int) ($item['pax_count'] ?? 0) ?: (int) $ticket->total_passengers ?: 1;
            $amount = (float) ($item['amount'] ?? 0);

            return [
                'description' => $item['airfare_description'] ?? 'Airfare',
                'details' => $item['price_details'] ?? null,
                'quantity' => $quantity,
                'unit_price' => round($amount / $quantity, 2),
                'amount' => $amount,
                // A line priced at nothing is a free inclusion, and is printed as such.
                'free' => $amount <= 0,
            ];
        })->values();

        if ($lines->isEmpty()) {
            $lines = collect([[
                'description' => "{$ticket->origin} to {$ticket->destination}",
                'details' => null,
                'quantity' => (int) $ticket->total_passengers ?: 1,
                'unit_price' => 0.0,
                'amount' => (float) $agreement->total_amount,
                'free' => false,
            ]]);
        }

        $leadPassenger = $ticket->passengers->sortBy('passenger_number')->first();
        // The declaration names a country: prefer the passport's, and read the
        // client's nationality as a country only where that is unambiguous.
        $nationality = $ticket->client?->nationality;
        $citizenship = $leadPassenger?->passport_country
            ?: (($leadPassenger?->nationality_type === 'filipino' || preg_match('/filipin|philippin/i', (string) $nationality)) ? 'the Philippines' : $nationality);

        return [
            'ticket' => $ticket,
            'details' => self::details($agreement, $ticket),
            'category' => $category,
            'carriers' => $carriers,
            'segmentLines' => $segmentLines,
            'lines' => $lines,
            // Sales tax is 0%, so the subtotal and the total are the agreed amount.
            'total' => (float) $agreement->total_amount > 0 ? (float) $agreement->total_amount : (float) $lines->sum('amount'),
            'declarant' => $agreement->passenger_client_name ?: $agreement->client_names,
            'citizenship' => $citizenship,
            'packageSpecs' => $ticket->custom_package_specs ?? [],
            'conditions' => [
                ['With Baggage', (bool) $agreement->has_baggage],
                ['Non-Refundable', (bool) $agreement->is_non_refundable],
                ['With Meals', (bool) $agreement->has_meals],
                ['With Rebooking Charge', (bool) $agreement->with_rebooking_charge],
                ['Without Baggage', ! $agreement->has_baggage],
                ['Non-Rebookable', (bool) $agreement->is_non_rebookable],
                ['Without Meals', ! $agreement->has_meals],
                ['With Airport Transfer', (bool) $agreement->with_airport_transfer],
            ],
        ];
    }
}
