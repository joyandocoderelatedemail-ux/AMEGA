<?php

use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** The trip details a quotation needs, with no documents and no passengers. */
function quotePayload(array $overrides = []): array
{
    return array_merge([
        'save_as_quotation' => 1,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Boracay',
        'trip_type' => 'one_way',
        'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 2,
        'adults_count' => 2,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
    ], $overrides);
}

test('a quotation can be created with no documents and no passenger details', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), quotePayload());

    $booking = TicketBooking::first();

    expect($booking)->not->toBeNull()
        ->and($booking->isQuotation())->toBeTrue()
        ->and($booking->contact_name)->toBe('Juan Dela Cruz')
        ->and($booking->passengers)->toHaveCount(0);

    // The whole point: it lands on the booking agreement form.
    $response->assertRedirect(route('ticketing.agreements.create', $booking));
});

test('a quotation still requires the route, date and a client name', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.store'), quotePayload(['destination' => '']))
        ->assertSessionHasErrors('destination');

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.store'), quotePayload(['contact_name' => '']))
        ->assertSessionHasErrors('contact_name');

    expect(TicketBooking::count())->toBe(0);
});

test('a quotation cannot be issued even once it is fully paid', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), quotePayload(['total_amount' => 5000]));

    $booking = TicketBooking::first();
    $booking->recordPayment((float) $booking->total_amount);

    expect($booking->isFullyPaid())->toBeTrue()
        ->and($booking->canBeIssued())->toBeFalse();

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.issue', $booking), ['data_privacy_consent' => '1'])
        ->assertSessionHas('error');

    expect($booking->fresh()->isIssued())->toBeFalse();
});

test('a normal booking is not flagged as a quotation and still enforces documents', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    // Same payload without the quotation flag: the document rules apply again.
    $response = $this->actingAs($officer)->post(
        route('ticketing.tickets.store'),
        quotePayload([
            'save_as_quotation' => 0,
            'contact_email' => 'juan@example.com',
            'contact_phone' => '09171234567',
            'passengers' => [[
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'passenger_type' => 'adult',
                'nationality_type' => 'filipino',
            ]],
        ])
    );

    $response->assertSessionHasErrors('passengers.0.government_id_file');
    expect(TicketBooking::count())->toBe(0);
});

test('the detail page explains why a quotation cannot be issued', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($officer)->post(route('ticketing.tickets.store'), quotePayload(['total_amount' => 5000]));

    $booking = TicketBooking::first();
    $booking->recordPayment(5000);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $booking))
        ->assertStatus(200)
        ->assertSee('This is a quotation', false)
        ->assertDontSee('Data Privacy and Consent', false);
});

test('the wizard exposes a quotation button that bypasses the step checks', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.tickets.create'))
        ->assertStatus(200)
        ->assertDontSee('Create Quotation', false)
        ->assertSee('name="save_as_quotation"', false)
        ->assertSee('saveAsQuotation()', false);
});

test('a quotation is saved even when passengers are missing documents or have an expiring passport', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $departure = Carbon::today()->addWeek();

    $response = $this->actingAs($officer)->post(route('ticketing.tickets.store'), quotePayload([
        'travel_type' => 'international',
        'destination_country' => 'Japan',
        'destination_city' => 'Tokyo',
        'arrival_airport' => 'NRT',
        'departure_date' => $departure->toDateString(),
        'adults_count' => 1,
        'infants_count' => 1,
        'passengers' => [
            ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino'],
            // No birth certificate, and a passport that runs out a month after departure.
            ['first_name' => 'Chin', 'last_name' => 'Chin', 'passenger_type' => 'infant', 'nationality_type' => 'filipino',
                'date_of_birth' => $departure->copy()->subMonths(8)->toDateString(),
                'passport_number' => 'P0000001', 'passport_expiry_date' => $departure->copy()->addMonth()->toDateString()],
        ],
    ]));

    $booking = TicketBooking::firstOrFail();

    expect($booking->isQuotation())->toBeTrue()
        ->and($booking->passengers)->toHaveCount(2);

    $response->assertRedirect(route('ticketing.agreements.create', $booking));
});

test('the booking wizard offers Save as Quotation', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.tickets.create'))
        ->assertOk()
        ->assertSee('@click="saveAsQuotation()"', false);
});
