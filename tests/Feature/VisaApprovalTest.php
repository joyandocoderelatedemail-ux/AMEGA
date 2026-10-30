<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\DeskApprovalRequestedNotification;
use App\Notifications\FileStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function visaAgent(): User
{
    return User::factory()->create(['role' => 'visa_assistance']);
}

function visaBoss(): User
{
    return User::factory()->create(['role' => 'admin']);
}

/** A counter file saved through the real intake, so it goes through approval the way an agent's does. */
function savedVisaFile(object $test, User $user, array $overrides = []): VisaApplication
{
    $test->actingAs($user)->post('/visa-assistance/applications', array_merge([
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'destination_country' => 'Japan',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'service_fee' => 3500,
    ], $overrides))->assertRedirect();

    return VisaApplication::latest('id')->firstOrFail();
}

test('an agent\'s saved file waits for the admin and the admins are emailed; an admin\'s own save is approved', function () {
    Notification::fake();
    $admin = visaBoss();

    $file = savedVisaFile($this, visaAgent());
    expect($file->approval_status)->toBe('pending')->and($file->acknowledged_by)->not->toBeNull();
    Notification::assertSentTo($admin, DeskApprovalRequestedNotification::class);

    $mine = savedVisaFile($this, $admin, ['client_name' => 'Admin Made', 'client_email' => 'am@example.com']);
    expect($mine->approval_status)->toBe('approved')->and($mine->reviewed_by)->toBe($admin->id);
});

test('until approved a file cannot advance, take payment, print, show consent or email the client', function () {
    Notification::fake();
    $agent = visaAgent();
    $file = savedVisaFile($this, $agent);
    $applicant = $file->applicants()->firstOrFail();
    $show = route('visa.applications.show', $file);

    $this->actingAs($agent)->from($show)->post(route('visa.applications.advance', $file))->assertSessionHas('error');
    $this->actingAs($agent)->from($show)->post(route('visa.applications.payments', $file), ['amount' => 100])->assertSessionHas('error');
    $this->actingAs($agent)->from($show)->get(route('visa.applications.agreement', $file))->assertRedirect($show);
    $this->actingAs($agent)->from($show)->get(route('visa.applications.acknowledgement', $file))->assertRedirect($show);
    $this->actingAs($agent)->from($show)->get(route('visa.applications.checklist.print', $file))->assertRedirect($show);
    $this->actingAs($agent)->get(route('visa.applications.consent', $file))->assertRedirect($show);
    $this->actingAs($agent)->get(route('visa.worksheets.print', [$file, $applicant]))->assertRedirect($show);

    expect((float) $file->fresh()->amount_paid)->toBe(0.0)->and($file->fresh()->status)->toBe('pending');

    // Cancelling stays open, but the client hears nothing before the admin has approved.
    $this->actingAs($agent)->post(route('visa.applications.cancel', $file));
    expect($file->fresh()->status)->toBe('cancelled');
    Notification::assertNotSentTo(new AnonymousNotifiable, FileStatusChangedNotification::class);

    $this->actingAs($agent)->get($show)->assertOk()
        ->assertSee('Waiting for the admin to approve this file.')->assertDontSee('Consent Form');
});

test('after the admin approves, the same file can be paid, printed and advanced', function () {
    Notification::fake();
    $agent = visaAgent();
    $admin = visaBoss();
    $file = savedVisaFile($this, $agent);

    // The approval needs the acknowledgement ticked.
    $this->actingAs($admin)->post(route('admin.visa-approvals.approve', ['file', $file->id]))->assertSessionHasErrors('admin_acknowledged');
    expect($file->fresh()->approval_status)->toBe('pending');

    $this->actingAs($admin)->post(route('admin.visa-approvals.approve', ['file', $file->id]), ['admin_acknowledged' => 1])->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('approved')->and($file->fresh()->reviewed_by)->toBe($admin->id);

    $this->actingAs($agent)->post(route('visa.applications.payments', $file), ['amount' => 100])->assertSessionHasNoErrors();
    expect((float) $file->fresh()->amount_paid)->toBe(100.0);
    $this->actingAs($agent)->get(route('visa.applications.consent', $file))->assertOk();
    $this->actingAs($agent)->get(route('visa.applications.agreement', $file))->assertOk();
});

test('a returned file shows the reason, and the agent\'s corrected save sends it back to the admin', function () {
    Notification::fake();
    $agent = visaAgent();
    $admin = visaBoss();
    $file = savedVisaFile($this, $agent);

    $this->actingAs($admin)->post(route('admin.visa-approvals.reject', ['file', $file->id]), [])->assertSessionHasErrors('review_note');
    $this->actingAs($admin)->post(route('admin.visa-approvals.reject', ['file', $file->id]), ['review_note' => 'Wrong country']);
    expect($file->fresh()->approval_status)->toBe('returned');

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Returned by the admin: Wrong country');
    $this->actingAs($agent)->post(route('visa.applications.advance', $file))->assertSessionHas('error');

    $this->actingAs($agent)->put(route('visa.applications.update', $file), [
        'service_type' => 'visit_visa', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com', 'destination_country' => 'South Korea',
        'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ])->assertSessionHasNoErrors();

    expect($file->fresh()->approval_status)->toBe('pending')->and($file->fresh()->review_note)->toBeNull();
});

test('an approved file saved unchanged stays approved; a real change sends it back', function () {
    Notification::fake();
    $agent = visaAgent();
    $file = savedVisaFile($this, $agent);
    $file->forceFill(['approval_status' => 'approved'])->save();
    $payload = [
        'service_type' => 'visit_visa', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com', 'destination_country' => 'Japan',
        'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500,
    ];

    $this->actingAs($agent)->put(route('visa.applications.update', $file), $payload)->assertSessionHasNoErrors();
    expect($file->fresh()->approval_status)->toBe('approved');

    $this->actingAs($agent)->put(route('visa.applications.update', $file), ['client_name' => 'Jane T. Tan'] + $payload);
    expect($file->fresh()->approval_status)->toBe('pending');
});

test('the approvals page lists files by queue and only an admin can open it', function () {
    Notification::fake();
    $agent = visaAgent();
    $admin = visaBoss();
    $file = savedVisaFile($this, $agent);

    $this->actingAs($admin)->get(route('admin.visa-approvals.index'))->assertOk()
        ->assertSee('Jane Tan')->assertSee($file->reference)->assertSee('Non-Immigrant');
    $this->actingAs($admin)->get(route('admin.visa-approvals.index', ['tab' => 'approved']))->assertOk()->assertSee('Nothing here yet.');
    $this->actingAs($agent)->get(route('admin.visa-approvals.index'))->assertRedirect();
});

test('a service lists its open files: an agent sees their own, with the waiting state, Open and New', function () {
    Notification::fake();
    $agent = visaAgent();
    $other = visaAgent();
    $mine = savedVisaFile($this, $agent);
    savedVisaFile($this, $other, ['client_name' => 'Someone Else', 'client_email' => 'else@example.com']);
    $released = savedVisaFile($this, $agent, ['client_name' => 'Already Released', 'client_email' => 'rel@example.com']);
    $released->forceFill(['status' => 'released'])->save();
    savedVisaFile($this, $agent, ['service_type' => 'e_visa', 'client_name' => 'Evisa Person', 'client_email' => 'ev@example.com', 'applicant_type' => 'individual', 'destination_country' => null, 'purpose' => null, 'processing_speed' => null]);

    $page = $this->actingAs($agent)->get(route('visa.services', 'visit_visa'))->assertOk()
        ->assertSee('Active files')->assertSee('Jane Tan')->assertSee('Waiting for Admin Approval')
        ->assertDontSee('Someone Else')->assertDontSee('Already Released')->assertDontSee('Evisa Person')
        ->assertSee(e(route('visa.applications.show', [$mine, 'preview' => 1])), false)
        ->assertSee(e(route('visa.visit.new')), false)
        ->getContent();
    expect($page)->toContain('New Non-Immigrant');

    // E-Visa starts on its own form.
    $this->actingAs($agent)->get(route('visa.services', 'e_visa'))->assertOk()
        ->assertSee('Evisa Person')->assertSee(e(route('visa.applications.create', ['service_type' => 'e_visa'])), false);

    // An admin sees every agent's files.
    $this->actingAs(visaBoss())->get(route('visa.services', 'visit_visa'))->assertOk()->assertSee('Jane Tan')->assertSee('Someone Else');

    $this->actingAs($agent)->get(route('visa.services', 'passporting'))->assertOk()->assertSee('No active Passporting files yet.');
});

test('the preview shows the whole file on one page, read-only; the admin approves from it, the agent can edit only when allowed', function () {
    Notification::fake();
    $agent = visaAgent();
    $admin = visaBoss();
    $file = savedVisaFile($this, $agent);
    $preview = route('visa.applications.show', [$file, 'preview' => 1]);

    // Waiting: the agent sees the whole file and the locked status, with no Edit.
    $this->actingAs($agent)->get($preview)->assertOk()
        ->assertSee('Waiting for Admin Approval')->assertSee('Back to files')->assertSee('New Non-Immigrant')
        ->assertSee('File Details')->assertSee('Fees')->assertSee('Applicants')->assertSee('Documents')->assertSee('Progress')
        ->assertDontSee('>Edit</a>', false);

    // The admin gets Approve and Return for it.
    $this->actingAs($admin)->get($preview)->assertOk()
        ->assertSee(route('admin.visa-approvals.approve', ['file', $file->id]), false)
        ->assertSee(route('admin.visa-approvals.reject', ['file', $file->id]), false)
        ->assertSee('Back to approvals');

    // Returned: Edit appears for the agent, with the admin's reason.
    $this->actingAs($admin)->post(route('admin.visa-approvals.reject', ['file', $file->id]), ['review_note' => 'Fix the fee']);
    $this->actingAs($agent)->get($preview)->assertOk()
        ->assertSee('Returned by Admin')->assertSee('Fix the fee')->assertSee('>Edit</a>', false);

    // The ordinary file page is still the working page.
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('Back to files')->assertSee('Returned by the admin: Fix the fee');
});

test('there is no File Directory or Services tab, or New Counter File; files sit under the dashboard cards', function () {
    $html = $this->actingAs(visaAgent())->get(route('visa.dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('<span>File Directory</span>')->not->toContain('New Transaction')
        ->and($html)->not->toContain('<span>Services</span>')
        ->and($html)->toContain('href="'.route('visa.services', 'e_visa').'"');
});
