<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Models\VisaApplication;

function lockAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

function lockAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** An ECC owned by the agent, in whatever state the test needs. */
function lockCase(User $agent, array $case = []): ImmigrationPetition
{
    $client = ImmigrationClient::forceCreate(['last_name' => 'Lock', 'given_name' => 'Test', 'created_by' => $agent->id]);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($case + [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'created_by' => $agent->id,
    ]);
}

function lockPreviewUrl(ImmigrationPetition $case): string
{
    return route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]);
}

test('the agent preview has the status dropdown until the file is released; then the status is locked text', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['workflow_status' => 'for_validation']);

    $html = $this->actingAs($agent)->get(lockPreviewUrl($case))->assertOk()->assertSee('For Validation')->getContent();
    expect($html)->toContain('<select name="status"')
        ->toContain(route('admin.immigration.cases.status', ['ecc', $case->id]))
        ->toContain('<option value="released"')
        ->not->toContain(route('admin.immigration.cases.request-edit', ['ecc', $case->id]))
        ->not->toContain(route('admin.immigration.cases.reopen', ['ecc', $case->id]));

    $case->forceFill(['released_on' => now()->toDateString()])->save();
    $released = $this->actingAs($agent)->get(lockPreviewUrl($case))->assertOk()->getContent();
    expect($released)->not->toContain('<select name="status"')
        ->not->toContain(route('admin.immigration.cases.status', ['ecc', $case->id]))
        ->toContain(route('admin.immigration.cases.request-edit', ['ecc', $case->id]));
});

test('the admin preview is read-only too, and keeps Approve and Return', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['approval_status' => 'pending', 'approval_requested_at' => now(), 'acknowledged_by' => $agent->id]);

    $html = $this->actingAs(lockAdmin())->get(lockPreviewUrl($case))->assertOk()
        ->assertSee('Preview only')->assertSee('Waiting for Admin Approval')->getContent();

    expect($html)->not->toContain('<select name="status"')
        ->not->toContain(route('admin.immigration.cases.status', ['ecc', $case->id]))
        ->not->toContain('<option value="released"')
        ->toContain(route('admin.immigration-approvals.approve', ['ecc', $case->id]))
        ->toContain(route('admin.immigration-approvals.reject', ['ecc', $case->id]));

    // A released case previewed by an admin has no Reopen either.
    $released = lockCase($agent, ['workflow_status' => 'approved', 'released_on' => now()->toDateString()]);
    expect($this->actingAs(lockAdmin())->get(lockPreviewUrl($released))->getContent())
        ->not->toContain(route('admin.immigration.cases.reopen', ['ecc', $released->id]));
});

test('the edit page of an open case has the status dropdown with Released, and no separate release button', function () {
    $agent = lockAgent();
    $case = lockCase($agent);

    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]))->assertOk()
        ->assertSee('<select name="status"', false)->assertSee('<option value="released"', false)->assertDontSee('Mark released');
});

test('a released case: the status is refused for an agent and an admin, and the page shows it locked', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString()]);

    foreach ([$agent, lockAdmin()] as $user) {
        $this->actingAs($user)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'for_briefing'])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'This file is released. Its status is locked; '.($user->is($agent) ? 'request an edit first.' : 'reopen it first.')));
    }
    expect($case->fresh()->workflow_status)->toBe('approved')->and($case->fresh()->released_on)->not->toBeNull();

    // A fetch/XHR caller gets a 423 with the message instead of a redirect.
    $this->actingAs($agent)->postJson(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'denied'])
        ->assertStatus(423)->assertJsonPath('message', fn (string $m) => str_contains($m, 'Its status is locked'));

    $edit = route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]);
    $html = $this->actingAs($agent)->get($edit)->assertOk()->assertSee('Released')->getContent();
    // The badge reads Released (not Approved) with a lock. An agent can only ask for an edit; an admin can reopen.
    expect($html)->not->toContain('<select name="status"')->toContain('data-lucide="lock"')->toContain('Released')
        ->toContain('Request edit')->toContain(route('admin.immigration.cases.request-edit', ['ecc', $case->id]))
        ->not->toContain('>Reopen</button>');

    $adminHtml = $this->actingAs(lockAdmin())->get($edit)->getContent();
    expect($adminHtml)->toContain('data-lucide="lock"')->toContain('Reopen')->not->toContain('Request edit</button>');

    // The Cases page shows the same locked state.
    $this->actingAs(lockAdmin())->get(route('admin.immigration.cases'))->assertOk()->assertSee('The file is locked', false);
});

test('only an admin or an approver can reopen; after that the status changes again', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString()]);

    $this->actingAs($agent)->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'Only an admin or an Immigration approver'));
    expect($case->fresh()->released_on)->not->toBeNull();

    $this->actingAs(lockAdmin())->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))->assertSessionHas('success');
    expect($case->fresh()->released_on)->toBeNull();

    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'for_briefing'])->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('for_briefing');
});

test('a supervisor with the Immigration approve permission can reopen', function () {
    $supervisor = staffWith('agent', ['immigration.approve'], ['immigration']);
    $case = lockCase(lockAgent(), ['workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString()]);

    $this->actingAs($supervisor)->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))->assertSessionHas('success');
    expect($case->fresh()->released_on)->toBeNull();
});

test('a case waiting for the admin refuses every status change; a returned one is free', function () {
    $agent = lockAgent();

    $case = lockCase($agent, ['approval_status' => 'pending']);
    foreach ([$agent, lockAdmin()] as $user) {
        $this->actingAs($user)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'for_validation'])->assertSessionHas('error');
    }
    expect($case->fresh()->workflow_status)->toBeNull();

    // Returned by the admin: the agent corrects the file and moves its status as before.
    $returned = lockCase($agent, ['approval_status' => 'returned', 'review_note' => 'Fix the dates']);
    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $returned->id]), ['remark' => 'Test remark', 'status' => 'for_validation'])->assertSessionHas('success');
    expect($returned->fresh()->workflow_status)->toBe('for_validation');
});

test('a released case cannot be released twice, and the date does not move', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['released_on' => '2026-10-01']);

    foreach ([$agent, lockAdmin()] as $user) {
        $this->actingAs($user)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'released', 'released_on' => now()->toDateString()])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'This file is released'));
    }
    expect($case->fresh()->released_on->toDateString())->toBe('2026-10-01');
});

test('Visa Assistance and SRRV files cannot be cancelled once released, collected or cancelled', function () {
    $officer = User::factory()->create(['role' => 'admin']);

    $visa = VisaApplication::create(['reference' => 'VSA-LOCK', 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa', 'status' => 'released', 'created_by' => $officer->id]);
    $srrv = SrrvApplication::create(['reference' => 'SRRV-LOCK', 'retiree_name' => 'Ramon Cruz', 'service_type' => 'renewal_application', 'visa_class' => 'classic', 'status' => 'released', 'created_by' => $officer->id]);
    $renewal = SrrvRenewal::create(['reference' => 'REN-LOCK', 'retiree_name' => 'Ramon Cruz', 'visa_class' => 'classic', 'status' => 'collected', 'created_by' => $officer->id]);

    $this->actingAs($officer)->post(route('visa.applications.cancel', $visa))->assertSessionHas('error', fn (string $m) => str_contains($m, 'Its status is locked'));
    $this->actingAs($officer)->post(route('srrv.applications.cancel', $srrv))->assertSessionHas('error', fn (string $m) => str_contains($m, 'Its status is locked'));
    $this->actingAs($officer)->post(route('srrv.renewals.cancel', $renewal))->assertSessionHas('error', fn (string $m) => str_contains($m, 'Its status is locked'));

    expect($visa->fresh()->status)->toBe('released')->and($srrv->fresh()->status)->toBe('released')->and($renewal->fresh()->status)->toBe('collected');

    // The cancel button is gone from the finished files' pages, and an open file can still be cancelled.
    expect($this->actingAs($officer)->get(route('visa.applications.show', $visa))->getContent())->not->toContain(route('visa.applications.cancel', $visa));

    $open = VisaApplication::create(['reference' => 'VSA-OPEN', 'client_name' => 'Jane Tan', 'service_type' => 'visit_visa', 'status' => 'pending', 'created_by' => $officer->id]);
    $this->actingAs($officer)->post(route('visa.applications.cancel', $open))->assertSessionHas('success');
    expect($open->fresh()->status)->toBe('cancelled');
});

test('the agent preview of a released file offers Request edit, and the admin preview does not', function () {
    $agent = lockAgent();
    $case = lockCase($agent, ['workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString()]);

    $this->actingAs($agent)->get(lockPreviewUrl($case))->assertOk()
        ->assertSee('Request edit')->assertSee(route('admin.immigration.cases.request-edit', ['ecc', $case->id]), false)->assertSee('Released');

    expect($this->actingAs(lockAdmin())->get(lockPreviewUrl($case))->getContent())->not->toContain(route('admin.immigration.cases.request-edit', ['ecc', $case->id]));
});
