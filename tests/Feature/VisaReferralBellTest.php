<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Support\VisaReferrals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    Storage::fake('public');
    Notification::fake();
});

function referredBooking(string $kind): TicketBooking
{
    $agent = User::factory()->create(['role' => 'ticketing']);
    $payload = bookingWithVisaChoice($kind === 'visa' ? 'needs_assistance' : 'visa_not_required');

    if ($kind === 'passport') {
        $payload['passengers'][0] = array_diff_key($payload['passengers'][0], array_flip(['passport_number', 'passport_expiry_date', 'passport_file']));
        $payload['passengers'][0]['needs_passport_assistance'] = '1';
    }

    test()->actingAs($agent)->post(route('ticketing.tickets.store'), $payload)->assertSessionHasNoErrors();

    return TicketBooking::latest('id')->firstOrFail();
}

test('a booking needing visa assistance shows in the Visa Assistance bell', function () {
    referredBooking('visa');
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $bell = $this->actingAs($officer)->getJson(route('visa.notifications'))->assertOk()->json();

    expect($bell['total'])->toBe(1)
        ->and($bell['latest'][0]['type'])->toBe('referral')
        ->and($bell['latest'][0]['title'])->toContain('client needs visa assistance')
        ->and($bell['latest'][0]['url'])->toBe(route('visa.referrals'));
});

test('a booking needing passport assistance shows in the bell too', function () {
    referredBooking('passport');
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->getJson(route('visa.notifications'))->assertOk()
        ->assertJsonPath('total', 1)->assertJsonPath('latest.0.title', fn ($t) => str_contains($t, 'client needs passport assistance'));
});

test('the bell is rendered in the Visa Assistance header with the count', function () {
    referredBooking('visa');
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.dashboard'))->assertOk()
        ->assertSee('client needs visa assistance', false)->assertSee('Ticketing referrals');
});

test('opening the referrals page lists the clients and clears the bell', function () {
    $ticket = referredBooking('passport');
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.referrals'))->assertOk()
        ->assertSee($ticket->booking_reference)->assertSee('This client needs passport assistance')->assertSee('Jose Rizal');

    $this->actingAs($officer)->getJson(route('visa.notifications'))->assertJsonPath('total', 0);
    // Still listed after being seen.
    $this->actingAs($officer)->get(route('visa.referrals'))->assertSee($ticket->booking_reference);
});

test('a booking with no help needed, a quotation, or an issued or cancelled ticket is not a referral', function () {
    $plain = referredBooking('visa');
    $plain->passengers()->update(['visa_status' => 'visa_not_required']);
    expect(VisaReferrals::query()->count())->toBe(0);

    $plain->passengers()->update(['visa_status' => 'needs_assistance']);
    expect(VisaReferrals::query()->count())->toBe(1);

    $plain->update(['status' => TicketBooking::STATUS_CANCELLED]);
    expect(VisaReferrals::query()->count())->toBe(0);

    $plain->update(['status' => TicketBooking::STATUS_PENDING, 'is_quotation' => true]);
    expect(VisaReferrals::query()->count())->toBe(0);
});

test('other desks cannot open the referrals', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $cashier = User::factory()->create(['role' => 'cashier']);

    $this->actingAs($agent)->get(route('visa.referrals'))->assertRedirect();
    $this->actingAs($cashier)->getJson(route('visa.notifications'))->assertForbidden();
});

test('with nothing referred the bell is empty and the page says so', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->getJson(route('visa.notifications'))->assertJsonPath('total', 0);
    $this->actingAs($officer)->get(route('visa.referrals'))->assertOk()->assertSee('No referrals right now.');
});
