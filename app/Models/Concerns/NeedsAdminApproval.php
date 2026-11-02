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

    /** The statuses that mean the admin is holding the file. */
    public const APPROVAL_HELD = [self::APPROVAL_PENDING, self::APPROVAL_RETURNED];

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

    /**
     * Why a step that needs the admin's go-ahead cannot be taken yet, or null when it can.
     * $doing reads as "printed", "advanced" and so on.
     */
    public function approvalBlocker(string $doing): ?string
    {
        return match (true) {
            $this->isAwaitingApproval() => "This file is waiting for the admin's approval, so it cannot be {$doing} yet.",
            $this->isReturned() => 'The admin returned this file'.($this->review_note ? ": {$this->review_note}" : '.')." Correct it and save to send it again before it is {$doing}.",
            default => null,
        };
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

    /** Files the admin is holding: waiting for approval, or returned to the agent. */
    public function scopeHeld(Builder $query): void
    {
        $query->whereIn('approval_status', self::APPROVAL_HELD);
    }

    /**
     * What is still being worked on, plus every held file whatever its status (a released file that
     * is waiting for approval is still waiting). Cancelled files never count. Needs a `status` column.
     *
     * @param  list<string>  $closed  the statuses that mean the work is finished
     */
    public function scopeOpenOrHeld(Builder $query, array $closed): void
    {
        $query->where('status', '!=', 'cancelled')
            ->where(fn (Builder $q) => $q->whereNotIn('status', $closed)->orWhereIn('approval_status', self::APPROVAL_HELD));
    }

    /**
     * The order a desk's service list shows: held files first (waiting, then returned), then the work in
     * progress, then the finished files. Needs a `status` column.
     *
     * @param  list<string>  $closed  the statuses that mean the work is finished
     */
    public function scopeOrderForList(Builder $query, array $closed): void
    {
        $marks = implode(', ', array_fill(0, max(count($closed), 1), '?'));
        $query->orderByRaw(
            self::heldFirstSql("status IN ({$marks})"),
            $closed === [] ? [''] : $closed
        );
    }

    /**
     * Order in SQL, before any limit: held files first (waiting, then returned), then the work not yet
     * finished, then the finished files. $finishedSql is the condition that means "finished".
     */
    public function scopeOrderHeldFirst(Builder $query, string $finishedSql): void
    {
        $query->orderByRaw(self::heldFirstSql($finishedSql));
    }

    private static function heldFirstSql(string $finishedSql): string
    {
        return "CASE approval_status WHEN '".self::APPROVAL_PENDING."' THEN 0 WHEN '".self::APPROVAL_RETURNED."' THEN 1 ELSE CASE WHEN {$finishedSql} THEN 3 ELSE 2 END END";
    }

    public function scopeApprovalIs(Builder $query, string $status): void
    {
        $query->where('approval_status', $status);
    }
}
