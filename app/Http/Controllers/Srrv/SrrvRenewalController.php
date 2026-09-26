<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The annual SRRV renewal. Due once a year for as long as the retiree stays.
 *
 * The fee turns on the visa class and nothing else, and is always recomputed
 * from the model — never taken from the form. At most two years can be paid
 * ahead.
 */
class SrrvRenewalController extends Controller
{
    /**
     * Which timestamps a stage records on entry.
     *
     * @var array<string, list<string>>
     */
    private const STAGE_MILESTONES = [
        'email_sent' => ['email_sent_at'],
        'processing' => ['processed_at'],
        'ready_for_collection' => ['ready_at_pra_at', 'client_notified_at'],
        'collected' => ['collected_at'],
    ];

    /**
     * Display the renewal ledger.
     */
    public function index(Request $request): View
    {
        $query = SrrvRenewal::with('application')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('retiree_name', 'like', "%{$search}%")
                    ->orWhere('srrv_card_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('visa_class')) {
            $query->where('visa_class', $request->input('visa_class'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $renewals = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => SrrvRenewal::count(),
            'classic' => SrrvRenewal::where('visa_class', 'classic')->count(),
            'courtesy' => SrrvRenewal::where('visa_class', 'courtesy')->count(),
            'open' => SrrvRenewal::whereNotIn('status', ['collected', 'cancelled'])->count(),
            'ready' => SrrvRenewal::where('status', 'ready_for_collection')->count(),
        ];

        return view('srrv.renewals.index', compact('renewals', 'stats'));
    }

    /**
     * Show the renewal intake form.
     */
    public function create(Request $request): View
    {
        $applicationId = $request->input('application');

        return view('srrv.renewals.create', [
            'application' => $applicationId ? SrrvApplication::find($applicationId) : null,
            'classFees' => SrrvRenewal::CLASS_FEES,
            'maxYears' => SrrvRenewal::MAX_YEARS_PREPAID,
        ]);
    }

    /**
     * Store a new renewal.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $years = (int) $validated['years_paid'];

        $renewal = SrrvRenewal::create([
            'reference' => SrrvRenewal::generateReference(),
            'srrv_application_id' => $validated['srrv_application_id'] ?? null,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'visa_class' => $validated['visa_class'],
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'years_paid' => $years,
            // The fee is derived, never submitted.
            'fee_amount' => SrrvRenewal::feeForClass($validated['visa_class']) * $years,
            'currency' => 'USD',
            'id_and_photocopy_received' => ! empty($validated['id_and_photocopy_received']),
            'form_filled_online' => ! empty($validated['form_filled_online']),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('SRRV', 'CREATE_RENEWAL', "Opened renewal {$renewal->reference} for {$renewal->retiree_name} ({$renewal->visa_class}, {$years} year(s))");

        ClientAccountService::findOrCreateClient([
            'name' => $renewal->retiree_name,
            'email' => $renewal->retiree_email,
            'phone' => $renewal->retiree_phone,
            'passport_number' => $renewal->passport_number,
        ]);

        return redirect()->route('srrv.renewals.show', $renewal)
            ->with('success', "Renewal {$renewal->reference} opened.");
    }

    /**
     * Display a renewal.
     */
    public function show(SrrvRenewal $renewal): View
    {
        $renewal->load(['application', 'creator']);

        $stages = SrrvRenewal::STAGES;
        $currentIndex = array_search($renewal->status, $stages, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return view('srrv.renewals.show', [
            'renewal' => $renewal,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $stages[$currentIndex + 1] ?? null,
            'isFinal' => $currentIndex >= count($stages) - 1,
            'blocker' => $renewal->stageBlocker(),
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(SrrvRenewal $renewal): View
    {
        return view('srrv.renewals.edit', [
            'renewal' => $renewal,
            'classFees' => SrrvRenewal::CLASS_FEES,
            'maxYears' => SrrvRenewal::MAX_YEARS_PREPAID,
        ]);
    }

    /**
     * Update a renewal. The fee is recomputed if the class or years changed.
     */
    public function update(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $years = (int) $validated['years_paid'];

        $renewal->update([
            'srrv_application_id' => $validated['srrv_application_id'] ?? $renewal->srrv_application_id,
            'visa_class' => $validated['visa_class'],
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'years_paid' => $years,
            'fee_amount' => SrrvRenewal::feeForClass($validated['visa_class']) * $years,
            'id_and_photocopy_received' => ! empty($validated['id_and_photocopy_received']),
            'form_filled_online' => ! empty($validated['form_filled_online']),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('SRRV', 'UPDATE_RENEWAL', "Updated renewal {$renewal->reference}");

        return redirect()->route('srrv.renewals.show', $renewal)
            ->with('success', 'Renewal updated.');
    }

    /**
     * Advance the renewal one step. The last step is collection in person.
     */
    public function advance(SrrvRenewal $renewal): RedirectResponse
    {
        $stages = SrrvRenewal::STAGES;
        $current = array_search($renewal->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This renewal is not on an active pipeline.');
        }

        if ($current >= count($stages) - 1) {
            return back()->with('error', 'This renewal is already at its final stage.');
        }

        $next = $stages[$current + 1];

        if ($next === 'collected') {
            return back()->with('error', 'Use "Record collection" so the collector is captured.');
        }

        if ($blocker = $renewal->stageBlocker()) {
            return back()->with('error', $renewal->status_label.' is not done yet. '.$blocker);
        }

        $milestones = [];
        foreach (self::STAGE_MILESTONES[$next] ?? [] as $column) {
            $milestones[$column] = now();
        }

        $renewal->update(['status' => $next] + $milestones);

        ActivityLogger::log('SRRV', 'ADVANCE_RENEWAL', "Renewal {$renewal->reference} advanced to {$next}");

        return back()->with('success', 'Renewal moved to '.$renewal->stageLabel($next).'.');
    }

    /**
     * Record that the card was collected in person at the PRA office.
     */
    public function collect(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        if ($renewal->status !== 'ready_for_collection') {
            return back()->with('error', 'The renewal is not ready at the PRA office yet.');
        }

        if ($blocker = $renewal->stageBlocker()) {
            return back()->with('error', $blocker);
        }

        $validated = $request->validate([
            'collected_by_name' => ['required', 'string', 'max:255'],
        ]);

        $renewal->update([
            'status' => 'collected',
            'collected_at' => now(),
            'collected_by_name' => $validated['collected_by_name'],
        ]);

        ActivityLogger::log('SRRV', 'COLLECT_RENEWAL', "Renewal {$renewal->reference} collected by {$validated['collected_by_name']}");

        return back()->with('success', "Renewal {$renewal->reference} marked as collected.");
    }

    /**
     * Record the renewal documents step: the SRRV ID and photocopy, the
     * online form, and the signature with thumb mark.
     */
    public function recordStage(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        if ($renewal->status !== 'pending') {
            return back()->with('error', 'There is nothing to record at this stage.');
        }

        $renewal->update([
            'id_and_photocopy_received' => $request->boolean('id_and_photocopy_received'),
            'form_filled_online' => $request->boolean('form_filled_online'),
            'signature_thumbmark_at' => $request->boolean('signature_thumbmark_taken')
                ? ($renewal->signature_thumbmark_at ?? now())
                : null,
        ]);

        ActivityLogger::log('SRRV', 'RECORD_STAGE', "Recorded renewal documents on {$renewal->reference}");

        return back()->with('success', 'Renewal documents recorded.');
    }

    /**
     * Take a payment against the renewal fee. The client collects only once
     * it is fully paid.
     */
    public function recordPayment(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        $balance = $renewal->outstandingBalance();

        if ($balance <= 0) {
            return back()->with('error', 'This renewal has no balance to pay.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
        ]);

        $renewal->update(['amount_paid' => (float) $renewal->amount_paid + (float) $validated['amount']]);

        ActivityLogger::log('SRRV', 'RENEWAL_PAYMENT', "Recorded {$renewal->currency} ".number_format((float) $validated['amount'], 2)." on {$renewal->reference}");

        return back()->with('success', $renewal->outstandingBalance() > 0
            ? 'Payment recorded. Balance: '.$renewal->currency.' '.number_format($renewal->outstandingBalance(), 2).'.'
            : 'Payment recorded. The renewal fee is fully paid.');
    }

    /**
     * Cancel a renewal.
     */
    public function cancel(SrrvRenewal $renewal): RedirectResponse
    {
        $renewal->update(['status' => 'cancelled']);

        ActivityLogger::log('SRRV', 'CANCEL_RENEWAL', "Cancelled renewal {$renewal->reference}");

        return back()->with('success', "Renewal {$renewal->reference} cancelled.");
    }

    /**
     * Remove a renewal.
     */
    public function destroy(SrrvRenewal $renewal): RedirectResponse
    {
        $reference = $renewal->reference;
        $renewal->delete();

        ActivityLogger::log('SRRV', 'DELETE_RENEWAL', "Deleted renewal {$reference}");

        return redirect()->route('srrv.renewals.index')
            ->with('success', "Renewal {$reference} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'srrv_application_id' => ['nullable', 'integer', 'exists:srrv_applications,id'],
            'visa_class' => ['required', 'in:'.implode(',', array_keys(SrrvRenewal::CLASS_FEES))],
            'retiree_name' => ['required', 'string', 'max:255'],
            'retiree_email' => ['nullable', 'email', 'max:255'],
            'srrv_card_number' => ['nullable', 'string', 'max:100'],
            // The ceiling on how far ahead a retiree can pay.
            'years_paid' => ['required', 'integer', 'min:1', 'max:'.SrrvRenewal::MAX_YEARS_PREPAID],
            'id_and_photocopy_received' => ['nullable', 'boolean'],
            'form_filled_online' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
