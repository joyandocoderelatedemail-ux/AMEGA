<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concerns\NeedsAdminApproval;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\User;
use App\Notifications\SrrvEditRequestNotification;
use App\Services\ActivityLogger;
use App\Support\Approvals;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Admins check what staff saved at the SRRV desk: each retiree file and each renewal. Approve it (with
 * their own acknowledgement) or return it with a reason. Until it is approved the file cannot be
 * advanced, paid, printed or sent to the client.
 */
class SrrvApprovalController extends Controller
{
    /** The three queues on the approvals page. */
    private const TABS = ['pending', 'approved', 'returned'];

    /** @var array<string, class-string<Model>> */
    private const KINDS = ['application' => SrrvApplication::class, 'renewal' => SrrvRenewal::class, 'request' => SrrvServiceRequest::class];

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
            'title' => 'SRRV Approvals',
            'routePrefix' => 'admin.srrv-approvals',
            'placeholder' => 'Retiree, reference or agent',
            'items' => $items,
            'tab' => $tab,
            'search' => $search,
            'counts' => collect(self::TABS)->mapWithKeys(fn (string $status) => [$status => $this->items($status)->count()]),
        ]);
    }

    public function approve(Request $request, string $kind, int $id): RedirectResponse
    {
        $request->validate(
            ['admin_acknowledged' => ['accepted']],
            ['admin_acknowledged.accepted' => 'Tick the acknowledgement to confirm you reviewed the details.'],
        );

        $file = $this->find($kind, $id);

        if (! $file->isAwaitingApproval()) {
            return back()->with('error', "{$file->reference} is not waiting for approval.");
        }

        if ($refusal = Approvals::refusal($request->user(), 'srrv', $file)) {
            return back()->with('error', $refusal);
        }

        $file->forceFill([
            'approval_status' => SrrvApplication::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('SRRV', 'APPROVE', "Approved {$file->reference} (acknowledged as correct by {$request->user()->name})");

        return back()->with('success', "{$file->reference} approved. The agent can advance it, take payment and print.");
    }

    public function reject(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['review_note' => ['required', 'string', 'max:2000']],
            ['review_note.required' => 'Say what the agent needs to correct.'],
        );

        $file = $this->find($kind, $id);

        if (! $file->isAwaitingApproval()) {
            return back()->with('error', "{$file->reference} is not waiting for approval.");
        }

        $file->forceFill([
            'approval_status' => SrrvApplication::APPROVAL_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => trim($validated['review_note']),
        ])->save();

        ActivityLogger::log('SRRV', 'RETURN', "Returned {$file->reference} to the agent: ".trim($validated['review_note']));

        return back()->with('success', "{$file->reference} returned to the agent to correct.");
    }

    /** Approve an agent's Request edit: the file opens again (a locked Approved status is unlocked instead). */
    public function editApprove(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $file = SrrvApplication::findOrFail($id);

        if ($refusal = $this->editRefusal($request->user(), $file)) {
            return back()->with('error', $refusal);
        }

        $requester = $file->editRequestedBy;
        $note = filled($validated['note'] ?? null) ? trim($validated['note']) : null;

        if ($file->isReleasedLocked()) {
            $file->reopen();
        } else {
            $file->forceFill(['status_unlocked_at' => now()] + SrrvApplication::clearedEditRequest())->save();
        }

        ActivityLogger::log('SRRV', 'EDIT_GRANTED', "Unlocked {$file->reference} at the request of ".($requester?->name ?? 'an agent').($note ? ": {$note}" : ''));
        $this->tell($requester, new SrrvEditRequestNotification('approved', $file->reference, (string) $file->retiree_name, $request->user()->name, $note));

        return back()->with('success', "{$file->reference} is unlocked. The agent can change it now.");
    }

    /** Decline it: the file stays locked and the admin's note goes back to the agent, who may ask again. */
    public function editDecline(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['note' => ['required', 'string', 'max:1000']],
            ['note.required' => 'Tell the agent why the file stays locked.'],
        );
        $file = SrrvApplication::findOrFail($id);

        if ($refusal = $this->editRefusal($request->user(), $file)) {
            return back()->with('error', $refusal);
        }

        $file->declineEdit(trim($validated['note']));
        ActivityLogger::log('SRRV', 'EDIT_DECLINED', "Declined the edit request on {$file->reference}: ".trim($validated['note']));
        $this->tell($file->editRequestedBy, new SrrvEditRequestNotification('declined', $file->reference, (string) $file->retiree_name, $request->user()->name, trim($validated['note'])));

        return back()->with('success', "Edit request on {$file->reference} declined. The file stays locked.");
    }

    /** Why this person may not decide this request: none is waiting, or they asked for it themselves. */
    private function editRefusal(User $user, SrrvApplication $file): ?string
    {
        if (! $file->hasEditRequest()) {
            return "{$file->reference} has no edit request waiting.";
        }

        if ($file->edit_requested_by === $user->id && ! Approvals::selfApproves($user, 'srrv')) {
            return 'You asked for this edit yourself, so another approver must decide it.';
        }

        return null;
    }

    private function tell(?User $user, SrrvEditRequestNotification $notification): void
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

    /** @return Model&NeedsAdminApproval */
    private function find(string $kind, int $id): Model
    {
        return (self::KINDS[$kind] ?? abort(404))::findOrFail($id);
    }

    /**
     * Every file with this approval status, as rows for the page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $status): Collection
    {
        $files = collect(self::KINDS)->flatMap(function (string $model, string $kind) use ($status) {
            return $model::with(['acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get()
                ->map(fn (Model $file): array => $this->row($file, $kind));
        });

        // A file an agent asked to unlock waits here too (declined ones are listed with the returned files).
        $edits = match ($status) {
            'pending' => SrrvApplication::editRequested()->with('editRequestedBy:id,name')->get(),
            'returned' => SrrvApplication::editDeclined()->with('editRequestedBy:id,name')->get(),
            default => collect(),
        };

        return $files->concat($edits->map(fn (SrrvApplication $file): array => $this->editRow($file)))->values();
    }

    /** @return array<string, mixed> */
    private function editRow(SrrvApplication $file): array
    {
        $at = $file->edit_request_replied_at ?? $file->edit_requested_at;

        return [
            'edit' => true,
            'agent' => $file->editRequestedBy?->name,
            'reviewer' => null,
            'note' => $file->edit_request_replied_at ? 'Declined: '.$file->edit_request_reply : 'Reason: '.$file->edit_request_note,
            'summary' => ($file->isReleasedLocked() ? 'Released file. Approving it opens the file again.' : 'Approved status is locked. Approving it unlocks the status.'),
            'at' => $at,
            'sort' => $at?->timestamp ?? 0,
        ] + $this->row($file, 'application');
    }

    /** @return array<string, mixed> */
    private function row(Model $file, string $kind): array
    {
        $isApplication = $kind === 'application';
        $isRequest = $kind === 'request';
        $label = match ($kind) {
            'application' => 'SRRV Application',
            'request' => $file->typeLabel(),
            default => 'ID Renewal',
        };
        $fee = match ($kind) {
            'application' => $file->service_fee,
            'request' => 0,
            default => $file->fee_amount,
        };
        $at = $file->approval_status === SrrvApplication::APPROVAL_PENDING ? $file->approval_requested_at : $file->reviewed_at;
        $money = fn ($amount) => $file->currency.' '.number_format((float) $amount, 2);

        return [
            'kind' => $kind,
            'id' => $file->getKey(),
            'reference' => $file->reference,
            'label' => $label,
            'client' => $file->retiree_name,
            'detail' => $file->srrv_card_number,
            'summary' => $isRequest
                ? collect([ucfirst((string) $file->visa_class), (float) $file->fee_usd > 0 ? 'fees US$ '.number_format((float) $file->fee_usd, 2).' + ₱'.number_format((float) $file->fee_php, 2) : null])->filter()->implode(' · ')
                : collect([ucfirst((string) $file->visa_class), (float) $fee > 0 ? 'fee '.$money($fee).' · paid '.$money($file->amount_paid).' · balance '.$money($file->outstandingBalance()) : null])->filter()->implode(' · '),
            'agent' => $file->acknowledgedBy?->name,
            'reviewer' => $file->reviewedBy?->name,
            'note' => $file->review_note,
            'at' => $at,
            'sort' => $at?->timestamp ?? 0,
            'url' => $isRequest
                ? route('srrv.requests.edit', ['serviceRequest' => $file->getKey(), 'preview' => 1])
                : route($isApplication ? 'srrv.applications.edit' : 'srrv.renewals.edit', [$isApplication ? 'application' : 'renewal' => $file->getKey(), 'preview' => 1]),
            'search' => implode(' ', [$file->reference, $label, $file->retiree_name, $file->srrv_card_number, $file->acknowledgedBy?->name]),
        ];
    }
}
