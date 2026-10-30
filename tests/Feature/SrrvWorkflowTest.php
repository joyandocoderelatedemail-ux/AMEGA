<?php

use App\Models\SrrvApplication;
use App\Models\SrrvApplicationDocument;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Support\DocumentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(fn () => autoApproveDeskFiles());

function srrvOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

/**
 * Open a retiree file through the real intake endpoint.
 *
 * @param  array<string, mixed>  $overrides
 */
function openRetireeFile(TestCase $test, User $officer, array $overrides = []): SrrvApplication
{
    $test->actingAs($officer)->post('/srrv/applications', array_merge([
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com',
        'retiree_phone' => '+63 900 111 2222',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'Filipino',
        'srrv_card_number' => 'SRRV-00123',
        'investment_amount' => 1500,
        'service_fee' => 1000,
        'amount_paid' => 1000,
    ], $overrides))->assertRedirect();

    return SrrvApplication::latest('id')->firstOrFail();
}

// ---------------------------------------------------------------------------
// Intake
// ---------------------------------------------------------------------------

test('srrv officer can open a classic retiree file', function () {
    $application = openRetireeFile($this, srrvOfficer());

    expect($application->reference)->toStartWith('SRV-');
    expect($application->visa_class)->toBe('classic');
    expect($application->status)->toBe('pending');
    expect($application->copies_submitted)->toBe(4);
    expect($application->isCourtesy())->toBeFalse();
});

test('the requirements follow the PRA list for each option, pension and age', function () {
    $officer = srrvOfficer();
    $basic = ['passport', 'application_form', 'medical_certificate', 'police_clearance', 'bi_clearance', 'nbi_clearance', 'visa_deposit', 'id_photos'];
    $types = fn (SrrvApplication $file) => collect($file->documentChecklist())->pluck('type')->all();
    $required = fn (SrrvApplication $file) => collect($file->documentChecklist())->where('required', true)->pluck('type')->all();

    $classic = openRetireeFile($this, $officer);
    $pensioner = openRetireeFile($this, $officer, ['is_pensioner' => 1]);
    $diplomat45 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => now()->subYears(45)->format('Y-m-d')]);
    $diplomat60 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national']);
    $formerFilipino = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'former_filipino', 'is_pensioner' => 1]);

    expect($types($classic))->toBe($basic)
        ->and($types($pensioner))->toBe([...$basic, 'pension_proof'])
        ->and($types($diplomat45))->toBe([...$basic, 'pension_proof', 'employment_certificate', 'military_service_proof'])
        ->and($types($diplomat60))->toBe([...$basic, 'employment_certificate', 'military_service_proof'])
        ->and($types($formerFilipino))->toBe([...$basic, 'former_filipino_proof'])
        // NBI clearance and military proof are only sometimes needed, so they never hold the file.
        ->and($required($diplomat60))->not->toContain('nbi_clearance', 'military_service_proof')
        // A former Filipino's deposit is the same with or without a pension.
        ->and($formerFilipino->is_pensioner)->toBeFalse();

    // A dependent adds proof of relationship.
    $family = openRetireeFile($this, $officer, ['form' => ['spouse_name' => 'Maria Cruz']]);
    expect($required($family))->toContain('relationship_proof');

    // Ticks count as received; the old police, pension and military ticks stay in step.
    $pensioner->update(SrrvApplication::tickAttributes(array_diff([...$basic, 'pension_proof'], ['nbi_clearance'])));
    expect($pensioner->fresh()->hasAllProofs())->toBeTrue()
        ->and($pensioner->fresh()->police_clearance_received)->toBeTrue()
        ->and($pensioner->fresh()->pension_proof_received)->toBeTrue();
});

test('the deposit and fees follow the PRA tables', function () {
    $officer = srrvOfficer();
    $fees = fn (array $overrides) => openRetireeFile($this, $officer, $overrides)->praFees();
    $at = fn (int $age) => now()->subYears($age)->format('Y-m-d');

    expect($fees(['date_of_birth' => $at(62)])['deposit'])->toBe(30000.0)
        ->and($fees(['date_of_birth' => $at(45)])['deposit'])->toBe(50000.0)
        ->and($fees(['date_of_birth' => $at(45), 'is_pensioner' => 1])['deposit'])->toBe(25000.0)
        ->and($fees(['date_of_birth' => $at(62), 'is_pensioner' => 1])['deposit'])->toBe(15000.0)
        ->and($fees(['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => $at(45)])['deposit'])->toBe(6000.0)
        ->and($fees(['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => $at(45), 'is_pensioner' => 1])['deposit'])->toBe(3000.0)
        ->and($fees(['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => $at(55)])['deposit'])->toBe(1500.0)
        ->and($fees(['visa_class' => 'courtesy', 'courtesy_type' => 'former_filipino', 'date_of_birth' => $at(45)])['deposit'])->toBe(3000.0)
        ->and($fees(['visa_class' => 'courtesy', 'courtesy_type' => 'former_filipino', 'date_of_birth' => $at(45)])['annual_fee'])->toBe(50.0);

    // Three dependents: one beyond two adds USD 15,000 deposit and USD 100 to the Classic annual fee.
    $family = $fees(['date_of_birth' => $at(62), 'form' => ['spouse_name' => 'Maria', 'child_1_name' => 'Ana', 'child_2_name' => 'Ben']]);
    expect($family)->toBe(['deposit' => 45000.0, 'annual_fee' => 460.0, 'processing_fee' => 2400.0, 'dependents' => 3]);
});

test('a retiree file requires a name and a class', function () {
    $this->actingAs(srrvOfficer())
        ->post('/srrv/applications', [
            'service_type' => 'renewal_application',
        ])
        ->assertSessionHasErrors(['visa_class', 'retiree_name']);
});

// ---------------------------------------------------------------------------
// Pipeline
// ---------------------------------------------------------------------------

test('a new SRRV application advances only as each stage is done', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer, ['investment_amount' => null, 'service_fee' => 1000, 'amount_paid' => 0]);
    $advance = fn () => $this->actingAs($officer)->post(route('srrv.applications.advance', $application));
    $record = fn (array $data = []) => $this->actingAs($officer)->post(route('srrv.applications.stage', $application), $data);

    // Application: the wizard's work. Every required PRA document in (filed or ticked), then the deposit.
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'Still missing'));
    $this->actingAs($officer)->post(route('srrv.applications.documents.store', $application), [
        'document_type' => 'passport',
        'file' => UploadedFile::fake()->create('passport.pdf', 50, 'application/pdf'),
    ]);
    $application->update(SrrvApplication::tickAttributes(['application_form', 'medical_certificate', 'police_clearance', 'bi_clearance', 'visa_deposit']));
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'ID photos'));
    $application->update(SrrvApplication::tickAttributes(['application_form', 'medical_certificate', 'police_clearance', 'bi_clearance', 'visa_deposit', 'id_photos']));
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'investment amount'));
    $application->update(['investment_amount' => 30000]);
    $advance();
    expect($application->fresh()->status)->toBe('documentation');

    // Documentation: lodged with PRA.
    $advance()->assertSessionHas('error');
    $record(['lodged_at' => now()->toDateString(), 'pra_reference' => 'PRA-7781']);
    $advance();
    expect($application->fresh()->status)->toBe('oath');

    // Oath taking.
    $advance()->assertSessionHas('error');
    $record(['oath_at' => now()->toDateString()]);
    $advance();
    expect($application->fresh()->status)->toBe('awaiting_release');

    // Release: fully paid only.
    $this->actingAs($officer)->post(route('srrv.applications.payments', $application), ['amount' => 400]);
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.payments', $application), ['amount' => 600]);
    $advance();

    $application->refresh();
    expect($application->status)->toBe('released')
        ->and($application->pra_reference)->toBe('PRA-7781')
        ->and($application->oath_at)->not->toBeNull()
        ->and($application->payment_in_full_at)->not->toBeNull()
        ->and($application->released_at)->not->toBeNull();
});

test('the PRA requires a principal aged 40 or older, and a Courtesy file says for whom', function () {
    $officer = srrvOfficer();
    $young = openRetireeFile($this, $officer, ['date_of_birth' => now()->subYears(38)->format('Y-m-d')]);
    $undated = openRetireeFile($this, $officer, ['date_of_birth' => null]);
    $courtesy45 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => now()->subYears(45)->format('Y-m-d')]);

    $this->actingAs($officer)->post(route('srrv.applications.advance', $young))->assertSessionHas('error', fn ($m) => str_contains($m, '40 or older'));
    $this->actingAs($officer)->post(route('srrv.applications.advance', $undated))->assertSessionHas('error', fn ($m) => str_contains($m, 'birth date'));
    // Old enough: what holds it now is only the paperwork.
    $this->actingAs($officer)->post(route('srrv.applications.advance', $courtesy45))->assertSessionHas('error', fn ($m) => str_contains($m, 'Still missing'));

    // Courtesy without saying for whom is refused.
    $this->actingAs($officer)->post('/srrv/applications', [
        'service_type' => 'renewal_application', 'visa_class' => 'courtesy', 'retiree_name' => 'No Type',
    ])->assertSessionHasErrors('courtesy_type');
});

test('re-stamping cannot be opened until its flow is defined', function () {
    $this->actingAs(srrvOfficer())->post('/srrv/applications', [
        'service_type' => 'restamping',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
    ])->assertSessionHasErrors('service_type');

    expect(SrrvApplication::count())->toBe(0);
});

test('a released file refuses to advance further', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $application->update(['status' => 'released']);

    $this->actingAs($officer)
        ->post(route('srrv.applications.advance', $application))
        ->assertSessionHas('error');

    expect($application->fresh()->status)->toBe('released');
});

test('a retiree file can be cancelled', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->post(route('srrv.applications.cancel', $application));

    expect($application->fresh()->status)->toBe('cancelled');
});

// ---------------------------------------------------------------------------
// Documents
// ---------------------------------------------------------------------------

test('srrv documents land on the private disk and never the public one', function () {
    Storage::fake('local');
    Storage::fake('public');

    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->post(route('srrv.applications.documents.store', $application), [
        'document_type' => 'police_clearance',
        'file' => UploadedFile::fake()->image('clearance.jpg'),
    ])->assertRedirect();

    $document = SrrvApplicationDocument::firstOrFail();

    expect($document->file_path)->toStartWith('srrv/');
    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('public')->assertMissing($document->file_path);
});

// ---------------------------------------------------------------------------
// Annual renewals
// ---------------------------------------------------------------------------

test('the renewal fee is derived from the visa class, not the form', function () {
    $officer = srrvOfficer();

    foreach (['classic' => 360.0, 'courtesy' => 10.0] as $class => $expected) {
        $this->actingAs($officer)->post('/srrv/renewals', [
            'visa_class' => $class,
            'retiree_name' => 'Ramon Dela Cruz',
            'years_paid' => 1,
            // The form tries to zero the fee. It must be ignored.
            'fee_amount' => 0,
        ])->assertRedirect();

        $renewal = SrrvRenewal::latest('id')->firstOrFail();

        expect((float) $renewal->fee_amount)->toBe($expected);
        expect($renewal->currency)->toBe('USD');
    }
});

test('a two year renewal is billed at twice the annual rate', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 2,
    ])->assertRedirect();

    expect((float) SrrvRenewal::firstOrFail()->fee_amount)->toBe(720.0);
});

test('a renewal cannot be paid more than two years ahead', function () {
    $this->actingAs(srrvOfficer())->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 3,
    ])->assertSessionHasErrors('years_paid');

    expect(SrrvRenewal::count())->toBe(0);
    expect(SrrvRenewal::withinPrepayCeiling(2))->toBeTrue();
    expect(SrrvRenewal::withinPrepayCeiling(3))->toBeFalse();
});

test('editing a renewal to a different class recomputes the fee', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    expect((float) $renewal->fee_amount)->toBe(360.0);

    $this->actingAs($officer)->put(route('srrv.renewals.update', $renewal), [
        'visa_class' => 'courtesy',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 2,
    ])->assertRedirect();

    expect((float) $renewal->fresh()->fee_amount)->toBe(20.0);
});

test('a renewal advances and the final step is collection in person', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();

    // The documents step first: ID + photocopy, online form, signature + thumb mark.
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.stage', $renewal), [
        'id_and_photocopy_received' => 1,
        'form_filled_online' => 1,
        'signature_thumbmark_taken' => 1,
    ]);

    foreach (['documented', 'email_sent', 'processing', 'ready_for_collection'] as $stage) {
        $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal));
        expect($renewal->fresh()->status)->toBe($stage);
    }

    $renewal->refresh();
    expect($renewal->signature_thumbmark_at)->not->toBeNull();
    expect($renewal->email_sent_at)->not->toBeNull();
    expect($renewal->processed_at)->not->toBeNull();
    expect($renewal->ready_at_pra_at)->not->toBeNull();
    expect($renewal->client_notified_at)->not->toBeNull();

    // The desk cannot step straight to collected; the collector must be named.
    $this->actingAs($officer)
        ->post(route('srrv.renewals.advance', $renewal))
        ->assertSessionHas('error');

    expect($renewal->fresh()->status)->toBe('ready_for_collection');
});

test('collecting a renewal records who took it', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'courtesy',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    $renewal->update(['status' => 'ready_for_collection']);

    // Not while the renewal fee is unpaid.
    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertSessionHas('error');
    expect($renewal->fresh()->status)->toBe('ready_for_collection');

    $this->actingAs($officer)->post(route('srrv.renewals.payments', $renewal), ['amount' => 10]);

    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertRedirect();

    $renewal->refresh();
    expect($renewal->status)->toBe('collected');
    expect($renewal->collected_by_name)->toBe('Ramon Dela Cruz');
    expect($renewal->collected_at)->not->toBeNull();
});

test('collecting requires the collector name', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    $renewal->update(['status' => 'ready_for_collection', 'amount_paid' => $renewal->fee_amount]);

    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), [])
        ->assertSessionHasErrors('collected_by_name');

    expect($renewal->fresh()->status)->toBe('ready_for_collection');
});

test('a renewal can be linked to a retiree file', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->post('/srrv/renewals', [
        'srrv_application_id' => $application->id,
        'visa_class' => 'classic',
        'retiree_name' => $application->retiree_name,
        'years_paid' => 1,
    ])->assertRedirect();

    $renewal = SrrvRenewal::firstOrFail();
    expect($renewal->srrv_application_id)->toBe($application->id);
    expect($application->renewals()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// Directory
// ---------------------------------------------------------------------------

test('the retiree file directory renders and filters by class', function () {
    $officer = srrvOfficer();
    openRetireeFile($this, $officer, ['visa_class' => 'classic', 'retiree_name' => 'Classic Retiree']);
    openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'retiree_name' => 'Courtesy Retiree']);

    $this->actingAs($officer)->get('/srrv/applications')
        ->assertOk()
        ->assertSee('Classic Retiree')
        ->assertSee('Courtesy Retiree');

    $this->actingAs($officer)->get('/srrv/applications?visa_class=courtesy')
        ->assertOk()
        ->assertSee('Courtesy Retiree')
        ->assertDontSee('Classic Retiree');
});

test('the retiree file page renders the pipeline and class paperwork', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->get(route('srrv.applications.show', $application))
        ->assertOk()
        ->assertSee($application->reference)
        ->assertSee('Ramon Dela Cruz')
        ->assertSee('Steps')
        ->assertSee('SRRV Classic')->assertSee('Visa deposit USD 30,000.00', false)
        ->assertSee('Document Checklist');
});

test('the renewal ledger renders', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ledger Retiree',
        'years_paid' => 1,
    ]);

    $this->actingAs($officer)->get('/srrv/renewals')
        ->assertOk()
        ->assertSee('Ledger Retiree')
        ->assertSee('Renewal Ledger');
});

// ---------------------------------------------------------------------------
// Isolation
// ---------------------------------------------------------------------------

test('the visa desk cannot reach any SRRV workflow route', function () {
    $srrv = srrvOfficer();
    $application = openRetireeFile($this, $srrv);

    $visa = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($visa)->get('/srrv/applications')
        ->assertRedirect(route('visa.dashboard'));
    $this->actingAs($visa)->get(route('srrv.applications.show', $application))
        ->assertRedirect(route('visa.dashboard'));
    $this->actingAs($visa)->post(route('srrv.applications.advance', $application))
        ->assertRedirect(route('visa.dashboard'));

    expect($application->fresh()->status)->toBe('pending');
});

test('an admin can work the SRRV desk', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/srrv/applications')->assertOk();
    $this->actingAs($admin)->get('/srrv/renewals')->assertOk();

    $this->actingAs($admin)->post('/srrv/applications', [
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Admin Opened',
    ])->assertRedirect();

    expect(SrrvApplication::where('retiree_name', 'Admin Opened')->exists())->toBeTrue();
});
