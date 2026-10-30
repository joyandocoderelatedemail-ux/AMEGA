<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A desk form saved as pending: the fields as they were left, and the form they reopen in.
 * Each staff member sees only their own; an admin sees everyone's.
 */
class DeskDraft extends Model
{
    public const DESKS = ['immigration', 'visa', 'srrv'];

    protected $fillable = ['desk', 'title', 'client_name', 'form_url', 'payload', 'created_by'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The desk's pending forms this user may continue. */
    public function scopeVisibleTo(Builder $query, ?User $user, string $desk): Builder
    {
        return $query->where('desk', $desk)
            ->when(! $user?->isAdmin(), fn (Builder $mine) => $mine->where('created_by', $user?->id));
    }

    /** @return Collection<int, self> */
    public static function listFor(string $desk): Collection
    {
        return self::visibleTo(auth()->user(), $desk)->with('creator')->latest('updated_at')->get();
    }

    public function isVisibleTo(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $this->created_by === $user->id);
    }
}
