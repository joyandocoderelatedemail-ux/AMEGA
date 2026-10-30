<?php

use App\Models\TicketBooking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * A ready-made package that includes its airfare: the wizard fills the Adult and Child fare rows
 * with the package's airfare per person (editable), and the package row carries the rest of the
 * price, so the total is the package price and nothing is charged twice.
 */
function airfarePackage(): TravelPackage
{
    return TravelPackage::create([
        'title' => 'RNZ HOTEL', 'duration' => '3 Days', 'price' => '₱15,000', 'price_amount' => 15000, 'price_currency' => 'PHP',
        'airfare_inclusion' => 'included', 'airfare_amount' => 5000,
        'rating' => 5, 'image' => 'images/test.jpg', 'description' => 'Test package', 'category' => 'short_haul', 'status' => 'active',
    ]);
}

test('the wizard fills the fare rows from the package airfare and lets the agent change them', function () {
    airfarePackage();
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('syncPackageAirfare()')
        ->toContain("const PACKAGE_AIRFARE_TYPES = ['adult', 'child'];")
        ->toContain('fare_auto: { adult: true, child: true }')
        ->toContain('Use package airfare')
        // Typing a fare keeps it; a saved form follows the package only on an empty row.
        ->toContain('this.formData.fare_auto = { ...(this.formData.fare_auto || {}), [type]: false };')
        ->toContain('this.upgradeFareAuto(parsed);')
        // The package row is the price less the airfare.
        ->toContain('(parseFloat(pkg.price_amount) || 0) - this.packageAirfare()')
        // The page knows the package's airfare.
        ->toContain('airfare_amount')->toContain('RNZ HOTEL');
});

test('a booking priced from the package airfare keeps the package price as its total', function () {
    $package = airfarePackage();

    // What the wizard sends for 1 adult and 1 child: ₱5,000 each in the fare rows, ₱10,000 × 2 for the package.
    $this->actingAs(User::factory()->create(['role' => 'ticketing']))->post(route('ticketing.tickets.store'), [...bookingFlight(),
        'travel_type' => 'domestic', 'package_type' => 'with_package', 'travel_package_id' => $package->id, 'origin' => 'Manila (MNL)', 'destination' => 'Cebu',
        'trip_type' => 'one_way', 'departure_date' => Carbon::today()->addMonths(2)->toDateString(),
        'total_passengers' => 2, 'adults_count' => 1, 'children_count' => 1, 'infants_count' => 0,
        'contact_name' => 'Ana Cruz', 'contact_email' => 'airfare@example.com', 'contact_phone' => '09181112222',
        'fare_prices' => ['adult' => '5000.00', 'child' => '5000.00'], 'package_amount' => 20000,
        'passengers' => [
            ['passport_expiry_date' => now()->addYears(3)->toDateString(), 'first_name' => 'Ana', 'last_name' => 'Cruz', 'passenger_type' => 'adult', 'nationality_type' => 'filipino'],
            ['passport_expiry_date' => now()->addYears(3)->toDateString(), 'first_name' => 'Ben', 'last_name' => 'Cruz', 'passenger_type' => 'child', 'nationality_type' => 'filipino', 'date_of_birth' => now()->subYears(8)->toDateString()],
        ],
    ])->assertSessionHasNoErrors();

    $ticket = TicketBooking::where('contact_email', 'airfare@example.com')->firstOrFail();

    expect((float) $ticket->estimated_fare)->toBe(10000.0)
        ->and((float) $ticket->package_amount)->toBe(20000.0)
        ->and((float) $ticket->total_amount)->toBe(30000.0);
});

test('the selected package card on the first step has a Show details button with everything the package carries', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect(substr_count($html, 'Show details'))->toBeGreaterThan(0)
        ->and(str_contains($html, 'aria-controls="selected-package-details"'))->toBeTrue()
        ->and(substr_count($html, 'packageDetails(selectedPackage)'))->toBe(2)
        ->and(substr_count($html, 'packageNotes(selectedPackage)'))->toBe(2);
});

test('step 6 shows the company of a corporate booking above the booker', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    $contact = substr($html, strpos($html, 'Booking Agent / Client Contact:'), 1500);
    expect($contact)->toContain('x-show="formData.corporate_account_id"')
        ->toContain('Company:')
        ->toContain('x-text="formData.corporate_name"');
});
