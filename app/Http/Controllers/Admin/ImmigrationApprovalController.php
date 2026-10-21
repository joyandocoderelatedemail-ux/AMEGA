<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Services\ActivityLogger;
use App\Services\VisaExtensionService;
use App\Support\ImmigrationCases;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $items = $this->items(self::TABS[$tab])
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn (array $row): bool => str_contains(mb_strtolower($row['search']), mb_strtolower($search))))
            ->sortBy($tab === 'pending' ? 'at' : fn (array $row) => -($row['at']?->timestamp ?? 0))
            ->values();

        $counts = collect(self::TABS)->map(fn (string $status): int => $this->items($status)->count());

        return view('admin.immigration-approvals.index', compact('items', 'tab', 'counts', 'search'));
    }

    public function approve(Request $request, string $kind, int $id): RedirectResponse
    {
        $request->validate(
            ['admin_acknowledged' => ['accepted']],
            ['admin_acknowledged.accepted' => 'Tick the acknowledgement to confirm you reviewed the dates and details.'],
        );

        if ($kind === 'extensions') {
            return $this->approveExtensions($request, ImmigrationClient::findOrFail($id));
        }

        $case = ImmigrationCases::find($kind, $id) ?? abort(404);

        if (! $case->isAwaitingApproval()) {
            return back()->with('error', "{$case->reference()} is not waiting for approval.");
        }

        $case->forceFill([
            'approval_status' => ImmigrationPetition::APPROVAL_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => null,
        ])->save();

        ActivityLogger::log('Immigration', 'APPROVE', "Approved {$case->reference()} (acknowledged as correct by {$request->user()->name})");

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

    /** Approving a client's new ledger rows moves their visa on, once per row. */
    private function approveExtensions(Request $request, ImmigrationClient $client): RedirectResponse
    {
        $rows = $client->extensions()->where('approval_status', ImmigrationPetition::APPROVAL_PENDING)->orderBy('sequence')->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'This extension is not waiting for approval.');
        }

        foreach ($rows as $row) {
            $row->forceFill(['approval_status' => ImmigrationPetition::APPROVAL_APPROVED, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => null])->save();
        }

        $until = VisaExtensionService::extend($client, $rows);

        ActivityLogger::log('Immigration', 'APPROVE', "Approved the Visa Extension of '{$client->full_name}' (acknowledged as correct by {$request->user()->name})");

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

        $extensions = ImmigrationClientExtension::with(['client', 'acknowledgedBy:id,name', 'reviewedBy:id,name'])
            ->where('approval_status', $status)->orderBy('sequence')->get()
            ->groupBy('immigration_client_id')
            ->map(fn (Collection $rows): array => $this->extensionRow($rows));

        return $cases->concat($extensions)->values();
    }

    /** @return array<string, mixed> */
    private function caseRow(Model $case): array
    {
        $kind = ImmigrationCases::kindOf($case);
        $label = ImmigrationCases::kinds()[$kind]['label'];
        $service = ImmigrationCases::kinds()[$kind]['service'];
        $client = $case->client;

        return [
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
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->getKey()]) : null,
            'search' => implode(' ', [$case->reference(), $label, $client?->full_name, $client?->passport_number, $case->acknowledgedBy?->name]),
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
            'url' => $client ? route('admin.client-sheets.edit', ['client_sheet' => $client->id]) : null,
            'search' => implode(' ', ['Visa Extension', $client?->full_name, $client?->passport_number, $first->acknowledgedBy?->name]),
        ];
    }
}
