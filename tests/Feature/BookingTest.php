<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can submit package booking request', function () {
    $package = TravelPackage::create([
        'title' => 'Japan Spring Tour',
        'duration' => '6 Days',
        'price' => '$2,000',
        'rating' => 5,
        'image' => 'images/japan.jpg',
        'description' => 'Test package description',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $response = $this->post('/bookings', [
        'travel_package_id' => $package->id,
        'customer_name' => 'Juan Dela Cruz',
        'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(10)->format('Y-m-d'),
        'number_of_passengers' => 2,
        'special_requests' => 'Window seats preferred',
    ]);

    $booking = Booking::first();

    $response->assertRedirect('/bookings/' . $booking->booking_reference);
    $this->assertDatabaseHas('bookings', [
        'customer_name' => 'Juan Dela Cruz',
        'travel_package_id' => $package->id,
        'number_of_passengers' => 2,
    ]);
});

test('authenticated client can submit booking request', function () {
    $client = User::factory()->create(['role' => 'client']);
    $package = TravelPackage::create([
        'title' => 'Spain Express',
        'duration' => '8 Days',
        'price' => '$3,000',
        'rating' => 5,
        'image' => 'images/spain.jpg',
        'description' => 'Test package',
        'category' => 'long_haul',
        'status' => 'active',
    ]);

    $response = $this->actingAs($client)->post('/bookings', [
        'travel_package_id' => $package->id,
        'customer_name' => $client->name,
        'customer_email' => $client->email,
        'customer_phone' => '09189876543',
        'travel_date' => now()->addDays(15)->format('Y-m-d'),
        'number_of_passengers' => 1,
    ]);

    $booking = Booking::first();
    expect($booking->user_id)->toBe($client->id);
});

test('admin can view bookings list and update status', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Dubai Luxury',
        'duration' => '5 Days',
        'price' => '$1,500',
        'rating' => 5,
        'image' => 'images/dubai.jpg',
        'description' => 'Desert safari',
        'category' => 'short_haul',
        'status' => 'active',
    ]);

    $booking = Booking::create([
        'booking_reference' => 'AMG-2026-TEST1',
        'travel_package_id' => $package->id,
        'customer_name' => 'Maria Clara',
        'customer_email' => 'maria@example.com',
        'customer_phone' => '09191112222',
        'travel_date' => now()->addDays(5)->format('Y-m-d'),
        'number_of_passengers' => 2,
        'total_amount' => '$1,500',
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    $indexResponse = $this->actingAs($admin)->get('/admin/bookings');
    $indexResponse->assertStatus(200);
    $indexResponse->assertSee('AMG-2026-TEST1');

    $statusResponse = $this->actingAs($admin)->post("/admin/bookings/{$booking->id}/status", [
        'status' => 'confirmed',
        'payment_status' => 'deposit_paid',
    ]);

    $statusResponse->assertStatus(302);
    expect($booking->fresh()->status)->toBe('confirmed');
    expect($booking->fresh()->payment_status)->toBe('deposit_paid');
});
