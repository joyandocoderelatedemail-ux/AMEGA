<?php

use App\Models\SrrvApplication;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\AdminNotifications;
use App\Support\ServiceList;
use App\Support\SrrvForms;
use App\Support\TicketNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** A new SRRV application opened through the real intake, left as the agent leaves it (not auto-approved). */
function srrvNewFile(User $officer, array $overrides = []): SrrvApplication
{
    test()->actingAs($officer)->post('/srrv/applications', array_merge([
        'service_type' => 'renewal_application',
        'visa_class' => 'classic',
        'retiree_name' => 'Ramon Dela Cruz',
        'retiree_email' => 'ramon@example.com',
        'date_of_birth' => now()->subYears(62)->format('Y-m-d'),
        'investment_amount' => 10000,
        'service_fee' => 0,
    ], $overrides))->assertRedirect();

    return SrrvApplication::latest('id')->firstOrFail();
}

test('a new SRRV application opens on Step 2 and is not sent to the admin until the agent sends it', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $file = srrvNewFile($officer, ['amount_paid' => 500]);

    expect($file->status)->toBe('agreement')->and($file->approval_status)->toBeNull()->and((float) $file->amount_paid)->toBe(0.0)
        ->and($file->stageLabel('agreement'))->toBe('Service Agreement & Fees')
        ->and($file->stages())->toBe(['pending', 'agreement', 'requirements', 'documentation', 'oath', 'awaiting_release', 'released']);

    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('Service Agreement &amp; Fees', false)->assertSee('Send to admin')->assertDontSee('File Details')->assertDontSee('Annual Renewals');
});

test('step 2 walks the agreement, the fee, the admin and the cashier before the documents', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $file = srrvNewFile($officer);

    // Nothing to send yet: no fee.
    $this->actingAs($officer)->post(route('srrv.applications.send-to-admin', $file))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.stage', $file), ['service_fee' => 25000])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.applications.stage', $file))->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.applications.send-to-admin', $file))->assertSessionHas('success');
    expect($file->fresh()->approval_status)->toBe('pending');

    // The cashier takes nothing until the admin approves.
    $this->actingAs($cashier)->post(route('cashier.srrv.store', $file->id), ['amount' => 5000, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('error');
    $file->forceFill(['approval_status' => 'approved'])->save();

    $this->actingAs($cashier)->get(route('cashier.srrv.index'))->assertOk()->assertSee($file->reference);
    // The agent cannot record a payment on a new application, and cannot advance without the cashier's.
    $this->actingAs($officer)->post(route('srrv.applications.payments', $file), ['amount' => 5000])->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.advance', $file))->assertSessionHas('error');

    $this->actingAs($cashier)->post(route('cashier.srrv.store', $file->id), ['amount' => 5000, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
    expect((float) $file->fresh()->amount_paid)->toBe(5000.0)->and($file->payments()->count())->toBe(1);

    $this->actingAs($officer)->post(route('srrv.applications.advance', $file))->assertSessionHas('success');
    expect($file->fresh()->status)->toBe('requirements');
    $this->actingAs($admin);
});

test('step 3 has no fixed list: only the signed acknowledgment is needed', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $file = srrvNewFile($officer);
    $file->forceFill(['status' => 'requirements', 'approval_status' => 'approved', 'service_fee' => 1000, 'amount_paid' => 100])->save();

    expect($file->fresh()->stageBlocker())->toContain('Acknowledgment of Documents');
    $this->actingAs($officer)->get(route('srrv.applications.show', $file))->assertOk()
        ->assertSee('Document Checklist')->assertSee('Retiree signed the acknowledgment')->assertDontSee('File Details');
    $this->actingAs($officer)->get(route('srrv.applications.acknowledgement', $file))->assertOk()->assertSee('ACKNOWLEDGMENT OF DOCUMENTS');
    $this->actingAs($officer)->post(route('srrv.applications.stage', $file))->assertSessionHas('success');
    expect($file->fresh()->stageBlocker())->toBeNull();
});

test('release waits for the file to be fully paid at the cashier', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $file = srrvNewFile($officer);
    $file->forceFill(['status' => 'awaiting_release', 'approval_status' => 'approved', 'service_fee' => 1000, 'amount_paid' => 400])->save();
    $file->payments()->create(['amount' => 400, 'method' => 'cash', 'received_at' => now()]);

    expect($file->fresh()->stageBlocker())->toContain('fully paid');
    $this->actingAs($cashier)->post(route('cashier.srrv.store', $file->id), ['amount' => 600, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
    expect($file->fresh()->stageBlocker())->toBeNull()->and($file->fresh()->payment_in_full_at)->not->toBeNull();
});

test('the cashier tabs and the admin bell show what waits for payment, by service', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $admin = User::factory()->create(['role' => 'admin']);
    $file = srrvNewFile($officer);
    $file->forceFill(['status' => 'agreement', 'approval_status' => 'approved', 'service_fee' => 5000, 'amount_paid' => 0, 'reviewed_at' => now()])->save();

    // The cashier: a count on the PRA-SRRV tab, and the bell names the service.
    $this->actingAs($cashier)->get(route('cashier.dashboard'))->assertOk()->assertSee('1 waiting for payment');
    $bell = TicketNotifications::forCashier();
    expect($bell['total'])->toBe(1)->and($bell['latest'][0]['title'])->toContain('(SRRV)')->and($bell['latest'][0]['detail'])->toStartWith('PRA-SRRV');

    // The admin: the Cashier is its own entry, no longer under Services, with the count.
    $groups = collect(AdminNavigation::for($admin));
    expect($groups->pluck('key')->all())->toContain('cashier')
        ->and($groups->firstWhere('key', 'services')['items'])->each(fn ($item) => $item->label->not->toBe('Cashier'))
        ->and($groups->firstWhere('key', 'cashier')['badge'])->toBe(1);
    expect(AdminNotifications::for($admin)['cashier_payments'])->toBe(1);
});

test('the admin top bar shows the cashier count on its own button', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $admin = User::factory()->create(['role' => 'admin']);
    $file = srrvNewFile($officer);
    $file->forceFill(['approval_status' => 'approved', 'service_fee' => 5000, 'amount_paid' => 0])->save();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('data-notify-count="cashier_payments"', false)->assertSee('aria-label="1 new"', false);
});

test('the SRRV wizard steps read Overview, Service & Class, Retiree, PRA Forms, Documents, Service Agreement & Fees, Remarks & Save', function () {
    expect(SrrvForms::WIZARD_STEPS)->toBe([1 => 'Service & Class', 2 => 'Retiree', 3 => 'PRA Forms', 4 => 'Documents', 5 => 'Service Agreement & Fees', 6 => 'Remarks & Save']);

    $this->actingAs(User::factory()->create(['role' => 'srrv']))->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('Overview')->assertSee('Service Agreement')->assertSee(route('srrv.agreement-blank'), false)
        ->assertSee('Requirements')->assertDontSee('Documents already handed in')->assertSee('PRA Forms');

    // The agreement shown there is the SRRV Assistance Booking Agreement, blank.
    $this->actingAs(User::factory()->create(['role' => 'srrv']))->get(route('srrv.agreement-blank', ['visa_class' => 'courtesy']))->assertOk()
        ->assertSee('SRRV ASSISTANCE BOOKING AGREEMENT')->assertSee('Services Included');
});

test('the wizard step 3 records the signature, saves the fee with the investment amount, and sends the file to the admin', function () {
    User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);

    // Save fee: the file opens with the signature and the fee and the investment amount, and the wizard comes back to step 3.
    $this->actingAs($officer)->post('/srrv/applications', [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz',
        'agreement_signed' => 1, 'service_fee' => 1000, 'investment_amount' => 25000, 'currency' => 'USD', 'then' => 'fee',
    ])->assertRedirect(route('srrv.applications.edit', [SrrvApplication::latest('id')->first(), 'step' => 5]));
    $file = SrrvApplication::latest('id')->firstOrFail();

    expect($file->agreement_signed_at)->not->toBeNull()->and((float) $file->investment_amount)->toBe(25000.0)
        ->and($file->outstandingBalance())->toBe(1000.0)->and($file->approval_status)->toBeNull();

    // Send to admin: saves the wizard again, then the file waits for the admin.
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), [
        'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz',
        'service_fee' => 1000, 'investment_amount' => 25000, 'currency' => 'USD', 'then' => 'send',
    ])->assertRedirect(route('srrv.applications.edit', [$file, 'step' => 5]));

    expect($file->fresh()->isAwaitingApproval())->toBeTrue();
});

test('the agreement printed from the wizard is filled from the Retiree step and the fee', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.agreement-blank', [
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'retiree_email' => 'ramon@example.com', 'retiree_phone' => '+63 900 111 2222',
        'date_of_birth' => '1960-03-04', 'nationality' => 'Filipino', 'service_fee' => 1500, 'currency' => 'PHP',
        'form' => ['gender' => 'M', 'passport_no' => 'P1234567', 'passport_expires_on' => '2030-01-15', 'primary_address' => '12 Rizal St, Angeles City', 'home_address' => '5 Main Rd, Sydney', 'spouse_name' => 'Maria Dela Cruz', 'spouse_dob' => '1962-05-06', 'srrv_option' => 'smile'],
    ]))->assertOk()
        ->assertSee('Male')->assertSee('P1234567')->assertSee('January 15, 2030')->assertSee('12 Rizal St, Angeles City')->assertSee('5 Main Rd, Sydney')
        ->assertSee('Maria Dela Cruz')->assertSee('Spouse, born May 6, 1962')
        ->assertSee('Dela Cruz')->assertSee('ramon@example.com')->assertSee('+63 900 111 2222')->assertSee('March 4, 1960')
        ->assertSee('Filipino')->assertSee('1,500.00')->assertSee('SRRV Classic');

    // An invalid date is refused rather than printed.
    $this->actingAs($officer)->get(route('srrv.agreement-blank', ['date_of_birth' => 'not a date']))->assertSessionHasErrors('date_of_birth');
});

test('the Documents step prints the acknowledgment before the file is saved, listing what has been ticked', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.applications.create'))->assertOk()
        ->assertSee('Print acknowledgment')->assertSee('Signed copy (scan)')->assertSee('name="documents[signed_acknowledgement][file]"', false);

    $this->actingAs($officer)->get(route('srrv.acknowledgement-blank', [
        'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'received' => ['passport', 'police_clearance', 'passport'],
    ]))->assertOk()->assertSee('ACKNOWLEDGMENT OF DOCUMENTS')->assertSee('Ramon Dela Cruz')->assertSee('Passport')->assertSee('Police clearance')->assertSee('Back to the application');

    // An unknown document type is refused rather than printed.
    $this->actingAs($officer)->get(route('srrv.acknowledgement-blank', ['received' => ['nonsense']]))->assertSessionHasErrors('received.0');
});

test('Save fee and Send to admin need a service fee, and the fee step opens on the error', function () {
    $officer = User::factory()->create(['role' => 'srrv']);

    foreach (['fee', 'send'] as $then) {
        $this->actingAs($officer)->post('/srrv/applications', [
            'service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'service_fee' => 0, 'then' => $then,
        ])->assertSessionHasErrors('service_fee');
    }
    expect(SrrvApplication::count())->toBe(0);

    // A plain save without a fee is still allowed.
    $this->actingAs($officer)->post('/srrv/applications', ['service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'service_fee' => 0])->assertSessionHasNoErrors();
});

test('Save & Print on the last step marks the file pending for the admin', function () {
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);
    $payload = ['service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'service_fee' => 1500, 'currency' => 'USD', 'then' => 'print'];

    // No fee, nothing to send: refused, and no file is opened.
    $this->actingAs($officer)->post('/srrv/applications', array_merge($payload, ['service_fee' => 0]))->assertSessionHasErrors('service_fee');
    expect(SrrvApplication::count())->toBe(0);

    // A new file: opened and sent in one go; it cannot print until approved, so it stays in the wizard.
    $this->actingAs($officer)->post('/srrv/applications', $payload)->assertRedirect(route('srrv.applications.edit', [SrrvApplication::latest('id')->first(), 'step' => 5]));
    $file = SrrvApplication::latest('id')->firstOrFail();
    expect($file->isAwaitingApproval())->toBeTrue();

    // An older file saved on step 6 that never went to the admin is sent too.
    $other = srrvNewFile($officer, ['service_fee' => 900]);
    expect($other->approval_status)->toBeNull();
    $this->actingAs($officer)->put(route('srrv.applications.update', $other), array_merge($payload, ['service_fee' => 900]))->assertRedirect(route('srrv.applications.edit', [$other, 'step' => 5]));
    expect($other->fresh()->isAwaitingApproval())->toBeTrue();
});

test('Finish saves the file, marks it pending, and ends the wizard on the service list', function () {
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);

    $this->actingAs($officer)->get(route('srrv.applications.create'))->assertOk()->assertSee('name="then" value="finish"', false)->assertSee('Finish');

    // A fee is needed to send the file, as with Save & Print.
    $this->actingAs($officer)->post('/srrv/applications', ['service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'then' => 'finish'])->assertSessionHasErrors('service_fee');

    $this->actingAs($officer)->post('/srrv/applications', ['service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Ramon Dela Cruz', 'service_fee' => 1500, 'then' => 'finish'])
        ->assertRedirect(route('srrv.services', 'classic'))->assertSessionHas('success');

    expect(SrrvApplication::latest('id')->firstOrFail()->isAwaitingApproval())->toBeTrue();
});

test('the wizard Advance moves the file itself on from Service Agreement & Fees, then opens the next step', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $cashier = User::factory()->create(['role' => 'cashier']);
    $file = srrvNewFile($officer);
    $this->actingAs($officer)->post(route('srrv.applications.stage', $file), ['service_fee' => 1500]);
    $this->actingAs($officer)->post(route('srrv.applications.stage', $file));
    $this->actingAs($officer)->post(route('srrv.applications.send-to-admin', $file));
    $file->forceFill(['approval_status' => 'approved'])->save();

    // Not paid yet: the file stays where it is, and the officer is sent back with the reason.
    $this->actingAs($officer)->post(route('srrv.applications.advance', $file), ['step' => 6])->assertSessionHas('error');
    expect($file->fresh()->status)->toBe('agreement');

    $this->actingAs($cashier)->post(route('cashier.srrv.store', $file->id), ['amount' => 500, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
    $this->actingAs($officer)->post(route('srrv.applications.advance', $file), ['step' => 6])->assertRedirect(route('srrv.applications.edit', [$file, 'step' => 6]));
    expect($file->fresh()->status)->toBe('requirements');
});

test('an agent sets the SRRV file status by hand, releases it to lock it, and asks the admin to unlock it', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);
    $file = srrvNewFile($officer, ['service_fee' => 900]);

    // The list and the preview carry the dropdown, and a file nobody updated is in preparation.
    expect($file->progress_label)->toBe('On-going Preparation');
    $this->actingAs($officer)->get(route('srrv.services', 'classic'))->assertOk()->assertSee('On-going Preparation')->assertSee('name="status"', false)->assertSee(route('srrv.applications.status', $file), false);
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'preview' => 1]))->assertOk()->assertSee('name="status"', false);

    // Free to move through the statuses; a nonsense one is refused.
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'for_validation'])->assertSessionHas('success');
    expect($file->fresh()->workflow_status)->toBe('for_validation');
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'nonsense'])->assertSessionHas('error');

    // Released asks for the date, then locks the file.
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'released'])->assertSessionHasErrors('released_on');
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'released', 'released_on' => now()->toDateString()])->assertSessionHas('success');
    $file = $file->fresh();
    expect($file->isReleasedLocked())->toBeTrue()->and($file->progress_label)->toBe('Released');
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'for_validation'])->assertSessionHas('error');
    $this->actingAs($officer)->put(route('srrv.applications.update', $file), ['service_type' => 'renewal_application', 'visa_class' => 'classic', 'retiree_name' => 'Changed Name'])->assertSessionHas('error');
    expect($file->fresh()->retiree_name)->toBe('Ramon Dela Cruz');

    // The agent asks to unlock it (a reason is needed); only one request waits at a time.
    $this->actingAs($officer)->get(route('srrv.applications.edit', [$file, 'preview' => 1]))->assertOk()->assertSee('Request edit');
    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), [])->assertSessionHasErrors('reason');
    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), ['reason' => 'Passport number typed wrong'])->assertSessionHas('success');
    expect($file->fresh()->hasEditRequest())->toBeTrue();
    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), ['reason' => 'Again'])->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.reopen', $file))->assertSessionHas('error');

    // It waits on the admin's page; declining keeps it locked, approving opens it again.
    $this->actingAs($admin)->get(route('admin.srrv-approvals.index'))->assertOk()->assertSee('Passport number typed wrong');
    $this->actingAs($admin)->post(route('admin.srrv-approvals.edit-decline', ['application', $file->id]), ['note' => 'Not now'])->assertSessionHas('success');
    expect($file->fresh()->isReleasedLocked())->toBeTrue()->and($file->fresh()->editRequestDeclined())->toBeTrue();
    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), ['reason' => 'Please, it is a typo'])->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.srrv-approvals.edit-approve', ['application', $file->id]))->assertSessionHas('success');
    $file = $file->fresh();
    expect($file->isReleasedLocked())->toBeFalse()->and($file->hasEditRequest())->toBeFalse();
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'for_submission'])->assertSessionHas('success');

    // Denied closes the file too.
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'denied'])->assertSessionHas('success');
    expect($file->fresh()->isReleasedLocked())->toBeTrue();
});

test('an Approved SRRV status is locked for agents and unlocked by an approved status request', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'admin']);
    $officer = User::factory()->create(['role' => 'srrv']);
    $file = srrvNewFile($officer);

    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'approved'])->assertSessionHas('success');
    expect($file->fresh()->approvedStatusLocked())->toBeTrue();

    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'submitted'])->assertSessionHas('error');
    $this->actingAs($officer)->post(route('srrv.applications.request-edit', $file), ['reason' => 'Wrong status picked'])->assertSessionHas('success');
    expect($file->fresh()->edit_request_type)->toBe('status');

    $this->actingAs($admin)->post(route('admin.srrv-approvals.edit-approve', ['application', $file->id]))->assertSessionHas('success');
    expect($file->fresh()->approvedStatusLocked())->toBeFalse();
    $this->actingAs($officer)->post(route('srrv.applications.status', $file), ['status' => 'submitted'])->assertSessionHas('success');
});

test('a denied SRRV file is listed under Denied, not Released', function () {
    $officer = User::factory()->create(['role' => 'srrv']);
    $released = srrvNewFile($officer);
    $denied = srrvNewFile($officer);
    $this->actingAs($officer)->post(route('srrv.applications.status', $released), ['status' => 'released', 'released_on' => now()->toDateString()]);
    $this->actingAs($officer)->post(route('srrv.applications.status', $denied), ['status' => 'denied']);

    $sections = SrrvServiceListProbe::sections($this, $officer);
    expect($sections['released'])->toBe([$released->reference])->and($sections['denied'])->toBe([$denied->reference]);
});

final class SrrvServiceListProbe
{
    /** @return array<string, list<string>> the references the service list puts in each section */
    public static function sections($test, User $officer): array
    {
        $rows = $test->actingAs($officer)->get(route('srrv.services', 'classic'))->viewData('rows');

        return collect(ServiceList::sections($rows))->map(fn ($section) => $section->map(fn ($row) => explode(' · ', $row['meta'])[0])->values()->all())->all();
    }
}
