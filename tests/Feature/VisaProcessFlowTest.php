<?php

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\VisaApplication;
use App\Support\TicketNotifications;
use Illuminate\Support\Facades\Notification;

/**
 * The Non-Immigrant processing flow, Step 0 to Step 5: the tracker numbers the steps from 0, Step 3 is the official
 * website encoding, Step 4 needs the appointment and biometrics dates (with an embassy appearance), and the cashier
 * takes the payments once the admin approves the file.
 */
function vpfAgent(): User
{
    return User::factory()->create(['role' => 'visa_assistance']);
}

function vpfCashier(): User
{
    return User::factory()->create(['role' => 'cashier', 'name' => 'Cara Cashier']);
}

function vpfFile(User $agent, array $file = []): VisaApplication
{
    return VisaApplication::withoutGlobalScopes()->forceCreate($file + [
        'reference' => 'VSA-'.fake()->unique()->numerify('####'), 'created_by' => $agent->id, 'service_type' => 'visit_visa',
        'status' => 'agreement', 'client_name' => 'Jane Tan', 'client_email' => 'jane@example.com', 'destination_country' => 'Japan',
        'approval_status' => 'approved', 'reviewed_at' => now(), 'service_fee' => 3000, 'visa_fee' => 2000, 'total_amount' => 5000, 'amount_paid' => 0,
    ]);
}

test('one step list, Step 0 to Step 6, drawn once on the wizard and once on the file', function () {
    $agent = vpfAgent();
    $steps = ['Inquiry &amp; Initial Assessment', 'Draft Form &amp; Admin Approval', 'Print Booking Agreement', 'Receiving of Documents', 'Official Website Encoding', 'Full Payment, Appointment &amp; Biometrics', 'Results'];

    $wizard = $this->actingAs($agent)->get(route('visa.visit.file'))->assertOk()
        ->assertSee('Step 0 - Inquiry &amp; Initial Assessment', false)->assertSee('of 6')->assertSeeInOrder($steps, false)
        ->assertDontSee('Open the file')->getContent();
    expect(substr_count($wizard, 'data-visit-visa-steps'))->toBe(1);

    $file = vpfFile($agent, ['status' => 'pending']);
    $page = $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk()
        ->assertSee('Step 1 - Draft Form &amp; Admin Approval', false)->assertSee('of 6')->assertSeeInOrder($steps, false)
        ->getContent();
    expect(substr_count($page, 'data-visit-visa-steps'))->toBe(1)->and(substr_count($page, 'aria-current="step"'))->toBe(1);

    $file->update(['status' => 'result']);
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Step 6 - Results');
});

test('Step 2: the booking agreement prints only once the admin approves', function () {
    $agent = vpfAgent();
    $held = vpfFile($agent, ['approval_status' => 'pending']);
    $this->actingAs($agent)->get(route('visa.applications.show', $held))->assertSee('Print Booking Agreement (after the admin approves)')
        ->assertDontSee('href="'.route('visa.applications.agreement', $held).'"', false);

    $approved = vpfFile($agent);
    $this->actingAs($agent)->get(route('visa.applications.show', $approved))->assertSee('Step 2 - Print Booking Agreement')
        ->assertSee('href="'.route('visa.applications.agreement', $approved).'"', false);
});

test('Step 4 needs the appointment and biometrics dates with an embassy appearance, not for Australia', function () {
    $agent = vpfAgent();
    $japan = vpfFile($agent, ['status' => 'lodged', 'amount_paid' => 5000]);
    $this->actingAs($agent)->post(route('visa.applications.stage', $japan), ['lodged_at' => now()->toDateString()])->assertSessionHasErrors(['appointment_at', 'biometrics_at']);

    $australia = vpfFile($agent, ['status' => 'lodged', 'amount_paid' => 5000, 'destination_country' => 'Australia', 'requires_appearance' => false]);
    $this->actingAs($agent)->post(route('visa.applications.stage', $australia), ['lodged_at' => now()->toDateString()])->assertSessionHas('success');
    // The dates are not asked for (the country's own steps, such as Australia's biometrics ticks, still are).
    expect($australia->fresh()->lodged_at)->not->toBeNull()
        ->and((string) $australia->fresh()->stageBlocker())->not->toContain('appointment')->not->toContain('biometrics date');
});

test('an approved visa file with a balance is on the cashier desk; the cashier records the payment', function () {
    Notification::fake();
    $agent = vpfAgent();
    $cashier = vpfCashier();
    $file = vpfFile($agent, ['agreement_signed_at' => now()]);
    $pending = vpfFile($agent, ['approval_status' => 'pending', 'client_name' => 'Held Client']);

    $this->actingAs($cashier)->get(route('cashier.visa.index'))->assertOk()
        ->assertSee($file->reference)->assertSee('Jane Tan')->assertDontSee('Held Client');
    expect(TicketNotifications::forCashier()['total'])->toBe(1);

    $this->actingAs($cashier)->get(route('cashier.visa.show', $file->id))->assertOk()->assertSee('Record payment')->assertSee('5,000.00');

    // The acknowledgement is required, and the amount cannot pass the balance.
    $this->actingAs($cashier)->post(route('cashier.visa.store', $file->id), ['amount' => 2000, 'method' => 'cash'])->assertSessionHasErrors('cashier_acknowledged');
    $this->actingAs($cashier)->post(route('cashier.visa.store', $file->id), ['amount' => 9000, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHasErrors('amount');

    $this->actingAs($cashier)->post(route('cashier.visa.store', $file->id), ['amount' => 2000, 'method' => 'gcash', 'reference' => 'OR-55', 'cashier_acknowledged' => 1])
        ->assertRedirect(route('cashier.visa.show', $file->id))->assertSessionHas('success');

    $fresh = $file->fresh();
    expect((float) $fresh->amount_paid)->toBe(2000.0)->and($fresh->payment_type)->toBe('deposit')
        ->and($fresh->payments)->toHaveCount(1)->and($fresh->payments->first()->received_by)->toBe($cashier->id)
        ->and($fresh->stageBlocker())->toBeNull();
    expect(ActivityLog::where('action', 'VISA_PAYMENT')->exists())->toBeTrue();

    // The agent sees it on the file.
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertOk();

    // A file the admin has not approved is not paid.
    $this->actingAs($cashier)->post(route('cashier.visa.store', $pending->id), ['amount' => 100, 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'waiting for admin approval'));

    // Paid in full: off the list, on the Paid tab.
    $this->actingAs($cashier)->post(route('cashier.visa.store', $file->id), ['amount' => 3000, 'method' => 'cash', 'cashier_acknowledged' => 1])->assertSessionHas('success');
    expect($file->fresh()->payment_type)->toBe('full');
    $this->actingAs($cashier)->get(route('cashier.visa.index'))->assertDontSee(route('cashier.visa.show', $file->id), false);
    $this->actingAs($cashier)->get(route('cashier.visa.index', ['paid' => 1]))->assertSee(route('cashier.visa.show', $file->id), false);
});

test('the visa agent cannot open the cashier desk, nor record a payment on the file', function () {
    $agent = vpfAgent();
    $file = vpfFile($agent);

    $this->actingAs($agent)->get(route('cashier.visa.index'))->assertRedirect();
    $this->actingAs($agent)->post(route('visa.applications.payments', $file), ['amount' => 100])->assertSessionHas('error');
    $this->actingAs($agent)->get(route('visa.applications.show', $file))->assertSee('Print Booking Agreement');
    expect((float) $file->fresh()->amount_paid)->toBe(0.0);
});

test('Step 0 of the wizard no longer lists the blank booking agreement and consent', function () {
    $this->actingAs(vpfAgent())->get(route('visa.visit.file'))->assertOk()
        ->assertDontSee(route('visa.agreement-blank'), false)->assertDontSee(route('visa.consent-blank'), false);
});
