<?php

use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view travel packages list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/packages');

    $response->assertStatus(200);
    $response->assertSee('Travel Packages Directory');
});

test('admin can create a new travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/packages', [
        'title' => 'Test Paradise Tour',
        'duration' => '5 Days / 4 Nights',
        'price' => '$1,500',
        'rating' => 5,
        'image' => 'images/test.jpg',
        'description' => 'A wonderful test package.',
        'inclusions' => 'Flights, Hotel, Breakfast',
        'exclusions' => 'Personal items',
        'itinerary' => 'Day 1: Arrival',
        'available_dates' => 'Oct 1 - Oct 5',
        'category' => 'short_haul',
        'status' => 'active',
        'is_featured' => '1',
    ]);

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseHas('travel_packages', [
        'title' => 'Test Paradise Tour',
        'category' => 'short_haul',
    ]);
});

test('admin can update a travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Old Title Tour',
        'duration' => '4 Days',
        'price' => '$999',
        'rating' => 4,
        'image' => 'images/old.jpg',
        'description' => 'Old description',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response = $this->actingAs($admin)->put("/admin/packages/{$package->id}", [
        'title' => 'Updated Title Tour',
        'duration' => '5 Days',
        'price' => '$1,299',
        'rating' => 5,
        'image' => 'images/new.jpg',
        'description' => 'Updated description',
        'category' => 'long_haul',
        'status' => 'active',
    ]);

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseHas('travel_packages', [
        'id' => $package->id,
        'title' => 'Updated Title Tour',
        'price' => '$1,299',
    ]);
});

test('admin can toggle package featured status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Toggle Feature Tour',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'images/toggle.jpg',
        'description' => 'Test toggle',
        'category' => 'short_haul',
        'status' => 'active',
        'is_featured' => false,
    ]);

    $response = $this->actingAs($admin)->post("/admin/packages/{$package->id}/toggle-featured");

    $response->assertStatus(302);
    expect($package->fresh()->is_featured)->toBeTrue();
});

test('admin can delete a travel package', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Delete Me Tour',
        'duration' => '2 Days',
        'price' => '$300',
        'rating' => 3,
        'image' => 'images/del.jpg',
        'description' => 'ToDelete',
        'category' => 'short_haul',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/packages/{$package->id}");

    $response->assertRedirect('/admin/packages');
    $this->assertDatabaseMissing('travel_packages', ['id' => $package->id]);
});
