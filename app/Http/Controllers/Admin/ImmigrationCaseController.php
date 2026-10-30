<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\CaseProgress;
use App\Support\DateRange;
use App\Support\ImmigrationCases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ImmigrationCaseController extends Controller
{
    /**
     * Every service case across clients, by service and status: what still needs
     * requirements, what is filed and waiting on the BI, what was released. A search
     * takes a client's name or passport, or a case reference such as OS-2026-0014.
     */
    public function index(Request $request)
    {
        $kinds = ImmigrationCases::kinds();
        $view = (string) $request->input('service');
        // The cases of one service, or the client folders themselves: all of them, or those with Visa Extension.
        $kind = array_key_exists($view, $kinds) ? $view : null;
        $foldersMode = in_array($view, ['folders', 'visa_extension'], true);
        $status = in_array((string) $request->input('status'), [...array_keys(ImmigrationCases::STATUSES), ...array_keys(CaseProgress::OPTIONS)], true) ? $request->input('status') : 'all';
        $search = trim((string) $request->input('q'));
        $flaggedOnly = $foldersMode && $request->boolean('flagged');

        $range = DateRange::fromRequest($request);
        $cases = $foldersMode ? collect() : ImmigrationCases::search($kind, $status, $search, 200)
            // Opened within the date range picked on the page.
            ->filter(fn (array $case) => $range->contains($case['opened']))->values();

        // Client folders: the whole list in folder view; otherwise the ones matching a search,
        // since a client with no case yet is only reachable through their folder.
        $folderService = $view === 'visa_extension' ? 'visa_extension' : null;
        $searchingFolders = $search !== '' && ! $kind && $status === 'all';
        $matches = collect();
        $clientsWithoutFolder = collect();
        $folders = null;

        if ($foldersMode && $search === '') {
            $folders = ImmigrationCases::folders($folderService)
                ->when($flaggedOnly, fn ($query) => $query->flagged())
                ->when(true, fn ($query) => $range->apply($query))
                ->latest()->paginate(25)->withQueryString();
        } elseif ($foldersMode || $searchingFolders) {
            $matches = ImmigrationCases::folders($folderService, ImmigrationClient::search($search))
                ->when(! $foldersMode, fn ($query) => $query->whereDoesntHave('overstays')->whereDoesntHave('restampings')->whereDoesntHave('petitions'))
                ->orderBy('last_name')->orderBy('given_name')->limit(25)->get();
            $clientsWithoutFolder = $folderService === null
                ? User::clientSearch($search)->whereDoesntHave('immigrationClients')->orderBy('name')->limit(8)->get()
                : collect();
        }

        return view('admin.immigration.cases', [
            'cases' => $cases,
            'counts' => ImmigrationCases::openCounts(),
            'kinds' => $kinds,
            'kind' => $kind,
            'view' => $foldersMode ? $view : ($kind ?? ''),
            'foldersMode' => $foldersMode,
            'status' => $status,
            'search' => $search,
            'flaggedOnly' => $flaggedOnly,
            'folders' => $folders,
            'matches' => $matches,
            'clientsWithoutFolder' => $clientsWithoutFolder,
            'folderCount' => ImmigrationClient::count(),
            'extensionClientCount' => ImmigrationClient::whereHas('extensions')->count(),
            'flaggedCount' => ImmigrationClient::flagged()->count(),
        ]);
    }

    /**
     * Update where the case stands (On-going Preparation through Approved, Denied and so on).
     * Marking it Submitted also records today as the date filed, when none was set.
     */
    public function updateStatus(Request $request, string $kind, int $id): RedirectResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);
        $status = (string) $request->input('status');

        if (! array_key_exists($status, CaseProgress::OPTIONS)) {
            return back()->with('error', 'Choose one of the listed statuses.');
        }

        // The work goes to the BI only once an admin has approved it.
        if (! $case->isCleared() && in_array($status, CaseProgress::NEEDS_APPROVAL, true)) {
            return back()->with('error', $this->heldMessage($case, 'marked '.CaseProgress::label($status)));
        }

        $before = $case->progressKey();

        if ($status === $before && $case->workflow_status !== null) {
            return back();
        }

        $updates = ['workflow_status' => $status, 'workflow_status_at' => now()];
        if ($status === 'submitted' && $case->filed_on === null) {
            $updates['filed_on'] = now()->toDateString();
        }

        // Denied, withdrawn and deferred close the case; moving it back off one opens it again.
        $closed = false;
        $reopened = false;
        if (CaseProgress::closes($status)) {
            if ($case->released_on === null) {
                $updates['released_on'] = now()->toDateString();
                $closed = true;
            }
        } elseif (CaseProgress::closes($before) && $case->released_on !== null) {
            if (ImmigrationCases::hasOpenSibling($case)) {
                return back()->with('error', "The client already has another open case of this service, so {$case->reference()} cannot be opened again.");
            }

            $updates['released_on'] = null;
            $reopened = true;
        }

        $case->update($updates);

        ActivityLogger::log('Immigration', 'CASE_STATUS', "{$case->reference()} status: ".CaseProgress::label($before).' → '.CaseProgress::label($status).($closed ? ' (case closed)' : '').($reopened ? ' (case reopened)' : ''));

        return back()->with('success', "{$case->reference()} is now ".CaseProgress::label($status).'.'.($closed ? ' The case is closed.' : '').($reopened ? ' The case is open again.' : ''));
    }

    /**
     * The BI has released it: record when, which closes the case (the client's next one of
     * this service starts fresh). The date is today unless the desk says otherwise.
     */
    public function release(Request $request, string $kind, int $id): RedirectResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if (! $case->isCleared()) {
            return back()->with('error', $this->heldMessage($case, 'released'));
        }

        if ($case->released_on) {
            return back()->with('error', "{$case->reference()} is already released.");
        }

        $rules = ['released_on' => ['required', 'date', 'before_or_equal:today']];
        if ($case->filed_on) {
            $rules['released_on'][] = 'after_or_equal:'.$case->filed_on->toDateString();
        }

        $validator = Validator::make($request->all(), $rules, [
            'released_on.before_or_equal' => 'A release date cannot be in the future.',
            'released_on.after_or_equal' => 'The release date cannot be before the filing date ('.$case->filed_on?->format('M d, Y').').',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $case->update(['released_on' => $validator->validated()['released_on']]);

        ActivityLogger::log('Immigration', 'CASE_RELEASED', "Marked {$case->reference()} released on {$case->released_on->format('M d, Y')}");

        return back()->with('success', "{$case->reference()} is marked released.");
    }

    /** Released by mistake, or the BI sent it back: the case is open again. */
    public function reopen(string $kind, int $id): RedirectResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if (! $case->released_on) {
            return back()->with('error', "{$case->reference()} is not released.");
        }

        if (ImmigrationCases::hasOpenSibling($case)) {
            return back()->with('error', "The client already has another open case of this service, so {$case->reference()} cannot be reopened.");
        }

        // A case closed by Denied, Withdrawn or Deferred starts over; a released one keeps its status.
        $case->update(['released_on' => null] + (CaseProgress::closes($case->progressKey())
            ? ['workflow_status' => CaseProgress::DEFAULT, 'workflow_status_at' => now()]
            : []));

        ActivityLogger::log('Immigration', 'CASE_REOPENED', "Reopened {$case->reference()}");

        return back()->with('success', "{$case->reference()} is open again.");
    }

    /** Why a case an admin has not approved cannot go on yet. */
    private function heldMessage(Model $case, string $action): string
    {
        return $case->isReturned()
            ? "{$case->reference()} was returned by the admin ({$case->review_note}). Correct it and save so it can be approved before it is {$action}."
            : "{$case->reference()} is waiting for an admin to approve it, so it cannot be {$action} yet.";
    }
}
