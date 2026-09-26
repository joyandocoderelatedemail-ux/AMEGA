<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admins take a desk file on themselves or hand it to a staff member at that
 * desk. Desk files are private to whoever owns them (OwnFilesScope), so this is
 * how a file is passed on when someone is away or leaves, or picked up when its
 * owner's account is removed.
 */
class FileOwnerController extends Controller
{
    /**
     * The desk files that can be reassigned: their model, the desk whose
     * staff may own them, and how they are named in the activity log.
     *
     * @var array<string, array{0: class-string<Model>, 1: string, 2: string}>
     */
    public const FILES = [
        'ticket' => [TicketBooking::class, 'ticketing', 'booking_reference'],
        'visa' => [VisaApplication::class, 'visa_assistance', 'reference'],
        'srrv' => [SrrvApplication::class, 'srrv', 'reference'],
        'srrv-renewal' => [SrrvRenewal::class, 'srrv', 'reference'],
    ];

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(isset(self::FILES[$type]), 404);

        [$model, $desk, $referenceColumn] = self::FILES[$type];

        // Admins are never limited to their own files, so this finds any file.
        $file = $model::findOrFail($id);

        $validated = $request->validate([
            'created_by' => ['required', 'integer', Rule::in(User::deskStaff($desk)->pluck('id'))],
        ], [
            'created_by.in' => 'Choose a staff member who works this desk.',
        ]);

        $previous = User::find($file->created_by)?->name ?? 'no owner';
        $owner = User::findOrFail($validated['created_by']);

        $file->created_by = $owner->id;
        $file->save();

        ActivityLogger::log('Files', 'REASSIGN', "Reassigned {$file->{$referenceColumn}} from {$previous} to {$owner->name}");

        return back()->with('success', $owner->is($request->user())
            ? 'You now own this file.'
            : "This file now belongs to {$owner->name}.");
    }
}
