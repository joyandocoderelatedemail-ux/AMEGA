<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmigrationPricingTier extends Model
{
    use HasFactory;

    public const PROCESS_TYPES = ['regular', 'express'];

    public const PAYMENT_METHODS = ['cash', 'card'];

    protected $fillable = [
        'immigration_category_id',
        'extension_label',
        'duration_label',
        'process_type',
        'payment_method',
        'condition_notes',
        'price',
        'processing_time',
        'needs_review',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'immigration_category_id' => 'integer',
            'price' => 'decimal:2',
            'needs_review' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCategory::class, 'immigration_category_id');
    }

    /**
     * Rows safe to show to the public: enabled and confirmed against the source sheet.
     */
    public function scopePublished($query)
    {
        return $query->where('is_active', true)->where('needs_review', false);
    }
}
