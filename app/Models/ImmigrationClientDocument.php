<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImmigrationClientDocument extends Model
{
    use HasFactory;

    /**
     * The three columns of the sheet's Travel Information grid.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'acr_icard' => 'ACR - I-Card',
        'crtv' => 'CRTV',
        'annual_report' => 'Annual Report',
    ];

    protected $fillable = [
        'immigration_client_id',
        'document_type',
        'reference_number',
        'date_paid',
        'ssrn_number',
        'validity',
    ];

    protected function casts(): array
    {
        return [
            'immigration_client_id' => 'integer',
            'date_paid' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class, 'immigration_client_id');
    }

    public function getLabelAttribute(): string
    {
        return self::TYPES[$this->document_type] ?? $this->document_type;
    }

    /**
     * True when the agent has entered nothing in this column of the grid.
     */
    public function isBlank(): bool
    {
        return blank($this->reference_number)
            && blank($this->date_paid)
            && blank($this->ssrn_number)
            && blank($this->validity);
    }
}
