<?php

use App\Models\Booking;
use App\Models\TravelPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('guests cannot access admin dashboard', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/admin/login');
});

test('non-admin client users cannot access admin dashboard', function () {
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->get('/admin/dashboard');

    $response->assertRedirect(route('admin.login'));
});

test('authenticated admin users can access admin dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Dashboard Overview');
});

test('admin dashboard renders the analytics suite without legacy tables or quick links', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertDontSee('Quick Management');
    $response->assertSee('Total Bookings', false);
    $response->assertSee('Booking Volume', false);
    $response->assertSee('Booking Pipeline', false);
    $response->assertSee('Recent Bookings', false);
    // Inquiries and notifications are not repeated on the dashboard: the bell and Contents > Inquiries have them.
    $response->assertDontSee('Recent Inquiries', false);
    $response->assertDontSee('id="notifications-heading"', false);
});

test('admin dashboard shows empty states rather than invented figures when there is no data', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);

    // These were hardcoded into the old dashboard and were never backed by a
    // query. If any of them reappears, a placeholder has crept back in.
    foreach (['+14.8%', '+24.8% YoY', '94.2%', '88.5%', '21.4 Pax', '99.98', '42ms', '2.6 Pax'] as $invented) {
        $response->assertDontSee($invented, false);
    }

    $response->assertSee('No bookings yet', false);
    $response->assertSee('No bookings in the last six months', false);
});

test('authenticated agent staff can access admin dashboard', function () {
    $agent = User::factory()->create(['role' => 'agent']);

    $response = $this->actingAs($agent)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Dashboard Overview');
});

test('admin can access package management index', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/packages');

    $response->assertStatus(200);
    $response->assertSee('Travel Packages Directory');
});

test('staff can create client record manually', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/users', [
        'first_name' => 'Maria',
        'last_name' => 'Clara',
        'email' => 'maria.clara@example.com',
        'phone' => '+63 999 888 7777',
        'address' => '45 Taft Ave',
        'province' => 'Metro Manila',
        'city' => 'Manila City',
        'nationality' => 'Filipino',
        'account_category' => 'Individual',
        'date_of_birth' => '1991-06-19',
        'gender' => 'female',
        'role' => 'client',
    ]);

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', [
        'email' => 'maria.clara@example.com',
        'account_category' => 'Individual',
        'role' => 'client',
    ]);
});

test('admin dashboard compares each key figure with the previous period of the same length', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $package = TravelPackage::create([
        'title' => 'Boracay 4D3N', 'duration' => '4 Days', 'price' => '₱20,000', 'rating' => 5,
        'image' => 'sample.jpg', 'description' => 'Test', 'category' => 'local',
    ]);

    $book = function (string $reference, Carbon $at) use ($package): void {
        $booking = Booking::create([
            'booking_reference' => $reference, 'travel_package_id' => $package->id,
            'customer_name' => 'Maria Reyes', 'customer_email' => 'maria@example.com', 'customer_phone' => '09171234567',
            'travel_date' => now()->addMonth()->toDateString(), 'number_of_passengers' => 2,
            'amount_due' => 10000, 'status' => 'confirmed', 'payment_status' => 'deposit_paid',
        ]);
        $booking->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
    };

    // Two bookings in the last 7 days, one in the 7 days before that.
    $book('AMG-BK-CUR001', now()->subDays(1));
    $book('AMG-BK-CUR002', now()->subDays(2));
    $book('AMG-BK-PRE001', now()->subDays(10));

    $this->actingAs($admin)->get(route('admin.dashboard', ['range' => '7d']))->assertOk()
        ->assertSee('+100%')
        ->assertSee('vs the previous 7 days')
        ->assertSee('Deposit paid')
        ->assertSee('₱10,000');

    // All time has nothing before it to compare with.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
        ->assertDontSee('vs the previous');
});
