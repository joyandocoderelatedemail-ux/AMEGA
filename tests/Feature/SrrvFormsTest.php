<?php

use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Support\BoxedForm;
use App\Support\SrrvForms;

beforeEach(fn () => autoApproveDeskFiles());

function praFormsOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

/** @return array<string, mixed> */
function praFilePayload(array $form = []): array
{
    return [
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'John Smith',
        'retiree_email' => 'john@example.com',
        'retiree_phone' => '+63 917 555 0101',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'British',
        'investment_amount' => 20000,
        'form' => $form,
    ];
}

test('the PRA form details save with the retiree file and blanks are dropped', function () {
    $this->actingAs(praFormsOfficer())->post(route('srrv.applications.store'), praFilePayload([
        'last_name' => 'Smith', 'first_name' => 'John', 'gender' => 'M', 'civil_status' => 'married',
        'religion' => '', 'plans' => ['tourism', 'investment'], 'spouse_name' => 'Jane Smith', 'spouse_included' => 'yes',
        'arrival_date' => '2026-09-01',
    ]))->assertRedirect();

    $file = SrrvApplication::latest('id')->firstOrFail();
    expect($file->detail('gender'))->toBe('M')
        ->and($file->detail('religion'))->toBeNull()
        ->and($file->listDetail('plans'))->toBe(['tourism', 'investment'])
        ->and($file->dateDetail('arrival_date')->format('Y-m-d'))->toBe('2026-09-01')
        ->and($file->form_details)->not->toHaveKey('religion');

    $this->actingAs(praFormsOfficer())->post(route('srrv.applications.store'), praFilePayload(['gender' => 'X', 'plans' => ['golf']]))
        ->assertSessionHasErrors(['form.gender', 'form.plans.0']);
});

test('the application form, checklist and medical certificate print from the file', function () {
    $officer = praFormsOfficer();
    $this->actingAs($officer)->post(route('srrv.applications.store'), praFilePayload([
        'gender' => 'M', 'civil_status' => 'married', 'passport_no' => 'GB1234567', 'religion' => 'Catholic',
        'spouse_name' => 'Jane Smith', 'spouse_included' => 'yes', 'father_name' => 'Robert Smith', 'marketer' => 'Amega Travel and Tours',
    ]));
    $file = SrrvApplication::latest('id')->firstOrFail();
    $file->forceFill(['srrv_card_number' => 'SRRV-00777'])->save(); // as PRA step 4 records it

    $application = $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertOk()
        ->assertSee('APPLICATION FORM FOR PRINCIPAL RETIREE')->assertSee('PRA-CR-FORM-0004')
        ->assertSee('Smith')->assertSee('GB1234567')->assertSee('Robert Smith')->assertSee('Amega Travel and Tours')->assertSee('SRRV-00777')
        ->getContent();
    // Classic and Male ticked, the spouse marked as included; the name split from the desk's single field.
    expect($application)->toContain(BoxedForm::tick(true).'Classic')
        ->and($application)->toContain(BoxedForm::tick(true).'Male')
        ->and($application)->toContain(BoxedForm::tick(true).'Married')
        ->and(SrrvForms::nameParts($file))->toBe(['Smith', 'John', null]);

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'checklist']))->assertOk()
        ->assertSee('CHECKLIST OF REQUIREMENTS FOR THE SRRV APPLICATION')
        ->assertSee('PRA medical certificate and results')->assertSee('BI Clearance Certificate (BICC)')->assertSee('Jane Smith')->assertSee('USD 20,000.00');

    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'medical']))->assertOk()
        ->assertSee('MEDICAL EXAMINATION FOR SRRV APPLICANTS')->assertSee('John Smith')->assertSee('Male');

    // The forms are opened from Step 7's PRA Forms card, not from each other's toolbar.
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 7]))->assertOk()
        ->assertSee('Checklist')->assertSee('Medical certificate')->assertSee('Retiree request form')
        ->assertSee(route('srrv.applications.forms', [$file, 'checklist']), false)
        ->assertSee(route('srrv.applications.forms', [$file, 'medical']), false)
        ->assertSee(route('srrv.applications.forms', [$file, 'request']), false);
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'checklist']))
        ->assertDontSee(route('srrv.applications.forms', [$file, 'request']), false);
});

test('a renewal prints the request form with ID renewal ticked, reading the linked file', function () {
    $officer = praFormsOfficer();
    $this->actingAs($officer)->post(route('srrv.applications.store'), praFilePayload(['primary_address' => '12 Rizal St, Makati City']));
    $file = SrrvApplication::latest('id')->firstOrFail();

    $this->actingAs($officer)->post(route('srrv.renewals.store'), [
        'srrv_application_id' => $file->id, 'visa_class' => 'classic', 'retiree_name' => 'John Smith',
        'srrv_card_number' => 'SRRV-00777', 'agreement_signed' => 1, 'years_paid' => 2, 'form' => ['income' => ['pension']],
    ])->assertRedirect();
    $renewal = SrrvRenewal::latest('id')->firstOrFail();

    $html = $this->actingAs($officer)->get(route('srrv.renewals.request-form', $renewal))->assertOk()
        ->assertSee('RETIREE REQUEST FORM')->assertSee('PRA-SD-FORM-0006')
        ->assertSee('SRRV-00777, 2 years')->assertSee('12 Rizal St, Makati City')->assertSee('Smith, John')
        ->getContent();
    expect($html)->toContain(BoxedForm::tick(true).'ID Renewal')
        ->and($html)->toContain(BoxedForm::tick(true).'Pension')
        ->and($html)->toContain(BoxedForm::tick(false).'Restamping');

    $this->actingAs($officer)->get(route('srrv.renewals.show', $renewal))->assertSee(route('srrv.renewals.request-form', $renewal), false);
});

test('blank PRA forms print from the dashboard, and other desks cannot reach them', function () {
    $officer = praFormsOfficer();

    foreach (SrrvForms::FORMS as $form) {
        $this->actingAs($officer)->get(route('srrv.forms.blank', $form))->assertOk()->assertDontSee('SRRV-00777');
    }
    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertSee(route('srrv.forms.blank', 'medical'), false);
    $this->actingAs($officer)->get('/srrv/forms/blank/passport')->assertNotFound();

    $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('srrv.forms.blank', 'application'))->assertRedirect();
});

test('the retiree request form fills what Step 4 leaves blank from the rest of the file', function () {
    $officer = praFormsOfficer();
    $this->actingAs($officer)->post(route('srrv.applications.store'), praFilePayload([
        'last_name' => 'Smith', 'first_name' => 'John', 'primary_address' => '12 Rizal St, Makati City', 'home_address' => '5 Main Rd, London',
        'home_mobile' => '+44 7700 900123', 'ph_email' => 'john.ph@example.com', 'company' => 'Acme Pensions, London', 'company_contact' => '+44 20 7946 0000',
    ]) + ['is_pensioner' => 1])->assertRedirect();
    SrrvApplication::latest('id')->first()->forceFill(['srrv_card_number' => 'SRRV-00777'])->save(); // as PRA step 4 records it
    $file = SrrvApplication::latest('id')->firstOrFail();

    $html = $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'request']))->assertOk()
        ->assertSee('Smith, John')->assertSee('SRRV-00777')->assertSee('12 Rizal St, Makati City')->assertSee('5 Main Rd, London')
        ->assertSee('+63 917 555 0101')->assertSee('john@example.com')
        // From the rest of the file: another number, the second email, the pension provider and its contact.
        ->assertSee('+44 7700 900123')->assertSee('john.ph@example.com')->assertSee('Acme Pensions, London')->assertSee('+44 20 7946 0000')
        ->getContent();
    // A pensioner's source of income is their pension.
    expect($html)->toContain(BoxedForm::tick(true).'Pension');

    // What the Retiree Request Form section says wins.
    $file->forceFill(['form_details' => $file->form_details + ['income' => ['business'], 'alternate_email' => 'other@example.com']])->save();
    $html = $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'request']))->assertOk()
        ->assertSee('other@example.com')->assertDontSee('john.ph@example.com')->getContent();
    expect($html)->toContain(BoxedForm::tick(true).'Business')->not->toContain(BoxedForm::tick(true).'Pension');
});

test('the new application form closes every div it opens, so the step buttons stay inside the wizard', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'srrv']))
        ->get(route('srrv.applications.create', ['service' => 'new_application', 'visa_class' => 'classic']))
        ->assertOk()->getContent();

    // A stray </div> once closed the wizard early and left Continue outside Alpine, dead to clicks.
    expect(preg_match_all('/<div\b/', $html))->toBe(substr_count($html, '</div>'));
});

test('the PRA Forms step fills its blanks from the file and the client record, never over what is saved', function () {
    User::factory()->create([
        'role' => 'client', 'email' => 'john@example.com', 'place_of_birth' => 'London',
        'passport_number' => 'GB7654321', 'civil_status' => 'married',
    ]);
    $file = SrrvApplication::withoutGlobalScopes()->create([
        'reference' => 'SRV-TEST-1', 'service_type' => 'new_application', 'visa_class' => 'classic', 'status' => 'pending',
        'retiree_name' => 'John Paul Smith', 'retiree_email' => 'john@example.com', 'retiree_phone' => '09171234567',
        'investment_amount' => 20000, 'investment_currency' => 'USD',
        'form_details' => ['passport_no' => 'SAVED-1', 'home_address' => '1 High St, London'],
    ]);

    expect(SrrvForms::prefill($file))->toMatchArray([
        'last_name' => 'Smith', 'first_name' => 'John Paul',
        'ph_email' => 'john@example.com', 'ph_mobile' => '09171234567',
        'place_of_birth' => 'London', 'civil_status' => 'married',
        'address_abroad' => '1 High St, London', 'marketer' => SrrvForms::MARKETER,
        'actual_deposit' => 'USD 20,000.00',
    ])->not->toHaveKey('passport_no');

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('srrv.applications.edit', [$file, 'step' => 4]))
        ->assertOk()->assertSee('value="London"', false)->assertSee('value="SAVED-1"', false);
});
