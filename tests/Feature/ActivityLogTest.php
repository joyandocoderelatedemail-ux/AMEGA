<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff login creates real-time activity log entry', function () {
    $admin = User::factory()->create([
        'email' => 'admin.logtest@amegatravel.com',
        'role' => 'admin',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => 'admin.logtest@amegatravel.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'module' => 'Auth',
        'action' => 'LOGIN',
    ]);
});

test('booking status update logs real-time audit event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Sample Package',
        'duration' => '3 Days',
        'price' => '$500',
        'rating' => 5,
        'image' => 'sample.jpg',
        'description' => 'Test',
        'category' => 'short_haul',
    ]);

    $booking = Booking::create([
        'booking_reference' => 'AMG-BK-TEST99',
        'travel_package_id' => $package->id,
        'customer_name' => 'John Doe',
        'customer_email' => 'john@example.com',
        'customer_phone' => '09171234567',
        'travel_date' => now()->addDays(5)->format('Y-m-d'),
        'number_of_passengers' => 2,
        'total_amount' => '$500',
        'status' => 'pending',
        'payment_status' => 'unpaid',
    ]);

    $response = $this->actingAs($admin)->post("/admin/bookings/{$booking->id}/status", [
        'status' => 'confirmed',
        'payment_status' => 'fully_paid',
    ]);

    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'module' => 'Bookings',
        'action' => 'UPDATE_STATUS',
    ]);
});

test('user account deletion logs real-time audit event', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client', 'email' => 'client.delete@example.com']);

    $response = $this->actingAs($admin)->delete("/admin/users/{$client->id}");

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseHas('activity_logs', [
        'user_id' => $admin->id,
        'module' => 'Users',
        'action' => 'DELETE',
    ]);
});
