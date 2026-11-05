<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Support\VisaWorksheets;
use App\Support\VisaWorksheetSpecs;

/**
 * AMEGA's country application forms (China, New Zealand, Australia tourist and business, Canada and its eTA, the UK,
 * the US): each prints blank, is filled in per applicant on the file, and prints with the answers.
 */
function vcfFile(User $agent, string $country, array $file = []): VisaApplication
{
    $application = VisaApplication::withoutGlobalScopes()->forceCreate($file + [
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'created_by' => $agent->id, 'service_type' => 'visit_visa',
        'status' => 'drafting', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com', 'destination_country' => $country,
        'approval_status' => 'approved', 'purpose' => 'tourist',
    ]);
    $application->applicants()->create(['applicant_number' => 1, 'first_name' => 'Jane', 'last_name' => 'Tan', 'is_primary' => true, 'passport_number' => 'P7654321A', 'nationality' => 'Filipino']);

    return $application;
}

test('every country form prints blank, with its heading and its sections', function (string $form) {
    $agent = User::factory()->create(['role' => 'visa_assistance']);
    [, $heading] = VisaWorksheetSpecs::META[$form];

    $page = $this->actingAs($agent)->get(route('visa.worksheets.blank', $form))->assertOk()
        ->assertSee($heading)->assertSee('Applicant')->assertSee('Passport number');
    foreach (array_keys(VisaWorksheets::sections($form)) as $section) {
        $page->assertSee($section);
    }
    $page->assertDontSee('P7654321A');
})->with(array_keys(VisaWorksheetSpecs::META));

test('each destination gets its form, Australia business trips the business form', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);

    expect(VisaWorksheets::forFile(vcfFile($agent, 'China')))->toBe('china')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'New Zealand')))->toBe('new_zealand')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'Australia')))->toBe('australia_tourist')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'Australia', ['purpose' => 'business'])))->toBe('australia_business')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'Canada')))->toBe('canada')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'United Kingdom')))->toBe('uk')
        ->and(VisaWorksheets::forFile(vcfFile($agent, 'USA')))->toBe('usa')
        ->and(VisaWorksheets::formsForCountry('Canada'))->toBe(['canada', 'canada_eta']);
});

test('a China form is filled in on the file and prints with the answers', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);
    $file = vcfFile($agent, 'China');
    $applicant = $file->applicants()->first();
    $file->update(['status' => 'pending']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Fill in draft form')->assertSee(route('visa.worksheets.blank', 'china'), false);
    $this->actingAs($agent)->get(route('visa.worksheets.edit', [$file, $applicant]))->assertOk()
        ->assertSee('China visa application form')->assertSee('Contact in China')->assertSee('Travel itinerary');

    $this->actingAs($agent)->put(route('visa.worksheets.update', [$file, $applicant]), ['form' => [
        'applying_for' => 'tourist', 'arrival_city' => 'Shanghai', 'arrival_date' => '2026-12-20', 'had_china_visa' => 'yes',
        'companion1_family' => 'Cruz', 'father_name' => 'Pedro Tan', 'cn_sex' => 'female',
    ]])->assertSessionHas('success');

    $this->actingAs($agent)->get(route('visa.worksheets.print', [$file, $applicant]))->assertOk()
        ->assertSee('CHINA APPLICATION FORM')->assertSee('Shanghai')->assertSee('20-12-2026')->assertSee('Pedro Tan')
        ->assertSee('☑ Yes', false)->assertSee('☑ Tourist', false)->assertSee('P7654321A')->assertSee('Tan');

    // A wrong choice is refused.
    $this->actingAs($agent)->put(route('visa.worksheets.update', [$file, $applicant]), ['form' => ['applying_for' => 'nonsense']])->assertSessionHasErrors('form.applying_for');
});

test('the wizard and the file offer every blank form of the country', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);
    $file = vcfFile($agent, 'Canada');

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee(route('visa.worksheets.blank', 'canada'), false)->assertSee(route('visa.worksheets.blank', 'canada_eta'), false);
    $this->actingAs($agent)->get(route('visa.visit.file'))->assertOk()
        ->assertSee(route('visa.worksheets.blank', 'australia_business'), false)->assertSee(route('visa.worksheets.blank', 'usa'), false);
});

test('Step 1 shows each applicant draft form; it holds the step until filled, and prints after approval', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);
    $file = vcfFile($agent, 'USA', ['status' => 'pending', 'approval_status' => 'pending']);
    $applicant = $file->applicants()->first();

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('data-draft-forms', false)->assertSee('Draft form: U.S. visa application form')
        ->assertSee('Not filled in yet')->assertSee('Print after the admin approves')
        ->assertDontSee('href="'.route('visa.worksheets.print', [$file, $applicant]).'"', false);
    expect($file->fresh(['applicants'])->draftFormsMissing())->toBe(['Jane Tan']);

    $this->actingAs($agent)->get(route('visa.worksheets.edit', [$file, $applicant]))->assertOk()
        ->assertSee('Draft form: U.S. visa application form')->assertDontSee('Save &amp; print', false);
    $this->actingAs($agent)->put(route('visa.worksheets.update', [$file, $applicant]), ['form' => ['us_places' => 'New York']])
        ->assertSessionHas('success', 'Draft form saved for Jane Tan.');

    $file->forceFill(['approval_status' => 'approved'])->save();
    $fresh = $file->fresh(['applicants']);
    expect($fresh->draftFormsMissing())->toBe([])->and((string) $fresh->stageBlocker())->not->toContain('draft form');
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Filled in')
        ->assertSee('href="'.route('visa.worksheets.print', [$file, $applicant]).'"', false);

    // A destination without a form has no draft form to wait for.
    expect(vcfFile($agent, 'Dubai', ['status' => 'pending'])->fresh(['applicants'])->draftFormsMissing())->toBe([]);
});

test('the wizard requires the draft form filled in before the file can be sent to the admin', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);

    // No draft form data at all: refused, and nothing is created.
    $this->actingAs($agent)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com',
        'destination_country' => 'China', 'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ])->assertSessionHasErrors('form_china');
    expect(VisaApplication::where('client_name', 'Jane Tan')->exists())->toBeFalse();

    // Filled in: the file is created, already carrying the draft form (before the admin ever sees it).
    $this->actingAs($agent)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com',
        'destination_country' => 'China', 'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
        'form_china' => ['arrival_city' => 'Shanghai', 'applying_for' => 'tourist'],
    ])->assertRedirect();

    $file = VisaApplication::where('client_name', 'Jane Tan')->firstOrFail();
    $applicant = $file->applicants()->firstOrFail();
    expect($applicant->form_details)->toMatchArray(['arrival_city' => 'Shanghai', 'applying_for' => 'tourist'])
        ->and($file->draftFormsMissing())->toBe([])
        ->and($file->approval_status)->toBe('pending');

    // A destination with no application form (Dubai) has nothing to fill in, and is never blocked by it.
    $this->actingAs($agent)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'No Form Needed', 'client_email' => 'nf@example.com',
        'destination_country' => 'Dubai', 'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ])->assertRedirect();
    expect(VisaApplication::where('client_name', 'No Form Needed')->exists())->toBeTrue();
});

test('an admin opening a Non-Immigrant file must fill in the draft form too', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'Admin Client', 'destination_country' => 'Korea',
        'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ])->assertSessionHasErrors('form_korea');
    expect(VisaApplication::where('client_name', 'Admin Client')->exists())->toBeFalse();
});

test('the wizard shows the resolved Australia business form, not the tourist one, for a business trip', function () {
    $agent = User::factory()->create(['role' => 'visa_assistance']);

    $this->actingAs($agent)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'Biz Traveller', 'client_email' => 'biz@example.com',
        'destination_country' => 'Australia', 'purpose' => 'business', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ])->assertSessionHasErrors('form_australia_business');

    $this->actingAs($agent)->post(route('visa.applications.store'), [
        'service_type' => 'visit_visa', 'client_name' => 'Biz Traveller', 'client_email' => 'biz@example.com',
        'destination_country' => 'Australia', 'purpose' => 'business', 'processing_speed' => 'regular', 'service_fee' => 3500,
        'form_australia_business' => ['phone' => '0917 000 0000'],
    ])->assertRedirect();

    $file = VisaApplication::where('client_name', 'Biz Traveller')->firstOrFail();
    expect(VisaWorksheets::forFile($file))->toBe('australia_business')
        ->and($file->applicants()->firstOrFail()->form_details)->toBe(['phone' => '0917 000 0000']);
});
