<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvApplicationDocument;
use App\Models\SrrvPricingTier;
use App\Notifications\FileStatusChangedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\SrrvEditRequestNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientNotifier;
use App\Services\DeskApproval;
use App\Support\Approvals;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use App\Support\SrrvForms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The SRRV retiree file workflow: the renewal application and re-stamping jobs
 * worked through the Philippine Retirement Authority.
 *
 * Milestones here are stamped when a file ENTERS a stage, because the stages are
 * states the file sits in ("lodged", "awaiting release") rather than actions the
 * counter completes. The visa counter works the other way round: its stages are
 * actions, so it stamps on the way out.
 */
class SrrvApplicationController extends Controller
{
    /**
     * Which timestamps a stage records on entry.
     *
     * @var array<string, list<string>>
     */
    private const STAGE_MILESTONES = [
        'released' => ['released_at'],
    ];

    /**
     * Display the retiree file directory with filters.
     */
    public function index(Request $request): View
    {
        $query = SrrvApplication::withCount('documents')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('retiree_name', 'like', "%{$search}%")
                    ->orWhere('retiree_email', 'like', "%{$search}%")
                    ->orWhere('srrv_card_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->filled('visa_class')) {
            $query->where('visa_class', $request->input('visa_class'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // The date range picked on the page (App\Support\DateRange).
        DateRange::fromRequest($request)->apply($query);
        $applications = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => SrrvApplication::count(),
            'classic' => SrrvApplication::where('visa_class', 'classic')->count(),
            'courtesy' => SrrvApplication::where('visa_class', 'courtesy')->count(),
            'open' => SrrvApplication::whereNotIn('status', ['released', 'cancelled'])->count(),
            'awaitingOath' => SrrvApplication::whereNull('oath_at')
                ->whereNotNull('payment_in_full_at')
                ->count(),
        ];

        return view('srrv.applications.index', compact('applications', 'stats'));
    }

    /**
     * Show the intake form.
     */
    public function create(Request $request): View
    {
        SrrvClientLookupController::prefill($request);

        $serviceType = $request->input('service_type', 'renewal_application');

        if (! in_array($serviceType, SrrvApplication::OPEN_SERVICE_TYPES, true)) {
            $serviceType = 'renewal_application';
        }

        return view('srrv.applications.create', [
            'serviceType' => $serviceType,
            'pricing' => SrrvPricingTier::published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Store a newly opened retiree file.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rulesFor($request, opening: true));

        // A new application is opened at the end of Step 1, so it starts on Step 2 (the service agreement and fee); it
        // goes to the admin from there, and money comes in only through the cashier (an admin may enter an amount paid).
        $application = SrrvApplication::create([...$this->attributesFrom($validated), 'amount_paid' => $request->user()->isAdmin() ? (float) ($validated['amount_paid'] ?? 0) : 0.0] + [
            'reference' => SrrvApplication::generateReference(),
            'created_by' => Auth::id(),
            'status' => 'agreement',
            // Ticked on the wizard's Step 5 when the retiree has already signed the printed agreement.
            'agreement_signed_at' => $request->boolean('agreement_signed') ? now() : null,
            // Likewise the Acknowledgment of Documents, ticked on the Documents step.
            'acknowledgement_signed_at' => $request->boolean('acknowledgement_signed') ? now() : null,
        ]);
        $this->fileUploads($request, $application);

        ActivityLogger::log('SRRV', 'CREATE', "Opened SRRV file {$application->reference} ({$application->visa_class}) for {$application->retiree_name}");

        $this->rememberClient($application);

        return $this->afterSave($request, $application, "SRRV file {$application->reference} opened successfully.");
    }

    /**
     * Save & Print: the filled PRA Application Form opens with the print dialog up. A file waiting for
     * the admin's approval is not printed yet, so it stays on the file with a note to say so.
     */
    private function afterSave(Request $request, SrrvApplication $application, string $message): RedirectResponse
    {
        // Step 5's Save fee and Send to admin save the whole wizard first, then come back to that step or send the file.
        if ($request->input('then') === 'fee') {
            return redirect()->route('srrv.applications.edit', [$application, 'step' => 5])->with('success', $message.' Fee saved.');
        }

        if ($request->input('then') === 'send') {
            if ($refusal = $this->sendRefusal($application)) {
                return redirect()->route('srrv.applications.edit', [$application, 'step' => 5])->with('error', $refusal);
            }

            // Stay in the wizard: its Service Agreement & Fees step shows the file waiting for the admin.
            return redirect()->route('srrv.applications.edit', [$application, 'step' => 5])->with('success', $this->send($request, $application));
        }

        // Save & Print and Finish also mark a file that has not gone to the admin yet as pending, by sending it for approval.
        if (in_array($request->input('then'), ['print', 'finish'], true) && $application->isFeeFirst() && $application->status === 'agreement' && $application->approval_status === null && (float) $application->service_fee > 0) {
            $this->send($request, $application);
        }

        $held = $application->approvalBlocker('printed') !== null;

        // Finish ends the wizard: back to the service's file list, where the file shows its status.
        if ($request->input('then') === 'finish') {
            return redirect()->route('srrv.services', SrrvClientLookupController::serviceFor('application', $application->visa_class))
                ->with('success', $message.($held ? " It is waiting for the admin's approval." : ''));
        }

        if ($request->input('then') === 'print' && ! $held) {
            return redirect()->route('srrv.applications.forms', [$application, 'application', 'autoprint' => 1]);
        }

        // Stay in the wizard (its Service Agreement & Fees step shows where the file stands) rather than the older file page.
        return redirect()->route('srrv.applications.edit', [$application, 'step' => 5])
            ->with('success', $message.($held ? ' It goes to the admin for approval; you can print it once they approve it.' : ''));
    }

    /**
     * Display the retiree file.
     */
    public function show(SrrvApplication $application): View
    {
        $application->load(['documents', 'renewals', 'creator']);

        $stages = $application->stages();
        $currentIndex = array_search($application->status, $stages, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return view('srrv.applications.show', [
            'application' => $application,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $stages[$currentIndex + 1] ?? null,
            'isFinal' => $currentIndex >= count($stages) - 1,
            'blocker' => $application->stageBlocker(),
            'documentTypes' => SrrvApplicationDocument::DOCUMENT_TYPES,
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(Request $request, SrrvApplication $application): View
    {
        // Opened from a service list or the approvals page: the whole file, read-only, on one page.
        if ($request->boolean('preview')) {
            return view('srrv.applications.preview', ['application' => $application->load(['documents', 'creator'])]);
        }

        return view('srrv.applications.edit', [
            'application' => $application,
            'preview' => false,
            'pricing' => SrrvPricingTier::published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Update a retiree file.
     */
    public function update(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($locked = $this->lockedRefusal($request, $application)) {
            return back()->with('error', $locked);
        }

        $validated = $request->validate($this->rulesFor($request, application: $application));

        $changes = $this->attributesFrom($validated);

        // What was paid changes only through the cashier's payments (an admin may correct it on an older file).
        if ($application->isFeeFirst()) {
            $changes['amount_paid'] = $application->amount_paid;
        }

        // The signature is recorded once and never taken back from this form.
        if ($request->boolean('agreement_signed') && $application->agreement_signed_at === null) {
            $changes['agreement_signed_at'] = now();
        }

        if ($request->boolean('acknowledgement_signed') && $application->acknowledgement_signed_at === null) {
            $changes['acknowledgement_signed_at'] = now();
        }

        // Copies and the amount paid are no longer on the wizard: a save without them leaves what the file has.
        foreach (['copies_submitted', 'amount_paid'] as $kept) {
            if (! $request->has($kept)) {
                $changes[$kept] = $application->{$kept};
            }
        }

        $application->update($changes);
        $this->fileUploads($request, $application);

        // A file not yet sent to the admin stays with the agent: it is sent from Step 2.
        if (! ($application->isFeeFirst() && $application->approval_status === null)) {
            DeskApproval::saved($application, $request->user(), 'SRRV', (string) $application->retiree_name, 'admin.srrv-approvals.index', $application->wasChanged() || $request->allFiles() !== []);
        }
        $this->rememberClient($application);

        ActivityLogger::log('SRRV', 'UPDATE', "Updated SRRV file {$application->reference}");

        return $this->afterSave($request, $application, 'SRRV file updated.');
    }

    /**
     * Advance the file one step along its pipeline.
     */
    public function advance(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($locked = $this->lockedRefusal($request, $application)) {
            return back()->with('error', $locked);
        }

        // The wizard's Advance button asks to land on its next step; the file page just goes back.
        $return = fn () => $request->filled('step') ? redirect()->route('srrv.applications.edit', [$application, 'step' => (int) $request->input('step')]) : back();

        if ($held = $application->approvalBlocker('advanced')) {
            return back()->with('error', $held);
        }

        $stages = $application->stages();
        $current = array_search($application->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This file is no longer moving through its steps.');
        }

        if ($current >= count($stages) - 1) {
            return back()->with('error', 'This file is already at its final stage.');
        }

        $application->load('documents');

        if ($blocker = $application->stageBlocker()) {
            return back()->with('error', $application->status_label.' is not done yet. '.$blocker);
        }

        $next = $stages[$current + 1];

        // Entering a stage is what records its milestone.
        $milestones = [];
        foreach (self::STAGE_MILESTONES[$next] ?? [] as $column) {
            $milestones[$column] = now();
        }

        $application->update(['status' => $next] + $milestones);

        ActivityLogger::log('SRRV', 'ADVANCE', "SRRV file {$application->reference} advanced to {$next}");

        $this->notifyStatus($application, $application->stageLabel($next), $next === 'released'
            ? 'Your SRRV is ready for release. Please visit our office to arrange collection.'
            : null);

        return $return()->with('success', 'File moved to '.$application->stageLabel($next).'.');
    }

    /**
     * Record the current stage's work: the class proofs seen at the desk, the
     * investment amount, the supporting documents, the PRA lodgement, or the
     * oath taking.
     */
    public function recordStage(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($application->isFeeFirst() && $application->status === 'agreement' && $request->has('service_fee')) {
            return $this->recordFee($request, $application);
        }

        $updates = match (true) {
            $application->isFeeFirst() && $application->status === 'agreement' => ['agreement_signed_at' => now()],
            $application->isFeeFirst() && $application->status === 'requirements' => ['acknowledgement_signed_at' => now()],
            default => $this->legacyStage($request, $application),
        };

        if ($updates === null) {
            return back()->with('error', 'There is nothing to record at this stage.');
        }

        $application->update($updates);

        ActivityLogger::log('SRRV', 'RECORD_STAGE', "Recorded {$application->status} on {$application->reference}");

        return back()->with('success', $application->status_label.' recorded.');
    }

    /**
     * The service fee on Step 2 of a new application. It is locked while the admin holds the file, or once approved.
     */
    private function recordFee(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($application->isAwaitingApproval() || $application->approval_status === SrrvApplication::APPROVAL_APPROVED) {
            return back()->with('error', 'The fee cannot be changed once the file is with the admin or approved.');
        }

        $validated = $request->validate([
            'service_fee' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ], ['service_fee.required' => 'Enter the service fee.']);
        $application->update(['service_fee' => $validated['service_fee']]);

        ActivityLogger::log('SRRV', 'RECORD_STAGE', "Set the service fee on {$application->reference}");

        return back()->with('success', 'Fee saved.');
    }

    /**
     * Step 2: send the file, with its fee, to the admin. Approved, it goes to the cashier for payment; returned, the
     * agent corrects it and sends it again.
     */
    public function sendToAdmin(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($refusal = $this->sendRefusal($application)) {
            return back()->with('error', $refusal);
        }

        return back()->with('success', $this->send($request, $application));
    }

    /** Why the file cannot go to the admin now, or null when it can. */
    private function sendRefusal(SrrvApplication $application): ?string
    {
        return match (true) {
            ! $application->isFeeFirst() || $application->status !== 'agreement' => 'A file is sent to the admin at the Service Agreement & Fees step.',
            (float) $application->service_fee <= 0 => 'Enter the fee first.',
            $application->isAwaitingApproval() => 'This file was already sent and is waiting for the admin.',
            $application->approval_status === SrrvApplication::APPROVAL_APPROVED => 'The admin already approved this file.',
            default => null,
        };
    }

    private function send(Request $request, SrrvApplication $application): string
    {
        DeskApproval::saved($application, $request->user(), 'SRRV', (string) $application->retiree_name, 'admin.srrv-approvals.index');
        ActivityLogger::log('SRRV', 'SEND', "Sent SRRV file {$application->reference} to the admin for approval");

        return $application->isAwaitingApproval()
            ? 'Sent to the admin. Once approved it goes to the cashier for payment.'
            : 'Approved. It is now with the cashier for payment.';
    }

    /**
     * What an older file (or one that is not a new application) records at its stage.
     *
     * @return ?array<string, mixed>
     */
    private function legacyStage(Request $request, SrrvApplication $application): ?array
    {
        return match ($application->status) {
            'requirements' => SrrvApplication::tickAttributes($this->tickedFrom($request->all())),
            'investment' => $request->validate([
                'investment_amount' => ['required', 'numeric', 'min:0.01'],
            ]),
            'supporting' => ['supporting_completed_at' => now()],
            'documentation' => $request->validate([
                'lodged_at' => ['required', 'date'],
                'pra_reference' => ['nullable', 'string', 'max:255'],
            ]),
            'oath' => $request->validate([
                'oath_at' => ['required', 'date'],
            ]),
            default => null,
        };
    }

    /**
     * Take a payment on the file. It can be taken at any stage; release
     * waits until the balance is cleared.
     */
    public function recordPayment(Request $request, SrrvApplication $application): RedirectResponse
    {
        if ($application->isFeeFirst() && ! $request->user()->isAdmin()) {
            return back()->with('error', 'The cashier records the payments on a new SRRV application.');
        }

        if ($held = $application->approvalBlocker('paid')) {
            return back()->with('error', $held);
        }

        $balance = $application->outstandingBalance();

        if ($balance <= 0) {
            return back()->with('error', 'This file has no balance to pay.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
        ]);

        $application->amount_paid = (float) $application->amount_paid + (float) $validated['amount'];

        if ($application->isFullyPaid()) {
            $application->payment_in_full_at = now();
        }

        $application->save();

        ActivityLogger::log('SRRV', 'PAYMENT', "Recorded {$application->currency} ".number_format((float) $validated['amount'], 2)." on {$application->reference}");

        ClientNotifier::send($application->retiree_email, $application->retiree_name, new PaymentReceivedNotification(
            'SRRV application', $application->reference, (string) $application->retiree_name, (string) $application->currency,
            (float) $validated['amount'], (float) $application->amount_paid, $application->outstandingBalance(),
        ));

        return back()->with('success', $application->outstandingBalance() > 0
            ? 'Payment recorded. Balance: '.$application->currency.' '.number_format($application->outstandingBalance(), 2).'.'
            : 'Payment recorded. The file is fully paid.');
    }

    /**
     * The Acknowledgment of Documents: what the counter received from the retiree, for them to sign.
     */
    public function acknowledgement(SrrvApplication $application): View
    {
        $application->load('documents');

        return view('srrv.applications.acknowledgement', ['application' => $application]);
    }

    /**
     * The Acknowledgment of Documents for the new-application wizard, before the file is saved: it lists what the
     * wizard has so far (the requirements ticked and the other documents chosen) for the retiree to check and sign.
     */
    public function blankAcknowledgement(Request $request): View
    {
        $given = $request->validate([
            'retiree_name' => ['nullable', 'string', 'max:255'],
            'visa_class' => ['nullable', 'in:'.implode(',', SrrvApplication::VISA_CLASSES)],
            'courtesy_type' => ['nullable', 'in:'.implode(',', array_keys(SrrvApplication::COURTESY_TYPES))],
            'is_pensioner' => ['nullable', 'boolean'],
            'received' => ['nullable', 'array'],
            'received.*' => ['string', 'in:'.implode(',', SrrvApplicationDocument::DOCUMENT_TYPES)],
        ]);
        $courtesy = ($given['visa_class'] ?? null) === 'courtesy';
        $application = new SrrvApplication([
            'service_type' => 'renewal_application',
            'visa_class' => $courtesy ? 'courtesy' : 'classic',
            'courtesy_type' => $courtesy ? ($given['courtesy_type'] ?? null) : null,
            'is_pensioner' => $request->boolean('is_pensioner') && ($given['courtesy_type'] ?? null) !== 'former_filipino',
            'retiree_name' => $given['retiree_name'] ?? null,
        ]);

        return view('srrv.applications.acknowledgement', [
            'application' => $application,
            'lines' => collect(array_unique($given['received'] ?? []))->map(fn (string $type) => SrrvApplicationDocument::TYPE_LABELS[$type])->values()->all(),
        ]);
    }

    /**
     * A released file is locked. The agent who needs a change asks the admin to unlock it, with a
     * reason; it shows on the admin's SRRV approvals page and in their email.
     */
    public function requestEdit(Request $request, SrrvApplication $application): RedirectResponse
    {
        if (! $application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is not locked. Request edit is for released files.");
        }

        if (! $application->isCleared()) {
            return back()->with('error', $application->approvalBlocker('unlocked'));
        }

        if ($request->user()->can('srrv.approve')) {
            return back()->with('error', "You can reopen {$application->reference} yourself.");
        }

        if ($application->hasEditRequest()) {
            return back()->with('error', "An edit is already requested for {$application->reference}. It is waiting for the admin.");
        }

        $validated = $request->validate(
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'Say what needs to change, so the admin can decide.'],
        );
        $reason = trim($validated['reason']);

        $application->requestEdit($request->user(), $reason);
        ActivityLogger::log('SRRV', 'EDIT_REQUESTED', "Asked to unlock {$application->reference}: {$reason}");

        try {
            Notification::send(Approvals::recipients('srrv', $request->user()), new SrrvEditRequestNotification('requested', $application->reference, (string) $application->retiree_name, $request->user()->name, $reason));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Edit requested for {$application->reference}. The admin will approve or decline it under SRRV Approvals.");
    }

    /** An approver opens a released file again (a pending edit request is cleared). */
    public function reopen(Request $request, SrrvApplication $application): RedirectResponse
    {
        if (! $request->user()->can('srrv.approve')) {
            return back()->with('error', "Only an admin or an SRRV approver can reopen {$application->reference}. Use Request edit to ask the admin.");
        }

        if (! $application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is not released.");
        }

        $application->reopen();
        ActivityLogger::log('SRRV', 'REOPEN', "Reopened released {$application->reference}");

        return back()->with('success', "{$application->reference} is open again.");
    }

    /** Why this person may not change a released file's content, or null: only an approver can, after reopening it. */
    private function lockedRefusal(Request $request, SrrvApplication $application): ?string
    {
        return $application->isReleasedLocked() && ! $request->user()->can('srrv.approve')
            ? "{$application->reference} is released and locked. Use Request edit to ask the admin to unlock it."
            : null;
    }

    /**
     * Cancel a retiree file.
     */
    public function cancel(SrrvApplication $application): RedirectResponse
    {
        if ($finished = $application->finishedState()) {
            return back()->with('error', "This file is {$finished}. Its status is locked.");
        }

        $application->update(['status' => 'cancelled']);

        ActivityLogger::log('SRRV', 'CANCEL', "Cancelled SRRV file {$application->reference}");

        $this->notifyStatus($application, 'Cancelled');

        return back()->with('success', "File {$application->reference} cancelled.");
    }

    /**
     * Remove a retiree file and everything filed under it.
     */
    public function destroy(SrrvApplication $application): RedirectResponse
    {
        $reference = $application->reference;

        DocumentStorage::disk()->deleteDirectory("srrv/{$reference}");
        $application->delete();

        ActivityLogger::log('SRRV', 'DELETE', "Deleted SRRV file {$reference}");

        return redirect()->route('srrv.services', SrrvClientLookupController::serviceFor('application', $application->visa_class))
            ->with('success', "SRRV file {$reference} deleted.");
    }

    /**
     * File a document against a retiree file.
     */
    public function storeDocument(Request $request, SrrvApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', SrrvApplicationDocument::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $this->fileDocument($application, $validated['document_type'], $request->file('file'), $validated['document_type'] === 'other' ? ($validated['remarks'] ?? null) : null);

        return back()->with('success', 'Document filed.');
    }

    /**
     * The requirements ticked as seen: the PRA list's boxes, plus the old police, pension and
     * military ticks for anything still sending them.
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function tickedFrom(array $input): array
    {
        $ticked = array_values(array_filter((array) ($input['requirements'] ?? []), 'is_string'));

        foreach (['police_clearance', 'pension_proof', 'military_service_proof'] as $type) {
            if (! empty($input["{$type}_received"])) {
                $ticked[] = $type;
            }
        }

        return $ticked;
    }

    /**
     * File the documents uploaded in the wizard's Requirements step.
     */
    private function fileUploads(Request $request, SrrvApplication $application): void
    {
        foreach ((array) $request->input('documents', []) as $index => $row) {
            $file = $request->file("documents.{$index}.file");

            if ($file instanceof UploadedFile) {
                $this->fileDocument($application, $row['type'], $file);
            }
        }
    }

    /**
     * Keep the document on the private disk and record it on the file.
     */
    private function fileDocument(SrrvApplication $application, string $type, UploadedFile $file, ?string $remarks = null): void
    {
        $application->documents()->create([
            'document_type' => $type,
            'file_path' => $file->store("srrv/{$application->reference}", DocumentStorage::diskName()),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
            'remarks' => $remarks,
        ]);

        ActivityLogger::log('SRRV', 'UPLOAD_DOCUMENT', "Filed a {$type} document on {$application->reference}");
    }

    /**
     * Download a filed document. Reachable only through the SRRV middleware.
     */
    public function downloadDocument(SrrvApplicationDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a file this staff member can see (their own, or any for admins).
        abort_unless($document->application, 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /**
     * Map validated input onto model attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated): array
    {
        return [
            'service_type' => $validated['service_type'],
            'visa_class' => $validated['visa_class'],
            'courtesy_type' => $validated['visa_class'] === 'courtesy' ? ($validated['courtesy_type'] ?? null) : null,
            // Former Filipinos pay the same deposit with or without a pension.
            'is_pensioner' => ! empty($validated['is_pensioner']) && ($validated['courtesy_type'] ?? null) !== 'former_filipino',
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'retiree_phone' => $validated['retiree_phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'investment_amount' => (float) ($validated['investment_amount'] ?? 0),

            // "Four copies of everything" is the standing rule.
            'copies_submitted' => (int) ($validated['copies_submitted'] ?? 4),
            'service_fee' => (float) ($validated['service_fee'] ?? 0),
            'amount_paid' => (float) ($validated['amount_paid'] ?? 0),
            'currency' => $validated['currency'] ?? 'USD',
            'remarks' => $validated['remarks'] ?? null,
            'form_details' => SrrvForms::clean($validated['form'] ?? null),
        ] + SrrvApplication::tickAttributes($this->tickedFrom($validated));
    }

    /**
     * The save rules; Save fee and Send to admin also need a service fee to save or send.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(Request $request, bool $opening = false, ?SrrvApplication $application = null): array
    {
        $rules = $this->rules($opening);

        // Save & Print and Finish send a file that has not gone to the admin yet, so they need a fee as well.
        $sendsOnPrint = in_array($request->input('then'), ['print', 'finish'], true) && ($opening || ($application?->isFeeFirst() && $application->approval_status === null));

        if ($sendsOnPrint || in_array($request->input('then'), ['fee', 'send'], true)) {
            $rules['service_fee'] = ['required', 'numeric', 'min:0.01', 'max:99999999.99'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $opening = false): array
    {
        return [
            'service_type' => ['required', 'in:'.implode(',', $opening ? SrrvApplication::OPEN_SERVICE_TYPES : SrrvApplication::SERVICE_TYPES)],
            'visa_class' => ['required', 'in:'.implode(',', SrrvApplication::VISA_CLASSES)],
            'courtesy_type' => ['nullable', 'required_if:visa_class,courtesy', 'in:'.implode(',', array_keys(SrrvApplication::COURTESY_TYPES))],
            'is_pensioner' => ['nullable', 'boolean'],
            // The PRA requirements ticked as seen.
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'in:'.implode(',', array_keys(SrrvApplication::REQUIREMENTS))],
            'retiree_name' => ['required', 'string', 'max:255'],
            'retiree_email' => ['nullable', 'email', 'max:255'],
            'retiree_phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'srrv_card_number' => ['nullable', 'string', 'max:100'],
            'investment_amount' => ['nullable', 'numeric', 'min:0'],
            'police_clearance_received' => ['nullable', 'boolean'],
            'pension_proof_received' => ['nullable', 'boolean'],
            'military_service_proof_received' => ['nullable', 'boolean'],
            'copies_submitted' => ['nullable', 'integer', 'min:1', 'max:10'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            // Documents uploaded in the Requirements step: a type for each file. Every requirement row
            // sends its type, so only the rows with a chosen file count towards the limit (PHP takes 20 files).
            'documents' => ['nullable', 'array', function (string $attribute, mixed $rows, \Closure $fail) {
                if (collect((array) $rows)->filter(fn ($row) => ($row['file'] ?? null) instanceof UploadedFile)->count() > 20) {
                    $fail('Upload at most 20 files at a time; save, then add the rest.');
                }
            }],
            'documents.*.type' => ['required_with:documents.*.file', 'nullable', 'in:'.implode(',', SrrvApplicationDocument::DOCUMENT_TYPES)],
            'documents.*.file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ] + SrrvForms::rules();
    }

    /**
     * Email the retiree that their file has moved on.
     */
    private function notifyStatus(SrrvApplication $application, string $stageLabel, ?string $note = null): void
    {
        // The client hears nothing until the admin has approved the file.
        if (! $application->isCleared()) {
            return;
        }

        ClientNotifier::send($application->retiree_email, $application->retiree_name, new FileStatusChangedNotification(
            'SRRV application', $application->reference, (string) $application->retiree_name, $stageLabel, $note,
        ));
    }

    /**
     * Keep what this file knows on the retiree's client record (the newest form wins), so their next
     * form at any desk starts with it: the SRRV number and class, passport, birth details, address.
     */
    private function rememberClient(SrrvApplication $application): void
    {
        $form = $application->form_details ?? [];

        ClientAccountService::findOrCreateClient([
            'name' => $application->retiree_name,
            'first_name' => $form['first_name'] ?? null,
            'last_name' => $form['last_name'] ?? null,
            'email' => $application->retiree_email,
            'phone' => $application->retiree_phone,
            'nationality' => $application->nationality,
            'date_of_birth' => $application->date_of_birth?->toDateString(),
            'place_of_birth' => $form['place_of_birth'] ?? null,
            'gender' => $form['gender'] ?? null,
            'civil_status' => $form['civil_status'] ?? null,
            'height' => $form['height'] ?? null,
            'weight' => $form['weight'] ?? null,
            'passport_number' => $form['passport_no'] ?? null,
            'passport_expiry' => $form['passport_expires_on'] ?? null,
            'passport_place' => $form['passport_place'] ?? null,
            'address' => $form['primary_address'] ?? null,
            'srrv_number' => $application->srrv_card_number,
            'srrv_visa_class' => $application->visa_class,
        ]);
    }
}
