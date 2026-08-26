<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmigrationRequirement extends Model
{
    use HasFactory;

    public const TYPES = ['requirement', 'note'];

    protected $fillable = [
        'immigration_category_id',
        'label',
        'type',
        'needs_review',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'immigration_category_id' => 'integer',
            'needs_review' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Entries confirmed against the source sheet and safe to show publicly.
     */
    public function scopePublished($query)
    {
        return $query->where('needs_review', false);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ImmigrationCategory::class, 'immigration_category_id');
    }
}
