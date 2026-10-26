<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A supporting document kept with a ticket booking, so the office has a copy: the real e-ticket,
 * boarding passes, hotel or tour vouchers, an insurance policy, a receipt. The file sits on the
 * private documents disk and is downloaded only through the ticketing desk.
 */
class TicketBookingDocument extends Model
{
    /** What each kind of document is called at the desk. */
    public const TYPES = [
        'e_ticket' => 'Airline e-ticket / ticket photo',
        'boarding_pass' => 'Boarding pass',
        'hotel_voucher' => 'Hotel voucher',
        'tour_voucher' => 'Tour / transfer voucher',
        'insurance_policy' => 'Insurance policy',
        'receipt' => 'Receipt / proof of payment',
        'other' => 'Other',
    ];

    protected $fillable = [
        'ticket_booking_id',
        'document_type',
        'note',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    /** The booking, under the same scope as the desk: an officer only reaches their own. */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }
}
