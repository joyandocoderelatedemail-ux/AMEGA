<?php

namespace App\Support;

use App\Models\TicketBooking;
use App\Models\TicketFlightChange;

/**
 * The words that tell a client their flight changed. One source for the email,
 * the text message and the copy-and-paste version staff send over Viber or
 * Messenger, so they never disagree about what moved.
 */
class FlightChangeMessage
{
    /** Where the client can reach the agency about it. */
    private const CONTACT = '+63 992 922 5733 / +63 949 9900 663';

    /**
     * What happened, in a sentence that follows "Your flight …".
     */
    public static function headline(TicketFlightChange $change): string
    {
        return match ($change->type) {
            TicketFlightChange::TYPE_DELAY => 'has a new schedule',
            TicketFlightChange::TYPE_RESCHEDULE => 'has been rescheduled by the airline',
            TicketFlightChange::TYPE_AIRLINE_CANCELLED => 'has been cancelled by the airline',
            default => 'has been changed as you requested',
        };
    }

    /**
     * What the client should expect or do next.
     */
    public static function nextStep(TicketFlightChange $change): string
    {
        return $change->isAirlineCancellation()
            ? 'We will contact you shortly about rebooking you on another flight or refunding your ticket.'
            : 'Please take note of the new schedule and arrive at the airport early. Call us if it does not suit you.';
    }

    /**
     * The route and flight the message is about.
     */
    private static function flightLabel(TicketBooking $ticket): string
    {
        $route = trim("{$ticket->origin} to {$ticket->destination}");

        return filled($ticket->flight_number) ? "{$route} ({$ticket->flight_number})" : $route;
    }

    /**
     * The message as plain text. $compact keeps it short enough for an SMS.
     */
    public static function text(TicketFlightChange $change, bool $compact = false): string
    {
        $ticket = $change->ticket;
        $flight = self::flightLabel($ticket);
        $moves = $change->movements();

        $intro = "Amega Travel: your flight {$flight} {$ticket->booking_reference} ".self::headline($change).'.';

        $detail = collect($moves)->map(fn (array $move): string => "{$move['label']}: {$move['to']} (was {$move['from']})");

        $lines = [$intro];

        if (! $change->isAirlineCancellation() && $detail->isNotEmpty()) {
            $lines[] = $compact ? $detail->implode('; ').'.' : $detail->implode("\n");
        }

        if (! $compact && filled($change->reason)) {
            $lines[] = 'Reason: '.trim($change->reason);
        }

        if ((float) $change->change_fee > 0) {
            $lines[] = 'Change fee: PHP '.number_format((float) $change->change_fee, 2).'.';
        }

        $lines[] = $compact ? 'Questions? Call '.self::CONTACT.'.' : self::nextStep($change)."\nQuestions? Call ".self::CONTACT.'.';

        return implode($compact ? ' ' : "\n\n", $lines);
    }
}
