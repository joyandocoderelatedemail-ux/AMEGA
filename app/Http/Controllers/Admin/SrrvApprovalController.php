<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Concerns\NeedsAdminApproval;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
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
        return collect(self::KINDS)->flatMap(function (string $model, string $kind) use ($status) {
            return $model::with(['acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get()
                ->map(fn (Model $file): array => $this->row($file, $kind));
        })->values();
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
