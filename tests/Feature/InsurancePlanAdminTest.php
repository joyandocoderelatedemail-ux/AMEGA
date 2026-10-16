<?php

use App\Models\InsurancePlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the three original plans are there to begin with', function () {
    expect(InsurancePlan::orderBy('sort_order')->pluck('key')->all())->toBe(['basic', 'standard', 'premium'])
        ->and(InsurancePlan::where('key', 'standard')->first()->is_popular)->toBeTrue();
});

test('an admin can add, edit and switch off a plan', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.insurance-plans.index'))->assertOk()->assertSee('Standard Plan');

    $this->actingAs($admin)->post(route('admin.insurance-plans.store'), [
        'name' => 'Family Plan',
        'price_per_pax' => '2400.50',
        'coverage' => "Up to \$75,000 Medical\n\n  Child cover  \n",
    ])->assertRedirect(route('admin.insurance-plans.index'));

    $plan = InsurancePlan::where('key', 'family-plan')->firstOrFail();
    expect($plan->coverage)->toBe(['Up to $75,000 Medical', 'Child cover'])
        ->and((float) $plan->price_per_pax)->toBe(2400.5)
        ->and($plan->is_active)->toBeTrue();

    // An unticked "offer" box switches it off; the key stays so old bookings keep the name.
    $this->actingAs($admin)->put(route('admin.insurance-plans.update', $plan), [
        'name' => 'Family Plus',
        'price_per_pax' => '2600',
        'coverage' => 'Child cover',
    ])->assertRedirect(route('admin.insurance-plans.index'));

    expect($plan->fresh()->key)->toBe('family-plan')
        ->and($plan->fresh()->name)->toBe('Family Plus')
        ->and($plan->fresh()->is_active)->toBeFalse();
});

test('only an admin can manage the plans', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($ticketing)->get(route('admin.insurance-plans.index'))->assertRedirect();
    $this->actingAs($ticketing)->post(route('admin.insurance-plans.store'), ['name' => 'X', 'price_per_pax' => 1])->assertRedirect();

    expect(InsurancePlan::count())->toBe(3);
});

test('a plan needs a name and a price', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('admin.insurance-plans.store'), ['name' => '', 'price_per_pax' => '-5'])
        ->assertSessionHasErrors(['name', 'price_per_pax']);
});

test('the ticket wizard offers the plans the admin has switched on, and hides insurance when none are', function () {
    $ticketing = User::factory()->create(['role' => 'ticketing']);
    InsurancePlan::where('key', 'basic')->update(['name' => 'Starter Cover', 'price_per_pax' => 700]);
    InsurancePlan::where('key', 'premium')->update(['is_active' => false]);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))
        ->assertOk()
        ->assertSee('Starter Cover')
        ->assertSee('Standard Plan')
        ->assertDontSee('Premium Plan');

    InsurancePlan::query()->update(['is_active' => false]);

    $this->actingAs($ticketing)->get(route('ticketing.tickets.create'))
        ->assertOk()
        ->assertDontSee('Starter Cover')
        ->assertDontSee('Standard Plan');
});

test('the name of a plan on a booking follows the admin list', function () {
    InsurancePlan::where('key', 'basic')->update(['name' => 'Starter Cover']);

    expect(InsurancePlan::labelFor('basic'))->toBe('Starter Cover')
        ->and(InsurancePlan::labelFor(null))->toBe('Standard Plan')
        ->and(InsurancePlan::labelFor('gold-tier'))->toBe('Gold Tier Plan');
});

test('an admin can add, edit and switch off plans from the ticketing portal', function () {
    $officer = User::factory()->create(['role' => 'admin']);

    $this->actingAs($officer)->get(route('ticketing.dashboard'))
        ->assertSeeInOrder(['Airlines', 'Insurance'])
        ->assertSee(route('ticketing.insurance-plans.index'), false);

    $this->actingAs($officer)->get(route('ticketing.insurance-plans.index'))
        ->assertOk()
        ->assertSee('portal-minimal', false)
        ->assertSee(route('ticketing.insurance-plans.store'), false);

    $this->actingAs($officer)->post(route('ticketing.insurance-plans.store'), [
        'name' => 'Senior Plan',
        'price_per_pax' => '1800',
        'coverage' => "Up to \$30,000 Medical\nPre-existing conditions",
    ])->assertRedirect(route('ticketing.insurance-plans.index'));

    $plan = InsurancePlan::where('key', 'senior-plan')->firstOrFail();
    expect($plan->is_active)->toBeTrue()->and($plan->coverage)->toHaveCount(2);

    $this->actingAs($officer)->put(route('ticketing.insurance-plans.update', $plan), [
        'name' => 'Senior Plan',
        'price_per_pax' => '1950',
    ])->assertRedirect(route('ticketing.insurance-plans.index'));

    expect($plan->fresh())->is_active->toBeFalse()
        ->and((float) $plan->fresh()->price_per_pax)->toBe(1950.0);

    // The wizard's insurance panel links to the plans.
    $this->actingAs($officer)->get(route('ticketing.tickets.create'))
        ->assertSee(route('ticketing.insurance-plans.index'), false);
});

test('ticketing officers use the plans but cannot open or change them', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $visa = User::factory()->create(['role' => 'visa_assistance']);
    $plan = InsurancePlan::firstOrFail();

    $this->actingAs($officer)->get(route('ticketing.dashboard'))
        ->assertDontSee(route('ticketing.insurance-plans.index'), false);
    $this->actingAs($officer)->get(route('ticketing.tickets.create'))
        ->assertDontSee('Manage plans');

    $this->actingAs($officer)->get(route('ticketing.insurance-plans.index'))->assertRedirect();
    $this->actingAs($officer)->post(route('ticketing.insurance-plans.store'), ['name' => 'X', 'price_per_pax' => 1])->assertRedirect();
    $this->actingAs($officer)->put(route('ticketing.insurance-plans.update', $plan), ['name' => 'Hacked', 'price_per_pax' => 1])->assertRedirect();
    $this->actingAs($visa)->post(route('ticketing.insurance-plans.store'), ['name' => 'X', 'price_per_pax' => 1])->assertRedirect();

    expect(InsurancePlan::where('name', 'X')->exists())->toBeFalse()
        ->and($plan->fresh()->name)->not->toBe('Hacked');
});
