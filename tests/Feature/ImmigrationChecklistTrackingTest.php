<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function trackOfficer(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function trackSheet(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Smith', 'given_name' => 'John', 'passport_number' => 'GB7654321', 'nationality' => 'British']);
}

function trackCase(string $type, array $details = [], array $checklist = []): ImmigrationPetition
{
    return ImmigrationPetition::create(['immigration_client_id' => trackSheet()->id, 'type' => $type, 'details' => $details, 'checklist' => $checklist]);
}

test('a 13/A case needs the dependent checklist only when the applicant is joined by a child', function () {
    $spouse = trackCase('spouse');
    $permanent = trackCase('permanent');

    expect(array_keys($spouse->requiredItems()))->not->toContain('dep_cgaf')
        ->and(array_keys($permanent->requiredItems()))->not->toContain('dep_cgaf');

    $spouse->update(['details' => ['with_dependents' => true]]);
    expect(array_keys($spouse->requiredItems()))->toBe(['joint_letter', 'cgaf', 'marriage_certificate', 'spouse_birth_certificate', 'passport_copy', 'bi_clearance', 'dep_cgaf', 'dep_passport', 'dep_filiation', 'dep_bi_clearance']);

    // The 6-month NBI and the Annex "A" quarantine clearance follow for the dependents too.
    $spouse->update(['details' => ['with_dependents' => true, 'nbi_needed' => true, 'annex_a' => true]]);
    expect(array_keys($spouse->requiredItems()))->toContain('dep_nbi', 'dep_quarantine');

    // A permanent amendment always wants each dependent's NBI Clearance.
    $permanent->update(['details' => ['with_dependents' => true]]);
    expect(array_keys($permanent->requiredItems()))->toContain('dep_nbi')->not->toContain('dep_quarantine');
});

test('a dual citizenship case adds each dependent child, and the ACR only for a registered child', function () {
    $case = trackCase('dual', ['with_dependents' => true], ['dep_supplement']);

    expect(array_keys($case->requiredItems()))->toContain('dep_supplement', 'dep_photos', 'dep_birth_certificate', 'dep_passport')->not->toContain('dep_acr')
        ->and(array_keys($case->missingItems()))->not->toContain('dep_supplement')->toContain('dep_photos');

    $case->update(['details' => ['with_dependents' => true, 'dependent_alien' => true]]);
    expect(array_keys($case->requiredItems()))->toContain('dep_acr');
});

test('an ACR I-Card renewal asks for the extra documents of what it is for', function () {
    $required = fn (array $details) => array_keys(trackCase('renewal', $details)->requiredItems());
    $core = ['application_form', 'acr_card', 'passport_copy'];

    // Nothing chosen: the immigrant-visa order, as before.
    expect($required([]))->toBe([...$core, 'boc_order']);
    expect($required(['renewal_category' => 'immigrant']))->toBe([...$core, 'boc_order']);
    expect($required(['renewal_category' => '47a2']))->toBe([...$core, 'ren_doj']);
    expect($required(['renewal_category' => 'zone']))->toBe([...$core, 'ren_zone']);
    expect($required(['renewal_category' => 'student']))->toBe([...$core, 'ren_student']);
    expect($required(['renewal_category' => 'dependent']))->toBe([...$core, 'ren_dep_boc', 'ren_dep_parent', 'ren_dep_photos']);
    expect($required(['renewal_category' => 'ph_born']))->toBe([...$core, 'ren_ph_birth', 'ren_ph_acr', 'ren_ph_parent']);
    expect($required(['renewal_category' => 'nonsense']))->toBe([...$core, 'boc_order']);
});

test('the case form offers the dependents and renewal controls, and saves them', function () {
    $sheet = trackSheet();
    $officer = trackOfficer();

    $this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => '13a_spouse']))->assertOk()
        ->assertSee('Joined by unmarried children under 21')->assertSee('Each dependent: duly accomplished CGAF (BI Form CGAF-001-Rev 2)');
    $this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'dual_citizenship']))->assertOk()
        ->assertSee('Joined by a dependent child')->assertSee('A dependent child is a registered alien');
    $this->actingAs($officer)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'permanent_13a_series']))->assertOk()
        ->assertSee('What is the renewal for?')->assertSee('name="petition[details][renewal_category]"', false)
        ->assertSee('Student Visa')->assertSee('Re-registration of a dependent: two (2) pieces of 2x2 colored photograph');

    $this->actingAs($officer)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'permanent_13a_series', 'last_name' => 'Smith', 'given_name' => 'John',
        'petition' => ['details' => ['renewal_category' => 'ph_born'], 'checklist' => ['ren_ph_birth']],
    ])->assertSessionHasNoErrors();
    $case = $sheet->fresh()->openPetition('renewal');
    expect($case->detail('renewal_category'))->toBe('ph_born')
        ->and(array_keys($case->missingItems()))->toBe(['application_form', 'acr_card', 'passport_copy', 'ren_ph_acr', 'ren_ph_parent']);

    $this->actingAs($officer)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'permanent_13a_series', 'last_name' => 'Smith', 'given_name' => 'John',
        'petition' => ['details' => ['renewal_category' => 'bogus']],
    ])->assertSessionHasErrors('petition.details.renewal_category');
});

test('the printed checklists tick the dependent and renewal extras from the case', function () {
    $officer = trackOfficer();
    $spouse = trackCase('spouse', ['with_dependents' => true], ['dep_cgaf', 'dep_filiation']);
    $renewal = ImmigrationPetition::create(['immigration_client_id' => $spouse->immigration_client_id, 'type' => 'renewal', 'details' => ['renewal_category' => 'student'], 'checklist' => ['ren_student']]);
    $dual = ImmigrationPetition::create(['immigration_client_id' => $spouse->immigration_client_id, 'type' => 'dual', 'details' => ['with_dependents' => true], 'checklist' => ['dep_birth_certificate']]);
    $sheet = ImmigrationClient::find($spouse->immigration_client_id);

    $ticks = fn (string $html, string $text) => preg_match('/<span class="fill">✓<\/span><\/span><span>[^<]*'.preg_quote($text, '/').'/u', $html) === 1;

    $html = $this->actingAs($officer)->get(route('admin.client-sheets.petition-checklist', [$sheet, 'spouse', 'case' => $spouse->id]))->assertOk()->getContent();
    expect($ticks($html, 'Duly accomplished CGAF (BI Form CGAF-001-Rev 2);'))->toBeTrue()
        ->and($ticks($html, 'Proof of filiation with the petitioner;'))->toBeTrue()
        ->and($ticks($html, 'Photocopy of passport bio-page and latest admission with valid authorized stay'))->toBeFalse();

    $html = $this->actingAs($officer)->get(route('admin.client-sheets.petition-checklist', [$sheet, 'renewal', 'case' => $renewal->id]))->assertOk()->getContent();
    expect($ticks($html, 'Photocopy of the Student Desk-approved Order'))->toBeTrue()
        ->and($ticks($html, 'Photocopy of endorsement from the Department of Justice'))->toBeFalse();

    $html = $this->actingAs($officer)->get(route('admin.client-sheets.petition-checklist', [$sheet, 'dual', 'case' => $dual->id]))->assertOk()->getContent();
    expect($ticks($html, 'Photocopy of Birth Certificate of Dependent Child;'))->toBeTrue()
        ->and($ticks($html, 'Photocopy of valid foreign passport bio-page of Dependent Child'))->toBeFalse();
});

test('the ACR I-Card renewal form carries the number BI publishes today, and the amendment checklist cites the IRD form', function () {
    $officer = trackOfficer();
    $renewal = trackCase('renewal');
    $sheet = ImmigrationClient::find($renewal->immigration_client_id);

    $this->actingAs($officer)->get(route('admin.client-sheets.petition-form', [$sheet, 'renewal', 'case' => $renewal->id]))->assertOk()
        ->assertSee('BI FORM 2015-08-002 Rev 0')->assertDontSee('BI FORM 2014-08-010 Rev 0');

    $this->actingAs($officer)->get(route('admin.client-sheets.restamping-checklist', [$sheet, 'amendment']))->assertOk()
        ->assertSee('Duly accomplished application form (BI Form IRD01.QF.006 Rev 03);')->assertDontSee('2016-10-004');
});
