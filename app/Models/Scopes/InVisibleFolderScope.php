<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * An immigration case (extension, overstay, re-stamping, petition) is visible only where its client
 * folder is: staff limited to their own folders (OwnFilesScope on ImmigrationClient) see the cases in
 * the folders they can open, so a case list or a case opened by its address never shows another agent's
 * client. For everyone else the folder's own scope lifts, so this only drops a case whose folder no
 * longer exists (left behind when the folder was deleted on a database that does not cascade): nothing
 * can open it, so it is shown to no one and counted nowhere.
 */
class InVisibleFolderScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('client');
    }
}
