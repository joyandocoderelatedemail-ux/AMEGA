<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\User;
use App\Notifications\ImmigrationEditDecidedNotification;
use App\Services\ActivityLogger;
use App\Services\VisaExtensionService;
use App\Support\Approvals;
use App\Support\Commissions;
use App\Support\DateRange;
use App\Support\ImmigrationCases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * Admins check what an agent saved at the immigration counter: each case, and the Visa Extension
 * rows added to a client's ledger. Approve it (with their own acknowledgement) or return it to the
 * agent with a reason. Until it is approved the agent cannot print it or mark it submitted or released.
 */
class ImmigrationApprovalController extends Controller
{
    /** The three queues on the approvals page. */
    private const TABS = [
        'pending' => 'pending',
        'approved' => 'approved',
        'returned' => 'returned',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists((string) $request->input('tab'), self::TABS) ? $request->input('tab') : 'pending';
        $search = trim((string) $request->input('search'));
        $range = DateRange::fromRequest($request);

        $items = $this->items(self::TABS[$tab])
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['search']), mb_strtolower($search))))
            // Sent (pending) or decided (approved, returned) within the date range picked on the page.
            ->filter(fn (array $row): bool => $range->contains($row['at']))
            ->sortBy($tab === 'pending' ? 'at' : fn (array $row) => -($row['at']?->timestamp ?? 0))
            ->values();

        $counts = collect(self::TABS)->map(fn (string $status): int => $this->items($status)->count());

        return view('admin.immigration-approvals.index', compact('items', 'tab', 'counts', 'search'));
    }

    public function approve(Request $request, string $kind, int $id): RedirectResponse
    {
        $request->validate(
            ['admin_acknowledged' => ['accepted']] + Commissions::rules($request->user()),
            ['admin_acknowledged.accepted' => 'Tick the acknowledgement to confirm you reviewed the dates and details.'],
        );

        if ($kind === 'extensions') {
            return $this->approveExtensions($request, ImmigrationClient::findOrFail($id));
        }

        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if (! $case->isAwaitingApproval()) {
            return back()->with('error', "{$case->reference()} is not waiting for approval.");
        }

        if ($refusal = Approvals::refusal($request->user(), 'immigration', $case)) {
            return back()->with('error', $refusal);
        }

        $case->forceFill([
            'approval_status' => ImmigrationPetition::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('Immigration', 'APPROVE', "Approved {$case->reference()} (acknowledged as correct by {$request->user()->name})");

        Commissions::record($request, 'immigration', $kind, $case, $case->reference(), $case->client?->full_name);

        return back()->with('success', "{$case->reference()} approved. The agent can print it and take it to the BI.");
    }

    public function reject(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['review_note' => ['required', 'string', 'max:2000']],
            ['review_note.required' => 'Say what the agent needs to correct.'],
        );
        $note = trim($validated['review_note']);

        if ($kind === 'extensions') {
            $client = ImmigrationClient::findOrFail($id);
            $rows = $client->extensions()->where('approval_status', ImmigrationPetition::APPROVAL_PENDING)->get();

            if ($rows->isEmpty()) {
                return back()->with('error', 'This extension is not waiting for approval.');
            }

            foreach ($rows as $row) {
                $row->forceFill(['approval_status' => ImmigrationPetition::APPROVAL_RETURNED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $note])->save();
            }

            ActivityLogger::log('Immigration', 'RETURN', "Returned the Visa Extension of '{$client->full_name}' to the agent: {$note}");

            return back()->with('success', "The Visa Extension of {$client->full_name} was returned to the agent to correct.");
        }

        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if (! $case->isAwaitingApproval()) {
            return back()->with('error', "{$case->reference()} is not waiting for approval.");
        }

        $case->forceFill([
            'approval_status' => ImmigrationPetition::APPROVAL_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ])->save();

        ActivityLogger::log('Immigration', 'RETURN', "Returned {$case->reference()} to the agent: {$note}");

        return back()->with('success', "{$case->reference()} returned to the agent to correct.");
    }

    /**
     * Approve an agent's request to edit a released or approved file: it opens again (a release is cleared) and
     * is returned to the agent with the admin's note, so the agent edits it and the next save goes back through
     * approval as usual. Printing stays blocked until it is approved again.
     */
    public function editApprove(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if ($refusal = $this->editRefusal($request->user(), $case)) {
            return back()->with('error', $refusal);
        }

        $requester = $case->editRequestedBy;

        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;
        $wasReleased = $case->released_on !== null;
        $statusOnly = $case->editRequestIsStatusOnly();
        $statusLock = $statusOnly && ! $wasReleased;

        if ($error = ImmigrationCases::reopen($case, $request->user(), $note)) {
            return back()->with('error', $error);
        }

        ActivityLogger::log('Immigration', 'EDIT_GRANTED', ($statusLock ? 'Unlocked the Approved status of' : ($statusOnly ? 'Reopened released' : 'Unlocked '.($wasReleased ? 'released' : 'approved')))." {$case->reference()} ".($statusOnly ? 'so its status can change' : 'for editing').' at the request of '.($requester?->name ?? 'an agent').($note ? ": {$note}" : ''));
        $this->tellRequester($requester, new ImmigrationEditDecidedNotification($case->reference(), $case->client?->full_name ?? 'a client', true, $request->user()->name, $note, $statusOnly ? 'status' : 'details'));

        return back()->with('success', $statusLock
            ? "The Approved status of {$case->reference()} is unlocked. The agent can change its status; the file's details stay locked."
            : ($statusOnly
            ? "{$case->reference()} is open again. The agent can change its status; the file's details stay locked."
            : "{$case->reference()} is open again. The agent can edit it; their changes will come back here for approval."));
    }

    /** Decline the request: the file stays locked and the admin's note goes back to the agent. */
    public function editDecline(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['note' => ['required', 'string', 'max:1000']],
            ['note.required' => 'Tell the agent why the file stays locked.'],
        );
        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if ($refusal = $this->editRefusal($request->user(), $case)) {
            return back()->with('error', $refusal);
        }

        $note = trim($validated['note']);
        $case->declineEdit($note);

        ActivityLogger::log('Immigration', 'EDIT_DECLINED', "Declined the request to edit {$case->reference()}: {$note}");
        $this->tellRequester($case->editRequestedBy, new ImmigrationEditDecidedNotification($case->reference(), $case->client?->full_name ?? 'a client', false, $request->user()->name, $note));

        return back()->with('success', "The request to edit {$case->reference()} was declined. The file stays locked.");
    }

    /** Why this edit request cannot be decided now, or null. */
    private function editRefusal(User $user, Model $case): ?string
    {
        if (! $case->hasEditRequest()) {
            return "{$case->reference()} has no edit request waiting.";
        }

        if (! $user->hasPermission('immigration.approve')) {
            return 'You do not have the Approve permission for this desk.';
        }

        if ((int) $case->edit_requested_by === $user->id && ! Approvals::selfApproves($user, 'immigration')) {
            return 'You asked for this edit yourself, so another approver must decide it.';
        }

        return null;
    }

    /** Email the agent the admin's answer. A failed email never stops the decision. */
    private function tellRequester(?User $requester, ImmigrationEditDecidedNotification $notification): void
    {
        if (! $requester || blank($requester->email)) {
            return;
        }

        try {
            Notification::send($requester, $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Approving a client's new ledger rows moves their visa on, once per row. */
    private function approveExtensions(Request $request, ImmigrationClient $client): RedirectResponse
    {
        $rows = $client->extensions()->where('approval_status', ImmigrationPetition::APPROVAL_PENDING)->orderBy('sequence')->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'This extension is not waiting for approval.');
        }

        if ($refusal = Approvals::refusal($request->user(), 'immigration', $rows->first())) {
            return back()->with('error', $refusal);
        }

        foreach ($rows as $row) {
            $row->forceFill(['approval_status' => ImmigrationPetition::APPROVAL_APPROVED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => null])->save();
        }

        $until = VisaExtensionService::extend($client, $rows);

        ActivityLogger::log('Immigration', 'APPROVE', "Approved the Visa Extension of '{$client->full_name}' (acknowledged as correct by {$request->user()->name})");

        // The extension's rows are the agent's work: the commission is for whoever sent them, kept on the client's folder.
        Commissions::record($request, 'immigration', 'extensions', $client, 'Visa Extension', $client->full_name, Approvals::makerOf($rows->first()));

        return back()->with('success', "The Visa Extension of {$client->full_name} is approved".($until ? ". The visa is now valid until {$until->format('F j, Y')}." : '.'));
    }

    /**
     * Everything with this approval status, as rows for the page: each case, and one row per client
     * for the Visa Extension rows added to their ledger.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $status): Collection
    {
        $cases = collect([ImmigrationPetition::class, ImmigrationOverstay::class, ImmigrationRestamping::class])
            ->flatMap(fn (string $model) => $model::with(['client', 'acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get())
            ->map(fn (Model $case): array => $this->caseRow($case));

        // A released file the agent asked to edit: waiting (Pending tab) or declined (Returned tab).
        $edits = in_array($status, ['pending', 'returned'], true)
            ? collect([ImmigrationPetition::class, ImmigrationOverstay::class, ImmigrationRestamping::class])
                ->flatMap(fn (string $model) => $model::with(['client', 'editRequestedBy:id,name'])->when(
                    $status === 'pending',
                    fn ($query) => $query->editRequested(),
                    fn ($query) => $query->editDeclined(),
                )->get())
                ->map(fn (Model $case): array => $this->editRow($case, $status))
            : collect();

        $extensions = ImmigrationClientExtension::with(['client', 'acknowledgedBy:id,name', 'reviewedBy:id,name'])
            ->where('approval_status', $status)->orderBy('sequence')->get()
            ->groupBy('immigration_client_id')
            ->map(fn (Collection $rows): array => $this->extensionRow($rows));

        return $cases->concat($extensions)->concat($edits)->values();
    }

    /** @return array<string, mixed> */
    private function caseRow(Model $case): array
    {
        $kind = ImmigrationCases::kindOf($case);
        $label = ImmigrationCases::kinds()[$kind]['label'];
        $service = ImmigrationCases::kinds()[$kind]['service'];
        $client = $case->client;

        return [
            'type' => 'file',
            'kind' => $kind,
            'id' => $case->getKey(),
            'reference' => $case->reference(),
            'label' => $label,
            'client' => $client?->full_name ?? 'Unknown client',
            'passport' => $client?->passport_number,
            'fee' => $case->fee,
            'payment' => $case->paymentLabel(),
            'paid' => $case->amount_paid,
            'balance' => $case->paymentBalance(),
            'terms' => $case->terms_count,
            'agent' => $case->acknowledgedBy?->name,
            'reviewer' => $case->reviewedBy?->name,
            'note' => $case->review_note,
            'at' => $case->approval_status === ImmigrationPetition::APPROVAL_PENDING ? $case->approval_requested_at : $case->reviewed_at,
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->getKey(), 'preview' => 1]) : null,
            'search' => implode(' ', [$case->reference(), $label, $client?->full_name, $client?->passport_number, $case->acknowledgedBy?->name]),
        ];
    }

    /**
     * A request to edit a released or approved file, as a row for the page.
     *
     * @return array<string, mixed>
     */
    private function editRow(Model $case, string $status): array
    {
        $kind = ImmigrationCases::kindOf($case);
        $label = ImmigrationCases::kinds()[$kind]['label'];
        $service = ImmigrationCases::kinds()[$kind]['service'];
        $client = $case->client;
        $agent = $case->editRequestedBy?->name;

        return [
            'type' => 'edit',
            'kind' => $kind,
            'id' => $case->getKey(),
            'reference' => $case->reference(),
            'label' => $label,
            'client' => $client?->full_name ?? 'Unknown client',
            'passport' => $client?->passport_number,
            'agent' => $agent,
            'reviewer' => null,
            'reason' => $case->edit_request_note,
            'edit_type' => $case->edit_request_type ?? 'details',
            'note' => null,
            'reply' => $status === 'returned' ? $case->edit_request_reply : null,
            'released_on' => $case->released_on,
            'approved_on' => $case->released_on ? null : $case->reviewed_at,
            'at' => $status === 'pending' ? $case->edit_requested_at : $case->edit_request_replied_at,
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->getKey(), 'preview' => 1]) : null,
            'search' => implode(' ', [$case->reference(), $label, $client?->full_name, $client?->passport_number, $agent, 'edit request']),
        ];
    }

    /**
     * @param  Collection<int, ImmigrationClientExtension>  $rows  one client's rows with the same approval status
     * @return array<string, mixed>
     */
    private function extensionRow(Collection $rows): array
    {
        $first = $rows->first();
        $client = $first->client;
        $total = $rows->sum(fn (ImmigrationClientExtension $row) => (float) $row->amount_paid);
        $received = $rows->sum(fn (ImmigrationClientExtension $row) => (float) $row->amount_received);

        return [
            'type' => 'file',
            'kind' => 'extensions',
            'id' => $first->immigration_client_id,
            'reference' => $rows->count().' '.($rows->count() === 1 ? 'row' : 'rows').' · #'.$rows->pluck('sequence')->implode(', #'),
            'label' => 'Visa Extension',
            'client' => $client?->full_name ?? 'Unknown client',
            'passport' => $client?->passport_number,
            'fee' => $total,
            'payment' => ImmigrationPetition::PAYMENT_TYPES[$first->payment_type] ?? null,
            'paid' => $received,
            'balance' => max(0.0, $total - $received),
            'terms' => $first->terms_count,
            'agent' => $first->acknowledgedBy?->name,
            'reviewer' => $first->reviewedBy?->name,
            'note' => $first->review_note,
            'at' => $first->approval_status === ImmigrationPetition::APPROVAL_PENDING ? $rows->min('approval_requested_at') : $first->reviewed_at,
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'preview' => 1]) : null,
            'search' => implode(' ', ['Visa Extension', $client?->full_name, $client?->passport_number, $first->acknowledgedBy?->name]),
        ];
    }
}
