<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;

function bookOnline(string $name = 'Juan Dela Cruz'): void
{
    $package = TravelPackage::firstOrCreate(['title' => 'Palawan Escape'], [
        'duration' => '5 Days', 'price' => '$1,000', 'rating' => 5, 'image' => 'images/test.jpg',
        'description' => 'Test package', 'category' => 'short_haul', 'status' => 'active',
    ]);

    test()->post('/bookings', [
        'travel_package_id' => $package->id, 'customer_name' => $name, 'customer_email' => 'juan@example.com',
        'customer_phone' => '09171234567', 'travel_date' => now()->addDays(10)->format('Y-m-d'), 'number_of_passengers' => 2,
    ])->assertRedirect();
}

test('an online booking shows a red badge on Bookings and in the bell until the list is opened', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    bookOnline();

    expect(Booking::first()->read_at)->toBeNull();

    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertOk()
        ->assertJsonPath('bookings', 1)->assertJsonPath('total', 1)
        ->assertJsonPath('latest.0.type', 'booking')->assertJsonPath('latest.0.title', 'New booking from Juan Dela Cruz')
        ->assertJsonPath('latest.0.detail', 'Palawan Escape · 2 pax');

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()
        ->assertSee('data-notify-count="bookings"', false)->assertSee('data-notify-count="bookings,inquiries"', false);

    // Opening the bookings list reads it: marked New this once, and the count drops to zero.
    $this->actingAs($admin)->get(route('admin.bookings.index'))->assertOk()->assertSee('>New</span>', false);
    $this->actingAs($admin)->getJson(route('admin.notifications'))->assertJsonPath('bookings', 0)->assertJsonPath('total', 0);
});

test('the bell counts only the pages a staff member has', function () {
    bookOnline();
    $bookingsOnly = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);
    $neither = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['users']]);

    $this->actingAs($bookingsOnly)->getJson(route('admin.notifications'))->assertJsonPath('bookings', 1)->assertJsonPath('inquiries', 0);
    $this->actingAs($bookingsOnly)->get(route('admin.bookings.index'))->assertOk()->assertSee('adminBell(', false);

    $this->actingAs($neither)->getJson(route('admin.notifications'))->assertJsonPath('total', 0);
    $this->actingAs($neither)->get(route('admin.users.index'))->assertOk()->assertDontSee('adminBell(', false);
});
