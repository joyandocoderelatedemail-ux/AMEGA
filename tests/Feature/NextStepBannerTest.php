<?php

use App\Models\TouristVisaApplication;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;

/** A visit visa file with one applicant, moved to the given stage. */
function fileAtStage(User $officer, string $status, array $changes = []): VisaApplication
{
    $file = VisaApplication::create(array_merge([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa',
        'destination_country' => 'Japan', 'status' => $status, 'created_by' => $officer->id, 'total_amount' => 5600, 'amount_paid' => 0,
    ], $changes));
    $file->applicants()->create(['applicant_number' => 1, 'is_primary' => true, 'first_name' => 'Jane', 'last_name' => 'Tan']);

    return $file->fresh(['applicants', 'documents']);
}

function biApplication(User $officer, array $changes = []): TouristVisaApplication
{
    return TouristVisaApplication::create(array_merge([
        'created_by' => $officer->id, 'application_method' => 'personal', 'application_mode' => 'regular', 'months_requested' => 1,
        'last_name' => 'Smith', 'first_name' => 'John', 'citizenship' => 'British', 'passport_number' => 'GB1234567', 'filed_on' => '2026-10-12',
    ], $changes));
}

test('a file with no applicant is told to add one, and the banner links to where', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = VisaApplication::create(['reference' => 'VSA-1', 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa', 'status' => 'pending', 'created_by' => $officer->id]);

    expect($file->fresh()->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Add the applicant', 'anchor' => 'applicants', 'step' => 1, 'steps' => 6]);

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('data-next-step="todo"', false)->assertSee('Next step')->assertSee('Step 1 of 6')
        ->assertSee('Add the applicant')->assertSee('Add at least one applicant to the file.')
        ->assertSee('Go to applicants')->assertSee('href="#applicants"', false)->assertSee('id="applicants"', false);
});

test('the requirements stage names what is missing, and sends the counter to the documents', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = fileAtStage($officer, 'requirements');

    expect($file->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Collect the documents', 'anchor' => 'documents', 'step' => 3])
        ->and($file->nextStep()['detail'])->toBe('Still missing: Passport copy — Jane Tan.');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Still missing: Passport copy', false)->assertSee('Go to documents')->assertSee('id="documents"', false);
});

test('a finished stage says it is ready, with the button to advance', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = fileAtStage($officer, 'requirements');
    VisaApplicationDocument::create([
        'visa_application_id' => $file->id, 'visa_applicant_id' => $file->applicants->first()->id, 'document_type' => 'passport_scan',
        'file_path' => 'x/p.pdf', 'original_name' => 'p.pdf', 'file_size' => 10, 'mime_type' => 'application/pdf', 'status' => 'uploaded',
    ]);
    $file = $file->fresh(['applicants', 'documents']);

    // The documents are in, so the Acknowledgment is what is left of this step.
    expect($file->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Get the Acknowledgment of Documents signed']);

    $file->update(['acknowledgement_signed_at' => now()]);
    $file = $file->fresh(['applicants', 'documents']);
    expect($file->nextStep())->toMatchArray(['state' => 'ready', 'title' => 'Documents is done', 'next' => 'Drafting of Forms']);

    $html = $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('data-next-step="ready"', false)->assertSee('Advance to Drafting of Forms')
        ->getContent();

    // One Advance button, under the step's work; the banner no longer repeats it.
    expect(substr_count($html, 'action="'.route('visa.applications.advance', $file).'"'))->toBe(1);
});

test('the payment stage shows the balance and links to the fees', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $file = fileAtStage($officer, 'agreement', ['total_amount' => 5600, 'amount_paid' => 0, 'agreement_signed_at' => now()]);

    expect($file->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Collect the down payment', 'anchor' => 'fees'])
        ->and($file->nextStep()['detail'])->toContain('Nothing has been paid yet');

    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Go to payment')->assertSee('id="fees"', false);
});

test('every stage that waits on work has a banner link that lands on something', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    foreach (VisaApplication::SERVICE_STAGES['visit_visa'] as $status) {
        $file = fileAtStage($officer, $status);
        $step = $file->nextStep();

        if ($step['state'] !== 'todo') {
            continue;
        }

        $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
            ->assertSee('href="#'.$step['anchor'].'"', false)
            ->assertSee('id="'.$step['anchor'].'"', false);
    }
});

test('a released file is complete and a cancelled one is off the pipeline', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);

    $released = fileAtStage($officer, 'released');
    expect($released->nextStep())->toMatchArray(['state' => 'done', 'title' => 'File complete', 'next' => null]);
    $this->actingAs($officer)->get(route('visa.applications.show', $released))->assertSee('data-next-step="done"', false)->assertSee('File complete');

    $cancelled = fileAtStage($officer, 'cancelled');
    expect($cancelled->nextStep())->toMatchArray(['state' => 'cancelled', 'title' => 'This file was cancelled']);
    $this->actingAs($officer)->get(route('visa.applications.show', $cancelled))
        ->assertSee('data-next-step="cancelled"', false)->assertDontSee('Step 1 of');
});

test('the Philippines application walks documents, fee, payment, then filing', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = biApplication($officer);

    expect($application->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Collect the documents', 'anchor' => 'checklist', 'step' => 1, 'steps' => 4])
        ->and($application->nextStep()['detail'])->toBe('Still missing: the application form (CGAF), the original passport.');

    $application->update(['checklist' => ['cgaf', 'passport']]);
    expect($application->fresh()->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Set the fee', 'anchor' => 'payment', 'step' => 2]);

    $application->update(['total_fee' => 5000]);
    expect($application->fresh()->nextStep())->toMatchArray(['state' => 'todo', 'title' => 'Collect the payment', 'step' => 3])
        ->and($application->fresh()->nextStep()['detail'])->toBe('PHP 5,000.00 is still owed.');

    $application->payments()->create(['amount' => 5000, 'method' => 'cash', 'paid_on' => '2026-10-12']);
    expect($application->fresh()->nextStep())->toMatchArray(['state' => 'ready', 'title' => 'Ready to file with the BI', 'step' => 4]);
});

test('in terms, the banner names the payment to collect', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = biApplication($officer, ['checklist' => ['cgaf', 'passport'], 'total_fee' => 10000, 'payment_type' => 'terms', 'terms_count' => 3]);
    $application->payments()->create(['amount' => 4000, 'method' => 'cash', 'paid_on' => '2026-10-12']);

    expect($application->fresh()->nextStep()['detail'])->toBe('PHP 6,000.00 is still owed. Payment 2 of 3: PHP 2,666.67 to collect.');
});

test('an overstayed applicant also needs the letter and the photo before the fee', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = biApplication($officer, ['overstayed' => true, 'checklist' => ['cgaf', 'passport'], 'total_fee' => 1000]);

    expect($application->nextStep()['detail'])->toBe('Still missing: the notarized letter of explanation, the 2x2 photo.');

    $application->update(['checklist' => ['cgaf', 'passport', 'overstay_letter']]);
    expect($application->fresh()->nextStep()['detail'])->toBe('Still missing: the 2x2 photo.');

    $application->update(['photo_path' => 'tourist-visa/x/photo.jpg']);
    expect($application->fresh()->nextStep()['title'])->toBe('Collect the payment');
});

test('the Philippines page shows the banner, and a ready application offers the prints', function () {
    $officer = User::factory()->create(['role' => 'visa_assistance']);
    $application = biApplication($officer);

    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))->assertOk()
        ->assertSee('data-next-step="todo"', false)->assertSee('Step 1 of 4')->assertSee('Collect the documents')
        ->assertSee('Go to the checklist')->assertSee('href="#checklist"', false)->assertSee('id="checklist"', false);
    // The prints are not the next step yet: only the page header offers them.
    $todo = $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))->getContent();
    expect(substr_count($todo, route('visa.tourist-visa.print-form', $application)))->toBe(1);

    $application->update(['checklist' => ['cgaf', 'passport'], 'total_fee' => 800]);
    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))
        ->assertSee('Go to payment')->assertSee('href="#payment"', false)->assertSee('id="payment"', false);

    $application->payments()->create(['amount' => 800, 'method' => 'cash', 'paid_on' => '2026-10-12']);
    $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))
        ->assertSee('data-next-step="ready"', false)->assertSee('Ready to file with the BI');
    $ready = $this->actingAs($officer)->get(route('visa.tourist-visa.edit', $application))->getContent();
    expect(substr_count($ready, route('visa.tourist-visa.print-form', $application)))->toBe(2)
        ->and(substr_count($ready, route('visa.tourist-visa.print-checklist', $application)))->toBe(2);
});
