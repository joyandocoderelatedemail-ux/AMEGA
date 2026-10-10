<?php

use App\Models\Airline;
use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Models\User;
use App\Services\BookingAgreementDrafter;
use App\Services\TicketDocumentPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('ticketing officer can view auto-completed booking agreement create form', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-AGR1',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Coron, Palawan',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::tomorrow(),
        'return_date' => Carbon::tomorrow()->addDays(3),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'status' => 'pending',
    ]);

    $ticket->passengers()->create([
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ]);

    $ticket->passengers()->create([
        'passenger_number' => 2,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Maria',
        'last_name' => 'Dela Cruz',
    ]);

    $response = $this->actingAs($ticketing)->get(route('ticketing.agreements.create', $ticket));

    $response->assertStatus(200);
    $response->assertSee('Generate Official Booking Agreement');
    $response->assertSee('Juan Dela Cruz, Maria Dela Cruz');
    $response->assertSee('Manila (MNL)');
    $response->assertSee('Coron, Palawan');
});

test('ticketing officer can store a booking agreement with custom agent pricing', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing', 'name' => 'Agent Sarah']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-AGR2',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Batanes (BSO)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::tomorrow(),
        'return_date' => Carbon::tomorrow()->addDays(4),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Roberto Garcia',
        'contact_email' => 'roberto@example.com',
        'contact_phone' => '09188887777',
        'status' => 'pending',
    ]);

    $flightSegments = [
        [
            'carrier' => 'PAL Express',
            'flight_number' => 'PR 2932',
            'flight_class' => 'Economy',
            'day' => '15',
            'month' => 'OCT',
            'from_location' => 'MNL',
            'to_location' => 'BSO',
            'departure_time' => '06:00 AM',
            'arrival_time' => '07:45 AM',
            'flight_status' => 'HK / Confirmed',
        ],
    ];

    $pricingItems = [
        [
            'airfare_description' => 'MNL to Batanes Round Trip Economy Promo',
            'price_details' => '₱12,500/pax + 20kg Baggage + Terminal Fees',
            'pax_count' => 2,
            'amount' => 25000.00,
        ],
    ];

    $response = $this->actingAs($ticketing)->post(route('ticketing.agreements.store', $ticket), [
        'client_names' => 'Roberto Garcia, Elena Garcia',
        'agreement_date' => Carbon::today()->toDateString(),
        'contact_phone' => '09188887777',
        'contact_email' => 'roberto@example.com',
        'home_hotel_address' => 'Basco, Batanes',
        'has_baggage' => 1,
        'is_non_refundable' => 1,
        'with_rebooking_charge' => 1,
        'flight_segments' => $flightSegments,
        'pricing_items' => $pricingItems,
        'total_amount' => 25000.00,
        'payment_terms' => 'Full payment required upon booking confirmation.',
        'agent_name' => 'Agent Sarah',
        'passenger_client_name' => 'Roberto Garcia',
    ]);

    $agreement = BookingAgreement::where('ticket_booking_id', $ticket->id)->first();
    expect($agreement)->not->toBeNull();
    expect($agreement->client_names)->toBe('Roberto Garcia, Elena Garcia');
    expect((float) $agreement->total_amount)->toBe(25000.00);
    expect($agreement->agent_name)->toBe('Agent Sarah');
    expect($agreement->has_baggage)->toBeTrue();
    expect($agreement->is_non_refundable)->toBeTrue();

    $response->assertRedirect(route('ticketing.agreements.show', $agreement));
    $response->assertSessionHas('success');
});

test('booking agreement show view renders official template details', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-AGR3',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay (MPH)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::tomorrow(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Carlos Yulo',
        'contact_email' => 'carlos@example.com',
        'contact_phone' => '09170000000',
        'status' => 'pending',
    ]);

    $agreement = BookingAgreement::create([
        'ticket_booking_id' => $ticket->id,
        'agreement_number' => 'AGR-202609-TEST',
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today(),
        'contact_phone' => '09170000000',
        'contact_email' => 'carlos@example.com',
        'home_hotel_address' => 'Balibago, Angeles City',
        'flight_segments' => [
            [
                'carrier' => 'Cebu Pacific',
                'flight_number' => '5J 891',
                'flight_class' => 'Economy',
                'day' => '20',
                'month' => 'NOV',
                'from_location' => 'MNL',
                'to_location' => 'MPH',
                'departure_time' => '09:00 AM',
                'arrival_time' => '10:15 AM',
                'flight_status' => 'HK',
            ],
        ],
        'has_baggage' => true,
        'is_non_refundable' => true,
        'has_meals' => false,
        'with_rebooking_charge' => true,
        'pricing_items' => [
            [
                'airfare_description' => 'MNL-MPH Round Trip',
                'price_details' => '₱8,500/pax All-in',
                'pax_count' => 1,
                'amount' => 8500.00,
            ],
        ],
        'total_amount' => 8500.00,
        'agent_name' => 'Amega Officer',
        'passenger_client_name' => 'Carlos Yulo',
        'status' => 'generated',
    ]);

    $response = $this->actingAs($ticketing)->get(route('ticketing.agreements.show', $agreement));

    $response->assertStatus(200);
    $response->assertSee('BOOKING AGREEMENT');
    $response->assertSee('AGR-202609-TEST');
    $response->assertSee('Carlos Yulo');
    $response->assertSee('5J 891');
    $response->assertSee('TOTAL PHP');
    $response->assertSee('8,500.00');
    $response->assertSee('Amega Officer');
});

test('booking agreement shows hotel policies and transfer for a custom package', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-AGR4',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'custom_package',
        'custom_package_specs' => [
            'hotel_name' => 'Seaside Resort',
            'smoking_preference' => 'non_smoking',
            'pet_friendly' => true,
            'has_transportation' => true,
            'transportation_type' => 'Private Van',
        ],
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay (MPH)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::tomorrow(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Carlos Yulo',
        'contact_email' => 'carlos@example.com',
        'contact_phone' => '09170000000',
        'status' => 'pending',
    ]);

    $agreement = BookingAgreement::create([
        'ticket_booking_id' => $ticket->id,
        'agreement_number' => 'AGR-202609-PKG',
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today(),
        'total_amount' => 0,
        'agent_name' => 'Amega Officer',
        'passenger_client_name' => 'Carlos Yulo',
        'status' => 'generated',
    ]);

    $response = $this->actingAs($ticketing)->get(route('ticketing.agreements.show', $agreement));

    $response->assertStatus(200);
    $response->assertSee('Hotel Policies &amp; Transfer', false);
    $response->assertSee('Non-Smoking');
    $response->assertSee('Pets Allowed');
    $response->assertSee('Private Van');
});

test('booking agreement hides hotel policies for a flight-only ticket', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-202609-AGR5',
        'created_by' => $ticketing->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay (MPH)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::tomorrow(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Carlos Yulo',
        'status' => 'pending',
    ]);

    $agreement = BookingAgreement::create([
        'ticket_booking_id' => $ticket->id,
        'agreement_number' => 'AGR-202609-FLT',
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today(),
        'total_amount' => 0,
        'agent_name' => 'Amega Officer',
        'passenger_client_name' => 'Carlos Yulo',
        'status' => 'generated',
    ]);

    $this->actingAs($ticketing)
        ->get(route('ticketing.agreements.show', $agreement))
        ->assertStatus(200)
        ->assertDontSee('Hotel Policies &amp; Transfer', false);
});

test('a new agreement starts from the ticket price, and a line amount is its total for all passengers', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-DOM-PRICE',
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'MNL',
        'destination' => 'MPH',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::today()->addDays(10),
        'total_passengers' => 2,
        'contact_name' => 'Carlos Yulo',
        'total_amount' => 10000,
        'status' => 'pending',
        'created_by' => $ticketing->id,
    ]);

    $this->actingAs($ticketing)->get(route('ticketing.agreements.create', $ticket))
        ->assertOk()
        ->assertViewHas('pricingItems', fn (array $items): bool => $items[0]['amount'] === 10000.0 && $items[0]['pax_count'] === 2);

    $this->actingAs($ticketing)->post(route('ticketing.agreements.store', $ticket), [
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today()->toDateString(),
        'pricing_items' => [['airfare_description' => 'MNL-MPH', 'pax_count' => 2, 'amount' => 10000]],
    ]);

    $agreement = BookingAgreement::where('ticket_booking_id', $ticket->id)->firstOrFail();
    expect((float) $agreement->total_amount)->toBe(10000.0);

    $this->actingAs($ticketing)->get(route('ticketing.agreements.show', $agreement))
        ->assertOk()
        ->assertSeeInOrder(['2.00', '5,000.00', '10,000.00'])
        ->assertDontSee('20,000.00');

    $this->actingAs($ticketing)->put(route('ticketing.agreements.update', $agreement), [
        'client_names' => 'Carlos Yulo',
        'agreement_date' => Carbon::today()->toDateString(),
        'pricing_items' => [['airfare_description' => 'MNL-MPH', 'pax_count' => 2, 'amount' => 12000]],
    ]);

    expect((float) $agreement->fresh()->total_amount)->toBe(12000.0);
});

function quotedTicket(User $officer): TicketBooking
{
    $ticket = TicketBooking::create([
        'booking_reference' => 'TKT-INT-202610-FULL',
        'created_by' => $officer->id,
        'travel_type' => 'international',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Tokyo (NRT)',
        'trip_type' => 'round_trip',
        'travel_class' => 'economy',
        'departure_date' => Carbon::today()->addMonths(2),
        'return_date' => Carbon::today()->addMonths(2)->addDays(7),
        'total_passengers' => 3,
        'adults_count' => 2,
        'children_count' => 1,
        'infants_count' => 0,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'contact_phone' => '09181112222',
        'emergency_contact_name' => 'Pedro Santos',
        'emergency_contact_relationship' => 'Brother',
        'emergency_contact_phone' => '09170000000',
        'has_insurance' => true,
        'insurance_plan' => 'basic',
        'selected_services' => ['hotel_booking', 'airport_transfer'],
        'special_requests_list' => ['extra_baggage'],
        'special_requests' => 'Window seats together please.',
        'airline_restrictions' => ['Non-refundable', 'No name changes allowed'],
        'fare_breakdown' => [
            'adult' => ['qty' => 1, 'price' => 20000, 'subtotal' => 20000],
            'child' => ['qty' => 1, 'price' => 15000, 'subtotal' => 15000],
            'infant' => ['qty' => 0, 'price' => 0, 'subtotal' => 0],
            'pwd_sc' => ['qty' => 1, 'price' => 16000, 'subtotal' => 16000],
        ],
        'estimated_fare' => 51000,
        'taxes_amount' => 3000,
        'insurance_fee' => 950,
        'extras_pricing' => [
            'hotel_booking' => ['free' => false, 'price' => 2500],
            'airport_transfer' => ['free' => true, 'price' => 0],
            'extra_baggage' => ['free' => false, 'price' => 700],
        ],
        'extras_amount' => 3200,
        'total_amount' => 58150,
        'status' => 'pending',
    ]);

    $ticket->passengers()->create(['passenger_number' => 1, 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'first_name' => 'Maria', 'last_name' => 'Santos', 'passport_number' => 'P1234567A']);
    $ticket->passengers()->create(['passenger_number' => 2, 'passenger_type' => 'adult', 'nationality_type' => 'filipino', 'first_name' => 'Jose', 'last_name' => 'Santos']);
    $ticket->passengers()->create(['passenger_number' => 3, 'passenger_type' => 'child', 'nationality_type' => 'filipino', 'first_name' => 'Ana', 'last_name' => 'Santos']);

    return $ticket;
}

test('the agreement starts with every quoted fare, charge, service and request as its own price line', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = quotedTicket($officer);

    $items = app(BookingAgreementDrafter::class)->defaults($ticket)['pricingItems'];
    $byDescription = collect($items)->keyBy('airfare_description');

    // Passenger fares, the charges, and each service including the free one.
    expect($items)->toHaveCount(8)
        ->and(round(array_sum(array_column($items, 'amount')), 2))->toBe(58150.0)
        ->and($byDescription->first(fn ($i) => str_contains($i['airfare_description'], 'Adult:'))['amount'])->toBe(20000.0)
        ->and($byDescription->first(fn ($i) => str_contains($i['airfare_description'], 'PWD:'))['pax_count'])->toBe(1)
        ->and($byDescription['Taxes & Surcharges']['amount'])->toBe(3000.0)
        ->and($byDescription['Travel Insurance (Basic Plan)']['amount'])->toBe(950.0)
        ->and($byDescription['Hotel Booking']['amount'])->toBe(2500.0)
        ->and($byDescription['Airport Transfer']['amount'])->toBe(0.0)
        ->and($byDescription['Airport Transfer']['price_details'])->toContain('Free')
        ->and($byDescription['Extra Baggage']['amount'])->toBe(700.0);
});

test('the printed agreement and its PDF show the full booking, with free services marked Free', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = quotedTicket($officer);
    $agreement = app(BookingAgreementDrafter::class)->ensureFor($ticket, $officer);

    expect((float) $agreement->total_amount)->toBe(58150.0);

    $this->actingAs($officer)->get(route('ticketing.agreements.show', $agreement))
        ->assertOk()
        ->assertSee('Airport Transfer')
        ->assertSee('Free')
        ->assertSee('Booking details')
        ->assertSee('1. Maria Santos')
        ->assertSee('3. Ana Santos')
        ->assertSee('2 Adults, 1 Child')
        ->assertSee('Maria Santos · 09181112222 · maria@example.com')
        ->assertSee('Basic Plan')
        ->assertSee('Pedro Santos (Brother)')
        ->assertSee('Window seats together please.')
        ->assertSee('No name changes allowed')
        ->assertSee('58,150.00');

    // The emailed PDF renders from the same data.
    expect(TicketDocumentPdf::bookingAgreement($agreement))->toStartWith('%PDF');
});

test('a ticket priced only as a lump sum still gets one price line at its total', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = quotedTicket($officer);
    $ticket->update(['fare_breakdown' => null, 'estimated_fare' => 0, 'taxes_amount' => 0, 'insurance_fee' => 0, 'extras_pricing' => null, 'extras_amount' => 0, 'total_amount' => 9000]);

    $items = app(BookingAgreementDrafter::class)->defaults($ticket->fresh())['pricingItems'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['amount'])->toBe(9000.0);
});

function bareTicket(User $officer, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-202610-'.strtoupper(Str::random(4)),
        'created_by' => $officer->id,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::today()->addMonth(),
        'return_date' => Carbon::today()->addMonth()->addDays(4),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 9000,
        'status' => 'pending',
    ], $overrides));
}

test('nothing on the agreement is ticked that the booking did not record', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = bareTicket($officer);

    $html = $this->actingAs($officer)->get(route('ticketing.agreements.create', $ticket))->assertOk()->getContent();

    foreach (['has_baggage', 'is_non_refundable', 'is_non_rebookable', 'has_meals', 'with_rebooking_charge', 'with_airport_transfer'] as $field) {
        expect($html)->toMatch('/name="'.$field.'" value="1"\s+class=/');
    }
});

test('the agreement ticks the conditions the booking recorded', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = bareTicket($officer, [
        'airline_restrictions' => ['Non-refundable', 'Date change fee applies'],
        'selected_services' => ['airport_transfer'],
        'special_requests_list' => ['special_meals', 'extra_baggage'],
    ]);

    expect(app(BookingAgreementDrafter::class)->conditions($ticket))->toBe([
        'has_baggage' => true,
        'is_non_refundable' => true,
        'is_non_rebookable' => false,
        'has_meals' => true,
        'with_rebooking_charge' => true,
        'with_airport_transfer' => true,
    ]);

    // A hand-carry-only fare has no checked baggage, whatever else was asked.
    $handCarry = bareTicket($officer, [
        'airline_restrictions' => ['Hand-carry only (7 kg), no checked baggage'],
        'special_requests_list' => ['extra_baggage'],
    ]);
    expect(app(BookingAgreementDrafter::class)->conditions($handCarry)['has_baggage'])->toBeFalse();
});

test('the agreement flight legs carry the booked airline, flight numbers and times', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $airline = Airline::factory()->create(['name' => 'Cebu Pacific']);
    $ticket = bareTicket($officer, [
        'airline_id' => $airline->id,
        'airline_pnr' => 'ABC123',
        'flight_number' => '5J 561',
        'departure_time' => '08:00',
        'arrival_time' => '09:20',
        'return_flight_number' => '5J 562',
        'return_departure_time' => '15:30',
        'return_arrival_time' => '16:50',
    ]);

    $legs = app(BookingAgreementDrafter::class)->defaults($ticket)['flightSegments'];

    expect($legs[0])->toMatchArray(['carrier' => 'Cebu Pacific', 'flight_number' => '5J 561', 'departure_time' => '08:00 AM', 'arrival_time' => '09:20 AM', 'from_location' => 'Manila (MNL)', 'to_location' => 'Cebu (CEB)'])
        ->and($legs[1])->toMatchArray(['carrier' => 'Cebu Pacific', 'flight_number' => '5J 562', 'departure_time' => '03:30 PM', 'arrival_time' => '04:50 PM', 'from_location' => 'Cebu (CEB)']);

    // The automatic agreement (drawn up at issue) records the same.
    $agreement = app(BookingAgreementDrafter::class)->ensureFor($ticket, $officer);
    expect($agreement->flight_segments[0]['flight_number'])->toBe('5J 561')
        ->and($agreement->has_baggage)->toBeFalse();

    $this->actingAs($officer)->get(route('ticketing.agreements.show', $agreement))
        ->assertOk()
        ->assertSee('PNR ABC123')
        ->assertSee('Departing: 5J 561 (08:00 AM – 09:20 AM)')
        ->assertSee('Route &amp; dates', false);
});

test('an unticked condition is saved as not included', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = bareTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.agreements.store', $ticket), [
        'client_names' => 'Juan Dela Cruz',
        'agreement_date' => now()->toDateString(),
        'pricing_items' => [['airfare_description' => 'Airfare', 'price_details' => '', 'pax_count' => 1, 'amount' => 9000]],
    ])->assertRedirect();

    $agreement = $ticket->fresh()->bookingAgreement;
    expect($agreement->has_baggage)->toBeFalse()
        ->and($agreement->is_non_refundable)->toBeFalse()
        ->and($agreement->with_rebooking_charge)->toBeFalse();
});
