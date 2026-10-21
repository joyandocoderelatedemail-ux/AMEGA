<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A counter record an agent saves and an admin then checks: approved, or returned with a reason.
 * Records saved before approvals existed have no status and are not held up. An admin's own
 * save is approved on the spot, since there is no one above them to ask.
 */
trait NeedsAdminApproval
{
    public const APPROVAL_PENDING = 'pending';

    public const APPROVAL_APPROVED = 'approved';

    public const APPROVAL_RETURNED = 'returned';

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isAwaitingApproval(): bool
    {
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isReturned(): bool
    {
        return $this->approval_status === self::APPROVAL_RETURNED;
    }

    /** Not waiting on an admin: approved, or saved before approvals existed. */
    public function isCleared(): bool
    {
        return ! $this->isAwaitingApproval() && ! $this->isReturned();
    }

    /** Send it (again) to the admins; the last review is cleared. */
    public function submitForApproval(?User $by = null): void
    {
        $this->approval_status = self::APPROVAL_PENDING;
        $this->approval_requested_at = now();
        $this->reviewed_by = null;
        $this->reviewed_at = null;
        $this->review_note = null;
        $this->acknowledged_by = $by?->id ?? $this->acknowledged_by;
        $this->acknowledged_at = $by ? now() : $this->acknowledged_at;
    }

    /** An admin's own save: approved by them, with their acknowledgement. */
    public function approveBy(User $admin): void
    {
        $this->approval_status = self::APPROVAL_APPROVED;
        $this->approval_requested_at ??= now();
        $this->reviewed_by = $admin->id;
        $this->reviewed_at = now();
        $this->review_note = null;
        $this->acknowledged_by = $admin->id;
        $this->acknowledged_at = now();
    }

    public function scopeApprovalIs(Builder $query, string $status): void
    {
        $query->where('approval_status', $status);
    }
}
