<?php

namespace App\Models;

use App\Support\ImmigrationServices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of an Immigration quotation: a counter service (or anything else), its quantity and price. */
class ImmigrationQuotationItem extends Model
{
    protected $fillable = [
        'immigration_quotation_id',
        'service_key',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(ImmigrationQuotation::class, 'immigration_quotation_id');
    }

    /** The counter service's name, or null for a line that is not one. */
    public function serviceLabel(): ?string
    {
        if (blank($this->service_key)) {
            return null;
        }

        $service = collect(ImmigrationServices::all())->firstWhere('key', $this->service_key);

        return $service['label'] ?? null;
    }

    /** A line for a service the counter runs can open that service's file. */
    public function opensFile(): bool
    {
        return filled($this->service_key) && collect(ImmigrationServices::all())->contains(fn (array $s) => $s['key'] === $this->service_key && $s['available']);
    }
}
