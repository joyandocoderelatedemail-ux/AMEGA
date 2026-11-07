<?php

use App\Models\Service;
use App\Models\TravelPackage;
use Database\Seeders\CruisePackageSeeder;
use Database\Seeders\CruiseServiceSeeder;
use Database\Seeders\ServiceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('re-running the cruise seeder on a live site keeps the price, slots and dates an admin set', function () {
    $this->seed(CruisePackageSeeder::class);

    $disney = TravelPackage::where('title', 'like', '%Disney%')->firstOrFail();
    $disney->update(['price_amount' => 1499, 'description' => 'Edited by the admin.']);
    $disney->departures()->orderBy('departure_date')->first()->update(['slots' => 4]);
    $disney->departures()->reorder('departure_date', 'desc')->first()->delete();
    $before = $disney->departures()->count();

    $this->seed(CruisePackageSeeder::class);

    $disney = $disney->fresh();
    expect(TravelPackage::where('category', 'cruise')->count())->toBe(6)
        ->and((float) $disney->price_amount)->toBe(1499.0)
        ->and($disney->description)->toBe('Edited by the admin.')
        ->and($disney->departures()->count())->toBe($before)
        ->and($disney->departures()->orderBy('departure_date')->first()->slots)->toBe(4);
});

test('the cruise seeder only adds the packages that are missing', function () {
    $this->seed(CruisePackageSeeder::class);
    TravelPackage::where('title', 'like', '%MSC Bellissima%')->delete();

    $this->seed(CruisePackageSeeder::class);

    expect(TravelPackage::where('category', 'cruise')->count())->toBe(6)
        ->and(TravelPackage::where('title', 'like', '%MSC Bellissima%')->exists())->toBeTrue();
});

test('the cruise service seeder adds the card once and never overwrites an edited one', function () {
    $this->seed(CruiseServiceSeeder::class);

    $card = Service::where('title', 'Cruise Services')->firstOrFail();
    expect($card->order)->toBe(8)->and($card->icon)->toBe('ship')
        ->and(array_filter(array_map('trim', explode("\n", $card->full_description))))->toHaveCount(6);

    $card->update(['short_description' => 'Reworded by the admin.', 'is_active' => false]);

    $this->seed(CruiseServiceSeeder::class);

    expect(Service::where('title', 'Cruise Services')->count())->toBe(1)
        ->and($card->fresh()->short_description)->toBe('Reworded by the admin.')
        ->and($card->fresh()->is_active)->toBeFalse();
});

test('the cruise service seeder leaves every other service alone', function () {
    Service::create(['title' => 'Visa Assistance', 'short_description' => 'My own wording.', 'icon' => 'stamp', 'order' => 1]);

    $this->seed(CruiseServiceSeeder::class);

    expect(Service::count())->toBe(2)->and(Service::where('title', 'Visa Assistance')->value('short_description'))->toBe('My own wording.');
});

test('a fresh install still gets the cruise card from the full service seeder', function () {
    $this->seed(ServiceSeeder::class);

    expect(Service::where('title', 'Cruise Services')->count())->toBe(1)
        ->and(Service::where('title', 'Cruise Services')->value('full_description'))->toBe(CruiseServiceSeeder::row()['full_description'])
        ->and(Service::count())->toBe(8);
});
