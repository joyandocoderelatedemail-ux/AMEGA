<?php

use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * PWD / Senior Citizen is a discount on some of the passengers, not a passenger type:
 * count × (fare + package per person) × rate, taken off the total. The passenger count never
 * changes, and the receipt shows the same sum that is saved.
 */
function pwdBooking(array $changes = []): array
{
    return array_merge(bookingFlight(), [
        'travel_type' => 'domestic', 'package_type' => 'without_package', 'origin' => 'Manila (MNL)', 'destination' => 'Cebu',
        'trip_type' => 'one_way', 'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 2, 'adults_count' => 2, 'children_count' => 0, 'infants_count' => 0,
        'contact_name' => 'Lola Santos', 'contact_email' => 'pwd@example.com', 'contact_phone' => '09181112222',
        'fare_prices' => ['adult' => '1500'],
        'passengers' => [['passport_expiry_date' => now()->addYears(3)->toDateString(), 'first_name' => 'Lola', 'last_name' => 'Santos', 'passenger_type' => 'adult', 'nationality_type' => 'filipino']],
    ], $changes);
}

test('the worked example: 2 adults, a package, 1 PWD/SC at 20% takes off 600 and the adults stay 2', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $package = TravelPackage::create([
        'title' => 'RNZ HOTL', 'duration' => '3 Days', 'price' => '₱1,500', 'price_amount' => 1500, 'price_currency' => 'PHP',
        'rating' => 5, 'image' => 'images/test.jpg', 'description' => 'Test package', 'category' => 'short_haul', 'status' => 'active',
    ]);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'package_type' => 'with_package', 'travel_package_id' => $package->id, 'package_amount' => 3000,
        'pwd_sc_count' => 1, 'pwd_sc_rate' => 20,
        // Whatever the browser sends, the server works the total out itself.
        'total_amount' => 99999,
    ]))->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();

    // 1 × (1,500 fare + 1,500 package per person) × 20% = 600; 3,000 + 3,000 − 600 = 5,400.
    expect($ticket->adults_count)->toBe(2)
        ->and($ticket->fare_breakdown['adult']['qty'])->toBe(2)
        ->and((float) $ticket->pwd_sc_discount)->toBe(600.0)
        ->and((float) $ticket->total_amount)->toBe(5400.0);
});

test('the discount is worked out in one place, and a count of 0 or no rate takes nothing off', function () {
    $perPerson = TicketBooking::pwdScPerPerson(['adult' => ['qty' => 2, 'price' => 1500]], 3000, 3000, 2);

    expect($perPerson)->toBe(3000.0)
        ->and(TicketBooking::pwdScDiscountFor(1, 20, $perPerson, 2))->toBe(600.0)
        ->and(TicketBooking::pwdScDiscountFor(0, 20, $perPerson, 2))->toBe(0.0)
        ->and(TicketBooking::pwdScDiscountFor(2, null, $perPerson, 2))->toBe(0.0)
        // Never more people than travel.
        ->and(TicketBooking::pwdScDiscountFor(5, 20, $perPerson, 2))->toBe(1200.0)
        // No adults: the average fare.
        ->and(TicketBooking::pwdScPerPerson(['child' => ['qty' => 2, 'price' => 800]], 1600, 0, 2))->toBe(800.0);
});

test('the PWD/SC count cannot exceed the passengers', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking(['pwd_sc_count' => 3]))
        ->assertSessionHasErrors('pwd_sc_count');
    // Children can be PWD too: up to every passenger, not just the adults.
    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'total_passengers' => 2, 'adults_count' => 1, 'children_count' => 1, 'pwd_sc_count' => 2,
        'fare_prices' => ['adult' => '1500', 'child' => '1000'],
        'passengers' => [
            ['passport_expiry_date' => now()->addYears(3)->toDateString(), 'first_name' => 'Lola', 'last_name' => 'Santos', 'passenger_type' => 'adult', 'nationality_type' => 'filipino'],
            ['passport_expiry_date' => now()->addYears(3)->toDateString(), 'first_name' => 'Ben', 'last_name' => 'Santos', 'passenger_type' => 'child', 'nationality_type' => 'filipino', 'date_of_birth' => now()->subYears(8)->toDateString()],
        ],
    ]))->assertSessionHasNoErrors();
});

test('the wizard has no PWD/SC passenger row, a discount control, the package in the fare table and one total', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->not->toContain('Counted out of the Adults')
        ->not->toContain('Add a PWD / Senior Citizen fare')
        ->not->toContain('Estimated Grand Total')
        ->toContain('name="pwd_sc_count"')->toContain('name="pwd_sc_rate"')
        ->toContain("'Cannot exceed total passengers ('")
        ->not->toContain('Quotation Summary')->toContain('Total Amount')
        // The tour package is a row of the fare table, with its price each and total.
        ->toContain('setPackagePriceEach($event.target.value)')->toContain('id="package_amount" name="package_amount"')
        ->toContain('const PWD_SC_DISCOUNTABLE = ')
        // A form saved when PWD / SC was a fare of its own is converted as it loads.
        ->toContain('this.upgradePwdDiscount(parsed);')->toContain('this.upgradePwdDiscount(payload);');
});

test('editing the prices works the discount out again; a booking priced with an old PWD fare keeps it', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking(['pwd_sc_count' => 1, 'pwd_sc_rate' => 20]))->assertSessionHasNoErrors();
    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();
    expect((float) $ticket->total_amount)->toBe(2700.0);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.edit', $ticket))->assertOk()->assertSee('name="pwd_sc_count"', false);

    // The adult fare goes up to 2,000: 4,000 less 1 × 2,000 × 20% = 3,600.
    $this->actingAs($ticketing)->put(route('ticketing.tickets.update', $ticket), [
        ...bookingFlight(),
        'travel_type' => 'domestic', 'trip_type' => 'one_way', 'origin' => 'Manila (MNL)', 'destination' => 'Cebu',
        'departure_date' => $ticket->departure_date->toDateString(), 'contact_name' => 'Lola Santos', 'contact_email' => 'pwd@example.com', 'contact_phone' => '09181112222',
        'fare_prices' => ['adult' => '2000'], 'pwd_sc_count' => 1, 'pwd_sc_rate' => 20,
        'passengers' => $ticket->passengers->map(fn ($p) => ['id' => $p->id, 'first_name' => $p->first_name, 'last_name' => $p->last_name,
            'passport_expiry_date' => now()->addYears(3)->toDateString()])->all(),
    ])->assertSessionHasNoErrors();

    expect((float) $ticket->fresh()->pwd_sc_discount)->toBe(400.0)->and((float) $ticket->fresh()->total_amount)->toBe(3600.0);

    // Saved when PWD / SC was a fare of its own: that fare stays, nothing is taken off twice.
    $ticket->forceFill(['fare_breakdown' => ['adult' => ['qty' => 1, 'price' => 2000, 'subtotal' => 2000], 'pwd_sc' => ['qty' => 1, 'price' => 1600, 'subtotal' => 1600]],
        'estimated_fare' => 3600, 'pwd_sc_count' => 1, 'pwd_sc_rate' => null, 'pwd_sc_discount' => 0, 'total_amount' => 3600])->save();
    $this->actingAs($ticketing)->get(route('ticketing.tickets.edit', $ticket))->assertOk()->assertDontSee('name="pwd_sc_count"', false);
    $this->actingAs($ticketing)->get(route('ticketing.tickets.show', $ticket))->assertOk()->assertSee('PWD / Senior Citizen')->assertDontSee('Discount (1 ×');
});

test('the discount can be a peso amount per PWD/SC passenger instead of a percentage', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    // 2 adults × 1,500 = 3,000; 2 PWD/SC × ₱250 = 500 off, whatever the rate says.
    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'pwd_sc_count' => 2, 'pwd_sc_mode' => 'amount', 'pwd_sc_amount' => 250, 'pwd_sc_rate' => 20,
    ]))->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();
    expect((float) $ticket->pwd_sc_amount)->toBe(250.0)
        ->and($ticket->pwd_sc_rate)->toBeNull()
        ->and((float) $ticket->pwd_sc_discount)->toBe(500.0)
        ->and((float) $ticket->total_amount)->toBe(2500.0)
        ->and($ticket->pwdScDiscountLabel())->toBe('PWD / Senior Citizen Discount (2 × ₱250.00)')
        ->and(TicketBooking::pwdScDiscountFor(2, null, 3000, 2, 250))->toBe(500.0);

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))->getContent();
    expect(str_contains($html, 'name="pwd_sc_mode"'))->toBeTrue()
        ->and(str_contains($html, 'name="pwd_sc_amount"'))->toBeTrue();
    $this->actingAs($ticketing)->get(route('ticketing.tickets.edit', $ticket))->assertSee('Peso amount each (₱)');
});
