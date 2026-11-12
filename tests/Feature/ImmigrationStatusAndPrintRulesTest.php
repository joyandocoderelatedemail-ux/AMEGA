<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationPetition;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * The status dropdown (free when returned, locked while waiting or released, Approved locks for agents), the last
 * step of the file (Send for admin approval, filled forms once approved), the print gate on the filled forms and the blank
 * agreement and consent.
 */
function spAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

function spAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** An ECC in the agent's own folder, with the approval and workflow status given. */
function spCase(User $agent, array $case = []): ImmigrationPetition
{
    $client = ImmigrationClient::forceCreate(['last_name' => 'Rules', 'given_name' => 'Client', 'created_by' => $agent->id]);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($case + [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['ecc_kind' => 'ecc'], 'created_by' => $agent->id,
        'workflow_status' => 'preparing', 'approval_status' => 'approved', 'fee' => 1000, 'payment_type' => 'none',
    ]);
}

function spStatus(User $user, ImmigrationPetition $case, string $status, array $extra = [])
{
    return test()->actingAs($user)->from('/back')->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => $status] + $extra);
}

function spEdit(ImmigrationPetition $case, array $extra = []): string
{
    return route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id] + $extra);
}

test('a returned file has a free status; a file waiting for the admin does not', function () {
    $agent = spAgent();
    $returned = spCase($agent, ['approval_status' => 'returned', 'review_note' => 'Fix the fee']);
    spStatus($agent, $returned, 'for_validation')->assertSessionHas('success');
    expect($returned->fresh()->workflow_status)->toBe('for_validation');

    $pending = spCase($agent, ['approval_status' => 'pending']);
    spStatus($agent, $pending, 'for_validation')->assertSessionHas('error', fn (string $m) => str_contains($m, "waiting for the admin's approval"));
    expect($pending->fresh()->workflow_status)->toBe('preparing');
});

test('a released file stays locked even when returned', function () {
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => 'returned', 'released_on' => now()->toDateString()]);

    spStatus($agent, $case, 'for_validation')->assertSessionHas('error', fn (string $m) => str_contains($m, 'released') && str_contains($m, 'request an edit first'));
});

test('an agent who marks a file Approved can then only mark it Released', function () {
    $agent = spAgent();
    $case = spCase($agent);

    spStatus($agent, $case, 'approved')->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('approved')->and($case->fresh()->approvedStatusLocked())->toBeTrue();

    spStatus($agent, $case, 'preparing')->assertSessionHas('error', fn (string $m) => str_contains($m, 'The Approved status is locked') && str_contains($m, 'Request edit'));
    expect($case->fresh()->workflow_status)->toBe('approved');

    spStatus($agent, $case, 'released', ['released_on' => now()->toDateString()])->assertSessionHas('success');
    expect($case->fresh()->released_on)->not->toBeNull();
});

test('an Approved lock refuses a JSON status call with a 423', function () {
    $agent = spAgent();
    $case = spCase($agent, ['workflow_status' => 'approved']);

    $this->actingAs($agent)->postJson(route('admin.immigration.cases.status', ['ecc', $case->id]), ['remark' => 'Test remark', 'status' => 'submitted'])
        ->assertStatus(423)->assertJsonPath('message', fn (string $m) => str_contains($m, 'The Approved status is locked'));
});

test('an admin or an approver is never blocked by the Approved lock', function () {
    $case = spCase(spAgent(), ['workflow_status' => 'approved']);

    spStatus(spAdmin(), $case, 'submitted')->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('submitted');

    $case->forceFill(['workflow_status' => 'approved'])->save();
    $supervisor = staffWith('agent', ['immigration.approve'], ['immigration']);
    spStatus($supervisor, $case, 'for_validation')->assertSessionHas('success');
});

test('a status-change request on an Approved-locked file, once approved, unlocks the status and keeps the file approved', function () {
    Notification::fake();
    $agent = spAgent();
    $admin = spAdmin();
    $case = spCase($agent, ['workflow_status' => 'approved']);

    $this->actingAs($agent)->post(route('admin.immigration.cases.request-edit', ['ecc', $case->id]), ['reason' => 'Wrong status picked', 'type' => 'status'])->assertSessionHas('success');
    expect($case->fresh()->edit_request_type)->toBe('status')->and($case->fresh()->hasEditRequest())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Approved status') && str_contains($m, 'unlocked'));

    $fresh = $case->fresh();
    expect($fresh->status_unlocked_at)->not->toBeNull()->and($fresh->approval_status)->toBe('approved')->and($fresh->workflow_status)->toBe('approved')
        ->and($fresh->hasEditRequest())->toBeFalse()->and($fresh->approvedStatusLocked())->toBeFalse();

    spStatus($agent, $case, 'for_validation')->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('for_validation');

    // Picking Approved again locks it again.
    spStatus($agent, $case, 'approved')->assertSessionHas('success');
    expect($case->fresh()->status_unlocked_at)->toBeNull()->and($case->fresh()->approvedStatusLocked())->toBeTrue();
});

test('a status request is forced to the status type when only the status is locked', function () {
    Notification::fake();
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => null, 'workflow_status' => 'approved']);

    $this->actingAs($agent)->post(route('admin.immigration.cases.request-edit', ['ecc', $case->id]), ['reason' => 'Wrong status', 'type' => 'details'])->assertSessionHas('success');

    expect($case->fresh()->edit_request_type)->toBe('status');
});

test('a status that is not locked cannot be asked about', function () {
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => null, 'workflow_status' => 'preparing']);

    $this->actingAs($agent)->post(route('admin.immigration.cases.request-edit', ['ecc', $case->id]), ['reason' => 'x'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'is not locked'));
});

test('reopening a released file whose status is Approved unlocks the status', function () {
    $agent = spAgent();
    $case = spCase($agent, ['workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString()]);

    $this->actingAs(spAdmin())->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))->assertSessionHas('success');

    $fresh = $case->fresh();
    expect($fresh->released_on)->toBeNull()->and($fresh->status_unlocked_at)->not->toBeNull()->and($fresh->approvedStatusLocked())->toBeFalse();
    spStatus($agent, $case, 'for_validation')->assertSessionHas('success');
});

test('the status dropdown: pop-up for an agent, lock icon and disabled options when Approved is locked', function () {
    $agent = spAgent();
    $case = spCase($agent);

    $html = $this->actingAs($agent)->get(spEdit($case))->assertOk()->getContent();
    expect($html)->toContain('confirmLock: true')->and($html)->toContain('This status will be locked');

    $this->actingAs(spAdmin())->get(spEdit($case))->assertOk()->assertSee('confirmLock: false', false);

    $case->forceFill(['workflow_status' => 'approved'])->save();
    $locked = $this->actingAs($agent)->get(spEdit($case))->assertOk()->getContent();
    expect($locked)->toContain('Approved is locked')->and($locked)->toMatch('/<option value="preparing"[^>]*disabled/');
    expect($locked)->not->toMatch('/<option value="approved"[^>]*disabled/');
});

test('the dropdown of a returned file is enabled with a Returned by Admin badge; a waiting one is disabled', function () {
    $agent = spAgent();
    $returned = spCase($agent, ['approval_status' => 'returned', 'review_note' => 'Fix', 'workflow_status' => 'for_validation']);
    $html = $this->actingAs($agent)->get(spEdit($returned))->assertOk()->getContent();
    expect($html)->toContain('Returned by Admin')->and($html)->toMatch('/<option value="for_validation"[^>]*selected/')->and($html)->not->toMatch('/<select name="status"[^>]*disabled/');

    $pending = spCase($agent, ['approval_status' => 'pending']);
    $this->actingAs($agent)->get(spEdit($pending))->assertOk()->assertSee('Waiting for Admin Approval')->assertSeeInOrder(['<select name="status"', 'disabled'], false);
});

test('the last step: an agent on a returned file gets Send for admin approval only; a file already sent cannot be sent again', function () {
    $agent = spAgent();
    $returned = spCase($agent, ['approval_status' => 'returned', 'review_note' => 'x']);
    $this->actingAs($agent)->get(spEdit($returned))->assertOk()
        ->assertSee('Send for admin approval')->assertSee('Send for Admin Approval')->assertDontSee('Save only')->assertDontSee('Save &amp; print', false)->assertDontSee('data-filled-forms', false);

    $pending = spCase($agent, ['approval_status' => 'pending']);
    $this->actingAs($agent)->get(spEdit($pending))->assertOk()
        ->assertSee('data-awaiting-admin', false)->assertSee('Sent, waiting for admin approval')
        ->assertDontSee('Send for admin approval')->assertDontSee('name="acknowledged"', false)->assertDontSee('Save &amp; print', false);
});

test('a file already sent is not saved again by an agent until the admin returns it', function () {
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => 'pending']);
    $client = ImmigrationClient::find($case->immigration_client_id);

    $this->actingAs($agent)->from(spEdit($case))->put(route('admin.client-sheets.update', $client), [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Changed', 'given_name' => 'Client', 'petition' => ['details' => ['ecc_kind' => 'ecc']],
    ])->assertSessionHas('error', fn (string $m) => str_contains($m, 'already sent'));
    expect($client->fresh()->last_name)->toBe('Rules');
});

test('the last step: an admin keeps Save only and Save & print', function () {
    $pending = spCase(spAgent(), ['approval_status' => 'pending']);

    $this->actingAs(spAdmin())->get(spEdit($pending))->assertOk()
        ->assertSee('Save only')->assertSee('Save &amp; print', false)->assertDontSee('Send for admin approval');
});

test('a supervisor with the Approve tick also sends for admin approval and cannot print before it is approved', function () {
    spAdmin();
    $supervisor = staffWith('agent', ['immigration.view', 'immigration.create', 'immigration.edit', 'immigration.approve'], ['immigration']);
    $case = spCase($supervisor, ['approval_status' => 'pending']);
    $id = $case->immigration_client_id;
    $query = ['service' => 'ecc', 'case' => $case->id];

    $this->actingAs($supervisor)->get(spEdit($case))->assertOk()
        ->assertSee('Sent, waiting for admin approval')->assertDontSee('Save only')->assertDontSee('Save &amp; print', false)->assertDontSee('data-filled-forms', false);

    foreach ([
        route('admin.client-sheets.print', ['clientSheet' => $id] + $query),
        route('admin.client-sheets.agreement', ['clientSheet' => $id] + $query),
        route('admin.client-sheets.consent', ['clientSheet' => $id] + $query),
        route('admin.client-sheets.petition-form', ['clientSheet' => $id, 'type' => 'ecc', 'case' => $case->id]),
    ] as $url) {
        $this->actingAs($supervisor)->get($url)->assertRedirect(spEdit($case));
    }

    // Once an admin approves it, the Print button and the filled forms appear.
    $case->forceFill(['approval_status' => 'approved'])->save();
    $this->actingAs($supervisor)->get(spEdit($case))->assertOk()->assertSee('data-filled-forms', false)
        ->assertSee(e(route('admin.client-sheets.petition-form', ['clientSheet' => $id, 'type' => 'ecc', 'case' => $case->id, 'autoprint' => 1])), false);
    $this->actingAs($supervisor)->get(route('admin.client-sheets.petition-form', ['clientSheet' => $id, 'type' => 'ecc', 'case' => $case->id]))->assertOk();
});

test('the last step of an approved file lists every filled form', function () {
    $agent = spAgent();
    $case = spCase($agent);

    $this->actingAs($agent)->get(spEdit($case))->assertOk()
        ->assertSee('data-filled-forms', false)->assertSee('Filled forms to print')
        ->assertSee(e(route('admin.client-sheets.print', ['clientSheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id])), false)
        ->assertSee(e(route('admin.client-sheets.agreement', ['clientSheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id])), false)
        ->assertSee(e(route('admin.client-sheets.consent', ['clientSheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id])), false)
        ->assertSee(e(route('admin.client-sheets.petition-form', ['clientSheet' => $case->immigration_client_id, 'type' => 'ecc', 'case' => $case->id])), false)
        ->assertSee(e(route('admin.client-sheets.petition-checklist', ['clientSheet' => $case->immigration_client_id, 'type' => 'ecc', 'case' => $case->id])), false);
});

test('the header only offers blank forms', function () {
    $agent = spAgent();
    $case = spCase($agent);

    $this->actingAs($agent)->get(spEdit($case))->assertOk()
        ->assertSee('Blank forms')
        ->assertSee(route('admin.client-sheets.blank'), false)
        ->assertSee(route('admin.client-sheets.agreement-blank', ['service' => 'ecc']), false)
        ->assertSee(route('admin.client-sheets.consent-blank'), false)
        ->assertSee(route('admin.client-sheets.petition-blank', ['type' => 'ecc', 'form' => 'form']), false);
});

test('an agent cannot print the filled forms of a pending or returned file; approvers and cleared files print', function () {
    $agent = spAgent();
    $pending = spCase($agent, ['approval_status' => 'pending']);
    $id = $pending->immigration_client_id;
    $query = ['service' => 'ecc', 'case' => $pending->id];

    foreach ([
        route('admin.client-sheets.print', ['clientSheet' => $id] + $query),
        route('admin.client-sheets.agreement', ['clientSheet' => $id] + $query),
        route('admin.client-sheets.consent', ['clientSheet' => $id] + $query),
    ] as $url) {
        $this->actingAs($agent)->get($url)->assertRedirect(spEdit($pending))->assertSessionHas('error', fn (string $m) => str_contains($m, 'waiting for an admin'));
        $this->actingAs(spAdmin())->get($url)->assertOk();
    }

    $pending->forceFill(['approval_status' => 'returned', 'review_note' => 'Fix'])->save();
    $this->actingAs($agent)->get(route('admin.client-sheets.print', ['clientSheet' => $id] + $query))->assertRedirect(spEdit($pending))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'returned this file'));

    $pending->forceFill(['approval_status' => 'approved'])->save();
    foreach (['print', 'agreement', 'consent'] as $route) {
        $this->actingAs($agent)->get(route("admin.client-sheets.{$route}", ['clientSheet' => $id] + $query))->assertOk();
    }
});

test('blank forms are never gated', function () {
    $agent = spAgent();
    spCase($agent, ['approval_status' => 'pending']);

    foreach ([route('admin.client-sheets.blank'), route('admin.client-sheets.agreement-blank'), route('admin.client-sheets.consent-blank')] as $url) {
        $this->actingAs($agent)->get($url)->assertOk();
    }
});

test('the Visa Extension forms are held while a ledger row waits for the admin', function () {
    $agent = spAgent();
    $client = ImmigrationClient::forceCreate(['last_name' => 'Ledger', 'given_name' => 'Client', 'created_by' => $agent->id]);
    ImmigrationClientExtension::forceCreate(['immigration_client_id' => $client->id, 'sequence' => 1, 'extension_date' => now()->toDateString(), 'approval_status' => 'pending']);

    $this->actingAs($agent)->get(route('admin.client-sheets.print', ['clientSheet' => $client->id]))
        ->assertRedirect()->assertSessionHas('error', fn (string $m) => str_contains($m, 'waiting for an admin'));
    $this->actingAs($agent)->get(route('admin.client-sheets.agreement', ['clientSheet' => $client->id]))->assertRedirect();
    $this->actingAs(spAdmin())->get(route('admin.client-sheets.print', ['clientSheet' => $client->id]))->assertOk();
});

test('saving with Save & print as an agent never redirects to print', function () {
    Notification::fake();
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => 'returned', 'review_note' => 'Fix']);

    $response = $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Rules', 'given_name' => 'Client', 'then' => 'print', 'acknowledged' => '1',
        'petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '2500', 'payment_type' => 'none'],
    ]);

    $response->assertRedirect(spEdit($case))->assertSessionHas('success', fn (string $m) => str_contains($m, 'once the admin approves'));
    expect($case->fresh()->approval_status)->toBe('pending');
});

test('the blank agreement and consent carry no client data and are in the dashboard dropdown', function () {
    $agent = spAgent();
    spCase($agent);

    $agreement = $this->actingAs($agent)->get(route('admin.client-sheets.agreement-blank'))->assertOk()->getContent();
    expect($agreement)->toContain('IMMIGRATION SERVICES BOOKING AGREEMENT')->and($agreement)->not->toContain(now()->format('F j, Y'))
        ->and($agreement)->not->toContain($agent->name)->and($agreement)->not->toContain('IMM-')->and($agreement)->not->toContain('<span class="box">✓</span>');

    $ticked = $this->actingAs($agent)->get(route('admin.client-sheets.agreement-blank', ['service' => 'ecc']))->assertOk()->getContent();
    expect($ticked)->toContain('<span class="box">✓</span>Emigration Clearance Certificate');

    $this->actingAs($agent)->get(route('admin.client-sheets.consent-blank'))->assertOk()->assertSee('Data Privacy Consent');

    $this->actingAs($agent)->get(route('admin.immigration.dashboard'))->assertOk()
        ->assertSee(route('admin.client-sheets.agreement-blank'), false)->assertSee(route('admin.client-sheets.consent-blank'), false);
});

test('a Visa Extension folder with a row waiting for the admin is not saved again by an agent', function () {
    $agent = spAgent();
    $client = ImmigrationClient::forceCreate(['last_name' => 'Ledger', 'given_name' => 'Client', 'created_by' => $agent->id]);
    $row = ImmigrationClientExtension::forceCreate(['immigration_client_id' => $client->id, 'sequence' => 1, 'extension_date' => now()->toDateString(), 'approval_status' => 'pending']);
    $save = fn (User $user) => test()->actingAs($user)->from('/back')->put(route('admin.client-sheets.update', $client), ['last_name' => 'Changed', 'given_name' => 'Client']);

    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $client->id]))->assertOk()->assertSee('Sent, waiting for admin approval');
    $save($agent)->assertSessionHas('error', fn (string $m) => str_contains($m, 'already sent'));
    expect($client->fresh()->last_name)->toBe('Ledger');

    // Once the admin returns it, the agent saves again; an admin was never blocked.
    $row->forceFill(['approval_status' => 'returned', 'review_note' => 'Fix'])->save();
    $save($agent)->assertSessionMissing('error');
    expect($client->fresh()->last_name)->toBe('Changed');
});

test('a returned file the agent marks Released reads Released, not Returned by Admin', function () {
    $agent = spAgent();
    $case = spCase($agent, ['approval_status' => 'returned', 'review_note' => 'Fix the fee', 'workflow_status' => 'approved']);

    spStatus($agent, $case, 'released', ['released_on' => now()->toDateString()])->assertSessionHas('success');
    expect($case->fresh()->released_on)->not->toBeNull();

    $html = $this->actingAs($agent)->get(spEdit($case))->assertOk()->getContent();
    expect($html)->not->toContain('Returned by Admin')->and($html)->not->toContain('Returned by the admin')
        ->and($html)->toMatch('/<i data-lucide="lock"[^>]*><\/i>\s*Released/');

    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()->assertDontSee('Returned by Admin');
});

test('the agent preview has the Notice button beside the status, as the file does', function () {
    $agent = spAgent();
    $case = spCase($agent, ['workflow_status' => 'approved']);

    $this->actingAs($agent)->get(spEdit($case, ['preview' => 1]))->assertOk()
        ->assertSeeInOrder(['<select name="status"', '<span>Notice</span>'], false);
});
