<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use App\Support\Approvals;
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

        $items = $this->items($tab)
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['search']), mb_strtolower($search))))
            ->sortBy($tab === 'pending' ? 'sort' : fn (array $row) => -$row['sort'])
            ->values();

        return view('admin.desk-approvals.index', [
            'title' => 'Visa Approvals',
            'routePrefix' => 'admin.visa-approvals',
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
            ['admin_acknowledged' => ['accepted']],
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

        return back()->with('success', "{$file->reference} approved. The agent can advance it, take payment and print.");
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

        return back()->with('success', "{$file->reference} returned to the agent to correct.");
    }

    /**
     * Every file with this approval status, as rows for the page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $status): Collection
    {
        return VisaApplication::with(['acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get()
            ->map(fn (VisaApplication $file): array => $this->row($file))
            ->values();
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
