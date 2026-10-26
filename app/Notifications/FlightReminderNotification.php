<?php

namespace App\Notifications;

use App\Models\TicketBooking;
use App\Support\MessageTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Reminds the client their flight is coming up, in the wording an admin set under Message
 * Templates (the desk's default otherwise), filled from the booking and signed by the sender.
 */
class FlightReminderNotification extends Notification
{
    use Queueable;

    /**
     * @param  string|null  $sender  the staff member sending it, who signs the email
     */
    public function __construct(public TicketBooking $ticket, public ?string $sender = null) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = MessageTemplates::render('flight_reminder', self::values($this->ticket, $this->sender));

        return (new MailMessage)
            ->subject($message['subject'])
            ->markdown('mail.template-message', ['paragraphs' => MessageTemplates::paragraphs($message['body'])]);
    }

    /**
     * What the template's placeholders stand for on this booking.
     *
     * @return array<string, string|null>
     */
    public static function values(TicketBooking $ticket, ?string $sender): array
    {
        $flights = self::flights($ticket);
        $first = reset($flights) ?: [];

        return [
            'client_name' => $ticket->contact_name,
            'reference' => $ticket->booking_reference,
            'flights' => collect($flights)->map(fn (array $flight, string $heading) => ($heading !== '' ? $heading."\n" : '').implode("\n", [
                '✈️ Flight Date: '.($flight['date']?->format('l, F j, Y') ?? 'To be confirmed'),
                '🛫 Departure: '.($flight['departs'] ?: 'To be confirmed'),
                '📍 From: '.($flight['from'] ?: 'To be confirmed'),
                '📍 To: '.($flight['to'] ?: 'To be confirmed'),
                '✈️ Flight Number: '.($flight['number'] ?: 'To be confirmed'),
            ]))->implode("\n\n"),
            'flight_date' => ($first['date'] ?? null)?->format('l, F j, Y'),
            'departure' => $first['departs'] ?? null,
            'from' => $first['from'] ?? null,
            'to' => $first['to'] ?? null,
            'flight_number' => $first['number'] ?? null,
            'travel_type' => $ticket->travel_type === 'international' ? 'international' : 'domestic',
            'when' => self::when($ticket),
            'agent_name' => $sender ?: 'Amega Travel and Tours Services',
        ];
    }

    /**
     * The flights to remind about, by heading: the one flight; the departing and return flights
     * on a round trip; each leg of a multi-city trip.
     *
     * @return array<string, array{date: ?Carbon, departs: ?string, from: ?string, to: ?string, number: ?string}>
     */
    private static function flights(TicketBooking $ticket): array
    {
        if ($ticket->multiCityLegs() !== []) {
            $flights = [];
            foreach ($ticket->itineraryLegs() as $i => $leg) {
                $flights['Flight '.($i + 1)] = [
                    'date' => $leg['date'] ?? null,
                    'departs' => $leg['departs'] ?? null,
                    'from' => $leg['from'] ?? null,
                    'to' => $leg['to'] ?? null,
                    'number' => $leg['flight'] ?? null,
                ];
            }

            return $flights;
        }

        $departing = [
            'date' => $ticket->departure_date,
            'departs' => TicketBooking::formatFlightTime($ticket->departure_time),
            'from' => $ticket->origin,
            'to' => $ticket->destination,
            'number' => $ticket->flight_number,
        ];

        if ($ticket->trip_type !== 'round_trip' || ! $ticket->return_date) {
            return ['' => $departing];
        }

        return [
            'Departing flight' => $departing,
            'Return flight' => [
                'date' => $ticket->return_date,
                'departs' => TicketBooking::formatFlightTime($ticket->return_departure_time),
                'from' => $ticket->destination,
                'to' => $ticket->origin,
                'number' => $ticket->return_flight_number,
            ],
        ];
    }

    /**
     * How far off the departure is, in words: "today", "tomorrow", "in 2 days".
     */
    public static function when(TicketBooking $ticket): string
    {
        $days = (int) now()->startOfDay()->diffInDays($ticket->departure_date->copy()->startOfDay(), false);

        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };
    }
}
