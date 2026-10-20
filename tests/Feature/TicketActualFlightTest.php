<?php

use App\Models\ActivityLog;
use App\Models\Airline;
use App\Models\TicketBooking;
use App\Models\User;
use App\Services\BookingAgreementDrafter;

/** A fully paid booking waiting to be issued by a ticketing officer. */
function paidBooking(User $officer, array $overrides = []): TicketBooking
{
    $ticket = TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'trip_type' => 'one_way',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
        'created_by' => $officer->id,
    ], $overrides));
    $ticket->recordPayment(10000);

    return $ticket;
}

test('issuing needs the actual flight: airline, booking code, flight number and times', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = paidBooking($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHasErrors(['airline_id', 'airline_pnr', 'flight_number', 'departure_time', 'arrival_time']);
    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), issueFlight(['airline_pnr' => 'AB-12']))
        ->assertSessionHasErrors('airline_pnr');
    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), issueFlight(['departure_time' => '8am']))
        ->assertSessionHasErrors('departure_time');

    expect($ticket->fresh()->isIssued())->toBeFalse();
});

test('a round trip also needs the return flight, a one way does not', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $round = paidBooking($officer, ['trip_type' => 'round_trip', 'return_date' => now()->addDays(30)]);
    $oneWay = paidBooking($officer);

    $noReturn = issueFlight(['return_flight_number' => '', 'return_departure_time' => '', 'return_arrival_time' => '']);
    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $round), $noReturn)
        ->assertSessionHasErrors(['return_flight_number', 'return_departure_time', 'return_arrival_time']);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $oneWay), $noReturn)->assertSessionHasNoErrors();
    expect($oneWay->fresh()->isIssued())->toBeTrue()->and($oneWay->fresh()->return_flight_number)->toBeNull();
});

test('the actual flight replaces what was held at booking, and is logged', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $held = Airline::factory()->create(['name' => 'Held Air']);
    $ticket = paidBooking($officer, ['airline_id' => $held->id, 'flight_number' => 'HA101', 'departure_time' => '06:00', 'arrival_time' => '07:30']);
    $actual = Airline::factory()->create(['name' => 'Actual Air']);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), issueFlight([
        'airline_id' => $actual->id, 'airline_pnr' => 'zx9y8w', 'flight_number' => 'aa 202', 'departure_time' => '09:15', 'arrival_time' => '10:45',
    ]))->assertSessionHas('success');

    $ticket = $ticket->fresh();
    expect($ticket->isIssued())->toBeTrue()
        ->and($ticket->airline_id)->toBe($actual->id)
        ->and($ticket->airline_pnr)->toBe('ZX9Y8W')
        ->and($ticket->flight_number)->toBe('AA202')
        ->and(substr((string) $ticket->departure_time, 0, 5))->toBe('09:15');

    $log = ActivityLog::latest('id')->first();
    expect($log->description)->toContain('flight AA202')->toContain('booking code ZX9Y8W')
        ->toContain('flight details changed')->toContain('airline')->toContain('departure time');
});

test('the issue panel asks for the actual flight, filled from the booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $airline = Airline::factory()->create(['name' => 'Sky Test', 'code' => 'ST']);
    $ticket = paidBooking($officer, [
        'trip_type' => 'round_trip', 'return_date' => now()->addDays(30), 'airline_id' => $airline->id,
        'flight_number' => 'ST100', 'departure_time' => '07:05:00', 'arrival_time' => '08:35:00',
        'return_flight_number' => 'ST101', 'return_departure_time' => '17:00:00', 'return_arrival_time' => '18:30:00',
    ]);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))->assertOk()
        ->assertSee('Actual Flight Information')->assertSee('Booking code (PNR)')->assertSee('Sky Test (ST)')
        ->assertSee('value="ST100"', false)->assertSee('value="07:05"', false)
        ->assertSee('name="return_flight_number"', false)->assertSee('value="ST101"', false);
});

test('the booking agreement carries the actual flight, whether drawn up before or at issue', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $held = Airline::factory()->create(['name' => 'Held Air']);
    $actual = Airline::factory()->create(['name' => 'Actual Air']);
    $drafter = app(BookingAgreementDrafter::class);

    // An agreement staff drew up before issuing, with a connection row they typed in themselves.
    $early = paidBooking($officer, [
        'trip_type' => 'round_trip', 'return_date' => now()->addDays(30), 'airline_id' => $held->id,
        'flight_number' => 'HA101', 'departure_time' => '06:00', 'arrival_time' => '07:30',
        'return_flight_number' => 'HA102', 'return_departure_time' => '18:00', 'return_arrival_time' => '19:30',
    ]);
    $agreement = $drafter->ensureFor($early, $officer);
    $segments = $agreement->flight_segments;
    $segments[2] = [
        'carrier' => 'Connect Air', 'flight_number' => 'CA9', 'flight_class' => 'Economy', 'day' => '', 'month' => '',
        'from_location' => 'CEB', 'to_location' => 'MNL', 'departure_time' => '10:00 AM', 'arrival_time' => '11:00 AM', 'flight_status' => 'HK',
    ];
    $agreement->update(['flight_segments' => $segments]);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $early), issueFlight([
        'airline_id' => $actual->id, 'flight_number' => 'AA202', 'departure_time' => '09:15', 'arrival_time' => '10:45',
        'return_flight_number' => 'AA203', 'return_departure_time' => '20:10', 'return_arrival_time' => '21:40',
    ]))->assertSessionHas('success');

    $rows = $early->fresh()->bookingAgreement->flight_segments;
    expect($rows[0])->toMatchArray(['carrier' => 'Actual Air', 'flight_number' => 'AA202', 'departure_time' => '09:15 AM', 'arrival_time' => '10:45 AM', 'from_location' => 'Manila (MNL)'])
        ->and($rows[1])->toMatchArray(['carrier' => 'Actual Air', 'flight_number' => 'AA203', 'departure_time' => '08:10 PM', 'arrival_time' => '09:40 PM'])
        ->and($rows[2]['flight_number'])->toBe('CA9');

    // No agreement yet: the one drawn up at issue starts from the actual flight.
    $late = paidBooking($officer, ['airline_id' => $held->id, 'flight_number' => 'HA101']);
    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $late), issueFlight(['airline_id' => $actual->id, 'flight_number' => 'AA300']));
    expect($late->fresh()->bookingAgreement->flight_segments[0])->toMatchArray(['carrier' => 'Actual Air', 'flight_number' => 'AA300']);
});

test('the issued ticket shows the actual flight in the voucher, the confirmation and the agreement sheet', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = paidBooking($officer);
    $actual = Airline::factory()->create(['name' => 'Actual Air', 'code' => 'AA']);

    $this->actingAs($officer)->post(route('ticketing.tickets.issue', $ticket), issueFlight(['airline_id' => $actual->id, 'airline_pnr' => 'PNR123', 'flight_number' => 'AA777']));

    $this->actingAs($officer)->get(route('ticketing.tickets.voucher', $ticket))->assertOk()->assertSee('AA777')->assertSee('PNR123')->assertSee('Actual Air (AA)');
    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))->assertOk()->assertSee('Ticketed Airline')->assertSee('AA777');
    $this->actingAs($officer)->get(route('ticketing.agreements.show', $ticket->fresh()->bookingAgreement))->assertOk()->assertSee('AA777')->assertSee('PNR123');
});
