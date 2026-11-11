<?php

namespace App\Models;

use App\Models\Concerns\NeedsAdminApproval;
use App\Models\Scopes\OwnFilesScope;
use App\Support\LocalTours;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A local tour booked for a client at the Local Tours desk (Puning Hot Spring, Corregidor Island,
 * the Angeles City tour, Camayan Beach Resort: see App\Support\LocalTours).
 *
 * It follows the booking procedure step by step. Each stage has tasks to tick and details to fill;
 * the booking moves on only when they are done:
 *
 *   availability -> quotation -> guest details -> [approver] -> supplier booking
 *     -> [cashier: paid in full] -> voucher -> driver and final instructions -> completed
 *
 * Leaving Guest Details sends the booking to an approver (maker-checker). Nothing is submitted or
 * paid to the supplier until it is approved, and the voucher is released only once the cashier has
 * recorded payment in full.
 */
#[ScopedBy([OwnFilesScope::class])]
class LocalTourBooking extends Model
{
    use NeedsAdminApproval;

    /** The pipeline, in order; cancelling takes a booking off it. */
    public const STAGES = [
        'availability' => 'Availability & Rates',
        'quotation' => 'Quotation & Agreement',
        'guest_details' => 'Guest Details & IDs',
        'supplier' => 'Supplier Booking',
        'voucher' => 'Voucher',
        'transport' => 'Driver & Final Instructions',
        'completed' => 'Completed',
    ];

    public const FINISHED_STATUSES = ['completed', 'cancelled'];

    /** Details an approver signed off on: changing one sends the booking back to them. */
    public const APPROVED_DETAILS = ['tour', 'lead_guest_name', 'adults', 'children', 'travel_date', 'total_amount', 'transport'];

    protected $fillable = [
        'reference', 'tour', 'created_by', 'user_id', 'status',
        'lead_guest_name', 'contact_phone', 'contact_email', 'nationality', 'adults', 'children', 'children_ages',
        'travel_date', 'pickup_time', 'pickup_location', 'dropoff_location', 'attractions', 'special_requests',
        'total_amount', 'supplier_cost', 'supplier_paid_amount', 'supplier_payment_reference', 'supplier_confirmation',
        'transport', 'driver', 'vehicle', 'checklist', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'adults' => 'integer',
            'children' => 'integer',
            'travel_date' => 'date',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'supplier_cost' => 'decimal:2',
            'supplier_paid_amount' => 'decimal:2',
            'checklist' => 'array',
            'payment_in_full_at' => 'datetime',
            'agreement_at' => 'datetime',
            'voucher_released_at' => 'datetime',
            'completed_at' => 'datetime',
            'approval_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** The desk whose "view_all" permission lifts OwnFilesScope. */
    public static function ownFilesDesk(): string
    {
        return 'local_tours';
    }

    public static function generateReference(): string
    {
        return 'LT-'.date('Y').'-'.strtoupper(substr(uniqid(), -5));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LocalTourPayment::class)->latest('received_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LocalTourDocument::class)->latest('id');
    }

    /** Approved bookings with money still to collect: what the cashier works on. */
    public function scopeAwaitingPayment(Builder $query): void
    {
        $query->where('approval_status', self::APPROVAL_APPROVED)
            ->where('status', '!=', 'cancelled')
            ->where('total_amount', '>', 0)
            ->whereColumn('amount_paid', '<', 'total_amount');
    }

    /** @return array<string, mixed> */
    public function tourInfo(): array
    {
        return LocalTours::tour($this->tour) ?? ['label' => 'Local Tour', 'supplier' => 'the supplier', 'phone' => null, 'contact_label' => 'Supplier', 'nationality' => 'if_required', 'asks' => [], 'guest_info' => [], 'checks' => [], 'important' => null, 'accreditation' => null, 'icon' => 'map'];
    }

    public function tourLabel(): string
    {
        return $this->tourInfo()['label'];
    }

    public function supplierName(): string
    {
        return $this->tourInfo()['supplier'];
    }

    public function guests(): int
    {
        return (int) $this->adults + (int) $this->children;
    }

    public function guestsLabel(): string
    {
        return collect([
            $this->adults.' '.((int) $this->adults === 1 ? 'adult' : 'adults'),
            (int) $this->children > 0 ? $this->children.' '.((int) $this->children === 1 ? 'child' : 'children') : null,
        ])->filter()->implode(', ');
    }

    public function usesAmegaTransport(): bool
    {
        return $this->transport === 'amega';
    }

    public function stageLabel(string $stage): string
    {
        return self::STAGES[$stage] ?? ucfirst($stage);
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status === 'cancelled' ? 'Cancelled' : $this->stageLabel((string) $this->status);
    }

    public function stageIndex(): int
    {
        $index = array_search($this->status, array_keys(self::STAGES), true);

        return $index === false ? 0 : $index;
    }

    public function nextStage(): ?string
    {
        $keys = array_keys(self::STAGES);

        return $keys[$this->stageIndex() + 1] ?? null;
    }

    /** Completed or cancelled: nothing further happens on it. */
    public function isFinal(): bool
    {
        return in_array($this->status, self::FINISHED_STATUSES, true);
    }

    /** Whether the booking has reached this stage (or gone past it). */
    public function hasReached(string $stage): bool
    {
        $index = array_search($stage, array_keys(self::STAGES), true);

        return $this->status !== 'cancelled' && $index !== false && $this->stageIndex() >= $index;
    }

    public function outstandingBalance(): float
    {
        return max(0, round((float) $this->total_amount - (float) $this->amount_paid, 2));
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->total_amount > 0 && $this->outstandingBalance() <= 0;
    }

    public function hasTicked(string $task): bool
    {
        return in_array($task, $this->checklist ?? [], true);
    }

    /**
     * The tasks to tick at a stage, for this booking's tour and transport: the steps every tour
     * shares, plus the tour's own checks.
     *
     * @return array<string, string> key => label
     */
    public function tasks(?string $stage = null): array
    {
        $stage ??= (string) $this->status;
        $supplier = $this->supplierName();
        $guide = $this->tour === 'angeles_city';

        $shared = match ($stage) {
            'availability' => [
                'availability_checked' => $guide ? "Checked the tour guide's availability and professional fee" : "Checked availability and rates with {$supplier}",
                'guests_transport_confirmed' => 'Confirmed the total number of guests and the transportation arrangements',
            ],
            'quotation' => [
                'quotation_presented' => 'Presented the quotation to the client',
                'agreement_secured' => "Secured the client's booking agreement",
            ],
            'guest_details' => [
                'details_collected' => 'Collected the required guest details and checked the names against the IDs',
            ],
            'supplier' => $guide
                ? [
                    'details_submitted' => 'Confirmed the itinerary and schedule with the tour guide',
                    'supplier_settled' => "Agreed the guide's fee and payment arrangements directly",
                    'confirmation_received' => 'Received final confirmation from the tour guide',
                ]
                : [
                    'details_submitted' => "Submitted the booking details to {$supplier}",
                    'supplier_settled' => "Booked and settled payment with {$supplier}, after verifying the payment instructions",
                    'confirmation_received' => 'Received the official booking confirmation or voucher',
                ],
            'voucher' => [
                'voucher_checked' => 'Checked the booking details against the supplier confirmation',
                'voucher_released' => 'Prepared and released the AMEGA booking voucher/order to the client',
            ],
            'transport' => ($this->usesAmegaTransport() ? [
                'driver_briefed' => 'Gave the driver the travel date, pick-up time, guest name and number, passengers, locations, itinerary and special instructions',
                'driver_accepted' => 'The driver accepted the assignment',
                'transport_reconfirmed' => 'Reconfirmed the transportation arrangements two days before the tour',
            ] : []) + [
                'instructions_sent' => $this->usesAmegaTransport()
                    ? "Sent the client the pick-up time, the driver's name and number, emergency contacts and travel reminders"
                    : 'Sent the client the final meeting-point instructions, departure time, emergency contacts and travel reminders',
            ],
            default => [],
        };

        return $shared + ($this->tourInfo()['checks'][$stage] ?? []);
    }

    /** Why the booking cannot leave its current stage yet, or null when it can. */
    public function stageBlocker(): ?string
    {
        if ($this->isFinal()) {
            return 'This booking is '.strtolower($this->status_label).'.';
        }

        $missing = array_diff_key($this->tasks(), array_flip($this->checklist ?? []));
        if ($missing !== []) {
            return 'Still to do at this step: '.implode('; ', $missing).'.';
        }

        return match ($this->status) {
            'availability' => match (true) {
                $this->travel_date === null => 'Enter the travel date before moving on.',
                (float) $this->total_amount <= 0 => 'Enter the amount quoted to the client before moving on.',
                default => null,
            },
            'guest_details' => match (true) {
                blank($this->contact_phone) => "Enter the lead guest's contact number.",
                blank($this->pickup_location) || blank($this->dropoff_location) => 'Enter the pick-up and drop-off locations.',
                $this->tourInfo()['nationality'] === 'required' && blank($this->nationality) => "Enter the guests' nationality: this supplier requires it.",
                ! $this->documents()->where('kind', 'guest_id')->exists() => "Upload at least one guest's valid ID.",
                default => null,
            },
            'supplier' => $this->approvalBlocker('booked with the supplier')
                ?? (blank($this->supplier_confirmation) ? "Enter the supplier's confirmation or voucher number." : null),
            'voucher' => $this->voucherBlocker(),
            'transport' => $this->usesAmegaTransport() && blank($this->driver) ? 'Assign an AMEGA driver.' : null,
            default => null,
        };
    }

    /** Why the AMEGA voucher cannot be released (or printed) yet, or null when it can. */
    public function voucherBlocker(): ?string
    {
        return match (true) {
            $this->status === 'cancelled' => 'This booking was cancelled.',
            ! $this->hasReached('voucher') => 'The voucher is prepared once the supplier has confirmed the booking.',
            $this->approvalBlocker('released') !== null => $this->approvalBlocker('released'),
            ! $this->isFullyPaid() => 'The voucher is released once the cashier has recorded payment in full. Balance: ₱'.number_format($this->outstandingBalance(), 2).'.',
            default => null,
        };
    }
}
