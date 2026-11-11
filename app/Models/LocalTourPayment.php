<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment on a local tour booking, recorded by the cashier (or an admin): how much, how, the OR or reference.
 */
class LocalTourPayment extends Model
{
    /** How the client paid: the same choices as the ticket payments. */
    public const METHODS = TicketPayment::METHODS;

    protected $fillable = ['local_tour_booking_id', 'amount', 'method', 'reference', 'note', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(LocalTourBooking::class, 'local_tour_booking_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
