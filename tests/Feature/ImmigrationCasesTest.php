<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use App\Support\ImmigrationCases;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function casesOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function casesClient(string $last = 'Smith', string $passport = 'GB7654321'): ImmigrationClient
{
    return ImmigrationClient::create([
        'last_name' => $last, 'given_name' => 'John', 'passport_number' => $passport, 'nationality' => 'British',
        'visa_expiry_date' => now()->addMonth()->toDateString(),
    ]);
}

test('each case has a reference made of its kind, year and number', function () {
    $client = casesClient();
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $client->id]);
    $petition = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'renewal']);
    $year = now()->format('Y');

    expect($overstay->reference())->toBe("OS-{$year}-".str_pad((string) $overstay->id, 4, '0', STR_PAD_LEFT))
        ->and($petition->reference())->toStartWith("ACR-{$year}-")
        ->and((new ImmigrationRestamping)->reference())->toBe('New')
        ->and(ImmigrationCases::parseReference($overstay->reference()))->toBe(['kind' => 'overstay', 'id' => $overstay->id])
        ->and(ImmigrationCases::parseReference('13ap-2026-7'))->toBe(['kind' => 'spouse', 'id' => 7])
        ->and(ImmigrationCases::parseReference('John Smith'))->toBeNull();
});

test('the folder has no Services card: a case shows its status and Mark released in the header', function () {
    $client = casesClient();
    $client->extensions()->create(['sequence' => 1, 'extension_date' => now()->subMonth()->toDateString(), 'amount_paid' => 3000]);
    $open = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'renewal', 'checklist' => ['application_form']]);
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'filed_on' => now()->subWeek(), 'released_on' => now()]);

    // The folder alone shows no card of cases.
    $this->actingAs(casesOfficer())->get(route('admin.client-sheets.edit', $client))->assertOk()
        ->assertDontSee('Start a service')->assertDontSee('Every case in this client');

    // An open case: its status dropdown and Mark released are in the header.
    $this->actingAs(casesOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'permanent_13a_series', 'case' => $open->id]))->assertOk()
        ->assertSee($open->reference())->assertSee('On-going Preparation')->assertSee('Mark released')
        ->assertSee(route('admin.immigration.cases.release', ['renewal', $open->id]), false);

    // A released case offers Reopen.
    $this->actingAs(casesOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'visa_overstaying', 'case' => $overstay->id]))->assertOk()
        ->assertSee($overstay->reference())->assertSee('Reopen')->assertSee('closed');
});

test('a released case opens from the folder, saves in place, and prints', function () {
    $client = casesClient();
    $old = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'reason_failed' => 'Old reason', 'filed_on' => now()->subMonth(), 'released_on' => now()->subWeek()]);
    $open = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'reason_failed' => 'New reason']);

    $this->actingAs(casesOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'visa_overstaying', 'case' => $old->id]))
        ->assertOk()->assertSee('Old reason')->assertSee('name="case_id" value="'.$old->id.'"', false);

    $this->actingAs(casesOfficer())->put(route('admin.client-sheets.update', $client), [
        'service' => 'visa_overstaying', 'case_id' => $old->id,
        'last_name' => 'Smith', 'given_name' => 'John',
        'overstay' => ['reason_failed' => 'Corrected reason', 'filed_on' => $old->filed_on->toDateString(), 'released_on' => $old->released_on->toDateString()],
    ])->assertRedirect(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'visa_overstaying', 'case' => $old->id]));

    expect($old->fresh()->reason_failed)->toBe('Corrected reason')
        ->and($open->fresh()->reason_failed)->toBe('New reason');

    $this->actingAs(casesOfficer())->get(route('admin.client-sheets.reconsideration', ['clientSheet' => $client, 'case' => $old->id]))
        ->assertOk()->assertSee('Corrected reason')->assertDontSee('New reason');
});

test('the cases page lists open cases across clients and filters by service and status', function () {
    $smith = casesClient();
    $tanaka = casesClient('Tanaka', 'JP1234567');
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $smith->id]);
    $filed = ImmigrationRestamping::create(['immigration_client_id' => $tanaka->id, 'applications' => ['failed_stamp'], 'filed_on' => now(), 'workflow_status' => 'submitted']);
    $released = ImmigrationPetition::create(['immigration_client_id' => $tanaka->id, 'type' => 'spouse', 'filed_on' => now()->subMonth(), 'released_on' => now()]);
    $officer = casesOfficer();

    $this->actingAs($officer)->get(route('admin.immigration.cases'))->assertOk()
        ->assertSee($overstay->reference())->assertSee($filed->reference())->assertSee($released->reference())
        ->assertSee('Failed to Stamp');

    $this->actingAs($officer)->get(route('admin.immigration.cases', ['service' => 'restamping']))
        ->assertSee($filed->reference())->assertDontSee($overstay->reference());

    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'submitted']))
        ->assertSee($filed->reference())->assertDontSee($overstay->reference());

    $this->actingAs($officer)->get(route('admin.immigration.cases', ['status' => 'closed']))
        ->assertSee($released->reference())->assertDontSee($filed->reference());

    // Search by client, and by reference.
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['q' => 'Tanaka']))
        ->assertSee($filed->reference())->assertDontSee($overstay->reference());
    $this->actingAs($officer)->get(route('admin.immigration.cases', ['q' => strtolower($overstay->reference())]))
        ->assertSee($overstay->reference())->assertDontSee($filed->reference());

    $this->actingAs($officer)->get(route('admin.immigration.cases', ['q' => 'Nobody']))->assertSee('No transactions match');
});

test('the cases tab is in the counter navigation', function () {
    $this->actingAs(casesOfficer())->get(route('admin.immigration.dashboard'))
        ->assertSee(route('admin.immigration.cases'), false);
});

test('a dedicated immigration agent can open the cases page', function () {
    $agent = User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);

    $this->actingAs($agent)->get(route('admin.immigration.cases'))->assertOk();
});

test('prints opened from a service folder come back to that folder and case', function () {
    $client = casesClient();
    $case = ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'spouse']);
    $folder = route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => '13a_spouse', 'case' => $case->id]);
    $officer = casesOfficer();

    $this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => '13a_spouse']))
        ->assertSee(e(route('admin.client-sheets.print', ['clientSheet' => $client, 'service' => '13a_spouse', 'case' => $case->id])), false)
        ->assertSee(e(route('admin.client-sheets.consent', ['clientSheet' => $client, 'service' => '13a_spouse', 'case' => $case->id])), false);

    $this->actingAs($officer)->get(route('admin.client-sheets.print', ['clientSheet' => $client, 'service' => '13a_spouse', 'case' => $case->id]))
        ->assertSee(e($folder), false);
    $this->actingAs($officer)->get(route('admin.client-sheets.consent', ['clientSheet' => $client, 'service' => '13a_spouse', 'case' => $case->id]))
        ->assertSee(e($folder), false);

    // Opened from Visa Extension, they go back to Visa Extension.
    $this->actingAs($officer)->get(route('admin.client-sheets.print', $client))
        ->assertSee('href="'.route('admin.client-sheets.edit', $client).'"', false);
});

test('the Visa Extension BI forms ignore an open overstay case; the overstay folder uses it', function () {
    $client = casesClient();
    $overstay = ImmigrationOverstay::create(['immigration_client_id' => $client->id, 'checklist' => ['cgaf', 'passport']]);
    $officer = casesOfficer();

    $extension = $this->actingAs($officer)->get(route('admin.client-sheets.bi-form', [$client, 'checklist']))->getContent();
    expect(substr_count($extension, '&#10003;'))->toBe(0);

    $fromOverstay = $this->actingAs($officer)->get(route('admin.client-sheets.bi-form', ['clientSheet' => $client, 'form' => 'checklist', 'service' => 'visa_overstaying', 'case' => $overstay->id]))->getContent();
    expect(substr_count($fromOverstay, '&#10003;'))->toBe(2)
        ->and($fromOverstay)->toContain(e(route('admin.client-sheets.bi-form', ['clientSheet' => $client, 'form' => 'cgaf', 'service' => 'visa_overstaying', 'case' => $overstay->id])));
});
