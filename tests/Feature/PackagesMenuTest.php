<?php

use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function menuPackage(string $title, string $category): TravelPackage
{
    return TravelPackage::create([
        'title' => $title, 'category' => $category, 'duration' => '5 Days / 4 Nights', 'price_amount' => 20000, 'rating' => 5,
        'image' => 'images/og-image.jpg', 'description' => 'A trip.', 'status' => 'active', 'package_type' => 'ready_made',
    ]);
}

test('the Tours & Packages menu lists destinations, domestic, international, cruise and all packages', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('Popular Destinations')->toContain('Places to explore')
        ->toContain('Domestic Packages')->toContain('Philippine island tours')
        ->toContain('International Packages')->toContain('Global tours')
        ->toContain('Cruise Packages')->toContain('Fly &amp; Cruise 2026-2027')
        ->toContain('All Tour Packages')->toContain('Browse Full Directory')
        // The old, mismatched wording is gone from both the desktop and the mobile menu.
        ->not->toContain('Philippine Islands & Packages')->not->toContain('Global World Tours')
        ->not->toContain('Domestic Tours')->not->toContain('International Tours');

    // In the menu's order.
    $positions = array_map(fn (string $label) => strpos($html, $label), ['Places to explore', 'Philippine island tours', 'Global tours', 'Fly &amp; Cruise 2026-2027', 'Browse Full Directory']);
    expect($positions)->toBe(collect($positions)->sort()->values()->all())->and(in_array(false, $positions, true))->toBeFalse();
});

test('each menu item goes to the right place', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('href="'.route('packages.index', ['search' => 'domestic']).'"')
        ->toContain('href="'.route('packages.index', ['search' => 'international']).'"')
        ->toContain('href="'.route('cruises').'"')
        ->toContain('href="'.route('packages.index').'"')
        // Popular Destinations jumps to the home page section of that name.
        ->toContain('href="#destinations"');
});

test('on any other page Popular Destinations opens the page that has the destinations section', function () {
    $this->get(route('cruises'))->assertOk()->assertSee(url('/tours#destinations'), false);
});

test('the Domestic Packages link lists domestic packages and not international or cruise ones', function () {
    menuPackage('Boracay Beach Escape', 'domestic');
    menuPackage('Japan Cherry Blossom', 'short_haul');
    menuPackage('Paris and Rome', 'long_haul');
    menuPackage('Disney Adventure Cruise', 'cruise');

    $this->get(route('packages.index', ['search' => 'domestic']))->assertOk()
        ->assertSee('Boracay Beach Escape')
        ->assertDontSee('Japan Cherry Blossom')->assertDontSee('Paris and Rome')->assertDontSee('Disney Adventure Cruise');
});

test('the International Packages link lists short and long haul packages, not domestic or cruise ones', function () {
    menuPackage('Boracay Beach Escape', 'domestic');
    menuPackage('Japan Cherry Blossom', 'short_haul');
    menuPackage('Paris and Rome', 'long_haul');
    menuPackage('Disney Adventure Cruise', 'cruise');

    $this->get(route('packages.index', ['search' => 'international']))->assertOk()
        ->assertSee('Japan Cherry Blossom')->assertSee('Paris and Rome')
        ->assertDontSee('Boracay Beach Escape')->assertDontSee('Disney Adventure Cruise');
});
