<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\ImmigrationCases;
use App\Support\ImmigrationServices;

function caseOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function caseSheet(): ImmigrationClient
{
    return ImmigrationClient::create([
        'last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M12345678', 'nationality' => 'Korean',
        'civil_status' => 'Married', 'date_of_birth' => '1988-07-02', 'mobile_number' => '0917 123 4567', 'email' => 'minjun@example.com',
        'address' => '12 Rizal St, Balibago, Angeles City, Pampanga 2009', 'visa_expiry_date' => now()->addMonth()->toDateString(),
    ]);
}

/** @return array<string, mixed> */
function casePayload(ImmigrationClient $sheet, string $service, array $petition = []): array
{
    return [
        'service' => $service, 'last_name' => $sheet->last_name, 'given_name' => $sheet->given_name,
        'passport_number' => $sheet->passport_number, 'nationality' => $sheet->nationality,
        'visa_expiry_date' => $sheet->visa_expiry_date?->toDateString(), 'petition' => $petition,
    ];
}

test('visa downgrading and ECC open from the services menu with their own steps', function (string $key, string $step, string $button) {
    $service = ImmigrationServices::find($key);
    expect($service['available'])->toBeTrue();

    $sheet = caseSheet();
    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => $key]))
        ->assertOk()->assertSee($step)->assertSee($button)->assertDontSee('Extension Ledger');
})->with([
    ['visa_downgrading', 'Downgrading Details', 'Letter request'],
    ['ecc', 'ECC Details', 'ECC application form'],
]);

test('a downgrade asks only for the document its visa category needs, and prints the letter and the BI checklist', function () {
    $sheet = caseSheet();

    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'visa_downgrading', [
        'details' => [
            'visa_category' => 'employment', 'present_status' => '9(G) Pre-Arranged Employee', 'acr_number' => 'E7654321',
            'downgrade_reason' => 'resignation', 'reason_detail' => 'resigned from ABC Corp. effective 30 September 2026', 'letter_by' => 'applicant',
            'by_representative' => '0',
        ],
        'checklist' => ['letter_request'],
    ]))->assertRedirectContains('service=visa_downgrading');

    $case = $sheet->fresh()->openPetition('downgrade');
    expect($case->reference())->toStartWith('DG-')
        ->and(array_keys($case->requiredItems()))->toBe(['letter_request', 'passport_copy', 'employment_cert'])
        ->and(array_keys($case->missingItems()))->toBe(['passport_copy', 'employment_cert']);

    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'downgrade']))->assertOk()
        ->assertSee('Request for Downgrading of Visa')->assertSee('MINJUN KIM')
        ->assertSee('by reason of <b class="fill-in">resigned from ABC Corp. effective 30 September 2026</b>', false)->assertSee('E7654321');

    $checklist = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-checklist', [$sheet, 'downgrade']))->assertOk()
        ->assertSee('BI FORM IRD01.QF.001 Rev 02')->assertSee('CLAIM STUB')->getContent();
    expect(substr_count($checklist, '<span class="chk">✓</span>'))->toBe(1);

    expect(ImmigrationCases::parseReference($case->reference()))->toBe(['kind' => 'downgrade', 'id' => $case->id]);

    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'visa_downgrading', ['details' => ['visa_category' => 'tourist']]))
        ->assertSessionHasErrors('petition.details.visa_category');
});

test('a company letter goes on its letterhead, and a marriage visa adds the spouse', function () {
    $sheet = caseSheet();
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'visa_downgrading', [
        'details' => ['visa_category' => 'missionary', 'letter_by' => 'company', 'company_name' => 'Grace Mission Inc.', 'company_signatory' => 'Rev. Jose Reyes'],
    ]));

    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'downgrade']))
        ->assertSee('Grace Mission Inc.')->assertSee('REV. JOSE REYES')->assertSee('petitioned by our congregation');

    $other = caseSheet();
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $other), casePayload($other, 'visa_downgrading', [
        'details' => ['visa_category' => 'marriage', 'spouse_name' => 'Ana Reyes'],
    ]));
    expect(array_keys($other->fresh()->openPetition('downgrade')->requiredItems()))->toContain('marriage_docs');
    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-form', [$other, 'downgrade']))->assertSee('ANA REYES')->assertSee('Spouse');
});

test('an ECC case prints the BI application form and the checklist', function () {
    $sheet = caseSheet();
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', [
        'details' => [
            'ecc_kind' => 'ecc', 'sex' => 'M', 'place_of_birth' => 'Busan, South Korea', 'present_status' => '9(A) Temporary Visitor',
            'arrival_date' => '2026-04-10', 'departure_date' => '2026-11-05', 'destination' => 'Seoul, South Korea', 'airline' => 'PR 468',
            'leaving_reason' => 'Returning home', 'registered_alien' => '0', 'by_representative' => '0',
        ],
        'checklist' => ['photos', 'passport_copy'],
    ]));

    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->reference())->toStartWith('ECC-')
        ->and($case->requiredItems())->not->toHaveKey('acr_card');

    $form = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc']))->assertOk()
        ->assertSee('EMIGRATION CLEARANCE CERTIFICATE (ECC) APPLICATION FORM')->assertSee('ARD04.QF.003')
        ->assertSee('Busan, South Korea')->assertSee('Seoul, South Korea')->assertSee('PR 468')->assertSee('Returning home')
        ->assertSee('KIM, MINJUN')->assertSee('Balibago')->getContent();
    expect($form)->toContain('<span class="tick">✓</span>Emigration Clearance Certificate (ECC)')
        ->and($form)->toContain('<span class="tick">✓</span>Married');

    $checklist = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-checklist', [$sheet, 'ecc']))->assertOk()
        ->assertSee('EMIGRATION CLEARANCE CERTIFICATE (ECC)')->assertSee('Four (4) pieces of 2x2 ID photos')
        ->assertSee('05 NOV 2026')->assertSee('(not applicable)', false)->getContent();
    expect(substr_count($checklist, '<span class="chk">✓</span>'))->toBe(2);
});

test('blank downgrading and ECC forms print from the counter', function () {
    foreach ([['downgrade', 'form'], ['downgrade', 'checklist'], ['ecc', 'form'], ['ecc', 'checklist']] as [$type, $form]) {
        $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-blank', [$type, $form]))->assertOk()->assertDontSee('MINJUN');
    }

    $this->actingAs(caseOfficer())->get(route('admin.immigration.dashboard'))
        ->assertSee(route('admin.client-sheets.petition-blank', ['downgrade', 'checklist']), false)
        ->assertSee(route('admin.client-sheets.petition-blank', ['ecc', 'form']), false);

    expect(ImmigrationPetition::SERVICES)->toHaveKeys(['visa_downgrading', 'ecc']);
});
