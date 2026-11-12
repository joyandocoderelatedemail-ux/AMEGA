<?php

use App\Models\Airline;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Models\User;
use App\Support\PackageImageStorage;

function readyMadePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Boracay Beach Getaway',
        'category' => 'domestic',
        'duration' => '4 Days / 3 Nights',
        'price_amount' => 24500,
        'price_currency' => 'PHP',
        'number_of_pax' => 2,
        'smoking_preference' => 'non_smoking',
        'status' => 'active',
    ], $overrides);
}

test('the admin package editor offers the flight fields with the airlines list', function () {
    Airline::factory()->create(['name' => 'Philippine Airlines']);
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['admin.packages.create'] as $route) {
        $this->actingAs($admin)->get(route($route))
            ->assertOk()
            ->assertSee('name="airfare_inclusion"', false)
            ->assertSee('name="baggage_allowance"', false)
            ->assertSee('Philippine Airlines');
    }
});

test('admins can set the flight terms when editing a package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $airline = Airline::factory()->create();
    $package = TravelPackage::create(readyMadePayload(['description' => 'Beach trip', 'rating' => 5, 'image' => 'images/packages/default-package.jpg']));

    $this->actingAs($admin)->get(route('admin.packages.edit', $package))
        ->assertOk()->assertSee('name="cabin_class"', false);

    $this->actingAs($admin)->put(route('admin.packages.update', $package), readyMadePayload([
        'description' => 'Beach trip',
        'rating' => 5,
        'image' => 'images/packages/default-package.jpg',
        'airfare_inclusion' => 'not_included',
        'airline_id' => $airline->id,
        'cabin_class' => 'business',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    expect($package->fresh())
        ->airfare_inclusion->toBe('not_included')
        ->airline_id->toBe($airline->id)
        ->cabin_class->toBe('business');
});

test('the ticket wizard receives each package with its flight terms and fills them in', function () {
    $officer = priceManager();
    $airline = Airline::factory()->create(['name' => 'AirAsia Philippines']);
    TravelPackage::create(readyMadePayload([
        'description' => 'Beach trip',
        'rating' => 5,
        'image' => 'images/packages/default-package.jpg',
        'airfare_inclusion' => 'included',
        'airline_id' => $airline->id,
        'origin_airport' => 'Cebu (CEB)',
        'baggage_allowance' => '15 kg checked',
    ]));

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('Cebu (CEB)')
        ->toContain('15 kg checked')
        ->toContain('AirAsia Philippines')
        ->toContain('this.applyPackageFlight(pkg);')
        ->toContain('Airfare is included in the package price');
});

test('admins can change a package to multi-city when editing it', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $base = ['description' => 'Beach trip', 'rating' => 5, 'image' => 'images/packages/default-package.jpg'];
    $package = TravelPackage::create(readyMadePayload($base + ['trip_type' => 'round_trip']));

    $this->actingAs($admin)->get(route('admin.packages.edit', $package))
        ->assertOk()->assertSee('name="trip_type"', false);

    $this->actingAs($admin)->put(route('admin.packages.update', $package), readyMadePayload($base + [
        'trip_type' => 'multi_city',
        'multi_city_segments' => [['from' => 'Manila (MNL)', 'to' => 'Seoul (ICN)'], ['from' => 'Seoul (ICN)', 'to' => 'Manila (MNL)']],
    ]))->assertSessionHasNoErrors();

    expect($package->fresh()->multi_city_segments)->toHaveCount(2);
});

test('the wizard fills in the package trip type and its route legs', function () {
    $officer = priceManager();
    TravelPackage::create(readyMadePayload([
        'description' => 'Japan loop',
        'rating' => 5,
        'image' => 'images/packages/default-package.jpg',
        'trip_type' => 'multi_city',
        'multi_city_segments' => [['from' => 'Manila (MNL)', 'to' => 'Tokyo (NRT)'], ['from' => 'Osaka (KIX)', 'to' => 'Manila (MNL)']],
    ]));

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('Osaka (KIX)')
        ->toContain('this.formData.trip_type = pkg.trip_type;')
        ->toContain('this.formData.multi_city_segments = pkg.multi_city_segments.map(');
});

function packageDestination(string $type): Destination
{
    return Destination::create([
        'name' => $type === 'domestic' ? 'Boracay Island' : 'Tokyo',
        'location' => $type === 'domestic' ? 'Aklan' : 'Japan',
        'description' => 'Test destination',
        'image' => 'images/destinations/test.jpg',
        'starting_price' => '₱5,000',
        'type' => $type,
    ]);
}

test('the wizard fills in the trip and flight fields from a package', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->getContent();
    expect($html)->toContain('this.formData.preferred_flight_time = pkg.preferred_flight_time;')
        ->toContain('this.formData.destination = pkg.flight_destination;')
        ->toContain('this.formData.departure_date = packageDate(pkg.departure_date);');
});

test('a picked ready-made package locks the Trip & Flight fields it sets', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    // Read-only, not disabled, so the locked values are still submitted with the form.
    expect($html)->toContain('get packageLock()')
        ->toContain(':readonly="packageLock.origin"')
        ->toContain(':readonly="packageLock.destination"')
        ->toContain(':readonly="packageLock.departure_date"')
        ->toContain(':readonly="packageLock.return_date"')
        ->toContain(":class=\"packageLock.trip_type ? 'pointer-events-none opacity-70' : ''\"")
        ->toContain(":class=\"packageLock.preferred_flight_time ? 'pointer-events-none opacity-70' : ''\"")
        ->not->toMatch('/name="(origin|destination|departure_date|return_date)"[^>]*:disabled/');
});

test('Trip & Flight waits for a package when Ready-Made Tour Package is chosen', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('<div x-show="!awaitingPackage" class="space-y-6 pt-4 border-t border-gray-100">')
        ->toContain('get awaitingPackage()')
        ->toContain("return { travel_package_id: 'Pick a ready-made package.' };")
        ->toContain('data-error-key="travel_package_id"')
        // The step panels themselves must never get x-transition (see TicketBookingModuleTest).
        ->not->toMatch('/x-show="!awaitingPackage"[^>]*x-transition/');
});

test('the wizard shows package prices as their label, never as NaN', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    // `price` is a display label like "$2,399"; turning it into a number gives NaN.
    expect($html)->not->toContain('Number(pkg.price)')
        ->not->toContain('formatNumber(selectedPackage?.price)')
        ->toContain('x-text="selectedPackage?.price || \'\'"');
});

test('the review step offers a details dropdown for the picked package', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('aria-controls="review-package-details"')
        ->toContain('id="review-package-details" x-show="open"')
        ->toContain('packageDetails(pkg) {')
        ->toContain('packageNotes(pkg) {');

    // Hotel dates reach the page as plain calendar days, like the flight dates.
    $package = TravelPackage::create(readyMadePayload([
        'description' => 'Trip', 'rating' => 5, 'image' => 'images/packages/default-package.jpg',
        'check_in_date' => '2026-12-01', 'check_out_date' => '2026-12-04',
    ]));
    expect($package->toArray())->check_in_date->toBe('2026-12-01')->check_out_date->toBe('2026-12-04');
});

test('step 3 starts with the package option and fills the international destination from the package', function () {
    $officer = priceManager();

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toMatch('/Package Option Selector.*International Destination Fields.*Trip & Flight \(formerly its own step\)/s')
        ->toContain("x-show=\"formData.travel_type === 'international' && !awaitingPackage\"")
        ->toContain('this.formData.destination_country = pkg.destination.location;')
        ->toContain('this.formData.preferred_airline = pkg.airline.name;')
        ->toContain(':readonly="packageLock.destination_country"')
        ->toContain('case 3: return this.awaitingPackage ? this.tripErrors() :');
});

test('the repair migration points old broken default photos at the placeholder', function () {
    $make = fn (string $title, string $image) => TravelPackage::create([
        'title' => $title, 'category' => 'domestic', 'duration' => '3 Days / 2 Nights', 'price_amount' => 1000,
        'description' => 'x', 'rating' => 5, 'status' => 'active', 'image' => $image,
    ]);
    $make('Old Default', 'images/packages/default-package.jpg');
    $make('Own Photo', 'uploads/packages/mine.jpg');

    (require database_path('migrations/2026_11_02_100000_repair_missing_default_package_photo.php'))->up();

    expect(TravelPackage::where('title', 'Old Default')->value('image'))->toBe(PackageImageStorage::PLACEHOLDER)
        ->and(TravelPackage::where('title', 'Own Photo')->value('image'))->toBe('uploads/packages/mine.jpg');
});
