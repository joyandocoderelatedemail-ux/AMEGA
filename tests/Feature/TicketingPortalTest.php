<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

test('guests are redirected from ticketing portal to login', function () {
    $response = $this->get('/ticketing');

    $response->assertRedirect('/login');
});

test('clients cannot access ticketing portal', function () {
    $client = User::factory()->create([
        'role' => 'client',
    ]);

    $this->actingAs($client);

    $response = $this->get('/ticketing');

    $response->assertRedirect(route('home'));
    $response->assertSessionHas('error');
});

test('ticketing officer is redirected to ticketing dashboard after login', function () {
    $ticketing = User::factory()->create([
        'role' => 'ticketing',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => $ticketing->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('ticketing.dashboard'));
    expect(Auth::user()->isTicketing())->toBeTrue();
    expect(Auth::user()->isStaff())->toBeTrue();
});

test('ticketing user can access ticketing dashboard and view empty workspace', function () {
    $ticketing = User::factory()->create([
        'name' => 'Maria Santos',
        'role' => 'ticketing',
    ]);

    $this->actingAs($ticketing);

    $response = $this->get('/ticketing');

    $response->assertStatus(200);
    $response->assertSee('Ticketing Workspace');
    $response->assertSee('Maria Santos');
});

test('admin can access ticketing dashboard', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($admin);

    $response = $this->get('/ticketing');

    $response->assertStatus(200);
    $response->assertSee('Ticketing Workspace');
});

test('ticketing user cannot access admin dashboard', function () {
    $ticketing = User::factory()->create([
        'role' => 'ticketing',
    ]);

    $this->actingAs($ticketing);

    $response = $this->get('/admin/dashboard');

    $response->assertRedirect(route('ticketing.dashboard'));
    $response->assertSessionHas('error');
});

test('admin sees ticketing system link in admin sidebar', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response->assertStatus(200);
    $response->assertSee('Ticketing System');
    $response->assertSee(route('ticketing.dashboard'));
});

test('agent with ticketing permission can access ticketing dashboard and sees link in sidebar', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'bookings', 'ticketing'],
    ]);

    $response = $this->actingAs($agent)->get('/admin/dashboard');
    $response->assertStatus(200);
    $response->assertSee('Ticketing System');
    $response->assertSee(route('ticketing.dashboard'));

    $portalResponse = $this->actingAs($agent)->get('/ticketing');
    $portalResponse->assertStatus(200);
    $portalResponse->assertSee('Ticketing Workspace');
});

test('agent without ticketing permission cannot access ticketing dashboard', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['dashboard', 'bookings'],
    ]);

    $response = $this->actingAs($agent)->get('/admin/dashboard');
    $response->assertStatus(200);
    $response->assertDontSee('Ticketing System');

    $portalResponse = $this->actingAs($agent)->get('/ticketing');
    $portalResponse->assertRedirect(route('admin.dashboard'));
    $portalResponse->assertSessionHas('error');
});

test('agent with only ticketing permission lands on ticketing dashboard at login and cannot access admin dashboard', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['ticketing'],
        'password' => bcrypt('password123'),
    ]);

    expect($agent->isTicketingAgent())->toBeTrue();
    expect($agent->isTicketingStaff())->toBeTrue();
    expect($agent->hasAdminAccess())->toBeFalse();
    expect($agent->staffHomeRoute())->toBe('ticketing.dashboard');

    $loginResponse = $this->post('/login', [
        'email' => $agent->email,
        'password' => 'password123',
    ]);
    $loginResponse->assertRedirect(route('ticketing.dashboard'));

    $adminResponse = $this->actingAs($agent)->get('/admin/dashboard');
    $adminResponse->assertRedirect(route('ticketing.dashboard'));
    $adminResponse->assertSessionHas('error');

    $portalResponse = $this->actingAs($agent)->get('/ticketing');
    $portalResponse->assertStatus(200);
    $portalResponse->assertSee('Ticketing Workspace');
    $portalResponse->assertDontSee('Main Admin');
    $portalResponse->assertDontSee('Audit Logs');
    $portalResponse->assertDontSee('Travel Packages');
});
