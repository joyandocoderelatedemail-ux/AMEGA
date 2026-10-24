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
        'srrv_card_number' => 'SRRV-00777',
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

    $this->actingAs($officer)->get(route('srrv.applications.show', $file))
        ->assertSee(route('srrv.applications.forms', [$file, 'request']), false);
});

test('a renewal prints the request form with ID renewal ticked, reading the linked file', function () {
    $officer = praFormsOfficer();
    $this->actingAs($officer)->post(route('srrv.applications.store'), praFilePayload(['primary_address' => '12 Rizal St, Makati City']));
    $file = SrrvApplication::latest('id')->firstOrFail();

    $this->actingAs($officer)->post(route('srrv.renewals.store'), [
        'srrv_application_id' => $file->id, 'visa_class' => 'classic', 'retiree_name' => 'John Smith',
        'srrv_card_number' => 'SRRV-00777', 'years_paid' => 2, 'form' => ['income' => ['pension']],
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
