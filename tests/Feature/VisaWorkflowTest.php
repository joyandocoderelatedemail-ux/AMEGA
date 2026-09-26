<?php

use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Models\VisaPricingTier;
use App\Support\DocumentStorage;
use Database\Seeders\VisaPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(RefreshDatabase::class);

function visaOfficer(): User
{
    return User::factory()->create(['role' => 'visa_assistance']);
}

/**
 * Open a counter file through the real intake endpoint.
 *
 * @param  array<string, mixed>  $overrides
 */
function openVisaFile(TestCase $test, User $officer, array $overrides = []): VisaApplication
{
    $test->actingAs($officer)->post('/visa-assistance/applications', array_merge([
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'client_phone' => '+63 900 000 0000',
        'destination_country' => 'Japan',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'service_fee' => 3500,
        'amount_paid' => 1000,
    ], $overrides))->assertRedirect();

    return VisaApplication::latest('id')->firstOrFail();
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

test('an e-visa requires an application type', function () {
    $this->actingAs(visaOfficer())
        ->post('/visa-assistance/applications', [
            'service_type' => 'e_visa',
            'client_name' => 'Group Lead',
        ])
        ->assertSessionHasErrors('applicant_type');

    $application = openVisaFile($this, visaOfficer(), [
        'service_type' => 'e_visa',
        'applicant_type' => 'group',
        'destination_country' => null,
        'purpose' => null,
        'processing_speed' => null,
    ]);

    expect($application->isGroupPackage())->toBeTrue();
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

    // File opened: needs an applicant.
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('visa.applicants.store', $application), ['first_name' => 'Jane', 'last_name' => 'Tan']);
    $advance();
    expect($application->fresh()->status)->toBe('requirements');

    // Requirements: the applicant's passport copy.
    $advance()->assertSessionHas('error');
    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'visa_applicant_id' => $application->applicants()->first()->id,
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);
    $advance();
    expect($application->fresh()->status)->toBe('agreement');

    // Agreement signed.
    $advance()->assertSessionHas('error');
    $record();
    $advance();
    expect($application->fresh()->status)->toBe('insurance');

    // Insurance taken or declined.
    $advance()->assertSessionHas('error');
    $record(['insurance_declined' => 1]);
    $advance();
    expect($application->fresh()->status)->toBe('etravel');

    // e-Travel reference.
    $advance()->assertSessionHas('error');
    $record(['etravel_reference' => 'ET-12345']);
    $advance();
    expect($application->fresh()->status)->toBe('payment');

    // Payment: a deposit is not enough; the file must be fully paid.
    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 2000]);
    $advance()->assertSessionHas('error');
    expect($application->fresh()->status)->toBe('payment');
    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 3600]);
    $advance();
    expect($application->fresh()->status)->toBe('acknowledged');

    // Acknowledgment signed.
    $advance()->assertSessionHas('error');
    $record();
    $advance();
    expect($application->fresh()->status)->toBe('lodged');

    // Embassy processing: waits for the result.
    $record(['lodged_at' => now()->toDateString(), 'embassy_reference' => 'EMB-9']);
    $advance()->assertSessionHas('error');
    $record(['result' => 'approved', 'result_at' => now()->toDateString(), 'result_reference' => 'V-001']);
    $advance();

    $application->refresh();
    expect($application->status)->toBe('released')
        ->and($application->status_label)->toBe('Application Result')
        ->and($application->result)->toBe('approved')
        ->and($application->agreement_signed_at)->not->toBeNull()
        ->and($application->acknowledgement_signed_at)->not->toBeNull()
        ->and($application->payment_type)->toBe('full');
});

test('each service follows its own flowchart order', function () {
    expect((new VisaApplication(['service_type' => 'visit_visa']))->stages())
        ->toBe(['pending', 'requirements', 'agreement', 'insurance', 'etravel', 'payment', 'acknowledged', 'lodged', 'released'])
        ->and((new VisaApplication(['service_type' => 'e_visa']))->stages())
        ->toBe(['pending', 'requirements', 'payment', 'agreement', 'lodged', 'released'])
        ->and((new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'local']))->stages())
        ->toBe(['pending', 'requirements', 'appointment', 'payment', 'lodged', 'released'])
        ->and((new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'foreign']))->stages())
        ->toBe(['pending', 'requirements', 'payment', 'lodged', 'released']);
});

test('a payment cannot exceed the balance', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer, ['service_fee' => 3500, 'amount_paid' => 0]);

    $this->actingAs($officer)->post(route('visa.applications.payments', $application), ['amount' => 5000])
        ->assertSessionHasErrors('amount');

    expect((float) $application->fresh()->amount_paid)->toBe(0.0);
});

test('a philippine passport needs a valid ID and PSA birth certificate', function () {
    $application = new VisaApplication(['service_type' => 'passporting', 'passport_type' => 'local']);

    expect(collect($application->requiredDocuments())->pluck('type')->all())->toBe(['valid_id', 'birth_certificate']);
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
        'destination_country' => null,
        'purpose' => null,
        'processing_speed' => null,
    ]);

    foreach ([['Ana', 'Reyes'], ['Ben', 'Cruz']] as [$first, $last]) {
        $this->actingAs($officer)->post(route('visa.applicants.store', $application), [
            'first_name' => $first,
            'last_name' => $last,
        ])->assertRedirect();
    }

    $applicants = $application->applicants()->orderBy('applicant_number')->get();

    expect($applicants)->toHaveCount(2);
    expect($applicants[0]->applicant_number)->toBe(1);
    expect($applicants[0]->is_primary)->toBeTrue();
    expect($applicants[1]->applicant_number)->toBe(2);
    expect($applicants[1]->is_primary)->toBeFalse();
    expect($applicants[0]->full_name)->toBe('Ana Reyes');
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

    $applicant = $application->applicants()->firstOrFail();

    $this->actingAs($officer)
        ->delete(route('visa.applicants.destroy', [$application, $applicant]))
        ->assertRedirect();

    expect($application->applicants()->count())->toBe(0);
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
        'destination_country' => null,
        'purpose' => null,
        'processing_speed' => null,
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

test('the show page renders the pipeline and document checklist', function () {
    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->get(route('visa.applications.show', $application))
        ->assertOk()
        ->assertSee($application->reference)
        ->assertSee('Jane Tan')
        ->assertSee('Pipeline')
        ->assertSee('Document Checklist')
        ->assertSee('Applicants');
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
    expect($application->outstandingBalance())->toBe(0.0);
});

test('deleting a file removes it and its filed documents', function () {
    Storage::fake('local');

    $officer = visaOfficer();
    $application = openVisaFile($this, $officer);

    $this->actingAs($officer)->post(route('visa.documents.store', $application), [
        'document_type' => 'passport_scan',
        'file' => UploadedFile::fake()->image('passport.jpg'),
    ]);

    $this->actingAs($officer)
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

test('the intake form offers the fee breakdown countries', function () {
    $this->seed(VisaPricingSeeder::class);

    $this->actingAs(visaOfficer())->get('/visa-assistance/applications/create')
        ->assertOk()
        ->assertSee('Schengen (Europe)')
        ->assertSee('name="visa_fee"', false)
        ->assertSee('visaFileForm(', false);
});

test('an e-visa starts from the starting price per person', function () {
    $this->seed(VisaPricingSeeder::class);

    $this->actingAs(visaOfficer())->get('/visa-assistance/applications/create?service_type=e_visa')
        ->assertOk()
        ->assertSee('eVisaRate\u0022:3500', false);
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
        ->toBe(route('visa.applications.create', ['client' => $client->id]));

    $this->actingAs($officer)->getJson(route('visa.lookup', ['q' => 'P1112']))
        ->assertJsonPath('clients.0.name', 'Chin Chin Chin');
});

test('the counter lookup waits for two characters', function () {
    $this->actingAs(visaOfficer())->getJson(route('visa.lookup', ['q' => 'c']))
        ->assertExactJson(['files' => [], 'clients' => []]);
});

test('a new file opened for a registered client starts with their details', function () {
    $client = User::factory()->create(['role' => 'client', 'first_name' => 'Chin', 'last_name' => 'Santos', 'name' => 'Chin Santos', 'email' => 'chin@example.com', 'phone' => '09171112222']);

    $this->actingAs(visaOfficer())->get(route('visa.applications.create', ['client' => $client->id]))
        ->assertOk()
        ->assertSee('chin@example.com', false)
        ->assertSee('09171112222', false);
});

test('clients cannot use the counter lookup', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)->getJson(route('visa.lookup', ['q' => 'chin']))->assertForbidden();
});
