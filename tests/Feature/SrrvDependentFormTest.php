<?php

use App\Models\SrrvApplication;
use App\Models\User;

beforeEach(fn () => autoApproveDeskFiles());

function dependentOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

function familyFile(object $test, User $officer): SrrvApplication
{
    $test->actingAs($officer)->post(route('srrv.applications.store'), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'John Smith',
        'retiree_email' => 'john@example.com', 'retiree_phone' => '+63 917 555 0101', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'British', 'srrv_card_number' => 'SRRV-00777', 'investment_amount' => 20000,
        'form' => [
            'last_name' => 'Smith', 'first_name' => 'John', 'srrv_option' => 'classic',
            'spouse_name' => 'Jane Mary Smith', 'spouse_dob' => '1970-05-04', 'spouse_id' => 'P-111', 'spouse_included' => 'yes',
            'child_1_name' => 'Tom Smith', 'child_1_dob' => '2010-02-03', 'child_1_id' => 'P-222', 'child_1_included' => 'yes',
            'child_2_name' => 'Amy Smith', 'child_2_dob' => '2012-08-09', 'child_2_included' => 'no',
        ],
    ])->assertRedirect();

    return SrrvApplication::latest('id')->firstOrFail();
}

test('the file offers one dependent form for each spouse or child it names', function () {
    $officer = dependentOfficer();
    $file = familyFile($this, $officer);

    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('Dependent form: Jane Mary Smith')->assertSee('Dependent form: Tom Smith')->assertSee('Dependent form: Amy Smith')
        ->assertSee(route('srrv.applications.forms', [$file, 'dependent', 'who' => 'spouse']), false)
        ->assertSee(route('srrv.applications.forms', [$file, 'dependent', 'who' => 'child_1']), false);
});

test('the spouse form is filled from the file: name, birth date, ID, the principal, and the children', function () {
    $officer = dependentOfficer();
    $file = familyFile($this, $officer);

    $html = $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'dependent', 'who' => 'spouse']))->assertOk()
        ->assertSee('APPLICATION FORM FOR DEPENDENT RETIREE')->assertSee('PRA-CR-FORM-0008')
        ->assertSee('FOR DEPENDENT RETIREE USE')->assertSee('Name of Principal')
        ->assertSee('Jane Mary')->assertSee('04 MAY 1970')->assertSee('P-111')
        ->assertSee('John Smith')
        // The spouse lists the children.
        ->assertSee('Tom Smith')->assertSee('Amy Smith')->assertSee('03 FEB 2010')
        ->getContent();

    // Dependent - Spouse is ticked, Classic is ticked, Married is ticked.
    expect(substr_count($html, '✓'))->toBeGreaterThanOrEqual(4);

    // Switch between dependents from the toolbar.
    expect($html)->toContain(route('srrv.applications.forms', [$file, 'dependent', 'who' => 'child_1']));
});

test('a child form lists the siblings, not the child itself', function () {
    $officer = dependentOfficer();
    $file = familyFile($this, $officer);

    $html = $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'dependent', 'who' => 'child_1']))->assertOk()
        ->assertSee('03 FEB 2010')->assertSee('P-222')->assertSee('Amy Smith')->getContent();

    // The spouse only appears in the toolbar link to her own form, never in the family rows.
    expect(substr_count($html, 'Jane Mary Smith'))->toBe(1)->and($html)->toContain('Dependent: Jane Mary Smith');
});

test('the dependent form prints blank from the dashboard, and says so when a file has no dependents', function () {
    $officer = dependentOfficer();

    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()
        ->assertSee('Dependent application form')->assertSee(route('srrv.forms.blank', 'dependent'), false);
    $this->actingAs($officer)->get(route('srrv.forms.blank', 'dependent'))->assertOk()
        ->assertSee('APPLICATION FORM FOR DEPENDENT RETIREE')->assertDontSee('✓');

    $this->actingAs($officer)->post(route('srrv.applications.store'), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Solo Retiree', 'retiree_email' => 'solo@example.com',
        'retiree_phone' => '+63 917 555 0102', 'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'British', 'investment_amount' => 20000,
    ])->assertRedirect();
    $solo = SrrvApplication::latest('id')->firstOrFail();
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$solo, 'dependent']))->assertOk()->assertSee('No dependent is named on this file yet.');
});
