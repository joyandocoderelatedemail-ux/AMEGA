<?php

use App\Models\CorporateAccount;
use App\Models\CorporatePayment;
use App\Models\TicketBooking;
use App\Models\User;
use App\Support\CorporateReceivables;
use App\Support\TicketNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function arCompany(array $credit = []): CorporateAccount
{
    $company = CorporateAccount::create(['company_name' => 'Acme Corp '.fake()->unique()->numerify('###'), 'contact_email' => 'ap@acme.test']);
    $company->forceFill($credit + ['credit_enabled' => true, 'credit_limit' => 50000, 'credit_terms_days' => 30])->save();

    return $company;
}

/** An approved, unpaid booking under the company. */
function arBooking(CorporateAccount $company, float $total = 10000, array $overrides = []): TicketBooking
{
    $agent = $overrides['agent'] ?? User::factory()->create(['role' => 'ticketing']);
    unset($overrides['agent']);

    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-AR-'.fake()->unique()->numerify('#####'),
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => today()->addMonth(),
        'total_passengers' => 1,
        'contact_name' => 'Maria Clara',
        'contact_email' => 'maria@acme.test',
        'total_amount' => $total,
        'approval_status' => TicketBooking::APPROVAL_APPROVED,
        'corporate_account_id' => $company->id,
        'created_by' => $agent->id,
    ], $overrides));
}

function arCashier(): User
{
    return User::factory()->create(['role' => 'cashier']);
}

test('only an admin can set a company credit account, and the page shows it', function () {
    $company = CorporateAccount::create(['company_name' => 'Globex Inc']);
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'agent', 'permissions' => ['users.view', 'users.edit', 'users.create']]);
    $payload = ['credit_enabled' => '1', 'credit_limit' => '200000', 'credit_terms_days' => '45', 'credit_interest_rate' => '1.5', 'credit_grace_days' => '5'];

    $this->actingAs($agent)->post(route('admin.corporates.credit', $company), $payload);
    expect($company->fresh()->credit_enabled)->toBeFalse();

    $this->actingAs($admin)->post(route('admin.corporates.credit', $company), $payload)->assertRedirect(route('admin.corporates.show', $company));

    expect($company->fresh())
        ->credit_enabled->toBeTrue()
        ->credit_terms_days->toBe(45)
        ->credit_grace_days->toBe(5)
        ->and((float) $company->fresh()->credit_limit)->toBe(200000.0)
        ->and((float) $company->fresh()->credit_interest_rate)->toBe(1.5);

    $this->actingAs($admin)->get(route('admin.corporates.show', $company))->assertOk()
        ->assertSee('Credit Account (A/R)')->assertSee('Credit on')->assertSee('Save credit account');
});

test('a new credit account defaults to Net 30 and the terms are required', function () {
    $company = CorporateAccount::create(['company_name' => 'Globex Inc']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.corporates.show', $company))->assertSee('name="credit_terms_days"', false)->assertSee('value="30"', false);
    $this->actingAs($admin)->post(route('admin.corporates.credit', $company), ['credit_enabled' => '1', 'credit_terms_days' => ''])->assertSessionHasErrors('credit_terms_days');
});

test('the cashier charges an approved booking to the company account, and it can then be issued unpaid', function () {
    $company = arCompany();
    $ticket = arBooking($company, 12000);
    $cashier = arCashier();

    $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->assertOk()
        ->assertSee('Corporate account: '.$company->company_name)->assertSee('to company account');

    expect($ticket->isPaidOrCharged())->toBeFalse();

    $this->actingAs($cashier)->post(route('cashier.payments.charge', $ticket))->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->isChargedToAccount())->toBeTrue()
        ->and((float) $ticket->ar_amount)->toBe(12000.0)
        ->and($ticket->ar_due_date->toDateString())->toBe(today()->addDays(30)->toDateString())
        ->and($ticket->charged_to_account_by)->toBe($cashier->id)
        ->and($ticket->isPaidOrCharged())->toBeTrue()
        ->and($ticket->isFullyPaid())->toBeFalse();

    // It leaves the cashier's "to collect" list and joins the company's account.
    expect(TicketBooking::awaitingPayment()->whereKey($ticket->id)->exists())->toBeFalse()
        ->and(CorporateReceivables::outstanding($company))->toBe(12000.0);

    // Issuing no longer stops at "full payment is required".
    $agent = User::find($ticket->created_by);
    $response = $this->actingAs($agent)->post(route('ticketing.tickets.issue', $ticket), []);
    expect(session('error'))->not->toBe('Full payment is required before a ticket can be issued.');

    // The agent's bell says it is ready.
    expect(collect(TicketNotifications::forAgent($agent)['latest'])->pluck('title')->implode(' '))->toContain("charged to the company's account");
});

test('an uncharged unpaid booking still cannot be issued', function () {
    $ticket = arBooking(arCompany());
    $agent = User::find($ticket->created_by);

    $this->actingAs($agent)->post(route('ticketing.tickets.issue', $ticket), [])
        ->assertSessionHas('error', 'Full payment is required before a ticket can be issued.');
});

test('a booking cannot be charged without a credit account, before approval, or twice', function () {
    $cashier = arCashier();

    $noCredit = arBooking(arCompany(['credit_enabled' => false]));
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $noCredit))->assertSessionHas('error');
    expect($noCredit->fresh()->isChargedToAccount())->toBeFalse();

    $pending = arBooking(arCompany(), 5000, ['approval_status' => TicketBooking::APPROVAL_PENDING]);
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $pending))->assertSessionHas('error');
    expect($pending->fresh()->isChargedToAccount())->toBeFalse();

    $individual = arBooking(arCompany(), 5000, ['corporate_account_id' => null]);
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $individual))->assertSessionHas('error');

    $ok = arBooking(arCompany());
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $ok))->assertSessionHas('success');
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $ok))->assertSessionHas('error');
});

test('going over the credit limit takes an admin who ticks the override', function () {
    $company = arCompany(['credit_limit' => 15000]);
    $cashier = arCashier();
    $admin = User::factory()->create(['role' => 'admin']);

    $first = arBooking($company, 10000);
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $first))->assertSessionHas('success');

    $second = arBooking($company, 8000);
    expect(CorporateReceivables::overLimitBy($second))->toBe(3000.0);

    $this->actingAs($cashier)->get(route('cashier.payments.show', $second))->assertSee('over its credit limit')->assertSee('Only an admin can approve going over the limit');
    $this->actingAs($cashier)->post(route('cashier.payments.charge', $second), ['override_limit' => '1'])->assertSessionHas('error');
    expect($second->fresh()->isChargedToAccount())->toBeFalse();

    $this->actingAs($admin)->post(route('cashier.payments.charge', $second))->assertSessionHas('error');
    expect($second->fresh()->isChargedToAccount())->toBeFalse();

    $this->actingAs($admin)->post(route('cashier.payments.charge', $second), ['override_limit' => '1'])->assertSessionHas('success');
    expect($second->fresh()->isChargedToAccount())->toBeTrue();
});

test('a company with no limit is never over it', function () {
    $company = arCompany(['credit_limit' => null]);
    $ticket = arBooking($company, 900000);

    expect(CorporateReceivables::available($company))->toBeNull()->and(CorporateReceivables::overLimitBy($ticket))->toBe(0.0);
});

test('a company payment clears its unpaid tickets oldest due first', function () {
    $company = arCompany();
    $cashier = arCashier();
    $old = arBooking($company, 10000);
    $new = arBooking($company, 6000);
    CorporateReceivables::charge($old, $cashier);
    CorporateReceivables::charge($new, $cashier);
    $old->forceFill(['ar_due_date' => today()->subDays(10)])->save();

    $this->actingAs($cashier)->post(route('cashier.receivables.store', $company), [
        'amount' => '12000', 'method' => 'cheque', 'reference' => 'CHK-001', 'acknowledged' => '1',
    ])->assertRedirect(route('cashier.receivables.show', $company))->assertSessionHas('success');

    $payment = CorporatePayment::firstOrFail();
    expect((float) $payment->amount)->toBe(12000.0)->and($payment->entries)->toHaveCount(2)
        ->and($old->fresh()->isFullyPaid())->toBeTrue()
        ->and((float) $new->fresh()->amount_paid)->toBe(2000.0)
        ->and(CorporateReceivables::outstanding($company))->toBe(4000.0)
        // A cheque goes on the ticket's own ledger as "other", with the company payment's number.
        ->and($old->payments()->first()->method)->toBe('other')
        ->and($old->payments()->first()->note)->toBe('Company payment '.$payment->number());
});

test('a company payment cannot be more than is owed, and needs the acknowledgement', function () {
    $company = arCompany();
    $cashier = arCashier();
    CorporateReceivables::charge(arBooking($company, 5000), $cashier);

    $this->actingAs($cashier)->post(route('cashier.receivables.store', $company), ['amount' => '5000.01', 'method' => 'cash', 'acknowledged' => '1'])
        ->assertSessionHas('error');
    $this->actingAs($cashier)->post(route('cashier.receivables.store', $company), ['amount' => '1000', 'method' => 'cash'])
        ->assertSessionHasErrors('acknowledged');
    $this->actingAs($cashier)->post(route('cashier.receivables.store', $company), ['amount' => '1000', 'method' => 'card', 'acknowledged' => '1'])
        ->assertSessionHasErrors('method');

    expect(CorporatePayment::count())->toBe(0)->and(CorporateReceivables::outstanding($company))->toBe(5000.0);
});

test('the aging report sorts what is owed by how late it is', function () {
    $company = arCompany(['credit_limit' => null]);
    $cashier = arCashier();
    foreach ([[1000, 5], [2000, -10], [3000, -45], [4000, -75], [5000, -120]] as [$amount, $dueInDays]) {
        $ticket = arBooking($company, $amount);
        CorporateReceivables::charge($ticket, $cashier);
        $ticket->forceFill(['ar_due_date' => today()->addDays($dueInDays)])->save();
    }
    // Cancelled bookings are off the account.
    $cancelled = arBooking($company, 9999);
    CorporateReceivables::charge($cancelled, $cashier);
    $cancelled->forceFill(['status' => TicketBooking::STATUS_CANCELLED])->save();

    expect(CorporateReceivables::aging($company))->toBe(['current' => 1000.0, 'd30' => 2000.0, 'd60' => 3000.0, 'd90' => 4000.0, 'over' => 5000.0, 'total' => 15000.0]);

    $this->actingAs($cashier)->get(route('cashier.receivables.index'))->assertOk()
        ->assertSee('Corporate A/R')->assertSee($company->company_name)->assertSee('₱15,000.00')->assertSee('Over 90 days');
    $this->actingAs($cashier)->get(route('cashier.receivables.show', $company))->assertOk()
        ->assertSee('Record a company payment')->assertSee('120 days overdue');
});

test('the monthly statement carries the balance forward and lists charges and payments', function () {
    $company = arCompany(['credit_interest_rate' => 1.5, 'credit_grace_days' => 5]);
    $cashier = arCashier();

    // Charged last month, part-paid last month.
    $last = arBooking($company, 10000);
    CorporateReceivables::charge($last, $cashier);
    $last->forceFill(['charged_to_account_at' => now()->subMonth()->startOfMonth()->addDays(2)])->save();
    CorporateReceivables::receive($company, 4000, 'bank_transfer', $cashier, 'BT-1', now()->subMonth()->startOfMonth()->addDays(10));

    // Charged this month, and a payment this month.
    $this_ = arBooking($company, 7000);
    CorporateReceivables::charge($this_, $cashier);
    CorporateReceivables::receive($company, 1000, 'cash', $cashier);

    $statement = CorporateReceivables::statement($company, now());

    expect($statement['opening'])->toBe(6000.0)
        ->and($statement['charges'])->toHaveCount(1)
        ->and((float) $statement['payments']->sum('amount'))->toBe(1000.0)
        ->and($statement['closing'])->toBe(12000.0)
        ->and($statement['closing'])->toBe(CorporateReceivables::outstanding($company));

    $this->actingAs($cashier)->get(route('cashier.receivables.statement', ['corporate' => $company, 'month' => now()->format('Y-m')]))->assertOk()
        ->assertSee('STATEMENT OF ACCOUNT')->assertSee($company->company_name)->assertSee('Balance brought forward')
        ->assertSee('₱6,000.00')->assertSee('₱12,000.00')->assertSee('Net 30 days')->assertSee('1.5% per month')->assertSee('grace period of 5 days');
});

test('only the cashier and admins open Corporate A/R', function () {
    $company = arCompany();

    $this->actingAs(User::factory()->create(['role' => 'ticketing']))->get(route('cashier.receivables.index'))->assertRedirect();
    $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('cashier.receivables.show', $company))->assertOk();
});

test('the account history lists charges, payments and credit-term changes with a running balance', function () {
    $company = arCompany();
    $admin = User::factory()->create(['role' => 'admin']);
    $cashier = arCashier();

    $this->actingAs($admin)->post(route('admin.corporates.credit', $company), ['credit_enabled' => '1', 'credit_limit' => '80000', 'credit_terms_days' => '45']);

    $first = arBooking($company, 10000);
    $second = arBooking($company, 6000);
    CorporateReceivables::charge($first, $cashier);
    CorporateReceivables::charge($second, $cashier);
    $payment = CorporateReceivables::receive($company, 12000, 'cheque', $cashier, 'CHK-77');

    $history = CorporateReceivables::history($company);

    expect($history->pluck('kind')->all())->toBe(['terms', 'charge', 'charge', 'payment'])
        ->and($history->pluck('balance')->all())->toBe([null, 10000.0, 16000.0, 4000.0])
        ->and($history->last()['applied'])->toHaveCount(2)
        ->and($history->first()['detail'])->toContain('Net 30 days → Net 45 days')
        ->and($history->last()['balance'])->toBe(CorporateReceivables::outstanding($company));

    $this->actingAs($cashier)->get(route('cashier.receivables.history', $company))->assertOk()
        ->assertSee('A/R history')->assertSee($payment->number())->assertSee('CHK-77')
        ->assertSee($first->booking_reference)->assertSee('₱4,000.00');

    // The admin's company page links to it; staff who manage clients but are not cashiers do not see the link.
    $this->actingAs($admin)->get(route('admin.corporates.show', $company))->assertOk()
        ->assertSee(route('cashier.receivables.history', $company));
    $agent = User::factory()->create(['role' => 'agent', 'permissions' => ['users.view']]);
    $this->actingAs($agent)->get(route('admin.corporates.show', $company))
        ->assertDontSee(route('cashier.receivables.history', $company));
});

test('the admin approval page and list show the company a corporate booking is under', function () {
    $company = arCompany(['credit_limit' => 50000]);
    $company->forceFill(['tin' => '123-456-789-000', 'contact_person' => 'Jose Rizal', 'contact_position' => 'HR Manager', 'address' => 'Makati City'])->save();
    $ticket = arBooking($company, 10000, ['approval_status' => TicketBooking::APPROVAL_PENDING, 'approval_requested_at' => now()]);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.ticket-approvals.show', $ticket))->assertOk()
        ->assertSee('Corporate booking')->assertSee($company->company_name)
        ->assertSee('123-456-789-000')->assertSee('Jose Rizal (HR Manager)')->assertSee('Makati City')
        ->assertSee('Net 30 days')->assertSee('₱50,000.00 credit left')
        ->assertSee(route('admin.corporates.show', $company));

    $this->actingAs($admin)->get(route('admin.ticket-approvals.index'))->assertOk()->assertSee($company->company_name);
    $this->actingAs($admin)->get(route('admin.ticket-approvals.index', ['search' => $company->company_name]))->assertOk()->assertSee($ticket->booking_reference);
});
