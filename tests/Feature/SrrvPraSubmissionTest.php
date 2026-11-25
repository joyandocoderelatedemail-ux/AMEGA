<?php

use App\Http\Controllers\Srrv\SrrvClientLookupController;
use App\Models\SrrvApplication;
use App\Models\User;
use App\Support\ClientExpiries;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * PRA Submission: the steps after the wizard, opened from its last step, to the client's turnover.
 */
function praOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

/** A file past the wizard: approved, paid in full, the agreement and the acknowledgment signed, advanced from Step 6. */
function praReadyFile(User $officer, array $overrides = []): SrrvApplication
{
    test()->actingAs($officer)->post('/srrv/applications', [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'service_fee' => 500, 'currency' => 'PHP',
    ])->assertRedirect();

    $file = SrrvApplication::latest('id')->firstOrFail();
    $file->forceFill(array_merge([
        'status' => 'requirements', 'approval_status' => 'approved', 'amount_paid' => 500,
        'agreement_signed_at' => now(), 'acknowledgement_signed_at' => now(),
    ], $overrides))->save();

    return $file->fresh();
}

function praSave(object $test, User $officer, SrrvApplication $file, array $data)
{
    return $test->actingAs($officer)->post(route('srrv.applications.pra.save', $file), $data);
}

test('the wizard offers PRA Submission once past it, and its steps open with the wizard stepper', function () {
    $officer = praOfficer();

    // Not past the wizard yet: no button, and the page sends it back.
    $early = praReadyFile($officer, ['status' => 'agreement']);
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$early, 'step' => 7]))->assertOk()
        ->assertDontSee(route('srrv.applications.pra', $early), false);
    $this->actingAs($officer)->get(route('srrv.applications.pra', $early))->assertRedirect(route('srrv.applications.edit', $early))->assertSessionHas('error');

    $file = praReadyFile($officer);
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 7]))->assertOk()
        ->assertSee('PRA Submission')->assertSee('name="then" value="pra"', false)
        // PRA Submission comes after Save & Print, as the next step.
        ->assertSeeInOrder(['Save &amp; Print Application Form', 'PRA Submission'], false);

    $this->actingAs($officer)->get(route('srrv.applications.pra', $file))->assertOk()
        ->assertSee('Step 1 - PRA Application Submission')->assertSee('of 5')
        ->assertSee('PRA reference number')->assertSee('Submitted to the PRA, proof uploaded')
        ->assertSee('Visa Deposit and Payment Verification')->assertSee('PRA Evaluation and Processing')->assertSee('Document Receive')->assertSee('Client Turnover of Documents')
        ->assertSee('Document Checklist')->assertSee('File Document')->assertSee('Remarks')->assertSee('locked: 1', false);
});

test('Step 1 needs the submission ticked; the reference, the proof with its type and the remarks are kept on the step', function () {
    Storage::fake('local');
    $officer = praOfficer();
    $file = praReadyFile($officer);

    praSave($this, $officer, $file, ['step' => 1, 'then' => 'continue', 'pra_reference' => 'PRA-2026-001'])
        ->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 1]))->assertSessionHas('error');
    expect($file->fresh()->status)->toBe('pra')->and($file->fresh()->praStep())->toBe(1);

    // Each step's Document Checklist: File Document saves the step with it (nothing typed is lost) and files the
    // document, which says what it is.
    $fileDocument = fn (int $step, string $type, $upload, array $typed = []) => praSave($this, $officer, $file, ['step' => $step, 'then' => 'document', 'document_type' => $type, 'file' => $upload] + $typed);
    $fileDocument(1, '', UploadedFile::fake()->image('proof.jpg'))->assertSessionHasErrors('document_type');
    praSave($this, $officer, $file, ['step' => 1, 'then' => 'document', 'document_type' => 'Proof of submission'])->assertSessionHasErrors('file');
    $fileDocument(1, 'Proof of submission', UploadedFile::fake()->image('proof.jpg'), ['pra_reference' => 'PRA-2026-001', 'submitted' => 1])
        ->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 1]))->assertSessionHas('success');
    // The reference typed and the Submitted tick are kept, and it stays on the step.
    expect($file->fresh()->pra_reference)->toBe('PRA-2026-001')->and($file->fresh()->lodged_at)->not->toBeNull()->and($file->fresh()->praStep())->toBe(1);
    // A step not reached yet takes no documents.
    $fileDocument(3, 'Too early', UploadedFile::fake()->image('early.jpg'))->assertSessionHasErrors('step');

    praSave($this, $officer, $file, [
        'step' => 1, 'then' => 'continue', 'submitted' => 1, 'pra_reference' => 'PRA-2026-001', 'remarks' => 'Filed at the Makati office',
    ])->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 2]))->assertSessionHas('success');

    $file->refresh();
    $proof = $file->documents()->where('document_type', 'pra_step_1')->sole();
    expect($file->pra_reference)->toBe('PRA-2026-001')->and($file->lodged_at)->not->toBeNull()
        ->and($file->praRemarks(1))->toBe('Filed at the Makati office')->and($file->praStep())->toBe(2)
        ->and($file->progress_label)->toBe('Step 2 - Visa Deposit and Payment Verification')
        ->and($proof->remarks)->toBe('Proof of submission');
    Storage::disk('local')->assertExists($proof->file_path);

    // Step 2: the visa deposit and payment proofs, each with its own type.
    $fileDocument(2, 'Certificate of visa deposit', UploadedFile::fake()->create('deposit.pdf', 50, 'application/pdf'));
    $fileDocument(2, 'Official receipt', UploadedFile::fake()->image('or.png'));
    praSave($this, $officer, $file, ['step' => 2, 'then' => 'continue', 'remarks' => 'Deposit at DBP'])->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 3]));
    expect($file->fresh()->documents()->where('document_type', 'pra_step_2')->pluck('remarks')->all())->toBe(['Certificate of visa deposit', 'Official receipt']);

    // The page shows what was filed on each step, and a step not reached yet cannot be saved.
    $this->actingAs($officer)->get(route('srrv.applications.pra', [$file, 'step' => 1]))->assertOk()
        ->assertSee('Upload Reference')->assertSee('File Document')->assertSee('name="then" value="document"', false)->assertDontSee('pra-doc-upload', false)
        ->assertSee('Proof of submission')->assertSee('proof.jpg')->assertSee('Filed at the Makati office');
    praSave($this, $officer, $file, ['step' => 4, 'then' => 'continue'])->assertSessionHasErrors('step');
});

test('the PRA decision: withdrawn and declined close the file; approved goes on to the turnover, which releases it once paid', function () {
    $officer = praOfficer();
    $atStep3 = fn (array $overrides = []) => praReadyFile($officer, ['status' => 'pra', 'pra_flow' => ['step' => 3, 'decision' => 'processing', 'remarks' => []]] + $overrides);

    // Still processing: it stays on Step 3.
    $file = $atStep3();
    praSave($this, $officer, $file, ['step' => 3, 'then' => 'continue', 'decision' => 'processing'])->assertSessionHas('error');
    expect($file->fresh()->praStep())->toBe(3)->and($file->fresh()->isReleasedLocked())->toBeFalse();

    // Withdrawn: marked Withdrawn by Client and closed.
    praSave($this, $officer, $file, ['step' => 3, 'then' => 'save', 'decision' => 'withdrawn', 'remarks' => 'Client changed plans'])->assertSessionHas('success');
    $file->refresh();
    expect($file->workflow_status)->toBe('withdrawn')->and($file->status)->toBe('released')->and($file->isReleasedLocked())->toBeTrue()
        ->and($file->progress_label)->toBe('Withdrawn by Client')->and($file->praRemarks(3))->toBe('Client changed plans')
        ->and(SrrvClientLookupController::continueUrl($file))->toBeNull();
    praSave($this, $officer, $file, ['step' => 3, 'decision' => 'approved'])->assertSessionHas('error');
    $this->actingAs($officer)->get(route('srrv.applications.pra', $file))->assertOk()->assertDontSee('Save &amp; Close the File', false);

    // Declined: marked Declined by PRA and closed.
    $declined = $atStep3();
    praSave($this, $officer, $declined, ['step' => 3, 'decision' => 'declined']);
    expect($declined->fresh()->workflow_status)->toBe('denied')->and($declined->fresh()->progress_label)->toBe('Declined by PRA')
        ->and($declined->fresh()->isReleasedLocked())->toBeTrue();

    // Approved: Document Receive, then the client turnover releases it.
    $approved = $atStep3();
    praSave($this, $officer, $approved, ['step' => 3, 'then' => 'continue', 'decision' => 'approved'])->assertRedirect(route('srrv.applications.pra', [$approved, 'step' => 4]));
    // Document Receive needs the SRRV number the PRA issued; it goes on the file and the client's record.
    praSave($this, $officer, $approved, ['step' => 4, 'then' => 'continue', 'remarks' => 'Received from PRA'])
        ->assertSessionHas('error', fn ($message) => str_contains($message, 'SRRV number'));
    expect($approved->fresh()->praStep())->toBe(4);
    praSave($this, $officer, $approved, ['step' => 4, 'then' => 'continue', 'remarks' => 'Received from PRA', 'srrv_card_number' => 'M-0071234',
        'srrv_issued_on' => today()->subDay()->toDateString(), 'pra_id_valid_until' => today()->addDays(20)->toDateString()])
        ->assertRedirect(route('srrv.applications.pra', [$approved, 'step' => 5]));
    expect($approved->fresh())->srrv_card_number->toBe('M-0071234')
        ->and($approved->fresh()->pra_id_valid_until->toDateString())->toBe(today()->addDays(20)->toDateString());
    $retiree = User::where('role', 'client')->where('email', 'ramon@example.com')->firstOrFail();
    expect($retiree->srrv_number)->toBe('M-0071234')->and($retiree->pra_id_valid_until->toDateString())->toBe(today()->addDays(20)->toDateString())
        ->and(ClientExpiries::all(allFiles: true)->firstWhere('key', "pra_id:user:{$retiree->id}"))->toMatchArray(['label' => 'PRA ID card', 'number' => 'M-0071234', 'days' => 20]);
    expect(SrrvClientLookupController::continueUrl($approved->fresh()))->toBe(route('srrv.applications.pra', [$approved, 'step' => 5]));
    praSave($this, $officer, $approved, ['step' => 5, 'then' => 'continue', 'remarks' => 'Handed to the client'])->assertSessionHas('success');
    $approved->refresh();
    expect($approved->status)->toBe('released')->and($approved->released_at)->not->toBeNull()->and($approved->progress_label)->toBe('Released')
        ->and($approved->praRemarks(4))->toBe('Received from PRA')->and($approved->praRemarks(5))->toBe('Handed to the client');
    $this->actingAs($officer)->get(route('srrv.applications.pra', [$approved, 'step' => 5]))->assertOk()
        ->assertSee('File complete')->assertSee(route('srrv.dashboard'), false);
    $this->actingAs($officer)->get(route('srrv.applications.pra', [$declined, 'step' => 3]))->assertOk()->assertDontSee('File complete');

    // Not fully paid: no turnover.
    $owing = praReadyFile($officer, ['status' => 'pra', 'amount_paid' => 200, 'pra_flow' => ['step' => 5, 'decision' => 'approved', 'remarks' => []]]);
    praSave($this, $officer, $owing, ['step' => 5, 'then' => 'continue'])->assertSessionHas('error', fn ($message) => str_contains($message, 'fully paid'));
    expect($owing->fresh()->status)->toBe('pra');
});

test('PRA Submission starts only once the client has signed the Acknowledgment of Documents', function () {
    $officer = praOfficer();
    $file = praReadyFile($officer, ['acknowledgement_signed_at' => null]);

    praSave($this, $officer, $file, ['step' => 1, 'submitted' => 1, 'then' => 'continue'])->assertSessionHas('error', fn ($message) => str_contains($message, 'Acknowledgment'));
    expect($file->fresh()->status)->toBe('requirements');
});

test('PRA Submission on Step 7 starts the steps: the status says so, and Continue comes back to the step reached', function () {
    $officer = praOfficer();
    $file = praReadyFile($officer);

    // Step 7's button posts the start; opening the page alone changes nothing.
    $this->actingAs($officer)->get(route('srrv.applications.pra', $file))->assertOk();
    expect($file->fresh()->status)->toBe('requirements');

    $this->actingAs($officer)->post(route('srrv.applications.pra.start', $file))->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 1]));
    $file->refresh();
    expect($file->status)->toBe('pra')->and($file->praStep())->toBe(1)->and($file->progress_label)->toBe('Step 1 - PRA Application Submission')
        ->and(SrrvClientLookupController::continueUrl($file))->toBe(route('srrv.applications.pra', [$file, 'step' => 1]));

    // Away to the dashboard and back: the list and the dashboard show where it stands, and Continue opens the PRA step.
    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->assertSee('Step 1 - PRA Application Submission');
    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()
        ->assertSee('Step 1 - PRA Application Submission')->assertSee('href="'.route('srrv.applications.pra', [$file, 'step' => 1]).'"', false);

    // Starting again just opens where it is.
    praSave($this, $officer, $file, ['step' => 1, 'then' => 'continue', 'submitted' => 1]);
    $this->actingAs($officer)->post(route('srrv.applications.pra.start', $file))->assertRedirect(route('srrv.applications.pra', [$file, 'step' => 2]));
    expect($file->fresh()->progress_label)->toBe('Step 2 - Visa Deposit and Payment Verification');

    // Without the client's signed acknowledgment it does not start: back to the Documents step.
    $unsigned = praReadyFile($officer, ['acknowledgement_signed_at' => null]);
    $this->actingAs($officer)->post(route('srrv.applications.pra.start', $unsigned))
        ->assertRedirect(route('srrv.applications.edit', [$unsigned, 'step' => 5]))->assertSessionHas('error');
    expect($unsigned->fresh()->status)->toBe('requirements');
});

test('a notice on a file with no email saves the typed email on the file and the client account, then sends', function () {
    Notification::fake();
    $officer = praOfficer();
    $file = praReadyFile($officer, ['retiree_email' => null]);
    $client = User::where('role', 'client')->where('name', 'Ramon Dela Cruz')->sole();
    $client->forceFill(['email' => 'client.ramon.1'.User::PLACEHOLDER_EMAIL_DOMAIN])->save();

    $this->actingAs($officer)->get(route('srrv.applications.pra', [$file, 'step' => 5]))->assertOk()
        ->assertSee('name="email"', false)->assertSee('Save email &amp; notify client', false);

    $notice = ['notice_for' => 'srrv-pra-turnover', 'subject' => 'Ready', 'message' => 'Your documents are ready.'];
    $this->actingAs($officer)->post(route('srrv.applications.pra.notify', $file), $notice + ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    expect($file->fresh()->retiree_email)->toBeNull();

    $this->actingAs($officer)->post(route('srrv.applications.pra.notify', $file), $notice + ['email' => 'Ramon@Example.com'])->assertSessionHas('success');
    expect($file->fresh()->retiree_email)->toBe('ramon@example.com')->and($client->fresh()->email)->toBe('ramon@example.com');

    // A file that has an email keeps it: the field is only for a file with none.
    $this->actingAs($officer)->post(route('srrv.applications.pra.notify', $file), $notice + ['email' => 'other@example.com'])->assertSessionHas('success');
    expect($file->fresh()->retiree_email)->toBe('ramon@example.com');
});
