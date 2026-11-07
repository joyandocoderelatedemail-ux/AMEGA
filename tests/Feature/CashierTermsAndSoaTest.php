<?php

use App\Models\TicketBooking;
use App\Models\TicketPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function termsTicket(User $agent, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'travel_type' => 'domestic',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(40),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ], $overrides));
}

function cashierAndTicket(array $overrides = []): array
{
    $agent = User::factory()->create(['role' => 'ticketing']);
    $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Cora Cashier']);

    return [$cashier, termsTicket($agent, $overrides)];
}

test('a card payment carries a 4% charge on top, and only the amount counts against the balance', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $card = $ticket->receivePayment(1000, 'card', $cashier);
    $cash = $ticket->receivePayment(500, 'cash', $cashier);

    expect((float) $card->card_surcharge)->toBe(40.0)
        ->and($card->totalCollected())->toBe(1040.0)
        ->and($card->hasSurcharge())->toBeTrue()
        ->and((float) $cash->card_surcharge)->toBe(0.0)
        ->and($cash->hasSurcharge())->toBeFalse()
        // Balance: 10,000 - 1,000 - 500, the ₱40 charge is not credited.
        ->and((float) $ticket->fresh()->amount_paid)->toBe(1500.0)
        ->and($ticket->fresh()->balanceDue())->toBe(8500.0)
        ->and(TicketPayment::surchargeFor('card', 333.33))->toBe(13.33)
        ->and(TicketPayment::surchargeFor('gcash', 1000))->toBe(0.0);
});

test('payments are numbered automatically and the one that clears the balance is the final payment', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $first = $ticket->receivePayment(3000, 'cash', $cashier);
    $second = $ticket->receivePayment(3000, 'gcash', $cashier);
    $third = $ticket->receivePayment(2000, 'cash', $cashier);
    $last = $ticket->receivePayment(2000, 'bank_transfer', $cashier);

    expect($first->sequenceLabel())->toBe('1st payment')
        ->and($second->sequenceLabel())->toBe('2nd payment')
        ->and($third->sequenceLabel())->toBe('3rd payment')
        ->and($last->sequenceLabel())->toBe('Final payment')
        ->and($last->is_final)->toBeTrue()
        ->and($third->is_final)->toBeFalse();

    [, $one] = cashierAndTicket();
    $paidAtOnce = $one->receivePayment(10000, 'cash', $cashier);
    expect($paidAtOnce->sequenceLabel())->toBe('Full payment');

    expect(collect([1, 2, 3, 4, 11, 12, 13, 21, 22, 23, 101, 111])->map(fn ($n) => TicketPayment::ordinal($n))->all())
        ->toBe(['1st', '2nd', '3rd', '4th', '11th', '12th', '13th', '21st', '22nd', '23rd', '101st', '111th']);
});

test('a refund is not numbered as a payment', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $ticket->receivePayment(4000, 'cash', $cashier);
    $ticket->cancel($cashier, 'Client cancelled');
    $refund = $ticket->fresh()->refundPayment(1000, 'cash', $cashier);

    expect($refund->sequenceLabel())->toBe('Refund')->and($refund->payment_number)->toBeNull()->and($refund->hasSurcharge())->toBeFalse();
});

test('the cashier sets terms and the schedule follows: equal payments, the last one final', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 3, 'terms_days' => 30])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $ticket = $ticket->fresh();
    expect($ticket->hasTerms())->toBeTrue()
        ->and($ticket->terms_set_by)->toBe($cashier->id)
        ->and($ticket->terms_start->isToday())->toBeTrue();

    $schedule = $ticket->paymentSchedule();
    expect($schedule)->toHaveCount(3)
        ->and(array_column($schedule, 'label'))->toBe(['1st payment', '2nd payment', 'Final payment'])
        ->and(array_column($schedule, 'amount'))->toBe([3333.33, 3333.33, 3333.34])
        ->and(array_sum(array_column($schedule, 'amount')))->toBe(10000.0)
        ->and($schedule[0]['due']->isToday())->toBeTrue()
        ->and($schedule[1]['due']->toDateString())->toBe(today()->addDays(30)->toDateString())
        ->and($schedule[2]['due']->toDateString())->toBe(today()->addDays(60)->toDateString())
        ->and(array_column($schedule, 'status'))->toBe(['upcoming', 'upcoming', 'upcoming']);
});

test('payments fill the schedule in order, and a late one reads overdue', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $ticket->forceFill(['terms_payments' => 3, 'terms_days' => 30, 'terms_start' => today()->subDays(45)])->save();

    // 45 days in: the 1st (day 0) is due and unpaid, the 2nd (day 30) too, the final (day 60) is ahead.
    expect(array_column($ticket->fresh()->paymentSchedule(), 'status'))->toBe(['overdue', 'overdue', 'upcoming'])
        ->and($ticket->fresh()->overdueInstallments())->toBe(2);

    $ticket->receivePayment(3333.33, 'cash', $cashier);
    $schedule = $ticket->fresh()->paymentSchedule();
    expect(array_column($schedule, 'status'))->toBe(['paid', 'overdue', 'upcoming'])
        ->and($ticket->fresh()->nextInstallment()['label'])->toBe('2nd payment')
        ->and($ticket->fresh()->suggestedPayment())->toBe(3333.33);

    $ticket->receivePayment(1000, 'cash', $cashier);
    $schedule = $ticket->fresh()->paymentSchedule();
    expect($schedule[1]['paid'])->toBe(1000.0)->and($schedule[1]['status'])->toBe('overdue')
        ->and($ticket->fresh()->suggestedPayment())->toBe(2333.33);

    $ticket->receivePayment(2333.33, 'cash', $cashier);
    $ticket->receivePayment(3333.34, 'cash', $cashier);
    expect(array_column($ticket->fresh()->paymentSchedule(), 'status'))->toBe(['paid', 'paid', 'paid'])
        ->and($ticket->fresh()->nextInstallment())->toBeNull()
        ->and($ticket->fresh()->overdueInstallments())->toBe(0);
});

test('terms need at least two payments and a number of days, and cannot be set once paid in full', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 1, 'terms_days' => 30])
        ->assertSessionHasErrors('terms_payments');
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 3, 'terms_days' => 0])
        ->assertSessionHasErrors('terms_days');
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 13, 'terms_days' => 30])
        ->assertSessionHasErrors('terms_payments');
    expect($ticket->fresh()->hasTerms())->toBeFalse();

    $ticket->receivePayment(10000, 'cash', $cashier);
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 3, 'terms_days' => 30])
        ->assertSessionHas('error');
    expect($ticket->fresh()->hasTerms())->toBeFalse();
});

test('the cashier can remove the terms', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 4, 'terms_days' => 14])->assertSessionHasNoErrors();
    expect($ticket->fresh()->hasTerms())->toBeTrue();

    $this->actingAs($cashier)->post(route('cashier.payments.terms.clear', $ticket))->assertSessionHas('success');

    expect($ticket->fresh()->hasTerms())->toBeFalse()->and($ticket->fresh()->paymentSchedule())->toBe([]);
});

test('terms are for the cashier desk only', function () {
    // Even the agent who made the booking cannot set terms or print the statement from the ticketing desk.
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = termsTicket($agent);

    $this->actingAs($agent)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 3, 'terms_days' => 30])->assertRedirect();
    $this->actingAs($agent)->get(route('cashier.payments.soa', $ticket))->assertRedirect();

    expect($ticket->fresh()->hasTerms())->toBeFalse();
});

test('recording a card payment at the cashier saves the 4% charge and labels the payment', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $this->actingAs($cashier)->post(route('cashier.payments.store', $ticket), [
        'amount' => 2500, 'method' => 'card', 'received_at' => today()->toDateString(), 'cashier_acknowledged' => 1,
    ])->assertSessionHasNoErrors()->assertRedirect(route('cashier.payments.show', $ticket));

    $payment = $ticket->fresh()->payments->first();
    expect((float) $payment->amount)->toBe(2500.0)->and((float) $payment->card_surcharge)->toBe(100.0)->and($payment->sequenceLabel())->toBe('1st payment');

    $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->assertOk()
        ->assertSee('1st payment')->assertSee('₱100.00 card charge (4%)', false)->assertSee('collected ₱2,600.00', false)
        // Only the Statement of Account is offered; there is no Receipt button.
        ->assertDontSee('href="'.route('cashier.payments.receipt', [$ticket, $payment]).'"', false)
        ->assertSee(route('cashier.payments.soa', $ticket), false);
});

test('the cashier page shows the terms, the schedule and the next amount due', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 4, 'terms_days' => 15]);

    $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->assertOk()
        ->assertSee('Terms')->assertSee('4 payments, 15 days apart', false)
        ->assertSee('1st payment')->assertSee('2nd payment')->assertSee('3rd payment')->assertSee('Final payment')
        ->assertSee('Next due:', false)->assertSee('₱2,500.00', false)
        ->assertSee('value="2500.00"', false)
        ->assertSee('Statement of Account')->assertSee(route('cashier.payments.soa', $ticket), false)
        ->assertSee('Card payment: 4% charge', false);
});

test('the Statement of Account prints the booking, its schedule, every payment and the balance due', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['terms_payments' => 2, 'terms_days' => 30]);
    $ticket = $ticket->fresh();
    $ticket->receivePayment(5000, 'card', $cashier);
    $ticket->receivePayment(1000, 'cash', $cashier);

    $this->actingAs($cashier)->get(route('cashier.payments.soa', $ticket))->assertOk()
        ->assertSee('Statement of Account')
        ->assertSee('Endless Possibilities in Travel and Tourism')
        ->assertSee('Astrofield Building, Balibago', false)
        ->assertSee('SOA-'.$ticket->booking_reference)
        ->assertSee('Juan Dela Cruz')->assertSee('Manila (MNL) to Cebu (CEB)')
        ->assertSee('₱10,000.00', false)           // booking total
        ->assertSee('₱6,000.00', false)            // paid to date (the card charge is not part of it)
        ->assertSee('₱4,000.00', false)            // balance due
        ->assertSee('Payment schedule')->assertSee('1st payment')->assertSee('Final payment')
        ->assertSee('OR-')->assertSee('₱200.00', false)   // 4% of 5,000
        ->assertSee('₱6,200.00', false)            // total received incl. the card charge
        ->assertSee('Cora Cashier');
});

test('a Statement of Account can be printed for a booking with no payments or terms', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $this->actingAs($cashier)->get(route('cashier.payments.soa', $ticket))->assertOk()
        ->assertSee('No payments received yet.')->assertDontSee('Payment schedule');
});

test('the receipt names the payment and shows the card charge', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $payment = $ticket->receivePayment(4000, 'card', $cashier);

    $this->actingAs($cashier)->get(route('cashier.payments.receipt', [$ticket, $payment]))->assertOk()
        ->assertSee('1st payment')->assertSee('Card charge (4%)', false)->assertSee('₱160.00', false)->assertSee('Total collected')->assertSee('₱4,160.00', false);

    $cash = $ticket->receivePayment(1000, 'cash', $cashier);
    $this->actingAs($cashier)->get(route('cashier.payments.receipt', [$ticket, $cash]))->assertOk()
        ->assertSee('2nd payment')->assertDontSee('Card charge (4%)', false);
});

test('the Payment card has a Full payment / Terms selector above the payment form', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $html = $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->assertOk()
        ->assertSee('Payment type')->assertSee('Full payment')->assertDontSee('Switch to full payment', false)->getContent();

    // Fresh booking: Full payment is selected, and the selector sits above where the amount is entered.
    expect($html)->toContain("mode: 'full'")
        ->and(strpos($html, 'Payment type'))->toBeLessThan(strpos($html, 'Amount received'))
        // One card now: the old separate Terms card is gone.
        ->and(substr_count($html, 'id="terms"'))->toBe(1);

    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'terms', 'terms_payments' => 3, 'terms_days' => 30]);

    $html = $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->getContent();

    expect($html)->toContain("mode: 'terms'")->toContain('Switch to full payment');
});

test('choosing Full payment takes the terms off', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'terms', 'terms_payments' => 4, 'terms_days' => 14])->assertSessionHasNoErrors();
    expect($ticket->fresh()->hasTerms())->toBeTrue();

    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'full'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Full payment: the client pays the whole balance in one go.');

    expect($ticket->fresh()->hasTerms())->toBeFalse()->and($ticket->fresh()->suggestedPayment())->toBe(10000.0);

    // Choosing it on a booking that never had terms is harmless.
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'full'])->assertSessionHasNoErrors();
});

test('Terms still needs the number of payments and days, and the type must be one of the two', function () {
    [$cashier, $ticket] = cashierAndTicket();

    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'terms'])
        ->assertSessionHasErrors(['terms_payments', 'terms_days']);
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'installment', 'terms_payments' => 3, 'terms_days' => 30])
        ->assertSessionHasErrors('payment_type');

    expect($ticket->fresh()->hasTerms())->toBeFalse();
});

test('a booking paid in full shows its payment type without the selector', function () {
    [$cashier, $ticket] = cashierAndTicket();
    $this->actingAs($cashier)->post(route('cashier.payments.terms', $ticket), ['payment_type' => 'terms', 'terms_payments' => 2, 'terms_days' => 30]);
    $ticket = $ticket->fresh();
    $ticket->receivePayment(5000, 'cash', $cashier);
    $ticket->receivePayment(5000, 'cash', $cashier);

    $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))->assertOk()
        ->assertSee('Payment type:')->assertSee('Terms')->assertSee('Final payment')
        ->assertDontSee('Switch to full payment', false);
});
