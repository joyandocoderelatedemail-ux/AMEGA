<?php

namespace App\Models;

use App\Models\Concerns\HasFormDetails;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One applicant on a visa file. A group e-Visa package carries several of
 * these under a single file; the steps are identical either way.
 */
class VisaApplicant extends Model
{
    use HasFactory, HasFormDetails;

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
        'form_details',
    ];

    protected function casts(): array
    {
        return [
            'visa_application_id' => 'integer',
            'applicant_number' => 'integer',
            'is_primary' => 'boolean',
            'date_of_birth' => 'date',
            'passport_expiry_date' => 'date',
            'form_details' => 'array',
        ];
    }

    /**
     * Under 18, by the date of birth on file. Unknown counts as an adult.
     */
    public function isMinor(): bool
    {
        return $this->date_of_birth !== null && $this->date_of_birth->age < 18;
    }

    /**
     * The registered client this applicant is, so the worksheets can pull what the client
     * record holds: matched on the passport number, else on the name and birth date.
     */
    public function registeredClient(): ?User
    {
        $clients = User::query()->where('role', 'client');

        if (filled($this->passport_number)) {
            $byPassport = (clone $clients)->where('passport_number', $this->passport_number)->first();

            if ($byPassport) {
                return $byPassport;
            }
        }

        if ($this->date_of_birth === null) {
            return null;
        }

        return $clients
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [mb_strtolower(trim((string) $this->first_name))])
            ->whereRaw('LOWER(TRIM(last_name)) = ?', [mb_strtolower(trim((string) $this->last_name))])
            ->whereDate('date_of_birth', $this->date_of_birth)
            ->first();
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VisaApplication::class, 'visa_application_id');
    }

    /**
     * Documents filed against this applicant rather than the file as a whole.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(VisaApplicationDocument::class, 'visa_applicant_id');
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
