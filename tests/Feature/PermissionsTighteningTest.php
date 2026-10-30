<?php

use App\Models\ImmigrationCategory;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationPricingTier;
use App\Models\User;

/**
 * What the rule book closes: prices and catalogs take "Manage prices"; immigration folders are private
 * to their agent (older unowned folders stay with the whole counter); a supervisor's "view_all" sees all.
 */
test('the counter reads the BI price list, but changing it takes Manage prices', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    $keeper = staffWith('agent', ['prices.manage'], ['immigration']);
    $category = ImmigrationCategory::factory()->create();
    $tier = ImmigrationPricingTier::factory()->create(['immigration_category_id' => $category->id]);

    $this->actingAs($agent)->get(route('admin.immigration-pricing.index'))->assertOk()
        ->assertDontSee(route('admin.immigration-pricing.create'), false)
        ->assertDontSee(route('admin.immigration-pricing.edit', $tier), false);
    $this->actingAs($agent)->get(route('admin.immigration-pricing.edit', $tier))->assertForbidden();
    $this->actingAs($agent)->put(route('admin.immigration-pricing.update', $tier), ['price' => 1])->assertForbidden();

    $this->actingAs($keeper)->get(route('admin.immigration-pricing.index'))->assertOk()
        ->assertSee(route('admin.immigration-pricing.edit', $tier), false);
    $this->actingAs($keeper)->get(route('admin.immigration-pricing.edit', $tier))->assertOk();
});

test('the ticketing desk reads airlines and packages, but changing them takes Manage prices', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($officer)->get(route('ticketing.airlines.index'))->assertOk()->assertDontSee('Add an airline');
    $this->actingAs($officer)->post(route('ticketing.airlines.store'), ['name' => 'X Air'])->assertForbidden();
    $this->actingAs($officer)->get(route('ticketing.packages.index'))->assertOk()->assertDontSee('Add package');
    $this->actingAs($officer)->get(route('ticketing.packages.configurator'))->assertForbidden();

    $this->actingAs(priceManager())->get(route('ticketing.airlines.index'))->assertOk()->assertSee('Add an airline');
});

test('immigration folders are private to their agent; unowned older folders stay with the counter', function () {
    $ana = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
    $ben = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);

    $this->actingAs($ana);
    $anas = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'P1']);
    expect($anas->created_by)->toBe($ana->id);

    $this->actingAs($ben);
    $bens = ImmigrationClient::create(['last_name' => 'Park', 'given_name' => 'Jisoo', 'passport_number' => 'P2']);
    $old = ImmigrationClient::withoutGlobalScopes()->create(['last_name' => 'Old', 'given_name' => 'Folder', 'passport_number' => 'P3']);
    $old->forceFill(['created_by' => null])->save();

    $this->actingAs($ana);
    expect(ImmigrationClient::pluck('last_name')->sort()->values()->all())->toBe(['Kim', 'Old']);
    $this->get(route('admin.client-sheets.edit', $bens))->assertNotFound();
    $this->get(route('admin.client-sheets.edit', $anas))->assertOk();

    // A supervisor's "See everyone's files" and an admin see every folder.
    $supervisor = staffWith('agent', ['immigration.view_all'], ['immigration']);
    $this->actingAs($supervisor);
    expect(ImmigrationClient::count())->toBe(3);
    $this->actingAs(User::factory()->create(['role' => 'admin']));
    expect(ImmigrationClient::count())->toBe(3);
});

test('a new agent with nothing ticked has no access', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->post(route('admin.agents.store'), [
        'first_name' => 'No', 'last_name' => 'Access', 'email_name' => 'noaccess', 'phone' => '09171234567', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'role' => 'agent',
    ]);

    $agent = User::where('email', 'like', 'noaccess@%')->firstOrFail();
    expect($agent->allowed_pages)->toBe([])->and($agent->grantedPages())->toBe([]);
});
