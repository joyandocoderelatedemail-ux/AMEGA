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

test('a courtesy file tracks the military service proof instead of police and pension', function () {
    $courtesy = openRetireeFile($this, srrvOfficer(), ['visa_class' => 'courtesy']);
    $classic = openRetireeFile($this, srrvOfficer(), ['visa_class' => 'classic']);

    expect($courtesy->requiredProofs())->toBe(['military_service_proof_received']);
    expect($classic->requiredProofs())->toBe(['police_clearance_received', 'pension_proof_received']);

    expect($courtesy->hasAllProofs())->toBeFalse();
    expect($classic->hasAllProofs())->toBeFalse();

    $courtesy->update(['military_service_proof_received' => true]);
    expect($courtesy->fresh()->hasAllProofs())->toBeTrue();

    // Classic is not satisfied by the courtesy proof.
    $classic->update(['military_service_proof_received' => true]);
    expect($classic->fresh()->hasAllProofs())->toBeFalse();

    $classic->update(['police_clearance_received' => true, 'pension_proof_received' => true]);
    expect($classic->fresh()->hasAllProofs())->toBeTrue();
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

    // Category: classic, nothing more to check.
    $advance();
    expect($application->fresh()->status)->toBe('requirements');

    // Requirements: the PRA checklist on file, and the classic proofs.
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.documents.store', $application), [
        'document_type' => 'pra_checklist',
        'file' => UploadedFile::fake()->create('checklist.pdf', 50, 'application/pdf'),
    ]);
    $record(['police_clearance_received' => 1, 'pension_proof_received' => 1]);
    $advance();
    expect($application->fresh()->status)->toBe('investment');

    // Investment amount.
    $advance()->assertSessionHas('error');
    $record(['investment_amount' => 10000]);
    $advance();
    expect($application->fresh()->status)->toBe('supporting');

    // Additional supporting documents confirmed.
    $advance()->assertSessionHas('error');
    $record();
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

test('courtesy is only for retirees aged 50 and above', function () {
    $officer = srrvOfficer();
    $young = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'date_of_birth' => now()->subYears(45)->format('Y-m-d')]);
    $undated = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'date_of_birth' => null]);
    $eligible = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'date_of_birth' => now()->subYears(55)->format('Y-m-d')]);

    $this->actingAs($officer)->post(route('srrv.applications.advance', $young))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.advance', $undated))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.advance', $eligible));

    expect($young->fresh()->status)->toBe('pending')
        ->and($undated->fresh()->status)->toBe('pending')
        ->and($eligible->fresh()->status)->toBe('requirements');
});

test('a courtesy file needs proof of military service', function () {
    $application = openRetireeFile($this, srrvOfficer(), ['visa_class' => 'courtesy']);

    expect(collect($application->documentChecklist())->pluck('label')->all())
        ->toBe(['Standard PRA checklist', 'Proof of military service']);
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
    openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'retiree_name' => 'Courtesy Retiree']);

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
        ->assertSee('Pipeline')
        ->assertSee('Class Paperwork')
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
