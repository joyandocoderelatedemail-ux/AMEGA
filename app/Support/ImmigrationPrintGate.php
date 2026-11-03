<?php

namespace App\Support;

use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The one rule for printing a FILLED Immigration form: an agent cannot print it while the file is waiting for
 * the admin or was returned; approvers never wait. Blank forms are never gated (they hold no client data).
 * The file is the case the link names (service + case) or, for Visa Extension, the folder's ledger rows.
 */
class ImmigrationPrintGate
{
    /** A redirect back to the file with the reason, or null when the form may be printed. */
    public static function held(Request $request, ImmigrationClient $client): ?RedirectResponse
    {
        if ($request->user()->hasPermission('immigration.approve')) {
            return null;
        }

        $key = ImmigrationServices::find($request->input('service'))['key'];
        $case = ImmigrationCases::caseFor($client, $key, $request->integer('case') ?: null);

        if ($case !== null) {
            return self::redirectFor($client, $case);
        }

        if (in_array($key, ['visa_extension', 'visa_overstaying'], true)
            && $client->extensions()->get()->contains(fn (ImmigrationClientExtension $row) => ! $row->isCleared())) {
            return redirect()->route('admin.client-sheets.edit', ['client_sheet' => $client->id])
                ->with('error', 'The new extension is waiting for an admin to approve it. You can print the forms once it is approved.');
        }

        return null;
    }

    /** A redirect back to a case the admin is holding, or null when it is cleared (or not saved yet). */
    public static function redirectFor(ImmigrationClient $client, Model $case): ?RedirectResponse
    {
        if (! $case->exists || $case->isCleared()) {
            return null;
        }

        $service = ImmigrationCases::kinds()[ImmigrationCases::kindOf($case)]['service'];

        return redirect()->route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->id])
            ->with('error', $case->isReturned()
                ? 'The admin returned this file'.($case->review_note ? ': '.$case->review_note : '.').' Correct it and save to send it again before printing.'
                : 'This file is waiting for an admin to approve it. You can print it once it is approved.');
    }
}
