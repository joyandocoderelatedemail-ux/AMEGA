<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationStatusChange;
use App\Models\User;
use App\Notifications\ImmigrationEditRequestedNotification;
use App\Services\ActivityLogger;
use App\Support\Approvals;
use App\Support\CaseProgress;
use App\Support\DateRange;
use App\Support\ImmigrationCases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
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
     * Update where the case stands (On-going Preparation through Approved, Denied and so on), or release it.
     * Marking it Submitted also records today as the date filed, when none was set. Released is the dropdown
     * choice that records the release date: it closes the case and locks it.
     */
    public function updateStatus(Request $request, string $kind, int $id): RedirectResponse|JsonResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);
        $status = (string) $request->input('status');

        if ($status !== CaseProgress::RELEASED && ! array_key_exists($status, CaseProgress::OPTIONS)) {
            return back()->with('error', 'Choose one of the listed statuses.');
        }

        if ($locked = $this->statusBlocker($case, $request->user()->can('immigration.approve'), $status)) {
            return $this->refuse($request, "{$case->reference()}: {$locked}");
        }

        $before = $case->progressKey();

        if ($status !== CaseProgress::RELEASED && $status === $before && $case->workflow_status !== null) {
            return back();
        }

        // Every status change says why: the remark is kept with the change, for good.
        $remark = trim((string) $request->input('remark'));
        if ($remark === '') {
            return $this->refuse($request, "{$case->reference()}: write a remark for the status change.");
        }
        if (mb_strlen($remark) > 1000) {
            return $this->refuse($request, "{$case->reference()}: the remark is too long (1,000 characters at most).");
        }

        if ($status === CaseProgress::RELEASED) {
            return $this->release($request, $case, $kind, $before, $remark);
        }

        // Any status write clears an old unlock: picking Approved again locks it again.
        $updates = ['workflow_status' => $status, 'workflow_status_at' => now(), 'status_unlocked_at' => null];
        if ($status === 'submitted' && $case->filed_on === null) {
            $updates['filed_on'] = now()->toDateString();
        }

        // Denied, withdrawn and deferred close the case (the case is open here, as a closed one is locked).
        $closed = CaseProgress::closes($status);
        if ($closed) {
            $updates['released_on'] = now()->toDateString();
        }

        $case->forceFill($updates)->save();
        $this->recordChange($request, $kind, $case, $before, $status, $remark);

        ActivityLogger::log('Immigration', 'CASE_STATUS', "{$case->reference()} status: ".CaseProgress::label($before).' → '.CaseProgress::label($status).($closed ? ' (transaction closed)' : '').". Remark: {$remark}");

        return back()->with('success', "{$case->reference()} is now ".CaseProgress::label($status).'.'.($closed ? ' The transaction is closed.' : ''));
    }

    /**
     * Why the status cannot be changed to $status, or null when it can. A file waiting for the admin is locked, and so is
     * a released one (Reopen for a supervisor, Request edit for an agent). A returned file is free. An approver is never
     * blocked. Anyone else, on a file whose Approved status is locked, may only mark it Released.
     */
    private function statusBlocker(Model $case, bool $approver, string $status): ?string
    {
        if ($case->isAwaitingApproval()) {
            return $case->approvalBlocker('changed');
        }

        if ($case->finishedState() !== null) {
            return 'This file is '.$case->finishedState().'. Its status is locked; '.($approver ? 'reopen it first.' : 'request an edit first.');
        }

        if ($approver || $case->isReturned()) {
            return null;
        }

        if ($case->approvedStatusLocked() && $status !== CaseProgress::RELEASED) {
            return 'The Approved status is locked. Use Request edit to ask the admin for a status change.';
        }

        return null;
    }

    /** A refusal: the flash message for the page's own forms, a 423 for a fetch/XHR call. */
    private function refuse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], 423)
            : back()->with('error', $message);
    }

    /**
     * The BI has released it: record when, which closes and locks the case (the client's next one of this
     * service starts fresh). The date is today unless the desk says otherwise. The status itself is kept.
     * The case is open and cleared here: updateStatus has already refused a held or released one.
     */
    /** Keep the change and its remark in the case's status history. */
    private function recordChange(Request $request, string $kind, Model $case, ?string $from, string $to, string $remark): void
    {
        ImmigrationStatusChange::create([
            'case_kind' => $kind,
            'case_id' => $case->getKey(),
            'from_status' => $from,
            'to_status' => $to,
            'remark' => $remark,
            'released_on' => $to === CaseProgress::RELEASED ? $case->released_on : null,
            'user_id' => $request->user()?->id,
        ]);
    }

    private function release(Request $request, Model $case, string $kind, ?string $before, string $remark): RedirectResponse|JsonResponse
    {
        $rules = ['released_on' => ['required', 'date', 'before_or_equal:today']];
        if ($case->filed_on) {
            $rules['released_on'][] = 'after_or_equal:'.$case->filed_on->toDateString();
        }

        $validator = Validator::make($request->all(), $rules, [
            'released_on.required' => 'Pick the date it was released.',
            'released_on.before_or_equal' => 'A release date cannot be in the future.',
            'released_on.after_or_equal' => 'The release date cannot be before the filing date ('.$case->filed_on?->format('M d, Y').').',
        ]);

        if ($validator->fails()) {
            return $this->refuse($request, $validator->errors()->first());
        }

        $case->forceFill(['released_on' => $validator->validated()['released_on'], 'status_unlocked_at' => null] + $case::clearedEditRequest())->save();
        $this->recordChange($request, $kind, $case, $before, CaseProgress::RELEASED, $remark);

        ActivityLogger::log('Immigration', 'CASE_RELEASED', "Marked {$case->reference()} released on {$case->released_on->format('M d, Y')}. Remark: {$remark}");

        return back()->with('success', "{$case->reference()} is marked released. The file is now locked.");
    }

    /** Released by mistake, or the BI sent it back: the case is open again. */
    public function reopen(Request $request, string $kind, int $id): RedirectResponse|JsonResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        // Unlocking a released file is a supervisor's call: an admin, or an Immigration approver.
        if (! $request->user()->can('immigration.approve')) {
            return $this->refuse($request, "Only an admin or an Immigration approver can reopen {$case->reference()}. Use Request edit to ask the admin.");
        }

        if ($error = ImmigrationCases::reopen($case)) {
            return back()->with('error', $error);
        }

        ActivityLogger::log('Immigration', 'CASE_REOPENED', "Reopened {$case->reference()}");

        return back()->with('success', "{$case->reference()} is open again.");
    }

    /**
     * A released or approved file is locked. The agent who needs a change asks the admin to unlock it, with a
     * reason; it shows on the admin's approvals page and in their bell.
     */
    public function requestEdit(Request $request, string $kind, int $id): RedirectResponse|JsonResponse
    {
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        // Only a released file is asked about, or an Approved status that is locked; an unreleased file's status moves from the dropdown.
        if (! $case->released_on && ! $case->approvedStatusLocked()) {
            return back()->with('error', "The status of {$case->reference()} is not locked: change it from the status dropdown. Request edit is for released files.");
        }

        if (! $case->isCleared()) {
            return back()->with('error', $this->heldMessage($case, 'unlocked'));
        }

        if ($request->user()->can('immigration.approve')) {
            return back()->with('error', "You can reopen or edit {$case->reference()} yourself.");
        }

        if ($case->hasEditRequest()) {
            return back()->with('error', "An edit is already requested for {$case->reference()}. It is waiting for the admin.");
        }

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000'], 'type' => ['nullable', 'in:details,status']],
            ['reason.required' => 'Say what needs to change, so the admin can decide.'],
        );
        $reason = trim($validated['reason']);
        $type = $validated['type'] ?? 'details';

        // A file that is not released can only ask to unlock its Approved status.
        if (! $case->released_on) {
            $type = 'status';
        }

        $case->requestEdit($request->user(), $reason, $type);

        $statusLock = $type === 'status' && ! $case->released_on;
        ActivityLogger::log('Immigration', 'EDIT_REQUESTED', ($statusLock ? 'Asked to unlock the Approved status of' : ($type === 'status' ? 'Asked to reopen released' : 'Asked to unlock '.($case->released_on ? 'released' : 'approved')))." {$case->reference()}".($type === 'status' && ! $statusLock ? ' to change its status' : '').": {$reason}");

        try {
            Notification::send(
                Approvals::recipients('immigration', $request->user()),
                new ImmigrationEditRequestedNotification($case->reference(), $case->client?->full_name ?? 'a client', $request->user()->name, $reason, $type),
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Edit requested for {$case->reference()}. The admin will approve or decline it.");
    }

    /** Why a case an admin has not approved cannot go on yet. */
    private function heldMessage(Model $case, string $action): string
    {
        return $case->isReturned()
            ? "{$case->reference()} was returned by the admin ({$case->review_note}). Correct it and save so it can be approved before it is {$action}."
            : "{$case->reference()} is waiting for an admin to approve it, so it cannot be {$action} yet.";
    }
}
