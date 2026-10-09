<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A travel insurance plan the ticketing desk can add to a booking.
 *
 * Admins keep this list. A plan is switched off rather than deleted, and its
 * key never changes, so bookings made on it keep showing its name.
 */
class InsurancePlan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'price_per_pax',
        'coverage',
        'is_popular',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_per_pax' => 'decimal:2',
            'coverage' => 'array',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Plans offered in the wizard, in the admin's chosen order.
     *
     * @param  Builder<InsurancePlan>  $query
     */
    public function scopeOffered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** A key for a new plan, made from its name and unique among all plans. */
    public static function keyFor(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $key = $base;

        for ($i = 2; static::where('key', $key)->exists(); $i++) {
            $key = "{$base}-{$i}";
        }

        return $key;
    }

    /** The name to print for a stored plan key; an unknown key is shown as typed. */
    public static function labelFor(?string $key): string
    {
        if (blank($key)) {
            return 'Standard Plan';
        }

        return static::where('key', $key)->value('name') ?? Str::headline($key).' Plan';
    }
}
