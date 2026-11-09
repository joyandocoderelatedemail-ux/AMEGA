<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\PassportAssistanceRequestedNotification;
use App\Notifications\VisaAssistanceRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
    Notification::fake();
});

/** An international booking whose passenger has no passport (no number, expiry or scan). */
function bookingWithoutPassport(bool $assistance): array
{
    $payload = bookingWithVisaChoice('visa_not_required');
    $payload['passengers'][0] = array_diff_key($payload['passengers'][0], array_flip(['passport_number', 'passport_expiry_date', 'passport_file']));

    if ($assistance) {
        $payload['passengers'][0]['needs_passport_assistance'] = '1';
    }

    return $payload;
}

test('the wizard offers Need Passport Assistance for international bookings', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->get(route('ticketing.tickets.create'))->assertOk()
        ->assertSee('Need Passport Assistance', false)
        ->assertSee('needs_passport_assistance', false)
        ->assertSee('This client needs passport assistance', false);
});

test('a passenger needing passport assistance can be booked without a passport and the Visa Assistance desk is told', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithoutPassport(true))->assertSessionHasNoErrors();

    $passenger = TicketBooking::firstOrFail()->passengers()->firstOrFail();
    expect($passenger->needs_passport_assistance)->toBeTrue();

    Notification::assertSentTo($visaOfficer, PassportAssistanceRequestedNotification::class, fn ($n) => $n->passengers->first()->full_name === 'Jose Rizal');
    Notification::assertNotSentTo($visaOfficer, VisaAssistanceRequestedNotification::class);
    Notification::assertNotSentTo($admin, PassportAssistanceRequestedNotification::class);
    Notification::assertNotSentTo($agent, PassportAssistanceRequestedNotification::class);
});

test('without the tick a missing passport is still refused', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithoutPassport(false))
        ->assertSessionHasErrors(['passengers.0.passport_file', 'passengers.0.passport_number']);
    expect(TicketBooking::count())->toBe(0);
    Notification::assertNothingSent();
});

test('the ticket stays held until the passport is on file', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithoutPassport(true))->assertSessionHasNoErrors();

    $missing = TicketBooking::firstOrFail()->missingDocuments();

    expect($missing)->toHaveCount(1)
        ->and($missing->first()['missing']['passport_scan'])->toContain('passport assistance');
});

test('the booking page says the client needs passport assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($agent)->post(route('ticketing.tickets.store'), bookingWithoutPassport(true))->assertSessionHasNoErrors();

    $this->actingAs($agent)->get(route('ticketing.tickets.show', TicketBooking::firstOrFail()))->assertOk()
        ->assertSee('Needs passport assistance: Jose Rizal');
});

test('a domestic booking never records passport assistance', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $payload = bookingWithoutPassport(true);
    $payload['travel_type'] = 'domestic';

    // The domestic form has no such option; whatever is sent is ignored.
    $this->actingAs($agent)->post(route('ticketing.tickets.store'), $payload);

    expect(TicketBooking::query()->first()?->passengers()->where('needs_passport_assistance', true)->exists() ?? false)->toBeFalse();
});

test('an expired passport can be booked with passport assistance, and is refused without it', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $expired = bookingWithVisaChoice('visa_not_required');
    $expired['passengers'][0]['passport_expiry_date'] = now()->subMonth()->toDateString();

    $this->actingAs($agent)->post(route('ticketing.tickets.store'), $expired)->assertSessionHasErrors('passengers.0.passport_expiry_date');
    expect(TicketBooking::count())->toBe(0);

    $expired['passengers'][0]['needs_passport_assistance'] = '1';
    $this->actingAs($agent)->post(route('ticketing.tickets.store'), $expired)->assertSessionHasNoErrors();
    expect(TicketBooking::count())->toBe(1);
});

test('Step 2 offers passport assistance when the passport has expired', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->get(route('ticketing.tickets.create'))->assertOk()
        ->assertSee('Client needs passport assistance (renewal)', false);
});
