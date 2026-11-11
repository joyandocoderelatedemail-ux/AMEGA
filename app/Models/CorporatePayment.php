<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One payment from a company on its credit account, applied to its unpaid tickets oldest first. */
class CorporatePayment extends Model
{
    /** How a company pays its account (no cards: a card payment carries a surcharge per ticket). */
    public const METHODS = [
        'bank_transfer' => 'Bank transfer',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'other' => 'Other',
    ];

    protected $fillable = ['corporate_account_id', 'amount', 'method', 'reference', 'note', 'received_at', 'received_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'received_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(CorporateAccount::class, 'corporate_account_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** The parts of this payment, one per ticket it was applied to. */
    public function entries(): HasMany
    {
        return $this->hasMany(TicketPayment::class);
    }

    public function number(): string
    {
        return sprintf('CP-%05d', $this->id);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? ucfirst((string) $this->method);
    }
}
