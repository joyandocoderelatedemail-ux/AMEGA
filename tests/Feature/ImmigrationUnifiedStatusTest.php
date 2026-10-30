<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;

function unifiedOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function unifiedClient(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123', 'nationality' => 'Korean']);
}

test('Denied, Withdrawn and Deferred close the case on the day they are set', function (string $status, string $label) {
    $this->travelTo('2026-10-20 09:00:00');
    $case = ImmigrationPetition::create(['immigration_client_id' => unifiedClient()->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(unifiedOfficer())->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => $status])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, "is now {$label}.") && str_contains($m, 'The transaction is closed.'));

    expect($case->fresh()->released_on->toDateString())->toBe('2026-10-20')->and($case->fresh()->isOpen())->toBeFalse();
})->with([['denied', 'Denied'], ['withdrawn', 'Withdrawn by Client'], ['deferred', 'Deferred by Applicant']]);

test('moving a closed case back to a working status opens it again, and Reopen starts it over', function () {
    $client = unifiedClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'workflow_status' => 'denied', 'released_on' => now()->subDay()]);
    $officer = unifiedOfficer();

    $this->actingAs($officer)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'for_validation'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'The transaction is open again.'));
    expect($case->fresh()->released_on)->toBeNull()->and($case->fresh()->workflow_status)->toBe('for_validation');

    $this->actingAs($officer)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'withdrawn']);
    expect($case->fresh()->released_on)->not->toBeNull();

    $this->actingAs($officer)->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]));
    expect($case->fresh()->released_on)->toBeNull()->and($case->fresh()->workflow_status)->toBe('preparing');
});

test('a closed case cannot be opened again while the client has another open one', function () {
    $client = unifiedClient();
    $closed = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'workflow_status' => 'deferred', 'released_on' => now()->subDay()]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(unifiedOfficer())->post(route('admin.immigration.cases.status', ['ecc', $closed->id]), ['status' => 'for_validation'])->assertSessionHas('error');

    expect($closed->fresh()->workflow_status)->toBe('deferred')->and($closed->fresh()->released_on)->not->toBeNull();
});

test('Approved does not close the case, and a released Approved case keeps being released', function () {
    $case = ImmigrationPetition::create(['immigration_client_id' => unifiedClient()->id, 'type' => 'ecc', 'details' => []]);
    $officer = unifiedOfficer();

    $this->actingAs($officer)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'approved']);
    expect($case->fresh()->released_on)->toBeNull();

    $this->actingAs($officer)->post(route('admin.immigration.cases.release', ['ecc', $case->id]), ['released_on' => now()->toDateString()]);
    $this->actingAs($officer)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'for_briefing']);
    expect($case->fresh()->released_on)->not->toBeNull();
});

test('the Cases page has one Status filter: open, closed, or any status', function () {
    $client = unifiedClient();
    $working = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'workflow_status' => 'for_validation']);
    $denied = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => [], 'workflow_status' => 'denied', 'released_on' => now()]);
    $officer = unifiedOfficer();

    $page = $this->actingAs($officer)->get(route('admin.immigration.cases'))->assertOk()
        ->assertSee('All statuses')->assertSee('Open transactions')->assertSee('Closed transactions')->assertSee('Where it stands')
        ->assertDontSee('Collecting requirements')->assertDontSee('Filed with the BI')->assertDontSee('Not released yet')
        ->assertSee($working->reference())->assertSee($denied->reference())->getContent();
    expect(substr_count($page, 'aria-label="Status"'))->toBe(1)->and($page)->not->toContain('name="progress"')->and($page)->toContain('Closed '.now()->format('M d, Y'));

    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'open']))->assertSee($working->reference())->assertDontSee($denied->reference());
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'closed']))->assertSee($denied->reference())->assertDontSee($working->reference());
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'denied']))->assertSee($denied->reference())->assertDontSee($working->reference());
});

test('every case form takes a fee, typed in and kept', function () {
    $client = unifiedClient();
    $officer = unifiedOfficer();
    $save = fn (array $extra) => $this->actingAs($officer)->put(route('admin.client-sheets.update', $client), ['last_name' => 'Kim', 'given_name' => 'Minjun'] + $extra);

    $save(['service' => 'ecc', 'petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '4500.50']])->assertSessionHasNoErrors();
    $save(['service' => 'visa_overstaying', 'overstay' => ['expired_on' => '2026-09-01', 'fee' => '8000']])->assertSessionHasNoErrors();
    $save(['service' => 're_stamping_amendment', 'restamping' => ['applications' => ['transfer'], 'fee' => '2500']])->assertSessionHasNoErrors();

    expect((float) ImmigrationPetition::where('type', 'ecc')->first()->fee)->toBe(4500.5)
        ->and((float) ImmigrationOverstay::first()->fee)->toBe(8000.0)
        ->and((float) ImmigrationRestamping::first()->fee)->toBe(2500.0);

    // The input is on the form, with the saved value; a bad amount is refused.
    $this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))
        ->assertSee('name="petition[fee]"', false)->assertSee('value="4500.50"', false);
    $save(['service' => 'ecc', 'petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '-5']])->assertSessionHasErrors('petition.fee');
});
