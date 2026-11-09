<?php

namespace App\Models;

use App\Models\Scopes\OwnFilesScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price quoted at the Immigration counter before a file is opened: the services the client asked about, with
 * the BI rates and AMEGA's fees, valid until a date. Once the client agrees, each service line opens its file in
 * the client's folder with the quoted fee filled in.
 */
#[ScopedBy([OwnFilesScope::class])]
class ImmigrationQuotation extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    /** Shown status => [label, tone]; "expired" is an open quotation past its date. */
    public const STATUS_LABELS = [
        'open' => ['Open', 'amber'],
        'accepted' => ['Accepted', 'emerald'],
        'declined' => ['Declined', 'rose'],
        'expired' => ['Expired', 'slate'],
    ];

    /** How long a new quotation stays valid unless the agent changes it. */
    public const DEFAULT_VALID_DAYS = 15;

    protected $fillable = [
        'immigration_client_id',
        'user_id',
        'client_name',
        'client_email',
        'client_phone',
        'nationality',
        'passport_number',
        'valid_until',
        'status',
        'subtotal',
        'discount',
        'total',
        'notes',
        'accepted_at',
        'declined_at',
        'emailed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public static function ownFilesDesk(): string
    {
        return 'immigration';
    }

    protected static function booted(): void
    {
        // IQ-2026-0001: numbered from the row, so two agents saving at once never share a number.
        static::created(function (ImmigrationQuotation $quotation): void {
            $quotation->quote_number = sprintf('IQ-%s-%04d', $quotation->created_at?->format('Y') ?? now()->format('Y'), $quotation->id);
            $quotation->saveQuietly();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImmigrationQuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function immigrationClient(): BelongsTo
    {
        return $this->belongsTo(ImmigrationClient::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->valid_until !== null && $this->valid_until->lt(today());
    }

    /** open, accepted, declined or expired. */
    public function statusKey(): string
    {
        return $this->isExpired() ? 'expired' : $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->statusKey()][0] ?? ucfirst($this->status);
    }

    public function statusTone(): string
    {
        return self::STATUS_LABELS[$this->statusKey()][1] ?? 'slate';
    }

    /** Still being discussed: it can be edited, accepted or declined. */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Add up the lines, then take off the discount (never below zero). */
    public function recalculate(): void
    {
        $subtotal = round((float) $this->items()->sum('amount'), 2);
        $discount = min(round((float) $this->discount, 2), $subtotal);

        $this->forceFill(['subtotal' => $subtotal, 'discount' => $discount, 'total' => round($subtotal - $discount, 2)])->save();
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(fn (Builder $q) => $q->where('quote_number', 'like', $like)
            ->orWhere('client_name', 'like', $like)
            ->orWhere('passport_number', 'like', $like)
            ->orWhere('client_email', 'like', $like)
            ->orWhere('client_phone', 'like', $like));
    }

    /** Filter by the status shown, where "open" leaves out the expired ones. */
    public function scopeShowing(Builder $query, string $status): Builder
    {
        return match ($status) {
            'open' => $query->where('status', self::STATUS_OPEN)->whereDate('valid_until', '>=', today()),
            'expired' => $query->where('status', self::STATUS_OPEN)->whereDate('valid_until', '<', today()),
            'accepted', 'declined' => $query->where('status', $status),
            default => $query,
        };
    }
}
