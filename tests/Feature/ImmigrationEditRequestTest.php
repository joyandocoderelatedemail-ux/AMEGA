<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Notifications\ImmigrationEditDecidedNotification;
use App\Notifications\ImmigrationEditRequestedNotification;
use App\Support\AdminNotifications;
use App\Support\ImmigrationCases;
use Illuminate\Support\Facades\Notification;

/**
 * A released file is locked. The agent asks the admin to unlock it (Request edit, with a reason); the admin
 * approves (the file opens again) or declines with a note (it stays locked and the agent may ask again).
 */
function editAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

function editAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** A released ECC in the agent's own folder. */
function releasedEcc(User $agent, array $case = []): ImmigrationPetition
{
    $client = ImmigrationClient::forceCreate(['last_name' => 'Locked', 'given_name' => 'Client', 'created_by' => $agent->id]);

    return ImmigrationPetition::withoutGlobalScopes()->forceCreate($case + [
        'immigration_client_id' => $client->id, 'type' => 'ecc', 'details' => [], 'created_by' => $agent->id,
        'workflow_status' => 'approved', 'released_on' => now()->subDay()->toDateString(), 'approval_status' => 'approved',
    ]);
}

function requestEditUrl(ImmigrationPetition $case): string
{
    return route('admin.immigration.cases.request-edit', ['ecc', $case->id]);
}

test('an agent asks for an edit on a released file with a reason, and the approvers are emailed', function () {
    Notification::fake();
    $admin = editAdmin();
    $agent = editAgent();
    $case = releasedEcc($agent);

    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'The passport number was typed wrong.'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'Edit requested'));

    $case->refresh();
    expect($case->hasEditRequest())->toBeTrue()
        ->and($case->edit_request_note)->toBe('The passport number was typed wrong.')
        ->and($case->edit_requested_by)->toBe($agent->id)
        ->and($case->released_on)->not->toBeNull();
    Notification::assertSentTo($admin, ImmigrationEditRequestedNotification::class, fn ($n) => $n->reason === 'The passport number was typed wrong.' && $n->reference === $case->reference());
    Notification::assertNotSentTo($agent, ImmigrationEditRequestedNotification::class);
});

test('a request needs a reason, a released file, no pending request, and a file the admin is not holding', function () {
    Notification::fake();
    $agent = editAgent();
    $case = releasedEcc($agent);

    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => str_repeat('a', 1001)])->assertSessionHasErrors('reason');
    expect($case->fresh()->hasEditRequest())->toBeFalse();

    // An open file is simply edited.
    $open = releasedEcc($agent, ['released_on' => null, 'approval_status' => null, 'workflow_status' => 'preparing']);
    $this->actingAs($agent)->post(requestEditUrl($open), ['reason' => 'x'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'is not locked'));

    // A file the admin is holding cannot be unlocked either.
    $held = releasedEcc($agent, ['approval_status' => 'pending']);
    $this->actingAs($agent)->post(requestEditUrl($held), ['reason' => 'x'])->assertSessionHas('error');
    expect($held->fresh()->hasEditRequest())->toBeFalse();

    // One request at a time.
    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'First'])->assertSessionHas('success');
    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'Second'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'already requested'));
    expect($case->fresh()->edit_request_note)->toBe('First');
});

test('an admin or approver reopens the file themselves instead of requesting an edit', function () {
    $case = releasedEcc(editAgent());

    $this->actingAs(editAdmin())->post(requestEditUrl($case), ['reason' => 'Fix it'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'You can reopen'));
    $this->actingAs(staffWith('agent', ['immigration.approve'], ['immigration']))->post(requestEditUrl($case), ['reason' => 'Fix it'])->assertSessionHas('error', fn (string $m) => str_contains($m, 'You can reopen'));
    expect($case->fresh()->hasEditRequest())->toBeFalse();
});

test('approving the edit request unlocks the file, clears the request and tells the agent', function () {
    Notification::fake();
    $agent = editAgent();
    $admin = editAdmin();
    $case = releasedEcc($agent);
    $case->requestEdit($agent, 'Wrong passport number');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]), ['note' => 'Go ahead'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'is open again'));

    $case->refresh();
    expect($case->released_on)->toBeNull()->and($case->workflow_status)->toBe('approved')
        ->and($case->edit_requested_at)->toBeNull()->and($case->edit_request_note)->toBeNull()->and($case->hasEditRequest())->toBeFalse();
    Notification::assertSentTo($agent, ImmigrationEditDecidedNotification::class, fn ($n) => $n->approved && $n->note === 'Go ahead');

    // The agent can save again, and the save goes back to the admin as usual.
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $case->immigration_client_id), [
        'service' => 'ecc', 'case_id' => $case->id, 'last_name' => 'Locked', 'given_name' => 'Client', 'then' => 'stay',
        'petition' => ['details' => ['ecc_kind' => 'ecc'], 'fee' => '1000', 'payment_type' => 'none'],
    ])->assertSessionHas('success');
    expect($case->fresh()->approval_status)->toBe('pending');
});

test('approving a file closed by Denied starts it over in preparation', function () {
    $agent = editAgent();
    $case = releasedEcc($agent, ['workflow_status' => 'denied']);
    $case->requestEdit($agent, 'Appeal');

    $this->actingAs(editAdmin())->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('success');

    expect($case->fresh()->released_on)->toBeNull()->and($case->fresh()->workflow_status)->toBe('preparing');
});

test('the file stays locked when the client already has another open one', function () {
    $agent = editAgent();
    $case = releasedEcc($agent);
    ImmigrationPetition::withoutGlobalScopes()->forceCreate(['immigration_client_id' => $case->immigration_client_id, 'type' => 'ecc', 'details' => [], 'created_by' => $agent->id]);
    $case->requestEdit($agent, 'Fix');

    $this->actingAs(editAdmin())->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('error', fn (string $m) => str_contains($m, 'another open transaction'));

    expect($case->fresh()->released_on)->not->toBeNull()->and($case->fresh()->hasEditRequest())->toBeTrue();
});

test('declining needs a note, keeps the file locked, and the agent can ask again', function () {
    Notification::fake();
    $agent = editAgent();
    $admin = editAdmin();
    $case = releasedEcc($agent);
    $case->requestEdit($agent, 'Change the fee');
    $decline = route('admin.immigration-approvals.edit-decline', ['ecc', $case->id]);

    $this->actingAs($admin)->post($decline, ['note' => ''])->assertSessionHasErrors('note');
    expect($case->fresh()->hasEditRequest())->toBeTrue();

    $this->actingAs($admin)->post($decline, ['note' => 'The fee was already paid and receipted.'])->assertSessionHas('success');

    $case->refresh();
    expect($case->released_on)->not->toBeNull()->and($case->hasEditRequest())->toBeFalse()->and($case->editRequestDeclined())->toBeTrue()
        ->and($case->edit_request_reply)->toBe('The fee was already paid and receipted.');
    Notification::assertSentTo($agent, ImmigrationEditDecidedNotification::class, fn ($n) => ! $n->approved && str_contains($n->note, 'receipted'));

    // The agent sees the reply and asks again; the new request replaces the old one.
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]))
        ->assertOk()->assertSee('Edit declined')->assertSee('The fee was already paid and receipted.')->assertSee('Request edit');
    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'A new receipt arrived'])->assertSessionHas('success');
    $case->refresh();
    expect($case->hasEditRequest())->toBeTrue()->and($case->edit_request_reply)->toBeNull()->and($case->edit_request_note)->toBe('A new receipt arrived');
});

test('whoever asked cannot approve their own request while another approver exists, and a plain agent cannot decide', function () {
    $agent = editAgent();
    $case = releasedEcc($agent);
    $supervisor = staffWith('agent', ['immigration.approve'], ['immigration']);
    $case->forceFill(['edit_requested_at' => now(), 'edit_requested_by' => $supervisor->id, 'edit_request_note' => 'Mine'])->save();
    editAdmin();

    $this->actingAs($supervisor)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('error', fn (string $m) => str_contains($m, 'another approver'));
    expect($case->fresh()->released_on)->not->toBeNull();

    // A desk agent without the approve permission never reaches the approvals routes.
    $this->actingAs($agent)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertForbidden();
    expect($case->fresh()->released_on)->not->toBeNull();
});

test('approving or declining a request that is not there is refused', function () {
    $case = releasedEcc(editAgent());

    $this->actingAs(editAdmin())->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]))->assertSessionHas('error', fn (string $m) => str_contains($m, 'no edit request'));
    $this->actingAs(editAdmin())->post(route('admin.immigration-approvals.edit-decline', ['ecc', $case->id]), ['note' => 'No'])->assertSessionHas('error');
    expect($case->fresh()->released_on)->not->toBeNull();
});

test('the approvals page lists the edit request with the agent reason, and the declined one under Returned', function () {
    $agent = editAgent();
    $admin = editAdmin();
    $case = releasedEcc($agent);
    $case->requestEdit($agent, 'Spelling of the surname');

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()
        ->assertSee('Edit request')->assertSee('Spelling of the surname')->assertSee('Approve edit')->assertSee('Decline')
        ->assertSee(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]), false)
        ->assertSee(route('admin.immigration-approvals.edit-decline', ['ecc', $case->id]), false);

    $case->declineEdit('Not needed');
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()->assertDontSee('Approve edit');
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index', ['tab' => 'returned']))->assertOk()
        ->assertSee('Edit declined')->assertSee('Spelling of the surname')->assertSee('Not needed');
});

test('a pending edit request counts for the approvers in the bell and the card counts, not for the agent who asked', function () {
    $agent = editAgent();
    $admin = editAdmin();
    $case = releasedEcc($agent);

    expect(AdminNotifications::for($admin)['immigration_approvals'])->toBe(0)
        ->and(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 0, 'waiting' => 0]);

    $case->requestEdit($agent, 'Fix it');

    expect(AdminNotifications::for($admin)['immigration_approvals'])->toBe(1)
        ->and(AdminNotifications::for($admin)['latest'][0]['title'])->toContain('waiting for your approval')
        ->and(ImmigrationCases::activeCounts()['ecc'])->toBe(['open' => 1, 'waiting' => 1]);

    // A request an approver made themselves is not waiting on them.
    $mine = releasedEcc($agent);
    $mine->forceFill(['edit_requested_at' => now(), 'edit_requested_by' => $admin->id, 'edit_request_note' => 'Mine'])->save();
    expect(AdminNotifications::for($admin)['immigration_approvals'])->toBe(1);

    // Declined: no longer waiting.
    $case->declineEdit('No');
    expect(ImmigrationCases::activeCounts()['ecc']['waiting'])->toBe(1); // only the approver's own pending one remains
});

test('the service list puts a pending edit request under Needs action, and a declined one stays with the released files', function () {
    $agent = editAgent();
    $waiting = releasedEcc($agent);
    $waiting->client->update(['last_name' => 'Waiting-Edit']);
    $waiting->requestEdit($agent, 'Fix');
    $declined = releasedEcc($agent);
    $declined->client->update(['last_name' => 'Declined-Edit']);
    $declined->requestEdit($agent, 'Fix');
    $declined->declineEdit('No');
    $plain = releasedEcc($agent);
    $plain->client->update(['last_name' => 'Plain-Released']);

    $page = $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSeeInOrder(['Needs action', 'Waiting-Edit', 'Edit requested: waiting for the admin', 'Released', 'Edit declined'])
        ->getContent();

    expect(editRowState($page, 'Waiting-Edit'))->toBe('action')
        ->and(editRowState($page, 'Declined-Edit'))->toBe('released')
        ->and(editRowState($page, 'Plain-Released'))->toBe('released');
});

test('the Request edit box is on the file header, the Cases page and the agent preview, and Reopen shows for an approver', function () {
    $agent = editAgent();
    $case = releasedEcc($agent);
    $edit = route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]);

    $this->actingAs($agent)->get($edit)->assertOk()
        ->assertSee('Request edit')->assertSee('Why does this released file need to be edited?')->assertSee('name="reason"', false)->assertDontSee('>Reopen<', false);
    $this->actingAs($agent)->get($edit.'&preview=1')->assertOk()->assertSee(requestEditUrl($case), false);
    $this->actingAs($agent)->get(route('admin.immigration.cases'))->assertOk()->assertSee(requestEditUrl($case), false);

    $case->requestEdit($agent, 'Please unlock');
    $this->actingAs($agent)->get($edit)->assertOk()->assertSee('Edit requested: waiting for the admin')->assertSee('Please unlock')->assertDontSee('name="reason"', false);
    $this->actingAs(editAdmin())->get($edit)->assertOk()->assertSee('Reopen')->assertSee('Edit requested');
});

test('reopening a file yourself clears a pending edit request', function () {
    $agent = editAgent();
    $case = releasedEcc($agent);
    $case->requestEdit($agent, 'Fix');

    $this->actingAs(editAdmin())->post(route('admin.immigration.cases.reopen', ['ecc', $case->id]))->assertSessionHas('success');

    expect($case->fresh()->released_on)->toBeNull()->and($case->fresh()->edit_requested_at)->toBeNull();
});

/** The data-state of a service-list row (action, progress, released), or null when it is not listed. */
function editRowState(string $html, string $name): ?string
{
    return preg_match('/<li data-state="([a-z_]+)"(?:(?!<\/li>).)*?'.preg_quote($name, '/').'/s', $html, $m) ? $m[1] : null;
}

test('the edit request and decision emails carry the reference, the reason and the admin note', function () {
    $requested = (new ImmigrationEditRequestedNotification('ECC-2026-0001', 'John Smith', 'Maria', 'Wrong passport number'))->toMail(editAdmin());
    $approved = (new ImmigrationEditDecidedNotification('ECC-2026-0001', 'John Smith', true, 'Shey', 'Go ahead'))->toMail(editAgent());
    $declined = (new ImmigrationEditDecidedNotification('ECC-2026-0001', 'John Smith', false, 'Shey', 'Already receipted'))->toMail(editAgent());

    expect($requested->subject)->toContain('Edit requested')
        ->and((string) $requested->render())->toContain('ECC-2026-0001')->toContain('Wrong passport number')->toContain(route('admin.immigration-approvals.index'))
        ->and($approved->subject)->toContain('Edit approved')->and((string) $approved->render())->toContain('open again')->toContain('Go ahead')
        ->and($declined->subject)->toContain('Edit declined')->and((string) $declined->render())->toContain('stays locked')->toContain('Already receipted');
});

test('a request only to change the status reopens the released file and leaves it approved, so the status can move', function () {
    Notification::fake();
    $admin = editAdmin();
    $agent = editAgent();
    $case = releasedEcc($agent);
    $statusUrl = route('admin.immigration.cases.status', ['ecc', $case->id]);

    // Locked while released.
    $this->actingAs($agent)->post($statusUrl, ['status' => 'awaiting_decision'])->assertSessionHas('error');

    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'The BI sent it back for a decision.', 'type' => 'status'])->assertSessionHas('success');
    expect($case->fresh()->edit_request_type)->toBe('status');
    Notification::assertSentTo($admin, ImmigrationEditRequestedNotification::class, fn ($n) => $n->type === 'status');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]), ['note' => 'Fine'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'change its status'));

    $case->refresh();
    // Reopened, still approved (not returned): no new approval round, and the request is gone.
    expect($case->released_on)->toBeNull()
        ->and($case->approval_status)->toBe('approved')
        ->and($case->edit_requested_at)->toBeNull()
        ->and($case->edit_request_type)->toBeNull();
    Notification::assertSentTo($agent, ImmigrationEditDecidedNotification::class, fn ($n) => $n->approved && $n->type === 'status');

    // The status moves again.
    $this->actingAs($agent)->post($statusUrl, ['status' => 'awaiting_decision'])->assertSessionHasNoErrors();
    expect($case->fresh()->workflow_status)->toBe('awaiting_decision');

    // The details are still locked for the agent.
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $case->immigration_client_id, 'service' => 'ecc', 'case' => $case->id]))
        ->assertOk()->assertSee('approved and locked');
});

test('a request to correct the details still returns the file, and the type defaults to details', function () {
    $admin = editAdmin();
    $agent = editAgent();
    $case = releasedEcc($agent);

    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'Passport number is wrong.'])->assertSessionHas('success');
    expect($case->fresh()->edit_request_type)->toBe('details');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.edit-approve', ['ecc', $case->id]));
    expect($case->fresh()->approval_status)->toBe('returned')->and($case->fresh()->released_on)->toBeNull();
});

test('a status-only request is for released files: an approved file that is open already moves its status itself', function () {
    $agent = editAgent();
    $case = releasedEcc($agent, ['released_on' => null, 'workflow_status' => 'preparing']);

    $this->actingAs($agent)->post(requestEditUrl($case), ['reason' => 'Need a new status.', 'type' => 'status'])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'not locked'));
    expect($case->fresh()->hasEditRequest())->toBeFalse();

    $this->actingAs($agent)->post(requestEditUrl(releasedEcc($agent)), ['reason' => 'x', 'type' => 'nonsense'])->assertSessionHasErrors('type');
});

test('the Request edit box asks released files what is needed, and the approvals page says which', function () {
    $admin = editAdmin();
    $agent = editAgent();
    $released = releasedEcc($agent);
    $approved = releasedEcc($agent, ['released_on' => null, 'workflow_status' => 'preparing']);

    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $released->immigration_client_id, 'service' => 'ecc', 'case' => $released->id]))
        ->assertOk()->assertSee('What do you need?')->assertSee('Change the status only')->assertSee("Correct the file's details", false);
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $approved->immigration_client_id, 'service' => 'ecc', 'case' => $approved->id]))
        ->assertOk()->assertDontSee('What do you need?')->assertDontSee('name="type"', false)->assertDontSee(requestEditUrl($approved), false);

    $this->actingAs($agent)->post(requestEditUrl($released), ['reason' => 'Back for a decision.', 'type' => 'status']);
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()
        ->assertSee('Status change')->assertSee('Approve reopening')->assertSee('its details stay locked');
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $released->immigration_client_id, 'service' => 'ecc', 'case' => $released->id]))
        ->assertOk()->assertSee('Status change requested');
});
