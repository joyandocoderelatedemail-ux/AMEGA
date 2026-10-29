<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Who checks a desk file (maker-checker). Approving is the desk's "approve" permission: admins have it,
 * and an admin can give it to a supervisor. Nobody approves a file they sent themselves. The one
 * exception keeps a small team moving: when the person is the only approver on that desk, their own
 * saves are approved on the spot, as before, and logged as self-approved.
 */
class Approvals
{
    /** Everyone who can approve this desk's files ('ticketing', 'immigration', 'visa_assistance', 'srrv'). */
    public static function approvers(string $desk): Collection
    {
        return User::query()
            ->whereIn('role', ['admin', 'agent', 'ticketing', 'visa_assistance', 'srrv', 'cashier'])
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($desk.'.approve'))
            ->values();
    }

    /** Whether this person approves their own saves on the spot: an approver with no other approver on the desk. */
    public static function selfApproves(User $user, string $desk): bool
    {
        return $user->hasPermission($desk.'.approve')
            && self::approvers($desk)->reject(fn (User $approver) => $approver->is($user))->isEmpty();
    }

    /** Who sent a file for approval: whoever last submitted it, otherwise who opened it. */
    public static function makerOf(Model $file): ?int
    {
        $maker = $file->getAttribute('acknowledged_by') ?? $file->getAttribute('agent_acknowledged_by') ?? $file->getAttribute('created_by');

        return $maker === null ? null : (int) $maker;
    }

    /** Why this person may not approve this file, or null when they may. */
    public static function refusal(User $user, string $desk, Model $file): ?string
    {
        if (! $user->hasPermission($desk.'.approve')) {
            return 'You do not have the Approve permission for this desk.';
        }

        if (self::makerOf($file) === $user->id && ! self::selfApproves($user, $desk)) {
            return 'You sent this file yourself, so another approver must approve it.';
        }

        return null;
    }

    /** The approvers to email about a file, leaving out the person who sent it. */
    public static function recipients(string $desk, ?User $except = null): Collection
    {
        return self::approvers($desk)
            ->filter(fn (User $approver) => filled($approver->email) && ! ($except && $approver->is($except)))
            ->values();
    }
}
