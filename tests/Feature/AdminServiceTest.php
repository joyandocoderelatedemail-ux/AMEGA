<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view services list', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/admin/services');

    $response->assertStatus(200);
    $response->assertSee('Services Management');
});

test('admin can create a service', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post('/admin/services', [
        'title' => 'Passport Renewal Service',
        'short_description' => 'Fast passport processing',
        'icon' => 'file-text',
        'is_active' => '1',
    ]);

    $response->assertRedirect('/admin/services');
    $this->assertDatabaseHas('services', [
        'title' => 'Passport Renewal Service',
        'is_active' => true,
    ]);
});

test('admin can edit and update a service', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = Service::create([
        'title' => 'Old Service Title',
        'short_description' => 'Old description',
        'icon' => 'globe',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->put("/admin/services/{$service->id}", [
        'title' => 'Updated Service Title',
        'short_description' => 'Updated description',
        'icon' => 'shield',
        'is_active' => '1',
    ]);

    $response->assertRedirect('/admin/services');
    $this->assertDatabaseHas('services', [
        'id' => $service->id,
        'title' => 'Updated Service Title',
        'icon' => 'shield',
    ]);
});

test('admin can toggle service status between enabled and disabled', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $service = Service::create([
        'title' => 'Toggle Test Service',
        'short_description' => 'Test',
        'icon' => 'plane',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)->post("/admin/services/{$service->id}/toggle-status");

    $response->assertStatus(302);
    expect($service->fresh()->is_active)->toBeFalse();

    // Enable again
    $this->actingAs($admin)->post("/admin/services/{$service->id}/toggle-status");
    expect($service->fresh()->is_active)->toBeTrue();
});
