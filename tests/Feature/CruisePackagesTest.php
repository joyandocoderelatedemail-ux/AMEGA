<?php

use App\Models\TravelPackage;
use Database\Seeders\CruisePackageSeeder;

beforeEach(fn () => $this->seed(CruisePackageSeeder::class));

test('cruises have their own page and stay out of Tour Packages', function () {
    $this->get(route('cruises'))->assertOk()
        ->assertSee('Cruise Packages')->assertSee('Disney Adventure')->assertSee('MSC Bellissima');

    $this->get(route('packages.index'))->assertOk()->assertDontSee('Disney Adventure');
    $this->get(route('tours'))->assertOk()->assertDontSee('Disney Adventure');
});

test('the home page and menu link to the cruise packages', function () {
    $this->get(route('home'))->assertOk()
        ->assertSee('id="cruises"', false)->assertSee(route('cruises'), false);
});

test('a cruise page shows its dates and links back to Cruise Packages', function () {
    $cruise = TravelPackage::where('title', 'like', '%Disney%')->firstOrFail();

    expect($cruise->category)->toBe('cruise')->and($cruise->departures)->toHaveCount(6);

    $this->get(route('packages.show', $cruise))->assertOk()
        ->assertSee('Available Travel Dates')->assertSee(route('cruises'), false);
});

test('seeding twice does not duplicate the cruises', function () {
    $this->seed(CruisePackageSeeder::class);

    expect(TravelPackage::where('category', 'cruise')->count())->toBe(6);
});
