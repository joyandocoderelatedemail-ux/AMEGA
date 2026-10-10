<?php

use App\Models\Destination;
use App\Models\TravelPackage;
use App\Models\User;

test('the ticketing portal has a package configurator tab next to airlines', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Airlines', 'Package Configurator'])
        ->assertSee(route('ticketing.packages.index'), false);
});

test('the package configurator opens on the package list, with add and edit', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $package = TravelPackage::create([
        'title' => 'Coron Island Hopping',
        'category' => 'domestic',
        'duration' => '3 Days / 2 Nights',
        'price_amount' => 12000,
        'description' => 'Island tour',
        'rating' => 5,
        'image' => 'images/packages/default-package.jpg',
        'status' => 'draft',
    ]);

    $this->actingAs($officer)->get(route('ticketing.packages.index'))
        ->assertOk()
        ->assertSee('Coron Island Hopping')
        ->assertSee('Draft')
        ->assertSee(route('ticketing.packages.configurator'), false)
        ->assertSee(route('ticketing.packages.edit', $package), false)
        ->assertSee('fa-regular fa-pen-to-square', false);

    $this->actingAs($officer)->get(route('ticketing.packages.index', ['search' => 'Boracay']))
        ->assertOk()
        ->assertDontSee('Coron Island Hopping')
        ->assertSee('No packages match these filters.');
});

test('the package list filters as you type, without a search button', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $html = $this->actingAs($officer)->get(route('ticketing.packages.index'))->assertOk()->getContent();

    expect($html)->toContain('@input.debounce.300ms="apply()"')
        ->toContain('id="package-results"')
        ->not->toContain('>Filter</button>');
});

test('a ticketing officer can edit a package, keeping its photo', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $package = TravelPackage::create([
        'title' => 'Coron Island Hopping',
        'category' => 'domestic',
        'duration' => '3 Days / 2 Nights',
        'price_amount' => 12000,
        'description' => 'Island tour',
        'rating' => 5,
        'image' => 'images/packages/coron.jpg',
        'status' => 'active',
        'remarks' => 'Min. 2 pax',
    ]);

    $this->actingAs($officer)->get(route('ticketing.packages.edit', $package))
        ->assertOk()
        ->assertSee('Edit Coron Island Hopping')
        ->assertSee(route('ticketing.packages.update', $package), false)
        ->assertSee('Min. 2 pax')
        ->assertSee('Save changes');

    $this->actingAs($officer)->put(route('ticketing.packages.update', $package), [
        'title' => 'Coron Island Hopping Deluxe',
        'category' => 'domestic',
        'duration' => '4 Days / 3 Nights',
        'price_amount' => 15000,
        'airfare_amount' => 5000,
        'number_of_pax' => 2,
        'smoking_preference' => 'non_smoking',
        'status' => 'active',
    ])->assertRedirect(route('ticketing.packages.index'))->assertSessionHas('success');

    expect($package->fresh())
        ->title->toBe('Coron Island Hopping Deluxe')
        ->duration->toBe('4 Days / 3 Nights')
        ->image->toBe('images/packages/coron.jpg')
        ->description->toBe('Island tour')
        ->airfare_inclusion->toBe('included');
});

test('the ticketing configurator is for ready-made packages only', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.packages.configurator'))
        ->assertOk()
        ->assertSee('Ready-Made Package Configurator')
        ->assertSee(route('ticketing.packages.configurator.ready-made'), false)
        ->assertDontSee('Customize Package')
        ->assertDontSee('Quotations')
        ->assertDontSee('name="client_email"', false)
        ->assertSee('portal-minimal', false);

    expect(Route::has('ticketing.packages.configurator.custom'))->toBeFalse()
        ->and(Route::has('ticketing.packages.custom-inquiries.show'))->toBeFalse();
});

test('a ticketing officer can save a ready-made package to the catalog', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->post(route('ticketing.packages.configurator.ready-made'), [
        'title' => 'Boracay Beach Getaway',
        'category' => 'domestic',
        'duration' => '4 Days / 3 Nights',
        'price_amount' => 24500,
        'price_currency' => 'PHP',
        'number_of_pax' => 2,
        'has_breakfast' => '1',
        'bed_config' => 'Queen Bed',
        'smoking_preference' => 'non_smoking',
        'status' => 'active',
    ])->assertRedirect(route('ticketing.packages.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('travel_packages', [
        'title' => 'Boracay Beach Getaway',
        'package_type' => 'ready_made',
        'has_breakfast' => 1,
        'status' => 'active',
    ]);
});

test('staff from another desk cannot reach the ticketing configurator', function () {
    $visaOfficer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($visaOfficer)->get(route('ticketing.packages.index'))->assertRedirect();
    $this->actingAs($visaOfficer)->get(route('ticketing.packages.configurator'))->assertRedirect();
    $this->actingAs($visaOfficer)->post(route('ticketing.packages.configurator.ready-made'), ['title' => 'Sneaky'])->assertRedirect();
    expect(TravelPackage::count())->toBe(0);
});

test('the package list search matches destinations as well as titles', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $destination = Destination::create([
        'name' => 'Palawan',
        'location' => 'Philippines',
        'description' => 'Islands',
        'image' => 'images/destinations/palawan.jpg',
        'starting_price' => '₱5,000',
        'type' => 'domestic',
    ]);
    $base = ['category' => 'domestic', 'duration' => '3 Days / 2 Nights', 'price_amount' => 12000, 'description' => 'Trip', 'rating' => 5, 'image' => 'images/packages/default-package.jpg', 'status' => 'active'];
    TravelPackage::create($base + ['title' => 'Coron Island Hopping', 'destination_id' => $destination->id]);
    TravelPackage::create($base + ['title' => 'Boracay Getaway']);

    $this->actingAs($officer)->get(route('ticketing.packages.index', ['search' => 'palawan']))
        ->assertSee('Coron Island Hopping')->assertDontSee('Boracay Getaway');

    // A typed % is a literal character, not a wildcard.
    $this->actingAs($officer)->get(route('ticketing.packages.index', ['search' => '%']))
        ->assertSee('No packages match these filters.');

    // Only the table filters; there is no suggestions dropdown on the search box.
    $this->actingAs($officer)->get(route('ticketing.packages.index'))
        ->assertDontSee('role="combobox"', false);
});
