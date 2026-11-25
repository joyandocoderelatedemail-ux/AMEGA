<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvApplicationDocument;
use App\Notifications\ClientCaseNotification;
use App\Notifications\FileStatusChangedNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientNotifier;
use App\Support\DocumentStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * PRA Submission: the steps after the wizard, opened from its last step. 1 the application submitted to the PRA
 * (reference, proof), 2 the visa deposit and payment proofs, 3 the PRA's evaluation (processing, then approved,
 * withdrawn or declined: the last two close the file), 4 the approved documents received, 5 their turnover to the
 * client, which releases the file. Every step keeps its remarks and documents.
 */
class SrrvPraController extends Controller
{
    public function show(Request $request, SrrvApplication $application): View|RedirectResponse
    {
        if (! $application->canStartPra()) {
            return redirect()->route('srrv.applications.edit', $application)->with('error', 'Finish the application first: PRA Submission starts once the file is approved, paid and advanced.');
        }

        $reached = $application->status === 'requirements' ? 1 : $application->praStep();
        $asked = (int) $request->query('step', $reached);

        return view('srrv.applications.pra', [
            'application' => $application->load('documents'),
            'reached' => $reached,
            'startStep' => $asked >= 1 && $asked <= $reached ? $asked : $reached,
            'locked' => $application->isReleasedLocked(),
        ]);
    }

    /**
     * Step 7's PRA Submission button: the file enters the PRA Submission steps (status "PRA Submission - Step 1"), so Continue
     * comes back here. One already in them (or finished) just opens where it is.
     */
    public function start(SrrvApplication $application): RedirectResponse
    {
        if (! $application->canStartPra()) {
            return redirect()->route('srrv.applications.edit', $application)->with('error', 'Finish the application first: PRA Submission starts once the file is approved, paid and advanced.');
        }

        if ($application->status === 'requirements') {
            // The documents must be in, with the client's signed acknowledgment (the wizard's Documents step).
            if ($blocker = $application->stageBlocker()) {
                return redirect()->route('srrv.applications.edit', [$application, 'step' => 5])->with('error', $blocker);
            }

            $application->update(['status' => 'pra', 'pra_flow' => $application->pra_flow ?? ['step' => 1, 'decision' => 'processing', 'remarks' => []]]);
            ActivityLogger::log('SRRV', 'PRA_START', "Started PRA Submission on {$application->reference}");
        }

        return redirect()->route('srrv.applications.pra', [$application, 'step' => $application->praStep()]);
    }

    public function save(Request $request, SrrvApplication $application): RedirectResponse
    {
        if (! $application->canStartPra()) {
            return redirect()->route('srrv.applications.edit', $application)->with('error', 'Finish the application first.');
        }

        if ($application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is {$application->finishedState()} and locked.");
        }

        $reached = $application->status === 'requirements' ? 1 : $application->praStep();
        $validated = $request->validate([
            'step' => ['required', 'integer', 'min:1', 'max:'.$reached],
            'then' => ['nullable', 'in:save,continue,document'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'pra_reference' => ['nullable', 'string', 'max:255'],
            'documents_received_at' => ['nullable', 'date'],
            // Step 4: what the PRA issued with the approved documents.
            'srrv_card_number' => ['nullable', 'string', 'max:100'],
            'srrv_issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'pra_id_valid_until' => ['nullable', 'date'],
            'submitted' => ['nullable', 'boolean'],
            'decision' => ['nullable', 'in:'.implode(',', array_keys(SrrvApplication::PRA_DECISIONS))],
            // The step's Document Checklist: File Document saves the step and files the document, so nothing typed is lost.
            'document_type' => ['nullable', 'required_with:file', 'string', 'max:100'],
            'file' => [$request->input('then') === 'document' ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ], ['step.max' => 'Finish the earlier PRA Submission steps first.', 'document_type.required_with' => 'Say what the document is.',
            'file.required' => 'Choose the file, then press File Document.']);

        $step = (int) $validated['step'];
        $continue = ($validated['then'] ?? 'save') === 'continue';
        $filing = ($validated['then'] ?? null) === 'document';

        // Starting: the documents must be in, with the client's signed acknowledgment (the wizard's last stage).
        if ($application->status === 'requirements' && ($blocker = $application->stageBlocker())) {
            return back()->with('error', $blocker);
        }

        $flow = $application->pra_flow ?? ['step' => 1, 'decision' => 'processing', 'remarks' => []];
        $flow['remarks'][$step] = filled($validated['remarks'] ?? null) ? trim($validated['remarks']) : null;
        $updates = ['status' => 'pra'];

        if ($step === 1) {
            $updates['pra_reference'] = $validated['pra_reference'] ?? $application->pra_reference;
            if ($request->boolean('submitted')) {
                $updates['lodged_at'] = $application->lodged_at ?? now();
            }
        }

        if ($step === 4 && array_key_exists('documents_received_at', $validated)) {
            $updates['documents_received_at'] = filled($validated['documents_received_at']) ? $validated['documents_received_at'] : null;
        }
        if ($step === 4) {
            foreach (['srrv_card_number', 'srrv_issued_on', 'pra_id_valid_until'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = filled($validated[$field]) ? trim((string) $validated[$field]) : null;
                }
            }
        }

        // Filing a document never closes the file: a Withdrawn or Declined choice waits for Save & Close.
        $decision = $validated['decision'] ?? $flow['decision'] ?? 'processing';
        if ($step === 3 && ! ($filing && isset(SrrvApplication::PRA_CLOSINGS[$decision]))) {
            $flow['decision'] = $decision;
        }

        // What moving on needs, step by step. The SRRV number goes on the file before the turnover, so the
        // client's later renewals start from it.
        $numbered = filled($updates['srrv_card_number'] ?? $application->srrv_card_number);
        $needNumber = 'Enter the SRRV number the PRA issued (Step 4, Document Receive) before the turnover.';
        $refusal = ! $continue ? null : match ($step) {
            1 => $request->boolean('submitted') || $application->lodged_at ? null : 'Tick that the application and its documents were submitted to the PRA first.',
            3 => $flow['decision'] === 'processing' ? 'The PRA is still processing it: choose Approved, Withdrawn or Declined once it decides.' : null,
            4 => $numbered ? null : $needNumber,
            5 => match (true) {
                ! $application->isFullyPaid() => 'The file must be fully paid before the turnover. Balance: '.$application->currency.' '.number_format($application->outstandingBalance(), 2).'.',
                ! $numbered => $needNumber,
                default => null,
            },
            default => null,
        };

        $closing = $step === 3 ? (SrrvApplication::PRA_CLOSINGS[$flow['decision']] ?? null) : null;
        $next = $step;
        if ($closing) {
            // Withdrawn or declined: recorded as the file's closing status, and the file is closed and locked (finished, as released).
            $updates = array_merge($updates, ['status' => 'released', 'workflow_status' => $closing, 'workflow_status_at' => now(), 'released_on' => now()->toDateString()]);
        } elseif ($continue && $refusal === null) {
            if ($step === 5) {
                $updates = array_merge($updates, ['status' => 'released', 'released_at' => now(), 'released_on' => now()->toDateString()]);
            } else {
                $next = $step + 1;
                $flow['step'] = max((int) ($flow['step'] ?? 1), $next);
            }
        }

        $application->update($updates + ['pra_flow' => $flow]);
        if ($step === 4) {
            $this->rememberCard($application);
        }
        if ($request->file('file')) {
            $this->fileDocument($application, $step, trim((string) $validated['document_type']), $request->file('file'));
        }

        ActivityLogger::log('SRRV', 'PRA_STEP', "Saved PRA Submission step {$step} (".SrrvApplication::PRA_STEPS[$step].") on {$application->reference}"
            .($closing ? ': '.SrrvApplication::PRA_DECISIONS[$flow['decision']].', file closed' : '').($step === 5 && $application->status === 'released' ? ': turned over, file released' : ''));

        if ($application->status === 'released') {
            $this->tellClient($application, $application->progress_label);
        }

        $redirect = redirect()->route('srrv.applications.pra', [$application, 'step' => $next]);

        return match (true) {
            $refusal !== null => $redirect->with('error', $refusal),
            $closing !== null => $redirect->with('success', "{$application->reference} is closed: ".$application->progress_label.'.'),
            $application->status === 'released' => $redirect->with('success', "{$application->reference} is turned over to the client and released."),
            $filing => $redirect->with('success', 'Document filed, and '.SrrvApplication::PRA_STEPS[$step].' saved.'),
            default => $redirect->with('success', SrrvApplication::PRA_STEPS[$step].' saved.'),
        };
    }

    /** The SRRV number and PRA ID card on the retiree's client record: their renewals start from it, and the card's date shows on Expiring Documents. */
    private function rememberCard(SrrvApplication $application): void
    {
        if (blank($application->srrv_card_number) && ! $application->pra_id_valid_until) {
            return;
        }

        ClientAccountService::findOrCreateClient([
            'name' => $application->retiree_name,
            'email' => $application->retiree_email,
            'phone' => $application->retiree_phone,
            'srrv_number' => $application->srrv_card_number,
            'srrv_visa_class' => $application->visa_class,
            'srrv_issued_on' => $application->srrv_issued_on?->toDateString(),
            'pra_id_valid_until' => $application->pra_id_valid_until?->toDateString(),
        ]);
    }

    /** A document filed on a step, with what it is (as typed) kept in its remarks. */
    private function fileDocument(SrrvApplication $application, int $step, string $type, UploadedFile $file): void
    {
        $application->documents()->create([
            'document_type' => "pra_step_{$step}",
            'file_path' => $file->store("srrv/{$application->reference}", DocumentStorage::diskName()),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
            'remarks' => $type,
        ]);
        ActivityLogger::log('SRRV', 'UPLOAD_DOCUMENT', "Filed '{$type}' on PRA Submission step {$step} of {$application->reference}");
    }

    /** Take off a PRA Submission upload filed by mistake (a correction, not closing the file). */
    public function destroyDocument(SrrvApplicationDocument $document): RedirectResponse
    {
        // Only from a file this staff member can open (their own, or any for admins).
        $application = SrrvApplication::find($document->srrv_application_id) ?? abort(404);

        abort_unless(str_starts_with($document->document_type, 'pra_step_'), 404);

        if ($application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is {$application->finishedState()} and locked.");
        }

        DocumentStorage::disk()->delete($document->file_path);
        $document->delete();
        ActivityLogger::log('SRRV', 'REMOVE_DOCUMENT', "Removed {$document->original_name} from PRA Submission of {$application->reference}");

        return back()->with('success', "{$document->original_name} is removed.");
    }

    /**
     * Notify the retiree by email that their SRRV documents are ready for collection: the agent pastes the
     * template from the Notice box (or writes their own message), edits it and sends it. Works at any PRA step.
     */
    public function notify(Request $request, SrrvApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [
            'subject.required' => 'Give the email a subject.',
            'message.required' => 'Paste the template or write the message to send.',
            'email.email' => 'Enter a valid email address.',
        ]);

        // An email typed in the notice fills a file that has none (it never replaces one); the client account gets it too.
        if (! ClientNotifier::canReceive($application->retiree_email) && ClientNotifier::canReceive($validated['email'] ?? null)) {
            $application->update(['retiree_email' => strtolower(trim($validated['email']))]);
            ClientAccountService::findOrCreateClient([
                'name' => $application->retiree_name,
                'email' => $application->retiree_email,
                'phone' => $application->retiree_phone,
                'passport_number' => $application->form_details['passport_no'] ?? null,
            ]);
            ActivityLogger::log('SRRV', 'UPDATE', "Added the email {$application->retiree_email} to {$application->reference} ({$application->retiree_name}) from the notice");
        }

        if (! ClientNotifier::canReceive($application->retiree_email)) {
            return back()->withInput()->with('error', "{$application->reference} has no retiree email address on file. Add one in the notice, or copy the message and send it by text or Messenger.");
        }

        $subject = trim($validated['subject']);
        $notification = new ClientCaseNotification($subject, trim($validated['message']), [
            'reference' => $application->reference,
            'service' => 'SRRV Assistance ('.$application->optionLabel().')',
            'status' => $application->status_label,
        ], $request->user()->name, 'SRRV');

        if (! ClientNotifier::send($application->retiree_email, $application->retiree_name, $notification)) {
            return back()->withInput()->with('error', "The message could not be emailed to {$application->retiree_email}. Please try again.");
        }

        ActivityLogger::log('SRRV', 'CLIENT_NOTICE', "Emailed {$application->retiree_name} ({$application->retiree_email}) about {$application->reference}: {$subject}");

        return back()->with('success', "{$application->retiree_name} was notified: the message was emailed to {$application->retiree_email}.");
    }

    /** The retiree hears when the file is released or closed, once the admin has approved it. */
    private function tellClient(SrrvApplication $application, string $label): void
    {
        if (! $application->isCleared()) {
            return;
        }

        ClientNotifier::send($application->retiree_email, $application->retiree_name, new FileStatusChangedNotification(
            'SRRV application', $application->reference, (string) $application->retiree_name, $label,
            $application->workflow_status ? null : 'Your SRRV documents have been turned over to you.',
        ));
    }
}
