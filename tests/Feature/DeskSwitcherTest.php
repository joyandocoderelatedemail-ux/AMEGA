<?php

use App\Models\User;

/**
 * Staff given more than one desk move between them from a Switch desk menu in every desk's top bar.
 */
test('an agent with four desks can open each one and switch between them from any desk', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['ticketing', 'immigration', 'visa_assistance', 'srrv']]);
    $desks = [
        route('ticketing.dashboard'),
        route('admin.immigration.dashboard'),
        route('visa.dashboard'),
        route('srrv.dashboard'),
    ];

    foreach ($desks as $desk) {
        $html = $this->actingAs($agent)->get($desk)->assertOk()->assertSee('Switch desk')->getContent();
        foreach ($desks as $other) {
            expect($html)->toContain('href="'.$other.'" role="menuitem"');
        }
        expect(substr_count($html, 'You&#039;re here') + substr_count($html, "You're here"))->toBe(1);
    }
});

test('the menu lists only the desks the person may open, and is left out for a single desk', function () {
    $two = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['ticketing', 'srrv']]);
    $html = $this->actingAs($two)->get(route('srrv.dashboard'))->assertOk()->assertSee('Switch desk')->getContent();
    expect($html)->toContain('href="'.route('ticketing.dashboard').'" role="menuitem"')
        ->not->toContain('href="'.route('visa.dashboard').'" role="menuitem"')
        ->not->toContain('href="'.route('admin.immigration.dashboard').'" role="menuitem"');

    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->assertDontSee('Switch desk');

    $single = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['ticketing']]);
    $this->actingAs($single)->get(route('ticketing.dashboard'))->assertOk()->assertDontSee('Switch desk');
});

test('administrators do not get the Switch desk menu: they move between desks from the admin panel', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    foreach (['ticketing.dashboard', 'admin.immigration.dashboard', 'visa.dashboard', 'srrv.dashboard'] as $desk) {
        $this->actingAs($admin)->get(route($desk))->assertOk()->assertDontSee('Switch desk');
    }
});
