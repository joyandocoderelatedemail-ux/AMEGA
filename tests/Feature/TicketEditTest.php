<?php

use App\Models\ActivityLog;
use App\Models\Airline;
use App\Models\TicketBooking;
use App\Models\User;

function editableTicket(User $officer, array $overrides = [], array $passenger = []): TicketBooking
{
    $ticket = TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $officer->id,
        'travel_type' => 'domestic',
        'trip_type' => 'round_trip',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(40)->toDateString(),
        'return_date' => now()->addDays(45)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
    ], $overrides));

    $ticket->passengers()->create(array_merge([
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
    ], $passenger));

    return $ticket->load('passengers');
}

/**
 * The edit form as the page sends it, with the changes on top.
 *
 * @param  array<string, mixed>  $changes
 * @param  array<string, mixed>  $passengerChanges
 * @return array<string, mixed>
 */
function editForm(TicketBooking $ticket, array $changes = [], array $passengerChanges = []): array
{
    $passenger = $ticket->passengers->first();

    return array_merge([
        'contact_name' => $ticket->contact_name,
        'contact_email' => $ticket->contact_email,
        'contact_phone' => $ticket->contact_phone,
        'departure_date' => $ticket->departure_date->toDateString(),
        'return_date' => $ticket->return_date?->toDateString(),
        'passengers' => [array_merge([
            'id' => $passenger->id,
            'first_name' => $passenger->first_name,
            'last_name' => $passenger->last_name,
        ], $passengerChanges)],
    ], $changes);
}

test('the edit page opens for a booking that has not been issued', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);

    $this->actingAs($officer)->get(route('ticketing.tickets.edit', $ticket))
        ->assertOk()
        ->assertSee('Edit Booking')
        ->assertSee('value="Juan"', false);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertSee(route('ticketing.tickets.edit', $ticket), false);
});

test('typos in the contact, passenger name, dates and flight can be corrected', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $airline = Airline::factory()->create();
    $ticket = editableTicket($officer);

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, [
        'contact_name' => 'Juan Reyes Dela Cruz',
        'contact_phone' => '09998887777',
        'departure_date' => now()->addDays(50)->toDateString(),
        'return_date' => now()->addDays(55)->toDateString(),
        'airline_id' => $airline->id,
        'airline_pnr' => 'abc123',
        'flight_number' => '5j 561',
        'departure_time' => '06:15',
        'return_flight_number' => '5J 562',
    ], [
        'first_name' => 'Juan Carlos',
        'gender' => 'male',
        'date_of_birth' => '1990-05-14',
    ]))->assertRedirect(route('ticketing.tickets.show', $ticket))->assertSessionHas('success');

    $ticket->refresh();
    $passenger = $ticket->passengers->first();

    expect($ticket->contact_name)->toBe('Juan Reyes Dela Cruz')
        ->and($ticket->contact_phone)->toBe('09998887777')
        ->and($ticket->departure_date->toDateString())->toBe(now()->addDays(50)->toDateString())
        ->and($ticket->return_date->toDateString())->toBe(now()->addDays(55)->toDateString())
        ->and($ticket->airline_id)->toBe($airline->id)
        ->and($ticket->airline_pnr)->toBe('ABC123')
        ->and($ticket->flight_number)->toBe('5J561')
        ->and($passenger->first_name)->toBe('Juan Carlos')
        ->and($passenger->gender)->toBe('male')
        ->and($passenger->date_of_birth->toDateString())->toBe('1990-05-14');

    expect(ActivityLog::where('action', 'UPDATE')->latest('id')->first()->description)
        ->toContain($ticket->booking_reference)
        ->toContain('contact_name')
        ->toContain('passenger 1');
});

test('editing leaves the fare, payment and passenger count alone', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);
    $ticket->receivePayment(2500, 'cash', $officer);

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, [
        'total_amount' => 1,
        'total_passengers' => 9,
        'amount_paid' => 99999,
        'status' => 'issued',
    ]))->assertSessionHasNoErrors();

    $ticket->refresh();

    expect((float) $ticket->total_amount)->toBe(10000.0)
        ->and($ticket->total_passengers)->toBe(1)
        ->and((float) $ticket->amount_paid)->toBe(2500.0)
        ->and($ticket->status)->not->toBe(TicketBooking::STATUS_ISSUED);
});

test('an issued or cancelled booking cannot be edited', function (string $state) {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);
    $ticket->receivePayment(10000, 'cash', $officer);
    $state === 'issued' ? $ticket->markAsIssued($officer) : $ticket->cancel($officer, 'Client withdrew');

    $this->actingAs($officer)->get(route('ticketing.tickets.edit', $ticket))
        ->assertRedirect(route('ticketing.tickets.show', $ticket))->assertSessionHas('error');

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, ['contact_name' => 'Someone Else']))
        ->assertSessionHas('error');

    expect($ticket->fresh()->contact_name)->toBe('Juan Dela Cruz');

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertDontSee(route('ticketing.tickets.edit', $ticket), false);
})->with(['issued', 'cancelled']);

test('the contact, dates and passenger names are checked', function (array $changes, array $passengerChanges, string $field) {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, $changes, $passengerChanges))
        ->assertSessionHasErrors($field);

    expect($ticket->fresh()->contact_name)->toBe('Juan Dela Cruz');
})->with([
    'no contact name' => [['contact_name' => ''], [], 'contact_name'],
    'bad email' => [['contact_email' => 'not-an-email'], [], 'contact_email'],
    'departure in the past' => [['departure_date' => '2020-01-01'], [], 'departure_date'],
    'return before departure' => [['return_date' => '2000-01-01'], [], 'return_date'],
    'no passenger name' => [[], ['first_name' => ''], 'passengers.0.first_name'],
    'bad pnr' => [['airline_pnr' => 'AB-12!'], [], 'airline_pnr'],
]);

test('a passenger id from another booking is refused', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);
    $other = editableTicket($officer);
    $otherPassenger = $other->passengers->first();

    $form = editForm($ticket);
    $form['passengers'][0]['id'] = $otherPassenger->id;
    $form['passengers'][0]['first_name'] = 'Hijacked';

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), $form)->assertSessionHasErrors('passengers.0.id');

    expect($otherPassenger->fresh()->first_name)->toBe('Juan');
});

test('a new departure date is held to the passport rule and the fare category', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer, ['travel_type' => 'international', 'trip_type' => 'one_way', 'return_date' => null], [
        'passport_number' => 'P1234567A',
        'passport_expiry_date' => now()->addMonths(10)->toDateString(),
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]);

    // Pushing the trip out so the passport has under six months left.
    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, [
        'departure_date' => now()->addMonths(6)->toDateString(),
        'return_date' => null,
    ], [
        'passport_number' => 'P1234567A',
        'passport_expiry_date' => now()->addMonths(10)->toDateString(),
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]))->assertSessionHasErrors('passengers.0.passport_expiry_date');

    // A birth date that makes the passenger a child is not an adult fare.
    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, ['return_date' => null], [
        'passport_number' => 'P1234567A',
        'passport_expiry_date' => now()->addYears(3)->toDateString(),
        'date_of_birth' => now()->subYears(5)->toDateString(),
    ]))->assertSessionHasErrors('passengers.0.date_of_birth');

    // And the original, valid details still save.
    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, ['return_date' => null], [
        'passport_number' => 'P7654321B',
        'passport_expiry_date' => now()->addYears(3)->toDateString(),
        'date_of_birth' => now()->subYears(30)->toDateString(),
    ]))->assertSessionHasNoErrors();

    expect($ticket->passengers()->first()->passport_number)->toBe('P7654321B');
});

test('saving without changes says so and logs nothing', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($officer);

    $this->actingAs($officer)->put(route('ticketing.tickets.update', $ticket), editForm($ticket))
        ->assertSessionHas('success', 'Nothing was changed.');

    expect(ActivityLog::where('action', 'UPDATE')->count())->toBe(0);
});

test('an officer cannot edit another officer\'s booking', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = editableTicket($owner);

    $this->actingAs($other)->get(route('ticketing.tickets.edit', $ticket))->assertNotFound();
    $this->actingAs($other)->put(route('ticketing.tickets.update', $ticket), editForm($ticket, ['contact_name' => 'Taken Over']))->assertNotFound();

    expect($ticket->fresh()->contact_name)->toBe('Juan Dela Cruz');
});
