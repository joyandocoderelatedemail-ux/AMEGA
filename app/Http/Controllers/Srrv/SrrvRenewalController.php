<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvRenewalDocument;
use App\Models\User;
use App\Notifications\ClientCaseNotification;
use App\Notifications\FileStatusChangedNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientMemory;
use App\Services\ClientNotifier;
use App\Services\DeskApproval;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use App\Support\SrrvForms;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        'new_id_ready' => ['ready_at_pra_at'],
        'released_to_client' => ['collected_at'],
        'closed' => ['closed_at'],
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

        // The date range picked on the page (App\Support\DateRange).
        DateRange::fromRequest($request)->apply($query);
        $renewals = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => SrrvRenewal::count(),
            'classic' => SrrvRenewal::where('visa_class', 'classic')->count(),
            'courtesy' => SrrvRenewal::where('visa_class', 'courtesy')->count(),
            'open' => SrrvRenewal::whereNotIn('status', ['released_to_client', 'closed', 'cancelled'])->count(),
            'ready' => SrrvRenewal::where('status', 'new_id_ready')->count(),
        ];

        return view('srrv.renewals.index', compact('renewals', 'stats'));
    }

    /**
     * Show the renewal intake form.
     */
    public function create(Request $request): View
    {
        SrrvClientLookupController::prefill($request);

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
        $validated = $request->validate($this->rules($request), $this->messages());
        $this->requireSignedAgreement($request, null);

        $years = (int) $validated['years_paid'];
        $dependents = (int) ($validated['dependents'] ?? 0);

        $renewal = SrrvRenewal::create([
            'reference' => SrrvRenewal::generateReference(),
            'srrv_application_id' => $validated['srrv_application_id'] ?? null,
            'created_by' => Auth::id(),
            'status' => 'pending',
            'visa_class' => $validated['visa_class'],
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'retiree_phone' => $validated['retiree_phone'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'years_paid' => $years,
            'dependents' => $dependents,
            ...$this->fees($validated, $dependents, $years),
            'remarks' => $validated['remarks'] ?? null,
            'form_details' => SrrvForms::clean($validated['form'] ?? null),
        ]);

        DeskApproval::saved($renewal, $request->user(), 'SRRV renewal', (string) $renewal->retiree_name, 'admin.srrv-approvals.index');

        ActivityLogger::log('SRRV', 'CREATE_RENEWAL', "Opened renewal {$renewal->reference} for {$renewal->retiree_name} ({$renewal->visa_class}, {$years} year(s))");

        $this->rememberClient($renewal);

        $this->recordAcknowledgement($request, $renewal);
        if ($request->boolean('agreement_signed') && ! $renewal->agreement_signed_at) {
            $renewal->forceFill(['agreement_signed_at' => now()])->save();
            ActivityLogger::log('SRRV', 'AGREEMENT_SIGNED', "Client signed the service agreement on renewal {$renewal->reference}");
        }

        // Add file (Step 3) on a new renewal: the renewal saves first, the file is attached to it.
        if ($request->file('documents.new.file') instanceof UploadedFile) {
            $this->fileDocument($renewal, $validated['documents']['new']['type'], $request->file('documents.new.file'), trim((string) ($validated['documents']['new']['remarks'] ?? null)) ?: null);

            if ($request->input('then') === 'document') {
                return redirect()->route('srrv.renewals.edit', [$renewal, 'step' => 3])->with('success', "Renewal {$renewal->reference} saved. Document filed.");
            }
        }

        // Saved in place: the renewal stays open on its form, marked as saved.
        // Print fingerprint card (Step 4) saves what is typed first, then opens the filled card.
        if ($request->input('then') === 'agreement') {
            return redirect()->route('srrv.renewals.agreement', $renewal);
        }
        if ($request->input('then') === 'card') {
            return redirect()->route('srrv.renewals.fingerprint-card', $renewal);
        }

        return redirect()->route('srrv.renewals.edit', $renewal)
            ->with('success', "Renewal {$renewal->reference} saved.".($renewal->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
    }

    /**
     * Display a renewal.
     */
    public function show(SrrvRenewal $renewal): View
    {
        $renewal->load(['application', 'creator', 'reviewedBy', 'documents', 'notes.author']);

        $stages = SrrvRenewal::STAGES;
        $currentIndex = array_search($renewal->status, $stages, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return view('srrv.renewals.show', [
            'renewal' => $renewal,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'blocker' => $renewal->stageBlocker(),
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(Request $request, SrrvRenewal $renewal): View
    {
        return view('srrv.renewals.edit', [
            'renewal' => $renewal,
            'preview' => $request->boolean('preview'),
            'classFees' => SrrvRenewal::CLASS_FEES,
            'maxYears' => SrrvRenewal::MAX_YEARS_PREPAID,
        ]);
    }

    /**
     * Update a renewal. The fee is recomputed if the class or years changed.
     */
    public function update(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        $validated = $request->validate($this->rules($request), $this->messages());
        $this->requireSignedAgreement($request, $renewal);

        $years = (int) $validated['years_paid'];
        $dependents = (int) ($validated['dependents'] ?? 0);

        $renewal->update([
            'srrv_application_id' => $validated['srrv_application_id'] ?? $renewal->srrv_application_id,
            'visa_class' => $validated['visa_class'],
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'retiree_phone' => $validated['retiree_phone'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'years_paid' => $years,
            'dependents' => $dependents,
            ...$this->fees($validated, $dependents, $years),
            'remarks' => $validated['remarks'] ?? null,
            'form_details' => SrrvForms::clean($validated['form'] ?? null),
        ]);

        DeskApproval::saved($renewal, $request->user(), 'SRRV renewal', (string) $renewal->retiree_name, 'admin.srrv-approvals.index', $renewal->wasChanged());

        ActivityLogger::log('SRRV', 'UPDATE_RENEWAL', "Updated renewal {$renewal->reference}");

        $this->rememberClient($renewal);

        $this->recordAcknowledgement($request, $renewal);
        if ($request->boolean('agreement_signed') && ! $renewal->agreement_signed_at) {
            $renewal->forceFill(['agreement_signed_at' => now()])->save();
            ActivityLogger::log('SRRV', 'AGREEMENT_SIGNED', "Client signed the service agreement on renewal {$renewal->reference}");
        }

        // File Document (Step 3): the step's upload row saves what is typed and files the document, then stays on Step 3.
        if ($request->file('documents.new.file') instanceof UploadedFile) {
            $this->fileDocument($renewal, $validated['documents']['new']['type'], $request->file('documents.new.file'), trim((string) ($validated['documents']['new']['remarks'] ?? null)) ?: null);

            if ($request->input('then') === 'document') {
                return redirect()->route('srrv.renewals.edit', [$renewal, 'step' => 3])->with('success', 'Document filed.');
            }
        }

        // Saved in place: stay on the step that was saved.
        $formStep = (int) $request->input('form_step', 0);

        // Print fingerprint card (Step 4) saves what is typed first, then opens the filled card.
        if ($request->input('then') === 'agreement') {
            return redirect()->route('srrv.renewals.agreement', $renewal);
        }
        if ($request->input('then') === 'card') {
            return redirect()->route('srrv.renewals.fingerprint-card', $renewal);
        }

        return redirect()->route('srrv.renewals.edit', [$renewal] + ($formStep >= 1 && $formStep <= 5 ? ['step' => $formStep] : []))
            ->with('success', 'Renewal saved.'.($renewal->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
    }

    /**
     * Download a filed scan. Reachable only through the SRRV middleware.
     */
    public function downloadDocument(SrrvRenewalDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a renewal this staff member can see (their own, or any for admins).
        abort_unless(SrrvRenewal::whereKey($document->srrv_renewal_id)->exists(), 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /** Take off a scan uploaded by mistake (a correction, not deleting the renewal). */
    public function destroyDocument(SrrvRenewalDocument $document): RedirectResponse
    {
        $renewal = SrrvRenewal::find($document->srrv_renewal_id) ?? abort(404);

        if ($renewal->finishedState()) {
            return back()->with('error', "This renewal is {$renewal->finishedState()} and locked.");
        }

        DocumentStorage::disk()->delete($document->file_path);
        $document->delete();
        ActivityLogger::log('SRRV', 'REMOVE_DOCUMENT', "Removed {$document->original_name} from renewal {$renewal->reference}");

        return back()->with('success', "{$document->original_name} is removed.");
    }

    /** The Acknowledgment of Documents, printed for the retiree to check and sign: what is filed on the renewal. */
    public function acknowledgement(SrrvRenewal $renewal): View
    {
        $lines = $renewal->documents->reject(fn ($document) => $document->document_type === 'signed_acknowledgement')
            ->map(fn ($document) => (SrrvRenewalDocument::TYPE_LABELS[$document->document_type] ?? $document->document_type).($document->remarks ? ': '.$document->remarks : ''))
            ->values()->all();

        return view('srrv.applications.acknowledgement', [
            'application' => $renewal,
            'lines' => $lines,
            'service' => ucfirst((string) $renewal->visa_class).' ID Renewal',
            'purpose' => 'SRRV ID renewal',
            'backUrl' => route('srrv.renewals.edit', [$renewal, 'step' => 3]),
        ]);
    }

    /** PRA-SD-FORM-0016, filled from the renewal: name, SRRV number and the officer's part; impressions and signature by hand. */
    public function fingerprintCard(SrrvRenewal $renewal): View
    {
        // Step 4 lets the agent continue only once the card has been printed.
        if (! $renewal->card_printed_at) {
            $renewal->forceFill(['card_printed_at' => now()])->save();
        }

        return view('srrv.renewals.fingerprint-card', ['renewal' => $renewal]);
    }

    /**
     * The renewal is recorded only once the client has signed the service agreement. Saving on the way (filing a
     * document, printing the agreement or the fingerprint card) is allowed, so the agreement can be printed first.
     */
    private function requireSignedAgreement(Request $request, ?SrrvRenewal $renewal): void
    {
        if (in_array($request->input('then'), ['document', 'card', 'agreement'], true) || $request->boolean('agreement_signed') || $renewal?->agreement_signed_at) {
            return;
        }

        throw ValidationException::withMessages(['agreement_signed' => 'The client must sign the service agreement before the renewal is saved. Print it, have the client sign, then record it on Step 5.']);
    }

    /** Step 3: the retiree signed the printed acknowledgment (recorded once), and the scan of the signed copy. */
    private function recordAcknowledgement(Request $request, SrrvRenewal $renewal): void
    {
        if ($request->boolean('acknowledgement_signed') && ! $renewal->acknowledgement_signed_at) {
            $renewal->forceFill(['acknowledgement_signed_at' => now()])->save();
            ActivityLogger::log('SRRV', 'ACKNOWLEDGEMENT_SIGNED', "Retiree signed the acknowledgment of documents on renewal {$renewal->reference}");
        }

        if ($request->file('signed_acknowledgement') instanceof UploadedFile) {
            $this->fileDocument($renewal, 'signed_acknowledgement', $request->file('signed_acknowledgement'));
        }
    }

    /**
     * Keep the document on the private disk and record it on the renewal.
     */
    private function fileDocument(SrrvRenewal $renewal, string $type, UploadedFile $file, ?string $remarks = null): void
    {
        $renewal->documents()->create([
            'document_type' => $type,
            'file_path' => $file->store("srrv/{$renewal->reference}", DocumentStorage::diskName()),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
            'remarks' => $remarks,
        ]);

        ActivityLogger::log('SRRV', 'UPLOAD_DOCUMENT', "Filed a {$type} document on renewal {$renewal->reference}");
    }

    /**
     * Write a line to the renewal's running notes log.
     */
    private function writeNote(SrrvRenewal $renewal, ?User $author, string $note): void
    {
        $renewal->notes()->create([
            'created_by' => $author?->id,
            'note' => $note,
        ]);

        ActivityLogger::log('SRRV', 'NOTE_RENEWAL', "Noted on renewal {$renewal->reference}: ".mb_substr($note, 0, 120));
    }

    /**
     * Advance the renewal one step. Steps that need their own details (the PRA
     * submission's date and remarks, who the new ID was released to) move
     * through the status form or the release form instead.
     */
    public function advance(SrrvRenewal $renewal): RedirectResponse
    {
        if ($held = $renewal->approvalBlocker('advanced')) {
            return back()->with('error', $held);
        }

        $stages = SrrvRenewal::STAGES;
        $current = array_search($renewal->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This renewal is no longer moving through its steps.');
        }

        if ($current >= count($stages) - 1) {
            return back()->with('error', 'This renewal is already at its final stage.');
        }

        $next = $stages[$current + 1];

        if ($next === 'released_to_client') {
            return back()->with('error', 'Use "Record release" so who received the new ID is captured.');
        }

        if ($next === 'submitted_to_pra') {
            return back()->with('error', 'Use the status form: submitting to the PRA needs its date and remarks.');
        }

        if ($blocker = $renewal->stageBlocker()) {
            return back()->with('error', $renewal->status_label.' is not done yet. '.$blocker);
        }

        $milestones = [];
        foreach (self::STAGE_MILESTONES[$next] ?? [] as $column) {
            $milestones[$column] = now();
        }

        // A new ID arriving without an expiry set is good for two years from today.
        if ($next === 'new_id_ready' && $renewal->card_expiry_date === null) {
            $milestones['card_expiry_date'] = now()->addYears(2)->toDateString();
        }

        $renewal->update(['status' => $next] + $milestones);

        ActivityLogger::log('SRRV', 'ADVANCE_RENEWAL', "Renewal {$renewal->reference} advanced to {$next}");

        $this->notifyStatus($renewal, $renewal->stageLabel($next), $next === 'new_id_ready'
            ? 'Your renewed SRRV ID is ready at the PRA office. It must be collected in person.'
            : null);

        return back()->with('success', 'Renewal moved to '.$renewal->stageLabel($next).'.');
    }

    /**
     * Move the renewal to the chosen status. The dropdown on the file replaces
     * stepping through pages: each stage's own details ride along (the PRA
     * submission's date and remarks, who received the new ID, the card expiry).
     */
    public function updateStatus(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        $target = (string) $request->input('status');

        if (! in_array($target, SrrvRenewal::STAGES, true)) {
            return back()->with('error', 'Choose a status to move the renewal to.');
        }

        if ($target === $renewal->status) {
            return back()->with('success', "Renewal {$renewal->reference} is already at {$renewal->status_label}.");
        }

        // A finished file is locked, except closing after the release.
        if ($renewal->finishedState() && ! ($renewal->status === 'released_to_client' && $target === 'closed')) {
            return back()->with('error', "This renewal is {$renewal->finishedState()}. Its status is locked.");
        }

        // One step at a time, in order: the file goes only to the stage after the one it is at.
        $current = array_search($renewal->status, SrrvRenewal::STAGES, true);
        if ($current === false || (SrrvRenewal::STAGES[$current + 1] ?? null) !== $target) {
            return back()->with('error', 'A renewal moves one step at a time: finish '.$renewal->status_label.' first.');
        }

        $validated = $request->validate([
            'submitted_on' => ['nullable', 'date', 'before_or_equal:today'],
            'submitted_remarks' => ['nullable', 'string', 'max:500'],
            'released_to_name' => ['nullable', 'string', 'max:255'],
            'card_expiry_date' => ['nullable', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'submitted_on.before_or_equal' => 'The submission date cannot be in the future.',
            'card_expiry_date.after' => 'The card expiry date must be in the future.',
        ]);

        if ($target === 'submitted_to_pra' && (blank($validated['submitted_on'] ?? null) || blank($validated['submitted_remarks'] ?? null))) {
            return back()->withInput()->with('error', 'Submitting to the PRA needs its date and remarks.');
        }

        if ($target === 'released_to_client' && blank($validated['released_to_name'] ?? null)) {
            return back()->withInput()->with('error', 'Record who the new ID was released to.');
        }

        $updates = ['status' => $target];

        if ($target === 'submitted_to_pra') {
            $updates['submitted_at'] = $validated['submitted_on'];
            $updates['submitted_remarks'] = trim((string) $validated['submitted_remarks']);
        }

        if ($target === 'released_to_client') {
            $updates['collected_at'] = $renewal->collected_at ?? now();
            $updates['collected_by_name'] = trim((string) $validated['released_to_name']);
        }

        // A new ID arriving without an expiry set is good for two years from today.
        if ($target === 'new_id_ready') {
            $updates['card_expiry_date'] = $validated['card_expiry_date'] ?? $renewal->card_expiry_date?->toDateString() ?? now()->addYears(2)->toDateString();
        } elseif (filled($validated['card_expiry_date'] ?? null)) {
            $updates['card_expiry_date'] = $validated['card_expiry_date'];
        }

        foreach (self::STAGE_MILESTONES[$target] ?? [] as $column) {
            $updates[$column] = $renewal->getAttribute($column) ?? now();
        }

        // The current stage's own work must be done before leaving it.
        if ($blocker = $renewal->stageBlocker()) {
            return back()->withInput()->with('error', $renewal->status_label.' is not done yet. '.$blocker);
        }

        $renewal->update($updates);

        if (filled($validated['note'] ?? null)) {
            $this->writeNote($renewal, $request->user(), trim((string) $validated['note']));
        }

        ActivityLogger::log('SRRV', 'STATUS_RENEWAL', "Renewal {$renewal->reference} moved to {$target}");

        $this->notifyStatus($renewal, $renewal->stageLabel($target), $target === 'new_id_ready'
            ? 'Your renewed SRRV ID is ready at the PRA office. It must be collected in person.'
            : null);

        $this->rememberClient($renewal);

        return back()->with('success', "Renewal {$renewal->reference} is now at {$renewal->status_label}.");
    }

    /**
     * Notify the retiree by email that their new SRRV ID is ready for collection:
     * the agent pastes the template from the Notice box (or writes their own
     * message), edits it and sends it.
     */
    public function notify(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'subject.required' => 'Give the email a subject.',
            'message.required' => 'Paste the template or write the message to send.',
        ]);

        if (! ClientNotifier::canReceive($renewal->retiree_email)) {
            return back()->withInput()->with('error', "{$renewal->reference} has no retiree email address on file. Copy the message and send it by text or Messenger.");
        }

        $subject = trim($validated['subject']);
        $notification = new ClientCaseNotification($subject, trim($validated['message']), [
            'reference' => $renewal->reference,
            'service' => 'SRRV ID Renewal ('.ucfirst((string) $renewal->visa_class).')',
            'status' => $renewal->status_label,
        ], $request->user()->name, 'SRRV');

        if (! ClientNotifier::send($renewal->retiree_email, $renewal->retiree_name, $notification)) {
            return back()->withInput()->with('error', "The message could not be emailed to {$renewal->retiree_email}. Please try again.");
        }

        $renewal->forceFill(['client_notified_at' => now()])->save();

        ActivityLogger::log('SRRV', 'CLIENT_NOTICE', "Emailed {$renewal->retiree_name} ({$renewal->retiree_email}) about {$renewal->reference}: {$subject}");

        return back()->with('success', "{$renewal->retiree_name} was notified: the message was emailed to {$renewal->retiree_email}.");
    }

    /**
     * Add a line to the renewal's running notes log: what happened, stamped
     * with when and who wrote it.
     */
    public function storeNote(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        if ($renewal->finishedState() && $renewal->status !== 'released_to_client') {
            return back()->with('error', "This renewal is {$renewal->finishedState()}. Its status is locked.");
        }

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $this->writeNote($renewal, $request->user(), trim($validated['note']));

        return back()->with('success', 'Note added to the log.');
    }

    /**
     * Record that the new ID was released in person at the PRA office.
     */
    public function collect(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        if ($held = $renewal->approvalBlocker('released')) {
            return back()->with('error', $held);
        }

        if ($renewal->status !== 'new_id_ready') {
            return back()->with('error', 'The new ID is not ready at the PRA office yet.');
        }

        if ($blocker = $renewal->stageBlocker()) {
            return back()->with('error', $blocker);
        }

        $validated = $request->validate([
            'collected_by_name' => ['required', 'string', 'max:255'],
        ]);

        $renewal->update([
            'status' => 'released_to_client',
            'collected_at' => now(),
            'collected_by_name' => $validated['collected_by_name'],
        ]);

        ActivityLogger::log('SRRV', 'RELEASE_RENEWAL', "Renewal {$renewal->reference} released to {$validated['collected_by_name']}");

        $this->notifyStatus($renewal, $renewal->stageLabel('released_to_client'), "Released to {$validated['collected_by_name']} on ".now()->format('F j, Y').'.');

        $this->rememberClient($renewal);

        return back()->with('success', "Renewal {$renewal->reference} released to {$validated['collected_by_name']}.");
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
     * Cancel a renewal.
     */
    public function cancel(SrrvRenewal $renewal): RedirectResponse
    {
        if ($finished = $renewal->finishedState()) {
            return back()->with('error', "This renewal is {$finished}. Its status is locked.");
        }

        $renewal->update(['status' => 'cancelled']);

        ActivityLogger::log('SRRV', 'CANCEL_RENEWAL', "Cancelled renewal {$renewal->reference}");

        $this->notifyStatus($renewal, 'Cancelled');

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

        return redirect()->route('srrv.services', SrrvClientLookupController::serviceFor('renewal', $renewal->visa_class))
            ->with('success', "Renewal {$reference} deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(Request $request): array
    {
        return [
            'srrv_application_id' => ['nullable', 'integer', 'exists:srrv_applications,id'],
            'visa_class' => ['required', 'in:'.implode(',', array_keys(SrrvRenewal::CLASS_FEES))],
            'retiree_name' => ['required', 'string', 'max:255'],
            'retiree_email' => ['nullable', 'email', 'max:255'],
            'retiree_phone' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            // Only a retiree who already holds an SRRV can renew its ID card.
            'srrv_card_number' => ['required', 'string', 'max:100'],
            // The ceiling on how far ahead a retiree can pay.
            'years_paid' => ['required', 'integer', 'min:1', 'max:'.SrrvRenewal::MAX_YEARS_PREPAID],
            'dependents' => ['nullable', 'integer', 'min:0', 'max:20'],
            // AMEGA's service fee is always in PHP, with the agent's discount on it; the admin approves both.
            // The PRA fee is always in USD and derived, never submitted: no currency, no conversion.
            'service_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'discount_type' => ['nullable', Rule::in(array_keys(SrrvRenewal::DISCOUNT_TYPES))],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            // Documents uploaded on Step 3 Documentation: a type and, for Other, what the document is.
            'documents.new.type' => ['required_with:documents.new.file', 'nullable', 'in:'.implode(',', SrrvRenewalDocument::DOCUMENT_TYPES)],
            'documents.new.remarks' => [Rule::requiredIf(fn () => $request->file('documents.new.file') instanceof UploadedFile && $request->input('documents.new.type') === 'other'), 'nullable', 'string', 'max:255'],
            'acknowledgement_signed' => ['nullable', 'boolean'],
            'agreement_signed' => ['nullable', 'boolean'],
            'signed_acknowledgement' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'documents.new.file' => [$request->input('then') === 'document' ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ] + SrrvForms::rules(renewal: true);
    }

    /**
     * What the renewal charges: the PRA fee in USD (derived, never submitted),
     * and the service fee in PHP with the agent's discount on it, a percentage
     * or an amount, never more than the fee.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function fees(array $validated, int $dependents, int $years): array
    {
        $prices = [
            'fee_amount' => round(SrrvRenewal::annualFee($validated['visa_class'], $dependents) * $years, 2),
            'currency' => SrrvRenewal::FEE_CURRENCY,
            'service_currency' => SrrvRenewal::SERVICE_CURRENCY,
            'exchange_rate' => null,
        ];

        $fee = round((float) ($validated['service_fee'] ?? 0), 2);
        $type = $validated['discount_type'] ?? null;
        $value = round((float) ($validated['discount_value'] ?? 0), 2);

        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages(['discount_value' => 'A percentage discount cannot be more than 100%.']);
        }

        $discount = SrrvRenewal::discountFor($type, $value, $fee);
        if ($discount > $fee) {
            throw ValidationException::withMessages(['discount_value' => 'The discount cannot be more than the service fee (₱'.number_format($fee, 2).').']);
        }

        return $prices + ($discount > 0
            ? ['service_fee' => $fee, 'discount_type' => $type, 'discount_value' => $value, 'discount_amount' => $discount]
            : ['service_fee' => $fee, 'discount_type' => null, 'discount_value' => 0.0, 'discount_amount' => 0.0]);
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'srrv_card_number.required' => 'Enter the retiree\'s SRRV number: only an SRRV holder can renew the ID card.',
            'documents.new.remarks.required' => 'Say what the other document is.',
            'documents.new.file.required' => 'Choose the file, then press File Document.',
        ];
    }

    /**
     * Email the retiree that their renewal has moved on.
     */
    private function notifyStatus(SrrvRenewal $renewal, string $stageLabel, ?string $note = null): void
    {
        // The client hears nothing until the admin has approved the file.
        if (! $renewal->isCleared()) {
            return;
        }

        ClientNotifier::send($renewal->retiree_email, $renewal->retiree_name, new FileStatusChangedNotification(
            'SRRV renewal', $renewal->reference, (string) $renewal->retiree_name, $stageLabel, $note,
        ));
    }

    /** Keep the SRRV number and class on the retiree's client record (the newest form wins). */
    /** Step 5 (New ID Ready): the new card's expiry as printed on it, also kept on the client's record. */
    public function saveCardExpiry(Request $request, SrrvRenewal $renewal): RedirectResponse
    {
        if ($renewal->status !== 'new_id_ready') {
            return back()->with('error', 'The expiry of the new ID is set at New ID Ready.');
        }

        $validated = $request->validate(['card_expiry_date' => ['required', 'date', 'after:today']], ['card_expiry_date.after' => 'The card expiry date must be in the future.']);
        $renewal->update(['card_expiry_date' => $validated['card_expiry_date']]);
        $this->rememberClient($renewal);
        ActivityLogger::log('SRRV', 'CARD_EXPIRY', "Set the new ID expiry on renewal {$renewal->reference} to {$validated['card_expiry_date']}");

        return back()->with('success', 'New ID expiry saved.');
    }

    private function rememberClient(SrrvRenewal $renewal): void
    {
        $client = ClientAccountService::findOrCreateClient([
            'name' => $renewal->retiree_name,
            'email' => $renewal->retiree_email,
            'phone' => $renewal->retiree_phone,
            'nationality' => $renewal->nationality,
            'date_of_birth' => $renewal->date_of_birth?->toDateString(),
            'srrv_number' => $renewal->srrv_card_number,
            'srrv_visa_class' => $renewal->visa_class,
        ]);

        // The new card's expiry feeds Reports & Analytics > Expiring Documents, which reminds the next renewal.
        if ($renewal->card_expiry_date) {
            ClientMemory::remember($client, ['pra_id_valid_until' => $renewal->card_expiry_date->toDateString()]);
        }
    }
}
