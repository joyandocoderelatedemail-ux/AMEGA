<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomPackageInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
        'destination_id',
        'destination_name',
        'travel_type',
        'check_in_date',
        'check_out_date',
        'duration',
        'number_of_pax',
        'adults_count',
        'children_count',
        'infants_count',
        'hotel_name',
        'preferred_hotel',
        'has_breakfast',
        'bed_config',
        'smoking_preference',
        'pet_friendly',
        'has_transportation',
        'transportation_type',
        'special_requests',
        'estimated_budget',
        'currency',
        'status',
        'agent_notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'has_breakfast' => 'boolean',
            'pet_friendly' => 'boolean',
            'has_transportation' => 'boolean',
            'number_of_pax' => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'infants_count' => 'integer',
            'estimated_budget' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $inquiry): void {
            if (empty($inquiry->reference_number)) {
                $datePrefix = now()->format('Ymd');
                $randomSuffix = strtoupper(Str::random(4));
                $inquiry->reference_number = "CP-{$datePrefix}-{$randomSuffix}";
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    public function getFormattedBudgetAttribute(): string
    {
        if ($this->estimated_budget === null || $this->estimated_budget <= 0) {
            return 'Custom Quotation Pending';
        }

        $symbol = $this->currency === 'USD' ? '$' : '₱';

        return $symbol.number_format((float) $this->estimated_budget, 2);
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'quoted' => 'bg-blue-100 text-blue-800 border-blue-200',
            'booked' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200',
            default => 'bg-amber-100 text-amber-800 border-amber-200',
        };
    }
}
