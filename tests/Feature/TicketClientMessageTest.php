<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\FlightReminderNotification;
use App\Notifications\TicketDocumentsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * @param  array<string, mixed>  $overrides
 */
function messageTicket(User $owner, array $overrides = []): TicketBooking
{
    return TicketBooking::create(array_merge([
        'booking_reference' => 'TKT-'.fake()->unique()->numerify('########'),
        'travel_type' => 'domestic',
        'package_type' => 'without_package',
        'origin' => 'Manila (MNL)',
        'destination' => 'Cebu (CEB)',
        'departure_date' => now()->addDays(2),
        'total_passengers' => 1,
        'contact_name' => 'Maria Santos',
        'contact_email' => 'maria@example.com',
        'total_amount' => 10000,
        'status' => 'issued',
        'created_by' => $owner->id,
    ], $overrides));
}

test('the dashboard lists flights departing this week with reminder and resend buttons', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $soon = messageTicket($officer);
    $later = messageTicket($officer, ['departure_date' => now()->addDays(20)]);

    $this->actingAs($officer)->get(route('ticketing.dashboard'))
        ->assertOk()
        ->assertSee('Departing Soon')
        ->assertSee('In 2 days')
        ->assertSee(route('ticketing.tickets.reminder', $soon))
        ->assertSee(route('ticketing.tickets.documents', $soon))
        ->assertSee(route('ticketing.tickets.reminder', $later));
});

test('staff can email the client a flight reminder, in the desk\'s words and signed by them', function () {
    $officer = User::factory()->create(['role' => 'ticketing', 'name' => 'Shey']);
    $ticket = messageTicket($officer, ['flight_number' => '5J 560', 'departure_time' => '08:30']);

    $this->actingAs($officer)->post(route('ticketing.tickets.reminder', $ticket))
        ->assertSessionHas('success', 'Sent the flight reminder to maria@example.com.');

    Notification::assertSentOnDemand(FlightReminderNotification::class, function ($notification, $channels, $notifiable) use ($ticket) {
        $mail = $notification->toMail($notifiable);
        $html = (string) $mail->render();

        return array_key_exists('maria@example.com', $notifiable->routes['mail'])
            && $mail->subject === 'Flight reminder from Amega: your flight is in 2 days'
            && str_contains($html, 'Mabuhay! 😊')
            && str_contains($html, 'Good day, Sir/Ma’am!')
            && str_contains($html, '✈️ Flight Date: '.$ticket->departure_date->format('l, F j, Y'))
            && str_contains($html, '🛫 Departure: 8:30 AM')
            && str_contains($html, '📍 From: Manila (MNL)')
            && str_contains($html, '📍 To: Cebu (CEB)')
            && str_contains($html, '✈️ Flight Number: 5J 560')
            && str_contains($html, 'at least 3 hours before your domestic flight')
            && str_contains($html, 'Thank you so much for choosing Amega Travel and Tours Services! 💙')
            && str_contains($html, 'Best Regards,<br>Shey');
    });
});

test('a round-trip reminder lists the departing and the return flight', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = messageTicket($officer, ['trip_type' => 'round_trip', 'return_date' => now()->addDays(6), 'flight_number' => '5J 560', 'return_flight_number' => '5J 561']);

    $html = (string) (new FlightReminderNotification($ticket, 'Shey'))->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Departing flight')->toContain('Return flight')
        ->toContain('✈️ Flight Number: 5J 561')->toContain('📍 From: Cebu (CEB)');
});

test('the reminder uses the wording an admin saved, and goes back to the default on reset', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $ticket = messageTicket($admin, ['flight_number' => '5J 560']);

    $this->actingAs($admin)->put(route('admin.message-templates.update', 'flight_reminder'), [
        'subject' => 'Your {to} flight is {when}', 'body' => "Hi {client_name}!\nFlight {flight_number} on {flight_date}.\n\nSee you, {agent_name}",
    ])->assertRedirect();

    $mail = (new FlightReminderNotification($ticket, 'Shey'))->toMail(new AnonymousNotifiable);
    expect($mail->subject)->toBe('Your Cebu (CEB) flight is in 2 days')
        ->and((string) $mail->render())->toContain('Hi Maria Santos!<br>Flight 5J 560 on')->toContain('See you, Shey')->not->toContain('Mabuhay');

    $this->actingAs($admin)->delete(route('admin.message-templates.reset', 'flight_reminder'))->assertRedirect();
    expect((string) (new FlightReminderNotification($ticket, 'Shey'))->toMail(new AnonymousNotifiable)->render())->toContain('Mabuhay! 😊');
});

test('no reminder goes out for a flight that has departed or a cancelled booking', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $departed = messageTicket($officer, ['departure_date' => now()->subDay()]);
    $cancelled = messageTicket($officer, ['status' => 'cancelled']);

    $this->actingAs($officer)->post(route('ticketing.tickets.reminder', $departed))->assertSessionHas('error');
    $this->actingAs($officer)->post(route('ticketing.tickets.reminder', $cancelled))->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('staff can resend the booking agreement and consent form', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = messageTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.documents', $ticket))
        ->assertSessionHas('success', 'Sent the booking agreement and consent form to maria@example.com.');

    Notification::assertSentOnDemand(TicketDocumentsNotification::class, function ($notification, $channels, $notifiable) use ($ticket) {
        $attachments = collect($notification->toMail($notifiable)->rawAttachments)->pluck('name');

        return $attachments->contains("Data-Privacy-Consent-{$ticket->booking_reference}.pdf");
    });
});

test('nothing is sent when the client has no email address', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = messageTicket($officer, ['contact_email' => '']);

    $this->actingAs($officer)->post(route('ticketing.tickets.reminder', $ticket))
        ->assertSessionHas('error', "{$ticket->booking_reference} has no client email address on file.");
    $this->actingAs($officer)->post(route('ticketing.tickets.documents', $ticket))->assertSessionHas('error');

    Notification::assertNothingSent();
});
