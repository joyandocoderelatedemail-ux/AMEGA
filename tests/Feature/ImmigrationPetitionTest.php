<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Support\BoxedForm;
use App\Support\DocumentStorage;
use App\Support\ImmigrationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function petitionOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function petitionSheet(): ImmigrationClient
{
    return ImmigrationClient::create([
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB7654321', 'nationality' => 'British',
        'civil_status' => 'Married', 'height' => '175 cm', 'weight' => '70 kg',
        'date_of_birth' => '1985-03-14', 'mobile_number' => '09171234567', 'email' => 'john@example.com',
        'address' => '12 Rizal St, Balibago, Angeles City, Pampanga 2009', 'visa_expiry_date' => now()->addMonth()->toDateString(),
    ]);
}

/** @return array<string, mixed> */
function petitionPayload(ImmigrationClient $sheet, string $service, array $petition = []): array
{
    return [
        'service' => $service,
        'last_name' => $sheet->last_name,
        'given_name' => $sheet->given_name,
        'passport_number' => $sheet->passport_number,
        'nationality' => $sheet->nationality,
        'visa_expiry_date' => $sheet->visa_expiry_date?->toDateString(),
        'petition' => $petition,
    ];
}

test('the three 13(A) services are open in the services menu', function (string $key, string $title) {
    $service = ImmigrationServices::find($key);

    expect($service['available'])->toBeTrue();
    $this->actingAs(petitionOfficer())->get($service['url'])->assertOk()->assertSee($title);
})->with([
    ['13a_spouse', 'New SEC 13/A Spouse Visa Application File'],
    ['13a_permanent', 'New SEC 13/A Permanent File'],
    ['permanent_13a_series', 'New Permanent Sec 13/A Series File'],
]);

test('a probationary folder shows the 13(A) steps and no ledger', function () {
    $sheet = petitionSheet();

    $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => '13a_spouse']))
        ->assertOk()
        ->assertSee('Application Details')->assertSee('Petitioner &amp; ACR', false)->assertSee('BI Requirements')
        ->assertDontSee('Extension Ledger')
        ->assertSee('Immigrant visa CGAF')->assertSee('New case: saved when you save the folder');

    $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'permanent_13a_series']))
        ->assertOk()->assertSee('ACR I-Card Renewal')->assertDontSee('Petitioner &amp; ACR', false)->assertSee('ACR I-Card renewal form');
});

test('saving a probationary case keeps its details, checklist and files, and leaves the ledger alone', function () {
    Storage::fake(DocumentStorage::diskName());
    $sheet = petitionSheet();
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => now()->subMonth()->toDateString(), 'amount_paid' => 3000]);

    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, '13a_spouse', [
        'details' => [
            'application_kind' => 'conversion',
            'present_status' => '9(a) Temporary Visitor',
            'petitioner_last' => 'Dela Cruz', 'petitioner_first' => 'Maria', 'petitioner_middle' => 'Santos',
            'arrival_date' => '2026-03-01',
            'nbi_needed' => '1', 'annex_a' => '0', 'by_representative' => '0',
            'representative_name' => 'Should be dropped',
        ],
        'checklist' => ['joint_letter', 'cgaf'],
        'files' => ['marriage_certificate' => UploadedFile::fake()->create('psa.pdf', 40, 'application/pdf')],
    ]))->assertRedirectContains('service=13a_spouse');

    $case = $sheet->fresh()->openPetition('spouse');
    expect($case->detail('petitioner_last'))->toBe('Dela Cruz')
        ->and($case->detail('representative_name'))->toBeNull()
        ->and($case->flag('nbi_needed'))->toBeTrue()
        ->and($case->hasChecked('marriage_certificate'))->toBeTrue()
        ->and(array_keys($case->missingItems()))->toBe(['spouse_birth_certificate', 'passport_copy', 'nbi_clearance', 'bi_clearance'])
        ->and($sheet->extensions()->count())->toBe(1);

    $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.petition-file', [$sheet, $case, 'marriage_certificate']))->assertOk();

    // A Visa Extension folder points to it.
    $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.edit', $sheet))->assertSee($case->reference())->assertSee('Collecting requirements');
});

test('each 13(A) type keeps its own case', function () {
    $sheet = petitionSheet();

    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, '13a_spouse', ['details' => ['present_status' => '9A']]));
    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, 'permanent_13a_series', ['details' => ['transactions' => ['renewal'], 'acr_number' => 'E123456789']]));
    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, '13a_spouse', ['details' => ['present_status' => '9A Tourist']]));

    expect($sheet->petitions()->count())->toBe(2)
        ->and($sheet->openPetition('spouse')->detail('present_status'))->toBe('9A Tourist')
        ->and($sheet->openPetition('renewal')->detail('acr_number'))->toBe('E123456789');
});

test('the requirements follow the boxes ticked', function () {
    $spouse = new ImmigrationPetition(['type' => 'spouse', 'details' => []]);
    expect(array_keys($spouse->requiredItems()))->toBe(['joint_letter', 'cgaf', 'marriage_certificate', 'spouse_birth_certificate', 'passport_copy', 'bi_clearance']);

    $spouse->details = ['nbi_needed' => true, 'annex_a' => true, 'by_representative' => true];
    expect(array_keys($spouse->requiredItems()))->toContain('nbi_clearance', 'quarantine_clearance', 'spa');

    $permanent = new ImmigrationPetition(['type' => 'permanent', 'details' => []]);
    expect(array_keys($permanent->requiredItems()))->toBe(['joint_letter', 'cgaf', 'cohabitation_affidavit', 'passport_copy', 'nbi_clearance', 'bi_clearance']);

    $renewal = new ImmigrationPetition(['type' => 'renewal', 'details' => []]);
    expect(array_keys($renewal->requiredItems()))->toBe(['application_form', 'acr_card', 'passport_copy', 'boc_order']);
});

test('the immigrant visa CGAF prints both pages filled from the folder', function () {
    $sheet = petitionSheet();
    ImmigrationPetition::create([
        'immigration_client_id' => $sheet->id,
        'type' => 'spouse',
        'details' => [
            'application_kind' => 'conversion', 'present_status' => '9A', 'sex' => 'M',
            'petitioner_last' => 'Dela Cruz', 'petitioner_first' => 'Maria',
            'reference_name' => 'Reyes Jose', 'acr_number' => 'E12',
        ],
    ]);

    $html = $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'spouse']))->assertOk()
        ->assertSee('CONSOLIDATED GENERAL APPLICATION FORM FOR IMMIGRANT VISA')
        ->assertSee('Page <b>2</b> of <b>2</b>', false)
        ->assertSee('MARIA DELA CRUZ')->assertSee('JOHN SMITH')
        ->getContent();

    expect($html)->toContain((string) BoxedForm::boxes('Smith', 42))
        ->and($html)->toContain((string) BoxedForm::boxes('Dela Cruz', 42))
        ->and($html)->toContain((string) BoxedForm::date(Carbon::parse('1985-03-14')));
});

test('the renewal form and the checklists print, and blank from the counter', function () {
    $sheet = petitionSheet();
    ImmigrationPetition::create([
        'immigration_client_id' => $sheet->id,
        'type' => 'renewal',
        'details' => ['transactions' => ['renewal', 'lost'], 'acr_number' => 'E123456789', 'leaving_soon' => false],
        'checklist' => ['application_form', 'boc_order'],
    ]);

    $html = $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.petition-form', [$sheet, 'renewal']))->assertOk()
        ->assertSee('BI FORM 2014-08-010 Rev 0')->getContent();
    expect($html)->toContain((string) BoxedForm::boxes('E123456789', 14))
        ->and(substr_count($html, '<span class="tick">✓</span>'))->toBe(4); // renewal, lost, married, NO

    $checklist = $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.petition-checklist', [$sheet, 'renewal']))->assertOk()
        ->assertSee('BI FORM 2014-08-008 Rev 0')->assertSee('SMITH, JOHN')->getContent();
    expect(substr_count($checklist, '<span class="fill">✓</span>'))->toBe(2);

    foreach (['spouse', 'permanent', 'renewal'] as $type) {
        foreach (['form', 'checklist'] as $form) {
            $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.petition-blank', [$type, $form]))->assertOk()->assertDontSee('SMITH');
        }
    }

    $this->actingAs(petitionOfficer())->get(route('admin.immigration.dashboard'))
        ->assertSee(route('admin.client-sheets.petition-blank', ['renewal', 'form']), false);
});

test('boxes hold one capital per box and drop accents', function () {
    expect((string) BoxedForm::boxes('Muñoz', 6))->toBe('<span class="cells "><span class="cell">M</span><span class="cell">U</span><span class="cell">N</span><span class="cell">O</span><span class="cell">Z</span><span class="cell"></span></span>');
});

test('save and print opens the service form, not the client information sheet', function () {
    $sheet = petitionSheet();

    $response = $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, '13a_spouse', ['details' => ['present_status' => '9A']]) + ['then' => 'print']);
    $case = $sheet->fresh()->openPetition('spouse');
    $response->assertRedirect(route('admin.client-sheets.petition-form', ['clientSheet' => $sheet, 'type' => 'spouse', 'case' => $case->id, 'autoprint' => 1]));

    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, 'visa_overstaying', []) + ['then' => 'print'])
        ->assertRedirectContains('/reconsideration');

    // Visa Extension still prints the client sheet.
    $this->actingAs(petitionOfficer())->put(route('admin.client-sheets.update', $sheet), petitionPayload($sheet, 'visa_extension', []) + ['then' => 'print'])
        ->assertRedirect(route('admin.client-sheets.print', ['clientSheet' => $sheet, 'autoprint' => 1]));

    $this->actingAs(petitionOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => '13a_spouse']))
        ->assertSee('Save &amp; print CGAF', false)->assertSee('Client information sheet')->assertDontSee('Print last saved');
});
