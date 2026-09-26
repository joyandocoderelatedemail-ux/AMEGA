<?php

use App\Models\TicketBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTicket(array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-DOM-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(20),
        'total_passengers' => 1,
        'contact_name' => 'Juan Dela Cruz',
        'contact_email' => 'juan@example.com',
        'contact_phone' => '09171234567',
        'total_amount' => 10000,
        'status' => TicketBooking::STATUS_PENDING,
    ], $overrides));
}

test('a new booking starts unpaid and cannot be issued', function () {
    $ticket = makeTicket();

    expect($ticket->payment_status)->toBe(TicketBooking::PAYMENT_UNPAID)
        ->and($ticket->canBeIssued())->toBeFalse()
        ->and($ticket->balanceDue())->toBe(10000.0);
});

test('a partial payment confirms the booking but does not make it issuable', function () {
    $ticket = makeTicket();

    $ticket->recordPayment(4000);

    expect($ticket->payment_status)->toBe(TicketBooking::PAYMENT_PARTIAL)
        ->and($ticket->status)->toBe(TicketBooking::STATUS_CONFIRMED)
        ->and($ticket->balanceDue())->toBe(6000.0)
        ->and($ticket->canBeIssued())->toBeFalse()
        ->and($ticket->paid_at)->toBeNull();
});

test('paying in full makes a booking issuable but does not issue it', function () {
    $ticket = makeTicket();

    $ticket->recordPayment(10000);

    expect($ticket->payment_status)->toBe(TicketBooking::PAYMENT_FULL)
        ->and($ticket->balanceDue())->toBe(0.0)
        ->and($ticket->canBeIssued())->toBeTrue()
        // The key rule: paid is not issued.
        ->and($ticket->status)->not->toBe(TicketBooking::STATUS_ISSUED)
        ->and($ticket->issued_at)->toBeNull();
});

test('a booking with no total cannot be settled by paying zero', function () {
    $ticket = makeTicket(['total_amount' => 0]);

    $ticket->recordPayment(0);

    expect($ticket->payment_status)->toBe(TicketBooking::PAYMENT_UNPAID)
        ->and($ticket->canBeIssued())->toBeFalse();
});

test('staff cannot issue a ticket that is not fully paid', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);
    $ticket->recordPayment(5000);

    $response = $this->actingAs($officer)
        ->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1']);

    $response->assertSessionHas('error');
    expect($ticket->fresh()->isIssued())->toBeFalse();
});

test('issuing requires the data privacy consent to be acknowledged', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);
    $ticket->recordPayment(10000);

    $response = $this->actingAs($officer)
        ->post(route('ticketing.tickets.issue', $ticket), []);

    $response->assertSessionHasErrors('data_privacy_consent');
    expect($ticket->fresh()->isIssued())->toBeFalse();
});

test('a fully paid booking can be issued with consent and records who issued it', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);
    $ticket->recordPayment(10000);

    $response = $this->actingAs($officer)
        ->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1']);

    $response->assertSessionHas('success');

    $ticket->refresh();

    expect($ticket->status)->toBe(TicketBooking::STATUS_ISSUED)
        ->and($ticket->issued_at)->not->toBeNull()
        ->and($ticket->issued_by)->toBe($officer->id)
        ->and($ticket->consent_accepted_at)->not->toBeNull()
        ->and($ticket->consent_accepted_by)->toBe($officer->id);
});

test('an issued ticket cannot be issued again or have its payment changed', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);
    $ticket->recordPayment(10000);
    $ticket->markAsIssued($officer);

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.issue', $ticket), ['data_privacy_consent' => '1'])
        ->assertSessionHas('error');

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.payment', $ticket), ['amount_paid' => 1])
        ->assertSessionHas('error');

    expect($ticket->fresh()->amount_paid)->toEqual(10000.00);
});

test('staff can record a payment through the ticket detail page', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);

    $this->actingAs($officer)
        ->post(route('ticketing.tickets.payment', $ticket), ['amount_paid' => 10000])
        ->assertSessionHas('success');

    expect($ticket->fresh()->isFullyPaid())->toBeTrue();
});

test('the detail page gates the issue action behind full payment', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertStatus(200)
        ->assertSee('Full payment is required', false)
        ->assertDontSee('Data Privacy and Consent', false);

    $ticket->recordPayment(10000);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertStatus(200)
        ->assertSee('Data Privacy and Consent', false)
        ->assertSee('Issue ticket', false);
});

test('the printable voucher says what the booking is, so a pending one never reads as a ticket', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);

    $this->actingAs($officer)->get(route('ticketing.tickets.voucher', $ticket))
        ->assertOk()
        ->assertSee('Booking Summary', false)
        ->assertSee('Pending confirmation', false)
        ->assertSee($ticket->booking_reference, false)
        ->assertDontSee('Ticket Voucher', false);

    $ticket->recordPayment(10000);
    $ticket->markAsIssued($officer);

    $this->actingAs($officer)->get(route('ticketing.tickets.voucher', $ticket))
        ->assertOk()
        ->assertSee('Ticket Voucher', false)
        ->assertSee('Ticket issued', false)
        ->assertSee('Issued by', false);
});

test('the detail page prints through the standalone voucher, not the portal screen', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $officer->id]);

    $this->actingAs($officer)->get(route('ticketing.tickets.show', $ticket))
        ->assertOk()
        ->assertSee(route('ticketing.tickets.voucher', ['ticket' => $ticket, 'autoprint' => 1]), false);
});

test('officers cannot print another officer\'s voucher', function () {
    $owner = User::factory()->create(['role' => 'ticketing']);
    $other = User::factory()->create(['role' => 'ticketing']);
    $ticket = makeTicket(['created_by' => $owner->id]);

    $this->actingAs($other)->get(route('ticketing.tickets.voucher', $ticket))->assertNotFound();
});
