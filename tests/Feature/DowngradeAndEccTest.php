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

test('a new ECC file opens on an Overview, step 0, with who can apply and how', function () {
    $sheet = caseSheet();

    $html = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('Overview')->assertSee('Who can apply?')
        ->assertSee('An individual certifying that he/she is not in any of derogatory database, list or record of the Bureau')
        ->assertSee('How to apply?')->assertSee('Secure and fill out application form.')
        ->assertSee('Wait for the issuance of Order of Payment Slip (OPS).')
        ->assertSee('Acknowledge receipt by signing the duplicate copy of the Certification before releasing the original Certification and Official Receipt.')
        ->assertSee('Continue to')->getContent();

    expect($html)->toContain('step: 0, first: 0, last: 5')
        ->and(substr_count($html, 'class="w-6 h-6 rounded-full bg-primary/10'))->toBe(7);

    // Opened again with the case started, it goes straight to step 1; the overview stays one click back.
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', ['details' => ['ecc_kind' => 'ecc']]));
    expect($this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->getContent())
        ->toContain('step: 1, first: 0, last: 5');
});

test('the ECC place of birth and next destination are picked as country, province and city', function () {
    $sheet = caseSheet();

    $html = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('Place of Birth')->assertSee('Country of birth')->assertSee('Province / State')->assertSee('City / Municipality')
        ->assertSee('Next country of destination')->assertSee('Next city of destination')
        ->getContent();

    expect($html)->toContain('name="petition[details][country_of_birth]"')
        ->and($html)->toContain('name="petition[details][birth_province]"')
        ->and($html)->toContain('name="petition[details][birth_city]"')
        ->and($html)->toContain('name="petition[details][destination_country]"')
        ->and($html)->toContain('name="petition[details][destination_city]"')
        ->and($html)->toContain('Seoul')
        ->and($html)->not->toContain('name="petition[details][place_of_birth]"')
        ->and($html)->not->toContain('name="petition[details][destination]"');
});

test('saving an ECC composes the printed place of birth and destination from the picks', function () {
    $sheet = caseSheet();
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', ['details' => [
        'country_of_birth' => 'Philippines', 'birth_province' => 'Pampanga', 'birth_city' => 'Angeles City',
        'destination_country' => 'South Korea', 'destination_city' => 'Seoul',
    ]]))->assertRedirect();

    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->detail('place_of_birth'))->toBe('Angeles City, Pampanga, Philippines')
        ->and($case->detail('destination'))->toBe('Seoul, South Korea');

    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc']))->assertOk()
        ->assertSee('Angeles City, Pampanga, Philippines')->assertSee('Seoul, South Korea');

    // Typed for a country with no list, and a country with no city picked.
    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', ['details' => [
        'country_of_birth' => 'South Korea', 'birth_province' => 'Gyeongsangnam-do', 'birth_city' => 'Busan', 'destination_country' => 'Japan',
    ]]));
    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->detail('place_of_birth'))->toBe('Busan, Gyeongsangnam-do, South Korea')->and($case->detail('destination'))->toBe('Japan');
});

test('an ECC saved before the pickers keeps its place of birth and destination until new ones are picked', function () {
    $sheet = caseSheet();
    ImmigrationPetition::create(['immigration_client_id' => $sheet->id, 'type' => 'ecc', 'details' => ['place_of_birth' => 'Busan, South Korea', 'destination' => 'Seoul, South Korea']]);

    $this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))
        ->assertSee('On file:')->assertSee('Busan, South Korea')->assertSee('Seoul, South Korea');

    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', ['details' => ['sex' => 'M']]));
    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->detail('place_of_birth'))->toBe('Busan, South Korea')->and($case->detail('destination'))->toBe('Seoul, South Korea');

    $this->actingAs(caseOfficer())->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'ecc', ['details' => ['destination_country' => 'Japan', 'destination_city' => 'Tokyo']]));
    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->detail('destination'))->toBe('Tokyo, Japan')->and($case->detail('place_of_birth'))->toBe('Busan, South Korea');
});

test('Downgrading, Dual Citizenship, Overstaying and Visa Extension each open on an Overview, step 0', function () {
    $sheet = caseSheet();
    $expected = [
        'visa_downgrading' => ['Foreign Nationals who shall apply for the reversion of their immigration visa to temporary visitor/tourist', 'Claim the passport stamped with the downgraded visa.', 7],
        'dual_citizenship' => ['Former Philippine citizens who have been naturalized in another country', 'Take Oath of Allegiance.', 7],
        'visa_overstaying' => ['Have overstayed beyond the maximum allowable stay', 'Claim the approved MR from the Visa Extension Section', 6],
        'visa_extension' => ['Non-visa required tourists admitted initially for thirty (30) days', 'Claim the passport with the extension stamped.', 7],
    ];

    foreach ($expected as $service => [$who, $lastStep, $count]) {
        $html = $this->actingAs(caseOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => $service]))->assertOk()
            ->assertSee('Overview')->assertSee('Who can apply?')->assertSee($who)->assertSee('How to apply?')->assertSee($lastStep)
            ->getContent();

        expect($html)->toMatch('/step: 0, first: 0, last: \d+/')
            ->and(substr_count($html, 'class="w-6 h-6 rounded-full bg-primary/10'))->toBe($count);
    }
});

test('a started Downgrading, Dual Citizenship or Overstaying case, or a Visa Extension with a ledger row, opens on step 1', function () {
    $sheet = caseSheet();
    $officer = caseOfficer();

    $this->actingAs($officer)->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'visa_downgrading', ['details' => ['visa_category' => 'employment']]))->assertSessionHasNoErrors();
    $this->actingAs($officer)->put(route('admin.client-sheets.update', $sheet), casePayload($sheet, 'dual_citizenship', ['details' => ['name_ph' => 'Kim Minjun']]))->assertSessionHasNoErrors();
    $this->actingAs($officer)->put(route('admin.client-sheets.update', $sheet), ['service' => 'visa_overstaying', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'overstay' => ['expired_on' => '2026-09-01']])->assertSessionHasNoErrors();

    foreach (['visa_downgrading', 'dual_citizenship', 'visa_overstaying'] as $service) {
        expect($this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => $service]))->getContent())
            ->toMatch('/step: 1, first: 0, last: \d+/');
    }

    // Visa Extension has no case: a new file opens on the overview until the ledger has a row.
    expect($this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id]))->getContent())->toMatch('/step: 0, first: 0/');
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => now()->toDateString(), 'amount_paid' => 2930, 'duration' => '29 days']);
    expect($this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id]))->getContent())->toMatch('/step: 1, first: 0/');
});
