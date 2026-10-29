<?php

use App\Models\SrrvServiceRequest;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * SRRV Termination of Membership (PRA-SD-FORM-0008) and Conversion into Active Investment
 * (PRA-SD-FORM-0010): the PRA checklist for the details given, the termination fees, the conversion
 * rules, the stages, approval and the printed checklist.
 */
function termination(array $details = [], array $checklist = []): array
{
    return [
        'type' => 'termination', 'retiree_name' => 'John Smith', 'retiree_email' => 'john@example.com', 'srrv_card_number' => 'M0012345', 'visa_class' => 'classic',
        'details' => $details + ['reason' => 'voluntary', 'members' => 2, 'with_downgrading' => 1, 'withdraw_deposit' => 0, 'tax_free_importation' => 0, 'via_representative' => 0],
        'checklist' => $checklist,
    ];
}

test('the Services menu opens Termination and Conversion', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $html = $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent();
    expect($html)->toContain('href="'.route('srrv.services', 'termination').'" role="menuitem"')
        ->toContain('href="'.route('srrv.services', 'conversion').'" role="menuitem"')
        ->not->toContain('Coming soon');

    $this->actingAs($officer)->get(route('srrv.services', 'termination'))->assertOk()->assertSee('No active SRRV Termination of Membership files yet.');
    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'termination']))->assertOk()
        ->assertSee('Accomplished Service Request Form')->assertSee('Notarized Affidavit of Quitclaim and Release');
    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'conversion']))->assertOk()
        ->assertSee('Deed of Undertaking');
    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'other']))->assertNotFound();
});

test('a termination keeps only the requirements that apply, works out the fees, and moves on once all is in and paid', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($officer)->post(route('srrv.requests.store'), termination([], ['service_request_form', 'death_certificate']))->assertRedirect();
    $file = SrrvServiceRequest::withoutGlobalScopes()->firstOrFail();

    // Downgrading, 2 members: US$20 + PHP 5,520 each. The death certificate does not apply to a voluntary termination.
    expect((float) $file->fee_usd)->toBe(40.0)->and((float) $file->fee_php)->toBe(11040.0)
        ->and($file->checklist)->toBe(['service_request_form'])
        ->and($file->approval_status)->toBe('pending')
        ->and($file->missingRequirements())->toContain('Valid (unexpired) passport, for the downgrading of the visa')
        ->not->toContain('Death Certificate')->not->toContain('Proof of planned departure (e.g. flight ticket)');

    // Waiting for approval, then the documents, then the fees.
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error');
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', fn ($m) => str_contains($m, 'Still needed'));

    $this->actingAs($officer)->put(route('srrv.requests.update', $file), termination([], ['service_request_form', 'passport_with_srrv', 'pra_id_cards', 'valid_passport']));
    $file->refresh();
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', fn ($m) => str_contains($m, 'fees must be fully paid'));

    $this->actingAs($officer)->post(route('srrv.requests.payment', $file), ['paid_usd' => 40, 'paid_php' => 11040])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('fees_paid');

    foreach (['submitted', 'processing', 'completed'] as $stage) {
        $this->actingAs($officer)->post(route('srrv.requests.advance', $file));
        expect($file->fresh()->status)->toBe($stage);
    }
    expect($file->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($officer)->get(route('srrv.requests.print', $file))->assertOk()
        ->assertSee('CHECKLIST OF DOCUMENTARY REQUIREMENTS FOR TERMINATION OF MEMBERSHIP')->assertSee('US$ 40.00')->assertSee('11,040.00');
});

test('a withdrawal after the principal died asks for the estate papers and the bank details', function () {
    $requirements = SrrvServiceRequest::requirementsFor('termination', ['reason' => 'death', 'withdraw_deposit' => true, 'estate' => 'intestate']);
    $applies = collect($requirements)->filter(fn ($item) => $item[2])->keys()->all();

    expect($applies)->toContain('death_certificate', 'claimant_ids', 'estate_tax', 'proof_of_relationship', 'self_adjudication', 'affidavit_of_publication', 'quitclaim')
        ->not->toContain('letters_testamentary', 'proof_of_incapacity');

    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.requests.store'), termination(['reason' => 'death', 'withdraw_deposit' => 1]))
        ->assertSessionHasErrors('details.bank_details');
});

test('a conversion follows the PRA rules: Classic, US$50,000, 25 years, 30 days after the SRRV, then the ocular inspection', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $all = array_keys(SrrvServiceRequest::requirementsFor('conversion', []));
    $docs = array_values(array_diff($all, ['spa', 'representative_id']));
    $form = fn (array $details) => [
        'type' => 'conversion', 'retiree_name' => 'Mary Jones', 'visa_class' => 'classic', 'checklist' => $docs,
        'details' => $details + ['property_address' => 'Unit 5, Clark', 'sublessor_name' => 'Clark Homes Inc.'],
    ];

    $this->actingAs($admin)->post(route('srrv.requests.store'), $form(['sublease_amount' => 40000, 'sublease_years' => 20, 'srrv_issued_on' => now()->subDays(10)->toDateString()]))->assertRedirect();
    $file = SrrvServiceRequest::firstOrFail();
    expect($file->approval_status)->toBe('approved'); // the only approver's own save

    $this->actingAs($admin)->post(route('srrv.requests.advance', $file))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'US$50,000') && str_contains($m, '25 years') && str_contains($m, '30 days'));

    $this->actingAs($admin)->put(route('srrv.requests.update', $file), $form(['sublease_amount' => 60000, 'sublease_years' => 25, 'srrv_issued_on' => now()->subDays(45)->toDateString()]));
    $this->actingAs($admin)->post(route('srrv.requests.advance', $file))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('srrv.requests.advance', $file));
    expect($file->fresh()->status)->toBe('ocular_inspection');
    $this->actingAs($admin)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', 'Record the date of the ocular inspection first.');
});

test('the desk permissions apply: Open alone cannot start a request; files stay private', function () {
    $viewer = User::factory()->create(['role' => 'agent']);
    $viewer->forceFill(['permissions' => ['srrv.view']])->save();
    $this->actingAs($viewer->refresh())->get(route('srrv.requests.create', ['type' => 'termination']))->assertSessionHas('error');

    $mine = User::factory()->create(['role' => 'srrv']);
    $other = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($mine)->post(route('srrv.requests.store'), termination());
    $file = SrrvServiceRequest::withoutGlobalScopes()->firstOrFail();
    $this->actingAs($other)->get(route('srrv.requests.show', $file))->assertNotFound();
    $this->actingAs($mine)->get(route('srrv.requests.show', $file))->assertOk();
});

test('the desk dashboard keeps only the counts and recent files; the blank PRA forms are in the top bar', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'srrv']))->get(route('srrv.dashboard'))->assertOk()->getContent();

    $page = Str::after($html, '<main');
    expect($page)->not->toContain('Jobs &amp; Pipelines')->not->toContain('Published Fees')->not->toContain('Recent Renewals')
        ->not->toContain('New SRRV Application')->not->toContain('Blank PRA Forms')->not->toContain('Annual Renewal');
    expect($html)
        ->toContain('Blank PRA Forms')
        ->toContain('href="'.route('srrv.forms.blank', 'application').'" role="menuitem"  target="_blank"');
});
