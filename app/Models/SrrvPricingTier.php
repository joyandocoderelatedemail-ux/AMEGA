<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SRRV fee schedule, editable from the admin panel. The renewal fee turns on
 * the visa class alone: classic USD 360 a year, courtesy USD 10 a year.
 */
class SrrvPricingTier extends Model
{
    use HasFactory;

    public const SERVICE_TYPES = ['renewal_application', 'renewal', 'restamping'];

    public const VISA_CLASSES = ['classic', 'courtesy', 'any'];

    protected $fillable = [
        'service_type',
        'visa_class',
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
}
