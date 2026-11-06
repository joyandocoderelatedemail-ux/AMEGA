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

beforeEach(fn () => autoApproveDeskFiles());

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

    $html = $this->actingAs($officer)->get(route('visa.visit.file', ['country' => 'Japan']))->assertOk()->getContent();

    expect($html)->toContain('name="checklist_key"')
        ->and($html)->toContain('japan_sofa')->and($html)->toContain('SOFA Status')
        ->and($html)->toContain('Original current and old passport');
});

test('a country passed in starts the wizard with that destination picked; the old form path opens the wizard', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.visit.file', ['country' => 'Canada']))->assertOk()
        ->assertSee('\u0022destination\u0022:\u0022Canada\u0022', false);
    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Canada']))
        ->assertRedirect(route('visa.visit.file', ['country' => 'Canada']));
});

test('the receiving documents step requires no documents: it waits only for the Acknowledgment', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    // Every destination alike, with or without a country checklist: no document is required.
    foreach ([['Japan', 'japan_tourist'], ['Canada', 'canada_tourist'], ['USA', null], ['Dubai', null]] as [$country, $key]) {
        $file = checklistFile($officer, $country, $key);

        expect($file->missingDocuments())->toBe([])->and($file->requiredDocuments())->toBe([])
            ->and($file->stageBlocker())->toBe('Record that the client signed the Acknowledgment of Documents.')
            ->and($file->nextStep())->toMatchArray(['title' => 'Get the Acknowledgment of Documents signed']);

        $file->update(['acknowledgement_signed_at' => now()]);
        expect($file->fresh(['applicants', 'documents'])->stageBlocker())->toBeNull();
    }
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

test('the acknowledgment receipt lists the documents filed on the file', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Japan', 'japan_tourist');
    VisaApplicationDocument::create([
        'visa_application_id' => $file->id, 'visa_applicant_id' => $file->applicants->first()->id, 'document_type' => 'passport_scan',
        'file_path' => 'x/p.pdf', 'original_name' => 'p.pdf', 'file_size' => 10, 'mime_type' => 'application/pdf', 'status' => 'uploaded',
    ]);

    $this->actingAs($officer)->get(route('visa.applications.acknowledgement', $file))
        ->assertOk()
        ->assertSee('Documents received')->assertSee('Jane Tan')->assertSee('Passport Scan')
        ->assertDontSee('Sponsor / Inviter')->assertDontSee('Request documents')
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

    $this->actingAs($officer)->get(route('visa.visit.file', ['country' => 'Japan']))
        ->assertSee('Print checklist');
});

test('after choosing a country, the wizard offers its visa types, each with a blank checklist to print', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($officer)->get(route('visa.visit.file', ['country' => 'Japan']))->assertOk()
        ->assertSee('japan_tourist')->assertSee('japan_visit_relative')->assertSee('japan_sofa')
        ->assertSee('Print checklist');
});

test('a Non-Immigrant file takes uploads only at Receiving Documents, in the plain Document Checklist', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    // With or without a country checklist, the same upload form shows at Receiving Documents and nowhere else.
    foreach ([['Japan', 'japan_tourist'], ['Dubai', null]] as [$country, $key]) {
        $file = checklistFile($officer, $country, $key);

        foreach (VisaApplication::SERVICE_STAGES['visit_visa'] as $stage) {
            $file->update(['status' => $stage]);
            $response = $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
                ->assertDontSee('or attach its scan right on the row');

            $stage === 'requirements'
                ? $response->assertSee('id="documents"', false)->assertSee('Document Checklist')->assertSee('File Document')
                    // Not filed per applicant: no Applicant picker; an Other document says what it is in Remarks.
                    ->assertDontSee('name="visa_applicant_id"', false)->assertSee('name="remarks"', false)->assertSee("type === 'other'", false)
                : $response->assertDontSee('File Document');
        }
    }
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

test('the service agreement lists only what the client availed; the blank one keeps the whole list', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $admin = User::factory()->create(['role' => 'admin']);
    $file = checklistFile($officer, 'USA', null, ['service_fee' => 19200, 'visa_fee' => 22800, 'total_amount' => 42000, 'status' => 'agreement']);
    $print = fn () => $this->actingAs($admin)->get(route('visa.applications.agreement', $file->fresh()))->assertOk();

    // Two travellers priced on the wizard, though only the client is an applicant so far.
    $page = $print()->assertSee('USA')->assertSee('PhP 21,000.00')->assertSee('PhP 42,000.00')
        ->assertDontSee('United Kingdom')->assertDontSee('Php 18,500')->assertDontSee('Immigrant Visa:')->assertDontSee('NVC Stage')->assertDontSee('US Immigrant')
        ->assertDontSee('Php5,000')->assertDontSee('Check the services availed')
        ->getContent();
    expect($page)->toContain('<td class="mid">2</td>');

    // Rush shows only when it was availed.
    $file->update(['rush_fee' => 5000, 'total_amount' => 47000]);
    $print()->assertSee('PhP 5,000.00')->assertSee('PhP 47,000.00');

    // A fee adjusted by hand still adds up: one applicant at the file's own amount.
    $file->update(['service_fee' => 9000, 'visa_fee' => 11400, 'rush_fee' => 0, 'total_amount' => 20400]);
    $print()->assertSee('PhP 20,400.00')->assertDontSee('PhP 21,000.00');

    // A country off the list shows under its own name, and another service without the Tourist Visa heading.
    $file->update(['destination_country' => 'Dubai']);
    $print()->assertSee('Dubai')->assertDontSee('Others:');
    $file->update(['service_type' => 'passporting', 'destination_country' => null, 'service_fee' => 1500, 'visa_fee' => 2000, 'total_amount' => 3500]);
    $print()->assertSee('Passporting')->assertSee('PhP 3,500.00')->assertDontSee('Tourist Visa:');

    // The blank one is still the whole list, to tick by hand.
    $this->actingAs($officer)->get(route('visa.agreement-blank'))->assertOk()
        ->assertSee('United Kingdom')->assertSee('Php 21,000')->assertSee('Immigrant Visa:')->assertSee('NVC Stage')->assertSee('Check the services availed');
});

test('the Non-Immigrant fees step shows the country fee and total', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $page = $this->actingAs($officer)->get(route('visa.visit.file'))->assertOk()->assertSee('data-fee-summary', false)->getContent();
    expect($page)->toContain('countryChanged()');
});

test('amounts are spelled out in pesos', function () {
    expect(AmountInWords::pesos(90000))->toBe('NINETY THOUSAND PESOS')
        ->and(AmountInWords::pesos(21500.5))->toBe('TWENTY-ONE THOUSAND FIVE HUNDRED PESOS AND FIFTY CENTAVOS')
        ->and(AmountInWords::pesos(1))->toBe('ONE PESO')
        ->and(AmountInWords::pesos(1250000))->toBe('ONE MILLION TWO HUNDRED FIFTY THOUSAND PESOS');
});

test('the next-step banner no longer lists missing documents', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist');

    expect($file->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Get the Acknowledgment of Documents signed'])
        ->and($file->nextStep())->not->toHaveKey('items');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('documents still missing')->assertDontSee('Still missing:');
});

test('the Non-Immigrant file shows no country step checkboxes on any step', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    foreach (['Canada', 'USA', 'Japan'] as $country) {
        foreach (['pending', 'agreement', 'drafting'] as $status) {
            $file = checklistFile($officer, $country, null, ['status' => $status]);

            expect($file->nextStep())->not->toHaveKey('items');
            $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
                ->assertDontSee($country.' steps')->assertDontSee('steps still to do.')->assertDontSee('Still to do:')->assertDontSee('visaTasks(', false);
        }
    }
});

test('the receiving documents step shows the plain Document Checklist, not the ORIG and COPY table', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = checklistFile($officer, 'Canada', 'canada_tourist');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))
        ->assertOk()
        ->assertSeeInOrder(['Document Checklist', 'Nothing filed yet.', 'File Document', 'Current step', 'Receiving of Documents'])->assertSee('Nothing filed yet.')->assertSee('File Document')->assertSee('Acknowledgment of Documents')
        ->assertDontSee('Canada &middot; Tourist checklist', false)->assertDontSee('Proof of financial support')
        ->assertDontSee('Tracked only: never holds the file')->assertDontSee('Other document (not on the checklist)');
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
