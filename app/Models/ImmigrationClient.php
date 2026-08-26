<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ImmigrationClient extends Model
{
    use HasFactory;

    /**
     * Number of extension rows printed on the paper Client Information Sheet.
     */
    public const LEDGER_ROWS = 10;

    protected $fillable = [
        'user_id',
        'last_name',
        'given_name',
        'address',
        'email',
        'mobile_number',
        'height',
        'weight',
        'civil_status',
        'nationality',
        'date_of_birth',
        'passport_number',
        'visa_expiry_date',
        'is_expired',
        'has_penalty',
        'status_note',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'date_of_birth' => 'date',
            'visa_expiry_date' => 'date',
            'is_expired' => 'boolean',
            'has_penalty' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ImmigrationClientDocument::class);
    }

    public function extensions(): HasMany
    {
        return $this->hasMany(ImmigrationClientExtension::class)->orderBy('sequence');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->given_name} {$this->last_name}");
    }

    /**
     * Match a passport number loosely, so spacing and case at the counter don't matter.
     */
    public function scopeMatchingPassport(Builder $query, string $passportNumber): Builder
    {
        $normalised = preg_replace('/[^A-Za-z0-9]/', '', $passportNumber);

        return $query->whereRaw(
            "UPPER(REPLACE(REPLACE(REPLACE(passport_number, ' ', ''), '-', ''), '.', '')) LIKE ?",
            ['%'.strtoupper((string) $normalised).'%']
        );
    }

    /**
     * The document row for a given type, creating an empty one when the client has none yet.
     */
    public function documentFor(string $type): ImmigrationClientDocument
    {
        return $this->documents->firstWhere('document_type', $type)
            ?? new ImmigrationClientDocument(['document_type' => $type]);
    }

    /**
     * The ledger row at a given position, or an empty placeholder for printing.
     */
    public function extensionAt(int $sequence): ImmigrationClientExtension
    {
        return $this->extensions->firstWhere('sequence', $sequence)
            ?? new ImmigrationClientExtension(['sequence' => $sequence]);
    }

    /**
     * The number the client's next extension would carry on the paper ledger.
     */
    public function getNextExtensionNumberAttribute(): int
    {
        return (int) $this->extensions->max('sequence') + 1;
    }

    /**
     * Whole days of visa validity left, or null when no expiry date is on file.
     * Negative once the visa has lapsed.
     */
    public function getDaysUntilVisaExpiryAttribute(): ?int
    {
        return $this->visa_expiry_date
            ? (int) now()->startOfDay()->diffInDays($this->visa_expiry_date->startOfDay(), false)
            : null;
    }

    /**
     * Which processing route the visa's remaining validity puts the client in,
     * following the thresholds on the Bureau of Immigration pricing sheet:
     * 8 days or more is regular, under 8 is express, lapsed adds penalties.
     *
     * @return array{key: string, label: string, detail: string}|null
     */
    public function getValidityBandAttribute(): ?array
    {
        $days = $this->days_until_visa_expiry;

        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return [
                'key' => 'expired',
                'label' => 'Visa expired',
                'detail' => abs($days).' '.Str::plural('day', abs($days)).' ago — express plus penalties',
            ];
        }

        if ($days < 8) {
            return [
                'key' => 'express',
                'label' => 'Express only',
                'detail' => $days.' '.Str::plural('day', $days).' of validity left',
            ];
        }

        return [
            'key' => 'regular',
            'label' => 'Regular processing',
            'detail' => $days.' '.Str::plural('day', $days).' of validity left',
        ];
    }

    /**
     * True when the agent has marked this sheet, or the visa date says it has lapsed.
     */
    public function isFlagged(): bool
    {
        return $this->is_expired
            || $this->has_penalty
            || ($this->days_until_visa_expiry !== null && $this->days_until_visa_expiry < 0);
    }

    /**
     * Short marks to stamp on the sheet and show beside the client's name.
     *
     * @return array<int, string>
     */
    public function getStatusMarksAttribute(): array
    {
        $marks = [];

        if ($this->is_expired) {
            $marks[] = 'VISA EXPIRED';
        }

        if ($this->has_penalty) {
            $marks[] = 'WITH PENALTY';
        }

        // Surface a lapsed date even when nobody has ticked the box yet
        if ($marks === [] && $this->days_until_visa_expiry !== null && $this->days_until_visa_expiry < 0) {
            $marks[] = 'VISA EXPIRED';
        }

        return $marks;
    }
}
