<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use App\Support\DocumentStorage;
use App\Support\ImmigrationServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function restampingOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function restampingSheet(): ImmigrationClient
{
    return ImmigrationClient::create([
        'last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB7654321', 'nationality' => 'British',
        'date_of_birth' => '1985-03-14', 'mobile_number' => '09171234567', 'email' => 'john@example.com',
        'address' => '12 Rizal St, Angeles City', 'visa_expiry_date' => now()->addMonth()->toDateString(),
    ]);
}

/** @return array<string, mixed> */
function restampingPayload(ImmigrationClient $sheet, array $restamping = []): array
{
    return [
        'service' => 're_stamping_amendment',
        'last_name' => $sheet->last_name,
        'given_name' => $sheet->given_name,
        'passport_number' => $sheet->passport_number,
        'nationality' => $sheet->nationality,
        'visa_expiry_date' => $sheet->visa_expiry_date?->toDateString(),
        'restamping' => $restamping + [
            'applications' => ['transfer'],
            'old_passport_number' => 'GB1111111',
            'arrival_date' => '2026-09-02',
            'flight_number' => 'PR 721',
            'reason' => 'Renewed passport at the British Embassy.',
        ],
    ];
}

test('Re Stamping Amendment is offered in the services menu', function () {
    $service = ImmigrationServices::find('re_stamping_amendment');

    expect($service['available'])->toBeTrue();

    $this->actingAs(restampingOfficer())->get($service['url'])->assertOk()->assertSee('New Re Stamping Amendment File');
});

test('a folder opened for re-stamping shows the admission steps and no ledger', function () {
    $sheet = restampingSheet();

    $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 're_stamping_amendment']))
        ->assertOk()
        ->assertSee('Admission Details')->assertSee('BI Requirements')
        ->assertDontSee('Extension Ledger')->assertDontSee('ACR I-Card file')
        ->assertSee('IRD application form')->assertDontSee('BI form (CGAF)')
        ->assertSee('New case: saved when you save the folder');
});

test('saving a re-stamping case records it without touching the ledger', function () {
    Storage::fake(DocumentStorage::diskName());
    $sheet = restampingSheet();
    $sheet->extensions()->create(['sequence' => 1, 'extension_date' => now()->subMonth()->toDateString(), 'amount_paid' => 3000]);

    $this->actingAs(restampingOfficer())->put(route('admin.client-sheets.update', $sheet), restampingPayload($sheet, [
        'passport_lost' => '1',
        'checklist' => ['cgaf', 'manifest'],
        'files' => ['certificate_of_travel' => UploadedFile::fake()->create('cot.pdf', 40, 'application/pdf')],
    ]))->assertRedirectContains('service=re_stamping_amendment');

    $case = $sheet->fresh()->openRestamping();
    expect($case->applications)->toBe(['transfer'])
        ->and($case->old_passport_number)->toBe('GB1111111')
        ->and($case->passport_lost)->toBeTrue()
        ->and($case->hasChecked('certificate_of_travel'))->toBeTrue()
        ->and(array_keys($case->missingItems()))->toBe(['new_passport']);

    // Re-stamping has no ledger step, so the ledger row on file stays.
    expect($sheet->extensions()->count())->toBe(1);

    $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.restamping-file', [$sheet, $case, 'certificate_of_travel']))->assertOk();
});

test('the application must be ticked', function () {
    $sheet = restampingSheet();

    $this->actingAs(restampingOfficer())->put(route('admin.client-sheets.update', $sheet), restampingPayload($sheet, ['applications' => []]))
        ->assertSessionHasErrors('restamping.applications');

    expect($sheet->restampings()->count())->toBe(0);
});

test('the requirements follow the applications and the boxes ticked', function () {
    $case = new ImmigrationRestamping(['applications' => ['failed_stamp']]);
    expect(array_keys($case->requiredItems()))->toBe(['amendment_form', 'passport', 'visa_page']);

    $case->balikbayan = true;
    $case->with_filipino_spouse = true;
    expect(array_keys($case->requiredItems()))->toBe(['amendment_form', 'passport', 'birth_certificate', 'marriage_contract']);

    $case->applications = ['transfer', 'amendment'];
    expect(array_keys($case->requiredItems()))->toBe(['cgaf', 'certificate_of_travel', 'manifest', 'old_passport', 'amendment_form', 'passport', 'birth_certificate', 'marriage_contract'])
        ->and($case->missingItems())->not->toHaveKey('old_passport');
});

test('the IRD form prints one character per box, filled from the folder', function () {
    $sheet = restampingSheet();
    ImmigrationRestamping::create([
        'immigration_client_id' => $sheet->id,
        'applications' => ['transfer'],
        'middle_name' => 'Paul',
        'old_passport_number' => 'GB1111111',
        'arrival_date' => '2026-09-02',
        'flight_number' => 'PR 721',
        'reason' => 'Renewed passport at the British Embassy.',
    ]);

    $html = $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.restamping-form', $sheet))->assertOk()
        ->assertSee('BI FORM NO. IRD01.QF.006 REV. 03')
        ->assertSee('Renewed passport at the British Embassy.')
        ->assertSee('JOHN SMITH')
        ->getContent();

    // Last name, flight number without its space, and the new passport from step 2.
    foreach (['S', 'M', 'I', 'T', 'H'] as $char) {
        expect($html)->toContain('<span class="fill">'.$char.'</span>');
    }
    expect($html)->toContain('<span class="fill">P</span></span><span class="cell"><span class="fill">R</span></span><span class="cell"><span class="fill">7</span>')
        ->and($html)->toContain('<span class="fill">S</span></span><span class="cell"><span class="fill">E</span></span><span class="cell"><span class="fill">P</span>')
        ->and(substr_count($html, '<span class="fill">✓</span>'))->toBe(1);
});

test('the checklists tick what the case holds, and print blank from the counter', function () {
    $sheet = restampingSheet();
    ImmigrationRestamping::create([
        'immigration_client_id' => $sheet->id,
        'applications' => ['transfer', 'amendment'],
        'checklist' => ['cgaf', 'manifest', 'passport'],
    ]);

    $transfer = $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.restamping-checklist', [$sheet, 'transfer']))->assertOk()
        ->assertSee('FOR TRANSFER OF ADMISSION STATUS FROM OLD/LOST/CANCELLED PASSPORT')->assertSee('SMITH, JOHN')->getContent();
    expect(substr_count($transfer, '<span class="fill">✓</span>'))->toBe(2);

    $amendment = $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.restamping-checklist', [$sheet, 'amendment']))->assertOk()
        ->assertSee('AMENDMENT/CORRECTION OF ADMISSION')->getContent();
    expect(substr_count($amendment, '<span class="fill">✓</span>'))->toBe(1);

    foreach (['form', 'transfer', 'amendment'] as $form) {
        $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.restamping-blank', $form))->assertOk()->assertDontSee('SMITH');
    }

    $this->actingAs(restampingOfficer())->get(route('admin.immigration.dashboard'))
        ->assertSee(route('admin.client-sheets.restamping-blank', 'form'), false);
});

test('a Visa Extension folder lists a re-stamping case in progress', function () {
    $sheet = restampingSheet();
    $case = ImmigrationRestamping::create(['immigration_client_id' => $sheet->id, 'applications' => ['amendment']]);

    $this->actingAs(restampingOfficer())->get(route('admin.client-sheets.edit', $sheet))
        ->assertSee($case->reference())->assertSee('Collecting requirements');
});
