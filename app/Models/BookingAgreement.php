<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_booking_id',
        'agreement_number',
        'client_names',
        'agreement_date',
        'contact_phone',
        'contact_email',
        'home_hotel_address',
        'flight_segments',
        'has_baggage',
        'is_non_refundable',
        'is_non_rebookable',
        'has_meals',
        'with_rebooking_charge',
        'with_airport_transfer',
        'pricing_items',
        'total_amount',
        'payment_terms',
        'agent_name',
        'passenger_client_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'agreement_date' => 'date',
            'flight_segments' => 'array',
            'pricing_items' => 'array',
            'has_baggage' => 'boolean',
            'is_non_refundable' => 'boolean',
            'is_non_rebookable' => 'boolean',
            'has_meals' => 'boolean',
            'with_rebooking_charge' => 'boolean',
            'with_airport_transfer' => 'boolean',
            'total_amount' => 'decimal:2',
        ];
    }

    public function ticketBooking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }
}
