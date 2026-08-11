<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest cannot access client dashboard', function () {
    $response = $this->get('/client/dashboard');

    $response->assertRedirect('/login');
});

test('authenticated client can view dashboard and bookings', function () {
    $client = User::factory()->create(['role' => 'client']);
    $package = TravelPackage::create([
        'title' => 'Japan Hokkaido Snow Festival',
        'duration' => '6 Days',
        'price' => '$2,399',
        'rating' => 5,
        'image' => 'images/hokkaido.jpg',
        'description' => 'Test package',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    Booking::create([
        'booking_reference' => 'AMG-2026-CLIENT1',
        'user_id' => $client->id,
        'travel_package_id' => $package->id,
        'customer_name' => $client->name,
        'customer_email' => $client->email,
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(20)->format('Y-m-d'),
        'number_of_passengers' => 2,
        'total_amount' => '$2,399',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($client)->get('/client/dashboard');

    $response->assertStatus(200);
    $response->assertSee('My Package Reservations');
    $response->assertSee('AMG-2026-CLIENT1');
    $response->assertSee('Japan Hokkaido Snow Festival');
});

test('client can update profile information', function () {
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->put('/client/profile', [
        'name' => 'Updated Client Name',
        'phone' => '09998887766',
        'address' => 'Quezon City, Metro Manila',
    ]);

    $response->assertStatus(302);
    $this->assertDatabaseHas('users', [
        'id' => $client->id,
        'name' => 'Updated Client Name',
        'phone' => '09998887766',
        'address' => 'Quezon City, Metro Manila',
    ]);
});

test('authenticated client can view read-only profile details', function () {
    $client = User::factory()->create([
        'role' => 'client',
        'first_name' => 'Readonly',
        'last_name' => 'Tester',
        'account_category' => 'Corporate',
    ]);

    $response = $this->actingAs($client)->get('/client/profile');

    $response->assertStatus(200);
    $response->assertSee('Profile Details');
    $response->assertSee('Readonly');
    $response->assertSee('Tester');
    $response->assertSee('Corporate Category');
});
