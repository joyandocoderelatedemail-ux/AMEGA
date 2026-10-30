<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\User;

function dashboardAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

test('each service card counts what is open on it and opens that service in the cases list', function () {
    $client = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123']);
    ImmigrationOverstay::create(['immigration_client_id' => $client->id]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'filed_on' => now()]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => [], 'released_on' => now()]);

    $viewData = $this->actingAs(dashboardAdmin())->get(route('admin.immigration.dashboard'))->assertOk()->viewData('services');
    $byLabel = $viewData->keyBy('label');

    expect($byLabel['ECC']['count'])->toBe(2)
        ->and($byLabel['ECC']['url'])->toBe(route('admin.immigration.cases', ['service' => 'ecc', 'status' => 'open']))
        ->and($byLabel['Visa Overstaying']['count'])->toBe(1)
        ->and($byLabel['Visa Downgrading']['count'])->toBe(0)
        ->and($byLabel['Visa Extension']['url'])->toBe(route('admin.immigration.cases', ['service' => 'folders', 'flagged' => 1]));
});

test('the totals count clients, open cases and those filed with the BI', function () {
    $client = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun']);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'filed_on' => now(), 'workflow_status' => 'submitted']);

    $stats = $this->actingAs(dashboardAdmin())->get(route('admin.immigration.dashboard'))->viewData('stats');

    expect($stats)->toMatchArray(['clients' => 1, 'open' => 2, 'submitted' => 1]);
});

test('the open cases list links to each case, and visas to chase link to the folder', function () {
    $client = ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => now()->subDays(2)]);
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(dashboardAdmin())->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('Open Transactions')->assertSee('Visas to Chase')->assertSee($case->reference())->assertSee('Minjun Kim')
        ->assertSee(e(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc', 'case' => $case->id])), false)
        ->assertSee(route('admin.client-sheets.edit', $client), false);
});

test('Start a service lists every service that runs, and the blank forms stay a click away', function () {
    $this->actingAs(dashboardAdmin())->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee(route('admin.client-sheets.applications', ['service' => 'ecc']), false)
        ->assertSee(route('admin.client-sheets.applications', ['service' => 'visa_extension']), false)
        ->assertSee('Blank forms')->assertSee(route('admin.client-sheets.petition-blank', ['ecc', 'form']), false);
});
