<?php

use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function filterPackage(string $title, string $category, array $overrides = []): TravelPackage
{
    return TravelPackage::create(array_merge([
        'title' => $title,
        'category' => $category,
        'duration' => '5 Days / 4 Nights',
        'price_amount' => 20000,
        'rating' => 5,
        'image' => 'images/og-image.jpg',
        'description' => 'A trip.',
        'status' => 'active',
        'package_type' => 'ready_made',
    ], $overrides));
}

function filterAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function filterCatalog(): void
{
    filterPackage('Boracay Beach Escape', 'domestic');
    filterPackage('Siargao Surf Camp', 'domestic', ['description' => 'Island in Japan style']);
    filterPackage('Japan Cherry Blossom', 'short_haul');
    filterPackage('Paris and Rome', 'long_haul');
    filterPackage('Disney Adventure Cruise', 'cruise');
}

test('the packages page has All, Domestic, International and Cruises buttons with their counts', function () {
    filterCatalog();

    $this->actingAs(filterAdmin())->get(route('admin.packages.index'))->assertOk()
        ->assertSeeInOrder(['Package type', 'All', '5', 'Domestic', '2', 'International', '2', 'Cruises', '1'], false);
});

test('Domestic shows only domestic packages', function () {
    filterCatalog();

    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'domestic']))->assertOk()
        ->assertSee('Boracay Beach Escape')->assertSee('Siargao Surf Camp')
        ->assertDontSee('Japan Cherry Blossom')->assertDontSee('Paris and Rome')->assertDontSee('Disney Adventure Cruise');
});

test('International shows short haul and long haul together, and nothing else', function () {
    filterCatalog();

    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'international']))->assertOk()
        ->assertSee('Japan Cherry Blossom')->assertSee('Paris and Rome')
        ->assertDontSee('Boracay Beach Escape')->assertDontSee('Disney Adventure Cruise');
});

test('Cruises shows only cruise packages', function () {
    filterCatalog();

    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'cruise']))->assertOk()
        ->assertSee('Disney Adventure Cruise')
        ->assertDontSee('Boracay Beach Escape')->assertDontSee('Japan Cherry Blossom')->assertDontSee('Paris and Rome');
});

test('All, or an unknown group, shows every package', function () {
    filterCatalog();

    foreach ([[], ['group' => 'nonsense']] as $query) {
        $this->actingAs(filterAdmin())->get(route('admin.packages.index', $query))->assertOk()
            ->assertSee('Boracay Beach Escape')->assertSee('Japan Cherry Blossom')->assertSee('Paris and Rome')->assertSee('Disney Adventure Cruise');
    }
});

test('searching inside a button stays inside it', function () {
    filterCatalog();

    // "Japan" is in a short-haul title and in a domestic package's description. Within Domestic only the second is wanted,
    // and the search must not pull in the international one (the old search let its OR leak past the other filters).
    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'domestic', 'search' => 'Japan']))->assertOk()
        ->assertSee('Siargao Surf Camp')->assertDontSee('Japan Cherry Blossom')->assertDontSee('Boracay Beach Escape');

    // The search box carries the chosen button along.
    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'cruise']))->assertOk()
        ->assertSee('name="group" value="cruise"', false);
});

test('the status filter and a category filter still narrow the list inside a button', function () {
    filterPackage('Boracay Beach Escape', 'domestic');
    filterPackage('Cebu Draft Trip', 'domestic', ['status' => 'draft']);
    filterPackage('Dubai Desert', 'long_haul');
    filterPackage('Japan Cherry Blossom', 'short_haul');

    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'domestic', 'status' => 'draft']))->assertOk()
        ->assertSee('Cebu Draft Trip')->assertDontSee('Boracay Beach Escape');

    $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['group' => 'international', 'category' => 'long_haul']))->assertOk()
        ->assertSee('Dubai Desert')->assertDontSee('Japan Cherry Blossom');
});

test('the buttons keep the search and status when you switch between them', function () {
    filterCatalog();

    $html = $this->actingAs(filterAdmin())->get(route('admin.packages.index', ['search' => 'trip', 'status' => 'active']))->assertOk()->getContent();

    expect($html)->toContain('group=cruise')->toContain('search=trip')->toContain('status=active');
});
