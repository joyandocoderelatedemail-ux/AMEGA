<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment on a new SRRV application, recorded by the cashier (or an admin): how much, how, the OR or reference.
 */
class SrrvPayment extends Model
{
    /** How the client paid: the same choices as the ticket payments. */
    public const METHODS = TicketPayment::METHODS;

    protected $fillable = ['srrv_application_id', 'amount', 'method', 'reference', 'note', 'received_by', 'received_at'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SrrvApplication::class, 'srrv_application_id');
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
