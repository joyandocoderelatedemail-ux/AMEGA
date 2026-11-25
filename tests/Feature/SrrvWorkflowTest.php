<?php

use App\Models\SrrvApplication;
use App\Models\SrrvApplicationDocument;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Notifications\ClientCaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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
    // A new application opens on Step 2: the wizard is Step 1, and nothing is sent to the admin yet.
    expect($application->status)->toBe('agreement');
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
    $pensionedDiplomat45 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'is_pensioner' => 1, 'date_of_birth' => now()->subYears(45)->format('Y-m-d')]);
    $diplomat60 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national']);
    $formerFilipino = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'former_filipino', 'is_pensioner' => 1]);

    expect($types($classic))->toBe($basic)
        ->and($types($pensioner))->toBe([...$basic, 'pension_proof'])
        // Courtesy aged 40 to 49: proof of pension only from a pensioner (it earns the USD 3,000 deposit instead of 6,000).
        ->and($types($diplomat45))->toBe([...$basic, 'employment_certificate', 'military_service_proof'])
        ->and($types($pensionedDiplomat45))->toBe([...$basic, 'pension_proof', 'employment_certificate', 'military_service_proof'])
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

test('the Courtesy visa deposit matches the PRA tables for foreign nationals and former Filipinos', function () {
    $officer = srrvOfficer();
    $deposit = fn (string $type, int $age, bool $pensioner = false) => openRetireeFile($this, $officer, [
        'visa_class' => 'courtesy', 'courtesy_type' => $type, 'date_of_birth' => now()->subYears($age)->format('Y-m-d'), 'is_pensioner' => $pensioner ? 1 : 0,
    ])->praFees()['deposit'];

    // Foreign nationals: USD 1,500 from 50; 40 to 49 is USD 3,000 for a pensioner and USD 6,000 for a non-pensioner.
    expect($deposit('foreign_national', 50))->toBe(1500.0)->and($deposit('foreign_national', 50, true))->toBe(1500.0)
        ->and($deposit('foreign_national', 49))->toBe(6000.0)->and($deposit('foreign_national', 49, true))->toBe(3000.0)
        // Former Filipinos: USD 1,500 from 50, USD 3,000 from 40 to 49, with or without a pension.
        ->and($deposit('former_filipino', 50))->toBe(1500.0)->and($deposit('former_filipino', 40))->toBe(3000.0)
        ->and($deposit('former_filipino', 45, true))->toBe(3000.0);
});

test('Step 3 fills the PRA visa deposit in on a new file, but never over an amount already saved', function () {
    $officer = srrvOfficer();

    // A new file: the amount follows the retiree's deposit until the agent types one of their own.
    $this->actingAs($officer)->get(route('srrv.applications.create', ['visa_class' => 'courtesy']))->assertOk()
        ->assertSee('invAuto: true', false)->assertDontSee('PRA visa deposit for this retiree')
        ->assertSee('@input="invAuto = false"', false)->assertSee('Enter the retiree\'s birth date on Step 2', false);

    // A saved file with its own amount keeps it: nothing overwrites it.
    $file = openRetireeFile($this, $officer, ['investment_amount' => 7777]);
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertOk()->assertSee('invAuto: false', false);

    // One saved without an amount (the deposit was not known yet) fills it in; once the admin has the file, it is locked.
    $blank = openRetireeFile($this, $officer, ['investment_amount' => 0]);
    $this->actingAs($officer)->get(route('srrv.applications.edit', $blank))->assertOk()->assertSee('invAuto: true', false);
    $blank->forceFill(['approval_status' => 'pending'])->save();
    $this->actingAs($officer)->get(route('srrv.applications.edit', $blank))->assertOk()->assertSee('invAuto: false', false);
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
    $officer = srrvOfficer();
    $cashier = User::factory()->create(['role' => 'cashier']);
    $application = openRetireeFile($this, $officer, ['service_fee' => 0, 'amount_paid' => 0]);
    $advance = fn () => $this->actingAs($officer)->post(route('srrv.applications.advance', $application));
    $record = fn (array $data = []) => $this->actingAs($officer)->post(route('srrv.applications.stage', $application), $data);

    // Step 2: the agreement signed, the fee, the admin, then the cashier.
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'signed the service agreement'));
    $record();
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'service fee'));
    $record(['service_fee' => 1000]);
    $this->actingAs($officer)->post(route('srrv.applications.send-to-admin', $application));
    $application->forceFill(['approval_status' => 'approved'])->save();
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'cashier'));
    $this->actingAs($cashier)->post(route('cashier.srrv.store', $application->id), ['amount' => 400, 'method' => 'cash', 'cashier_acknowledged' => 1]);
    $advance();
    expect($application->fresh()->status)->toBe('requirements');

    // Step 3: no fixed list; the signed Acknowledgment of Documents.
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'Acknowledgment'));
    $record();
    $advance();
    expect($application->fresh()->status)->toBe('pra');

    // Then the PRA Submission steps move it (SrrvPraSubmissionTest): Advance does not skip them.
    $advance()->assertSessionHas('error', fn ($message) => str_contains($message, 'PRA Submission'));
    expect($application->fresh()->status)->toBe('pra');
});

test('the PRA requires a principal aged 40 or older, and a Courtesy file says for whom', function () {
    $officer = srrvOfficer();
    $young = openRetireeFile($this, $officer, ['date_of_birth' => now()->subYears(38)->format('Y-m-d')]);
    $undated = openRetireeFile($this, $officer, ['date_of_birth' => null]);
    $courtesy45 = openRetireeFile($this, $officer, ['visa_class' => 'courtesy', 'courtesy_type' => 'foreign_national', 'date_of_birth' => now()->subYears(45)->format('Y-m-d')]);

    // The PRA's rules are checked on Step 1, the one a file opened before this flow still sits on.
    foreach ([$young, $undated, $courtesy45] as $file) {
        $file->update(['status' => 'pending']);
    }

    $this->actingAs($officer)->post(route('srrv.applications.advance', $young))->assertSessionHas('error', fn ($m) => str_contains($m, '40 or older'));
    $this->actingAs($officer)->post(route('srrv.applications.advance', $undated))->assertSessionHas('error', fn ($m) => str_contains($m, 'birth date'));
    // Old enough, with the deposit recorded: it moves on to the agreement.
    $this->actingAs($officer)->post(route('srrv.applications.advance', $courtesy45))->assertSessionHas('success');

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
        'document_remarks' => 'Police clearance, apostilled',
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
            'srrv_card_number' => 'M-0070001',
            'agreement_signed' => 1,
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
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 2,
    ])->assertRedirect();

    expect((float) SrrvRenewal::firstOrFail()->fee_amount)->toBe(720.0);
});

test('a renewal cannot be paid more than two years ahead', function () {
    $this->actingAs(srrvOfficer())->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
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
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    expect((float) $renewal->fee_amount)->toBe(360.0);

    $this->actingAs($officer)->put(route('srrv.renewals.update', $renewal), [
        'visa_class' => 'courtesy',
        'retiree_name' => 'Ramon Dela Cruz',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 2,
    ])->assertRedirect();

    expect((float) $renewal->fresh()->fee_amount)->toBe(20.0);
});

test('a renewal walks its pipeline: submission needs its date and remarks, payment its dues, release a name', function () {
    $officer = srrvOfficer();
    $cashierUser = cashier();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 1,
    ])->assertRedirect();

    $renewal = SrrvRenewal::firstOrFail();
    expect($renewal->status)->toBe('pending');

    // The documents step first: ID + photocopy, online form, signature + thumb mark.
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.stage', $renewal), [
        'id_and_photocopy_received' => 1,
        'form_filled_online' => 1,
        'signature_thumbmark_taken' => 1,
    ])->assertSessionHasNoErrors();

    // Not until the cashier has taken both dues.
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error', fn ($message) => str_contains($message, 'Waiting for the cashier'));
    $this->actingAs($cashierUser)->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 360, 'apply_to' => 'fee', 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHasNoErrors();

    // Ready for submission, then the submission itself needs its date and remarks.
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('ready_for_submission');

    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'submitted_to_pra'])
        ->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), [
        'status' => 'submitted_to_pra', 'submitted_on' => now()->toDateString(), 'submitted_remarks' => 'Filed at the Makati office',
    ])->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('submitted_to_pra')
        ->and($renewal->fresh()->submitted_at->toDateString())->toBe(now()->toDateString());

    // Paid before it left the first step, so Payment Confirmed moves straight on.
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('payment_confirmed');
    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('new_id_ready');

    // New ID ready defaulted the 2-year card expiry; release needs a name.
    expect($renewal->fresh()->card_expiry_date->toDateString())->toBe(now()->addYears(2)->toDateString());

    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'released_to_client'])
        ->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertRedirect();
    expect($renewal->fresh()->status)->toBe('released_to_client');

    // Closed locks the file.
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'closed'])->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('closed');
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'pending'])->assertSessionHas('error');
});

test('the status dropdown moves the file with its details, notifies, logs notes and closes', function () {
    Notification::fake();
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 1,
    ])->assertRedirect();

    $renewal = SrrvRenewal::firstOrFail();

    // The dropdown cannot skip the current stage's own work.
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'submitted_to_pra'])
        ->assertSessionHas('error');

    // Record the documents, then submit with its date and remarks.
    $this->actingAs($officer)->post(route('srrv.renewals.stage', $renewal), [
        'id_and_photocopy_received' => 1,
        'form_filled_online' => 1,
        'signature_thumbmark_taken' => 1,
    ])->assertSessionHasNoErrors();
    $renewal->forceFill(['fee_paid' => $renewal->fresh()->fee_amount, 'amount_paid' => $renewal->fresh()->serviceDue()])->save();
    // One step at a time: Ready for Submission comes first.
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'ready_for_submission'])->assertSessionHasNoErrors();
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), [
        'status' => 'submitted_to_pra', 'submitted_on' => now()->toDateString(), 'submitted_remarks' => 'Filed at the counter',
    ])->assertSessionHasNoErrors();
    expect($renewal->fresh()->submitted_at->toDateString())->toBe(now()->toDateString());
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'payment_confirmed'])->assertSessionHasNoErrors();
    $renewal->forceFill(['fee_paid' => $renewal->fresh()->fee_amount, 'amount_paid' => $renewal->fresh()->serviceDue()])->save();

    // New ID ready sets the 2-year card expiry; the retiree is notified from the file.
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'new_id_ready'])
        ->assertSessionHasNoErrors();
    expect($renewal->fresh()->card_expiry_date->toDateString())->toBe(now()->addYears(2)->toDateString());

    $this->actingAs($officer)->post(route('srrv.renewals.notify', $renewal), ['subject' => 'Ready', 'message' => 'Come and claim it'])
        ->assertSessionHasNoErrors();
    expect($renewal->fresh()->client_notified_at)->not->toBeNull();
    Notification::assertSentOnDemand(ClientCaseNotification::class);

    // The notes log records who wrote what, and when.
    $this->actingAs($officer)->post(route('srrv.renewals.notes.store', $renewal), ['note' => 'Called the retiree about pickup.'])
        ->assertSessionHasNoErrors();
    expect($renewal->fresh()->notes)->toHaveCount(1)
        ->and($renewal->fresh()->notes->first()->created_by)->toBe($officer->id);
    $this->get(route('srrv.renewals.show', $renewal))->assertOk()->assertSee('Called the retiree about pickup.');

    // The expiry feeds the client's record for the next renewal reminder.
    expect(User::where('role', 'client')->where('name', 'Ramon Dela Cruz')->first()?->pra_id_valid_until?->toDateString())
        ->toBe(now()->addYears(2)->toDateString());

    // Released, then closed: closed locks the file.
    $this->actingAs($officer)->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertRedirect();
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'closed'])->assertSessionHasNoErrors();
    expect($renewal->fresh()->status)->toBe('closed')->and($renewal->fresh()->closed_at)->not->toBeNull();
    $this->actingAs($officer)->post(route('srrv.renewals.status', $renewal), ['status' => 'pending'])->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.notes.store', $renewal), ['note' => 'After close'])->assertSessionHas('error');
});

test('releasing a renewal records who took it', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'courtesy',
        'retiree_name' => 'Ramon Dela Cruz',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    $renewal->update(['status' => 'new_id_ready']);

    // Not while the renewal fee is unpaid.
    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertSessionHas('error');
    expect($renewal->fresh()->status)->toBe('new_id_ready');

    $this->actingAs(cashier())->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 10, 'apply_to' => 'fee', 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHasNoErrors();

    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), ['collected_by_name' => 'Ramon Dela Cruz'])
        ->assertRedirect();

    $renewal->refresh();
    expect($renewal->status)->toBe('released_to_client');
    expect($renewal->collected_by_name)->toBe('Ramon Dela Cruz');
    expect($renewal->collected_at)->not->toBeNull();
});

test('releasing requires the receiver name', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
        'years_paid' => 1,
    ]);

    $renewal = SrrvRenewal::firstOrFail();
    $renewal->update(['status' => 'new_id_ready', 'fee_paid' => $renewal->fee_amount]);

    $this->actingAs($officer)
        ->post(route('srrv.renewals.collect', $renewal), [])
        ->assertSessionHasErrors('collected_by_name');

    expect($renewal->fresh()->status)->toBe('new_id_ready');
});

test('a renewal can be linked to a retiree file', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->post('/srrv/renewals', [
        'srrv_application_id' => $application->id,
        'visa_class' => 'classic',
        'retiree_name' => $application->retiree_name,
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
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

test('the old retiree file page is gone: its address opens the wizard, which ends at Save & Print', function () {
    $officer = srrvOfficer();
    $application = openRetireeFile($this, $officer);

    $this->actingAs($officer)->get(route('srrv.applications.show', $application))->assertRedirect(route('srrv.applications.edit', $application));

    // Past the agreement and fee too: no PRA Processing page, the last step ends at Save & Print Application Form.
    $application->update(['status' => 'documentation']);
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$application, 'step' => 7]))->assertOk()
        ->assertSee($application->reference)->assertSee('SRRV Classic')->assertSee('Save &amp; Print Application Form', false)
        ->assertDontSee('PRA Processing');
});

test('the renewal ledger renders', function () {
    $officer = srrvOfficer();

    $this->actingAs($officer)->post('/srrv/renewals', [
        'visa_class' => 'classic',
        'retiree_name' => 'Ledger Retiree',
        'srrv_card_number' => 'M-0070001',
        'agreement_signed' => 1,
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

    expect($application->fresh()->status)->toBe('agreement');
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
