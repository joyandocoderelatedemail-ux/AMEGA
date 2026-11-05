<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\ClientCaseNotification;
use App\Notifications\VisaEditRequestNotification;
use App\Support\AdminNotifications;
use Illuminate\Support\Facades\Notification;

/**
 * The visa counter works like the Immigration counter: an agent sends the file for the admin's approval (and cannot
 * send it again, or print it, until it is approved), the file shows blank forms and lists the filled ones once
 * approved, the Notice box emails the client at any stage, and a released file is locked (Request edit / Reopen).
 */
function vliAgent(): User
{
    return User::factory()->create(['role' => 'visa_assistance', 'name' => 'Vina Agent']);
}

function vliAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
}

function vliFile(User $agent, array $file = []): VisaApplication
{
    return VisaApplication::withoutGlobalScopes()->forceCreate($file + [
        'reference' => 'VA-'.fake()->unique()->numerify('#####'), 'created_by' => $agent->id, 'service_type' => 'visit_visa',
        'status' => 'requirements', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com',
        'destination_country' => 'Schengen (Europe)', 'approval_status' => 'approved', 'acknowledged_by' => $agent->id,
    ]);
}

function vliSave(VisaApplication $file): array
{
    return ['client_name' => 'Jane Changed', 'client_email' => 'jane@example.com', 'destination_country' => 'Japan', 'purpose' => 'tourist', 'processing_speed' => 'regular', 'service_fee' => 3500];
}

test('an agent sends the file for admin approval; an admin saves it directly', function () {
    $this->actingAs(vliAgent())->get(route('visa.visit.file'))->assertOk()
        ->assertSee('Send to Admin')->assertDontSee('Open the file');
    // No "Open the file" for an admin either: every draft form is sent to the admin.
    $this->actingAs(vliAdmin())->get(route('visa.visit.file'))->assertOk()
        ->assertSee('Send to Admin')->assertDontSee('Open the file');
});

test('a file already sent cannot be sent again by the agent until the admin decides', function () {
    $agent = vliAgent();
    $file = vliFile($agent, ['approval_status' => 'pending']);

    $this->actingAs($agent)->get(route('visa.applications.edit', $file))->assertOk()
        ->assertSee('Sent, waiting for admin approval')->assertDontSee('Send for admin approval');
    $this->actingAs($agent)->put(route('visa.applications.update', $file), vliSave($file))
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'already sent'));
    expect($file->fresh()->client_name)->toBe('Jane Tan');

    // Returned: the agent corrects it and sends it again.
    $file->forceFill(['approval_status' => 'returned', 'review_note' => 'Fix'])->save();
    $this->actingAs($agent)->get(route('visa.applications.edit', $file))->assertSee('Send for admin approval');
});

test('only an admin prints a file the admin has not approved', function () {
    $agent = vliAgent();
    $file = vliFile($agent, ['approval_status' => 'pending']);

    $this->actingAs($agent)->get(route('visa.applications.agreement', $file))->assertRedirect();
    $this->actingAs($agent)->get(route('visa.applications.consent', $file))->assertRedirect();
    $this->actingAs(vliAdmin())->get(route('visa.applications.agreement', $file))->assertOk();
    $this->actingAs(vliAdmin())->get(route('visa.applications.consent', $file))->assertOk();
});

test('the file lists only the blank forms', function () {
    $agent = vliAgent();
    $file = vliFile($agent, ['approval_status' => 'pending']);
    $show = route('visa.applications.show', $file);

    $this->actingAs($agent)->get($show)->assertOk()
        ->assertSee(route('visa.consent-blank'), false)->assertSee(route('visa.agreement-blank'), false)
        ->assertSee(route('visa.worksheets.blank', 'schengen'), false)
        ->assertDontSee(route('visa.applications.agreement', $file), false);

    $file->forceFill(['approval_status' => 'approved'])->save();
    $this->actingAs($agent)->get($show)->assertOk()
        ->assertDontSee(route('visa.applications.agreement', $file), false)->assertDontSee(route('visa.applications.consent', $file), false);
});

test('the blank agreement and consent carry no client data', function () {
    $agent = vliAgent();
    vliFile($agent);

    $this->actingAs($agent)->get(route('visa.agreement-blank'))->assertOk()
        ->assertSee('Blank agreement, to fill in by hand.')->assertDontSee('Jane Tan')->assertDontSee('PhP 0.00');
    $this->actingAs($agent)->get(route('visa.consent-blank'))->assertOk()->assertDontSee('Jane Tan');
});

test('the Notice box offers the template for the stage and Notify client emails the agent message at once, at any stage', function () {
    Notification::fake();
    $agent = vliAgent();
    $file = vliFile($agent, ['approval_status' => 'pending']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('<span>Notice</span>', false)->assertSee('Good day, Jane Tan!')->assertSee('Notify client')->assertSee('Sent now to');

    $this->actingAs($agent)->post(route('visa.applications.notify', $file), ['subject' => 'Your visa', 'message' => 'We got your papers.'])
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'was notified'));
    Notification::assertSentOnDemand(ClientCaseNotification::class, function ($n, $channels, $notifiable) use ($file) {
        $html = (string) $n->toMail($notifiable)->render();

        return $n->body === 'We got your papers.' && $n->file['reference'] === $file->reference
            && str_contains($html, 'Visa Assistance') && ! str_contains($html, 'Immigration Services');
    });

    $this->actingAs($agent)->post(route('visa.applications.notify', $file), ['subject' => '', 'message' => ''])->assertSessionHasErrors(['subject', 'message']);
    $noEmail = vliFile($agent, ['client_email' => null]);
    $this->actingAs($agent)->post(route('visa.applications.notify', $noEmail), ['subject' => 'x', 'message' => 'y'])->assertSessionHas('error');
});

test('a released file is locked: nothing changes, but payments still go through', function () {
    $agent = vliAgent();
    $file = vliFile($agent, ['status' => 'released']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('This file is released and locked')->assertSee('Request edit')->assertDontSee(route('visa.applications.edit', $file), false);
    $this->actingAs($agent)->put(route('visa.applications.update', $file), vliSave($file))->assertSessionHas('error', fn (string $m) => str_contains($m, 'released and locked'));
    $this->actingAs($agent)->post(route('visa.applications.requests.store', $file), ['label' => 'More papers'])->assertSessionHas('error');
    $this->actingAs($agent)->post(route('visa.applicants.store-client', $file))->assertSessionHas('error');
    expect($file->fresh()->client_name)->toBe('Jane Tan');
});

test('an agent requests an edit on a released file; the admin approves it and the file reopens', function () {
    Notification::fake();
    $agent = vliAgent();
    $admin = vliAdmin();
    $file = vliFile($agent, ['status' => 'released']);

    $this->actingAs($agent)->post(route('visa.applications.request-edit', $file), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($agent)->post(route('visa.applications.request-edit', $file), ['reason' => 'Wrong passport number'])->assertSessionHas('success');
    expect($file->fresh()->hasEditRequest())->toBeTrue();
    Notification::assertSentTo($admin, VisaEditRequestNotification::class, fn ($n) => $n->state === 'requested');

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Edit requested: waiting for the admin');
    $this->actingAs($admin)->get(route('admin.visa-approvals.index'))->assertOk()
        ->assertSee('Edit request')->assertSee('Wrong passport number')->assertSee('Approve reopening');

    $this->actingAs($admin)->post(route('admin.visa-approvals.edit-approve', ['file', $file->id]))->assertSessionHas('success');
    $fresh = $file->fresh();
    expect($fresh->status)->toBe('result')->and($fresh->hasEditRequest())->toBeFalse()->and($fresh->isReleasedLocked())->toBeFalse();
    Notification::assertSentTo($agent, VisaEditRequestNotification::class, fn ($n) => $n->state === 'approved');
});

test('declining keeps the file locked and tells the agent why', function () {
    Notification::fake();
    $agent = vliAgent();
    $admin = vliAdmin();
    $file = vliFile($agent, ['status' => 'released']);
    $file->requestEdit($agent, 'Fix it');

    $this->actingAs($admin)->post(route('admin.visa-approvals.edit-decline', ['file', $file->id]), ['note' => ''])->assertSessionHasErrors('note');
    $this->actingAs($admin)->post(route('admin.visa-approvals.edit-decline', ['file', $file->id]), ['note' => 'Already sent to the embassy'])->assertSessionHas('success');

    expect($file->fresh()->status)->toBe('released')->and($file->fresh()->editRequestDeclined())->toBeTrue();
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Edit declined: the file stays locked')->assertSee('Already sent to the embassy');
    Notification::assertSentTo($agent, VisaEditRequestNotification::class, fn ($n) => $n->state === 'declined');
});

test('an approver reopens a released file directly; an agent cannot', function () {
    $agent = vliAgent();
    $file = vliFile($agent, ['status' => 'released', 'service_type' => 'passporting']);

    $this->actingAs($agent)->post(route('visa.applications.reopen', $file))->assertSessionHas('error');
    $this->actingAs(vliAdmin())->get(route('visa.applications.show', $file))->assertSee('Reopen');
    $this->actingAs(vliAdmin())->post(route('visa.applications.reopen', $file))->assertSessionHas('success');

    expect($file->fresh()->status)->toBe('result');
});

test('an edit request counts in the admin approvals badge', function () {
    $agent = vliAgent();
    $admin = vliAdmin();
    vliFile($agent, ['status' => 'released'])->requestEdit($agent, 'Fix');

    expect(AdminNotifications::for($admin)['visa_approvals'])->toBe(1);
});
