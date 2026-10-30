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
        'receipt_file_path',
        'receipt_file_name',
        'receipt_file_mime',
        'receipt_file_size',
        'currency',
        'foreign_amount',
        'exchange_rate',
    ];

    /** The currencies a payment can be made in; `amount` is always the peso equivalent. */
    public const CURRENCIES = ['PHP' => '₱ Peso', 'USD' => 'US$ Dollar'];

    public function isInUsd(): bool
    {
        return $this->currency === 'USD' && $this->foreign_amount !== null;
    }

    /** The amount as the receipt shows it: "₱11,300.00" or "US$200.00 (₱11,300.00 @ 56.50)". */
    public function amountLabel(): string
    {
        $peso = '₱'.number_format((float) $this->amount, 2);
        if (! $this->isInUsd()) {
            return $peso;
        }
        $rate = rtrim(rtrim(number_format((float) $this->exchange_rate, 4), '0'), '.');

        return 'US$'.number_format((float) $this->foreign_amount, 2)." ({$peso} @ {$rate})";
    }

    /** Whether the actual receipt (a scan or photo) was attached to this entry. */
    public function hasReceiptFile(): bool
    {
        return filled($this->receipt_file_path);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'foreign_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
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
