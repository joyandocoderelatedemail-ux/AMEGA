<?php

use App\Models\BookingAgreement;
use App\Models\TicketBooking;
use App\Models\TicketFlightChange;
use App\Models\User;
use App\Notifications\FlightChangedNotification;
use App\Services\BookingAgreementDrafter;
use App\Services\SmsSender;
use App\Support\FlightChangeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

function flightTicket(User $officer, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $officer->id,
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'trip_type' => 'round_trip',
        'departure_date' => Carbon::today()->addDays(10),
        'return_date' => Carbon::today()->addDays(14),
        'flight_number' => '5J 5054',
        'departure_time' => '06:00',
        'arrival_time' => '07:30',
        'return_flight_number' => '5J 5055',
        'return_departure_time' => '18:00',
        'return_arrival_time' => '19:30',
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ], $overrides));
}

function recordChange($test, User $officer, TicketBooking $ticket, array $data = [])
{
    return $test->actingAs($officer)->post(route('ticketing.tickets.flight-changes.store', $ticket), $data + [
        'type' => 'delay',
        'departure_time' => '08:30',
        'arrival_time' => '10:00',
    ]);
}

test('a delay updates the booking, keeps the history and emails the client', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);

    recordChange($this, $officer, $ticket, ['reason' => 'Aircraft change', 'notify_email' => '1'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $ticket->refresh();
    expect(substr($ticket->departure_time, 0, 5))->toBe('08:30')
        ->and(substr($ticket->arrival_time, 0, 5))->toBe('10:00')
        // What was not mentioned stays as it was.
        ->and($ticket->flight_number)->toBe('5J 5054')
        ->and($ticket->departure_date->toDateString())->toBe(Carbon::today()->addDays(10)->toDateString());

    $change = $ticket->flightChanges()->firstOrFail();
    expect($change->type)->toBe('delay')
        ->and($change->schedule_before['departure_time'])->toBe('06:00')
        ->and($change->schedule_after['departure_time'])->toBe('08:30')
        ->and($change->reason)->toBe('Aircraft change')
        ->and($change->recorded_by)->toBe($officer->id)
        ->and($change->clientIsNotified())->toBeTrue()
        ->and($change->notified_via)->toBe('email');

    Notification::assertSentOnDemand(FlightChangedNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === ['juan@example.com' => 'Juan Dela Cruz'];
    });
});

test('a new date and flight number are applied, and the return leg too on a round trip', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);
    $newDeparture = Carbon::today()->addDays(12)->toDateString();
    $newReturn = Carbon::today()->addDays(16)->toDateString();

    recordChange($this, $officer, $ticket, [
        'type' => 'reschedule',
        'departure_date' => $newDeparture,
        'flight_number' => '5j 5060',
        'departure_time' => '06:00',
        'return_date' => $newReturn,
        'return_flight_number' => 'PR 2002',
    ])->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->departure_date->toDateString())->toBe($newDeparture)
        ->and($ticket->return_date->toDateString())->toBe($newReturn)
        ->and($ticket->flight_number)->toBe('5J5060')
        ->and($ticket->return_flight_number)->toBe('PR2002');

    $moves = collect($ticket->flightChanges()->first()->movements())->pluck('label')->all();
    expect($moves)->toContain('Departure date', 'Flight number', 'Return date', 'Return flight number')
        // The departure time was sent unchanged, so it is not listed as moved.
        ->not->toContain('Departure time');
});

test('a change that changes nothing, or a bad date, is refused', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);

    recordChange($this, $officer, $ticket, ['departure_time' => '06:00', 'arrival_time' => '07:30'])
        ->assertSessionHasErrors('changes');

    recordChange($this, $officer, $ticket, ['departure_date' => Carbon::yesterday()->toDateString()])
        ->assertSessionHasErrors('departure_date');

    recordChange($this, $officer, $ticket, ['return_date' => Carbon::today()->addDays(5)->toDateString()])
        ->assertSessionHasErrors('return_date');

    recordChange($this, $officer, $ticket, ['type' => 'nonsense'])->assertSessionHasErrors('type');

    expect($ticket->flightChanges()->count())->toBe(0);
});

test('a flight the airline cancelled is recorded without moving the booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.flight-changes.store', $ticket), [
        'type' => 'airline_cancelled',
        'reason' => 'Route discontinued',
        'notify_email' => '1',
    ])->assertSessionHasNoErrors();

    $ticket->refresh();
    $change = $ticket->flightChanges()->firstOrFail();

    expect($ticket->departure_date->toDateString())->toBe(Carbon::today()->addDays(10)->toDateString())
        ->and($ticket->isCancelled())->toBeFalse()
        ->and($change->isAirlineCancellation())->toBeTrue()
        ->and($change->movements())->toBe([])
        ->and(FlightChangeMessage::text($change))->toContain('cancelled by the airline')->toContain('rebooking');

    Notification::assertSentOnDemand(FlightChangedNotification::class);
});

test('it works on an issued ticket, and the fee is only added while the ticket is not issued', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $unissued = flightTicket($officer);
    $unissued->recordPayment(10000);
    expect($unissued->fresh()->isFullyPaid())->toBeTrue();

    recordChange($this, $officer, $unissued, ['type' => 'client_request', 'change_fee' => '1500'])->assertSessionHasNoErrors();

    $unissued->refresh();
    // The fee raises the total, so the booking is no longer paid in full.
    expect((float) $unissued->total_amount)->toBe(11500.0)
        ->and((float) $unissued->other_charges)->toBe(1500.0)
        ->and($unissued->payment_status)->toBe(TicketBooking::PAYMENT_PARTIAL)
        ->and($unissued->flightChanges()->first()->fee_added)->toBeTrue();

    $issued = flightTicket($officer);
    $issued->recordPayment(10000);
    $issued->markAsIssued($officer);

    recordChange($this, $officer, $issued, ['type' => 'client_request', 'change_fee' => '1500'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'collect it separately'));

    $issued->refresh();
    expect($issued->isIssued())->toBeTrue()
        ->and((float) $issued->total_amount)->toBe(10000.0)
        ->and(substr($issued->departure_time, 0, 5))->toBe('08:30')
        ->and($issued->flightChanges()->first()->fee_added)->toBeFalse();
});

test('a cancelled booking or a quotation has no flight to change', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $cancelled = flightTicket($officer);
    $cancelled->cancel($officer, 'Client withdrew');
    recordChange($this, $officer, $cancelled)->assertSessionHas('error');

    $quotation = flightTicket($officer, ['is_quotation' => true]);
    recordChange($this, $officer, $quotation)->assertSessionHas('error');

    expect(TicketFlightChange::count())->toBe(0);
});

test('with no email or SMS the client is not told, and staff mark them told after sending it themselves', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer, ['contact_email' => null]);

    recordChange($this, $officer, $ticket, ['notify_email' => '1', 'notify_sms' => '1'])
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'not been told yet')
            && str_contains($message, 'no client email address')
            && str_contains($message, 'SMS is not set up yet'));

    Notification::assertNothingSent();
    $change = $ticket->flightChanges()->firstOrFail();
    expect($change->clientIsNotified())->toBeFalse();

    $this->actingAs($officer)->get(route('ticketing.dashboard'))->assertOk()->assertSee('Flight Changes To Tell')->assertSee($ticket->booking_reference);

    $this->actingAs($officer)->post(route('ticketing.tickets.flight-changes.notify', [$ticket, $change]), ['channel' => 'manual'])
        ->assertSessionHas('success');

    expect($change->fresh()->clientIsNotified())->toBeTrue()
        ->and($change->fresh()->notified_via)->toBe('manual');

    $this->actingAs($officer)->get(route('ticketing.dashboard'))->assertDontSee('Flight Changes To Tell');
});

test('the message to copy lists what moved, and the SMS is the short version', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);
    recordChange($this, $officer, $ticket, ['reason' => 'Weather']);

    $change = $ticket->flightChanges()->firstOrFail()->setRelation('ticket', $ticket->fresh());
    $text = FlightChangeMessage::text($change);
    $sms = FlightChangeMessage::text($change, compact: true);

    expect($text)->toContain('Departure time: 8:30 AM (was 6:00 AM)')
        ->toContain('Arrival time: 10:00 AM (was 7:30 AM)')
        ->toContain('Reason: Weather')
        ->toContain($ticket->booking_reference)
        ->and($sms)->toContain('Departure time: 8:30 AM (was 6:00 AM)')
        ->not->toContain('Reason')
        ->and(strlen($sms))->toBeLessThan(400);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Flight Changes')
        ->assertSee('Message for the client')
        ->assertSee('Departure time: 8:30 AM (was 6:00 AM)')
        ->assertSee('Client not told yet');
});

test('the SMS option is off until a provider is set up, then sends through the driver', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);

    expect(SmsSender::enabled())->toBeFalse()
        ->and(SmsSender::send('09171234567', 'hello'))->toBeFalse();

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))->assertSee('SMS is not set up yet');

    config(['sms.driver' => 'log']);
    Log::spy();

    expect(SmsSender::enabled())->toBeTrue();

    recordChange($this, $officer, $ticket, ['notify_sms' => '1'])->assertSessionHas('success');

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []) => $message === 'SMS (log driver)' && $context['to'] === '+639171234567')->once();
    expect($ticket->flightChanges()->first()->notified_via)->toBe('sms');
});

test('mobile numbers are put in international form, and anything else is refused', function () {
    expect(SmsSender::normalise('0917 123 4567'))->toBe('+639171234567')
        ->and(SmsSender::normalise('+63 917 123 4567'))->toBe('+639171234567')
        ->and(SmsSender::normalise('639171234567'))->toBe('+639171234567')
        ->and(SmsSender::normalise('9171234567'))->toBe('+639171234567')
        ->and(SmsSender::normalise('+1 415 555 0100'))->toBe('+14155550100')
        ->and(SmsSender::normalise('123'))->toBeNull()
        ->and(SmsSender::normalise(''))->toBeNull()
        ->and(SmsSender::normalise(null))->toBeNull();
});

test('the booking agreement is flagged as out of date once the flight changes after it', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);
    $agreement = app(BookingAgreementDrafter::class)->ensureFor($ticket, $officer);

    // Back-date the agreement so the change is clearly later.
    BookingAgreement::whereKey($agreement->id)->update(['updated_at' => now()->subDay()]);
    expect($ticket->fresh()->agreementOutdated())->toBeFalse();

    recordChange($this, $officer, $ticket);

    expect($ticket->fresh()->agreementOutdated())->toBeTrue();

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('still shows the old schedule');
    $this->actingAs($officer)->get(route('ticketing.agreements.show', $agreement->fresh()))
        ->assertOk()
        ->assertSee('The flight changed after this agreement was made');
});

test('the voucher tells the client the schedule changed', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);
    recordChange($this, $officer, $ticket, ['reason' => 'Aircraft change']);

    $this->actingAs($officer)->get(route('ticketing.tickets.voucher', $ticket))
        ->assertOk()
        ->assertSee('Schedule changes')
        ->assertSee('Departure time: 8:30 AM (was 6:00 AM)')
        ->assertSee('Aircraft change');
});

test('another officer cannot change or look at a flight that is not theirs', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($owner);
    recordChange($this, $owner, $ticket);
    $change = $ticket->flightChanges()->firstOrFail();

    $this->actingAs($other)->post(route('ticketing.tickets.flight-changes.store', $ticket), ['type' => 'delay', 'departure_time' => '09:00'])->assertNotFound();
    $this->actingAs($other)->post(route('ticketing.tickets.flight-changes.notify', [$ticket, $change]), ['channel' => 'manual'])->assertNotFound();

    // Nor does the change turn up on their dashboard.
    $this->actingAs($other)->get(route('ticketing.dashboard'))->assertOk()->assertDontSee('Flight Changes To Tell');
});

test('a change recorded against another ticket cannot be reached through this one', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = flightTicket($officer);
    $other = flightTicket($officer);
    recordChange($this, $officer, $other);
    $change = $other->flightChanges()->firstOrFail();

    $this->actingAs($officer)->post(route('ticketing.tickets.flight-changes.notify', [$ticket, $change]), ['channel' => 'manual'])->assertNotFound();
    expect($change->fresh()->clientIsNotified())->toBeFalse();
});
