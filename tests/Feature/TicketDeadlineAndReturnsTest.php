<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\TicketApprovalRequestedNotification;
use App\Notifications\TicketDeadlineDueNotification;
use Illuminate\Support\Facades\Notification;

function heldBooking(User $agent, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
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
        'approval_status' => TicketBooking::APPROVAL_APPROVED,
        'reviewed_at' => now(),
    ], $overrides));
}

test('the airline hold saves the PNR and deadline without sending an approved booking back', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = heldBooking($agent);

    $this->actingAs($agent)->get(route('ticketing.tickets.show', $ticket))->assertOk()->assertSee('Airline Hold');

    $this->actingAs($agent)->put(route('ticketing.tickets.hold', $ticket), [
        'airline_pnr' => 'qwe 4rt', 'ticketing_deadline' => now()->addHours(5)->format('Y-m-d\TH:i'),
    ])->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->airline_pnr)->toBe('QWE4RT')
        ->and($ticket->isApproved())->toBeTrue()
        ->and($ticket->deadlineState())->toBe('due');
});

test('holds due within 24 hours head the dashboard and the cashier queue', function () {
    $agent = User::factory()->create(['role' => 'ticketing']);
    $later = heldBooking($agent, ['contact_name' => 'Later Client', 'ticketing_deadline' => now()->addDays(3)]);
    $due = heldBooking($agent, ['contact_name' => 'Due Client', 'ticketing_deadline' => now()->addHours(3), 'airline_pnr' => 'ABC123']);
    heldBooking($agent, ['contact_name' => 'Issued Client', 'ticketing_deadline' => now()->addHour(), 'status' => TicketBooking::STATUS_ISSUED]);

    $this->actingAs($agent)->get(route('ticketing.dashboard'))->assertOk()
        ->assertSee('Airline Holds Due')
        ->assertSeeInOrder(['Airline Holds Due', 'Due Client', 'PNR ABC123'])
        ->assertViewHas('deadlineTickets', fn ($tickets) => $tickets->pluck('id')->all() === [$due->id]);

    $this->actingAs(cashier())->get(route('cashier.dashboard'))->assertOk()
        ->assertSee('must be paid within 24 hours')
        ->assertSeeInOrder([$due->booking_reference, $later->booking_reference]);
});

test('the hourly reminder emails the agent once per deadline', function () {
    Notification::fake();
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = heldBooking($agent, ['ticketing_deadline' => now()->addHours(6)]);
    heldBooking($agent, ['ticketing_deadline' => now()->addDays(2)]);

    $this->artisan('ticketing:send-deadline-reminders')->assertSuccessful();
    $this->artisan('ticketing:send-deadline-reminders')->assertSuccessful();

    Notification::assertSentToTimes($agent, TicketDeadlineDueNotification::class, 1);
    expect($ticket->fresh()->deadline_reminded_at)->not->toBeNull();

    // A new deadline is reminded again.
    $this->actingAs($agent)->put(route('ticketing.tickets.hold', $ticket), ['ticketing_deadline' => now()->addHours(10)->format('Y-m-d\TH:i')]);
    expect($ticket->fresh()->deadline_reminded_at)->toBeNull();
});

test('a returned booking heads the agent dashboard with the reason and can be resent as it is', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => 'admin']);
    $agent = User::factory()->create(['role' => 'ticketing']);
    $ticket = heldBooking($agent, [
        'approval_status' => TicketBooking::APPROVAL_REJECTED,
        'review_note' => 'Upload the passport scan.',
        'reviewed_by' => $admin->id,
    ]);

    $this->actingAs($agent)->get(route('ticketing.dashboard'))->assertOk()
        ->assertSeeInOrder(['Returned To You', $ticket->booking_reference, 'Upload the passport scan.', 'Fix and resend', 'Resend as is', 'Total Tickets'])
        ->assertSee(route('ticketing.tickets.resubmit', $ticket), false);

    $this->actingAs($agent)->post(route('ticketing.tickets.resubmit', $ticket))->assertSessionHas('success');

    $ticket->refresh();
    expect($ticket->isAwaitingApproval())->toBeTrue()->and($ticket->review_note)->toBeNull();
    Notification::assertSentTo($admin, TicketApprovalRequestedNotification::class);

    $this->actingAs($agent)->get(route('ticketing.dashboard'))->assertDontSee('Returned To You');

    // Only a returned booking can be resent this way.
    $this->actingAs($agent)->post(route('ticketing.tickets.resubmit', $ticket))->assertSessionHas('error');
});
