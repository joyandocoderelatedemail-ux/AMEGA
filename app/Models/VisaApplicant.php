<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One applicant on a visa file. A group e-Visa package carries several of
 * these under a single file; the steps are identical either way.
 */
class VisaApplicant extends Model
{
    use HasFactory;

    protected $fillable = [
        'visa_application_id',
        'applicant_number',
        'is_primary',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'date_of_birth',
        'gender',
        'nationality',
        'passport_number',
        'passport_expiry_date',
        'passport_country',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'visa_application_id' => 'integer',
            'applicant_number' => 'integer',
            'is_primary' => 'boolean',
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ])));
    }
}
