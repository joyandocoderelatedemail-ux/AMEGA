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
    ], $overrides))->assertRedirect();

    return SrrvApplication::latest('id')->firstOrFail();
}

function savedRenewal(object $test, User $user, array $overrides = []): SrrvRenewal
{
    $test->actingAs($user)->post('/srrv/renewals', array_merge([
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'retiree_email' => 'ramon@example.com', 'years_paid' => 1,
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
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertRedirect(route('srrv.applications.show', $file));
    $this->actingAs($officer)->get(route('srrv.applications.consent', $file))->assertRedirect(route('srrv.applications.show', $file));
    $this->actingAs($officer)->post(route('srrv.applications.cancel', $file));

    expect($file->fresh()->status)->toBe('cancelled')->and((float) $file->fresh()->amount_paid)->toBe(0.0);
    // Cancelling is allowed, but the client hears nothing before the admin has approved.
    Notification::assertNotSentTo(new AnonymousNotifiable, FileStatusChangedNotification::class);

    // The page says so and hides the print and consent buttons.
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('Waiting for the admin to approve this file.')->assertDontSee('Consent Form')
        ->assertDontSee(route('srrv.applications.forms', [$file, 'application']), false);
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

    $this->actingAs($officer)->post(route('srrv.applications.payments', $file), ['amount' => 100])->assertSessionHasNoErrors();
    expect((float) $file->fresh()->amount_paid)->toBe(100.0);
    $this->actingAs($officer)->get(route('srrv.applications.forms', [$file, 'application']))->assertOk();
    $this->actingAs($officer)->get(route('srrv.applications.consent', $file))->assertOk();
});

test('a returned file shows the reason, and the agent\'s corrected save sends it back to the admin', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $file = savedRetireeFile($this, $officer);

    $this->actingAs($admin)->post(route('admin.srrv-approvals.reject', ['application', $file->id]), [])->assertSessionHasErrors('review_note');
    $this->actingAs($admin)->post(route('admin.srrv-approvals.reject', ['application', $file->id]), ['review_note' => 'Wrong birth date']);
    expect($file->fresh()->approval_status)->toBe('returned');

    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertSee('Returned by the admin: Wrong birth date');
    $this->actingAs($officer)->post(route('srrv.applications.advance', $file))->assertSessionHas('error');

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'retiree_email' => 'ramon@example.com',
        'date_of_birth' => now()->subYears(61)->format('Y-m-d'), 'nationality' => 'Filipino', 'service_fee' => 1000,
    ])->assertSessionHasNoErrors();

    expect($file->fresh()->approval_status)->toBe('pending')->and($file->fresh()->review_note)->toBeNull();
});

test('an untouched approved file stays approved when the agent saves it unchanged', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $file = savedRetireeFile($this, $officer);
    $file->forceFill(['approval_status' => 'approved'])->save();
    $payload = $file->only(['service_type', 'visa_class', 'retiree_name', 'retiree_email', 'nationality']) + [
        'date_of_birth' => $file->date_of_birth->format('Y-m-d'), 'service_fee' => 1000,
    ];

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), $payload)->assertSessionHasNoErrors();
    expect($file->fresh()->approval_status)->toBe('approved');

    $this->actingAs($officer)->put(route('srrv.applications.update', $file), ['retiree_name' => 'Ramon D. Cruz'] + $payload);
    expect($file->fresh()->approval_status)->toBe('pending');
});

test('renewals follow the same approval: held until approved, then collectable', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $admin = approvalBoss();
    $renewal = savedRenewal($this, $officer);

    $this->actingAs($officer)->post(route('srrv.renewals.advance', $renewal))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.renewals.payments', $renewal), ['amount' => 10])->assertSessionHas('error');
    $this->actingAs($officer)->get(route('srrv.renewals.request-form', $renewal))->assertRedirect(route('srrv.renewals.show', $renewal));
    $this->actingAs($officer)->get(route('srrv.renewals.consent', $renewal))->assertRedirect(route('srrv.renewals.show', $renewal));

    $this->actingAs($admin)->post(route('admin.srrv-approvals.approve', ['renewal', $renewal->id]), ['admin_acknowledged' => 1]);
    $this->actingAs($officer)->post(route('srrv.renewals.payments', $renewal), ['amount' => 10])->assertSessionHasNoErrors();
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

test('a service lists its active files: an officer sees their own, with the waiting state, Open and New', function () {
    Notification::fake();
    $officer = approvalOfficer();
    $other = approvalOfficer();
    $mine = savedRetireeFile($this, $officer);
    savedRetireeFile($this, $other, ['retiree_name' => 'Someone Else', 'retiree_email' => 'else@example.com']);
    $released = savedRetireeFile($this, $officer, ['retiree_name' => 'Already Released', 'retiree_email' => 'rel@example.com']);
    $released->forceFill(['status' => 'released'])->save();

    $page = $this->actingAs($officer)->get(route('srrv.services', 'application'))->assertOk()
        ->assertSee('Active files')->assertSee('Ramon Dela Cruz')->assertSee('Waiting for Admin Approval')
        ->assertDontSee('Someone Else')->assertDontSee('Already Released')
        ->assertSee(e(route('srrv.applications.edit', [$mine, 'preview' => 1])), false)
        ->assertSee(e(route('srrv.start', 'application')), false)
        ->getContent();
    expect($page)->toContain('New SRRV Application');

    $this->actingAs(approvalBoss())->get(route('srrv.services', 'application'))->assertOk()
        ->assertSee('Ramon Dela Cruz')->assertSee('Someone Else');

    $this->actingAs($officer)->get(route('srrv.services', 'renewal'))->assertOk()->assertSee('No active SRRV Renewal files yet.');
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
    expect($page)->toContain('all: true')->toContain('field.disabled = true')
        ->not->toContain('Continue to')->not->toContain('Step 1 -');

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
        ->assertSee('Waiting for Admin Approval')->assertSee('New SRRV '.ucfirst($renewal->visa_class).' Renewal')->assertSee('all: true', false);

    // Open file, then back: to the file's service list.
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('href="'.route('srrv.services', 'classic').'"', false)->assertSee('SRRV Classic');
});

test('there are no Retiree Files or Annual Renewals tabs; open files sit under Services', function () {
    $officer = approvalOfficer();
    $html = $this->actingAs($officer)->get(route('srrv.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('<span>Retiree Files</span>')->not->toContain('<span>Annual Renewals</span>')
        ->and($html)->toContain('<span>Services</span>');
});
