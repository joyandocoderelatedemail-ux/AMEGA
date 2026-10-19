<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin login screen can be rendered', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
});

test('admin can authenticate via admin login screen', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'password' => bcrypt('adminpass123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'adminpass123',
    ]);

    $this->assertAuthenticated();
    expect(Auth::user()->isAdmin())->toBeTrue();
});

test('non-admin users cannot authenticate via admin login screen', function () {
    $client = User::factory()->create([
        'role' => 'client',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/admin/login', [
        'email' => $client->email,
        'password' => 'password123',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('email');
});

test('users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('staff members are redirected to admin login when logging out', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
    ]);

    $this->actingAs($admin);

    $response = $this->post('/logout');

    $this->assertGuest();
    $response->assertRedirect(route('admin.login'));
});

test('logout with redirect_to admin parameter redirects to admin login', function () {
    $user = User::factory()->create([
        'role' => 'client',
    ]);

    $this->actingAs($user);

    $response = $this->post('/logout', [
        'redirect_to' => 'admin',
    ]);

    $this->assertGuest();
    $response->assertRedirect(route('admin.login'));
});

test('the client sign-in, sign-up and portal are gone; /login hands over to the staff sign-in', function () {
    $this->get('/login')->assertRedirect(route('admin.login'));
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['email' => 'x@example.com'])->assertNotFound();
    $this->get('/client/dashboard')->assertNotFound();
    $this->get('/client/profile')->assertNotFound();
});

test('the agency domain has no "s" anywhere the public or clients see it', function () {
    $this->get('/contact')->assertOk()
        ->assertDontSee('@amegatravelandtours.com')
        ->assertSee('visas@amegatravelandtour.com');

    foreach (['consent/data-privacy', 'consent/data-privacy-pdf', 'ticketing/agreements/pdf', 'admin/client-sheets/print', 'partials/contact'] as $view) {
        expect(file_get_contents(resource_path("views/{$view}.blade.php")))->not->toContain('amegatravelandtours.com');
    }
});
