<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalTourBooking;
use App\Services\ActivityLogger;
use App\Support\Approvals;
use App\Support\Commissions;
use App\Support\DateRange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Approvers check a local tour booking once its quotation, agreement and guest details are in:
 * approve it (with their own acknowledgement) or return it with a reason. Until it is approved the
 * agent cannot book with the supplier, the cashier cannot take payment and no voucher is released.
 */
class LocalTourApprovalController extends Controller
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
            'title' => 'Local Tour Approvals',
            'routePrefix' => 'admin.local-tour-approvals',
            'commissionDesk' => 'local_tours',
            'placeholder' => 'Guest, reference or agent',
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

        $booking = LocalTourBooking::findOrFail($id);

        if (! $booking->isAwaitingApproval()) {
            return back()->with('error', "{$booking->reference} is not waiting for approval.");
        }

        if ($refusal = Approvals::refusal($request->user(), 'local_tours', $booking)) {
            return back()->with('error', $refusal);
        }

        $booking->forceFill([
            'approval_status' => LocalTourBooking::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('Local Tours', 'APPROVE', "Approved {$booking->reference} (acknowledged as correct by {$request->user()->name})");

        Commissions::record($request, 'local_tours', 'booking', $booking, $booking->reference, $booking->lead_guest_name);

        return back()->with('success', "{$booking->reference} approved. The agent can book with the supplier and the cashier can take payment.");
    }

    public function reject(Request $request, string $kind, int $id): RedirectResponse
    {
        $validated = $request->validate(
            ['review_note' => ['required', 'string', 'max:2000']],
            ['review_note.required' => 'Say what the agent needs to correct.'],
        );

        $booking = LocalTourBooking::findOrFail($id);

        if (! $booking->isAwaitingApproval()) {
            return back()->with('error', "{$booking->reference} is not waiting for approval.");
        }

        $booking->forceFill([
            'approval_status' => LocalTourBooking::APPROVAL_RETURNED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => trim($validated['review_note']),
        ])->save();

        ActivityLogger::log('Local Tours', 'RETURN', "Returned {$booking->reference} to the agent: ".trim($validated['review_note']));

        return back()->with('success', "{$booking->reference} returned to the agent to correct.");
    }

    /**
     * Every booking with this approval status, as rows for the page.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(string $status): Collection
    {
        return LocalTourBooking::with(['acknowledgedBy:id,name', 'reviewedBy:id,name'])->where('approval_status', $status)->get()
            ->map(function (LocalTourBooking $booking): array {
                $at = $booking->isAwaitingApproval() ? $booking->approval_requested_at : $booking->reviewed_at;

                return [
                    'kind' => 'booking',
                    'id' => $booking->getKey(),
                    'reference' => $booking->reference,
                    'label' => $booking->tourLabel(),
                    'client' => $booking->lead_guest_name,
                    'detail' => $booking->travel_date?->format('M j, Y'),
                    'summary' => collect([
                        $booking->guestsLabel(),
                        'quoted ₱'.number_format((float) $booking->total_amount, 2),
                        $booking->supplier_cost !== null ? 'supplier ₱'.number_format((float) $booking->supplier_cost, 2) : null,
                        (float) $booking->amount_paid > 0 ? 'paid ₱'.number_format((float) $booking->amount_paid, 2) : null,
                    ])->filter()->implode(' · '),
                    'agent' => $booking->acknowledgedBy?->name,
                    'reviewer' => $booking->reviewedBy?->name,
                    'note' => $booking->review_note,
                    'at' => $at,
                    'sort' => $at?->timestamp ?? 0,
                    'url' => route('local-tours.bookings.show', $booking),
                    'search' => implode(' ', [$booking->reference, $booking->tourLabel(), $booking->lead_guest_name, $booking->acknowledgedBy?->name]),
                ];
            })->values();
    }
}
