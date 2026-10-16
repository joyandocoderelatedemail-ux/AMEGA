<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One payment recorded against a BI application. */
class TouristVisaPayment extends Model
{
    public const METHODS = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card',
        'other' => 'Other',
    ];

    protected $fillable = ['tourist_visa_application_id', 'amount', 'method', 'paid_on', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return [
            'tourist_visa_application_id' => 'integer',
            'recorded_by' => 'integer',
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(TouristVisaApplication::class, 'tourist_visa_application_id')->withoutGlobalScopes();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method));
    }
}
