<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketApprovalRequestedNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Agent submits → admin approves (acknowledging) or returns → cashier records
 * payment → agent issues the ticket.
 */
function submittedBooking(User $agent, array $overrides = []): TicketBooking
{
    $ticket = TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'created_by' => $agent->id,
        'travel_type' => 'domestic',
        'trip_type' => 'one_way',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(30)->toDateString(),
        'total_passengers' => 1,
        'adults_count' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 6200,
        'approval_status' => TicketBooking::APPROVAL_PENDING,
        'approval_requested_at' => now(),
    ], $overrides));

    $ticket->passengers()->create([
        'passenger_number' => 1,
        'passenger_type' => 'adult',
        'nationality_type' => 'filipino',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'passport_expiry_date' => now()->addYears(3)->toDateString(),
    ]);

    return $ticket;
}

test('a submitted booking waits for an admin: no payment, no issuing', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = submittedBooking($agent);

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee('Pending approval')
        ->assertSee('Sent to the admin for approval')
        ->assertDontSee('Record payment');

    // Agents never record payments now; the cashier waits for the approval.
    $this->actingAs($agent)->post(route('ticketing.tickets.payment', $ticket), ['amount' => 100, 'method' => 'cash'])
        ->assertSessionHas('error');
    $this->actingAs(cashier())->post(route('cashier.payments.store', $ticket), ['cashier_acknowledged' => 1, 'amount' => 100, 'method' => 'cash'])
        ->assertSessionHas('error');
    $this->actingAs($agent)->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => 1])
        ->assertSessionHas('error');

    expect($ticket->fresh()->payments()->count())->toBe(0);
});

test('admins are emailed and the booking is on the approvals page', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = submittedBooking($agent);

    // Resubmitting after an edit tells the admins (a fresh submission does the same from the wizard).
    $ticket->update(['approval_status' => TicketBooking::APPROVAL_APPROVED]);
    $this->actingAs($agent)->put(route('ticketing.tickets.update', $ticket), [
        'travel_type' => 'domestic', 'trip_type' => 'one_way', 'origin' => 'Manila (MNL)', 'destination' => 'Davao (DVO)',
        'departure_date' => $ticket->departure_date->toDateString(),
        'contact_name' => 'Juan Dela Cruz', 'contact_email' => 'juan@example.com', 'contact_phone' => '09171234567',
        'passengers' => [['id' => $ticket->passengers->first()->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'passport_expiry_date' => now()->addYears(3)->toDateString()]],
    ])->assertSessionHasNoErrors();

    expect($ticket->fresh()->approval_status)->toBe(TicketBooking::APPROVAL_PENDING);
    Notification::assertSentTo($admin, TicketApprovalRequestedNotification::class);

    $this->actingAs($admin)->get(route('admin.ticket-approvals.index'))
        ->assertOk()->assertSee($ticket->booking_reference)->assertSee('Review');

    $this->actingAs($admin)->get(route('admin.ticket-approvals.show', $ticket))
        ->assertOk()
        ->assertSee('Official Ticket Booking Confirmation')
        ->assertSee('I acknowledge that I have reviewed all the information in this booking')
        ->assertDontSee('Save Restrictions');
});

test('approving needs the admin acknowledgement and records who approved', function () {
    $admin = User::factory()->create(['role' => 'admin', 'name' => 'Ana Admin']);
    $ticket = submittedBooking(User::factory()->create(['role' => 'ticketing']));

    $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket))
        ->assertSessionHasErrors('admin_acknowledged');
    expect($ticket->fresh()->isAwaitingApproval())->toBeTrue();

    $this->actingAs($admin)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1])
        ->assertRedirect(route('admin.ticket-approvals.index'));

    $ticket->refresh();
    expect($ticket->isApproved())->toBeTrue()
        ->and($ticket->reviewed_by)->toBe($admin->id)
        ->and($ticket->reviewed_at)->not->toBeNull();
});

test('a returned booking shows the reason and goes back for approval when corrected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = submittedBooking($agent);

    $this->actingAs($admin)->post(route('admin.ticket-approvals.reject', $ticket), [])->assertSessionHasErrors('review_note');

    $this->actingAs($admin)->post(route('admin.ticket-approvals.reject', $ticket), ['review_note' => 'Wrong destination airport.'])
        ->assertRedirect();
    expect($ticket->fresh()->isRejected())->toBeTrue();

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))
        ->assertSee('Returned by admin')
        ->assertSee('Wrong destination airport.')
        ->assertSee('Correct and resubmit');
});

test('the cashier records payment on approved bookings, then the agent can issue', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $otherAgent = User::factory()->create(['role' => 'ticketing']);
    $cashier = cashier();
    $approved = submittedBooking($agent, ['approval_status' => TicketBooking::APPROVAL_APPROVED, 'reviewed_at' => now()]);
    $pending = submittedBooking($otherAgent);

    // The cashier sees every agent's approved bookings, and only those.
    $this->actingAs($cashier)->get(route('cashier.dashboard'))
        ->assertOk()
        ->assertSee($approved->booking_reference)
        ->assertDontSee($pending->booking_reference);

    $this->actingAs($cashier)->get(route('cashier.payments.show', $approved))
        ->assertOk()->assertSee('Record payment')->assertSee('Official Ticket Booking Confirmation');

    $this->actingAs($cashier)->post(route('cashier.payments.store', $approved), ['cashier_acknowledged' => 1, 'amount' => 6200, 'method' => 'cash'])
        ->assertRedirect(route('cashier.payments.show', $approved))
        ->assertSessionHas('success');

    $approved->refresh();
    expect($approved->isFullyPaid())->toBeTrue()
        ->and($approved->payments()->first()->received_by)->toBe($cashier->id)
        // Approved and paid: only the passenger documents (none uploaded here) still stand between it and issuing.
        ->and($approved->missingDocuments()->isNotEmpty() || $approved->canBeIssued())->toBeTrue();

    $payment = $approved->payments()->first();
    $this->actingAs($cashier)->get(route('cashier.payments.receipt', [$approved, $payment]))->assertOk();

    $this->actingAs($cashier)->get(route('cashier.paid'))->assertSee($approved->booking_reference);
    $this->actingAs($agent)->get(route('ticketing.tickets.show', $approved))
        ->assertSee('Fully paid')
        ->assertDontSee('Waiting for admin approval');
});

test('each portal keeps to its own people', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $cashier = cashier();
    $ticket = submittedBooking($agent);

    $this->actingAs($agent)->get(route('cashier.dashboard'))->assertRedirect();
    $this->actingAs($agent)->get(route('admin.ticket-approvals.index'))->assertRedirect();
    $this->actingAs($cashier)->get(route('ticketing.dashboard'))->assertRedirect();
    $this->actingAs($cashier)->get(route('admin.ticket-approvals.show', $ticket))->assertRedirect();
    $this->actingAs($cashier)->post(route('admin.ticket-approvals.approve', $ticket), ['admin_acknowledged' => 1]);
    expect($ticket->fresh()->isAwaitingApproval())->toBeTrue();

    // A cashier lands on the cashier portal.
    expect($cashier->staffHomeRoute())->toBe('cashier.dashboard')
        ->and($cashier->seesOnlyOwnFiles())->toBeFalse();
});

test('admins can create cashier accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->get(route('admin.agents.create'))->assertOk()->assertSee('Cashier');
});

test('the wizard submits for approval instead of issuing', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);

    $this->actingAs($agent)->get(route('ticketing.tickets.create'))
        ->assertOk()
        ->assertSee('Submit for Approval')
        ->assertDontSee('Confirm &amp; Issue Domestic Ticket', false);
});

test('the cashier list shows the latest approval first', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $older = submittedBooking($agent, ['approval_status' => TicketBooking::APPROVAL_APPROVED, 'reviewed_at' => now()->subDay()]);
    $legacy = submittedBooking($agent, ['approval_status' => TicketBooking::APPROVAL_APPROVED, 'reviewed_at' => null]);
    $latest = submittedBooking($agent, ['approval_status' => TicketBooking::APPROVAL_APPROVED, 'reviewed_at' => now()]);

    $this->actingAs(cashier())->get(route('cashier.dashboard'))
        ->assertSeeInOrder([$latest->booking_reference, $older->booking_reference, $legacy->booking_reference]);
});

test('the cashier must acknowledge before a payment is recorded', function () {
    $cashier = User::factory()->create(['role' => 'cashier', 'name' => 'Cora Cashier']);
    $ticket = submittedBooking(User::factory()->create(['role' => 'ticketing']), ['approval_status' => TicketBooking::APPROVAL_APPROVED, 'reviewed_at' => now()]);

    $this->actingAs($cashier)->get(route('cashier.payments.show', $ticket))
        ->assertSee('I acknowledge that I have received the amount entered above')
        ->assertSee('<strong>Cora Cashier</strong>', false)
        ->assertSee('name="cashier_acknowledged"', false);

    $this->actingAs($cashier)->post(route('cashier.payments.store', $ticket), ['amount' => 6200, 'method' => 'cash'])
        ->assertSessionHasErrors('cashier_acknowledged');
    expect($ticket->payments()->count())->toBe(0);

    $this->actingAs($cashier)->post(route('cashier.payments.store', $ticket), ['amount' => 6200, 'method' => 'cash', 'cashier_acknowledged' => 1])
        ->assertSessionHas('success');
    expect($ticket->fresh()->isFullyPaid())->toBeTrue();
});
