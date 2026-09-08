<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('agent can log in via dedicated agent portal login page', function () {
    $agent = User::factory()->create([
        'email' => 'agent.test@amegatravel.com',
        'role' => 'agent',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->post('/agent/login', [
        'email' => 'agent.test@amegatravel.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect('/admin/dashboard');
    $this->assertAuthenticatedAs($agent);
});

test('admin can update agent allowed page permissions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings']]);

    $response = $this->actingAs($admin)->put("/admin/users/{$agent->id}", [
        'first_name' => $agent->first_name ?? 'Test',
        'last_name' => $agent->last_name ?? 'Agent',
        'email' => $agent->email,
        'phone' => '09171112222',
        'account_category' => 'Individual',
        'role' => 'agent',
        'allowed_pages' => ['bookings', 'inquiries', 'users'],
    ]);

    $response->assertRedirect('/admin/users');
    $agent->refresh();

    expect($agent->canAccessPage('inquiries'))->toBeTrue();
    expect($agent->canAccessPage('services'))->toBeFalse();
});

test('non-admin agent cannot access staff agent management or audit logs', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['bookings', 'users'],
    ]);

    $this->actingAs($agent)->get('/admin/agents')->assertRedirect(route('admin.dashboard'));
    $this->actingAs($agent)->get('/admin/activity-logs')->assertRedirect(route('admin.dashboard'));
});

test('agent without package permission cannot access package management routes', function () {
    $agent = User::factory()->create([
        'role' => 'agent',
        'allowed_pages' => ['bookings'],
    ]);

    $this->actingAs($agent)->get('/admin/packages')->assertRedirect(route('admin.dashboard'));
});
