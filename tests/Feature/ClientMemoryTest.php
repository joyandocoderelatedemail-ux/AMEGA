<?php

use App\Models\SrrvApplication;
use App\Models\User;
use App\Services\ClientMemory;

beforeEach(fn () => autoApproveDeskFiles());

function memoryOfficer(string $role = 'srrv'): User
{
    return User::factory()->create(['role' => $role]);
}

function memoryCgaf(array $changes = []): array
{
    return array_merge([
        'application_method' => 'personal', 'application_mode' => 'regular', 'months_requested' => 1, 'reason' => 'To continue my vacation.',
        'mobile_number' => '09171234567', 'email' => 'john.smith@example.com', 'last_name' => 'Smith', 'first_name' => 'John',
        'date_of_birth' => '1985-03-14', 'sex' => 'M', 'birthplace' => 'London', 'citizenship' => 'British', 'height_cm' => '180', 'weight_kg' => '78',
        'civil_status' => 'married', 'address_street' => 'Unit 12B, 88 Ayala Avenue', 'barangay' => 'San Lorenzo', 'city' => 'Makati City',
        'province' => 'Metro Manila', 'zip_code' => '1223', 'passport_number' => 'GB1234567', 'passport_validity' => '2031-06-30',
        'latest_arrival_date' => '2026-09-20', 'flight_number' => 'PR 721', 'checklist' => ['cgaf', 'passport'], 'filed_on' => '2026-10-12',
    ], $changes);
}

function memoryApplication(User $officer, array $overrides = []): void
{
    test()->actingAs($officer)->post(route('srrv.applications.store'), $overrides + [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com',
        'retiree_phone' => '09170000000', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'investment_amount' => 1500,
    ])->assertRedirect();
}

test('an SRRV number on an application fills the next SRRV form, even one another officer opens', function () {
    memoryApplication(memoryOfficer(), ['srrv_card_number' => 'SRRV-777']);
    $client = User::where('role', 'client')->where('email', 'ramon@example.com')->firstOrFail();

    expect($client->srrv_number)->toBe('SRRV-777')->and($client->srrv_visa_class)->toBe('classic');

    $other = memoryOfficer();
    $this->actingAs($other)->get(route('srrv.applications.create', ['client' => $client->id]))->assertOk()->assertSee('value="SRRV-777"', false);
    $this->actingAs($other)->get(route('srrv.renewals.create', ['client' => $client->id]))->assertOk()->assertSee('value="SRRV-777"', false);
    $this->actingAs($other)->get(route('srrv.requests.create', ['type' => 'termination', 'client' => $client->id]))->assertOk()->assertSee('value="SRRV-777"', false);
});

test('the newest SRRV form wins: a later termination replaces the number', function () {
    $officer = memoryOfficer();
    memoryApplication($officer, ['srrv_card_number' => 'OLD-1']);

    $this->actingAs($officer)->post(route('srrv.requests.store'), [
        'type' => 'termination', 'retiree_name' => 'Ramon Cruz', 'retiree_email' => 'ramon@example.com', 'srrv_card_number' => 'NEW-2', 'visa_class' => 'classic',
        'details' => ['reason' => 'voluntary', 'members' => 1, 'with_downgrading' => 0, 'withdraw_deposit' => 0, 'tax_free_importation' => 0, 'via_representative' => 0],
        'checklist' => [],
    ])->assertRedirect();

    expect(User::where('email', 'ramon@example.com')->value('srrv_number'))->toBe('NEW-2');
});

test('a client with no email or phone never gets another retiree\'s number', function () {
    memoryApplication(memoryOfficer(), ['srrv_card_number' => 'SOMEONE-ELSE']);
    $bare = User::factory()->create(['role' => 'client', 'name' => 'Bare Client', 'email' => 'bare.'.uniqid().User::PLACEHOLDER_EMAIL_DOMAIN, 'phone' => null]);

    $this->actingAs(memoryOfficer())->get(route('srrv.renewals.create', ['client' => $bare->id]))->assertOk()->assertDontSee('SOMEONE-ELSE');
});

test('a renewed passport replaces the old one; the name is never changed', function () {
    $client = User::factory()->create(['role' => 'client', 'name' => 'Ana Reyes', 'passport_number' => 'P1', 'passport_expiry' => '2027-01-01']);

    ClientMemory::remember($client, ['name' => 'Someone Else', 'passport_number' => 'P2', 'passport_expiry' => '2036-05-05', 'gender' => 'F', 'phone' => '  ']);

    $client->refresh();
    expect($client->name)->toBe('Ana Reyes')
        ->and($client->passport_number)->toBe('P2')
        ->and($client->passport_expiry->toDateString())->toBe('2036-05-05')
        ->and($client->gender)->toBe('female');
});

test('an email someone else has, or a made-up one, is never taken', function () {
    User::factory()->create(['role' => 'agent', 'email' => 'taken@example.com']);
    $client = User::factory()->create(['role' => 'client', 'email' => 'mine@example.com']);

    ClientMemory::remember($client, ['email' => 'taken@example.com']);
    ClientMemory::remember($client, ['email' => 'x'.User::PLACEHOLDER_EMAIL_DOMAIN]);

    expect($client->fresh()->email)->toBe('mine@example.com');

    // Staff records are never touched.
    $staff = User::factory()->create(['role' => 'srrv']);
    ClientMemory::remember($staff, ['srrv_number' => 'X']);
    expect($staff->fresh()->srrv_number)->toBeNull();
});

test('the ACR and SSRN on a CGAF fill the next CGAF picked from the client search', function () {
    $officer = memoryOfficer('visa_assistance');
    $this->actingAs($officer)->post(route('visa.tourist-visa.store'), memoryCgaf([
        'acr_number' => 'E123456', 'acr_validity' => '2027-08-01', 'ssrn' => 'SSRN-99',
    ]))->assertRedirect();

    $client = User::where('role', 'client')->where('email', 'john.smith@example.com')->firstOrFail();
    expect($client->acr_number)->toBe('E123456')->and($client->ssrn)->toBe('SSRN-99')->and($client->place_of_birth)->toBe('London');

    $fields = $this->actingAs(memoryOfficer('visa_assistance'))->getJson(route('visa.tourist-visa.clients', ['q' => 'GB1234567']))->json('clients.0.fields');
    expect($fields)->toMatchArray(['acr_number' => 'E123456', 'acr_validity' => '2027-08-01', 'ssrn' => 'SSRN-99', 'birthplace' => 'London']);
});

test('the backfill copies the latest SRRV number from existing files', function () {
    $client = User::factory()->create(['role' => 'client', 'name' => 'Lito Santos', 'email' => 'lito@example.com']);
    $file = SrrvApplication::withoutEvents(fn () => SrrvApplication::withoutGlobalScopes()->forceCreate([
        'reference' => 'SRRV-TEST-1', 'service_type' => 'renewal_application', 'visa_class' => 'courtesy', 'retiree_name' => 'Lito Santos',
        'retiree_email' => 'lito@example.com', 'srrv_card_number' => 'BF-555', 'investment_amount' => 1500,
    ]));

    ClientMemory::backfill();

    expect($file->exists)->toBeTrue()
        ->and($client->fresh()->srrv_number)->toBe('BF-555')
        ->and($client->fresh()->srrv_visa_class)->toBe('courtesy');
});
