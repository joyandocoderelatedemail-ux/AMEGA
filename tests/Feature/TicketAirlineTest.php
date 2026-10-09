<?php

use App\Models\Airline;
use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** A quotation: the smallest booking the store action accepts. */
function airlineQuotePayload(array $overrides = []): array
{
    return array_merge([
        'save_as_quotation' => 1,
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::today()->addMonth()->toDateString(),
        'return_date' => Carbon::today()->addMonth()->addDays(4)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'children_count' => 0,
        'infants_count' => 0,
        'contact_name' => 'Juan Dela Cruz',
    ], $overrides);
}

test('the local carriers are loaded by the migration', function () {
    expect(Airline::pluck('code')->all())->toEqualCanonicalizing(['5J', 'PR', 'Z2']);
});

test('the wizard offers only active airlines in the fare search', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    Airline::factory()->inactive()->create(['name' => 'Retired Air']);

    $response = $this->actingAs($officer)->get(route('ticketing.tickets.create'));

    $response->assertOk()
        ->assertSee('Search Fares')
        ->assertSee('cebupacificair.com', false)
        ->assertDontSee('Retired Air');
});

test('the booked flight is saved with the ticket', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $cebuPacific = Airline::where('code', '5J')->first();

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), airlineQuotePayload([
        'airline_id' => $cebuPacific->id,
        'airline_pnr' => 'x7k2qp',
        'flight_number' => '5j 5054',
        'departure_time' => '08:15',
        'arrival_time' => '09:35',
        'return_flight_number' => '5J 5055',
        'return_departure_time' => '18:40',
    ]))->assertSessionHasNoErrors();

    $booking = TicketBooking::first();

    expect($booking->airline->is($cebuPacific))->toBeTrue()
        ->and($booking->airline_pnr)->toBe('X7K2QP')
        ->and($booking->flight_number)->toBe('5J5054')
        ->and(TicketBooking::formatFlightTime($booking->departure_time))->toBe('8:15 AM')
        ->and($booking->return_flight_number)->toBe('5J5055')
        ->and($booking->flightEmailLines())->toContain('**Airline:** Cebu Pacific (5J)', '**Booking code:** X7K2QP');

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $booking))
        ->assertOk()
        ->assertSee('X7K2QP')
        ->assertSee('8:15 AM');

    $this->actingAs($officer)->get(route('ticketing.tickets.voucher', $booking))
        ->assertOk()
        ->assertSee('5J5054');
});

test('a one way ticket drops any return flight left in the form', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.tickets.store'), airlineQuotePayload([
        'trip_type' => 'one_way',
        'return_date' => null,
        'flight_number' => 'PR 1845',
        'return_flight_number' => 'PR 1846',
    ]))->assertSessionHasNoErrors();

    expect(TicketBooking::first()->return_flight_number)->toBeNull();
});

test('flight details are optional and checked when given', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.store'), airlineQuotePayload([
            'airline_id' => 999,
            'airline_pnr' => 'AB-12',
            'departure_time' => '25:00',
        ]))
        ->assertSessionHasErrors(['airline_id', 'airline_pnr', 'departure_time']);

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.store'), airlineQuotePayload())
        ->assertSessionHasNoErrors();

    expect(TicketBooking::first()->hasBookedFlight())->toBeFalse();
});

test('the desk can add an airline and switch one off', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.airlines.index'))
        ->assertOk()
        ->assertSee('Philippine Airlines');

    $this->actingAs($officer)->post(route('ticketing.airlines.store'), [
        'name' => 'Cebgo',
        'code' => 'dg',
        'booking_url' => 'https://www.cebupacificair.com',
        'agent_portal_url' => 'https://agents.example.com',
    ])->assertRedirect(route('ticketing.airlines.index'));

    $cebgo = Airline::where('name', 'Cebgo')->first();
    expect($cebgo->code)->toBe('DG')->and($cebgo->is_active)->toBeTrue();

    // An unchecked box means "hide from the wizard".
    $this->actingAs($officer)->put(route('ticketing.airlines.update', $cebgo), [
        'name' => 'Cebgo',
        'code' => 'DG',
        'booking_url' => 'https://www.cebupacificair.com',
    ])->assertRedirect(route('ticketing.airlines.index'));

    expect($cebgo->fresh()->is_active)->toBeFalse()
        ->and(Airline::offered()->pluck('name'))->not->toContain('Cebgo');
});

test('airline links must be web addresses', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.airlines.store'), [
        'name' => 'Sketchy Air',
        'booking_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('booking_url');

    expect(Airline::where('name', 'Sketchy Air')->exists())->toBeFalse();
});

test('clients cannot manage airlines', function () {
    $client = User::factory()->create(['role' => 'client']);

    // The portal turns clients away to the public site.
    $this->actingAs($client)->get(route('ticketing.airlines.index'))->assertRedirect(route('home'));

    $this->actingAs($client)->post(route('ticketing.airlines.store'), [
        'name' => 'Client Air',
        'booking_url' => 'https://example.com',
    ]);

    expect(Airline::where('name', 'Client Air')->exists())->toBeFalse();
});

test('an airline can be saved without a booking link', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.airlines.store'), [
        'name' => 'Sunlight Air',
    ])->assertSessionHasNoErrors()->assertRedirect(route('ticketing.airlines.index'));

    $airline = Airline::where('name', 'Sunlight Air')->firstOrFail();
    expect($airline->booking_url)->toBeNull();

    $this->actingAs($officer)->get(route('ticketing.airlines.index'))->assertOk()->assertSee('Sunlight Air');
    $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk();
});
