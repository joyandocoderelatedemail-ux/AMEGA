<?php

use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\User;
use App\Notifications\DeskApprovalRequestedNotification;
use App\Notifications\FileStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function approvalOfficer(): User
{
    return User::factory()->create(['role' => 'srrv']);
}

function approvalBoss(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** A retiree file saved through the real intake, so it goes through approval the way an agent's does. */
function savedRetireeFile(object $test, User $user, array $overrides = []): SrrvApplication
{
    $test->actingAs($user)->post('/srrv/applications', array_merge([
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'nationality' => 'Filipino',
        'service_fee' => 1000,
        'agreement_signed' => 1,
    ], $overrides))->assertRedirect();

    // A new application opens on Step 2; sending it to the admin is what puts it in the approval queue.
    $file = SrrvApplication::latest('id')->firstOrFail();
    $test->actingAs($user)->post(route('srrv.applications.send-to-admin', $file));

    return $file->fresh();
}

function savedRenewal(object $test, User $user, array $overrides = []): SrrvRenewal
{
    $test->actingAs($user)->post('/srrv/renewals', array_merge([
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'retiree_email' => 'ramon@example.com', 'srrv_card_number' => 'M-0070001', 'agreement_signed' => 1, 'years_paid' => 1,
    ], $overrides))->assertRedirect();

    return SrrvRenewal::latest('id')->firstOrFail();
}

test('an officer\'s saved file waits for the admin and the admins are emailed; an admin\'s own save is approved', function () {
    Notification::fake();
    $admin = approvalBoss();

    $file = savedRetireeFile($this, approvalOfficer());
    expect($file->approval_status)->toBe('pending')->and($file->acknowledged_by)->not->toBeNull();
    Notification::assertSentTo($admin, DeskApprovalRequestedNotification::class);

    $mine = savedRetireeFile($this, $admin, ['retiree_name' => 'Admin Made', 'retiree_email' => 'am@example.com']);
    expect($mine->approval_status)->toBe('approved')->and($mine->reviewed_by)->toBe($admin->id);

    $renewal = savedRenewal($this, approvalOfficer());
    expect($renewal->approval_status)->toBe('pending');
});

test('until approved a file cannot advance, take payment, print, show consent or email the client', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $file = savedRetireeFile($this, $officer);

    $this->actingAs($officer)->post(route('srrv.applications.advance', $file))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.payments', $file), ['amount' => 100])->assertSessionHas('error');
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertRedirect(route('srrv.applications.edit', [$file, 'step' => 6]));
    $this->actingAs($officer)->get(route('srrv.applications.consent', $file))->assertRedirect(route('srrv.applications.edit', [$file, 'step' => 6]));
    $this->actingAs($officer)->post(route('srrv.applications.cancel', $file));

    expect($file->fresh()->status)->toBe('cancelled')->and((float) $file->fresh()->amount_paid)->toBe(0.0);
    // Cancelling is allowed, but the client hears nothing before the admin has approved.
    Notification::assertNotSentTo(new AnonymousNotifiable, FileStatusChangedNotification::class);

    // The old file page is gone: its address opens the wizard.
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertRedirect(route('srrv.applications.edit', $file));
});

test('after the admin approves, the same file can advance, be paid and printed', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);

    // The approval needs the acknowledgement ticked.
    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['application', $file->id]))->assertSessionHasErrors('admin_acknowledged');
    expect($file->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['application', $file->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('approved')->and($file->fresh()->reviewed_by)->toBe($admin->id);

    // The cashier takes the payment on a new application.
    $cashier = User::factory()->create(['role' => 'cashier']);
    $this->actingAs($cashier)->post(route('cashier.srrv.store', $file->id), ['amount' => 100, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
    expect((float) $file->fresh()->amount_paid)->toBe(100.0);
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertOk();
    $this->actingAs($officer)->get(route('srrv.applications.consent', $file))->assertOk();
});

test('a returned file shows the reason, and the agent\'s corrected save sends it back to the admin', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);

    // Returned from the file's preview: a reason is needed, and the admin lands back on the preview.
    $preview = route('srrv.applications.edit', [$file, 'preview' => 1]);
    $this->actingAs($admin)->from($preview)->post(route('admin.srrv-approvals.reject', ['application', $file->id]), [])->assertSessionHasErrors('review_note');
    $this->actingAs($admin)->from($preview)->post(route('admin.srrv-approvals.reject', ['application', $file->id]), ['review_note' => 'Wrong birth date'])
        ->assertRedirect($preview)->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('returned');
    $this->actingAs($admin)->get(route('admin.srrv-approvals.index', ['tab' => 'returned']))->assertOk()
        ->assertSee($file->reference)->assertSee(e($preview), false);

    // The agent finds it on the service list, the dashboard, the preview and the wizard's Step 6: never on the old file page.
    $oldPage = 'href="'.route('srrv.applications.show', $file).'"';
    $wizard = route('srrv.applications.edit', [$file, 'step' => 6]);
    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()
        ->assertSee('Returned by Admin')->assertSee('href="'.$wizard.'"', false)->assertDontSee($oldPage, false);
    $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->assertSee('Returned by Admin')->assertDontSee($oldPage, false);
    $this->actingAs($officer)->get($preview)->assertOk()->assertSee('Wrong birth date')->assertDontSee($oldPage, false);
    $this->actingAs($officer)->get($wizard)->assertOk()
        ->assertSee('The admin returned this file: Wrong birth date')->assertSee('Send to admin again')->assertDontSee($oldPage, false);
    $this->actingAs($officer)->post(route('srrv.applications.advance', $file))->assertSessionHas('error');

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'retiree_email' => 'ramon@example.com',
        'date_of_birth' => now()->subYears(61)->format('Y-m-d'), 'nationality' => 'Filipino', 'service_fee' => 1000,
    ])->assertSessionHasNoErrors();

    expect($file->fresh()->approval_status)->toBe('pending')->and($file->fresh()->review_note)->toBeNull();
});

test('an approved file stays approved whenever the agent saves it, and the admins are not asked again', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);
    $file->forceFill(['approval_status' => 'approved'])->save();
    Notification::fake(); // only what the saves below send
    $payload = $file->only(['service_type', 'visa_class', 'retiree_name', 'retiree_email', 'nationality']) + [
        'date_of_birth' => $file->date_of_birth->format('Y-m-d'), 'service_fee' => 1000,
    ];

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), $payload)->assertSessionHasNoErrors();
    expect($file->fresh()->approval_status)->toBe('approved');

    // A changed detail neither sends it back nor changes it: the approved details are locked (Request edit unlocks them).
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), ['retiree_name' => 'Ramon D. Cruz'] + $payload);
    expect($file->fresh()->approval_status)->toBe('approved')->and($file->fresh()->retiree_name)->toBe('Ramon Dela Cruz');
    Notification::assertNotSentTo($admin, DeskApprovalRequestedNotification::class);
});

test('renewals follow the same approval: held until approved, then collectable', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $renewal = savedRenewal($this, $officer);

    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $pay = fn () => $this->actingAs(cashier())->post(route('cashier.srrv.renewals.store', $renewal->id), ['amount' => 10, 'apply_to' => 'fee', 'method' => 'cash', 'cashier_acknowledged' => 1]);
    $pay()->assertSessionHas('error', fn ($message) => str_contains($message, 'waiting for admin approval'));
    $this->actingAs($officer)->get(route('srrv.renewals.request-form', $renewal))->assertRedirect(route('srrv.renewals.show', $renewal));
    $this->actingAs($officer)->get(route('srrv.renewals.consent', $renewal))->assertRedirect(route('srrv.renewals.show', $renewal));

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['renewal', $renewal->id]), ['admin_acknowledged' => 1]);
    $pay()->assertSessionHasNoErrors();
    expect((float) $renewal->fresh()->fee_paid)->toBe(10.0);
    $this->actingAs($officer)->get(route('srrv.renewals.request-form', $renewal))->assertOk();
});

test('the approvals page lists files by queue and only an admin can open it', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);

    $this->actingAs($admin)->get(route('admin.srrv-approvals.index'))->assertOk()
        ->assertSee('Ramon Dela Cruz')->assertSee($file->reference)->assertSee('SRRV Application');
    $this->actingAs($admin)->get(route('admin.srrv-approvals.index', ['tab' => 'approved']))->assertOk()->assertSee('Nothing here yet.');
    $this->actingAs($officer)->get(route('admin.srrv-approvals.index'))->assertRedirect();
});

test('a service lists its files: an officer sees their own, with the waiting state, Open and New', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $other = approvalOfficer();
    $mine = savedRetireeFile($this, $officer);
    savedRetireeFile($this, $other, ['retiree_name' => 'Someone Else', 'retiree_email' => 'else@example.com']);
    $released = savedRetireeFile($this, $officer, ['retiree_name' => 'Already Released', 'retiree_email' => 'rel@example.com']);
    $released->forceFill(['status' => 'released'])->save();

    $page = $this->actingAs($officer)->get(route('srrv.services', 'application'))->assertOk()
        ->assertSee('Needs action')->assertSee('Ramon Dela Cruz')->assertSee('Waiting for Admin Approval')
        // A released file is still listed, in its own closed section after the rest.
        ->assertDontSee('Someone Else')->assertSee('Already Released')->assertSee('Released')
        ->assertSee(e(route('srrv.applications.edit', [$mine, 'preview' => 1])), false)
        ->assertSee(e(route('srrv.start', 'application')), false)
        ->getContent();
    expect($page)->toContain('New SRRV Application');

    $this->actingAs(approvalBoss())->get(route('srrv.services', 'application'))->assertOk()
        ->assertSee('Ramon Dela Cruz')->assertSee('Someone Else');

    $this->actingAs($officer)->get(route('srrv.services', 'renewal'))->assertOk()->assertSee('No SRRV Renewal transactions yet.');
});

test('the preview shows the whole file on one page, read-only; the admin approves from it, the agent can edit only when allowed', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);
    $preview = route('srrv.applications.edit', [$file, 'preview' => 1]);

    // Waiting: the agent sees the file and the locked status, with no Edit.
    $page = $this->actingAs($officer)->get($preview)->assertOk()
        ->assertSee('Waiting for Admin Approval')->assertSee('Back to files')->assertSee('New SRRV Classic')
        // Back and New stay in the file's own service, not the old all-files list.
        ->assertSee('href="'.route('srrv.services', 'classic').'"', false)
        ->assertDontSee('>Edit</a>', false)->assertDontSee('Save Changes')
        ->getContent();
    // Laid out like the Visa Assistance preview: the file's details and payment, its documents and the PRA form details, all disabled.
    expect($page)->toContain('Retiree Information')->toContain('PRA Form Details')->toContain('f.disabled = true')
        ->not->toContain('Continue to')->not->toContain('Step 1 -')->not->toContain('Open file');

    // The admin gets Approve and Return for it.
    $this->actingAs($admin)->get($preview)->assertOk()
        ->assertSee(route('admin.srrv-approvals.approve', ['application', $file->id]), false)
        ->assertSee(route('admin.srrv-approvals.reject', ['application', $file->id]), false)
        ->assertSee('Back to approvals');

    // Returned: Edit appears for the agent, with the admin's reason.
    $this->actingAs($admin)->post(route('admin.srrv-approvals.reject', ['application', $file->id]), ['review_note' => 'Fix the fee']);
    $this->actingAs($officer)->get($preview)->assertOk()
        ->assertSee('Returned by Admin')->assertSee('Fix the fee')->assertSee('>Edit</a>', false);

    // The ordinary edit page is still the step-by-step form.
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertOk()
        ->assertDontSee('all: true')->assertSee('Continue to');

    // A renewal previews the same way.
    $renewal = savedRenewal($this, $officer);
    $this->actingAs($officer)->get(route('srrv.renewals.edit', [$renewal, 'preview' => 1]))->assertOk()
        ->assertSee('Waiting for Admin Approval')->assertSee('New '.ucfirst($renewal->visa_class).' ID Renewal')->assertSee('all: true', false);

    // Back from the preview: to the file's service list.
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'preview' => 1]))->assertOk()
        ->assertSee('href="'.route('srrv.services', 'classic').'"', false)->assertSee('SRRV Classic');
});

test('there are no Retiree Files, Annual Renewals or Services tabs; open files sit under the dashboard cards', function () {
    $officer = approvalOfficer();
    $html = $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('<span>Retiree Files</span>')->not->toContain('<span>Annual Renewals</span>')
        ->and($html)->not->toContain('<span>Services</span>')
        ->and($html)->toContain('href="'.route('srrv.services', 'classic').'"');
});

test('once approved, the details the admin approved are locked for the agent; documents, ticks and remarks stay open', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    approvalBoss();
    $file = savedRetireeFile($this, $officer, ['form' => ['passport_no' => 'P111']]);
    $file->forceFill(['approval_status' => 'approved', 'investment_amount' => 30000])->save();

    // The wizard shows them read-only, with Request edit; the preview still offers Edit for the rest.
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'step' => 2]))->assertOk()
        ->assertSee('these details are locked')->assertSee('Request edit')->assertSee('<fieldset disabled', false);
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'preview' => 1]))->assertOk()->assertSee('Press Edit to change it');

    // A save keeps them as approved, whatever is sent; the open parts are saved.
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'courtesy', 'courtesy_type' => 'former_filipino', 'retiree_name' => 'Someone Else',
        'investment_amount' => 1, 'investment_currency' => 'PHP', 'form' => ['passport_no' => 'CHANGED'],
        'remarks' => 'Seen at the counter', 'requirements' => ['passport'],
    ])->assertSessionHasNoErrors();
    $file->refresh();
    expect($file->retiree_name)->toBe('Ramon Dela Cruz')->and($file->visa_class)->toBe('classic')
        ->and((float) $file->investment_amount)->toBe(30000.0)->and($file->investment_currency)->toBe('USD')->and($file->detail('passport_no'))->toBe('P111')
        ->and($file->remarks)->toBe('Seen at the counter')->and($file->ticked())->toContain('passport')
        ->and($file->approval_status)->toBe('approved');

    // The admin is not held to it.
    $this->actingAs($admin)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon D. Cruz', 'service_fee' => 1000,
    ])->assertSessionHasNoErrors();
    expect($file->fresh()->retiree_name)->toBe('Ramon D. Cruz');
});

test('Request edit on approved details: the admin\'s OK unlocks them until a detail is changed', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    approvalBoss();
    $file = savedRetireeFile($this, $officer);
    $file->forceFill(['approval_status' => 'approved'])->save();

    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), ['reason' => 'The name was typed wrong'])->assertSessionHasNoErrors();
    expect($file->fresh()->hasEditRequest())->toBeTrue();
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertSee('Edit requested: waiting for the admin');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.edit-approve', ['application', $file->id]))->assertSessionHas('success');
    expect($file->fresh()->detailsLocked())->toBeFalse();
    $this->actingAs($officer)->get(route('srrv.applications.edit', $file))->assertSee('The admin unlocked the approved details')->assertDontSee('<fieldset disabled', false);

    // The correction is saved, still approved, and the details lock again.
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon D. Cruz', 'retiree_email' => 'ramon@example.com',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'), 'nationality' => 'Filipino', 'service_fee' => 1000,
    ])->assertSessionHasNoErrors();
    $file->refresh();
    expect($file->retiree_name)->toBe('Ramon D. Cruz')->and($file->approval_status)->toBe('approved')->and($file->detailsLocked())->toBeTrue();
});

test('the preview cancels a file (anyone at the desk) or deletes it (admins only), each after a confirm', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    approvalBoss();
    $file = savedRetireeFile($this, $officer);
    $preview = fn (SrrvApplication $f) => route('srrv.applications.edit', [$f, 'preview' => 1]);

    $this->actingAs($officer)->get($preview($file))->assertOk()
        ->assertSee('Cancel file')->assertSee('onsubmit="return confirm(', false)->assertDontSee('>Delete</button>', false);
    $this->actingAs($admin)->get($preview($file))->assertOk()->assertSee('Cancel file')->assertSee('>Delete</button>', false);

    // Cancel: back on the list, which no longer shows it.
    $this->actingAs($officer)->post(route('srrv.applications.cancel', $file))->assertRedirect(route('srrv.services', 'classic'))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('cancelled');
    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()->assertSee("File {$file->reference} cancelled.");
    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()->assertDontSee($file->reference);
    $this->actingAs($officer)->get($preview($file))->assertOk()->assertDontSee('Cancel file');

    // A released file is not cancelled.
    $released = savedRetireeFile($this, $officer, ['retiree_name' => 'Ana Cruz', 'retiree_email' => 'ana@example.com']);
    $released->forceFill(['status' => 'released', 'released_on' => now()->toDateString(), 'approval_status' => 'approved'])->save();
    $this->actingAs($officer)->post(route('srrv.applications.cancel', $released))->assertSessionHas('error');
    expect($released->fresh()->status)->toBe('released');

    // Delete: never by an agent; by an admin, for good, but not once the cashier has taken a payment.
    $paid = savedRetireeFile($this, $officer, ['retiree_name' => 'Ben Cruz', 'retiree_email' => 'ben@example.com']);
    $this->actingAs($officer)->delete(route('srrv.applications.destroy', $paid));
    expect(SrrvApplication::withoutGlobalScopes()->find($paid->id))->not->toBeNull();
    $paid->forceFill(['approval_status' => 'approved'])->save();
    $this->actingAs(User::factory()->create(['role' => 'cashier']))->post(route('cashier.srrv.store', $paid->id), ['amount' => 100, 'method' => 'cash', 'cashier_acknowledged' => 1]);
    $this->actingAs($admin)->delete(route('srrv.applications.destroy', $paid))->assertSessionHas('error', fn ($message) => str_contains($message, 'Cancel it instead'));
    expect(SrrvApplication::withoutGlobalScopes()->find($paid->id))->not->toBeNull();

    $this->actingAs($admin)->delete(route('srrv.applications.destroy', $released))->assertRedirect(route('srrv.services', 'classic'));
    expect(SrrvApplication::withoutGlobalScopes()->find($released->id))->toBeNull();
});
