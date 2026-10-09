<?php

namespace App\Services;

use App\Models\BookingAgreement;
use App\Models\InsurancePlan;
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

        return [
            'passengerNames' => $passengerNames,
            'flightSegments' => $flightSegments,
            'pricingItems' => $this->pricingItems($ticket),
        ];
    }

    /**
     * The price lines of the agreement, drawn from the quotation so nothing the
     * agent priced is left out: each passenger fare, taxes, visa assistance,
     * insurance, other charges, and every service and special request, free ones
     * included. A ticket whose pieces do not add up to its total (or that was
     * only given a total) keeps one line at the agreed total instead.
     *
     * @return list<array{airfare_description: string, price_details: string, pax_count: int, amount: float}>
     */
    private function pricingItems(TicketBooking $ticket): array
    {
        $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
        $route = "{$ticket->origin} to {$ticket->destination}";
        $trip = $ticket->trip_type === 'round_trip' ? 'Round Trip' : 'One Way';
        $label = ucfirst($ticket->package_name ? 'Tour Package' : 'Airfare');
        $subject = $ticket->package_name ? "{$ticket->package_name} ({$ticket->origin} - {$ticket->destination})" : "{$route} ({$trip})";

        $items = [];

        $typeLabels = $ticket->fareTypeLabels();
        foreach ($typeLabels as $type => $typeLabel) {
            $row = $ticket->fare_breakdown[$type] ?? null;

            if ($row && (int) $row['qty'] > 0 && (float) $row['price'] > 0) {
                $items[] = [
                    'airfare_description' => "{$label} - {$typeLabel}: {$subject}",
                    'price_details' => $peso((float) $row['price']).' per person',
                    'pax_count' => (int) $row['qty'],
                    'amount' => round((float) $row['subtotal'], 2),
                ];
            }
        }

        // No per-passenger prices: the one estimated fare.
        if ($items === [] && (float) $ticket->estimated_fare > 0) {
            $items[] = [
                'airfare_description' => "{$label}: {$subject}",
                'price_details' => 'Quoted Agent Rate',
                'pax_count' => (int) $ticket->total_passengers ?: 1,
                'amount' => round((float) $ticket->estimated_fare, 2),
            ];
        }

        $charges = [
            'Taxes & Surcharges' => (float) $ticket->taxes_amount,
            'Visa Assistance' => (float) $ticket->visa_assistance_fee,
            ($ticket->has_insurance && $ticket->insurance_plan ? 'Travel Insurance ('.InsurancePlan::labelFor($ticket->insurance_plan).')' : 'Travel Insurance') => (float) $ticket->insurance_fee,
            'Other Charges' => (float) $ticket->other_charges,
        ];
        foreach ($charges as $description => $amount) {
            if ($amount > 0) {
                $items[] = ['airfare_description' => $description, 'price_details' => '', 'pax_count' => 1, 'amount' => round($amount, 2)];
            }
        }

        $extras = [
            'Concierge Service' => $ticket->selected_services ?? [],
            'Special Request' => $ticket->special_requests_list ?? [],
        ];
        foreach ($extras as $kind => $keys) {
            foreach ($keys as $key) {
                $price = $ticket->extraPriceLabel($key);

                // A service nobody priced is left for staff to add by hand.
                if ($price === null) {
                    continue;
                }

                $items[] = [
                    'airfare_description' => ucwords(str_replace('_', ' ', $key)),
                    'price_details' => $price === 'Free' ? "{$kind} - Free" : $kind,
                    'pax_count' => 1,
                    'amount' => $price === 'Free' ? 0.0 : round((float) ($ticket->extras_pricing[$key]['price'] ?? 0), 2),
                ];
            }
        }

        $sum = round(array_sum(array_column($items, 'amount')), 2);
        $total = round((float) $ticket->total_amount, 2);

        if ($items === [] || ($total > 0 && abs($sum - $total) > 0.01)) {
            return [[
                'airfare_description' => "{$label}: {$subject}",
                'price_details' => 'Quoted Agent Rate (Incl. Taxes & Surcharges)',
                'pax_count' => (int) $ticket->total_passengers ?: 1,
                // The line's total for all its passengers, starting from the ticket's price.
                'amount' => $total,
            ]];
        }

        return $items;
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
            'total_amount' => round(array_sum(array_column($defaults['pricingItems'], 'amount')), 2),
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
