<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Support\AmountInWords;
use App\Support\DocumentStorage;
use App\Support\VisaChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** A Non-Immigrant file on a checklist, at Receiving Documents, with one applicant. */
function checklistFile(User $officer, string $country, ?string $key, array $changes = []): VisaApplication
{
    $file = VisaApplication::create(array_merge([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa',
        'destination_country' => $country, 'checklist_key' => $key, 'purpose' => 'tourist', 'status' => 'requirements',
        'created_by' => $officer->id, 'total_amount' => 5600, 'amount_paid' => 1000,
    ], $changes));
    $file->applicants()->create(['applicant_number' => 1, 'is_primary' => true, 'first_name' => 'Jane', 'last_name' => 'Tan']);

    return $file->fresh(['applicants', 'documents']);
}

/** Tick the original of every row the applicant must hand in (one item of each group). */
function receiveRequired(VisaApplication $file): void
{
    $applicant = $file->applicants->first();
    $progress = [];

    foreach ($file->checklist()['sections'] as $section) {
        if ($section['owner'] !== 'applicant' || $section['gating'] !== true) {
            continue;
        }

        foreach ($section['rows'] as $row) {
            if (! empty($row['optional'])) {
                continue;
            }

            $id = isset($row['items']) ? $row['items'][0]['id'] : $row['id'];
            $progress['applicant:'.$applicant->id][$id] = ['orig' => true, 'copy' => false];
        }
    }

    $file->update(['checklist_progress' => $progress]);
}

test('every checklist is well formed', function () {
    expect(config('visa_checklists'))->not->toBeEmpty();

    foreach (config('visa_checklists') as $country => $variants) {
        foreach ($variants as $key => $variant) {
            $applicant = VisaChecklist::idsFor($variant, 'applicant');
            $sponsor = VisaChecklist::idsFor($variant, 'sponsor');

            expect($applicant)->toBe(array_values(array_unique($applicant)), "{$key} repeats an applicant row id")
                ->and($sponsor)->toBe(array_values(array_unique($sponsor)), "{$key} repeats a sponsor row id")
                ->and($variant['purpose'])->toBeIn(VisaApplication::PURPOSES)
                ->and(collect($variant['sections'])->pluck('key'))->toContain('applicant', 'sponsor');

            foreach ($sponsor as $id) {
                expect($id)->toStartWith('sp_');
            }
        }
    }
});

test('a destination with checklists asks for the visa type, and the type sets the purpose', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $open = fn (array $data) => $this->actingAs($officer)->post('/visa-assistance/applications', array_merge([
        'service_type' => 'visit_visa', 'client_name' => 'Jane Tan', 'destination_country' => 'Japan', 'service_fee' => 3500,
    ], $data));

    $open(['checklist_key' => 'japan_visit_relative'])->assertSessionHasNoErrors();
    $file = VisaApplication::latest('id')->firstOrFail();
    expect($file->checklist_key)->toBe('japan_visit_relative')->and($file->purpose)->toBe('family');

    // A type from another destination is refused; so is no type at all.
    $open(['checklist_key' => 'canada_tourist'])->assertSessionHasErrors('checklist_key');
    $open([])->assertSessionHasErrors('purpose');

    // A destination with no checklist keeps the plain purpose.
    $open(['destination_country' => 'Dubai', 'purpose' => 'business'])->assertSessionHasNoErrors();
    expect(VisaApplication::latest('id')->firstOrFail())->checklist_key->toBeNull()->purpose->toBe('business');
});

test('the intake form offers the visa types and previews the checklist', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $html = $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Japan']))->assertOk()->getContent();

    expect($html)->toContain('name="checklist_key"')
        ->and($html)->toContain('japan_sofa')->and($html)->toContain('SOFA Status')
        ->and($html)->toContain('Original current and old passport');
});

test('a country chosen on the country page is shown, not asked again', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $html = $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Canada']))->assertOk()->getContent();
    expect($html)->toContain('id="destination_choice" x-model="choice" @change="pickCountry()"  hidden')
        ->and($html)->toContain('Change country')
        ->and($html)->toContain('Choose the visa type: its checklist and the estimate follow.');

    // Opened without a country, the destination is still a choice.
    $plain = $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa']))->getContent();
    expect($plain)->not->toContain('@change="pickCountry()"  hidden');
});

test('the receiving documents step waits for the applicant checklist, not the sponsor', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');

    expect($file->stageBlocker())->toStartWith('Still missing: ')
        ->and($file->checklistMissing())->toContain('Original current and old passport — Jane Tan')
        ->and($file->checklistMissing())->toContain('Proof of income (any one) — Jane Tan')
        // Optional rows and the sponsor and minor sections never hold an adult's file.
        ->and(implode(' ', $file->checklistMissing()))->not->toContain('Invitation letter')
        ->and(implode(' ', $file->checklistMissing()))->not->toContain('School registration')
        ->and(implode(' ', $file->checklistMissing()))->not->toContain('PRC ID');

    receiveRequired($file);

    // The documents are in; the Acknowledgment finishes the step.
    expect($file->fresh(['applicants', 'documents'])->stageBlocker())->toBe('Record that the client signed the Acknowledgment of Documents.');
    $file->update(['acknowledgement_signed_at' => now()]);
    expect($file->fresh(['applicants', 'documents'])->stageBlocker())->toBeNull();
});

test('a minor must also hand in the minor documents', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    $file->applicants->first()->update(['date_of_birth' => now()->subYears(10)]);
    receiveRequired($file);
    $file = $file->fresh(['applicants', 'documents']);

    expect($file->checklistMissing())->toContain('School registration — Jane Tan');
});

test('an uploaded passport scan counts as the passport copy', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    expect($file->checklistMissing())->toContain('Copy of passport: bio page and all used pages with amendments, visas and entry/exit stamps (current and old) — Jane Tan');

    VisaApplicationDocument::create([
        'visa_application_id' => $file->id, 'visa_applicant_id' => $file->applicants->first()->id, 'document_type' => 'passport_scan',
        'file_path' => 'x/p.pdf', 'original_name' => 'p.pdf', 'file_size' => 10, 'mime_type' => 'application/pdf', 'status' => 'uploaded',
    ]);

    expect(implode(' ', $file->fresh(['applicants', 'documents'])->checklistMissing()))->not->toContain('Copy of passport');
});

test('ticking saves, either box counts, and unknown rows are refused', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    $owner = 'applicant:'.$file->applicants->first()->id;
    $tick = fn (array $data) => $this->actingAs($officer)->postJson(route('visa.applications.checklist', $file), $data);

    $tick(['owner' => $owner, 'item' => 'orig_passport', 'column' => 'copy'])->assertOk()->assertJson(['on' => true]);
    expect($file->fresh(['applicants', 'documents'])->checklistMissing())->not->toContain('Original current and old passport — Jane Tan');

    // Ticking again unticks.
    $tick(['owner' => $owner, 'item' => 'orig_passport', 'column' => 'copy'])->assertJson(['on' => false]);

    // The sponsor's rows can be ticked; a row from the wrong side, or another file's applicant, cannot.
    $tick(['owner' => 'sponsor', 'item' => 'sp_invitation', 'column' => 'orig'])->assertOk();
    $tick(['owner' => 'sponsor', 'item' => 'orig_passport', 'column' => 'orig'])->assertStatus(422);
    $tick(['owner' => 'applicant:999999', 'item' => 'orig_passport', 'column' => 'orig'])->assertStatus(422);
    $tick(['owner' => $owner, 'item' => 'orig_passport', 'column' => 'sideways'])->assertStatus(422);
});

test('the acknowledgment receipt lists what was received for applicant and sponsor, and what is requested', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    $owner = 'applicant:'.$file->applicants->first()->id;
    $file->update(['checklist_progress' => [
        $owner => ['orig_passport' => ['orig' => true, 'copy' => true], 'photos' => ['orig' => false, 'copy' => true]],
        'sponsor' => ['sp_invitation' => ['orig' => true, 'copy' => false]],
    ], 'additional_requests' => [['label' => 'Bank certificate with ADB', 'requested_at' => now()->toIso8601String(), 'received_at' => null]]]);

    $this->actingAs($officer)->get(route('visa.applications.acknowledgement', $file))
        ->assertOk()
        ->assertSee('Acknowledgment receipt of documents')->assertSee('Sponsor / Inviter')
        ->assertSee('Original current and old passport')->assertSee('(original and copy)')
        ->assertSee('(copy)')->assertSee('Invitation letter')->assertSee('(original)')
        ->assertSee('Request documents')->assertSee('Bank certificate with ADB')
        ->assertSee('Prepared by:');
});

test('a file prints its checklist like the counter sheet, one page per applicant, with received rows ticked', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_visit_relative', ['purpose' => 'family']);
    $file->applicants()->create(['applicant_number' => 2, 'first_name' => 'Ken', 'last_name' => 'Tan']);
    $owner = 'applicant:'.$file->applicants->first()->id;
    $file->update(['checklist_progress' => [
        $owner => ['orig_passport' => ['orig' => true, 'copy' => false], 'pay_slip' => ['orig' => false, 'copy' => true]],
        'sponsor' => ['sp_guarantee' => ['orig' => true, 'copy' => false]],
    ]]);

    $html = $this->actingAs($officer)->get(route('visa.applications.checklist.print', $file))->assertOk()
        ->assertSee('JAPAN VISIT RELATIVE VISA CHECKLIST')
        ->assertSee('REQUIREMENTS: <strong>For Applicant</strong>', false)->assertSee('REQUIREMENTS: <strong>For Sponsor</strong>', false)
        ->assertSee('REQUIREMENTS: <strong>For Minor</strong>', false)
        ->assertSee('PROOF OF INCOME')->assertSee('Proof of residence (Juminhiyo)')
        ->assertSee('Jane Tan')->assertSee('Ken Tan')->assertSee($file->reference)
        ->assertSee('09176264181 / 09929225735')
        ->getContent();

    // Jane's sheet has three ticks (passport orig, pay slip copy, sponsor guarantee); Ken's has the sponsor's only.
    expect(substr_count($html, '<span class="tick">✓</span>'))->toBe(4)
        ->and(substr_count($html, 'class="sheet"'))->toBe(2);
});

test('a blank checklist prints for any visa type, and a file without one cannot print', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.checklists.blank', 'us_tourist_business'))->assertOk()
        ->assertSee('US TOURIST / BUSINESS VISA CHECKLIST')->assertSee('Documents to prepare before interview (optional)')
        ->assertSee('Fees PHP 21,000', false)->assertDontSee('✓');
    $this->actingAs($officer)->get(route('visa.checklists.blank', 'germany_visit_relative'))->assertOk()
        ->assertSee('GERMANY VISIT RELATIVE VISA CHECKLIST');
    $this->actingAs($officer)->get(route('visa.checklists.blank', 'mars_tourist'))->assertNotFound();

    $file = checklistFile($officer, 'Dubai', null);
    $this->actingAs($officer)->get(route('visa.applications.checklist.print', $file))->assertRedirect()->assertSessionHas('error');

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Japan']))
        ->assertSee('Print blank checklist');
});

test('after choosing a country, its checklists can be printed straight from the page', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Japan']))->assertOk()
        ->assertSee('Japan checklists')
        ->assertSee(route('visa.checklists.blank', 'japan_tourist'), false)
        ->assertSee(route('visa.checklists.blank', 'japan_visit_relative'), false)
        ->assertSee(route('visa.checklists.blank', 'japan_sofa'), false);

    // A country without checklists shows none.
    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Dubai']))->assertOk()
        ->assertDontSee('Dubai checklists');
});

test('a Non-Immigrant file takes uploads only at Receiving Documents, on the checklist rows', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');

    foreach (VisaApplication::SERVICE_STAGES['visit_visa'] as $stage) {
        $file->update(['status' => $stage]);
        $response = $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk();

        // With a checklist the scans go on its rows; the old upload form is gone.
        $response->assertDontSee('File Document');
        $stage === 'requirements' ? $response->assertSee('or attach its scan right on the row') : $response->assertDontSee('or attach its scan right on the row');
    }

    // A Non-Immigrant file with no checklist keeps the upload form at Receiving Documents.
    $plain = checklistFile($officer, 'Dubai', null);
    $this->actingAs($officer)->get(route('visa.applications.show', $plain))->assertSee('id="documents"', false)->assertSee('File Document');

    // Other services keep it on every stage.
    $eVisa = VisaApplication::create(['reference' => 'VSA-EV1', 'client_name' => 'Ana', 'service_type' => 'e_visa', 'status' => 'pending', 'created_by' => $officer->id]);
    $this->actingAs($officer)->get(route('visa.applications.show', $eVisa))->assertSee('id="documents"', false);
});

test('the service agreement prints filled from the file and the client profile', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance', 'name' => 'Zam Reyes']);
    User::factory()->create(['role' => 'client', 'name' => 'Jane Tan', 'email' => 'jane@example.com', 'nationality' => 'Filipino', 'address_line' => 'Clarkhills Haus D', 'city' => 'Angeles City', 'province' => 'Pampanga']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist', [
        'client_email' => 'jane@example.com', 'service_fee' => 3500, 'visa_fee' => 2100, 'total_amount' => 5600, 'amount_paid' => 2000, 'status' => 'agreement',
    ]);

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertSee(route('visa.applications.agreement', $file), false);

    $this->actingAs($officer)->get(route('visa.applications.agreement', $file))->assertOk()
        ->assertSee('Service Engagement Agreement')
        ->assertSee('JANE TAN')->assertSee('FILIPINO')->assertSee('CLARKHILLS HAUS D, ANGELES CITY, PAMPANGA')
        ->assertSee('TOURIST VISA')->assertSee('JAPAN')
        ->assertSee('FIVE THOUSAND SIX HUNDRED PESOS')->assertSee('PhP 5,600.00')
        ->assertSee('PhP 2,000.00')->assertSee('PhP 3,600.00')
        ->assertSee('Zam Reyes')
        ->assertSee('images/visa/agreement-letterhead.png', false);
});

test('amounts are spelled out in pesos', function () {
    expect(AmountInWords::pesos(90000))->toBe('NINETY THOUSAND PESOS')
        ->and(AmountInWords::pesos(21500.5))->toBe('TWENTY-ONE THOUSAND FIVE HUNDRED PESOS AND FIFTY CENTAVOS')
        ->and(AmountInWords::pesos(1))->toBe('ONE PESO')
        ->and(AmountInWords::pesos(1250000))->toBe('ONE MILLION TWO HUNDRED FIFTY THOUSAND PESOS');
});

test('the next-step banner sums up a long list of missing documents', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist');
    $missing = $file->checklistMissing();

    expect($file->nextStep())
        ->toMatchArray(['state' => 'todo', 'title' => 'Collect the documents', 'detail' => count($missing).' documents still missing for Jane Tan.'])
        ->and($file->nextStep()['items'])->toContain('Original current and old passport', 'Government ID');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee(count($missing).' documents still missing for Jane Tan.')
        ->assertSee('+ '.(count($missing) - 6).' more in the checklist below')
        ->assertDontSee('text-slate-700">Still missing:', false);
});

test('the next-step banner lists open country steps instead of one long sentence', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist', ['status' => 'drafting']);

    expect($file->nextStep())->toMatchArray(['title' => 'Finish Drafting of Forms', 'detail' => '6 steps still to do.'])
        ->and($file->nextStep()['items'])->toContain('IRCC account (GCKey) created');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('6 steps still to do.')
        ->assertDontSee('text-slate-700">Still to do:', false);
});

test('country steps tick in place without a reload, and say when the last one is done', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist', ['status' => 'pending']);
    $tick = fn (string $task) => $this->actingAs($officer)->postJson(route('visa.applications.tasks', [$file, $task]));

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Canada steps')->assertSee('visaTasks(', false);

    $tick('return_intent')->assertOk()->assertJson(['done' => true, 'open' => 2]);
    $tick('funds')->assertJson(['done' => true, 'open' => 1]);
    $tick('no_work_intent')->assertJson(['done' => true, 'open' => 0]);
    $tick('funds')->assertJson(['done' => false, 'open' => 1]);
    $tick('ircc_account')->assertStatus(422);
});

test('the receiving documents step shows the ORIG and COPY table', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))
        ->assertOk()
        ->assertSee('Canada &middot; Tourist checklist', false)->assertSee('Print checklist')
        ->assertSee('Proof of financial support')->assertSee('Tracked only: never holds the file')
        ->assertSee('Other document (not on the checklist)')->assertSee('Jane Tan');
});

test('a scan attached to a checklist row counts it as received, and can be removed', function () {
    Storage::fake(DocumentStorage::diskName());
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    $owner = 'applicant:'.$file->applicants->first()->id;
    $upload = fn (array $data) => $this->actingAs($officer)->post(route('visa.applications.checklist.upload', $file), $data, ['Accept' => 'application/json']);

    $response = $upload(['owner' => $owner, 'item' => 'psa_birth', 'file' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf')])
        ->assertOk()->assertJsonPath('file.name', 'birth.pdf');
    expect(implode(' ', $file->fresh(['applicants', 'documents'])->checklistMissing()))->not->toContain('PSA birth certificate');

    // The sponsor's scans have no applicant; extra documents go under "_other"; unknown rows and other file types are refused.
    $upload(['owner' => 'sponsor', 'item' => 'sp_invitation', 'file' => UploadedFile::fake()->image('invite.jpg')])->assertOk();
    $upload(['owner' => $owner, 'item' => '_other', 'file' => UploadedFile::fake()->image('extra.jpg')])->assertOk();
    $upload(['owner' => $owner, 'item' => 'not_a_row', 'file' => UploadedFile::fake()->image('x.jpg')])->assertStatus(422);
    $upload(['owner' => $owner, 'item' => 'psa_birth', 'file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])->assertStatus(422);

    expect(VisaApplicationDocument::where('checklist_item', 'sp_invitation')->first()->visa_applicant_id)->toBeNull();

    // The scan opens in the browser, and the printed checklist ticks it (and the sponsor's) under COPY.
    $document = VisaApplicationDocument::where('checklist_item', 'psa_birth')->first();
    $this->actingAs($officer)->get(route('visa.documents.download', ['document' => $document, 'inline' => 1]))->assertOk();
    expect(substr_count($this->actingAs($officer)->get(route('visa.applications.checklist.print', $file))->getContent(), '<span class="tick">✓</span>'))->toBe(2);

    $this->actingAs($officer)->deleteJson($response->json('file.delete_url'))->assertOk();
    expect(VisaApplicationDocument::find($document->id))->toBeNull()
        ->and($file->fresh(['applicants', 'documents'])->checklistMissing())->toContain('PSA birth certificate, issued within a year (late registered: baptismal certificate and Form 137) — Jane Tan');
});
