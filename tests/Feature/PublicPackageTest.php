<?php

use App\Models\TravelPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public packages directory page can be rendered', function () {
    $response = $this->get('/packages');

    $response->assertStatus(200);
    $response->assertSee('Explore the World with AMEGA');
});

test('public package details page can be rendered', function () {
    $package = TravelPackage::create([
        'title' => 'Sample Hokkaido Winter Tour',
        'duration' => '6 Days / 5 Nights',
        'price' => '$2,399',
        'rating' => 5,
        'image' => 'images/hokkaido.jpg',
        'description' => 'Experience Sapporo snow festival and hot springs.',
        'inclusions' => 'Flights, Hotel, Breakfast',
        'exclusions' => 'Personal expenses',
        'itinerary' => "Day 1: Arrival\nDay 2: Festival",
        'category' => 'short_haul',
        'status' => 'active',
        'is_featured' => true,
    ]);

    $response = $this->get("/packages/{$package->id}");

    $response->assertStatus(200);
    $response->assertSee('Sample Hokkaido Winter Tour');
    $response->assertSee('Package Overview');
    $response->assertSee('Day-by-Day Travel Itinerary');
});

test('search filters packages directory results by keyword', function () {
    TravelPackage::create([
        'title' => 'Unique Tokyo Blossom Tour',
        'duration' => '5 Days',
        'price' => '$1,999',
        'rating' => 5,
        'image' => 'images/tokyo.jpg',
        'description' => 'Cherry blossom season.',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response = $this->get('/packages?search=Tokyo');

    $response->assertStatus(200);
    $response->assertSee('Unique Tokyo Blossom Tour');
});

test('search intelligently handles local and international queries', function () {
    TravelPackage::create([
        'title' => 'Boracay Island Beach Tour',
        'duration' => '4 Days',
        'price' => '₱14,999',
        'rating' => 5,
        'image' => 'images/boracay.jpg',
        'description' => 'White beach',
        'category' => 'domestic',
        'status' => 'active',
    ]);

    TravelPackage::create([
        'title' => 'Spain & Portugal Adventure',
        'duration' => '12 Days',
        'price' => '$4,299',
        'rating' => 5,
        'image' => 'images/spain.jpg',
        'description' => 'Grand Europe tour',
        'category' => 'long_haul',
        'status' => 'active',
    ]);

    $localResponse = $this->get('/packages?search=local');
    $localResponse->assertStatus(200);
    $localResponse->assertSee('Boracay Island Beach Tour');
    $localResponse->assertDontSee('Spain & Portugal Adventure');

    $intlResponse = $this->get('/packages?search=international');
    $intlResponse->assertStatus(200);
    $intlResponse->assertSee('Spain & Portugal Adventure');
    $intlResponse->assertDontSee('Boracay Island Beach Tour');
});
