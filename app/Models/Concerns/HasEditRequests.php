<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A released Immigration case is locked. The agent who needs a change asks the admin to unlock it, with a
 * reason; the request is pending until the admin decides. Approving unlocks the case and clears the request;
 * declining keeps it locked and stores the admin's note, and the agent may ask again.
 * Only the three case models use this: their tables carry the edit_request_* columns.
 */
trait HasEditRequests
{
    public function initializeHasEditRequests(): void
    {
        $this->mergeCasts([
            'edit_requested_at' => 'datetime',
            'edit_request_replied_at' => 'datetime',
            'status_unlocked_at' => 'datetime',
        ]);
    }

    public function editRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edit_requested_by');
    }

    /** An edit request the admin has not answered yet. */
    public function hasEditRequest(): bool
    {
        return $this->edit_requested_at !== null && $this->edit_request_replied_at === null;
    }

    /** The admin declined the last request: the agent sees the reply and may ask again. */
    public function editRequestDeclined(): bool
    {
        return $this->edit_requested_at !== null && $this->edit_request_replied_at !== null;
    }

    /** An approved case: its content is locked for agents, who must ask the admin to unlock it. */
    public function isApprovedLocked(): bool
    {
        return $this->approval_status === 'approved';
    }

    /** Whether this case's details are locked for an agent: released, or approved (only a released one is asked about with Request edit). */
    public function needsEditRequest(): bool
    {
        return $this->released_on !== null || $this->isApprovedLocked();
    }

    /**
     * The Bureau's Approved status is locked for agents: once picked it stays until the admin approves a status-change
     * request (which sets status_unlocked_at). Released files have their own lock; held files are not locked this way.
     */
    public function approvedStatusLocked(): bool
    {
        return $this->progressKey() === 'approved'
            && $this->status_unlocked_at === null
            && $this->released_on === null
            && $this->isCleared();
    }

    /** The request is only to change the status of a released file: approving it reopens the file, its details stay locked. */
    public function editRequestIsStatusOnly(): bool
    {
        return $this->edit_request_type === 'status';
    }

    /** @param  string  $type  'details' (correct the file) or 'status' (reopen a released file to change its status) */
    public function requestEdit(User $by, string $note, string $type = 'details'): void
    {
        $this->forceFill([
            'edit_request_type' => $type,
            'edit_requested_at' => now(),
            'edit_requested_by' => $by->id,
            'edit_request_note' => $note,
            'edit_request_reply' => null,
            'edit_request_replied_at' => null,
        ])->save();
    }

    public function declineEdit(string $reply): void
    {
        $this->forceFill(['edit_request_reply' => $reply, 'edit_request_replied_at' => now()])->save();
    }

    /** Back to no request. Does not save: callers fold it into their own update. */
    public static function clearedEditRequest(): array
    {
        return [
            'edit_request_type' => null,
            'edit_requested_at' => null,
            'edit_requested_by' => null,
            'edit_request_note' => null,
            'edit_request_reply' => null,
            'edit_request_replied_at' => null,
        ];
    }

    public function clearEditRequest(): void
    {
        $this->forceFill(self::clearedEditRequest())->save();
    }

    /** Requests waiting for the admin. */
    public function scopeEditRequested(Builder $query): void
    {
        $query->whereNotNull('edit_requested_at')->whereNull('edit_request_replied_at');
    }

    /** Requests the admin declined. */
    public function scopeEditDeclined(Builder $query): void
    {
        $query->whereNotNull('edit_requested_at')->whereNotNull('edit_request_replied_at');
    }
}
