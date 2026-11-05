<?php

use App\Models\SrrvApplication;
use App\Models\User;
use App\Support\AdminNavigation;
use App\Support\AdminNotifications;
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
