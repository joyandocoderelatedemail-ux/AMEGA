<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter fee schedule. Editable from the admin panel so a price change needs
 * no deploy, mirroring the immigration pricing tiers.
 */
class VisaPricingTier extends Model
{
    use HasFactory;

    public const SERVICE_TYPES = ['visit_visa', 'e_visa', 'passporting'];

    protected $fillable = [
        'service_type',
        'label',
        'condition_notes',
        'amount',
        'currency',
        'processing_time',
        'needs_review',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'needs_review' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Rows safe to show to the public: enabled and confirmed against the source sheet.
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)->where('needs_review', false);
    }

    public function scopeForService($query, string $serviceType)
    {
        return $query->where('service_type', $serviceType);
    }

    /**
     * The published amount for a service, matched on a label fragment.
     * Returns 0.0 when nothing confirmed is on file, so a missing fee can never
     * silently become a charge.
     */
    public static function amountFor(string $serviceType, string $labelFragment): float
    {
        return (float) (static::published()
            ->where('service_type', $serviceType)
            ->where('label', 'like', "%{$labelFragment}%")
            ->orderBy('sort_order')
            ->value('amount') ?? 0);
    }
}
