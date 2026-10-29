<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A tourist visa extension filed with the Bureau of Immigration: the CGAF
 * (IRD04.QF.004) as filled at the counter, and the ticks on the Checklist of
 * Documentary Requirements (IRD04.QF.007).
 */
#[ScopedBy([OwnFilesScope::class])]
class TouristVisaApplication extends Model
{
    /** The desk whose "view_all" permission lifts OwnFilesScope. */
    public static function ownFilesDesk(): string
    {
        return 'visa_assistance';
    }

    public const METHODS = ['personal' => 'Personal', 'representative' => 'Authorized Representative'];

    public const MODES = ['regular' => 'Regular', 'express' => 'Express'];

    public const MONTHS = [1 => 'One (1) month', 2 => 'Two (2) months', 6 => 'Six (6) months'];

    public const CIVIL_STATUSES = [
        'single' => 'Single', 'married' => 'Married', 'annulled' => 'Annulled',
        'separated' => 'Separated', 'widowed' => 'Widowed', 'divorced' => 'Divorced',
    ];

    /** The numbered items on the BI checklist, in its order. */
    public const CHECKLIST = [
        'cgaf' => 'Duly accomplished Consolidated General Application Form (CGAF) for Extension of Temporary Visitor’s Visa',
        'passport' => 'Original Passport/Travel Document of the applicant',
        'downgrading_order' => 'Photocopy of Downgrading Order',
    ];

    /** Items the checklist marks "(If applicable)". */
    public const CHECKLIST_IF_APPLICABLE = ['downgrading_order'];

    /** Asked for only when the stay ran six months or more past what is allowed. */
    public const OVERSTAY_CHECKLIST = [
        'overstay_letter' => 'Notarized letter of explanation for overstaying with attached original or Certified True Copy (CTC) of supporting documents.',
    ];

    public const PAYMENT_TYPES = ['full' => 'Full payment', 'terms' => 'Terms'];

    /** How many payments a fee in terms may be split into. */
    public const MIN_TERMS = 2;

    public const MAX_TERMS = 12;

    /** How long the BI takes, by mode of application (from the published pricing). */
    public const PROCESSING_TIMES = ['regular' => '7-10 working days', 'express' => '1 day'];

    protected $fillable = [
        'reference', 'created_by',
        'application_method', 'representative_name', 'bi_accreditation_number',
        'application_mode', 'months_requested', 'reason',
        'mobile_number', 'email',
        'last_name', 'first_name', 'middle_name', 'alias', 'date_of_birth', 'sex', 'birthplace',
        'citizenship', 'height_cm', 'weight_kg', 'civil_status',
        'address_street', 'barangay', 'city', 'province', 'zip_code',
        'passport_number', 'passport_validity', 'latest_arrival_date', 'flight_number',
        'latest_extension_validity', 'latest_extension_place',
        'acr_number', 'acr_validity', 'ssrn',
        'checklist', 'documents', 'overstayed', 'filed_on', 'filed_time', 'processing_time', 'evaluator_name', 'photo_path',
        'total_fee', 'payment_type', 'terms_count',
    ];

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'months_requested' => 'integer',
            'date_of_birth' => 'date',
            'passport_validity' => 'date',
            'latest_arrival_date' => 'date',
            'latest_extension_validity' => 'date',
            'acr_validity' => 'date',
            'filed_on' => 'date',
            'checklist' => 'array',
            'documents' => 'array',
            'total_fee' => 'decimal:2',
            'terms_count' => 'integer',
            'overstayed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $application) {
            $application->reference ??= self::newReference();
        });
    }

    public static function newReference(): string
    {
        do {
            $reference = 'TVE-'.now()->format('Y').'-'.strtoupper(Str::random(5));
        } while (self::withoutGlobalScopes()->where('reference', $reference)->exists());

        return $reference;
    }

    /**
     * A client's folder is their passport: the number with spacing, dashes and
     * case ignored, so "gb 123-4567" and "GB1234567" are the same client.
     */
    public static function passportKey(?string $number): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $number));
    }

    public function getPassportKeyAttribute(): string
    {
        return self::passportKey($this->passport_number);
    }

    public function scopeForPassport(Builder $query, string $key): Builder
    {
        return $query->whereRaw(
            "UPPER(REPLACE(REPLACE(REPLACE(passport_number, ' ', ''), '-', ''), '.', '')) = ?",
            [self::passportKey($key)]
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** "Last name, First name, Middle name", as the BI forms ask for it. */
    public function getFormalNameAttribute(): string
    {
        return collect([$this->last_name, $this->first_name, $this->middle_name])
            ->filter(fn ($part) => filled($part))
            ->implode(', ');
    }

    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->first_name, $this->middle_name, $this->last_name])->filter()->implode(' '));
    }

    /** A requirement is in hand when it is ticked or a file was uploaded for it. */
    public function hasChecked(string $item): bool
    {
        return in_array($item, $this->checklist ?? [], true) || $this->documentFor($item) !== null;
    }

    /**
     * The file uploaded for a checklist requirement, if any.
     *
     * @return array{path: string, name: string, size: int, mime: string}|null
     */
    public function documentFor(string $item): ?array
    {
        $document = ($this->documents ?? [])[$item] ?? null;

        return is_array($document) && filled($document['path'] ?? null) ? $document : null;
    }

    /** "245 KB", "1.4 MB": written out here because the web server's PHP may lack the intl extension. */
    public static function fileSizeLabel(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => number_format($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TouristVisaPayment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function amountPaid(): float
    {
        return round((float) ($this->relationLoaded('payments') ? $this->payments->sum('amount') : $this->payments()->sum('amount')), 2);
    }

    public function balance(): float
    {
        return round(max(0, (float) $this->total_fee - $this->amountPaid()), 2);
    }

    /** none (no fee set), unpaid, partial or paid. */
    public function paymentStatus(): string
    {
        $total = (float) $this->total_fee;
        $paid = $this->amountPaid();

        return match (true) {
            $total <= 0 && $paid <= 0 => 'none',
            $total > 0 && $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            default => 'unpaid',
        };
    }

    /**
     * The fee split into its terms, in centavos so the parts add up exactly:
     * the first term takes any remainder. Each shows how much of it the
     * payments so far cover.
     *
     * @return list<array{number: int, amount: float, paid: float, status: string}>
     */
    public function installments(): array
    {
        $count = (int) $this->terms_count;
        $cents = (int) round((float) $this->total_fee * 100);

        if ($this->payment_type !== 'terms' || $count < self::MIN_TERMS || $cents <= 0) {
            return [];
        }

        $each = intdiv($cents, $count);
        $remaining = (int) round($this->amountPaid() * 100);
        $terms = [];

        for ($number = 1; $number <= $count; $number++) {
            $due = $number === 1 ? $cents - $each * ($count - 1) : $each;
            $covered = min($remaining, $due);
            $remaining -= $covered;

            $terms[] = [
                'number' => $number,
                'amount' => $due / 100,
                'paid' => $covered / 100,
                'status' => $covered >= $due ? 'paid' : ($covered > 0 ? 'partial' : 'unpaid'),
            ];
        }

        return $terms;
    }

    /**
     * The checklist items this application needs: the overstay letter only
     * when the applicant overstayed.
     *
     * @return array<string, string>
     */
    public function requiredChecklist(): array
    {
        return self::CHECKLIST + ($this->overstayed ? self::OVERSTAY_CHECKLIST : []);
    }

    /** The required items not ticked yet ("if applicable" ones are never missing). */
    public function missingChecklist(): array
    {
        return collect($this->requiredChecklist())
            ->reject(fn ($label, $key) => $this->hasChecked($key) || in_array($key, self::CHECKLIST_IF_APPLICABLE, true))
            ->all();
    }

    /** How a missing requirement reads in the next-step banner. */
    private const CHECKLIST_NAMES = [
        'cgaf' => 'the application form (CGAF)',
        'passport' => 'the original passport',
        'downgrading_order' => 'the downgrading order',
        'overstay_letter' => 'the notarized letter of explanation',
    ];

    /**
     * What the counter does next on this application, for the banner at the
     * top of its page: the documents, then the fee, then the payment, then
     * filing it with the BI.
     *
     * @return array{state: string, title: string, detail: ?string, anchor: ?string, action: ?string, step: int, steps: int, next: ?string}
     */
    public function nextStep(): array
    {
        $steps = ['steps' => 4, 'next' => null];

        $missing = array_map(fn (string $key) => self::CHECKLIST_NAMES[$key] ?? $key, array_keys($this->missingChecklist()));
        if ($this->overstayed && blank($this->photo_path)) {
            $missing[] = 'the 2x2 photo';
        }

        if ($missing !== []) {
            return ['state' => 'todo', 'title' => 'Collect the documents', 'detail' => 'Still missing: '.implode(', ', $missing).'.', 'anchor' => 'checklist', 'action' => 'Go to the checklist', 'step' => 1] + $steps;
        }

        if ((float) $this->total_fee <= 0) {
            return ['state' => 'todo', 'title' => 'Set the fee', 'detail' => 'Enter the total fee, and whether it is paid in full or in terms.', 'anchor' => 'payment', 'action' => 'Go to payment', 'step' => 2] + $steps;
        }

        if ($this->balance() > 0) {
            $owed = 'PHP '.number_format($this->balance(), 2).' is still owed.';
            $term = collect($this->installments())->first(fn (array $term) => $term['status'] !== 'paid');

            if ($term) {
                $owed .= ' Payment '.$term['number'].' of '.$this->terms_count.': PHP '.number_format($term['amount'] - $term['paid'], 2).' to collect.';
            }

            return ['state' => 'todo', 'title' => 'Collect the payment', 'detail' => $owed, 'anchor' => 'payment', 'action' => 'Go to payment', 'step' => 3] + $steps;
        }

        return [
            'state' => 'ready', 'title' => 'Ready to file with the BI',
            'detail' => 'Print the application form and the checklist, and file them at the Bureau of Immigration. Give the client the claim slip.',
            'anchor' => null, 'action' => null, 'step' => 4,
        ] + $steps;
    }

    /**
     * A date split the way the BI forms print it: DD, MMM, YYYY ("01 JAN 1990").
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function dateParts(?Carbon $date): array
    {
        return $date
            ? [$date->format('d'), strtoupper($date->format('M')), $date->format('Y')]
            : ['', '', ''];
    }

    public static function formDate(?Carbon $date): string
    {
        return $date ? strtoupper($date->format('d M Y')) : '';
    }
}
