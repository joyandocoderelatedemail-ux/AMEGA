<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('admin login screen can be rendered', function () {
    $response = $this->get('/admin/login');

    $response->assertStatus(200);
});

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new clients can register', function () {
    $response = $this->post('/register', [
        'name' => 'Test Client',
        'email' => 'testclient@example.com',
        'phone' => '09170001122',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));

    $user = User::where('email', 'testclient@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('client');
});

test('clients can authenticate using the login screen', function () {
    $user = User::factory()->create([
        'role' => 'client',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password123',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home'));
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
