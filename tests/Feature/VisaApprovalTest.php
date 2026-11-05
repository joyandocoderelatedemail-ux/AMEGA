<?php

use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\ClientCaseNotification;
use App\Notifications\DeskApprovalRequestedNotification;
use App\Notifications\FileStatusChangedNotification;
use App\Support\DocumentStorage;
use App\Support\VisaWorksheets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
    $payload = array_merge([
        'service_type' => 'visit_visa',
        'client_name' => 'Jane Tan',
        'client_email' => 'jane@example.com',
        'destination_country' => 'Japan',
        'purpose' => 'tourist',
        'processing_speed' => 'regular',
        'service_fee' => 3500,
    ], $overrides);

    // A destination with an application form needs its draft form filled in before the file can be sent.
    $worksheet = VisaWorksheets::resolve($payload['destination_country'] ?? null, $payload['checklist_key'] ?? null, $payload['purpose'] ?? null);
    if ($worksheet && ($payload['service_type'] ?? 'visit_visa') === 'visit_visa') {
        $payload['form_'.$worksheet] = ['phone' => '0917 000 0000'];
    }

    $test->actingAs($user)->post('/visa-assistance/applications', $payload)->assertRedirect();

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
        ->assertSee('Waiting for the admin to approve this file.')
        ->assertSee(route('visa.consent-blank'), false)->assertDontSee(route('visa.applications.consent', $file), false)
        ->assertDontSee('once the admin approves the file');
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

    // The agent sends the client to the cashier, who takes the payment.
    $this->actingAs($agent)->post(route('visa.applications.payments', $file), ['amount' => 100])->assertSessionHas('error');
    $cashier = User::factory()->create(['role' => 'cashier']);
    $this->actingAs($cashier)->post(route('cashier.visa.store', $file->id), ['amount' => 100, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
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

test('a service lists its files: an agent sees their own, with the waiting state, Open and New', function () {
    Notification::fake();
    $agent = visaAgent();
    $other = visaAgent();
    $mine = savedVisaFile($this, $agent);
    savedVisaFile($this, $other, ['client_name' => 'Someone Else', 'client_email' => 'else@example.com']);
    $released = savedVisaFile($this, $agent, ['client_name' => 'Already Released', 'client_email' => 'rel@example.com']);
    $released->forceFill(['status' => 'released'])->save();
    savedVisaFile($this, $agent, ['service_type' => 'e_visa', 'client_name' => 'Evisa Person', 'client_email' => 'ev@example.com', 'applicant_type' => 'individual']);

    $page = $this->actingAs($agent)->get(route('visa.services', 'visit_visa'))->assertOk()
        ->assertSee('Needs action')->assertSee('Jane Tan')->assertSee('Waiting for Admin Approval')
        // A released file is still listed, in its own closed section after the rest.
        ->assertDontSee('Someone Else')->assertSee('Already Released')->assertSee('Released')->assertDontSee('Evisa Person')
        ->assertSee(e(route('visa.applications.show', [$mine, 'preview' => 1])), false)
        ->assertSee(e(route('visa.visit.new')), false)
        ->getContent();
    expect($page)->toContain('New Non-Immigrant');

    // E-Visa starts on its own form.
    $this->actingAs($agent)->get(route('visa.services', 'e_visa'))->assertOk()
        ->assertSee('Evisa Person')->assertSee('visit-visa/new?service=e_visa', false);

    // An admin sees every agent's files.
    $this->actingAs(visaBoss())->get(route('visa.services', 'visit_visa'))->assertOk()->assertSee('Jane Tan')->assertSee('Someone Else');

    $this->actingAs($agent)->get(route('visa.services', 'passporting'))->assertOk()->assertSee('No Passporting transactions yet.');
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
        ->assertSee('Client Information')->assertSee('Payment')
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

test('an e-visa file walks its own steps: agreement and fees sent to the admin, documents, then the result', function () {
    Notification::fake();
    $officer = visaAgent();
    visaBoss();
    visaBoss();

    $this->actingAs($officer)->get(route('visa.visit.new', ['service' => 'e_visa']))->assertOk()->assertSee('E-Visa');
    $this->actingAs($officer)->get(route('visa.visit.file', ['service' => 'e_visa']))->assertOk()
        ->assertSee('Who Can Apply &amp; General Requirements', false)->assertSee('name="service_type" value="e_visa"', false);

    // Step 1 opens the file straight on Step 2: no approval yet.
    $this->actingAs($officer)->post(route('visa.applications.store'), ['service_type' => 'e_visa', 'client_name' => 'Evisa Steps', 'destination_country' => 'Japan'])->assertRedirect();
    $file = VisaApplication::where('client_name', 'Evisa Steps')->firstOrFail();

    expect($file->status)->toBe('agreement')->and($file->approval_status)->toBeNull()
        ->and($file->stages())->toBe(['pending', 'agreement', 'requirements', 'drafting', 'result'])
        ->and($file->stageLabel('agreement'))->toBe('Service Agreement & Fees');
    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()->assertSee('Service Agreement &amp; Fees', false)->assertSee('Send to admin');
    expect(VisaApplication::awaitingPayment()->count())->toBe(0);

    // Step 2: nothing is sent before the fee is entered; only an approved file reaches the cashier.
    $this->actingAs($officer)->post(route('visa.applications.send-to-admin', $file))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('visa.applications.stage', $file), ['service_fee' => 3500, 'visa_fee' => 500])->assertSessionHas('success');
    expect((float) $file->fresh()->total_amount)->toBe(4000.0)->and($file->fresh()->agreement_signed_at)->toBeNull();
    $this->actingAs($officer)->post(route('visa.applications.stage', $file))->assertSessionHas('success');
    expect($file->fresh()->agreement_signed_at)->not->toBeNull()->and(VisaApplication::awaitingPayment()->count())->toBe(0);

    $this->actingAs($officer)->post(route('visa.applications.send-to-admin', $file))->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('pending')->and(VisaApplication::awaitingPayment()->count())->toBe(0);
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('error');

    $file->forceFill(['approval_status' => 'approved'])->save();
    expect(VisaApplication::awaitingPayment()->count())->toBe(1);
    // Approved is not enough: the cashier records a payment (partial or full) before Step 3.
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('error');
    $file->forceFill(['amount_paid' => 1000])->save();
    $file->payments()->create(['amount' => 1000, 'method' => 'cash', 'received_at' => now()]);
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('requirements')->and($file->fresh()->stageBlocker())->toContain('Acknowledgment of Documents');
});

test('an e-visa file at Step 2 shows only the service agreement: no applicants, file details or fees', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV9', 'client_name' => 'Evisa Two', 'service_type' => 'e_visa', 'status' => 'agreement', 'created_by' => $agent->id, 'destination_country' => 'Japan']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Service Agreement')->assertDontSee('id="applicants"', false)->assertDontSee('File Details')->assertDontSee('id="fees"', false);

    // Step 3 (paid in full here): still no fees.
    $file->update(['status' => 'requirements']);
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()->assertDontSee('id="fees"', false);
});

test('an e-visa file at Step 3 hides applicants and file details, and shows fees only while a balance is owed', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV8', 'client_name' => 'Evisa Three', 'service_type' => 'e_visa', 'status' => 'requirements', 'created_by' => $agent->id, 'destination_country' => 'Japan', 'total_amount' => 2000, 'amount_paid' => 500]);

    $owing = $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk();
    $owing->assertDontSee('File Details')->assertDontSee('id="applicants"', false)->assertSee('id="fees"', false);
    // One card: the Document Checklist first, then the step's own Collect Documents & Acknowledgment.
    $owing->assertSeeInOrder(['Document Checklist', 'Collect Documents &amp; Acknowledgment'], false);

    $file->update(['amount_paid' => 2000]);
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('File Details')->assertDontSee('id="applicants"', false)->assertDontSee('id="fees"', false);
});

test('an e-visa document has no applicant: an "other" document takes remarks', function () {
    Storage::fake(DocumentStorage::diskName());
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV7', 'client_name' => 'Evisa Docs', 'service_type' => 'e_visa', 'status' => 'requirements', 'created_by' => $agent->id, 'destination_country' => 'Japan', 'total_amount' => 1000, 'amount_paid' => 100]);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('name="visa_applicant_id"', false)->assertSee('name="remarks"', false);

    $this->actingAs($agent)->post(route('visa.documents.store', $file), [
        'document_type' => 'other', 'remarks' => 'Hotel booking', 'file' => UploadedFile::fake()->create('hotel.pdf', 20, 'application/pdf'),
    ])->assertSessionHas('success');

    // No fixed list: nothing is "missing", whatever the agent filed.
    expect($file->fresh()->missingDocuments())->toBe([])->and($file->fresh()->stageBlocker())->not->toContain('Still missing');
    expect($file->documents()->first()->remarks)->toBe('Hotel booking');
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Hotel booking');
    $this->actingAs($agent)->get(route('visa.applications.acknowledgement', $file))->assertOk()->assertSee('Other (Hotel booking)');
});

test('an e-visa file has an Encoding on the Site step before the results: a reference or an uploaded copy', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV6', 'client_name' => 'Evisa Site', 'service_type' => 'e_visa', 'status' => 'drafting', 'created_by' => $agent->id, 'destination_country' => 'Japan', 'total_amount' => 1000, 'amount_paid' => 1000]);

    expect($file->stageLabel('drafting'))->toBe('Encoding on the Site')
        ->and($file->stageBlocker())->toContain('encoded');

    $this->actingAs($agent)->post(route('visa.applications.stage', $file), ['action' => 'encoded'])->assertSessionHas('success');
    expect($file->fresh()->stageBlocker())->toContain('reference number');

    $file->update(['portal_reference' => 'EV-12345']);
    expect($file->fresh()->stageBlocker())->toBeNull();
    $this->actingAs($agent)->post(route('visa.applications.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('result');
});

test('an e-visa file at Step 4 shows only the encoding and the file upload', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV5', 'client_name' => 'Evisa Four', 'service_type' => 'e_visa', 'status' => 'drafting', 'created_by' => $agent->id, 'destination_country' => 'Canada', 'total_amount' => 1000, 'amount_paid' => 1000]);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('File Details')->assertDontSee('id="applicants"', false)->assertDontSee('Canada steps')
        ->assertSee('Details encoded on official website')->assertSee('Copy of the submitted application');
});

test('an e-visa file at Step 5 shows no applicants or file details', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV4', 'client_name' => 'Evisa Five', 'service_type' => 'e_visa', 'status' => 'result', 'created_by' => $agent->id, 'destination_country' => 'Canada', 'total_amount' => 1000, 'amount_paid' => 1000]);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertDontSee('File Details')->assertDontSee('id="applicants"', false)->assertSee('Result');
});

test('an approved e-visa file has a Results button that emails the client', function () {
    Notification::fake();
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV3', 'client_name' => 'Evisa Mail', 'client_email' => 'evisa@example.com', 'service_type' => 'e_visa', 'status' => 'result', 'created_by' => $agent->id, 'destination_country' => 'Japan', 'agreement_signed_at' => now(), 'total_amount' => 2000, 'amount_paid' => 2000, 'approval_status' => 'pending']);

    // Not approved yet: no button, and the route refuses.
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertDontSee('Email the client');
    $this->actingAs($agent)->post(route('visa.applications.notify-approved', $file))->assertSessionHas('error');

    $file->forceFill(['result' => 'denied'])->save();
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Email the client: E-Visa denied');
    $this->actingAs($agent)->post(route('visa.applications.notify-approved', $file))->assertSessionHas('success');

    Notification::assertSentOnDemand(ClientCaseNotification::class);
});

test('a passporting file walks the same steps as an e-visa, with the appointment before the results', function () {
    Notification::fake();
    $officer = visaAgent();
    visaBoss();
    visaBoss();

    $this->actingAs($officer)->get(route('visa.visit.new', ['service' => 'passporting']))->assertOk()->assertSee('Passporting');
    $this->actingAs($officer)->get(route('visa.visit.file', ['service' => 'passporting']))->assertOk()
        ->assertSee('Who Can Apply &amp; General Requirements', false)->assertSee('name="passport_type"', false);

    // Step 1 opens the file straight on Step 2; a foreign passport needs its embassy.
    $this->actingAs($officer)->post(route('visa.applications.store'), ['service_type' => 'passporting', 'client_name' => 'Pass Port', 'passport_type' => 'foreign'])->assertSessionHasErrors('embassy_country');
    $this->actingAs($officer)->post(route('visa.applications.store'), ['service_type' => 'passporting', 'client_name' => 'Pass Port', 'passport_type' => 'local'])->assertRedirect();
    $file = VisaApplication::where('client_name', 'Pass Port')->firstOrFail();

    expect($file->status)->toBe('agreement')->and($file->approval_status)->toBeNull()
        ->and($file->stages())->toBe(['pending', 'agreement', 'requirements', 'appointment', 'result'])
        ->and($file->stageLabel('appointment'))->toBe('Appointment & Submission');
    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Service Agreement &amp; Fees', false)->assertDontSee('id="applicants"', false)->assertDontSee('File Details');

    // Step 2: the same fee, Send to admin, cashier flow.
    $this->actingAs($officer)->post(route('visa.applications.stage', $file), ['service_fee' => 1500, 'visa_fee' => 0])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.stage', $file))->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.send-to-admin', $file))->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('pending')->and(VisaApplication::awaitingPayment()->count())->toBe(0);

    $file->forceFill(['approval_status' => 'approved', 'amount_paid' => 500])->save();
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('error');
    $file->payments()->create(['amount' => 500, 'method' => 'cash', 'received_at' => now()]);
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('requirements')->and($file->fresh()->stageBlocker())->toContain('Acknowledgment of Documents');

    // A Philippine passport needs its DFA appointment; a foreign one does not.
    $file->update(['status' => 'appointment']);
    expect($file->fresh()->stageBlocker())->toContain('DFA appointment');
    $file->update(['passport_type' => 'foreign', 'embassy_country' => 'Canada']);
    expect($file->fresh()->stageBlocker())->toBeNull();
});

test('a denied result finishes the file instead of releasing it', function () {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-EV9', 'client_name' => 'Denied One', 'service_type' => 'passporting', 'status' => 'result', 'created_by' => $agent->id, 'total_amount' => 1000, 'amount_paid' => 1000, 'approval_status' => 'approved', 'result' => 'denied']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()->assertSee('Finish')->assertDontSee('Release file');
    $this->actingAs($agent)->post(route('visa.applications.advance', $file))->assertSessionHas('success', 'File finished.');
    expect($file->fresh()->status)->toBe('released')->and($file->fresh()->status_label)->toBe('Denied');
});

test('a denied file is listed under Denied, not Released', function () {
    $agent = visaAgent();
    VisaApplication::create(['reference' => 'VSA-D1', 'client_name' => 'Denied Dan', 'service_type' => 'passporting', 'status' => 'released', 'created_by' => $agent->id, 'approval_status' => 'approved', 'result' => 'denied']);

    $this->actingAs($agent)->get(route('visa.services', 'passporting'))->assertOk()->assertSee('aria-label="Denied"', false)->assertDontSee('aria-label="Released"', false);
});

test('every step-by-step service ends the same way on a denied result', function (string $service) {
    $agent = visaAgent();
    $file = VisaApplication::create(['reference' => 'VSA-D-'.$service, 'client_name' => 'Denied Dee', 'client_email' => 'dee@example.com', 'service_type' => $service, 'status' => 'result', 'created_by' => $agent->id, 'destination_country' => 'Japan', 'total_amount' => 1000, 'amount_paid' => 1000, 'approval_status' => 'approved', 'result' => 'denied']);

    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Finish')->assertDontSee('Release file')->assertSee('Email the client: '.VisaApplication::SERVICE_LABELS[$service].' denied');
    $this->actingAs($agent)->post(route('visa.applications.advance', $file));
    expect($file->fresh()->status_label)->toBe('Denied');
    $this->actingAs($agent)->get(route('visa.services', $service))->assertSee('aria-label="Denied"', false);
})->with(['visit_visa', 'e_visa', 'passporting', 'citizenship']);

test('a citizenship claim opens from its own wizard and walks the fee-first steps to the embassy appointment', function () {
    Notification::fake();
    $officer = visaAgent();
    visaBoss();
    visaBoss();

    $this->actingAs($officer)->get(route('visa.visit.file', ['service' => 'citizenship']))->assertOk()
        ->assertSee('New Citizenship File')->assertSee('Who can apply?')->assertSee('born outside the US to a US citizen parent')
        ->assertSee('name="claim_type"', false)->assertSee('name="us_citizen_parent"', false);
    $this->actingAs($officer)->get(route('visa.services', 'citizenship'))->assertOk();

    // Step 1 needs the kind of application and the US citizen parent.
    $this->actingAs($officer)->post(route('visa.applications.store'), ['service_type' => 'citizenship', 'client_name' => 'Baby Mc'])->assertSessionHasErrors(['claim_type', 'us_citizen_parent']);
    $this->actingAs($officer)->post(route('visa.applications.store'), ['service_type' => 'citizenship', 'client_name' => 'Baby Mc', 'claim_type' => 'crba', 'us_citizen_parent' => 'Mark McCoy'])->assertRedirect();
    $file = VisaApplication::where('client_name', 'Baby Mc')->firstOrFail();

    expect($file->status)->toBe('agreement')->and($file->destination_country)->toBe('USA')->and($file->purpose)->toBe('crba')
        ->and($file->us_citizen_parent)->toBe('Mark McCoy')->and($file->isFeeFirst())->toBeTrue()
        ->and($file->stages())->toBe(['pending', 'agreement', 'requirements', 'appointment', 'result'])
        ->and($file->stageLabel('appointment'))->toBe('Application & Embassy Appointment');
    $this->actingAs($officer)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Service Agreement &amp; Fees', false)->assertDontSee('id="applicants"', false);

    $this->actingAs($officer)->get(route('visa.applications.show', [$file, 'preview' => 1]))->assertOk()->assertSee('CRBA (under 18)')->assertSee('Mark McCoy');
    $this->actingAs($officer)->post(route('visa.applications.stage', $file), ['service_fee' => 5000, 'visa_fee' => 0])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.stage', $file))->assertSessionHas('success');
    $this->actingAs($officer)->post(route('visa.applications.send-to-admin', $file))->assertSessionHas('success');
    $file->forceFill(['approval_status' => 'approved'])->save();
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('error');
    $file->forceFill(['amount_paid' => 1000])->save();
    $file->payments()->create(['amount' => 1000, 'method' => 'cash', 'received_at' => now()]);
    $this->actingAs($officer)->post(route('visa.applications.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('requirements')->and($file->fresh()->stageBlocker())->toContain('Acknowledgment of Documents');

    $file->update(['status' => 'appointment']);
    expect($file->fresh()->stageBlocker())->toContain('US Embassy appointment');
    $file->update(['appointment_at' => now()->addWeek()]);
    expect($file->fresh()->stageBlocker())->toBeNull();
});
