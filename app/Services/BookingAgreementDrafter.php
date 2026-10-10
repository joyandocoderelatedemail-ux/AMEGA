<?php

namespace App\Services;

use App\Models\BookingAgreement;
use App\Models\InsurancePlan;
use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
     *     conditions: array<string, bool>,
     * }
     */
    public function defaults(TicketBooking $ticket, bool $forForm = true): array
    {
        $ticket->loadMissing('passengers', 'airline', 'travelPackage');

        $passengerNames = $ticket->passengers->pluck('full_name')->filter()->implode(', ') ?: (string) $ticket->contact_name;

        // The flight booked in the wizard (Selected Flight): airline, flight numbers and times.
        $carrier = (string) ($ticket->airline?->name ?: $ticket->preferred_airline);
        $class = $ticket->travel_class ? ucwords(str_replace('_', ' ', $ticket->travel_class)) : 'Economy';
        $time = fn ($value): string => filled($value) ? Carbon::parse($value)->format('h:i A') : 'TBA';

        $leg = fn ($date, ?string $from, ?string $to, ?string $flightNumber = null, $departs = null, $arrives = null): array => [
            'carrier' => $carrier,
            'flight_number' => (string) $flightNumber,
            'flight_class' => $class,
            'day' => $date ? $date->format('d (D)') : '',
            'month' => $date ? strtoupper($date->format('M')) : '',
            'from_location' => (string) $from,
            'to_location' => (string) $to,
            'departure_time' => $time($departs),
            'arrival_time' => $time($arrives),
            'flight_status' => 'HK / Confirmed',
        ];

        $legs = collect($ticket->trip_type === 'multi_city' ? ($ticket->multi_city_segments ?? []) : [])
            ->filter(fn ($segment): bool => is_array($segment) && (filled($segment['from'] ?? null) || filled($segment['to'] ?? null)))
            ->values();

        if ($legs->isNotEmpty()) {
            // Each multi-city leg on its own row; the booked flight is the first leg.
            $flightSegments = $legs->map(fn (array $segment, int $index): array => $leg(
                filled($segment['date'] ?? null) ? Carbon::parse($segment['date']) : null,
                $segment['from'] ?? null,
                $segment['to'] ?? null,
                $index === 0 ? $ticket->flight_number : null,
                $index === 0 ? $ticket->departure_time : null,
                $index === 0 ? $ticket->arrival_time : null,
            ))->all();
        } else {
            $flightSegments = [$leg($ticket->departure_date, $ticket->origin, $ticket->destination, $ticket->flight_number, $ticket->departure_time, $ticket->arrival_time)];

            if ($ticket->trip_type === 'round_trip' && $ticket->return_date) {
                $flightSegments[] = $leg($ticket->return_date, $ticket->destination, $ticket->origin, $ticket->return_flight_number, $ticket->return_departure_time, $ticket->return_arrival_time);
            }
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
            'conditions' => $this->conditions($ticket),
        ];
    }

    /**
     * Conditions & Inclusions as the booking recorded them, so nothing is ticked
     * that staff did not choose: the airline restrictions (refunds, rebooking,
     * baggage), the concierge services and special requests from Contact &
     * Extras, and the ready-made package's terms.
     *
     * @return array{has_baggage: bool, is_non_refundable: bool, is_non_rebookable: bool, has_meals: bool, with_rebooking_charge: bool, with_airport_transfer: bool}
     */
    public function conditions(TicketBooking $ticket): array
    {
        $ticket->loadMissing('travelPackage');

        $restrictions = Str::lower(implode(' | ', array_filter((array) ($ticket->airline_restrictions ?? []), 'is_string')));
        $says = fn (string ...$phrases): bool => collect($phrases)->contains(fn (string $phrase): bool => str_contains($restrictions, $phrase));
        $services = (array) ($ticket->selected_services ?? []);
        $requests = (array) ($ticket->special_requests_list ?? []);
        $package = $ticket->travelPackage;

        $noCheckedBaggage = $says('hand-carry only', 'hand carry only', 'no checked baggage', 'without baggage', 'baggage must be purchased');

        return [
            'has_baggage' => ! $noCheckedBaggage && (
                in_array('extra_baggage', $requests, true)
                || filled($package?->baggage_allowance)
                || $says('with baggage', 'baggage included', 'kg checked', 'checked baggage included')
            ),
            'is_non_refundable' => $says('non-refundable', 'non refundable', 'nonrefundable'),
            'is_non_rebookable' => $says('non-rebookable', 'non rebookable', 'no rebooking', 'no date change'),
            'has_meals' => in_array('special_meals', $requests, true) || $says('with meals', 'meals included'),
            'with_rebooking_charge' => $says('date change fee', 'rebooking fee', 'rebooking charge', 'change fee'),
            'with_airport_transfer' => in_array('airport_transfer', $services, true) || (bool) $package?->has_transportation,
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
            'Amega Service Fee' => (float) $ticket->service_fee,
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
            ...$defaults['conditions'],
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
