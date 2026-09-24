<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests cannot access admin dashboard', function () {
    $response = $this->get('/admin/dashboard');

    $response->assertRedirect('/login');
});

test('non-admin client users cannot access admin dashboard', function () {
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->get('/admin/dashboard');

    $response->assertRedirect('/login');
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
    $response->assertSee('Recent Inquiries', false);
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
    $response->assertSee('No inquiries yet', false);
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
        'address' => 'Manila, Philippines',
        'nationality' => 'Filipino',
        'account_category' => 'Individual',
        'role' => 'client',
    ]);

    $response->assertRedirect('/admin/users');
    $this->assertDatabaseHas('users', [
        'email' => 'maria.clara@example.com',
        'account_category' => 'Individual',
        'role' => 'client',
    ]);
});
