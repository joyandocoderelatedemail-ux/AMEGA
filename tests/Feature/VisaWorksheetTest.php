<?php

use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;

function worksheetOfficer(): User
{
    return User::factory()->create(['role' => 'visa_assistance']);
}

/** A Non-Immigrant file for a destination, with its primary applicant. */
function worksheetFile(User $officer, string $country = 'Schengen (Europe)'): VisaApplication
{
    $file = VisaApplication::create([
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com',
        'client_phone' => '0917 555 0101', 'service_type' => 'visit_visa', 'destination_country' => $country, 'purpose' => 'tourist',
        'status' => 'requirements', 'created_by' => $officer->id,
    ]);
    $file->applicants()->create([
        'applicant_number' => 1, 'is_primary' => true, 'first_name' => 'Jane', 'middle_name' => 'Cruz', 'last_name' => 'Tan',
        'date_of_birth' => '1990-04-12', 'gender' => 'female', 'nationality' => 'Filipino', 'passport_number' => 'P1234567A',
        'passport_expiry_date' => '2031-01-20', 'passport_country' => 'Philippines',
    ]);

    return $file->fresh('applicants');
}

test('a Schengen applicant gets a worksheet, pre-filled from the file the first time', function () {
    $officer = worksheetOfficer();
    $file = worksheetFile($officer);
    $applicant = $file->applicants->first();

    $this->actingAs($officer)->get(route('visa.applications.show', $file))
        ->assertSee(route('visa.worksheets.edit', [$file, $applicant]), false)->assertSee('Fill in worksheet');

    $this->actingAs($officer)->get(route('visa.worksheets.edit', [$file, $applicant]))->assertOk()
        ->assertSee('Schengen visa worksheet')->assertSee('P1234567A')
        ->assertSee('value="jane@example.com"', false)->assertSee('value="Philippines"', false);
});

test('the worksheet saves and prints with the applicant record', function () {
    $officer = worksheetOfficer();
    $file = worksheetFile($officer);
    $applicant = $file->applicants->first();

    $this->actingAs($officer)->put(route('visa.worksheets.update', [$file, $applicant]), ['then' => 'print', 'form' => [
        'place_of_birth' => 'Quezon City', 'country_of_birth' => 'Philippines', 'civil_status' => 'single', 'occupation' => 'Nurse',
        'purpose' => 'tourism', 'main_destination' => 'France', 'first_entry' => 'France', 'entries' => 'single',
        'arrival_date' => '2027-05-01', 'departure_date' => '2027-05-14', 'hotel_name' => 'Hotel Lutetia', 'cost_covered_by' => 'self', 'religion' => '',
    ]])->assertRedirect(route('visa.worksheets.print', [$file, $applicant]));

    expect($applicant->fresh()->detail('main_destination'))->toBe('France');

    $this->actingAs($officer)->get(route('visa.worksheets.print', [$file, $applicant]))->assertOk()
        ->assertSee('APPLICATION FOR SCHENGEN VISA')
        ->assertSee('Tan')->assertSee('Jane Cruz')->assertSee('12-04-1990')->assertSee('P1234567A')->assertSee('20-01-2031')
        ->assertSee('Quezon City')->assertSee('Single')->assertSee('Tourism')->assertSee('01-05-2027')->assertSee('Single entry')
        ->assertSee('Hotel Lutetia')->assertSee('By the applicant himself/herself');

    $this->actingAs($officer)->put(route('visa.worksheets.update', [$file, $applicant]), ['form' => ['entries' => 'unlimited']])
        ->assertSessionHasErrors('form.entries');
});

test('no worksheet for a country without one, nor for an applicant of another file', function () {
    $officer = worksheetOfficer();
    $japan = worksheetFile($officer, 'Japan');
    $schengen = worksheetFile($officer);

    $this->actingAs($officer)->get(route('visa.worksheets.edit', [$japan, $japan->applicants->first()]))->assertNotFound();
    $this->actingAs($officer)->get(route('visa.applications.show', $japan))->assertDontSee('Fill in worksheet');
    $this->actingAs($officer)->get(route('visa.worksheets.print', [$schengen, $japan->applicants->first()]))->assertNotFound();
});

test('a blank Schengen worksheet prints from the new-file page', function () {
    $officer = worksheetOfficer();

    $this->actingAs($officer)->get(route('visa.worksheets.blank', 'schengen'))->assertOk()
        ->assertSee('APPLICATION FOR SCHENGEN VISA')->assertDontSee('P1234567A');
    $this->actingAs($officer)->get(route('visa.applications.create', ['service_type' => 'visit_visa', 'country' => 'Schengen (Europe)']))
        ->assertSee(route('visa.worksheets.blank', 'schengen'), false);
    $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('visa.worksheets.blank', 'schengen'))->assertRedirect();

    expect(VisaApplicant::query()->count())->toBe(0);
});

test('a Korea applicant fills and prints the Korea worksheet', function () {
    $officer = worksheetOfficer();
    $file = worksheetFile($officer, 'Korea');
    $applicant = $file->applicants->first();

    $this->actingAs($officer)->get(route('visa.worksheets.edit', [$file, $applicant]))->assertOk()
        ->assertSee('Korea visa worksheet')->assertSee('Work information')->assertSee('Invitation');

    $this->actingAs($officer)->put(route('visa.worksheets.update', [$file, $applicant]), ['form' => [
        'civil_status' => 'married', 'purpose' => 'tourism', 'arrival_date' => '2027-03-10',
        'job_title' => 'Accountant', 'work_city' => 'Makati City', 'work_start_date' => '2019-06-01',
        'school_name' => 'University of Santo Tomas', 'course' => 'BS Accountancy',
        'spouse_surname' => 'Tan', 'spouse_first_name' => 'Mark', 'has_children' => 'yes', 'children_count' => '2',
        'invited' => 'no',
    ]])->assertRedirect(route('visa.applications.show', $file));

    $this->actingAs($officer)->get(route('visa.worksheets.print', [$file, $applicant]))->assertOk()
        ->assertSee('VISA APPLICATION FORM')->assertSee('WORK INFORMATION')
        ->assertSee('Married')->assertSee('10-03-2027')->assertSee('Accountant')->assertSee('01-06-2019')
        ->assertSee('University of Santo Tomas')->assertSee('Mark')->assertSee('Yes');

    $this->actingAs($officer)->get(route('visa.worksheets.blank', 'korea'))->assertOk()->assertSee('EDUCATION INFORMATION');
});
