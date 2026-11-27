<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Services\BookingAgreementDrafter;

/**
 * Each passenger has a fare of their own. A type's total is the sum of its passengers' fares;
 * split per passenger, they can differ (Adult 1 ₱3,000, Adult 2 ₱7,000).
 */
test('a total is shared to the centavo, the leftover to the first passenger', function () {
    expect(TicketBooking::splitEvenly(10001, 3))->toBe([3333.68, 3333.66, 3333.66])
        ->and(array_sum(TicketBooking::splitEvenly(10001, 3)))->toEqualWithDelta(10001, 0.001)
        ->and(TicketBooking::splitEvenly(1000, 3))->toBe([333.34, 333.33, 333.33])
        ->and(TicketBooking::splitEvenly(500, 0))->toBe([]);
});

test('passengers of one type can pay different prices, and the documents list them one by one', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'fare_prices' => ['adult' => '5000'],
        'fare_items' => ['adult' => ['3000', '7000']],
    ]))->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();
    expect($ticket->fare_breakdown['adult'])->toMatchArray(['qty' => 2, 'price' => 5000.0, 'subtotal' => 10000.0, 'prices' => [3000.0, 7000.0], 'split' => true])
        ->and((float) $ticket->estimated_fare)->toBe(10000.0)
        ->and((float) $ticket->total_amount)->toBe(10000.0)
        // By name, in passenger order; a passenger with no name yet is "Adult 2".
        ->and(array_column($ticket->fareLines(), 'label'))->toBe(['Lola Santos (Adult)', 'Adult 2'])
        ->and(array_column($ticket->fareLines(), 'subtotal'))->toBe([3000.0, 7000.0]);

    $items = collect(app(BookingAgreementDrafter::class)->defaults($ticket)['pricingItems'])->pluck('amount')->all();
    expect($items)->toContain(3000.0)->toContain(7000.0);
});

test('a typed total keeps its passengers equal and adds up exactly', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'total_passengers' => 3, 'adults_count' => 3,
        'fare_totals' => ['adult' => '10001'],
    ]))->assertSessionHasNoErrors();

    $row = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail()->fare_breakdown['adult'];
    expect($row['prices'])->toBe([3333.68, 3333.66, 3333.66])
        ->and($row['subtotal'])->toEqual(10001)
        ->and($row['split'])->toBeFalse();
});

test('the PWD / SC discount is taken from the ticked passengers own fares', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    // Adult 2 (₱7,000) is the senior citizen: 20% of 7,000 = 1,400, whatever count was typed.
    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'fare_items' => ['adult' => ['3000', '7000']],
        'fare_pwd' => ['adult' => [1]],
        'pwd_sc_count' => 0, 'pwd_sc_rate' => 20,
    ]))->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();
    expect($ticket->pwd_sc_count)->toBe(1)
        ->and($ticket->fare_breakdown['adult']['pwd'])->toBe([1])
        ->and((float) $ticket->pwd_sc_discount)->toBe(1400.0)
        ->and((float) $ticket->total_amount)->toBe(8600.0);
});

test('editing a split booking changes one passenger and keeps the rest', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    $this->actingAs($ticketing)->post(route('ticketing.tickets.store'), pwdBooking([
        'fare_items' => ['adult' => ['3000', '7000']],
    ]))->assertSessionHasNoErrors();
    $ticket = TicketBooking::where('contact_email', 'pwd@example.com')->firstOrFail();

    $html = $this->actingAs($ticketing)->get(route('ticketing.tickets.edit', $ticket))->assertOk()->getContent();
    expect($html)->toContain('name="fare_items[adult][]"')->not->toContain('name="fare_prices[adult]"');

    $this->actingAs($ticketing)->put(route('ticketing.tickets.update', $ticket), [
        ...bookingFlight(),
        'travel_type' => 'domestic', 'trip_type' => 'one_way', 'origin' => 'Manila (MNL)', 'destination' => 'Cebu',
        'departure_date' => $ticket->departure_date->toDateString(), 'contact_name' => 'Lola Santos', 'contact_email' => 'pwd@example.com', 'contact_phone' => '09181112222',
        'fare_items' => ['adult' => ['3000', '8000']],
        'passengers' => $ticket->passengers->map(fn ($p) => ['id' => $p->id, 'first_name' => $p->first_name, 'last_name' => $p->last_name,
            'passport_expiry_date' => now()->addYears(3)->toDateString()])->all(),
    ])->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->fare_breakdown['adult'])->toMatchArray(['qty' => 2, 'price' => 5500.0, 'subtotal' => 11000.0, 'prices' => [3000.0, 8000.0], 'split' => true])
        ->and((float) $ticket->estimated_fare)->toBe(11000.0)
        ->and((float) $ticket->total_amount)->toBe(11000.0);
});

test('the wizard offers a split per passenger and makes all equal again', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('Split per passenger')
        ->toContain('Make all equal')
        ->toContain('x-text="farePassengerName(type, i)"')
        ->toContain(":name=\"isFareSplit(type) ? 'fare_items[' + type + '][]' : null\"")
        ->toContain(":name=\"'fare_pwd[' + type + '][]'\"")
        // The same centavo rule as the server.
        ->toContain('shares[0] += cents - share * qty;');
});
