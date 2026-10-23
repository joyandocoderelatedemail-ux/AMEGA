<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use App\Support\ImmigrationCases;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function carryAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function carryClient(string $last = 'Smith'): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => $last, 'given_name' => 'John', 'passport_number' => 'P'.random_int(1000000, 9999999), 'nationality' => 'British']);
}

/** A field each petition type's form shows, so the test can see whether it was filled in. */
const CARRY_MARKERS = [
    '13a_spouse' => ['spouse', 'passport_place'],
    '13a_permanent' => ['permanent', 'passport_place'],
    'permanent_13a_series' => ['renewal', 'remarks'],
    'dual_citizenship' => ['dual', 'father_name'],
    'visa_downgrading' => ['downgrade', 'company_name'],
    'ecc' => ['ecc', 'airline'],
];

dataset('petition services', array_keys(CARRY_MARKERS));

test('a returning client\'s new case starts from their previous one, with a notice and a Start blank link', function (string $service) {
    [$type, $key] = CARRY_MARKERS[$service];
    $client = carryClient();
    $previous = ImmigrationPetition::create([
        'immigration_client_id' => $client->id, 'type' => $type, 'details' => [$key => 'CARRIED-OVER-VALUE'],
        'checklist' => ['passport_copy'], 'fee' => 1500, 'filed_on' => '2026-09-01', 'released_on' => '2026-09-30',
        'approval_status' => 'approved', 'workflow_status' => 'approved',
    ]);

    $page = $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service]))->assertOk()
        ->assertSee('CARRIED-OVER-VALUE')
        ->assertSee('previous '.ImmigrationCases::kinds()[$type]['label'])->assertSee($previous->reference())->assertSee('Start blank')
        ->getContent();
    expect($page)->toContain('blank=1');

    // Start blank: nothing is carried and there is no notice.
    $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'blank' => 1]))->assertOk()
        ->assertDontSee('CARRIED-OVER-VALUE')->assertDontSee('Start blank');
})->with('petition services');

test('a client with no earlier case, or only another client\'s, gets a blank form and no notice', function () {
    $client = carryClient('Smith');
    $other = carryClient('Jones');
    ImmigrationPetition::create(['immigration_client_id' => $other->id, 'type' => 'ecc', 'details' => ['airline' => 'NOT-FOR-SMITH'], 'released_on' => '2026-09-30']);

    $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertDontSee('NOT-FOR-SMITH')->assertDontSee('Start blank');
});

test('an open case keeps its own data and is never replaced by an older one', function () {
    $client = carryClient();
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['airline' => 'OLD-RELEASED'], 'released_on' => '2026-08-30']);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['airline' => 'CURRENT-OPEN']]);

    $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('CURRENT-OPEN')->assertDontSee('OLD-RELEASED')->assertDontSee('Start blank');
});

test('a new case starts from the most recent earlier one of that service only', function () {
    $client = carryClient();
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['airline' => 'FIRST-TRIP'], 'released_on' => '2026-06-30']);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['airline' => 'LATEST-TRIP'], 'released_on' => '2026-09-30']);
    ImmigrationPetition::create(['immigration_client_id' => $client->id, 'type' => 'dual', 'details' => ['father_name' => 'OTHER-SERVICE'], 'released_on' => '2026-09-30']);

    $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('LATEST-TRIP')->assertDontSee('FIRST-TRIP')->assertDontSee('OTHER-SERVICE');
});

test('re-stamping and overstay start from their previous case too, and an overstay keeps the current visa expiry', function () {
    $client = carryClient();
    $client->update(['visa_expiry_date' => '2026-11-15']);
    ImmigrationRestamping::create([
        'immigration_client_id' => $client->id, 'applications' => ['transfer'], 'middle_name' => 'CARRIED-MIDDLE', 'flight_number' => 'PR 101',
        'checklist' => ['passport_copy'], 'fee' => 900, 'released_on' => '2026-09-30',
    ]);
    ImmigrationOverstay::create([
        'immigration_client_id' => $client->id, 'expired_on' => '2026-01-01', 'reason_failed' => 'CARRIED-REASON', 'destination' => 'Manila',
        'checklist' => ['passport_copy'], 'fee' => 900, 'released_on' => '2026-09-30',
    ]);

    $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 're_stamping_amendment']))->assertOk()
        ->assertSee('CARRIED-MIDDLE')->assertSee('PR 101')->assertSee('Start blank');

    $page = $this->actingAs(carryAdmin())->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => 'visa_overstaying']))->assertOk()
        ->assertSee('CARRIED-REASON')->assertSee('Start blank')->getContent();
    // The lapsed stay is the client's current one, not the earlier case's.
    expect($page)->toContain('2026-11-15')->and($page)->not->toContain('value="2026-01-01"');
});

test('saving the pre-filled form opens a new case with the carried answers and a fresh filing', function () {
    $client = carryClient();
    $previous = ImmigrationPetition::create([
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['airline' => 'PR 468', 'ecc_kind' => 'ecc'],
        'checklist' => ['passport_copy'], 'fee' => 1500, 'filed_on' => '2026-09-01', 'released_on' => '2026-09-30',
        'approval_status' => 'approved', 'workflow_status' => 'approved',
    ]);

    $this->actingAs(carryAdmin())->put(route('admin.client-sheets.update', $client), [
        'service' => 'ecc', 'last_name' => 'Smith', 'given_name' => 'John', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'petition' => ['details' => ['airline' => 'PR 468', 'ecc_kind' => 'ecc', 'remarks' => 'only this changed']],
    ])->assertSessionHasNoErrors();

    $fresh = $client->fresh()->petitions()->where('type', 'ecc')->get();
    expect($fresh)->toHaveCount(2);

    $new = $fresh->first(fn ($case) => $case->id !== $previous->id);
    expect($new->detail('airline'))->toBe('PR 468')->and($new->detail('remarks'))->toBe('only this changed')
        ->and($new->checklist)->toBe([])->and($new->released_on)->toBeNull()->and($new->filed_on)->toBeNull()
        ->and($new->workflow_status)->toBeNull();

    // The earlier case is untouched.
    expect($previous->fresh()->released_on?->toDateString())->toBe('2026-09-30')->and($previous->fresh()->checklist)->toBe(['passport_copy']);
});

test('what is carried is only form answers: no checklist, files, dates, fee, payment or approval', function () {
    $petition = new ImmigrationPetition([
        'details' => ['airline' => 'X'], 'checklist' => ['a'], 'documents' => ['a' => 'f.pdf'], 'filed_on' => '2026-09-01', 'fee' => 1500,
        'payment_type' => 'full', 'amount_paid' => 1500, 'approval_status' => 'approved', 'workflow_status' => 'approved', 'released_on' => '2026-09-30',
    ]);
    expect($petition->carryForward())->toBe(['details' => ['airline' => 'X']]);

    $restamping = new ImmigrationRestamping(['middle_name' => 'M', 'checklist' => ['a'], 'fee' => 1, 'approval_status' => 'approved', 'released_on' => '2026-09-30']);
    expect($restamping->carryForward())->toHaveKey('middle_name')->not->toHaveKeys(['checklist', 'fee', 'approval_status', 'released_on', 'documents']);

    $overstay = new ImmigrationOverstay(['reason_failed' => 'R', 'expired_on' => '2026-01-01', 'checklist' => ['a'], 'fee' => 1, 'approval_status' => 'approved']);
    expect($overstay->carryForward())->toHaveKey('reason_failed')->not->toHaveKeys(['expired_on', 'checklist', 'fee', 'approval_status', 'documents']);
});
