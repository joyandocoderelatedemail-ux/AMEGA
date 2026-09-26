<?php

use App\Models\User;
use App\Support\AdminNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the admin menu reads Services, Clients, Users, Reports & Analytics, Contents', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.dashboard'));

    $groups = collect(AdminNavigation::for($admin))->keyBy('key');

    expect($groups->pluck('label')->all())->toBe(['Services', 'Clients', 'Users', 'Reports & Analytics', 'Contents'])
        ->and(collect($groups['services']['items'])->pluck('label')->all())
        ->toBe(['Ticketing System', 'Immigration Counter', 'Visa Assistance', 'SRRV Desk'])
        ->and($groups['clients']['type'])->toBe('link')
        ->and($groups['users']['type'])->toBe('link')
        ->and(collect($groups['reports']['items'])->pluck('label')->all())->toBe(['Dashboard & Analytics', 'Audit Logs'])
        ->and(collect($groups['contents']['items'])->pluck('label')->all())->toContain('Travel Packages', 'Bookings', 'Destinations', 'Inquiries', 'Testimonials');
});

test('the admin top bar renders the new menu', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $html = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

    $positions = array_map(fn (string $label) => strpos($html, '<span class="whitespace-nowrap">'.$label.'</span>'), ['Services', 'Clients', 'Users', 'Reports &amp; Analytics', 'Contents']);

    expect($positions)->not->toContain(false)
        ->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->not->toContain('Portals &amp; Desks')
        ->and($html)->not->toContain('>Operations<');
});

test('an agent sees only the menu entries they may open', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['dashboard', 'bookings', 'ticketing']]);
    $this->actingAs($agent);

    $groups = collect(AdminNavigation::for($agent))->keyBy('key');

    expect($groups->keys()->all())->toBe(['services', 'reports', 'contents'])
        ->and(collect($groups['services']['items'])->pluck('label')->all())->toBe(['Ticketing System'])
        ->and(collect($groups['reports']['items'])->pluck('label')->all())->toBe(['Dashboard & Analytics'])
        // Bookings access also opens the CRM, and chats come with admin-panel access.
        ->and(collect($groups['contents']['items'])->pluck('label')->all())->toBe(['Bookings', 'CRM Pipeline', 'Live Guest Chats']);
});
