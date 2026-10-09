<?php

namespace App\Http\Controllers\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\TicketBooking;
use App\Models\TicketFlightChange;
use App\Notifications\FlightChangedNotification;
use App\Services\ActivityLogger;
use App\Services\ClientNotifier;
use App\Services\SmsSender;
use App\Support\FlightChangeMessage;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Records a change to a booked flight (a delay, a new date, a cancellation by
 * the airline, a change the client asked for) and tells the client.
 *
 * The airline's schedule is not visible to the system, so staff enter what the
 * airline told them. The booking is updated, the old and new schedule are kept
 * as a history entry on the ticket, and the client is told by email, by SMS
 * once a provider is connected, or by a message staff copy and send themselves.
 * It works on an issued ticket too: only the flight details change, never the
 * price or the passengers.
 */
class TicketFlightChangeController extends Controller
{
    public function store(Request $request, TicketBooking $ticket): RedirectResponse
    {
        if ($ticket->isCancelled()) {
            return back()->with('error', 'A cancelled booking has no flight to change.');
        }

        if ($ticket->isQuotation()) {
            return back()->with('error', 'This is a quotation, not a booked flight. Complete the booking first.');
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(TicketFlightChange::TYPES))],
            'departure_date' => ['nullable', 'date'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'flight_number' => ['nullable', 'string', 'max:20'],
            'return_date' => ['nullable', 'date'],
            'return_departure_time' => ['nullable', 'date_format:H:i'],
            'return_arrival_time' => ['nullable', 'date_format:H:i'],
            'return_flight_number' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:500'],
            'change_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_sms' => ['nullable', 'boolean'],
        ]);

        $cancelled = $validated['type'] === TicketFlightChange::TYPE_AIRLINE_CANCELLED;
        $new = $cancelled ? [] : $this->newSchedule($validated, $ticket);

        if (! $cancelled && $new === []) {
            throw ValidationException::withMessages(['changes' => 'Nothing was changed. Enter the new date, time or flight number.']);
        }

        $this->guardReturnAfterDeparture($ticket, $new);

        $fee = round((float) ($validated['change_fee'] ?? 0), 2);

        $change = DB::transaction(function () use ($ticket, $request, $validated, $new, $fee): TicketFlightChange {
            $previous = TicketFlightChange::snapshot($ticket);

            if ($new !== []) {
                $ticket->fill($new)->save();
            }

            // A fee can still be added to a ticket that is not issued yet; once it
            // is issued the payment is locked, so the fee is only noted and quoted.
            $feeAdded = $fee > 0 && ! $ticket->isIssued();
            if ($feeAdded) {
                $ticket->other_charges = round((float) $ticket->other_charges + $fee, 2);
                $ticket->total_amount = round((float) $ticket->total_amount + $fee, 2);
                $ticket->recordPayment((float) $ticket->amount_paid);
            }

            return $ticket->flightChanges()->create([
                'recorded_by' => $request->user()->id,
                'type' => $validated['type'],
                'schedule_before' => $previous,
                'schedule_after' => TicketFlightChange::snapshot($ticket->refresh()),
                'reason' => filled($validated['reason'] ?? null) ? trim($validated['reason']) : null,
                'change_fee' => $fee,
                'fee_added' => $feeAdded,
            ]);
        });

        $change->setRelation('ticket', $ticket);

        ActivityLogger::log('Ticketing', 'FLIGHT_CHANGE', "Recorded a flight change on {$ticket->booking_reference}: {$change->label()}"
            .($change->movements() ? ' ('.collect($change->movements())->map(fn (array $m): string => "{$m['label']} {$m['from']} to {$m['to']}")->implode(', ').')' : ''));

        $told = $this->tellClient($change, $request->boolean('notify_email'), $request->boolean('notify_sms'));

        $message = "Flight change recorded on {$ticket->booking_reference}.";
        $message .= $told['sent'] !== []
            ? ' The client was told by '.implode(' and ', $told['sent']).'.'
            : ' The client has not been told yet: copy the message below and send it, then mark them as told.';

        foreach ($told['problems'] as $problem) {
            $message .= " {$problem}";
        }

        if ($fee > 0 && $ticket->isIssued()) {
            $message .= ' The ticket is already issued, so the ₱'.number_format($fee, 2).' fee was noted in the message but not added to the balance; collect it separately.';
        }

        return redirect()->to(route('ticketing.tickets.show', $ticket).'#flight-changes')->with('success', $message);
    }

    /**
     * Tell the client about a recorded change, or mark that staff already did.
     */
    public function notify(Request $request, TicketBooking $ticket, TicketFlightChange $flightChange): RedirectResponse
    {
        $channel = $request->validate(['channel' => ['required', Rule::in(['email', 'sms', 'manual'])]])['channel'];
        $flightChange->setRelation('ticket', $ticket);

        if ($channel === 'manual') {
            $this->markTold($flightChange, 'manual');
            ActivityLogger::log('Ticketing', 'FLIGHT_CHANGE', "Marked the client of {$ticket->booking_reference} as told about the flight change");

            return redirect()->to(route('ticketing.tickets.show', $ticket).'#flight-changes')->with('success', 'Marked as told.');
        }

        $told = $this->tellClient($flightChange, $channel === 'email', $channel === 'sms');

        if ($told['sent'] === []) {
            return redirect()->to(route('ticketing.tickets.show', $ticket).'#flight-changes')
                ->with('error', $told['problems'][0] ?? 'The client could not be reached that way.');
        }

        return redirect()->to(route('ticketing.tickets.show', $ticket).'#flight-changes')
            ->with('success', 'Sent to the client by '.implode(' and ', $told['sent']).'.');
    }

    /**
     * Send the change by the channels asked for, and note the ones that worked.
     *
     * @return array{sent: list<string>, problems: list<string>}
     */
    private function tellClient(TicketFlightChange $change, bool $email, bool $sms): array
    {
        $ticket = $change->ticket;
        $sent = [];
        $problems = [];

        if ($email) {
            if (! ClientNotifier::canReceive($ticket->contact_email)) {
                $problems[] = 'There is no client email address on file.';
            } elseif (ClientNotifier::send($ticket->contact_email, $ticket->contact_name, new FlightChangedNotification($change))) {
                $sent[] = 'email';
            } else {
                $problems[] = "The email to {$ticket->contact_email} could not be sent.";
            }
        }

        if ($sms) {
            if (! SmsSender::enabled()) {
                $problems[] = 'SMS is not set up yet, so no text message was sent.';
            } elseif (SmsSender::send($ticket->contact_phone, FlightChangeMessage::text($change, compact: true))) {
                $sent[] = 'SMS';
            } else {
                $problems[] = 'The SMS could not be sent: check the client\'s mobile number.';
            }
        }

        foreach ($sent as $channel) {
            $this->markTold($change, strtolower($channel));
        }

        return ['sent' => $sent, 'problems' => $problems];
    }

    private function markTold(TicketFlightChange $change, string $channel): void
    {
        $via = collect(explode(',', (string) $change->notified_via))->filter()->push($channel)->unique()->implode(',');

        $change->forceFill(['client_notified_at' => $change->client_notified_at ?? now(), 'notified_via' => $via])->save();
    }

    /**
     * The booking fields that really move: what was typed, where it differs from now.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, string>
     */
    private function newSchedule(array $validated, TicketBooking $ticket): array
    {
        $current = TicketFlightChange::snapshot($ticket);
        $new = [];

        foreach (TicketFlightChange::FIELDS as $field) {
            $value = $validated[$field] ?? null;

            // Blank means "no change", and the return leg only exists on a round trip.
            if (blank($value) || (str_starts_with($field, 'return_') && $ticket->trip_type !== 'round_trip')) {
                continue;
            }

            $value = str_ends_with($field, 'flight_number') ? strtoupper(str_replace(' ', '', trim($value))) : trim($value);

            if (str_ends_with($field, '_date')) {
                $value = Carbon::parse($value)->toDateString();
            }

            if ($value !== $current[$field]) {
                $new[$field] = $value;
            }
        }

        return $new;
    }

    /**
     * Whatever the new dates are, a round trip cannot return before it departs.
     *
     * @param  array<string, string>  $new
     */
    private function guardReturnAfterDeparture(TicketBooking $ticket, array $new): void
    {
        // A new departure date has to be a day that has not passed yet.
        if (isset($new['departure_date']) && $new['departure_date'] < today()->toDateString()) {
            throw ValidationException::withMessages(['departure_date' => 'The new departure date cannot be in the past.']);
        }

        if ($ticket->trip_type !== 'round_trip' || ! array_intersect_key($new, array_flip(['departure_date', 'return_date']))) {
            return;
        }

        $departure = $new['departure_date'] ?? $ticket->departure_date?->toDateString();
        $return = $new['return_date'] ?? $ticket->return_date?->toDateString();

        if ($departure && $return && $return < $departure) {
            throw ValidationException::withMessages(['return_date' => 'The return date cannot be before the departure date.']);
        }
    }
}
