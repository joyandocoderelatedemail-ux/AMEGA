<?php

use App\Models\ImmigrationClient;
use App\Models\ImmigrationPetition;
use App\Models\User;
use App\Notifications\ImmigrationApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function approvalAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function approvalAgent(): User
{
    return User::factory()->create(['role' => 'agent', 'allowed_pages' => ['immigration']]);
}

function approvalSheet(): ImmigrationClient
{
    return ImmigrationClient::create(['last_name' => 'Kim', 'given_name' => 'Minjun', 'passport_number' => 'M123456', 'nationality' => 'Korean', 'visa_expiry_date' => '2026-10-21']);
}

/** An ECC saved the way the wizard sends it, with the acknowledgement ticked. */
function saveEcc(object $test, User $user, ImmigrationClient $sheet, array $petition = [], array $extra = [])
{
    return $test->actingAs($user)->put(route('admin.client-sheets.update', $sheet), array_merge([
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'petition' => ['details' => ['ecc_kind' => 'ecc']] + $petition,
    ], $extra));
}

test('the last step asks for an acknowledgement before saving, and the save is refused without it', function () {
    $sheet = approvalSheet();
    $admin = approvalAdmin();

    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('I acknowledge that the dates, the fee, the payment and all the details entered are correct.')
        ->assertSee('name="acknowledged"', false)->assertSee('name="acknowledgement_required"', false)
        ->assertSee(':disabled="! acknowledged"', false);

    $this->actingAs($admin)->put(route('admin.client-sheets.update', $sheet), [
        'service' => 'ecc', 'last_name' => 'Kim', 'given_name' => 'Minjun', 'acknowledgement_required' => 1,
        'petition' => ['details' => ['ecc_kind' => 'ecc']],
    ])->assertSessionHasErrors('acknowledged');
    expect($sheet->fresh()->petitions)->toHaveCount(0);

    saveEcc($this, $admin, $sheet)->assertSessionHasNoErrors();
    expect($sheet->fresh()->petitions)->toHaveCount(1);
});

test('an admin saving a case approves it on the spot, and nothing is sent', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $sheet = approvalSheet();

    saveEcc($this, $admin, $sheet);

    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->approval_status)->toBe('approved')->and($case->reviewed_by)->toBe($admin->id)->and($case->acknowledged_by)->toBe($admin->id);
    Notification::assertNothingSent();
});

test('an agent saving a case sends it to the admins, and cannot print or file it until it is approved', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();

    saveEcc($this, $agent, $sheet)->assertSessionHas('success', fn (string $m) => str_contains($m, 'Sent to the admin for approval.'));

    $case = $sheet->fresh()->openPetition('ecc');
    expect($case->approval_status)->toBe('pending')->and($case->acknowledged_by)->toBe($agent->id)->and($case->approval_requested_at)->not->toBeNull();
    Notification::assertSentTo($admin, ImmigrationApprovalRequestedNotification::class);

    // Printing goes back to the case; the admin prints it.
    $this->actingAs($agent)->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc', 'case' => $case->id]))->assertRedirect()
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'waiting for an admin to approve it'));
    $this->actingAs($agent)->get(route('admin.client-sheets.petition-checklist', [$sheet, 'ecc', 'case' => $case->id]))->assertRedirect();
    $this->actingAs($admin)->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc', 'case' => $case->id]))->assertOk();

    // Not marked submitted, approved or released while it waits; the early stages are fine.
    foreach (['submitted', 'awaiting_decision', 'for_briefing', 'approved'] as $status) {
        $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => $status])->assertSessionHas('error');
    }
    $this->actingAs($agent)->post(route('admin.immigration.cases.release', ['ecc', $case->id]), ['released_on' => now()->toDateString()])->assertSessionHas('error');
    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'for_validation'])->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('for_validation')->and($case->fresh()->released_on)->toBeNull();
});

test('Save and print by an agent stays on the case and says it prints once approved', function () {
    Notification::fake();
    $agent = approvalAgent();
    $sheet = approvalSheet();

    $response = saveEcc($this, $agent, $sheet, [], ['then' => 'print']);
    $response->assertSessionHas('success', fn (string $m) => str_contains($m, 'You can print it once the admin approves it.'));
    expect($response->headers->get('Location'))->toContain('/edit')->not->toContain('/petition/ecc/form');
});

test('Save and print by an admin goes straight to the form', function () {
    $sheet = approvalSheet();

    $response = saveEcc($this, approvalAdmin(), $sheet, [], ['then' => 'print']);
    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('/petition/ecc/form');
});

test('an admin approves with an acknowledgement, and the agent can then print and file it', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->openPetition('ecc');

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()
        ->assertSee($case->reference())->assertSee('Minjun Kim')->assertSee($agent->name);

    // Only an admin reaches the page.
    $this->actingAs($agent)->get(route('admin.immigration-approvals.index'))->assertRedirect();
    $this->actingAs($agent)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]), ['admin_acknowledged' => 1])->assertRedirect();
    expect($case->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]))->assertSessionHasErrors('admin_acknowledged');
    expect($case->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    $case = $case->fresh();
    expect($case->approval_status)->toBe('approved')->and($case->reviewed_by)->toBe($admin->id)->and($case->reviewed_at)->not->toBeNull();

    $this->actingAs($agent)->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc', 'case' => $case->id]))->assertOk();
    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'submitted'])->assertSessionHas('success');
    expect($case->fresh()->workflow_status)->toBe('submitted');

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index', ['tab' => 'approved']))->assertOk()->assertSee($case->reference());
});

test('a returned case shows the reason, blocks printing, and goes back to pending when the agent corrects it', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->openPetition('ecc');

    $this->actingAs($admin)->post(route('admin.immigration-approvals.reject', ['ecc', $case->id]), [])->assertSessionHasErrors('review_note');
    $this->actingAs($admin)->post(route('admin.immigration-approvals.reject', ['ecc', $case->id]), ['review_note' => 'The departure date is wrong'])->assertSessionHas('success');
    expect($case->fresh()->approval_status)->toBe('returned')->and($case->fresh()->review_note)->toBe('The departure date is wrong');

    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id]))->assertOk()
        ->assertSee('Returned by the admin: The departure date is wrong');
    $this->actingAs($agent)->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc', 'case' => $case->id]))->assertRedirect()
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'The departure date is wrong'));
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index', ['tab' => 'returned']))->assertOk()->assertSee('The departure date is wrong');

    saveEcc($this, $agent, $sheet);
    $case = $case->fresh();
    expect($case->approval_status)->toBe('pending')->and($case->review_note)->toBeNull();
    Notification::assertSentToTimes($admin, ImmigrationApprovalRequestedNotification::class, 2);
});

test('the Cases list and the folder show a case waiting for the admin', function () {
    Notification::fake();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);

    $this->actingAs(approvalAdmin())->get(route('admin.immigration.cases'))->assertOk()->assertSee('Waiting for Admin Approval');
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('Waiting for the admin to approve this file.');
});

test('the payment option records full, a downpayment, terms or nothing, against the fee', function () {
    $admin = approvalAdmin();
    $sheet = approvalSheet();
    $save = fn (array $petition) => saveEcc($this, $admin, $sheet, $petition);
    $case = fn () => $sheet->fresh()->openPetition('ecc');

    $save(['fee' => '5000', 'payment_type' => 'full'])->assertSessionHasNoErrors();
    expect($case()->payment_type)->toBe('full')->and((float) $case()->amount_paid)->toBe(5000.0)->and($case()->paymentBalance())->toBe(0.0)->and($case()->paymentLabel())->toBe('Full payment');

    $save(['fee' => '5000', 'payment_type' => 'downpayment', 'amount_paid' => '1500'])->assertSessionHasNoErrors();
    expect($case()->payment_type)->toBe('downpayment')->and((float) $case()->amount_paid)->toBe(1500.0)->and($case()->paymentBalance())->toBe(3500.0)->and($case()->terms_count)->toBeNull();

    $save(['fee' => '6000', 'payment_type' => 'terms', 'amount_paid' => '2000', 'terms_count' => '3'])->assertSessionHasNoErrors();
    expect($case()->payment_type)->toBe('terms')->and($case()->terms_count)->toBe(3)->and($case()->paymentBalance())->toBe(4000.0);

    $save(['fee' => '6000', 'payment_type' => 'none', 'amount_paid' => '999', 'terms_count' => '4'])->assertSessionHasNoErrors();
    expect($case()->payment_type)->toBe('none')->and((float) $case()->amount_paid)->toBe(0.0)->and($case()->terms_count)->toBeNull();

    // The case form shows the options and what is on file.
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc']))->assertOk()
        ->assertSee('Payment option')->assertSee('Full payment')->assertSee('Downpayment')->assertSee('Terms')->assertSee('No payment')
        ->assertSee('name="petition[payment_type]"', false)->assertSee('name="petition[terms_count]"', false);
});

test('the payment option is checked against the fee', function () {
    $admin = approvalAdmin();
    $sheet = approvalSheet();
    $save = fn (array $petition) => saveEcc($this, $admin, $sheet, $petition);

    $save(['payment_type' => 'full'])->assertSessionHasErrors('petition.payment_type');
    $save(['fee' => '1000', 'payment_type' => 'downpayment'])->assertSessionHasErrors('petition.amount_paid');
    $save(['fee' => '1000', 'payment_type' => 'downpayment', 'amount_paid' => '1500'])->assertSessionHasErrors('petition.amount_paid');
    $save(['fee' => '1000', 'payment_type' => 'terms', 'amount_paid' => '100'])->assertSessionHasErrors('petition.terms_count');
    $save(['fee' => '1000', 'payment_type' => 'terms', 'amount_paid' => '100', 'terms_count' => '1'])->assertSessionHasErrors('petition.terms_count');
    $save(['fee' => '1000', 'payment_type' => 'bogus'])->assertSessionHasErrors('petition.payment_type');
    $save(['payment_type' => 'none'])->assertSessionHasNoErrors();
    expect($sheet->fresh()->petitions)->toHaveCount(1);
});

test('every kind of case takes the payment option', function () {
    $admin = approvalAdmin();
    $sheet = approvalSheet();
    $put = fn (array $payload) => $this->actingAs($admin)->put(route('admin.client-sheets.update', $sheet), ['last_name' => 'Kim', 'given_name' => 'Minjun'] + $payload)->assertSessionHasNoErrors();
    $payment = ['fee' => '8000', 'payment_type' => 'downpayment', 'amount_paid' => '2000'];

    $put(['service' => 'visa_overstaying', 'overstay' => ['expired_on' => '2026-09-01'] + $payment]);
    $put(['service' => 're_stamping_amendment', 'restamping' => ['applications' => ['transfer']] + $payment]);
    $put(['service' => '13a_spouse', 'petition' => ['details' => ['spouse_name' => 'Maria']] + $payment]);

    expect($sheet->fresh()->overstays->first()->payment_type)->toBe('downpayment')
        ->and($sheet->fresh()->restampings->first()->paymentBalance())->toBe(6000.0)
        ->and($sheet->fresh()->openPetition('spouse')->payment_type)->toBe('downpayment')
        ->and($sheet->fresh()->overstays->first()->approval_status)->toBe('approved');
});

test('a Visa Extension an agent adds waits for the admin, and the visa moves on when it is approved', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();

    $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => '2026-10-21', 'acknowledgement_required' => 1, 'acknowledged' => 1, 'then' => 'stay',
        'extensions' => [1 => ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days']],
        'extension_payment' => ['payment_type' => 'downpayment', 'amount_received' => '1000'],
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, 'Sent to the admin for approval.') && str_contains($m, 'The visa moves on once the admin approves the extension.'));

    $row = $sheet->fresh()->extensionAt(1);
    expect($row->approval_status)->toBe('pending')->and($row->payment_type)->toBe('downpayment')->and((float) $row->amount_received)->toBe(1000.0)
        ->and($row->visa_extended_at)->toBeNull()->and($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2026-10-21');
    Notification::assertSentTo($admin, ImmigrationApprovalRequestedNotification::class);

    // The BI forms for the folder wait too.
    $this->actingAs($agent)->get(route('admin.client-sheets.bi-form', [$sheet, 'cgaf']))->assertRedirect();
    $this->actingAs($admin)->get(route('admin.client-sheets.bi-form', [$sheet, 'cgaf']))->assertOk();

    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()->assertSee('Visa Extension')->assertSee('Minjun Kim')->assertSee('Downpayment');
    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['extensions', $sheet->id]), ['admin_acknowledged' => 1])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'The visa is now valid until November 19, 2026.'));

    expect($sheet->fresh()->extensionAt(1)->approval_status)->toBe('approved')->and($sheet->fresh()->extensionAt(1)->visa_extended_at)->not->toBeNull()
        ->and($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2026-11-19');

    // Approving again moves nothing.
    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['extensions', $sheet->id]), ['admin_acknowledged' => 1])->assertSessionHas('error');
    expect($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2026-11-19');
    $this->actingAs($agent)->get(route('admin.client-sheets.bi-form', [$sheet, 'cgaf']))->assertOk();
});

test('a returned Visa Extension goes back to the agent, and an admin adding one moves the visa at once', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    $row = ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days'];
    $payload = fn () => ['last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => $sheet->fresh()->visa_expiry_date->toDateString(), 'then' => 'stay', 'extensions' => [1 => $row]];

    $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), $payload());
    $this->actingAs($admin)->post(route('admin.immigration-approvals.reject', ['extensions', $sheet->id]), ['review_note' => 'Wrong SOA number'])->assertSessionHas('success');
    expect($sheet->fresh()->extensionAt(1)->approval_status)->toBe('returned')->and($sheet->fresh()->visa_expiry_date->toDateString())->toBe('2026-10-21');
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', $sheet))->assertOk()->assertSee('returned by the admin: Wrong SOA number');

    // The admin entering their own row is approved immediately.
    $other = approvalSheet();
    $this->actingAs($admin)->put(route('admin.client-sheets.update', $other), [
        'last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => '2026-10-21', 'then' => 'stay', 'extensions' => [1 => $row],
    ])->assertSessionHas('success', fn (string $m) => str_contains($m, 'The visa is now valid until November 19, 2026.'));
    expect($other->fresh()->extensionAt(1)->approval_status)->toBe('approved');
});

test('cases saved before approvals existed are not held up', function () {
    $agent = approvalAgent();
    $sheet = approvalSheet();
    $case = ImmigrationPetition::create(['immigration_client_id' => $sheet->id, 'type' => 'ecc', 'details' => []]);

    expect($case->approval_status)->toBeNull()->and($case->isCleared())->toBeTrue();
    $this->actingAs($agent)->get(route('admin.client-sheets.petition-form', [$sheet, 'ecc', 'case' => $case->id]))->assertOk();
    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'submitted'])->assertSessionHas('success');
});

test('an admin opening a file from the approvals page sees a read-only preview they can approve or return', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->openPetition('ecc');

    // The approvals page links to the preview.
    $this->actingAs($admin)->get(route('admin.immigration-approvals.index'))->assertOk()
        ->assertSee('>Preview</a>', false)->assertSee('preview=1', false);

    $page = $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]))->assertOk()
        ->assertSee('Preview only')->assertSee('Nothing here can be changed from this page.')
        ->assertSee('Back to approvals')->assertSee(route('admin.immigration-approvals.approve', ['ecc', $case->id]), false)
        ->assertSee(route('admin.immigration-approvals.reject', ['ecc', $case->id]), false)
        ->assertSee('data-preview', false)->assertDontSee('Start a service')
        ->getContent();
    // The fields are disabled and the save buttons and uploads are hidden.
    expect($page)->toContain('field.disabled = true')->and($page)->toContain('form[data-preview] [data-save-controls]');
    // Every step is on one page to scroll through, with no step bar or Continue buttons.
    expect($page)->toContain('all: true')->toContain('The whole file')
        ->not->toContain('aria-label="Client sheet steps"')->not->toContain("'Continue to ' + titles[step + 1]");

    // The ordinary file stays editable.
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id]))->assertOk()
        ->assertDontSee('Preview only')->assertDontSee('data-preview', false)->assertDontSee('Start a service');
    // An agent gets the same one-page view, with no Approve / Return of their own.
    $this->actingAs($agent)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]))->assertOk()
        ->assertSee('The whole file')->assertSee('Back to applications')->assertSee('Waiting for Admin Approval')
        ->assertDontSee(route('admin.immigration-approvals.approve', ['ecc', $case->id]), false)->assertDontSee('Preview only');

    // Once approved the preview has nothing left to approve.
    $this->actingAs($admin)->post(route('admin.immigration-approvals.approve', ['ecc', $case->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]))->assertOk()
        ->assertSee('Preview only')->assertDontSee(route('admin.immigration-approvals.reject', ['ecc', $case->id]), false);
});

test('a Visa Extension an agent added previews the same way, approved from the banner', function () {
    Notification::fake();
    $admin = approvalAdmin();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    $this->actingAs($agent)->put(route('admin.client-sheets.update', $sheet), [
        'last_name' => 'Kim', 'given_name' => 'Minjun', 'visa_expiry_date' => '2026-10-21', 'then' => 'stay',
        'extensions' => [1 => ['extension_date' => '2026-10-15', 'amount_paid' => 2930, 'duration' => '29 days']],
    ]);

    $this->actingAs($admin)->get(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'preview' => 1]))->assertOk()
        ->assertSee('Preview only')->assertSee(route('admin.immigration-approvals.approve', ['extensions', $sheet->id]), false);
});

test('a service lists its active applications: an agent sees their own, with Open, the waiting state and New', function () {
    Notification::fake();
    $agent = approvalAgent();
    $other = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->petitions->first();

    $otherSheet = ImmigrationClient::create(['last_name' => 'Park', 'given_name' => 'Jisoo', 'passport_number' => 'P777777']);
    saveEcc($this, $other, $otherSheet, [], ['last_name' => 'Park', 'given_name' => 'Jisoo']);

    $page = $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSee('Active applications')->assertSee('Minjun Kim')->assertDontSee('Jisoo Park')
        ->assertSee('Waiting for Admin Approval')
        ->assertSee(e(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1])), false)
        ->assertSee(e(route('admin.client-sheets.start', ['service' => 'ecc'])), false)
        ->getContent();
    expect($page)->toContain('New ECC');

    // An admin sees every agent's applications.
    $this->actingAs(approvalAdmin())->get(route('admin.client-sheets.applications', ['service' => 'ecc']))->assertOk()
        ->assertSee('Minjun Kim')->assertSee('Jisoo Park');

    // A service nobody has started shows its empty state.
    $this->actingAs($agent)->get(route('admin.client-sheets.applications', ['service' => 'visa_downgrading']))->assertOk()
        ->assertSee('No active Visa Downgrading applications yet.');
});

test('waiting for the admin is part of the status dropdown, locked, and not a status an agent can set', function () {
    Notification::fake();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->petitions->first();

    // The dropdown reads Waiting for Admin Approval and is disabled; no separate badge is left.
    $page = $this->actingAs(approvalAdmin())->get(route('admin.immigration.cases'))->assertOk()->getContent();
    expect($page)->toContain('Waiting for Admin Approval')->not->toContain('Awaiting admin approval')
        ->and($page)->toMatch('/<select name="status"[^>]*disabled/');

    // It is not an option anyone can post.
    $this->actingAs($agent)->post(route('admin.immigration.cases.status', ['ecc', $case->id]), ['status' => 'awaiting_admin'])
        ->assertSessionHas('error');
    expect($case->fresh()->workflow_status)->toBeNull();
});

test('an agent opening a returned file can edit it; a waiting one is read-only; New starts the next one for the same client', function () {
    Notification::fake();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->petitions->first();
    $preview = route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc', 'case' => $case->id, 'preview' => 1]);

    // Waiting: read-only, no Edit; the client already has an open ECC, so no New either.
    $this->actingAs($agent)->get($preview)->assertOk()
        ->assertDontSee('>Edit</a>', false)->assertDontSee('New ECC');

    // Returned by the admin: Edit appears, with the reason beside the locked status.
    $this->actingAs(approvalAdmin())->post(route('admin.immigration-approvals.reject', ['ecc', $case->id]), ['review_note' => 'Fix the passport number']);
    $this->actingAs($agent)->get($preview)->assertOk()
        ->assertSee('Returned by Admin')->assertSee('Fix the passport number')->assertSee('>Edit</a>', false);

    // Released: the client can start a new ECC, which opens on Step 0 of the same folder.
    $case->forceFill(['released_on' => now()->toDateString(), 'approval_status' => 'approved'])->save();
    $this->actingAs($agent)->get($preview)->assertOk()
        ->assertSee(e(route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'service' => 'ecc'])), false)->assertSee('New ECC');
});

test('the ECC form and checklist go back to the one-page preview of the application', function () {
    Notification::fake();
    $agent = approvalAgent();
    $sheet = approvalSheet();
    saveEcc($this, $agent, $sheet);
    $case = $sheet->fresh()->petitions->first();
    $preview = route('admin.client-sheets.edit', ['client_sheet' => $sheet->id, 'preview' => 1, 'service' => 'ecc', 'case' => $case->id]);

    foreach (['petition-form', 'petition-checklist'] as $name) {
        $this->actingAs(approvalAdmin())->get(route('admin.client-sheets.'.$name, ['clientSheet' => $sheet, 'type' => 'ecc', 'case' => $case->id]))->assertOk()
            ->assertSee('Back to the application')->assertSee(e($preview), false)->assertDontSee('Back to the client sheet');
    }
});
