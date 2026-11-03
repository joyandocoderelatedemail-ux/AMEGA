<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Notifications\ImmigrationEditDecidedNotification;
use App\Support\ImmigrationCases;
use Illuminate\Support\Facades\Notification;

/**
 * An approved case's content is locked for agents, but its status stays free until it is released; Request edit is
 * for released files (and a locked Approved status). Approvers keep editing approved files directly.
 */
function apLockAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

function apLockAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** An approved (not released) ECC in the agent's own folder. */
function approvedEcc(User $agent, array $case = []): ImmigrationPetition
{
    $client = ImmigrationClient::forceCreate(['last_name' => 'Approved', 'given_name' => 'Client', 'created_by' => $agent->id]);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($case + [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => ['ecc_kind' => 'ecc'], 'created_by' => $agent->id,
        'workflow_status' => 'preparing', 'approval_status' => 'approved', 'fee' => 1000, 'payment_type' => 'none',
    ]);
}

function apLockSave(ImmigrationPetition $case, array $overrides = []): array
{
    return $overrides + [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Approved', 'given_name' => 'Client', 'then' => 'stay',
        'petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '2500', 'payment_type' => 'none'],
    ];
}

function apLockEditUrl(ImmigrationPetition $case, array $extra = []): string
{
    return route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id] + $extra);
}

test('an agent cannot save an approved case: nothing is written and the input is kept', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent);

    $this->actingAs($agent)->from(apLockEditUrl($case))->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case, ['last_name' => 'Renamed']))
        ->assertRedirect(apLockEditUrl($case))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'details are locked') && str_contains($m, 'status dropdown'))
        ->assertSessionHasInput('last_name', 'Renamed');

    expect($case->fresh()->approval_status)->toBe('approved')->and((float) $case->fresh()->fee)->toBe(1000.0)
        ->and($case->client->fresh()->last_name)->toBe('Approved');
});

test('an admin or an approver still saves an approved case directly', function () {
    $case = approvedEcc(apLockAgent());

    $this->actingAs(apLockAdmin())->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case))->assertSessionHas('success');
    expect((float) $case->fresh()->fee)->toBe(2500.0)->and($case->fresh()->approval_status)->toBe('approved');

    $supervisor = staffWith('agent', ['immigration.approve'], ['immigration']);
    $this->actingAs($supervisor)->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case, ['petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '3000', 'payment_type' => 'none']]))->assertSessionHas('success');
    expect((float) $case->fresh()->fee)->toBe(3000.0);
});

test('a held case behaves as before: a returned one is editable by the agent', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent, ['approval_status' => 'returned', 'review_note' => 'Fix the fee']);

    $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case))->assertSessionHas('success');

    expect((float) $case->fresh()->fee)->toBe(2500.0)->and($case->fresh()->approval_status)->toBe('pending');
});

test('an approved, unreleased case is not asked about; an edit request already on one is still decided, and returns it for editing', function () {
    Notification::fake();
    $agent = apLockAgent();
    $admin = apLockAdmin();
    $case = approvedEcc($agent);

    // Its status is free, so there is nothing to ask the admin for.
    $this->actingAs($agent)->post(route('admin.immigration.cases.request-edit', ['ecc', $case->id]), ['reason' => 'Wrong fee'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'is not locked'));
    expect($case->fresh()->hasEditRequest())->toBeFalse();

    // A request made before this rule is still decided by the admin.
    $case->requestEdit($agent, 'Wrong fee');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]), ['note' => 'Go ahead'])->assertSessionHas('success');

    $case->refresh();
    expect($case->approval_status)->toBe('returned')->and($case->review_note)->toContain('Edit approved: Go ahead')->toContain('save to send it for approval again')
        ->and($case->reviewed_by)->toBe($admin->id)->and($case->reviewed_at)->not->toBeNull()
        ->and($case->edit_requested_at)->toBeNull()->and($case->edit_request_note)->toBeNull()->and($case->hasEditRequest())->toBeFalse();
    Notification::assertSentTo($agent, ImmigrationEditDecidedNotification::class, fn ($n) => $n->approved);

    // The agent edits, and the save goes to the admin again.
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case))->assertSessionHas('success');
    expect($case->fresh()->approval_status)->toBe('pending')->and((float) $case->fresh()->fee)->toBe(2500.0);
});

test('printing is blocked while an unlocked case is back with the admin', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent);
    $case->requestEdit($agent, 'Fix');
    $this->actingAs(apLockAdmin())->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('success');

    $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case, ['then' => 'print']))
        ->assertRedirect(apLockEditUrl($case, []))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'once the admin approves it'));
});

test('approving an edit on a released case clears the release and returns the file, with a default note', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent, ['released_on' => now()->subDay()->toDateString(), 'workflow_status' => 'approved']);
    $case->requestEdit($agent, 'Typo');

    $this->actingAs(apLockAdmin())->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('success');

    $case->refresh();
    expect($case->released_on)->toBeNull()->and($case->approval_status)->toBe('returned')
        ->and($case->review_note)->toContain('Edit approved: ')->toContain('Make your changes')->and($case->hasEditRequest())->toBeFalse();
});

test('a direct Reopen by an approver only clears the release', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent, ['released_on' => now()->subDay()->toDateString(), 'workflow_status' => 'approved']);

    $this->actingAs(apLockAdmin())->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))->assertSessionHas('success');

    expect($case->fresh()->released_on)->toBeNull()->and($case->fresh()->approval_status)->toBe('approved');
});

test('declining the request keeps an approved case locked', function () {
    Notification::fake();
    $agent = apLockAgent();
    $case = approvedEcc($agent);
    $case->requestEdit($agent, 'Change it');

    $this->actingAs(apLockAdmin())->post(route('admin.immigration-approvals.edit-decline', ['ecc', $case->id]), ['note' => 'No need'])->assertSessionHas('success');
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), apLockSave($case))->assertSessionHas('error');

    expect($case->fresh()->approval_status)->toBe('approved')->and($case->fresh()->editRequestDeclined())->toBeTrue();
});

test('Request edit needs a released case, and an approver reopens it directly instead', function () {
    $agent = apLockAgent();
    $url = fn ($c) => route('admin.immigration.cases.request-edit', ['ecc', $c->id]);

    foreach ([approvedEcc($agent, ['approval_status' => null]), approvedEcc($agent)] as $open) {
        $this->actingAs($agent)->post($url($open), ['reason' => 'x'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'is not locked'));
    }

    $held = approvedEcc($agent, ['approval_status' => 'pending', 'released_on' => now()->toDateString()]);
    $this->actingAs($agent)->post($url($held), ['reason' => 'x'])->assertSessionHas('error');

    $released = approvedEcc($agent, ['released_on' => now()->toDateString()]);
    $this->actingAs(apLockAdmin())->post($url($released), ['reason' => 'x'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'You can reopen or edit'));
    $this->actingAs($agent)->post($url($released), ['reason' => ''])->assertSessionHasErrors('reason');
    expect($released->fresh()->hasEditRequest())->toBeFalse();
});

test('the agent preview of an approved case has the status dropdown, no Edit and no Request edit; an admin keeps Edit', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent);

    $page = $this->actingAs($agent)->get(apLockEditUrl($case, ['preview' => 1]))->assertOk()
        ->assertSee('its details are locked; you can still update its status')->assertDontSee('Request edit')->getContent();
    expect($page)->not->toContain('>Edit</a>')->and($page)->toContain('<select name="status"');

    $this->actingAs(apLockAdmin())->get(apLockEditUrl($case, ['preview' => 1]))->assertOk()->assertSee('>Edit</a>', false);
    $this->actingAs(staffWith('agent', ['immigration.approve'], ['immigration']))->get(apLockEditUrl($case, ['preview' => 1]))->assertOk()->assertSee('>Edit</a>', false);
});

test('the edit page tells an agent an approved case is locked, and an approver is not told so', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent);

    $this->actingAs($agent)->get(apLockEditUrl($case))->assertOk()
        ->assertSee('This file is approved and locked, so saving is switched off.')->assertSee('You can still update its status above')->assertDontSee('Use Request edit above')
        ->assertDontSee('change something and Save comes back');
    $this->actingAs(apLockAdmin())->get(apLockEditUrl($case))->assertOk()->assertDontSee('approved and locked, so saving is switched off');
});

test('the status dropdown and Release still work on an approved case', function () {
    $agent = apLockAgent();
    $case = approvedEcc($agent);

    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'submitted'])->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('submitted');

    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'released', 'released_on' => now()->toDateString()])->assertSessionHas('success');
    expect($case->fresh()->released_on)->not->toBeNull()->and($case->fresh()->approval_status)->toBe('approved');
});

test('a pending request on an approved case is counted for approvers and listed under Needs action', function () {
    $agent = apLockAgent();
    $admin = apLockAdmin();
    $case = approvedEcc($agent);
    $case->client->update(['last_name' => 'Approved-Waiting']);

    expect(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 1, 'waiting' => 0]);

    $case->requestEdit($agent, 'Fix');

    expect(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 1, 'waiting' => 1]);
    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Approved-Waiting', 'Edit requested: waiting for the admin']);
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()->assertSee('Edit request');
});
