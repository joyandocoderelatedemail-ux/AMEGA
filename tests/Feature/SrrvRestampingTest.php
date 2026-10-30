<?php

use App\Models\SrrvServiceRequest;
use App\Models\User;

/**
 * SRRV Re-stamping (PRA Citizen's Charter 2025): the SRRV sticker moved to the retiree's new passport.
 * The checklist for the details given, the fees, the stages through the BI, the Retiree Request Form
 * printed with Restamping ticked, and the new passport kept on the client record.
 */
function restamping(array $details = [], array $checklist = []): array
{
    return [
        'type' => 'restamping', 'retiree_name' => 'Hana Tanaka', 'retiree_email' => 'hana@example.com', 'srrv_card_number' => 'M0098765', 'visa_class' => 'classic',
        'details' => $details + ['members' => 2, 'old_passport' => 'surrendered', 'old_passport_number' => 'TK1111111', 'new_passport_number' => 'TK2222222',
            'new_passport_expiry' => now()->addYears(10)->toDateString(), 'address_local' => '5 Roxas Blvd, Pasay', 'via_representative' => 0],
        'checklist' => $checklist,
    ];
}

const RESTAMPING_BASIC = ['retiree_request_form', 'official_receipt', 'old_passport', 'new_passport'];

test('the Services menu opens SRRV Re-stamping and its form', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->assertSee(route('srrv.services', 'restamping'), false)->assertSee('SRRV Re-stamping');
    $this->actingAs($officer)->get(route('srrv.services', 'restamping'))->assertOk()->assertSee('No active SRRV Re-stamping files yet.');
    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'restamping']))->assertOk()
        ->assertSee('Passport details')->assertSee('New passport number')->assertSee('Certificate of Not the Same Person');
});

test('a re-stamping keeps what applies, charges US$10 + PHP 1,010 a member, and goes through the BI', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($officer)->post(route('srrv.requests.store'), restamping([], ['retiree_request_form', 'affidavit_of_loss']))->assertRedirect();
    $file = SrrvServiceRequest::withoutGlobalScopes()->firstOrFail();

    expect($file->reference)->toStartWith('SRV-RST-')
        ->and((float) $file->fee_usd)->toBe(20.0)->and((float) $file->fee_php)->toBe(2020.0)
        // The affidavit of loss does not apply when the old passport is surrendered.
        ->and($file->checklist)->toBe(['retiree_request_form'])
        ->and($file->missingRequirements())->toContain('New passport')->not->toContain('Certificate of Not the Same Person (not cleared on the BI derogatory check)');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', fn ($m) => str_contains($m, 'Still needed'));

    $this->actingAs($officer)->put(route('srrv.requests.update', $file), restamping([], RESTAMPING_BASIC));
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', fn ($m) => str_contains($m, 'fees must be fully paid'));

    $this->actingAs($officer)->post(route('srrv.requests.payment', $file), ['paid_usd' => 20, 'paid_php' => 2020])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file));
    expect($file->fresh()->status)->toBe('endorsed')->and($file->fresh()->submitted_at)->not->toBeNull();

    // Not cleared on the BI derogatory check: the sticker waits for the Certificate of Not the Same Person.
    $this->actingAs($officer)->put(route('srrv.requests.update', $file), restamping(['derogatory_hit' => 1], RESTAMPING_BASIC));
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($officer)->post(route('srrv.requests.advance', $file))->assertSessionHas('error', fn ($m) => str_contains($m, 'Not the Same Person'));

    $this->actingAs($officer)->put(route('srrv.requests.update', $file), restamping(['derogatory_hit' => 1], [...RESTAMPING_BASIC, 'not_same_person']));
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);
    foreach (['implemented', 'completed'] as $stage) {
        $this->actingAs($officer)->post(route('srrv.requests.advance', $file));
        expect($file->fresh()->status)->toBe($stage);
    }

    $this->actingAs($officer)->get(route('srrv.requests.print', $file))->assertOk()
        ->assertSee('RE-STAMPING OF THE SPECIAL RESIDENT RETIREE')->assertSee('US$ 20.00')->assertSee('2,020.00');
});

test('a lost passport, a name change or a representative brings their documents', function () {
    $applies = fn (array $details) => collect(SrrvServiceRequest::requirementsFor('restamping', $details))->filter(fn ($item) => $item[2])->keys()->all();

    expect($applies(['old_passport' => 'lost']))->toContain('affidavit_of_loss')->not->toContain('old_passport', 'affidavit_of_explanation', 'spa')
        ->and($applies(['old_passport' => 'not_submitted']))->toContain('affidavit_of_explanation')
        ->and($applies(['name_changed' => true, 'nationality_changed' => true]))->toContain('change_of_name', 'change_of_nationality')
        ->and($applies(['via_representative' => true]))->toContain('spa', 'pra_id_copy', 'representative_id');
});

test('the Retiree Request Form prints with Restamping ticked and the SRRV number', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $this->actingAs($officer)->post(route('srrv.requests.store'), restamping(['request_office' => 'head_office']))->assertRedirect();
    $file = SrrvServiceRequest::withoutGlobalScopes()->firstOrFail();

    // Nothing prints before an approver has checked the file.
    $this->actingAs($officer)->get(route('srrv.requests.request-form', $file))->assertRedirect(route('srrv.requests.show', $file));
    $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('admin.srrv-approvals.approve', ['request', $file->id]), ['admin_acknowledged' => 1]);

    $html = $this->actingAs($officer)->get(route('srrv.requests.request-form', $file))->assertOk()->getContent();
    expect($html)->toContain('RETIREE REQUEST FORM')->toContain('M0098765')->toContain('5 Roxas Blvd, Pasay');

    $this->actingAs($officer)->get(route('srrv.requests.show', $file))->assertSee(route('srrv.requests.request-form', $file), false);
});

test('the new passport replaces the old one on the client record, and the next form starts from it', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $client = User::factory()->create(['role' => 'client', 'name' => 'Hana Tanaka', 'email' => 'hana@example.com', 'passport_number' => 'TK1111111', 'address' => '5 Roxas Blvd, Pasay']);

    // A re-stamping opened for the client starts with the passport on record as the old one.
    $this->actingAs($officer)->get(route('srrv.requests.create', ['type' => 'restamping', 'client' => $client->id]))->assertOk()
        ->assertSee('value="TK1111111"', false)->assertSee('value="5 Roxas Blvd, Pasay"', false);

    $this->actingAs($officer)->post(route('srrv.requests.store'), restamping())->assertRedirect();

    expect($client->fresh()->passport_number)->toBe('TK2222222')->and($client->fresh()->srrv_number)->toBe('M0098765');
});
