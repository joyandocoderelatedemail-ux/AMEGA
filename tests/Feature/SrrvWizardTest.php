<?php

use App\Models\SrrvApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function wizardOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

test('the SRRV desk has a Services menu like the other desks, opening each service', function () {
    $html = $this->actingAs(wizardOfficer())->get(route('srrv.dashboard'))->assertOk()
        ->assertSee('Services')->assertSee('aria-haspopup="menu"', false)
        ->assertSee('SRRV Application')->assertSee('ID Renewal')
        ->assertDontSee('New Retiree File')
        ->getContent();

    expect($html)->toContain('href="'.route('srrv.applications.create').'" role="menuitem"')
        ->and($html)->toContain('href="'.route('srrv.renewals.create').'" role="menuitem"');
});

test('a new SRRV application is step by step and opens on the PRA overview', function () {
    $html = $this->actingAs(wizardOfficer())->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('Who can apply?')->assertSee('How to apply?')
        ->assertSee('SRRV Classic:')->assertSee('Filipino retirees that are naturalized in other countries and have not re-acquired Filipino citizenship.')
        ->assertSee('Bureau of Immigration Clearance Certificate (BICC).')
        ->assertSee('Continue to')
        ->getContent();

    expect($html)->toContain('step: 0, first: 0, last: 6')
        ->and($html)->toContain('Step 0 - Overview')
        ->and($html)->toContain('novalidate')
        ->and(substr_count($html, 'class="w-6 h-6 rounded-full bg-primary/10'))->toBe(10);
});

test('editing an SRRV file starts at step 1 with no overview, and a failed save opens the step with the problem', function () {
    $officer = wizardOfficer();
    $this->actingAs($officer)->post('/srrv/applications', [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com', 'retiree_phone' => '+63 900 111 2222', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'Filipino', 'srrv_card_number' => 'SRRV-00123', 'investment_amount' => 1500, 'service_fee' => 1000, 'amount_paid' => 1000,
    ])->assertRedirect();
    $file = SrrvApplication::latest('id')->firstOrFail();

    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertOk()
        ->assertDontSee('Who can apply?')->assertSee('step: 1, first: 1, last: 6', false);

    // A missing retiree name sends the officer back to the Retiree step.
    $this->actingAs($officer)->from(route('srrv.applications.create'))->post('/srrv/applications', ['service_type' => 'renewal_application', 'visa_class' => 'classic'])
        ->assertSessionHasErrors('retiree_name');
    $this->actingAs($officer)->get(route('srrv.applications.create'))->assertOk()->assertSee('step: 2, first: 0', false);
});

test('a new ID renewal is step by step and opens on the PRA overview', function () {
    $html = $this->actingAs(wizardOfficer())->get(route('srrv.renewals.create'))->assertOk()
        ->assertSee('Who can apply?')->assertSee('The principal SRRV holder, for the renewal of the SRRV ID card.')
        ->assertSee('Get a queue number at Window 2 of the PRA Servicing Lounge.')
        ->getContent();

    expect($html)->toContain('step: 0, first: 0, last: 6')
        ->and($html)->toContain('Step 0 - Overview')
        ->and(substr_count($html, 'class="w-6 h-6 rounded-full bg-primary/10'))->toBe(6);
});
