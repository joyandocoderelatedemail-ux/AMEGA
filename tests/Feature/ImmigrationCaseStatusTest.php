<?php

use App\Models\ActivityLog;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use App\Support\CaseProgress;

function statusOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function statusClient(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123', 'nationality' => 'Korean']);
}

test('the status options are the eleven the desk uses, in order', function () {
    expect(array_values(CaseProgress::OPTIONS))->toBe([
        'On-going Preparation', 'For Validation', 'For Final Checking', 'For Submission', 'Submitted',
        'Awaiting Decision/Result', 'For Briefing', 'Approved', 'Denied', 'Withdrawn by Client', 'Deferred by Applicant',
    ]);
});

test('a case that was never updated is On-going Preparation', function () {
    $case = ImmigrationPetition::create(['immigration_client_id' => statusClient()->id, 'type' => 'ecc', 'details' => []]);

    expect($case->progressKey())->toBe('preparing')->and($case->progressLabel())->toBe('On-going Preparation');
});

test('the Cases list has an Update Status control on every case', function () {
    $client = statusClient();
    $ecc = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'workflow_status' => 'for_validation']);

    $html = $this->actingAs(statusOfficer())->get(route('admin.immigration.cases'))->assertOk()
        ->assertSee('Update Status')
        ->assertSee(route('admin.immigration.cases.status', ['ecc', $ecc->id]), false)
        ->assertSee(route('admin.immigration.cases.status', ['overstay', $overstay->id]), false)
        ->assertSeeInOrder(['On-going Preparation', 'For Validation', 'For Final Checking', 'For Submission', 'Submitted', 'Awaiting Decision/Result', 'For Briefing', 'Approved', 'Denied', 'Withdrawn by Client', 'Deferred by Applicant'])
        ->getContent();

    expect($html)->toContain('<option value="for_validation" selected')
        ->and($html)->toContain('<option value="preparing" selected');
});

test('updating a status saves it, notes when, and logs who moved it from where', function () {
    $case = ImmigrationPetition::create(['immigration_client_id' => statusClient()->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'for_final_checking'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, $case->reference().' is now For Final Checking'));

    $case = $case->fresh();
    expect($case->workflow_status)->toBe('for_final_checking')->and($case->workflow_status_at)->not->toBeNull();

    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'awaiting_decision']);
    expect(ActivityLog::latest('id')->first()->description)->toContain($case->reference())->toContain('For Final Checking → Awaiting Decision/Result');
});

test('every kind of case takes a status', function () {
    $client = statusClient();
    $cases = [
        'overstay' => ImmigrationOverstay::create(['immigration_client_id' => $client->id]),
        'restamping' => ImmigrationRestamping::create(['immigration_client_id' => $client->id]),
        'spouse' => ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'spouse', 'details' => []]),
        'downgrade' => ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => []]),
    ];

    foreach ($cases as $kind => $case) {
        $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', [$kind, $case->id]), ['status' => 'approved'])->assertSessionHas('success');
        expect($case->fresh()->progressKey())->toBe('approved');
    }
});

test('only the listed statuses are accepted, and an unknown case is a 404', function () {
    $case = ImmigrationPetition::create(['immigration_client_id' => statusClient()->id, 'type' => 'ecc', 'details' => []]);

    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'whatever'])->assertSessionHas('error');
    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', $case->id]), [])->assertSessionHas('error');
    expect($case->fresh()->workflow_status)->toBeNull();

    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', 99999]), ['status' => 'approved'])->assertNotFound();
    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['downgrade', $case->id]), ['status' => 'approved'])->assertNotFound();
});

test('marking a case Submitted records today as the date filed, unless one was set', function () {
    $client = statusClient();
    $fresh = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    $filed = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => [], 'filed_on' => now()->subDays(5)]);

    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['ecc', $fresh->id]), ['status' => 'submitted']);
    $this->actingAs(statusOfficer())->post(route('admin.immigration.cases.status', ['downgrade', $filed->id]), ['status' => 'submitted']);

    expect($fresh->fresh()->filed_on->toDateString())->toBe(now()->toDateString())
        ->and($filed->fresh()->filed_on->toDateString())->toBe(now()->subDays(5)->toDateString());
});

test('the list filters by status, and a case never updated counts as On-going Preparation', function () {
    $client = statusClient();
    $untouched = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => []]);
    $validating = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'downgrade', 'details' => [], 'workflow_status' => 'for_validation']);
    $approved = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'workflow_status' => 'approved']);

    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['status' => 'preparing']))->assertOk()
        ->assertSee($untouched->reference())->assertDontSee($validating->reference())->assertDontSee($approved->reference());
    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['status' => 'for_validation']))->assertOk()
        ->assertSee($validating->reference())->assertDontSee($untouched->reference());
    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['status' => 'approved', 'service' => 'overstay']))->assertOk()
        ->assertSee($approved->reference())->assertDontSee($validating->reference());
    // An unknown filter is ignored.
    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['status' => 'nonsense']))->assertOk()
        ->assertSee($untouched->reference())->assertSee($validating->reference());
});

test('the folder shows the same status control on its cases, and the dashboard shows where each stands', function () {
    $client = statusClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'workflow_status' => 'for_briefing']);

    $this->actingAs(statusOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee(route('admin.immigration.cases.status', ['ecc', $case->id]), false);

    $this->actingAs(statusOfficer())->get(route('admin.immigration.dashboard'))->assertOk()->assertSee('For Briefing')->assertSee($case->reference());
});

test('a released case can still have its status updated, and the status survives saving the folder', function () {
    $client = statusClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'released_on' => now()->subDay(), 'workflow_status' => 'approved']);

    $this->actingAs(statusOfficer())->put(route('admin.client-sheets.update', $client), [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Kim', 'given_name' => 'Minjun',
        'petition' => ['details' => ['ecc_kind' => 'ecc']],
    ])->assertRedirect();

    expect($case->fresh()->workflow_status)->toBe('approved');
});

test('a search on the Cases page lists the matching cases and the folders that have no case yet', function () {
    $withCase = ImmigrationClient::create(['last_name' => 'Santos', 'given_name' => 'Ana', 'passport_number' => 'P111']);
    $case = ImmigrationPetition::create(['immigration_client_id' => $withCase->id, 'type' => 'ecc', 'details' => []]);
    $ledgerOnly = ImmigrationClient::create(['last_name' => 'Santos', 'given_name' => 'Ben', 'passport_number' => 'P222']);
    $ledgerOnly->extensions()->create(['sequence' => 1, 'extension_date' => now()->toDateString(), 'amount_paid' => 3000]);
    $nothing = ImmigrationClient::create(['last_name' => 'Santos', 'given_name' => 'Cleo', 'passport_number' => 'P333']);

    $html = $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['q' => 'Santos']))->assertOk()
        ->assertSee($case->reference())->assertSee('Ana Santos')
        ->assertSee('Client folders with no transaction yet')->assertSee('Ben Santos')->assertSee('Cleo Santos')
        ->getContent();

    // Ana's folder is not repeated: she is already there through her case.
    expect($html)->not->toContain('href="'.route('admin.client-sheets.edit', ['client_sheet' => $withCase->id]).'"')
        ->and($html)->toContain('href="'.route('admin.client-sheets.edit', ['client_sheet' => $ledgerOnly->id]).'"');

    // A name nobody has gets the new-client path; a name with a case needs no such notice.
    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['q' => 'Nobody']))->assertOk()->assertSee('No client on file')->assertSee('Create client record');
    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases', ['q' => 'Ana']))->assertOk()->assertDontSee('No client on file');
});

test('the Cases page no longer has a Client Folders tab, and the folder list sits behind the Service dropdown', function () {
    statusClient();

    $this->actingAs(statusOfficer())->get(route('admin.immigration.cases'))->assertOk()
        ->assertDontSee('Client Folders')->assertSee('All client folders (1)')->assertSee('Visa Extension (0 clients)');
    $this->actingAs(statusOfficer())->get(route('admin.immigration.dashboard'))->assertOk()->assertDontSee('Client Folders');
});
