<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\ImmigrationExpiries;
use App\Support\ImmigrationServices;

function expiringFolder(string $last, array $attributes = []): ImmigrationClient
{
    return ImmigrationClient::create(array_merge([
        'last_name' => $last, 'given_name' => 'Test', 'passport_number' => 'P'.fake()->unique()->numerify('#######'),
        'mobile_number' => '0917 123 4567',
    ], $attributes));
}

test('the visa call list shows visas expiring in 30 days and lapsed ones, with the service to start', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $soon = expiringFolder('Soonvisa', ['visa_expiry_date' => today()->addDays(5)]);
    $lapsed = expiringFolder('Lapsedvisa', ['visa_expiry_date' => today()->subDays(3)]);
    expiringFolder('Farvisa', ['visa_expiry_date' => today()->addDays(45)]);
    expiringFolder('Oldvisa', ['visa_expiry_date' => today()->subDays(200)]);

    $rows = ImmigrationExpiries::visas();
    expect($rows->pluck('folder.id')->all())->toBe([$lapsed->id, $soon->id])
        ->and($rows[0]['tone'])->toBe('rose')->and($rows[0]['action'])->toBe('Start overstay')
        ->and($rows[1]['tone'])->toBe('amber')->and($rows[1]['action'])->toBe('Start extension');

    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('Visas Expiring')
        ->assertSeeInOrder(['Lapsedvisa', 'Lapsed 3 days ago', 'Start overstay', 'Soonvisa', 'In 5 days', 'Start extension'])
        ->assertSee('href="'.e(ImmigrationServices::folderUrl($soon, 'visa_extension')).'"', false)
        ->assertSee('href="tel:09171234567"', false)
        ->assertDontSee('Farvisa')
        ->assertDontSee('Oldvisa');
});

test('the ACR list takes the latest card date on file and leaves out cards being renewed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    // From a 13(A) case's details.
    $fromCase = expiringFolder('Casecard');
    ImmigrationPetition::create(['immigration_client_id' => $fromCase->id, 'type' => 'permanent',
        'details' => ['acr_number' => 'E1234567', 'acr_expires_on' => today()->addDays(20)->toDateString()]]);

    // From the client's profile.
    $profile = User::factory()->create(['role' => 'client', 'acr_valid_until' => today()->subDays(10), 'acr_number' => 'E7654321']);
    $fromProfile = expiringFolder('Profilecard', ['user_id' => $profile->id]);

    // A later card on the profile wins over the old date on a case: not due.
    $renewedProfile = User::factory()->create(['role' => 'client', 'acr_valid_until' => today()->addYears(4)]);
    $later = expiringFolder('Latercard', ['user_id' => $renewedProfile->id]);
    ImmigrationPetition::create(['immigration_client_id' => $later->id, 'type' => 'spouse',
        'details' => ['acr_expires_on' => today()->addDays(10)->toDateString()]]);

    // A renewal under way: left out.
    $renewing = expiringFolder('Renewingcard');
    ImmigrationPetition::create(['immigration_client_id' => $renewing->id, 'type' => 'renewal',
        'details' => ['acr_expires_on' => today()->addDays(15)->toDateString()]]);

    $rows = ImmigrationExpiries::acrCards();
    expect($rows->pluck('folder.id')->all())->toBe([$fromProfile->id, $fromCase->id])
        ->and($rows[0]['tone'])->toBe('rose')->and($rows[1]['acr'])->toBe('E1234567');

    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('ACR I-Cards Expiring')
        ->assertSeeInOrder(['Profilecard', 'Expired 10 days ago', 'Casecard', 'ACR E1234567', 'In 20 days', 'Start renewal'])
        ->assertSee('href="'.e(ImmigrationServices::folderUrl($fromCase, 'permanent_13a_series')).'"', false)
        // Their cases are open, so their names show under Open Transactions; not as cards to renew.
        ->assertDontSee('href="'.e(ImmigrationServices::folderUrl($later, 'permanent_13a_series')).'"', false)
        ->assertDontSee('href="'.e(ImmigrationServices::folderUrl($renewing, 'permanent_13a_series')).'"', false);
});

test('the call lists show an empty state when nothing is running out', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee('No visa runs out in the next 30 days.')
        ->assertSee('No ACR I-Card on file expires in the next 60 days.');
});
