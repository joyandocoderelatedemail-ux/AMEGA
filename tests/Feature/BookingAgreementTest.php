<?php

use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Models\User;
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
