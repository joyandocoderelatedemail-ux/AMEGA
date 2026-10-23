<?php

namespace App\Services;

use App\Models\Concerns\NeedsAdminApproval;
use App\Models\User;
use App\Notifications\DeskApprovalRequestedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Who checks a desk file once it is saved: an admin's own save is approved on the spot, since there
 * is no one above them; anyone else's waits for an admin, who is emailed. The model must use
 * NeedsAdminApproval.
 */
class DeskApproval
{
    /**
     * @param  Model&NeedsAdminApproval  $file
     * @param  bool  $changed  whether this save changed what is on file; an untouched approved file stays approved
     * @param  string  $approvalsRoute  the admin approvals page the email links to
     */
    public static function saved(Model $file, User $user, string $desk, string $fileName, string $approvalsRoute, bool $changed = true): void
    {
        if ($user->isAdmin()) {
            if ($changed || ! $file->isCleared() || $file->approval_status === null) {
                $file->approveBy($user);
                $file->save();
            }

            return;
        }

        if (! $changed && ! $file->isReturned()) {
            return;
        }

        $resubmitted = $file->isReturned();
        $file->submitForApproval($user);
        $file->save();

        try {
            Notification::send(
                User::where('role', 'admin')->whereNotNull('email')->get(),
                new DeskApprovalRequestedNotification($desk, $fileName, (string) $file->reference, $user->name, route($approvalsRoute), $resubmitted),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
