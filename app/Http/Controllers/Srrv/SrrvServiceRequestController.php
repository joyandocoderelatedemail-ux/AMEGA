<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvServiceRequest;
use App\Models\SrrvServiceRequestDocument;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\DeskApproval;
use App\Support\DocumentStorage;
use App\Support\SrrvForms;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SRRV Termination of Membership, SRRV Conversion into Active Investment and SRRV Re-stamping: the
 * PRA's checklist (PRA-SD-FORM-0008 / 0010, the Citizen's Charter for re-stamping) ticked off as the documents come in, the details that decide which
 * requirements apply, the termination fees, and the stages through to completion. Each file is
 * checked by an approver before it moves on, like every desk file.
 */
class SrrvServiceRequestController extends Controller
{
    /** Which timestamp a stage records on entry. */
    private const STAGE_STAMPS = ['submitted' => 'submitted_at', 'endorsed' => 'submitted_at', 'ocular_inspection' => 'inspected_at', 'completed' => 'completed_at'];

    public function create(Request $request): View
    {
        $type = $this->type($request->query('type'));
        SrrvClientLookupController::prefill($request);

        return view('srrv.requests.create', ['type' => $type]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = $this->type($request->input('type'));
        $validated = $this->validated($request, $type);

        $file = new SrrvServiceRequest($this->attributes($validated, $type) + [
            'reference' => SrrvServiceRequest::generateReference($type),
            'type' => $type,
            'status' => 'documents',
        ]);
        $file->created_by = $request->user()->id;
        $file->save();
        $this->fileUploads($request, $file);

        DeskApproval::saved($file, $request->user(), 'SRRV', $file->retiree_name, 'admin.srrv-approvals.index');
        $this->rememberClient($file);
        ActivityLogger::log('SRRV', 'CREATE_REQUEST', "Opened {$file->typeLabel()} {$file->reference} for {$file->retiree_name}");

        return redirect()->route('srrv.requests.show', $file)->with('success', "{$file->reference} is opened.".($file->isAwaitingApproval() ? ' It goes to an approver before it moves on.' : ''));
    }

    public function show(SrrvServiceRequest $serviceRequest): View
    {
        return view('srrv.requests.show', ['file' => $serviceRequest->load(['creator:id,name', 'reviewedBy:id,name', 'documents'])]);
    }

    public function edit(Request $request, SrrvServiceRequest $serviceRequest): View
    {
        return view('srrv.requests.edit', ['file' => $serviceRequest, 'preview' => $request->boolean('preview')]);
    }

    public function update(Request $request, SrrvServiceRequest $serviceRequest): RedirectResponse
    {
        $file = $serviceRequest;
        $validated = $this->validated($request, $file->type);

        $file->fill($this->attributes($validated, $file->type));
        $changed = $file->isDirty();
        $file->save();
        $changed = $this->fileUploads($request, $file) || $changed;

        DeskApproval::saved($file, $request->user(), 'SRRV', $file->retiree_name, 'admin.srrv-approvals.index', $changed);
        $this->rememberClient($file);
        ActivityLogger::log('SRRV', 'UPDATE_REQUEST', "Updated {$file->typeLabel()} {$file->reference}");

        return redirect()->route('srrv.requests.show', $file)->with('success', "{$file->reference} is saved.");
    }

    /** Move the file to its next stage, once everything its current stage needs is in. */
    public function advance(SrrvServiceRequest $serviceRequest): RedirectResponse
    {
        $file = $serviceRequest;
        $next = $file->nextStage();

        if ($file->isFinal() || $next === null) {
            return back()->with('error', 'This request has no further stage.');
        }
        if ($blocker = $file->stageBlocker()) {
            return back()->with('error', $blocker);
        }

        $from = $file->status_label;
        $file->status = $next;
        if (isset(self::STAGE_STAMPS[$next])) {
            $file->{self::STAGE_STAMPS[$next]} = now();
        }
        $file->save();

        ActivityLogger::log('SRRV', 'ADVANCE_REQUEST', "{$file->reference}: {$from} → {$file->status_label}");

        return back()->with('success', "{$file->reference} is now at {$file->status_label}.");
    }

    /** Record what was paid towards the fees (US dollars to the PRA, pesos to the BI). */
    public function payment(Request $request, SrrvServiceRequest $serviceRequest): RedirectResponse
    {
        $file = $serviceRequest;
        abort_unless($file->hasFees(), 404);

        $validated = $request->validate([
            'paid_usd' => ['nullable', 'numeric', 'min:0', 'max:'.max(0, (float) $file->fee_usd - (float) $file->paid_usd)],
            'paid_php' => ['nullable', 'numeric', 'min:0', 'max:'.max(0, (float) $file->fee_php - (float) $file->paid_php)],
        ], [
            'paid_usd.max' => 'That is more than the US dollar balance.',
            'paid_php.max' => 'That is more than the peso balance.',
        ]);

        $usd = (float) ($validated['paid_usd'] ?? 0);
        $php = (float) ($validated['paid_php'] ?? 0);
        if ($usd <= 0 && $php <= 0) {
            return back()->with('error', 'Enter an amount received.');
        }

        $file->forceFill(['paid_usd' => (float) $file->paid_usd + $usd, 'paid_php' => (float) $file->paid_php + $php])->save();
        ActivityLogger::log('SRRV', 'PAYMENT', "Received US$ {$usd} and ₱{$php} on {$file->reference}");

        return back()->with('success', 'Payment recorded.'.($file->isFullyPaid() ? ' The fees are fully paid.' : ''));
    }

    public function cancel(SrrvServiceRequest $serviceRequest): RedirectResponse
    {
        $file = $serviceRequest;
        if ($file->isFinal()) {
            return back()->with('error', 'This request is already closed.');
        }

        $file->forceFill(['status' => 'cancelled'])->save();
        ActivityLogger::log('SRRV', 'CANCEL_REQUEST', "Cancelled {$file->reference}");

        return back()->with('success', "{$file->reference} is cancelled.");
    }

    public function destroy(SrrvServiceRequest $serviceRequest): RedirectResponse
    {
        $file = $serviceRequest;
        $type = $file->type;
        $reference = $file->reference;
        $file->delete();

        ActivityLogger::log('SRRV', 'DELETE_REQUEST', "Deleted {$reference}");

        return redirect()->route('srrv.services', $type)->with('success', "{$reference} deleted.");
    }

    /** Download a filed scan: only from a request this person can open (OwnFilesScope). */
    public function downloadDocument(SrrvServiceRequestDocument $document): StreamedResponse|RedirectResponse
    {
        abort_unless(SrrvServiceRequest::whereKey($document->srrv_service_request_id)->exists(), 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The document could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /** Take off a scan uploaded by mistake (a correction, not deleting the request). */
    public function destroyDocument(SrrvServiceRequestDocument $document): RedirectResponse
    {
        $file = SrrvServiceRequest::find($document->srrv_service_request_id) ?? abort(404);

        DocumentStorage::disk()->delete($document->file_path);
        $document->delete();
        ActivityLogger::log('SRRV', 'REMOVE_DOCUMENT', "Removed {$document->original_name} from {$file->reference}");

        return back()->with('success', "{$document->original_name} is removed.");
    }

    /**
     * File the scans sent with the form: one beside each requirement (which ticks it as received) and
     * any others. Whether anything was filed.
     */
    private function fileUploads(Request $request, SrrvServiceRequest $file): bool
    {
        $filed = false;
        $checklist = $file->checklist ?? [];

        foreach ((array) $request->file('documents', []) as $requirement => $upload) {
            if ($upload instanceof UploadedFile && array_key_exists($requirement, $file->requirements())) {
                $this->fileDocument($request, $file, $requirement, $upload);
                $checklist[] = $requirement;
                $filed = true;
            }
        }
        foreach ((array) $request->file('other_documents', []) as $upload) {
            if ($upload instanceof UploadedFile) {
                $this->fileDocument($request, $file, 'other', $upload);
                $filed = true;
            }
        }

        if ($filed) {
            $file->forceFill(['checklist' => array_values(array_unique($checklist))])->save();
        }

        return $filed;
    }

    private function fileDocument(Request $request, SrrvServiceRequest $file, string $requirement, UploadedFile $upload): void
    {
        $file->documents()->create([
            'requirement' => $requirement,
            'file_path' => $upload->store("srrv/{$file->reference}", DocumentStorage::diskName()),
            'original_name' => $upload->getClientOriginalName(),
            'file_size' => $upload->getSize(),
            'mime_type' => $upload->getClientMimeType(),
            'uploaded_by' => $request->user()->id,
        ]);
        ActivityLogger::log('SRRV', 'UPLOAD_DOCUMENT', "Filed {$upload->getClientOriginalName()} ({$requirement}) on {$file->reference}");
    }

    /** The PRA checklist, ticked from the file, to print and bring to the PRA. */
    public function print(SrrvServiceRequest $serviceRequest): View
    {
        return view('srrv.requests.print', ['file' => $serviceRequest]);
    }

    private function type(mixed $type): string
    {
        abort_unless(is_string($type) && array_key_exists($type, SrrvServiceRequest::TYPES), 404);

        return $type;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $type): array
    {
        $details = match ($type) {
            'termination' => [
                'details.reason' => ['required', Rule::in(array_keys(SrrvServiceRequest::REASONS))],
                'details.with_downgrading' => ['nullable', 'boolean'],
                'details.members' => ['required', 'integer', 'min:1', 'max:20'],
                'details.withdraw_deposit' => ['nullable', 'boolean'],
                'details.tax_free_importation' => ['nullable', 'boolean'],
                'details.estate' => ['nullable', Rule::in(['testate', 'intestate'])],
                'details.bank_details' => ['nullable', 'required_if:details.withdraw_deposit,1', 'string', 'max:2000'],
                'details.departure_on' => ['nullable', 'date'],
                'details.via_representative' => ['nullable', 'boolean'],
            ],
            'restamping' => [
                'details.members' => ['required', 'integer', 'min:1', 'max:20'],
                'details.old_passport' => ['required', Rule::in(array_keys(SrrvServiceRequest::OLD_PASSPORT))],
                'details.old_passport_number' => ['nullable', 'string', 'max:50'],
                'details.new_passport_number' => ['required', 'string', 'max:50'],
                'details.new_passport_issued_on' => ['nullable', 'date'],
                'details.new_passport_expiry' => ['nullable', 'date', 'after:today'],
                'details.new_passport_place' => ['nullable', 'string', 'max:255'],
                'details.address_local' => ['nullable', 'string', 'max:500'],
                'details.request_office' => ['nullable', Rule::in(array_keys(SrrvForms::OFFICES))],
                'details.name_changed' => ['nullable', 'boolean'],
                'details.nationality_changed' => ['nullable', 'boolean'],
                'details.derogatory_hit' => ['nullable', 'boolean'],
                'details.via_representative' => ['nullable', 'boolean'],
            ],
            default => [
                'details.property_address' => ['required', 'string', 'max:255'],
                'details.sublessor_name' => ['required', 'string', 'max:255'],
                'details.sublease_amount' => ['required', 'numeric', 'min:0'],
                'details.sublease_years' => ['required', 'integer', 'min:1', 'max:99'],
                'details.srrv_issued_on' => ['required', 'date', 'before_or_equal:today'],
                'details.ocular_inspection_on' => ['nullable', 'date'],
                'details.via_representative' => ['nullable', 'boolean'],
            ],
        };

        return $request->validate([
            'retiree_name' => ['required', 'string', 'max:255'],
            'retiree_email' => ['nullable', 'email', 'max:255'],
            'retiree_phone' => ['nullable', 'string', 'max:50'],
            'srrv_card_number' => ['nullable', 'string', 'max:100'],
            'visa_class' => ['nullable', Rule::in(SrrvApplication::VISA_CLASSES)],
            'srrv_application_id' => ['nullable', 'integer', 'exists:srrv_applications,id'],
            'checklist' => ['nullable', 'array'],
            'checklist.*' => ['string', Rule::in(array_keys(SrrvServiceRequest::requirementsFor($type, [])))],
            'remarks' => ['nullable', 'string', 'max:3000'],
            // Scans beside each requirement, and any others (PDF or image, up to 10 MB each).
            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'other_documents' => ['nullable', 'array', 'max:10'],
            'other_documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ] + $details, [
            'documents.*.mimes' => 'Upload a PDF, JPG, PNG or WEBP file.',
            'documents.*.max' => 'Each file can be up to 10 MB.',
            'other_documents.*.mimes' => 'Upload a PDF, JPG, PNG or WEBP file.',
            'details.bank_details.required_if' => "Enter the principal's complete bank details for the withdrawal of the visa deposit.",
            'details.srrv_issued_on.required' => 'Enter the date the SRRV was issued.',
            'details.new_passport_number.required' => 'Enter the new passport number.',
            'details.new_passport_expiry.after' => 'The new passport must not have expired.',
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(array $validated, string $type): array
    {
        $details = collect($validated['details'] ?? [])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
        foreach (['with_downgrading', 'withdraw_deposit', 'tax_free_importation', 'via_representative', 'name_changed', 'nationality_changed', 'derogatory_hit'] as $flag) {
            $details[$flag] = ! empty($details[$flag]);
        }

        $attributes = [
            'retiree_name' => trim($validated['retiree_name']),
            'retiree_email' => $validated['retiree_email'] ?? null,
            'retiree_phone' => $validated['retiree_phone'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'visa_class' => $validated['visa_class'] ?? ($type === 'conversion' ? 'classic' : null),
            'srrv_application_id' => $validated['srrv_application_id'] ?? null,
            'details' => $details,
            // Only what applies to these details is kept as received.
            'checklist' => array_values(array_intersect(
                $validated['checklist'] ?? [],
                array_keys(array_filter(SrrvServiceRequest::requirementsFor($type, $details), fn (array $item) => $item[2])),
            )),
            'remarks' => $validated['remarks'] ?? null,
        ];

        if ($fees = SrrvServiceRequest::feesFor($type, $details)) {
            $attributes['fee_usd'] = $fees['usd'];
            $attributes['fee_php'] = $fees['php'];
        }

        return $attributes;
    }

    /**
     * Keep what the request knows on the retiree's client record (the newest form wins): a
     * re-stamping brings the new passport, so the next form starts with it.
     */
    private function rememberClient(SrrvServiceRequest $file): void
    {
        $restamping = $file->type === 'restamping';

        ClientAccountService::findOrCreateClient([
            'name' => $file->retiree_name,
            'email' => $file->retiree_email,
            'phone' => $file->retiree_phone,
            'srrv_number' => $file->srrv_card_number,
            'srrv_visa_class' => $file->visa_class,
            'srrv_issued_on' => $file->detail('srrv_issued_on'),
            'passport_number' => $restamping ? $file->detail('new_passport_number') : null,
            'passport_expiry' => $restamping ? $file->detail('new_passport_expiry') : null,
            'passport_place' => $restamping ? $file->detail('new_passport_place') : null,
            'address' => $restamping ? $file->detail('address_local') : null,
        ]);
    }
}
