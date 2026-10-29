<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Desk files are private to the staff member who opened them: a ticketing,
 * visa, SRRV or immigration officer or agent only ever loads files they created, so another
 * account's files never appear in lists, counts or searches, and opening one
 * by its address is a 404. Admins, the cashier, and anyone granted the desk's
 * "view_all" permission (a supervisor) see every file; clients are unaffected.
 *
 * A model names its desk with ownFilesDesk(), and may keep files with no
 * recorded owner (from before owners were recorded) visible to the whole desk
 * with ownFilesShowUnowned().
 *
 * Work that must see every file whoever triggers it (the client account and
 * CRM syncs) opts out with withoutGlobalScope(OwnFilesScope::class).
 */
class OwnFilesScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->seesOnlyOwnFiles()) {
            return;
        }

        $desk = method_exists($model, 'ownFilesDesk') ? $model::ownFilesDesk() : null;
        // A supervisor sees every file, and an approver every file they may be asked to approve.
        if ($desk !== null && ($user->hasPermission($desk.'.view_all') || $user->hasPermission($desk.'.approve'))) {
            return;
        }

        $column = $model->qualifyColumn('created_by');

        if (method_exists($model, 'ownFilesShowUnowned') && $model::ownFilesShowUnowned()) {
            $builder->where(fn (Builder $mine) => $mine->where($column, $user->id)->orWhereNull($column));

            return;
        }

        $builder->where($column, $user->id);
    }
}
