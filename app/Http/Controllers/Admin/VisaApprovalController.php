<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisaApplication;
use App\Notifications\VisaEditRequestNotification;
use App\Services\ActivityLogger;
use App\Support\Approvals;
use App\Support\Commissions;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admins check what staff saved at the visa counter. Approve a file (with their own acknowledgement) or
 * return it with a reason. Until it is approved the file cannot be advanced, paid, printed or sent to
 * the client.
 */
class VisaApprovalController extends Controller
{
    /** The three queues on the approvals page. */
    private const TABS = ['pending', 'approved', 'returned'];

    public function index(Request $request): View
    {
        $tab = in_array((string) $request->input('tab'), self::TABS, true) ? $request->input('tab') : 'pending';
        $search = trim((string) $request->input('search'));
        $range = DateRange::fromRequest($request);

        $items = $this->items($tab)
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['search']), mb_strtolower($search))))
            // Sent (pending) or decided (approved, returned) within the date range picked on the page.
            ->filter(fn (array $row): bool => $range->contains($row['at']))
            ->sortBy($tab === 'pending' ? 'sort' : fn (array $row) => -$row['sort'])
            ->values();

        return view('admin.desk-approvals.index', [
            'title' => 'Visa Approvals',
            'routePrefix' => 'admin.visa-approvals',
            'commissionDesk' => 'visa_assistance',
            'placeholder' => 'Client, reference or agent',
            'items' => $items,
            'tab' => $tab,
            'search' => $search,
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $status) => [$status => $this->items($status)->count()]),
        ]);
    }

    public function approve(Request $request, string $kind, int $id): RedirectResponse
    {
        $request->validate(
            ['admin_acknowledged' => ['accepted']] + Commissions::rules($request->user()),
            ['admin_acknowledged.accepted' => 'Tick the acknowledgement to confirm you reviewed the details.'],
        );

        $file = VisaApplication::findOrFail($id);

        if (! $file->isAwaitingApproval()) {
            return back()->with('error', "{$file->reference} is not waiting for approval.");
        }

        if ($refusal = Approvals::refusal($request->user(), 'visa_assistance', $file)) {
            return back()->with('error', $refusal);
        }

        $file->forceFill([
            'approval_status' => VisaApplication::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('Visa Assistance', 'APPROVE', "Approved {$file->reference} (acknowledged as correct by {$request->user()->name})");

        Commissions::record($request, 'visa_assistance', 'file', $file, $file->reference, $file->client_name);

        return redirect()->route('admin.visa-approvals.index')->with('success', "{$file->reference} approved. It is now with the cashier for payment.");
    }

    public function reject(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['review_note' => ['required', 'string', 'max:2000']],
            ['review_note.required' => 'Say what the agent needs to correct.'],
        );

        $file = VisaApplication::findOrFail($id);

        if (! $file->isAwaitingApproval()) {
            return back()->with('error', "{$file->reference} is not waiting for approval.");
        }

        $file->forceFill([
            'approval_status' => VisaApplication::APPROVAL_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => trim($validated['review_note']),
        ])->save();

        ActivityLogger::log('Visa Assistance', 'RETURN', "Returned {$file->reference} to the agent: ".trim($validated['review_note']));

        return redirect()->route('admin.visa-approvals.index')->with('success', "{$file->reference} returned to the agent to correct.");
    }

    /** Approve an agent's Request edit on a released file: it opens again, at the stage before Released. */
    public function editApprove(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $file = VisaApplication::findOrFail($id);

        if ($refusal = $this->editRefusal($request->user(), $file)) {
            return back()->with('error', $refusal);
        }

        $requester = $file->editRequestedBy;
        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;
        $file->reopen();

        ActivityLogger::log('Visa Assistance', 'EDIT_GRANTED', "Reopened released {$file->reference} at the request of ".($requester?->name ?? 'an agent').($note ? ": {$note}" : ''));
        $this->tell($requester, new VisaEditRequestNotification('approved', $file->reference, (string) $file->client_name, $request->user()->name, $note));

        return back()->with('success', "{$file->reference} is open again, at {$file->status_label}. The agent can edit it; their changes come back here for approval.");
    }

    /** Decline it: the file stays locked and the admin's note goes back to the agent, who may ask again. */
    public function editDecline(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['note' => ['required', 'string', 'max:1000']],
            ['note.required' => 'Tell the agent why the file stays locked.'],
        );
        $file = VisaApplication::findOrFail($id);

        if ($refusal = $this->editRefusal($request->user(), $file)) {
            return back()->with('error', $refusal);
        }

        $file->declineEdit(trim($validated['note']));
        ActivityLogger::log('Visa Assistance', 'EDIT_DECLINED', "Declined the edit request on {$file->reference}: ".trim($validated['note']));
        $this->tell($file->editRequestedBy, new VisaEditRequestNotification('declined', $file->reference, (string) $file->client_name, $request->user()->name, trim($validated['note'])));

        return back()->with('success', "Edit request on {$file->reference} declined. The file stays locked.");
    }

    /** Why this person may not decide this request: none is waiting, or they asked for it themselves. */
    private function editRefusal(User $user, VisaApplication $file): ?string
    {
        if (! $file->hasEditRequest()) {
            return "{$file->reference} has no edit request waiting.";
        }

        if ($file->edit_requested_by === $user->id && ! Approvals::selfApproves($user, 'visa_assistance')) {
            return 'You asked for this edit yourself, so another approver must decide it.';
        }

        return null;
    }

    private function tell(?User $user, VisaEditRequestNotification $notification): void
    {
        if ($user === null || blank($user->email)) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Every file with this approval status, as rows for the page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $status): Collection
    {
        $files = VisaApplication::with(['acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get()
            ->map(fn (VisaApplication $file): array => $this->row($file));

        // A released file an agent asked to unlock waits here too (declined ones are listed with the returned files).
        $edits = match ($status) {
            'pending' => VisaApplication::editRequested()->with('editRequestedBy:id,name')->get(),
            'returned' => VisaApplication::editDeclined()->with('editRequestedBy:id,name')->get(),
            default => collect(),
        };

        return $files->concat($edits->map(fn (VisaApplication $file): array => $this->editRow($file)))->values();
    }

    /** @return array<string, mixed> */
    private function editRow(VisaApplication $file): array
    {
        $at = $file->edit_request_replied_at ?? $file->edit_requested_at;

        return [
            'edit' => true,
            'agent' => $file->editRequestedBy?->name,
            'reviewer' => null,
            'note' => $file->edit_request_replied_at ? 'Declined: '.$file->edit_request_reply : 'Reason: '.$file->edit_request_note,
            'summary' => 'Released file. Approving it opens the file again, at '.$file->stageLabel($file->reopenStage()).'.',
            'at' => $at,
            'sort' => $at?->timestamp ?? 0,
        ] + $this->row($file);
    }

    /** @return array<string, mixed> */
    private function row(VisaApplication $file): array
    {
        $label = VisaApplication::SERVICE_LABELS[$file->service_type] ?? $file->service_type;
        $at = $file->approval_status === VisaApplication::APPROVAL_PENDING ? $file->approval_requested_at : $file->reviewed_at;
        $money = fn ($amount) => $file->currency.' '.number_format((float) $amount, 2);

        return [
            'kind' => 'file',
            'id' => $file->getKey(),
            'reference' => $file->reference,
            'label' => $label,
            'client' => $file->client_name,
            'detail' => $file->destination_country,
            'summary' => (float) $file->total_amount > 0
                ? 'fee '.$money($file->total_amount).' · paid '.$money($file->amount_paid).' · balance '.$money($file->outstandingBalance())
                : null,
            'agent' => $file->acknowledgedBy?->name,
            'reviewer' => $file->reviewedBy?->name,
            'note' => $file->review_note,
            'at' => $at,
            'sort' => $at?->timestamp ?? 0,
            'url' => route('visa.applications.show', [$file, 'preview' => 1]),
            'search' => implode(' ', [$file->reference, $label, $file->client_name, $file->destination_country, $file->acknowledgedBy?->name]),
        ];
    }
}
