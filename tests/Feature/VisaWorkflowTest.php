<?php

use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Models\VisaPricingTier;
use App\Notifications\ClientCaseNotification;
use App\Support\DocumentStorage;
use App\Support\VisaWorksheets;
use Database\Seeders\VisaPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(fn () => autoApproveDeskFiles());

function visaOfficer(): User
{
    return User::factory()->create(['role' => 'visa_assistance']);
}

/** The cashier takes a payment on a visa file (the visa agent no longer records payments). */
function visaCashierPays(TestCase $test, VisaApplication $application, float $amount)
{
    $cashier = User::where('role', 'cashier')->first() ?? User::factory()->create(['role' => 'cashier']);

    return $test->actingAs($cashier)->post(route('cashier.visa.store', $application->id), ['amount' => $amount, 'method' => 'cash', 'reference' => 'OR-'.random_int(100, 999), 'cashier_acknowledged' => 1]);
}

/**
 * Open a counter file through the real intake endpoint.
 *
 * @param  array<string, mixed>  $overrides
 */
function openVisaFile(TestCase $test, User $officer, array $overrides = []): VisaApplication
{
    $payload = array_merge([
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'client_phone' => '+63 900 000 0000',
        'destination_country' => 'Japan',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'service_fee' => 3500,
        'amount_paid' => 1000,
    ], $overrides);

    $test->actingAs($officer)->post('/visa-assistance/applications', $payload + visaDraftFormPayload($payload))->assertRedirect();

    return VisaApplication::latest('id')->firstOrFail();
}

/**
 * The destination's draft form, filled in with just enough to pass ("it must be filled in before the file is sent"),
 * for test payloads that open a visit-visa file: empty for a destination with no application form.
 */
function visaDraftFormPayload(array $payload): array
{
    if (($payload['service_type'] ?? 'visit_visa') !== 'visit_visa') {
        return [];
    }

    $worksheet = VisaWorksheets::resolve($payload['destination_country'] ?? null, $payload['checklist_key'] ?? null, $payload['purpose'] ?? null);

    return $worksheet ? ['form_'.$worksheet => ['phone' => '0917 000 0000']] : [];
}

// ---------------------------------------------------------------------------
// Intake
// ---------------------------------------------------------------------------

test('visa officer can open a visit visa counter file', function () {
    $officer = visaOfficer();

    $application = openVisaFile($this, $officer);

    expect($application->reference)->toStartWith('VSA-');
    expect($application->service_type)->toBe('visit_visa');
    expect($application->status)->toBe('pending');
    expect($application->client_name)->toBe('Jane Tan');
    expect($application->created_by)->toBe($officer->id);
});

test('a visit visa to Australia is recorded as needing no appearance', function () {
    $australia = openVisaFile($this, visaOfficer(), ['destination_country' => 'Australia']);
    $japan = openVisaFile($this, visaOfficer(), ['destination_country' => 'Japan']);

    expect($australia->needsAppearance())->toBeFalse();
    expect($australia->requires_appearance)->toBeFalse();
    expect($japan->needsAppearance())->toBeTrue();
});

test('the rush surcharge is taken from the published schedule not the form', function () {
    $this->seed(VisaPricingSeeder::class);

    $application = openVisaFile($this, visaOfficer(), [
        'processing_speed' => 'rush',
        'service_fee' => 3500,
        // The form tries to talk the price down. It must be ignored.
        'rush_fee' => 1,
    ]);

    expect((float) $application->rush_fee)->toBe(5000.0);
    expect((float) $application->total_amount)->toBe(8500.0);
});

test('a regular visit visa carries no rush surcharge', function () {
    $this->seed(VisaPricingSeeder::class);

    $application = openVisaFile($this, visaOfficer(), ['processing_speed' => 'regular']);

    expect((float) $application->rush_fee)->toBe(0.0);
});

test('a visit visa requires a destination and purpose', function () {
    $this->actingAs(visaOfficer())
        ->post('/visa-assistance/applications', [
            'service_type' => 'visit_visa',
            'client_name' => 'Jane Tan',
            'processing_speed' => 'regular',
        ])
        ->assertSessionHasErrors(['destination_country', 'purpose']);
});

test('a foreign passporting job requires an embassy', function () {
    $this->actingAs(visaOfficer())
        ->post('/visa-assistance/applications', [
            'service_type' => 'passporting',
            'client_name' => 'Sam Lee',
            'passport_type' => 'foreign',
        ])
        ->assertSessionHasErrors('embassy_country');
});

test('a local passporting job does not require an embassy', function () {
    $application = openVisaFile($this, visaOfficer(), [
        'service_type' => 'passporting',
        'passport_type' => 'local',
        'destination_country' => null,
        'purpose' => null,
        'processing_speed' => null,
    ]);

    expect($application->service_type)->toBe('passporting');
    expect($application->passport_type)->toBe('local');
    expect($application->embassy_country)->toBeNull();
});

// ---------------------------------------------------------------------------
// Pipeline
// ---------------------------------------------------------------------------

test('a visit visa file advances only as each stage is done', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['service_fee' => 3500, 'visa_fee' => 2100, 'amount_paid' => 0]);
    $advance = fn () => $this->actingAs($officer)->post(route('visa.applications.advance', $application));
    $record = fn (array $data = []) => $this->actingAs($officer)->post(route('visa.applications.stage', $application), $data);

    // File opened: needs an applicant. Opening adds the client, so take them off first.
    $application->applicants()->delete();
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('visa.applicants.store', $application), ['first_name' => 'Jane', 'last_name' => 'Tan']);
    $advance();
    expect($application->fresh()->status)->toBe('agreement');

    // Step 1, Draft Form & Approval: signed, then the cashier takes a down payment (enough to start the work).
    $advance()->assertSessionHas('error');
    $record();
    $advance()->assertSessionHas('error');
    expect($application->fresh()->status)->toBe('agreement');
    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 2000])->assertSessionHas('error');
    visaCashierPays($this, $application, 2000)->assertSessionHas('success');
    $advance();
    expect($application->fresh()->status)->toBe('requirements');

    // Step 3, Documents: the applicant's passport copy, then the signed Acknowledgment.
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'visa_applicant_id' => $application->applicants()->first()->id,
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);
    $advance()->assertSessionHas('error');
    expect($application->fresh()->status)->toBe('requirements');
    $record(['action' => 'acknowledged']);
    $advance();
    expect($application->fresh()->status)->toBe('drafting');

    // Step 3, Official Website Encoding: ticked, with the reference the website gave.
    $advance()->assertSessionHas('error');
    $record(['action' => 'encoded', 'portal_reference' => 'WEB-77']);
    $advance();
    expect($application->fresh()->status)->toBe('lodged');

    // Submission: the balance is settled here, then the submission recorded.
    $record(['lodged_at' => now()->toDateString(), 'embassy_reference' => 'EMB-9']);
    expect($application->fresh()->lodged_at)->toBeNull(); // Japan needs an appearance: the appointment and biometrics dates are required.
    $record(['lodged_at' => now()->toDateString(), 'embassy_reference' => 'EMB-9', 'processing_days' => 14, 'appointment_at' => now()->addDays(3)->toDateString(), 'biometrics_at' => now()->addDays(3)->toDateString()]);
    $advance()->assertSessionHas('error');
    expect($application->fresh()->status)->toBe('lodged')
        ->and($application->fresh()->nextStep())->toMatchArray(['title' => 'Collect the balance', 'anchor' => 'fees']);
    visaCashierPays($this, $application, 3600)->assertSessionHas('success');
    $advance();
    expect($application->fresh()->status)->toBe('result');

    // Step 6, Result & Release: the result recorded, then the file released.
    $advance()->assertSessionHas('error');
    $record(['result' => 'approved', 'result_at' => now()->toDateString(), 'result_reference' => 'V-001']);
    expect($application->fresh()->nextStep())->toMatchArray(['state' => 'ready', 'next' => 'Released', 'step' => 6, 'steps' => 6]);
    $advance();

    $application->refresh();
    expect($application->status)->toBe('released')
        ->and($application->status_label)->toBe('Released')
        ->and($application->nextStep())->toMatchArray(['state' => 'done', 'step' => 6])
        ->and($application->completedStages())->toBe(VisaApplication::SERVICE_STAGES['visit_visa'])
        ->and($application->result)->toBe('approved')
        ->and($application->processing_days)->toBe(14)
        ->and($application->agreement_signed_at)->not->toBeNull()
        ->and($application->acknowledgement_signed_at)->not->toBeNull()
        ->and($application->encoded_by)->toBe($officer->id)->and($application->portal_reference)->toBe('WEB-77')
        ->and($application->biometrics_at)->not->toBeNull()->and($application->payments)->toHaveCount(2)
        ->and($application->payment_type)->toBe('full');
});

test('a country has no ticked steps: Step 1 waits only for the draft forms, and the tick action is gone', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['destination_country' => 'Canada', 'service_fee' => 3500]);
    $advance = fn () => $this->actingAs($officer)->post(route('visa.applications.advance', $application));

    // Nothing to tick: the page shows no step list and no checkboxes for the country.
    $this->actingAs($officer)->get(route('visa.applications.show', $application))->assertOk()
        ->assertDontSee('Canada steps')->assertDontSee('of 3 done')->assertDontSee('visaTasks(', false);
    expect(Route::has('visa.applications.tasks'))->toBeFalse();

    // The client's own draft form was already filled in at intake (it is required before the file can be sent),
    // but Step 1 also waits for every OTHER applicant's draft form (the Canada application form) added after.
    $this->actingAs($officer)->post(route('visa.applicants.store', $application), ['first_name' => 'Mark', 'last_name' => 'Tan']);
    $advance()->assertSessionHas('error', fn (string $m) => str_contains($m, 'Fill in the draft form of: Mark Tan'));
    $second = $application->applicants()->where('first_name', 'Mark')->firstOrFail();
    $this->actingAs($officer)->put(route('visa.worksheets.update', [$application, $second]), ['form' => ['place_of_birth' => 'Manila']])->assertSessionHas('success');

    $advance();
    expect($application->fresh()->status)->toBe('agreement');
});

test('a requested additional document holds the file at drafting until it is received', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['service_fee' => 3500, 'amount_paid' => 3500]);
    $application->update(['status' => 'drafting', 'drafted_by' => $officer->id, 'drafted_at' => now()]);
    $application->documents()->create(['document_type' => 'application_form', 'checklist_item' => VisaApplication::DRAFTED_FORM, 'file_path' => 'x/form.pdf', 'original_name' => 'form.pdf', 'status' => 'uploaded']);

    $this->actingAs($officer)->post(route('visa.applications.requests.store', $application), ['label' => 'Bank certificate']);
    expect($application->fresh()->stageBlocker())->toContain('Bank certificate');

    $this->actingAs($officer)->post(route('visa.applications.requests.received', [$application, 0]));
    expect($application->fresh()->stageBlocker())->toBeNull();
});

test('insurance and e-travel are add-ons that never hold a visit visa file', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.applications.addons', $application), ['section' => 'insurance', 'insurance_provider' => 'Pioneer', 'insurance_policy_number' => 'P-1'])
        ->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.addons', $application), ['section' => 'etravel', 'etravel_reference' => 'ET-1']);

    expect($application->fresh()->insurance_policy_number)->toBe('P-1')
        ->and($application->fresh()->etravel_reference)->toBe('ET-1')
        ->and(VisaApplication::SERVICE_STAGES['visit_visa'])->not->toContain('insurance', 'etravel');
});

test('each service follows its own flowchart order', function () {
    expect((new VisaApplication(['service_type' => 'visit_visa']))->stages())
        ->toBe(['pending', 'agreement', 'requirements', 'drafting', 'lodged', 'result'])
        ->and((new VisaApplication(['service_type' => 'e_visa']))->stages())
        ->toBe(['pending', 'agreement', 'requirements', 'drafting', 'result'])
        ->and((new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'local']))->stages())
        ->toBe(['pending', 'agreement', 'requirements', 'appointment', 'result'])
        ->and((new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'foreign']))->stages())
        ->toBe(['pending', 'agreement', 'requirements', 'appointment', 'result']);
});

test('a payment cannot exceed the balance, and the visa agent no longer records payments', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['service_fee' => 3500, 'amount_paid' => 0]);

    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 100])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'recorded by the cashier'));
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('visa.applications.payments', $application), ['amount' => 5000])
        ->assertSessionHasErrors('amount');
    visaCashierPays($this, $application, 5000)->assertSessionHasErrors('amount');

    expect((float) $application->fresh()->amount_paid)->toBe(0.0);
});

test('a passporting file has no fixed document list', function () {
    $application = new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'local']);

    expect(collect($application->requiredDocuments())->pluck('type')->all())->toBe([]);
});

test('the acknowledgment of documents lists what was received', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);
    $this->actingAs($officer)->post(route('visa.applicants.store', $application), ['first_name' => 'Jane', 'last_name' => 'Tan']);
    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'visa_applicant_id' => $application->applicants()->first()->id,
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);

    $this->actingAs($officer)->get(route('visa.applications.acknowledgement', $application))
        ->assertOk()
        ->assertSee('ACKNOWLEDGMENT OF DOCUMENTS')
        ->assertSee('Jane Tan')
        ->assertSee('Passport Scan');
});

test('a file at its final stage refuses to advance further', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $application->update(['status' => 'released']);

    $this->actingAs($officer)
        ->post(route('visa.applications.advance', $application))
        ->assertSessionHas('error');

    expect($application->fresh()->status)->toBe('released');
});

test('a counter file can be cancelled', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.applications.cancel', $application));

    expect($application->fresh()->status)->toBe('cancelled');
});

// ---------------------------------------------------------------------------
// Applicants
// ---------------------------------------------------------------------------

test('applicants can be added to a group file and the first is primary', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, [
        'service_type' => 'e_visa',
        'applicant_type' => 'group',
    ]);

    foreach ([['Ana', 'Reyes'], ['Ben', 'Cruz']] as [$first, $last]) {
        $this->actingAs($officer)->post(route('visa.applicants.store', $application), [
            'first_name' => $first,
            'last_name' => $last,
        ])->assertRedirect();
    }

    $applicants = $application->applicants()->orderBy('applicant_number')->get();

    // The client was added as the primary applicant when the file opened.
    expect($applicants)->toHaveCount(3);
    expect($applicants[0]->full_name)->toBe('Jane Tan');
    expect($applicants[0]->is_primary)->toBeTrue();
    expect($applicants[1]->applicant_number)->toBe(2);
    expect($applicants[1]->is_primary)->toBeFalse();
    expect($applicants[1]->full_name)->toBe('Ana Reyes');
    expect($applicants[2]->applicant_number)->toBe(3);
});

test('the file page renders once it has applicants', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.applicants.store', $application), [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertOk()
        ->assertSee('Ana Reyes');
});

test('an applicant can be removed', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.applicants.store', $application), [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    $applicant = $application->applicants()->where('first_name', 'Ana')->firstOrFail();

    $this->actingAs($officer)
        ->delete(route('visa.applicants.destroy', [$application, $applicant]))
        ->assertRedirect();

    expect($application->applicants()->pluck('first_name')->all())->toBe(['Jane']);
});

test('an applicant belonging to another file cannot be removed', function () {
    $officer = visaOfficer();
    $first = openVisaFile($this, $officer, ['client_name' => 'File One']);
    $second = openVisaFile($this, $officer, ['client_name' => 'File Two']);

    $this->actingAs($officer)->post(route('visa.applicants.store', $first), [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);

    $applicant = $first->applicants()->firstOrFail();

    $this->actingAs($officer)
        ->delete(route('visa.applicants.destroy', [$second, $applicant]))
        ->assertSessionHas('error');

    expect(VisaApplicant::find($applicant->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Documents
// ---------------------------------------------------------------------------

test('filed documents land on the private disk and never the public one', function () {
    Storage::fake('local');
    Storage::fake('public');

    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ])->assertRedirect();

    $document = VisaApplicationDocument::firstOrFail();

    Storage::disk('local')->assertExists($document->file_path);
    Storage::disk('public')->assertMissing($document->file_path);
    expect($document->document_type)->toBe('passport_scan');
    expect($document->status)->toBe('uploaded');
});

test('a document can be downloaded by the counter', function () {
    Storage::fake('local');

    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);

    $document = VisaApplicationDocument::firstOrFail();

    $this->actingAs($officer)
        ->get(route('visa.documents.download', $document))
        ->assertOk();
});

test('a document can be filed against a named applicant', function () {
    Storage::fake('local');

    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.applicants.store', $application), [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);
    $applicant = $application->applicants()->firstOrFail();

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_photo',
        'visa_applicant_id' => $applicant->id,
        'file' => UploadedFile::fake()->image('photo.png'),
    ])->assertRedirect();

    expect(VisaApplicationDocument::firstOrFail()->visa_applicant_id)->toBe($applicant->id);
});

test('a document cannot be filed against an applicant from another file', function () {
    Storage::fake('local');

    $officer = visaOfficer();
    $first = openVisaFile($this, $officer, ['client_name' => 'File One']);
    $second = openVisaFile($this, $officer, ['client_name' => 'File Two']);

    $this->actingAs($officer)->post(route('visa.applicants.store', $first), [
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
    ]);
    $applicant = $first->applicants()->firstOrFail();

    $this->actingAs($officer)->post(route('visa.documents.store', $second), [
        'document_type' => 'passport_scan',
        'visa_applicant_id' => $applicant->id,
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ])->assertSessionHas('error');

    expect(VisaApplicationDocument::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Directory
// ---------------------------------------------------------------------------

test('the file directory renders and filters by service', function () {
    $officer = visaOfficer();
    openVisaFile($this, $officer, ['client_name' => 'Visit Client']);
    openVisaFile($this, $officer, [
        'service_type' => 'e_visa',
        'client_name' => 'Evisa Client',
        'applicant_type' => 'individual',
    ]);

    $this->actingAs($officer)->get('/visa-assistance/applications')
        ->assertOk()
        ->assertSee('Visit Client')
        ->assertSee('Evisa Client');

    $this->actingAs($officer)->get('/visa-assistance/applications?service_type=e_visa')
        ->assertOk()
        ->assertSee('Evisa Client')
        ->assertDontSee('Visit Client');
});

test('the payment step offers a down payment and a full payment to an admin; an agent waits for the cashier', function () {
    $officer = visaOfficer();
    $admin = User::factory()->create(['role' => 'admin']);
    $application = openVisaFile($this, $officer, ['service_fee' => 6500, 'visa_fee' => 8500, 'amount_paid' => 0]);
    $application->update(['status' => 'agreement', 'agreement_signed_at' => now()]);

    $this->actingAs($officer)->get(route('visa.applications.show', $application))->assertOk()
        ->assertSee('Print Booking Agreement')->assertDontSee('Record a payment');

    // Print Booking Agreement is only its current-step panel: no applicants, details or fees.
    $this->actingAs($admin)->get(route('visa.applications.show', $application))->assertOk()
        ->assertSee('Current step')->assertDontSee('id="applicants"', false)->assertDontSee('id="fees"', false)
        ->assertDontSee('File Details');

    // Any down payment is enough to move on; afterwards the counter is offered the balance.
    $this->actingAs($admin)->post(route('visa.applications.payments', $application), ['amount' => 3000])->assertSessionHas('success');
    expect($application->fresh()->stageBlocker())->toBeNull()->and($application->fresh()->payments)->toHaveCount(1);

});

test('the show page renders the pipeline and document checklist', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);
    // The upload card belongs to Receiving Documents on a Non-Immigrant file.
    $application->update(['status' => 'requirements']);

    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertOk()
        ->assertSee($application->reference)
        ->assertSee('Jane Tan')
        ->assertSee('Pipeline')
        ->assertSee('Document Checklist')
        ->assertDontSee('id="applicants"', false);
});

test('a counter file can be updated', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->put(route('visa.applications.update', $application), [
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan-Reyes',
        'destination_country' => 'Japan',
        'purpose' => 'business',
        'processing_speed' => 'regular',
        'service_fee' => 4000,
        'amount_paid' => 4000,
    ])->assertRedirect();

    $application->refresh();
    expect($application->client_name)->toBe('Jane Tan-Reyes');
    expect($application->purpose)->toBe('business');
    expect((float) $application->total_amount)->toBe(4000.0);
    // What was paid moves only through the cashier: an agent's edit cannot mark the file paid.
    expect($application->outstandingBalance())->toBe(4000.0);
});

test('deleting a file removes it and its filed documents', function () {
    Storage::fake('local');

    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->delete(route('visa.applications.destroy', $application))
        ->assertRedirect(route('visa.applications.index'));

    expect(VisaApplication::count())->toBe(0);
    expect(VisaApplicationDocument::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Isolation
// ---------------------------------------------------------------------------

test('the SRRV desk cannot reach any visa workflow route', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $srrv = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($srrv)->get('/visa-assistance/applications')
        ->assertRedirect(route('srrv.dashboard'));
    $this->actingAs($srrv)->get(route('visa.applications.show', $application))
        ->assertRedirect(route('srrv.dashboard'));
    $this->actingAs($srrv)->post(route('visa.applications.advance', $application))
        ->assertRedirect(route('srrv.dashboard'));

    expect($application->fresh()->status)->toBe('pending');
});

test('an admin can work the visa counter', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get('/visa-assistance/applications')->assertOk();

    $this->actingAs($admin)->post('/visa-assistance/applications', [
        'service_type' => 'visit_visa',
        'client_name' => 'Admin Opened',
        'destination_country' => 'Canada',
        'purpose' => 'family',
        'processing_speed' => 'regular',
        'form_canada' => ['phone' => '0917 000 0000'],
    ])->assertRedirect();

    expect(VisaApplication::where('client_name', 'Admin Opened')->exists())->toBeTrue();
});

test('the fee breakdown is loaded as per-person country prices', function () {
    $this->seed(VisaPricingSeeder::class);

    $rates = VisaPricingTier::countryRates()->get()->keyBy('country');

    expect($rates->keys()->all())->toBe([
        'Canada', 'USA', 'Korea', 'Japan', 'New Zealand', 'Schengen (Europe)',
        'China', 'Dubai', 'United Kingdom', 'Australia',
    ]);

    // Every selling price is the service fee plus the visa fee / expenses.
    $rates->each(fn ($rate) => expect((float) $rate->amount)->toBe((float) $rate->service_fee + (float) $rate->visa_fee));

    expect((float) $rates['USA']->amount)->toBe(21000.0)
        ->and($rates['USA']->inclusions)->toBe('Biometric, briefing and interview')
        ->and((float) $rates['Schengen (Europe)']->visa_fee)->toBe(0.0)
        ->and((float) $rates['Schengen (Europe)']->insurance_fee)->toBe(1000.0);
});

test('the visa fee is billed with the service fee', function () {
    $application = openVisaFile($this, visaOfficer(), [
        'destination_country' => 'Japan',
        'service_fee' => 7000,
        'visa_fee' => 4200,
        'amount_paid' => 0,
    ]);

    expect((float) $application->visa_fee)->toBe(4200.0)
        ->and((float) $application->total_amount)->toBe(11200.0);
});

test('the counter lookup finds registered clients and counter files', function () {
    $officer = visaOfficer();
    $client = User::factory()->create(['role' => 'client', 'name' => 'Chin Chin Chin', 'email' => 'chin@example.com', 'passport_number' => 'P1112223A']);
    User::factory()->create(['role' => 'admin', 'name' => 'Chin Admin']);
    $file = openVisaFile($this, $officer, ['client_name' => 'Chin Reyes']);

    $response = $this->actingAs($officer)->getJson(route('visa.lookup', ['q' => 'chin']))->assertOk();

    expect($response->json('files.0.reference'))->toBe($file->reference)
        ->and(collect($response->json('clients'))->pluck('name')->all())->toContain('Chin Chin Chin')
        ->and(collect($response->json('clients'))->pluck('name')->all())->not->toContain('Chin Admin')
        ->and(collect($response->json('clients'))->firstWhere('name', 'Chin Chin Chin')['url'])
        ->toBe(route('visa.visit.file', ['client' => $client->id]));

    $this->actingAs($officer)->getJson(route('visa.lookup', ['q' => 'P1112']))
        ->assertJsonPath('clients.0.name', 'Chin Chin Chin');
});

test('the counter lookup waits for two characters', function () {
    $this->actingAs(visaOfficer())->getJson(route('visa.lookup', ['q' => 'c']))
        ->assertExactJson(['files' => [], 'clients' => []]);
});

test('a new file opened for a registered client starts with their details', function () {
    $client = User::factory()->create(['role' => 'client', 'first_name' => 'Chin', 'last_name' => 'Santos', 'name' => 'Chin Santos', 'email' => 'chin@example.com', 'phone' => '09171112222']);

    $this->actingAs(visaOfficer())->get(route('visa.visit.file', ['client' => $client->id, 'service' => 'passporting']))
        ->assertOk()
        ->assertSee('chin@example.com', false)
        ->assertSee('09171112222', false);
});

test('clients cannot use the counter lookup', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->getJson(route('visa.lookup', ['q' => 'chin']))->assertForbidden();
});

test('opening a file adds the client as the primary applicant from their profile', function () {
    $officer = visaOfficer();
    User::factory()->create([
        'role' => 'client',
        'email' => 'jane@example.com',
        'first_name' => 'Jane',
        'middle_name' => 'Lim',
        'last_name' => 'Tan',
        'nationality' => 'Philippines',
        'passport_number' => 'P1234567A',
        'passport_expiry' => '2030-01-31',
    ]);

    $application = openVisaFile($this, $officer);
    $applicant = $application->applicants()->sole();

    expect($applicant->applicant_number)->toBe(1)
        ->and($applicant->is_primary)->toBeTrue()
        ->and($applicant->full_name)->toBe('Jane Lim Tan')
        ->and($applicant->nationality)->toBe('Philippines')
        ->and($applicant->passport_number)->toBe('P1234567A')
        ->and($applicant->passport_expiry_date->toDateString())->toBe('2030-01-31');
});

test('a file without applicants can add its client in one click', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['client_name' => 'Maria Clara Santos', 'client_email' => 'maria@example.com']);
    $application->applicants()->delete();

    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertOk()
        ->assertSee('Add Maria Clara Santos as applicant');

    $this->actingAs($officer)->post(route('visa.applicants.store-client', $application))
        ->assertSessionHas('success', 'Maria Clara Santos added as applicant #1.');

    $applicant = $application->applicants()->sole();
    expect($applicant->first_name)->toBe('Maria Clara')
        ->and($applicant->last_name)->toBe('Santos')
        ->and($applicant->is_primary)->toBeTrue();
});

test('each Non-Immigrant stage shows only what belongs to it', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['destination_country' => 'USA', 'service_fee' => 9600, 'visa_fee' => 11400]);
    $stages = VisaApplication::SERVICE_STAGES['visit_visa'];

    foreach ($stages as $index => $stage) {
        $application->update(['status' => $stage]);

        $response = $this->actingAs($officer)->get(route('visa.applications.show', $application))
            ->assertOk()->assertSee('Step '.($index + 1).' - ');

        // Requests for more documents: drafting and the embassy stages. Insurance and e-Travel: the last step only.
        in_array($stage, ['drafting', 'lodged', 'result'], true) ? $response->assertSee('Additional Documents') : $response->assertDontSee('Additional Documents');
        $stage === 'result' ? $response->assertSee('Insurance &amp; e-Travel', false) : $response->assertDontSee('Insurance &amp; e-Travel', false);
    }

    $application->update(['status' => 'pending']);
    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertSee('DS-160 (CEAC)')->assertSee('Estimated price', false);

    $application->update(['status' => 'drafting']);
    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertSee('Details encoded on official website')->assertDontSee('Second assessment')
        // The USA's own steps are no longer a ticked list.
        ->assertDontSee('USA steps')->assertDontSee('DS-160 drafted through CEAC')->assertDontSee('of 4 done');
});

test('a Non-Immigrant file opens from the step 1 form with no processing, payment or add-on fields', function () {
    $officer = visaOfficer();

    $this->actingAs($officer)->post('/visa-assistance/applications', [
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan',
        'destination_country' => 'Canada',
        'purpose' => 'tourist',
        'pax' => 1,
        'service_fee' => 6500,
        'visa_fee' => 8500,
        'insurance_fee' => 0,
        'etravel_fee' => 0,
        'form_canada' => ['phone' => '0917 000 0000'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $application = VisaApplication::latest('id')->firstOrFail();

    expect($application->status)->toBe('pending')
        ->and($application->processing_speed)->toBe('regular')
        ->and((float) $application->total_amount)->toBe(15000.0)
        ->and((float) $application->amount_paid)->toBe(0.0)
        ->and($application->payment_type)->toBe('deposit');
});

test('a Non-Immigrant file shows the Fees box only at Draft Form & Approval and Submission', function () {
    $officer = visaOfficer();
    $admin = User::factory()->create(['role' => 'admin']);
    $application = openVisaFile($this, $officer, ['service_fee' => 6500, 'visa_fee' => 8500]);

    foreach (VisaApplication::SERVICE_STAGES['visit_visa'] as $stage) {
        $application->update(['status' => $stage]);
        $response = $this->actingAs($admin)->get(route('visa.applications.show', $application))->assertOk();

        in_array($stage, ['lodged'], true)
            ? $response->assertSee('id="fees"', false)->assertSee('Record a payment')
            : $response->assertDontSee('id="fees"', false)->assertDontSee('Record a payment');
    }

    // Other services keep it on every stage.
    $eVisa = VisaApplication::create(['reference' => 'VSA-EV2', 'client_name' => 'Ana', 'service_type' => 'passporting', 'status' => 'pending', 'created_by' => $officer->id]);
    $this->actingAs($officer)->get(route('visa.applications.show', $eVisa))->assertSee('id="fees"', false);
});

test('Official Website Encoding waits for the tick; a copy of the submitted application is optional', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);
    $application->update(['status' => 'drafting']);
    $upload = fn (array $files) => $this->actingAs($officer)->post(route('visa.applications.forms.store', $application), ['forms' => $files]);

    expect($application->fresh(['documents'])->stageBlocker())->toContain('Details encoded on official website');
    $this->actingAs($officer)->get(route('visa.applications.show', $application))->assertOk()
        ->assertSee('Step 4 - Official Website Encoding')->assertSee('Details encoded on official website')
        ->assertSee('Copy of the submitted application (optional)');

    // Copies: only documents and images; then two at once.
    $upload([UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])->assertSessionHasErrors('forms.0');
    $upload([UploadedFile::fake()->create('form-a.pdf', 50, 'application/pdf'), UploadedFile::fake()->create('form-b.docx', 30)])->assertSessionHas('success');
    expect($application->fresh()->draftedForms())->toHaveCount(2);
    expect($application->fresh(['applicants', 'documents'])->stageBlocker())->toContain('Details encoded on official website');

    // The tick, with the website's reference: the step is done.
    $this->actingAs($officer)->post(route('visa.applications.stage', $application), ['action' => 'encoded', 'portal_reference' => 'IRCC-123'])->assertSessionHas('success');
    $fresh = $application->fresh(['applicants', 'documents']);
    expect($fresh->stageBlocker())->toBeNull()->and($fresh->portal_reference)->toBe('IRCC-123')->and($fresh->encoded_by)->toBe($officer->id);
    $this->actingAs($officer)->get(route('visa.applications.show', $application))->assertSee('IRCC-123');
});

test('a scan of the signed acknowledgment is filed on the file and shown at Receiving of Documents', function () {
    Storage::fake(config('filesystems.documents_disk', 'local'));
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);
    $application->update(['status' => 'requirements']);

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'signed_acknowledgement',
        'file' => UploadedFile::fake()->create('signed-ack.pdf', 100, 'application/pdf'),
    ])->assertSessionHas('success');

    expect($application->fresh()->documents->where('document_type', 'signed_acknowledgement'))->toHaveCount(1);
    $this->actingAs($officer)->get(route('visa.applications.show', $application))->assertOk()
        ->assertSee('Signed copy (scan)')->assertSee('signed-ack.pdf');
});

test('Notify client at Submission emails the appointment and biometrics dates', function () {
    Notification::fake();
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['client_email' => 'jane@example.com', 'destination_country' => 'Japan']);
    $application->forceFill(['status' => 'lodged', 'amount_paid' => $application->total_amount])->save();

    $payload = ['lodged_at' => '2026-11-10', 'appointment_at' => '2026-11-20', 'biometrics_at' => '2026-11-21'];
    $this->actingAs($officer)->post(route('visa.applications.stage', $application), $payload)->assertSessionHas('success');
    Notification::assertNothingSent();

    $this->actingAs($officer)->post(route('visa.applications.stage', $application), $payload + ['notify_client' => 1])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'emailed'));
    Notification::assertSentOnDemand(ClientCaseNotification::class, fn ($n) => str_contains($n->body, 'November 21, 2026'));
});

test('the service list colours a released file green, a denied one red, one with the embassy navy, and one in progress amber', function () {
    $officer = visaOfficer();
    $admin = User::factory()->create(['role' => 'admin']);
    $done = openVisaFile($this, $officer, ['client_name' => 'Rel Client']);
    $done->forceFill(['status' => 'released', 'result' => 'approved'])->save();
    $denied = openVisaFile($this, $officer, ['client_name' => 'Den Client']);
    $denied->forceFill(['status' => 'released', 'result' => 'denied'])->save();
    $open = openVisaFile($this, $officer, ['client_name' => 'Open Client']);
    $open->forceFill(['status' => 'requirements'])->save();
    $withEmbassy = openVisaFile($this, $officer, ['client_name' => 'Embassy Client']);
    $withEmbassy->forceFill(['status' => 'lodged'])->save();

    // As on the Immigration list: navy once it is with the embassy.
    $html = $this->actingAs($admin)->get(route('visa.services', 'visit_visa'))->assertOk()->getContent();
    expect($html)->toContain('bg-emerald-50 text-emerald-800')->toContain('bg-rose-50 text-rose-800')->toContain('bg-amber-50 text-amber-800')->toContain('bg-navy-50 text-navy-700');
});
