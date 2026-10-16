<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\DocumentStorage;
use App\Support\ImmigrationCases;
use App\Support\ImmigrationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function dualOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function dualSheet(): ImmigrationClient
{
    return ImmigrationClient::create([
        'last_name' => 'Garcia-Miller', 'given_name' => 'Maria', 'passport_number' => '567891234', 'nationality' => 'American',
        'civil_status' => 'Married', 'date_of_birth' => '1975-08-20', 'mobile_number' => '09171234567',
        'address' => '45 Mabini St, Angeles City', 'visa_expiry_date' => now()->addMonth()->toDateString(),
    ]);
}

test('Dual Citizenship is open in the services menu', function () {
    $service = ImmigrationServices::find('dual_citizenship');

    expect($service['available'])->toBeTrue();
    $this->actingAs(dualOfficer())->get($service['url'])->assertOk()->assertSee('New Dual Citizenship File');
});

test('a dual citizenship folder shows the petition steps and no ledger', function () {
    $sheet = dualSheet();

    $this->actingAs(dualOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'dual_citizenship']))
        ->assertOk()
        ->assertSee('Petition Details')->assertSee('BI Requirements')->assertSee('Proof of natural-born citizenship')
        ->assertDontSee('Extension Ledger')
        ->assertSee('R.A. 9225 petition');
});

test('saving a dual citizenship case keeps the petition and follows the proof of birth', function () {
    Storage::fake(DocumentStorage::diskName());
    $sheet = dualSheet();

    $this->actingAs(dualOfficer())->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'dual_citizenship',
        'last_name' => $sheet->last_name, 'given_name' => $sheet->given_name,
        'petition' => [
            'details' => [
                'name_ph' => 'Maria Santos Garcia', 'sex' => 'F', 'spouse_name' => 'Robert Miller',
                'father_name' => 'Jose Garcia', 'father_citizenship' => 'Filipino', 'father_acquired' => 'birth',
                'mother_name' => 'Ana Santos', 'mother_citizenship' => 'Filipino', 'mother_acquired' => 'birth', 'parents_married' => '1',
                'lost_country' => 'United States of America', 'lost_on' => '2001-07-04',
                'birth_proof' => 'lcr', 'registered_alien' => '0', 'name_variance' => '1', 'by_representative' => '0',
            ],
            'checklist' => ['petition_form', 'photos'],
            'files' => ['birth_lcr' => UploadedFile::fake()->create('lcr.pdf', 40, 'application/pdf')],
        ],
    ])->assertRedirectContains('service=dual_citizenship');

    $case = $sheet->fresh()->openPetition('dual');
    expect($case->reference())->toStartWith('DC-')
        ->and($case->birthProof())->toBe('lcr')
        ->and(array_keys($case->requiredItems()))->toBe(['petition_form', 'photos', 'birth_lcr', 'foreign_passport', 'naturalization', 'name_variance'])
        ->and(array_keys($case->missingItems()))->toBe(['foreign_passport', 'naturalization', 'name_variance']);

    expect(ImmigrationCases::parseReference($case->reference()))->toBe(['kind' => 'dual', 'id' => $case->id]);
});

test('the petition prints filled from the folder, with the applicable choices underlined', function () {
    $sheet = dualSheet();
    ImmigrationPetition::create([
        'immigration_client_id' => $sheet->id,
        'type' => 'dual',
        'details' => [
            'name_ph' => 'Maria Santos Garcia', 'sex' => 'F', 'spouse_name' => 'Robert Miller', 'birth_place' => 'Angeles, Pampanga',
            'father_name' => 'Jose Garcia', 'father_citizenship' => 'Filipino', 'father_acquired' => 'birth',
            'mother_name' => 'Ana Santos', 'mother_citizenship' => 'Filipino', 'mother_acquired' => 'birth', 'parents_married' => true,
            'lost_country' => 'United States of America', 'lost_on' => '2001-07-04', 'passport_country' => 'American',
            'dep_1_name' => 'Miller, Anna', 'dep_1_sex' => 'F', 'dep_1_dob' => '2015-02-01',
        ],
        'checklist' => ['petition_form', 'birth_psa'],
    ]);

    $this->actingAs(dualOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'dual']))->assertOk()
        ->assertSee('BI FORM 2014-01-005 Rev 0')
        ->assertSee('MARIA SANTOS GARCIA')->assertSee('MARIA GARCIA-MILLER')->assertSee('Robert Miller')
        ->assertSee('20 AUG 1975')->assertSee('04 JUL 2001')->assertSee('Miller, Anna')
        ->assertSee('<u class="chosen">female</u>', false)
        ->assertSee('<u class="chosen">married</u>', false);

    $checklist = $this->actingAs(dualOfficer())->get(route('admin.client-sheets.petition-checklist', [$sheet, 'dual']))->assertOk()
        ->assertSee('BSI03.QF.001')->assertSee('MARIA SANTOS GARCIA')->getContent();
    // The petition form, item 3 and its option a.
    expect(substr_count($checklist, '<span class="fill">✓</span>'))->toBe(3);

    foreach (['form', 'checklist'] as $form) {
        $this->actingAs(dualOfficer())->get(route('admin.client-sheets.petition-blank', ['dual', $form]))->assertOk()->assertDontSee('GARCIA');
    }
    $this->actingAs(dualOfficer())->get(route('admin.immigration.dashboard'))->assertSee(route('admin.client-sheets.petition-blank', ['dual', 'form']), false);
});

test('dual citizenship cases appear on the cases page', function () {
    $sheet = dualSheet();
    $case = ImmigrationPetition::create(['immigration_client_id' => $sheet->id, 'type' => 'dual']);

    $this->actingAs(dualOfficer())->get(route('admin.immigration.cases', ['service' => 'dual']))
        ->assertOk()->assertSee($case->reference())->assertSee('Dual Citizenship');
});
