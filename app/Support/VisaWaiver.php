<?php

namespace App\Support;

use App\Models\TicketBooking;
use App\Models\TicketPassenger;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The agency's Visa Waiver, filled from a ticket booking: signed by a client who books a flight
 * before the visa is approved, for the passengers who ticked "Need Visa Assistance".
 */
class VisaWaiver
{
    /**
     * @param  Collection<int, TicketPassenger>  $passengers  Passengers whose visa is not yet approved.
     */
    public function __construct(
        public string $clientName,
        public Collection $passengers,
        public string $destination,
        public ?Carbon $departure,
        public string $reference,
        public string $backUrl,
        public string $pdfUrl,
        public ?string $handlingAgent,
    ) {}

    /** The waiver for a booking, or null when no passenger needs visa assistance. */
    public static function forTicket(TicketBooking $ticket): ?self
    {
        $passengers = ($ticket->relationLoaded('passengers') ? $ticket->passengers : $ticket->passengers()->get())
            ->where('visa_status', 'needs_assistance')
            ->sortBy('passenger_number')
            ->values();

        if ($passengers->isEmpty()) {
            return null;
        }

        return new self(
            (string) $ticket->contact_name,
            $passengers,
            (string) ($ticket->destination ?: $ticket->destination_country),
            $ticket->departure_date,
            (string) $ticket->booking_reference,
            route('ticketing.tickets.show', $ticket),
            route('ticketing.tickets.visa-waiver.pdf', $ticket),
            $ticket->issuedBy?->name ?? $ticket->createdBy?->name ?? auth()->user()?->name,
        );
    }

    public function departureLabel(): string
    {
        return $this->departure ? $this->departure->format('F j, Y') : 'the date of travel';
    }

    public function filename(): string
    {
        return 'Visa-Waiver-'.$this->reference.'.pdf';
    }
}
