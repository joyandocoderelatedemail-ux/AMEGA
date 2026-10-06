<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against a ticket booking, or returned to the client after a
 * cancellation. Entries are never edited: a mistake is corrected by a new one.
 */
class TicketPayment extends Model
{
    public const TYPE_PAYMENT = 'payment';

    public const TYPE_REFUND = 'refund';

    /** How the money was handed over, as shown on the desk's receipts. */
    public const METHODS = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Credit / debit card',
        'other' => 'Other',
    ];

    protected $fillable = [
        'ticket_booking_id',
        'type',
        'amount',
        'method',
        'reference',
        'note',
        'received_at',
        'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isRefund(): bool
    {
        return $this->type === self::TYPE_REFUND;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst(str_replace('_', ' ', (string) $this->method));
    }

    /**
     * The receipt number printed on the slip.
     */
    public function receiptNumber(): string
    {
        return ($this->isRefund() ? 'REF-' : 'OR-').str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
