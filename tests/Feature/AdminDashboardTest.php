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
