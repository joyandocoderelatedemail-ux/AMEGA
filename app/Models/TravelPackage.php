<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination_id',
        'title',
        'duration',
        'price',
        'rating',
        'image',
        'description',
        'inclusions',
        'exclusions',
        'itinerary',
        'available_dates',
        'category',
        'status',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }
}
