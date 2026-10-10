<?php

use App\Models\Airline;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Models\User;

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

test('a ready-made package saves the flight terms it is sold with', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $airline = Airline::factory()->create(['name' => 'Cebu Pacific']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'airfare_inclusion' => 'included',
        'airline_id' => $airline->id,
        'origin_airport' => 'Manila (MNL)',
        'cabin_class' => 'economy',
        'baggage_allowance' => '20 kg checked + 7 kg hand-carry',
    ]))->assertRedirect()->assertSessionHasNoErrors();

    $package = TravelPackage::where('title', 'Boracay Beach Getaway')->firstOrFail();
    expect($package->airfare_inclusion)->toBe('included')
        ->and($package->airline->is($airline))->toBeTrue()
        ->and($package->origin_airport)->toBe('Manila (MNL)')
        ->and($package->cabin_class)->toBe('economy')
        ->and($package->baggage_allowance)->toBe('20 kg checked + 7 kg hand-carry');
});

test('flight terms are optional and validated', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload())
        ->assertSessionHasNoErrors();

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'title' => 'Bad Flight Terms',
        'airfare_inclusion' => 'free',
        'cabin_class' => 'cargo',
        'airline_id' => 999,
    ]))->assertSessionHasErrors(['airfare_inclusion', 'cabin_class', 'airline_id']);
});

test('both configurators offer the flight fields with the airlines list', function () {
    Airline::factory()->create(['name' => 'Philippine Airlines']);
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['ticketing.packages.configurator', 'admin.packages.configurator'] as $route) {
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
    $officer = User::factory()->create(['role' => 'ticketing']);
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

test('a multi-city package keeps its route legs and drops blank ones', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'multi_city',
        'multi_city_segments' => [
            ['from' => 'Manila (MNL)', 'to' => 'Tokyo (NRT)'],
            ['from' => ' Tokyo (NRT) ', 'to' => 'Osaka (KIX)'],
            ['from' => '', 'to' => ''],
            ['from' => 'Osaka (KIX)', 'to' => 'Manila (MNL)'],
        ],
    ]))->assertSessionHasNoErrors();

    $package = TravelPackage::where('title', 'Boracay Beach Getaway')->firstOrFail();
    expect($package->trip_type)->toBe('multi_city')
        ->and($package->multi_city_segments)->toBe([
            ['from' => 'Manila (MNL)', 'to' => 'Tokyo (NRT)'],
            ['from' => 'Tokyo (NRT)', 'to' => 'Osaka (KIX)'],
            ['from' => 'Osaka (KIX)', 'to' => 'Manila (MNL)'],
        ]);
});

test('a multi-city package needs at least two legs', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'multi_city',
        'multi_city_segments' => [['from' => 'Manila (MNL)', 'to' => 'Tokyo (NRT)'], ['from' => '', 'to' => '']],
    ]))->assertSessionHasErrors('multi_city_segments');

    expect(TravelPackage::count())->toBe(0);
});

test('route legs are only kept for multi-city packages', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'one_way',
        'multi_city_segments' => [['from' => 'A', 'to' => 'B'], ['from' => 'B', 'to' => 'C']],
    ]))->assertSessionHasNoErrors();

    $package = TravelPackage::firstOrFail();
    expect($package->trip_type)->toBe('one_way')
        ->and($package->multi_city_segments)->toBeNull();
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
    $officer = User::factory()->create(['role' => 'ticketing']);
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

test('a ready-made package saves its remarks and the airfare share of its total', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'price_amount' => 24500,
        'airfare_amount' => 8000,
        'remarks' => 'Peak season rate, min. 2 pax',
    ]))->assertSessionHasNoErrors();

    $package = TravelPackage::firstOrFail();
    expect((float) $package->airfare_amount)->toBe(8000.0)
        ->and((float) $package->price_amount)->toBe(24500.0)
        ->and($package->remarks)->toBe('Peak season rate, min. 2 pax')
        // An airfare share means the flight is included unless staff said otherwise.
        ->and($package->airfare_inclusion)->toBe('included');
});

test('the airfare cannot be more than the total price', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'price_amount' => 5000,
        'airfare_amount' => 8000,
    ]))->assertSessionHasErrors('airfare_amount');
});

test('the destination must suit the category', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $domestic = packageDestination('domestic');
    $international = packageDestination('international');

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'category' => 'domestic',
        'destination_id' => $international->id,
    ]))->assertSessionHasErrors('destination_id');

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'category' => 'short_haul',
        'destination_id' => $international->id,
    ]))->assertSessionHasNoErrors();

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'title' => 'Island hop',
        'category' => 'domestic',
        'destination_id' => $domestic->id,
    ]))->assertSessionHasNoErrors();

    expect(TravelPackage::count())->toBe(2);
});

test('the ready-made form puts category first and offers durations as a dropdown', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.packages.configurator'))
        ->assertOk()
        ->assertSeeInOrder(['for="rm-category"', 'for="rm-destination"', 'for="rm-remarks"', 'for="rm-duration"'], false)
        ->assertSee('<option value="4 Days / 3 Nights">', false)
        ->assertSee('name="airfare_amount"', false)
        ->assertSee('Total price per person');
});

test('a ready-made package saves the trip and flight details the wizard uses', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $departure = now()->addMonth()->toDateString();
    $return = now()->addMonth()->addDays(4)->toDateString();

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'round_trip',
        'preferred_flight_time' => 'morning',
        'origin_airport' => 'Manila (MNL)',
        'flight_destination' => 'Tokyo, Japan',
        'departure_date' => $departure,
        'return_date' => $return,
    ]))->assertSessionHasNoErrors();

    $package = TravelPackage::firstOrFail();
    expect($package->preferred_flight_time)->toBe('morning')
        ->and($package->flight_destination)->toBe('Tokyo, Japan')
        ->and($package->departure_date->toDateString())->toBe($departure)
        ->and($package->return_date->toDateString())->toBe($return)
        // The wizard reads plain calendar days, not UTC timestamps.
        ->and($package->toArray()['departure_date'])->toBe($departure);
});

test('package trip dates follow the wizard rules', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'departure_date' => now()->subDay()->toDateString(),
    ]))->assertSessionHasErrors(['departure_date' => 'Departure date cannot be in the past.']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'round_trip',
        'departure_date' => now()->addDays(10)->toDateString(),
        'return_date' => now()->addDays(5)->toDateString(),
    ]))->assertSessionHasErrors('return_date');

    // A one-way package does not keep a return date.
    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), readyMadePayload([
        'trip_type' => 'one_way',
        'departure_date' => now()->addDays(10)->toDateString(),
        'return_date' => now()->addDays(15)->toDateString(),
    ]))->assertSessionHasNoErrors();
    expect(TravelPackage::firstOrFail()->return_date)->toBeNull();
});

test('editing a package whose departure has passed does not force a new date', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $package = TravelPackage::create(readyMadePayload([
        'description' => 'Beach trip', 'rating' => 5, 'image' => 'images/packages/default-package.jpg',
        'departure_date' => now()->subWeek()->toDateString(),
    ]));

    $this->actingAs($officer)->put(route('ticketing.packages.update', $package), readyMadePayload([
        'title' => 'Renamed',
        'departure_date' => now()->subWeek()->toDateString(),
    ]))->assertSessionHasNoErrors();

    expect($package->fresh()->title)->toBe('Renamed');
});

test('the configurator shows the trip and flight fields and the wizard fills them in', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.packages.configurator'))
        ->assertOk()
        ->assertSeeInOrder(['name="trip_type"', 'name="preferred_flight_time"', 'for="rm-origin"', 'name="flight_destination"', 'name="departure_date"', 'name="return_date"'], false);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->getContent();
    expect($html)->toContain('this.formData.preferred_flight_time = pkg.preferred_flight_time;')
        ->toContain('this.formData.destination = pkg.flight_destination;')
        ->toContain('this.formData.departure_date = packageDate(pkg.departure_date);');
});

test('a picked ready-made package locks the Trip & Flight fields it sets', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

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
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toContain('<div x-show="!awaitingPackage" class="space-y-6 pt-4 border-t border-gray-100">')
        ->toContain('get awaitingPackage()')
        ->toContain("return { travel_package_id: 'Pick a ready-made package.' };")
        ->toContain('data-error-key="travel_package_id"')
        // The step panels themselves must never get x-transition (see TicketBookingModuleTest).
        ->not->toMatch('/x-show="!awaitingPackage"[^>]*x-transition/');
});

test('the wizard shows package prices as their label, never as NaN', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    // `price` is a display label like "$2,399"; turning it into a number gives NaN.
    expect($html)->not->toContain('Number(pkg.price)')
        ->not->toContain('formatNumber(selectedPackage?.price)')
        ->toContain('x-text="selectedPackage?.price || \'\'"');
});

test('the review step offers a details dropdown for the picked package', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

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
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.tickets.create'))->assertOk()->getContent();

    expect($html)->toMatch('/Package Option Selector.*International Destination Fields.*Trip & Flight \(formerly its own step\)/s')
        ->toContain("x-show=\"formData.travel_type === 'international' && !awaitingPackage\"")
        ->toContain('this.formData.destination_country = pkg.destination.location;')
        ->toContain('this.formData.preferred_airline = pkg.airline.name;')
        ->toContain(':readonly="packageLock.destination_country"')
        ->toContain('case 3: return this.awaitingPackage ? this.tripErrors() :');
});
