<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ScopedBy([OwnFilesScope::class])]
class TicketBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_reference',
        'created_by',
        'user_id',
        'travel_type',
        'package_type',
        'travel_package_id',
        'package_name',
        'origin',
        'destination',
        'trip_type',
        'departure_date',
        'return_date',
        'total_passengers',
        'adults_count',
        'children_count',
        'infants_count',
        'contact_name',
        'contact_email',
        'contact_phone',
        'travel_tax_included',
        'total_amount',
        'status',
        'special_requests',
        // Phase 2 International fields
        'destination_country',
        'destination_city',
        'arrival_airport',
        'preferred_airline',
        'travel_class',
        'preferred_flight_time',
        'multi_city_segments',
        'has_insurance',
        'insurance_plan',
        'selected_services',
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
        'emergency_contact_email',
        'special_requests_list',
        'estimated_fare',
        'taxes_amount',
        'visa_assistance_fee',
        'insurance_fee',
        'other_charges',
        // Payment and issuance
        'payment_status',
        'amount_paid',
        'paid_at',
        'issued_at',
        'issued_by',
        'consent_accepted_at',
        'consent_accepted_by',
        'is_quotation',
        'custom_package_specs',
    ];

    /** Money received against the booking. */
    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIAL = 'partially_paid';

    public const PAYMENT_FULL = 'fully_paid';

    /**
     * Mirror the database defaults so a freshly created model reports the same
     * values in memory as it would after a refresh — otherwise `payment_status`
     * reads as null until the row is re-fetched.
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'payment_status' => self::PAYMENT_UNPAID,
        'amount_paid' => 0,
        'is_quotation' => false,
    ];

    /** Booking lifecycle, independent of payment. */
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'return_date' => 'date',
            'paid_at' => 'datetime',
            'issued_at' => 'datetime',
            'consent_accepted_at' => 'datetime',
            'is_quotation' => 'boolean',
            'amount_paid' => 'decimal:2',
            'total_passengers' => 'integer',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'infants_count' => 'integer',
            'travel_tax_included' => 'boolean',
            'has_insurance' => 'boolean',
            'multi_city_segments' => 'array',
            'selected_services' => 'array',
            'special_requests_list' => 'array',
            'custom_package_specs' => 'array',
            'total_amount' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'taxes_amount' => 'decimal:2',
            'visa_assistance_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
            'other_charges' => 'decimal:2',
        ];
    }

    public function isCustomPackage(): bool
    {
        return $this->package_type === 'custom_package' || ! empty($this->custom_package_specs);
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(TicketPassenger::class)->orderBy('passenger_number');
    }

    public function documents(): HasManyThrough
    {
        return $this->hasManyThrough(TicketPassengerDocument::class, TicketPassenger::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function travelPackage(): BelongsTo
    {
        return $this->belongsTo(TravelPackage::class);
    }

    public function bookingAgreement(): HasOne
    {
        return $this->hasOne(BookingAgreement::class, 'ticket_booking_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function consentAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_accepted_by');
    }

    /**
     * What is still owed on this booking.
     */
    public function balanceDue(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_FULL;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * A quotation was saved without the document checks, so it is priced but
     * not yet a complete booking.
     */
    public function isQuotation(): bool
    {
        return (bool) $this->is_quotation;
    }

    /**
     * Full payment is necessary but not sufficient — issuance stays a separate,
     * deliberate step so a paid booking is never silently turned into a ticket.
     */
    public function canBeIssued(): bool
    {
        return $this->isFullyPaid()
            && ! $this->isIssued()
            && ! $this->isCancelled()
            // A quotation skipped the document rules, so it must be completed
            // as a full booking before it can become a ticket.
            && ! $this->isQuotation();
    }

    /**
     * Derive the payment status from the amount recorded against the total.
     *
     * A booking with no total on it yet cannot be considered settled, otherwise
     * a zero total would make every new booking instantly issuable.
     */
    public function recordPayment(float $amountPaid): void
    {
        $total = (float) $this->total_amount;
        $amountPaid = max(0, round($amountPaid, 2));

        $this->amount_paid = $amountPaid;

        if ($total > 0 && $amountPaid >= $total) {
            $this->payment_status = self::PAYMENT_FULL;
            $this->paid_at = $this->paid_at ?? now();
        } elseif ($amountPaid > 0) {
            $this->payment_status = self::PAYMENT_PARTIAL;
            $this->paid_at = null;
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
            $this->paid_at = null;
        }

        // Taking money confirms the booking, but never issues it.
        if ($this->status === self::STATUS_PENDING && $amountPaid > 0) {
            $this->status = self::STATUS_CONFIRMED;
        }

        $this->save();
    }

    /**
     * Issue the ticket, recording who consented and who issued it.
     */
    public function markAsIssued(User $staff): void
    {
        $this->forceFill([
            'status' => self::STATUS_ISSUED,
            'issued_at' => now(),
            'issued_by' => $staff->id,
            'consent_accepted_at' => $this->consent_accepted_at ?? now(),
            'consent_accepted_by' => $this->consent_accepted_by ?? $staff->id,
        ])->save();
    }

    /**
     * Generate unique reference code for tickets.
     */
    public static function generateReference(string $type = 'DOM'): string
    {
        $prefix = strtoupper($type) === 'DOM' ? 'TKT-DOM' : 'TKT-INT';
        $timestamp = date('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));

        return "{$prefix}-{$timestamp}-{$random}";
    }
}
