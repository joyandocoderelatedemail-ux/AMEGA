<?php

namespace App\Services;

use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Models\User;

/**
 * Fills a Booking Agreement from its ticket: the passengers, the flight legs
 * and a price line at the ticket's price. The agreement form starts from
 * this, and a ticket issued without one gets it drawn up automatically so
 * the client is always sent their agreement.
 */
class BookingAgreementDrafter
{
    /**
     * What the agreement form, or an automatic agreement, starts from.
     *
     * With $forForm the legs carry a carrier hint and blank rows for staff to
     * fill; an automatic agreement keeps only what the ticket actually says.
     *
     * @return array{
     *     passengerNames: string,
     *     flightSegments: list<array<string, string>>,
     *     pricingItems: list<array{airfare_description: string, price_details: string, pax_count: int, amount: float}>,
     * }
     */
    public function defaults(TicketBooking $ticket, bool $forForm = true): array
    {
        $ticket->loadMissing('passengers');

        $passengerNames = $ticket->passengers->pluck('full_name')->filter()->implode(', ') ?: (string) $ticket->contact_name;

        $carrier = $forForm ? 'Cebu Pacific / PAL / AirAsia' : (string) $ticket->preferred_airline;
        $class = $ticket->travel_class ? ucwords(str_replace('_', ' ', $ticket->travel_class)) : 'Economy';

        $leg = fn ($date, ?string $from, ?string $to): array => [
            'carrier' => $carrier,
            'flight_number' => '',
            'flight_class' => $class,
            'day' => $date ? $date->format('d (D)') : '',
            'month' => $date ? strtoupper($date->format('M')) : '',
            'from_location' => (string) $from,
            'to_location' => (string) $to,
            'departure_time' => 'TBA',
            'arrival_time' => 'TBA',
            'flight_status' => 'HK / Confirmed',
        ];

        $flightSegments = [$leg($ticket->departure_date, $ticket->origin, $ticket->destination)];

        if ($ticket->trip_type === 'round_trip' && $ticket->return_date) {
            $flightSegments[] = $leg($ticket->return_date, $ticket->destination, $ticket->origin);
        }

        // The form offers blank rows for connections and extra legs.
        if ($forForm) {
            $blank = array_fill_keys(array_keys($flightSegments[0]), '');

            while (count($flightSegments) < 4) {
                $flightSegments[] = $blank;
            }
        }

        $description = $ticket->package_name
            ? "Tour Package: {$ticket->package_name} ({$ticket->origin} - {$ticket->destination})"
            : "Airfare: {$ticket->origin} to {$ticket->destination} (".($ticket->trip_type === 'round_trip' ? 'Round Trip' : 'One Way').')';

        return [
            'passengerNames' => $passengerNames,
            'flightSegments' => $flightSegments,
            'pricingItems' => [[
                'airfare_description' => $description,
                'price_details' => 'Quoted Agent Rate (Incl. Taxes & Surcharges)',
                'pax_count' => (int) $ticket->total_passengers ?: 1,
                // The line's total for all its passengers, starting from the ticket's price.
                'amount' => round((float) $ticket->total_amount, 2),
            ]],
        ];
    }

    /**
     * The ticket's agreement, drawn up from the ticket when staff never made one.
     */
    public function ensureFor(TicketBooking $ticket, ?User $preparedBy = null): BookingAgreement
    {
        if ($existing = $ticket->bookingAgreement()->first()) {
            return $existing;
        }

        $defaults = $this->defaults($ticket, forForm: false);
        $ticket->loadMissing('client');

        $agreement = $ticket->bookingAgreement()->create([
            'agreement_number' => self::newNumber(),
            'client_names' => $defaults['passengerNames'],
            'agreement_date' => now()->toDateString(),
            'contact_phone' => $ticket->contact_phone,
            'contact_email' => $ticket->contact_email,
            'home_hotel_address' => $ticket->client?->address,
            'flight_segments' => $defaults['flightSegments'],
            'has_baggage' => true,
            'is_non_refundable' => false,
            'is_non_rebookable' => false,
            'has_meals' => false,
            'with_rebooking_charge' => false,
            'with_airport_transfer' => false,
            'pricing_items' => $defaults['pricingItems'],
            'total_amount' => $defaults['pricingItems'][0]['amount'],
            'agent_name' => $preparedBy?->name ?? 'Amega Travel Agent',
            'passenger_client_name' => $ticket->contact_name,
            'status' => 'generated',
        ]);

        $ticket->setRelation('bookingAgreement', $agreement);

        return $agreement;
    }

    /**
     * A fresh agreement number: AGR-YYYYMM-XXXX.
     */
    public static function newNumber(): string
    {
        do {
            $number = 'AGR-'.now()->format('Ym').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (BookingAgreement::where('agreement_number', $number)->exists());

        return $number;
    }
}
