<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use Database\Seeders\TravelPackageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 */
function makePackage(array $overrides = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'title' => 'Test Package',
        'duration' => '5 Days',
        'rating' => 5,
        'image' => 'images/test.jpg',
        'description' => 'Test package',
        'category' => 'short_haul',
        'status' => 'active',
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeBooking(TravelPackage $package, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'booking_reference' => Booking::generateReference(),
        'travel_package_id' => $package->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(10)->format('Y-m-d'),
        'number_of_passengers' => 1,
    ], $overrides));
}

/**
 * Book through the real endpoint so the controller's billing is exercised.
 */
function bookThroughEndpoint(TestCase $test, TravelPackage $package, int $passengers): Booking
{
    $test->post('/bookings', [
        'travel_package_id' => $package->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(10)->format('Y-m-d'),
        'number_of_passengers' => $passengers,
    ])->assertRedirect();

    return Booking::latest('id')->firstOrFail();
}

// ---------------------------------------------------------------------------
// The display string <-> numeric bridge
// ---------------------------------------------------------------------------

test('a legacy display string is parsed into an amount and a currency', function () {
    $peso = makePackage(['price' => '₱14,999']);
    $dollar = makePackage(['price' => '$2,399', 'title' => 'US Package']);

    expect((float) $peso->price_amount)->toBe(14999.0);
    expect($peso->price_currency)->toBe('PHP');

    expect((float) $dollar->price_amount)->toBe(2399.0);
    expect($dollar->price_currency)->toBe('USD');
});

test('a numeric amount renders the display string', function () {
    $package = makePackage(['price_amount' => 18500, 'price_currency' => 'PHP']);

    expect($package->price)->toBe('₱18,500');

    $usd = makePackage(['price_amount' => 4299, 'price_currency' => 'USD', 'title' => 'US']);
    expect($usd->price)->toBe('$4,299');
});

test('changing the amount re-renders the display string', function () {
    $package = makePackage(['price_amount' => 1000, 'price_currency' => 'USD']);
    expect($package->price)->toBe('$1,000');

    $package->update(['price_amount' => 2500]);

    expect($package->fresh()->price)->toBe('$2,500');
});

test('changing the display string re-derives the amount', function () {
    $package = makePackage(['price_amount' => 1000, 'price_currency' => 'USD']);

    $package->update(['price' => '₱7,500']);

    $package = $package->fresh();
    expect((float) $package->price_amount)->toBe(7500.0);
    expect($package->price_currency)->toBe('PHP');
});

test('changing only the currency re-renders the label at the same amount', function () {
    $package = makePackage(['price_amount' => 3000, 'price_currency' => 'PHP']);
    expect($package->price)->toBe('₱3,000');

    $package->update(['price_currency' => 'USD']);

    $package = $package->fresh();
    expect($package->price)->toBe('$3,000');
    expect((float) $package->price_amount)->toBe(3000.0);
});

test('an unparseable price leaves the amount null rather than inventing one', function () {
    $package = makePackage(['price' => 'Price on request']);

    expect($package->price_amount)->toBeNull();
    expect($package->hasNumericPrice())->toBeFalse();
    // The label is preserved untouched.
    expect($package->price)->toBe('Price on request');
});

test('the parse helper reads both currency symbols', function () {
    expect(TravelPackage::parsePrice('₱14,999'))->toBe([14999.0, 'PHP']);
    expect(TravelPackage::parsePrice('$2,399'))->toBe([2399.0, 'USD']);
    expect(TravelPackage::parsePrice('PHP 5,000'))->toBe([5000.0, 'PHP']);
    expect(TravelPackage::parsePrice('USD 1,200.50'))->toBe([1200.5, 'USD']);
    expect(TravelPackage::parsePrice('ask us'))->toBe([null, 'PHP']);
});

// ---------------------------------------------------------------------------
// Bookings are billed per person
// ---------------------------------------------------------------------------

test('a booking is billed at the per person price times the party size', function () {
    $package = makePackage(['price_amount' => 2000, 'price_currency' => 'USD']);

    $booking = bookThroughEndpoint($this, $package, 4);

    expect((float) $booking->amount_due)->toBe(8000.0);
    expect($booking->currency)->toBe('USD');
    expect($booking->formatted_amount)->toBe('$8,000');
});

test('party size actually changes what is owed', function () {
    $package = makePackage(['price_amount' => 15000, 'price_currency' => 'PHP']);

    $single = bookThroughEndpoint($this, $package, 1);
    $full = bookThroughEndpoint($this, $package, 50);

    // Previously both were simply a copy of the package's display price.
    expect((float) $single->amount_due)->toBe(15000.0);
    expect((float) $full->amount_due)->toBe(750000.0);
    expect((float) $full->amount_due)->toBeGreaterThan((float) $single->amount_due);
});

test('a package with no numeric price produces a booking with no amount', function () {
    $package = makePackage(['price' => 'Price on request']);

    $booking = bookThroughEndpoint($this, $package, 2);

    expect($booking->amount_due)->toBeNull();
    expect($booking->formatted_amount)->toBeNull();
});

test('the currency carries from the package onto the booking', function () {
    $peso = makePackage(['price_amount' => 15000, 'price_currency' => 'PHP']);
    $dollar = makePackage(['price_amount' => 500, 'price_currency' => 'USD', 'title' => 'US Package']);

    $pesoBooking = bookThroughEndpoint($this, $peso, 2);
    $dollarBooking = bookThroughEndpoint($this, $dollar, 2);

    expect($pesoBooking->currency)->toBe('PHP');
    expect($dollarBooking->currency)->toBe('USD');

    // Same party size, different currencies — the amounts must not be confused.
    expect((float) $pesoBooking->amount_due)->toBe(30000.0);
    expect((float) $dollarBooking->amount_due)->toBe(1000.0);
});

// ---------------------------------------------------------------------------
// Amounts are now reportable
// ---------------------------------------------------------------------------

test('booking amounts can be summed and sorted', function () {
    $package = makePackage(['price_amount' => 1000, 'price_currency' => 'USD']);

    makeBooking($package, ['number_of_passengers' => 1, 'amount_due' => 1000]);
    makeBooking($package, ['number_of_passengers' => 2, 'amount_due' => 2000]);
    makeBooking($package, ['number_of_passengers' => 3, 'amount_due' => 3000]);

    // This is the whole point of the change — none of it was possible while the
    // column held the string "₱14,999".
    expect((float) Booking::sum('amount_due'))->toBe(6000.0);
    expect((float) Booking::avg('amount_due'))->toBe(2000.0);
    expect((float) Booking::orderByDesc('amount_due')->value('amount_due'))->toBe(3000.0);
    expect(Booking::where('amount_due', '>', 1500)->count())->toBe(2);
});

test('package amounts can be summed and compared', function () {
    makePackage(['price_amount' => 2399, 'price_currency' => 'USD']);
    makePackage(['price_amount' => 1899, 'price_currency' => 'USD', 'title' => 'Second']);

    expect((float) TravelPackage::sum('price_amount'))->toBe(4298.0);
    expect((float) TravelPackage::orderBy('price_amount')->value('price_amount'))->toBe(1899.0);
});

test('the seeder produces numeric prices', function () {
    $this->seed(TravelPackageSeeder::class);

    $packages = TravelPackage::all();

    expect($packages)->not->toBeEmpty();
    expect($packages->whereNull('price_amount'))->toBeEmpty();

    foreach ($packages as $package) {
        expect($package->hasNumericPrice())->toBeTrue();
        // The display string is regenerated to match the amount.
        expect($package->price)->toBe(
            TravelPackage::renderPrice((float) $package->price_amount, $package->price_currency)
        );
    }
});
