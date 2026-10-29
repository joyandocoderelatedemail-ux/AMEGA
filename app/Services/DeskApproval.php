<?php

namespace App\Services;

use App\Models\Concerns\NeedsAdminApproval;
use App\Models\User;
use App\Notifications\DeskApprovalRequestedNotification;
use App\Support\Approvals;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

/**
 * Who checks a desk file once it is saved (maker-checker, see App\Support\Approvals). It waits for an
 * approver, who is emailed; nobody approves what they sent. Only the desk's sole approver has their
 * own save approved on the spot (logged as self-approved). The model must use NeedsAdminApproval.
 */
class DeskApproval
{
    /** The desk (permission module) behind each desk name the controllers pass. */
    private const DESKS = [
        'SRRV' => 'srrv',
        'SRRV renewal' => 'srrv',
        'Visa Assistance' => 'visa_assistance',
        'Immigration' => 'immigration',
    ];

    /**
     * @param  Model&NeedsAdminApproval  $file
     * @param  bool  $changed  whether this save changed what is on file; an untouched approved file stays approved
     * @param  string  $approvalsRoute  the approvals page the email links to
     */
    public static function saved(Model $file, User $user, string $desk, string $fileName, string $approvalsRoute, bool $changed = true): void
    {
        $permissionDesk = self::DESKS[$desk] ?? 'srrv';

        if (Approvals::selfApproves($user, $permissionDesk)) {
            if ($changed || ! $file->isCleared() || $file->approval_status === null) {
                $file->approveBy($user);
                $file->save();
                ActivityLogger::log($desk, 'SELF_APPROVE', "Self-approved {$file->reference} ({$user->name} is the only approver for this desk)");
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
                Approvals::recipients($permissionDesk, $user),
                new DeskApprovalRequestedNotification($desk, $fileName, (string) $file->reference, $user->name, route($approvalsRoute), $resubmitted),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
