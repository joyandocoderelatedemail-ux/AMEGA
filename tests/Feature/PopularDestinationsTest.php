<?php

use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the home page section is called Popular Destinations, not Domestic Island Packages', function () {
    Destination::create(['name' => 'Boracay', 'location' => 'Aklan', 'description' => 'White sand.', 'image' => 'images/og-image.jpg', 'starting_price' => '₱8,000', 'type' => 'domestic', 'is_featured' => true]);
    Destination::create(['name' => 'Tokyo', 'location' => 'Japan', 'description' => 'City.', 'image' => 'images/og-image.jpg', 'starting_price' => '₱40,000', 'type' => 'international', 'is_featured' => true]);

    $this->get(route('home'))->assertOk()
        ->assertSee('Popular Destinations')
        ->assertDontSee('Domestic Island Packages')
        // The two lists keep their own headings, fed by the Destinations marked Featured.
        ->assertSee('Local Destinations')->assertSee('International Destinations')
        ->assertSee('Boracay')->assertSee('Tokyo');
});

test('the Tour Packages page keeps its own name', function () {
    $this->get(route('tours'))->assertOk()->assertSee('Tour Packages')->assertDontSee('Domestic Island Packages');
});

test('the backend Destinations page says where they show on the website', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.destinations.index'))->assertOk()
        ->assertSee('Popular Destinations')->assertSee('only the ones marked Featured')->assertSee('add a Travel Package');
});
