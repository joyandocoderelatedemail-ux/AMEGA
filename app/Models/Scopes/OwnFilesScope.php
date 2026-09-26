<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Desk files are private to the staff member who opened them: a ticketing,
 * visa, SRRV officer or agent only ever loads files they created, so another
 * account's files never appear in lists, counts or searches, and opening one
 * by its address is a 404. Admins see every file; clients are unaffected.
 *
 * Work that must see every file whoever triggers it (the client account and
 * CRM syncs) opts out with withoutGlobalScope(OwnFilesScope::class).
 */
class OwnFilesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user instanceof User && $user->seesOnlyOwnFiles()) {
            $builder->where($model->qualifyColumn('created_by'), $user->id);
        }
    }
}
