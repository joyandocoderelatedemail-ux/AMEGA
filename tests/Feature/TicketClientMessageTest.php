<?php

use App\Models\TicketBooking;
use App\Models\User;
use App\Notifications\FlightReminderNotification;
use App\Notifications\TicketDocumentsNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

test('staff can email the client a flight reminder', function () {
    $officer = User::factory()->create(['role' => 'ticketing']);
    $ticket = messageTicket($officer);

    $this->actingAs($officer)->post(route('ticketing.tickets.reminder', $ticket))
        ->assertSessionHas('success', 'Sent the flight reminder to maria@example.com.');

    Notification::assertSentOnDemand(FlightReminderNotification::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return array_key_exists('maria@example.com', $notifiable->routes['mail'])
            && $mail->subject === 'Reminder from Amega: your flight is in 2 days'
            && str_contains($mail->introLines[0], "Your flight is in 2 days. Don't miss it!");
    });
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
