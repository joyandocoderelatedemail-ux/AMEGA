<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * An immigration case (extension, overstay, re-stamping, petition) is visible only to those who can
 * see its client folder (OwnFilesScope on ImmigrationClient), so a case list or a case opened by its
 * address never shows another agent's client.
 */
class InVisibleFolderScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if ($user instanceof User && $user->seesOnlyOwnFiles()
            && ! $user->hasPermission('immigration.view_all') && ! $user->hasPermission('immigration.approve')) {
            $builder->whereHas('client');
        }
    }
}
