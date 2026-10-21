<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;

function releaseOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function releaseClient(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123', 'nationality' => 'Korean']);
}

test('Date released is gone from the requirements step of every service, and Date filed stays', function () {
    $client = releaseClient();

    foreach (['ecc', 'visa_downgrading', '13a_spouse', 'dual_citizenship', 'visa_overstaying', 're_stamping_amendment'] as $service) {
        $this->actingAs(releaseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service]))->assertOk()
            ->assertSee('Date filed')->assertDontSee('Date released')
            ->assertDontSee('name="petition[released_on]"', false)->assertDontSee('name="overstay[released_on]"', false)->assertDontSee('name="restamping[released_on]"', false);
    }
});

test('saving a case does not clear a release date that was already recorded', function () {
    $client = releaseClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'filed_on' => now()->subWeek(), 'released_on' => now()->subDay()]);

    $this->actingAs(releaseOfficer())->put(route('admin.client-sheets.update', $client), [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Kim', 'given_name' => 'Minjun',
        'petition' => ['details' => ['ecc_kind' => 'ecc'], 'filed_on' => now()->subWeek()->toDateString()],
    ])->assertRedirect();

    expect($case->fresh()->released_on?->toDateString())->toBe(now()->subDay()->toDateString());
});

test('an open case is marked released from the Cases list, with the date given or today', function () {
    $client = releaseClient();
    $ecc = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'filed_on' => now()->subWeek()]);
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $client->id]);
    $restamp = ImmigrationRestamping::create(['immigration_client_id' => $client->id]);

    $this->actingAs(releaseOfficer())->get(route('admin.immigration.cases'))->assertOk()
        ->assertSee('Mark released')
        ->assertSee(route('admin.immigration.cases.release', ['ecc', $ecc->id]), false);

    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['ecc', $ecc->id]), ['released_on' => now()->subDays(2)->toDateString()])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, $ecc->reference().' is marked released'));
    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['overstay', $overstay->id]), ['released_on' => now()->toDateString()])->assertSessionHas('success');
    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['restamping', $restamp->id]), ['released_on' => now()->toDateString()])->assertSessionHas('success');

    expect($ecc->fresh()->released_on->toDateString())->toBe(now()->subDays(2)->toDateString())
        ->and($overstay->fresh()->released_on)->not->toBeNull()
        ->and($restamp->fresh()->released_on)->not->toBeNull()
        ->and($client->fresh()->openPetition('ecc'))->toBeNull();

    $this->actingAs(releaseOfficer())->get(route('admin.immigration.cases', ['status' => 'closed']))->assertOk()->assertSee('Reopen')->assertSee($ecc->reference());
});

test('a release needs a real date: not in the future, not before filing, not twice', function () {
    $client = releaseClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'filed_on' => now()->subDays(3)]);
    $url = route('admin.immigration.cases.release', ['ecc', $case->id]);

    $this->actingAs(releaseOfficer())->post($url, ['released_on' => now()->addDay()->toDateString()])->assertSessionHas('error', fn (string $m) => str_contains($m, 'future'));
    $this->actingAs(releaseOfficer())->post($url, ['released_on' => now()->subDays(5)->toDateString()])->assertSessionHas('error', fn (string $m) => str_contains($m, 'before the filing date'));
    $this->actingAs(releaseOfficer())->post($url, [])->assertSessionHas('error');
    expect($case->fresh()->released_on)->toBeNull();

    $this->actingAs(releaseOfficer())->post($url, ['released_on' => now()->toDateString()])->assertSessionHas('success');
    $this->actingAs(releaseOfficer())->post($url, ['released_on' => now()->toDateString()])->assertSessionHas('error', fn (string $m) => str_contains($m, 'already released'));
});

test('a released case can be reopened, unless the client already has another open one', function () {
    $client = releaseClient();
    $first = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'released_on' => now()->subWeek()]);

    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.reopen', ['ecc', $first->id]))->assertSessionHas('success', fn (string $m) => str_contains($m, 'open again'));
    expect($first->fresh()->released_on)->toBeNull();

    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.reopen', ['ecc', $first->id]))->assertSessionHas('error', fn (string $m) => str_contains($m, 'not released'));

    $first->fresh()->update(['released_on' => now()->subWeek()]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.reopen', ['ecc', $first->id]))->assertSessionHas('error', fn (string $m) => str_contains($m, 'another open case'));
    expect($first->fresh()->released_on)->not->toBeNull();
});

test('the folder lists each case with the same release action, and unknown cases are 404', function () {
    $client = releaseClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(releaseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee(route('admin.immigration.cases.release', ['ecc', $case->id]), false);

    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['ecc', 99999]), ['released_on' => now()->toDateString()])->assertNotFound();
    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['nonsense', $case->id]), ['released_on' => now()->toDateString()])->assertNotFound();
    // A case of another service is not reachable under the wrong kind.
    $this->actingAs(releaseOfficer())->post(route('admin.immigration.cases.release', ['downgrade', $case->id]), ['released_on' => now()->toDateString()])->assertNotFound();
});

test('only staff with the immigration permission can release a case', function () {
    $client = releaseClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['bookings']]);

    $this->actingAs($agent)->post(route('admin.immigration.cases.release', ['ecc', $case->id]), ['released_on' => now()->toDateString()])->assertRedirect();
    expect($case->fresh()->released_on)->toBeNull();
});

test('a released case stays on the Cases page, after the open ones, and can be filtered to', function () {
    $client = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123']);
    $released = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'released_on' => now()->subDay()]);
    $open = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => []]);
    $released->touch();

    $officer = User::factory()->create(['role' => 'admin']);

    // The default list holds both, released last even though it changed most recently.
    $this->actingAs($officer)->get(route('admin.immigration.cases'))->assertOk()
        ->assertSeeInOrder([$open->reference(), $released->reference(), 'Released '])->assertSee('All statuses');

    // The Stage filter still narrows to either group.
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'open']))->assertOk()->assertSee($open->reference())->assertDontSee($released->reference());
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'closed']))->assertOk()->assertSee($released->reference())->assertDontSee($open->reference());
});
