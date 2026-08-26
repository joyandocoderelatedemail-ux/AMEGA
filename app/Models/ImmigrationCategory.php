<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImmigrationCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'processing_time',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(ImmigrationRequirement::class)->orderBy('sort_order');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(ImmigrationPricingTier::class)->orderBy('sort_order');
    }

    /**
     * Document checklist entries only, excluding free-text process notes.
     */
    public function documentRequirements(): HasMany
    {
        return $this->requirements()->where('type', 'requirement');
    }

    /**
     * Free-text process rules that do not fit a checklist or price row.
     */
    public function processNotes(): HasMany
    {
        return $this->requirements()->where('type', 'note');
    }
}
