<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmigrationClientExtension extends Model
{
    use HasFactory;

    protected $fillable = [
        'immigration_client_id',
        'sequence',
        'soa_or_number',
        'extension_date',
        'details',
        'amount_paid',
        'annual_report',
        'refund',
        'immigration_pricing_tier_id',
    ];

    protected function casts(): array
    {
        return [
            'immigration_client_id' => 'integer',
            'immigration_pricing_tier_id' => 'integer',
            'sequence' => 'integer',
            'extension_date' => 'date',
            'amount_paid' => 'decimal:2',
            'refund' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class, 'immigration_client_id');
    }

    public function pricingTier(): BelongsTo
    {
        return $this->belongsTo(ImmigrationPricingTier::class, 'immigration_pricing_tier_id');
    }

    /**
     * Ordinal suffix for a ledger position, as printed on the sheet: st, nd, rd, th.
     */
    public static function ordinalSuffix(int $sequence): string
    {
        return match (true) {
            in_array($sequence % 100, [11, 12, 13], true) => 'th',
            $sequence % 10 === 1 => 'st',
            $sequence % 10 === 2 => 'nd',
            $sequence % 10 === 3 => 'rd',
            default => 'th',
        };
    }

    /**
     * Ordinal as printed on the sheet: 1st, 2nd, 3rd, 4th...
     */
    public function getOrdinalAttribute(): string
    {
        return $this->sequence.self::ordinalSuffix((int) $this->sequence);
    }

    /**
     * True when the agent has entered nothing on this ledger row.
     */
    public function isBlank(): bool
    {
        return blank($this->soa_or_number)
            && blank($this->extension_date)
            && blank($this->details)
            && blank($this->amount_paid)
            && blank($this->annual_report)
            && blank($this->refund);
    }
}
