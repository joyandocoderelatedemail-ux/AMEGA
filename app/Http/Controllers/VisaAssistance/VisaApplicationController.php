<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Models\VisaPricingTier;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Support\DocumentStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The visa assistance counter file workflow: intake, the service pipeline,
 * applicants, and the document checklist.
 *
 * Uploads go to the private document disk and are only reachable through
 * downloadDocument(), so passport and ID scans are never web-exposed.
 */
class VisaApplicationController extends Controller
{
    /** How many files and clients the type-ahead shows of each. */
    private const LOOKUP_LIMIT = 6;

    /**
     * Display the counter file directory with filters.
     */
    public function index(Request $request): View
    {
        $query = VisaApplication::withCount('applicants')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('destination_country', 'like', "%{$search}%");
            });
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $applications = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => VisaApplication::count(),
            'visitVisas' => VisaApplication::where('service_type', 'visit_visa')->count(),
            'eVisas' => VisaApplication::where('service_type', 'e_visa')->count(),
            'passporting' => VisaApplication::where('service_type', 'passporting')->count(),
            'open' => VisaApplication::whereNotIn('status', ['released', 'cancelled'])->count(),
        ];

        return view('visa-assistance.applications.index', compact('applications', 'stats'));
    }

    /**
     * Type-ahead for the counter: open files matching the reference, client or
     * country, then registered clients (to open a new file for) by name, email,
     * phone or passport.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['files' => [], 'clients' => []]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $files = VisaApplication::query()
            ->where(fn ($match) => $match
                ->where('reference', 'like', $like)
                ->orWhere('client_name', 'like', $like)
                ->orWhere('client_email', 'like', $like)
                ->orWhere('client_phone', 'like', $like)
                ->orWhere('destination_country', 'like', $like)
                ->orWhereHas('applicants', fn ($applicant) => $applicant
                    ->where('passport_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                )
            )
            ->latest()
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (VisaApplication $file) => [
                'reference' => $file->reference,
                'name' => $file->client_name,
                'service' => str_replace('_', ' ', $file->service_type),
                'status' => $file->status_label,
                'country' => $file->destination_country,
                'url' => route('visa.applications.show', $file),
            ]);

        $clients = User::clientSearch($term)
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->full_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'passport' => $client->passport_number,
                'url' => route('visa.applications.create', ['client' => $client->id]),
            ]);

        return response()->json(['files' => $files, 'clients' => $clients]);
    }

    /**
     * Show the intake form. The service type decides which fields appear.
     */
    public function create(Request $request): View
    {
        $serviceType = $request->input('service_type', 'visit_visa');

        if (! in_array($serviceType, VisaApplication::SERVICE_TYPES, true)) {
            $serviceType = 'visit_visa';
        }

        // Opened for a registered client picked from the search.
        $client = $request->filled('client')
            ? User::where('role', 'client')->find($request->integer('client'))
            : null;

        return view('visa-assistance.applications.create', [
            'serviceType' => $serviceType,
            'prefillClient' => $client ? [
                'client_name' => $client->full_name,
                'client_email' => $client->email,
                'client_phone' => $client->phone,
            ] : [],
            'pricing' => VisaPricingTier::published()->orderBy('sort_order')->get(),
            'rushFee' => VisaPricingTier::amountFor('visit_visa', 'Rush'),
            ...$this->priceList(),
        ]);
    }

    /**
     * Store a newly opened counter file.
     */
    public function store(Request $request): RedirectResponse
    {
        $serviceType = $request->input('service_type', 'visit_visa');

        $validated = $request->validate($this->rulesFor($serviceType));

        $destinationCountry = $validated['destination_country'] ?? null;
        $processingSpeed = $validated['processing_speed'] ?? 'regular';

        // The rush surcharge comes from the counter's published fee schedule,
        // never from the form.
        $rushFee = ($serviceType === 'visit_visa' && $processingSpeed === 'rush')
            ? VisaPricingTier::amountFor('visit_visa', 'Rush')
            : 0.0;

        $serviceFee = (float) ($validated['service_fee'] ?? 0);
        $visaFee = (float) ($validated['visa_fee'] ?? 0);
        $insuranceFee = (float) ($validated['insurance_fee'] ?? 0);
        $etravelFee = (float) ($validated['etravel_fee'] ?? 0);

        $application = VisaApplication::create([
            'reference' => VisaApplication::generateReference(),
            'created_by' => Auth::id(),
            'service_type' => $serviceType,
            'status' => 'pending',
            'applicant_type' => $validated['applicant_type'] ?? 'individual',
            'client_name' => $validated['client_name'],
            'client_email' => $validated['client_email'] ?? null,
            'client_phone' => $validated['client_phone'] ?? null,
            'destination_country' => $destinationCountry,
            'purpose' => $validated['purpose'] ?? null,
            // Australia and New Zealand are handled without an embassy appearance.
            'requires_appearance' => $destinationCountry
                ? ! in_array($destinationCountry, VisaApplication::NO_APPEARANCE_COUNTRIES, true)
                : true,
            'processing_speed' => $processingSpeed,
            'passport_type' => $validated['passport_type'] ?? null,
            'embassy_country' => $validated['embassy_country'] ?? null,
            'appointment_at' => $validated['appointment_at'] ?? null,
            'insurance_included' => ! empty($validated['insurance_included']),
            'etravel_reference' => $validated['etravel_reference'] ?? null,
            'service_fee' => $serviceFee,
            'visa_fee' => $visaFee,
            'rush_fee' => $rushFee,
            'insurance_fee' => $insuranceFee,
            'etravel_fee' => $etravelFee,
            'total_amount' => $serviceFee + $visaFee + $rushFee + $insuranceFee + $etravelFee,
            'amount_paid' => (float) ($validated['amount_paid'] ?? 0),
            'payment_type' => $validated['payment_type'] ?? 'full',
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('Visa Assistance', 'CREATE', "Opened counter file {$application->reference} ({$application->service_type}) for {$application->client_name}");

        ClientAccountService::findOrCreateClient([
            'name' => $application->client_name,
            'email' => $application->client_email,
            'phone' => $application->client_phone,
        ]);

        return redirect()->route('visa.applications.show', $application)
            ->with('success', "Counter file {$application->reference} opened successfully.");
    }

    /**
     * Display the counter file: pipeline, applicants, documents and money.
     */
    public function show(VisaApplication $application): View
    {
        $application->load(['applicants.documents', 'documents', 'creator']);

        $stages = $application->stages();
        $currentIndex = array_search($application->status, $stages, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return view('visa-assistance.applications.show', [
            'application' => $application,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $stages[$currentIndex + 1] ?? null,
            'isFinal' => $currentIndex >= count($stages) - 1,
            'blocker' => $application->stageBlocker(),
            'documentTypes' => VisaApplicationDocument::DOCUMENT_TYPES,
        ]);
    }

    /**
     * Show the edit form for a counter file.
     */
    public function edit(VisaApplication $application): View
    {
        return view('visa-assistance.applications.edit', [
            'application' => $application,
            'pricing' => VisaPricingTier::published()->orderBy('sort_order')->get(),
            'rushFee' => VisaPricingTier::amountFor('visit_visa', 'Rush'),
            ...$this->priceList(),
        ]);
    }

    /**
     * Update a counter file's details.
     */
    public function update(Request $request, VisaApplication $application): RedirectResponse
    {
        $validated = $request->validate($this->rulesFor($application->service_type));

        $destinationCountry = $validated['destination_country'] ?? null;
        $processingSpeed = $validated['processing_speed'] ?? 'regular';

        $rushFee = ($application->service_type === 'visit_visa' && $processingSpeed === 'rush')
            ? VisaPricingTier::amountFor('visit_visa', 'Rush')
            : 0.0;

        $serviceFee = (float) ($validated['service_fee'] ?? 0);
        $visaFee = (float) ($validated['visa_fee'] ?? 0);
        $insuranceFee = (float) ($validated['insurance_fee'] ?? 0);
        $etravelFee = (float) ($validated['etravel_fee'] ?? 0);

        $application->update([
            'client_name' => $validated['client_name'],
            'client_email' => $validated['client_email'] ?? null,
            'client_phone' => $validated['client_phone'] ?? null,
            'applicant_type' => $validated['applicant_type'] ?? $application->applicant_type,
            'destination_country' => $destinationCountry,
            'purpose' => $validated['purpose'] ?? null,
            'requires_appearance' => $destinationCountry
                ? ! in_array($destinationCountry, VisaApplication::NO_APPEARANCE_COUNTRIES, true)
                : true,
            'processing_speed' => $processingSpeed,
            'passport_type' => $validated['passport_type'] ?? null,
            'embassy_country' => $validated['embassy_country'] ?? null,
            'appointment_at' => $validated['appointment_at'] ?? null,
            'insurance_included' => ! empty($validated['insurance_included']),
            'etravel_reference' => $validated['etravel_reference'] ?? null,
            'service_fee' => $serviceFee,
            'visa_fee' => $visaFee,
            'rush_fee' => $rushFee,
            'insurance_fee' => $insuranceFee,
            'etravel_fee' => $etravelFee,
            'total_amount' => $serviceFee + $visaFee + $rushFee + $insuranceFee + $etravelFee,
            'amount_paid' => (float) ($validated['amount_paid'] ?? 0),
            'payment_type' => $validated['payment_type'] ?? $application->payment_type,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('Visa Assistance', 'UPDATE', "Updated counter file {$application->reference}");

        return redirect()->route('visa.applications.show', $application)
            ->with('success', 'Counter file updated.');
    }

    /**
     * Advance the file one step along its service pipeline, once the current
     * stage's work is recorded on the file.
     */
    public function advance(VisaApplication $application): RedirectResponse
    {
        $stages = $application->stages();
        $current = array_search($application->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This file is not on an active pipeline.');
        }

        if ($current >= count($stages) - 1) {
            return back()->with('error', 'This file is already at its final stage.');
        }

        $application->load(['applicants', 'documents']);

        if ($blocker = $application->stageBlocker()) {
            return back()->with('error', $application->status_label.' is not done yet. '.$blocker);
        }

        $next = $stages[$current + 1];
        $from = $application->status;

        $application->update(['status' => $next]);

        ActivityLogger::log('Visa Assistance', 'ADVANCE', "Counter file {$application->reference} advanced from {$from} to {$next}");

        return back()->with('success', 'File moved to '.$application->stageLabel($next).'.');
    }

    /**
     * Record the current stage's work: the signed agreement, the insurance
     * taken or declined, the e-Travel reference, the signed acknowledgment,
     * the DFA appointment, or the lodgement and result.
     */
    public function recordStage(Request $request, VisaApplication $application): RedirectResponse
    {
        $updates = match ($application->status) {
            'agreement' => ['agreement_signed_at' => now()],
            'acknowledged' => ['acknowledgement_signed_at' => now()],
            'insurance' => $this->insuranceRecord($request),
            'etravel' => $request->validate([
                'etravel_reference' => ['required', 'string', 'max:255'],
            ]),
            'appointment' => $request->validate([
                'appointment_at' => ['required', 'date'],
            ]),
            'lodged' => $request->validate([
                'lodged_at' => ['nullable', 'date'],
                'embassy_reference' => ['nullable', 'string', 'max:255'],
                'result' => ['nullable', 'in:'.implode(',', VisaApplication::RESULTS)],
                'result_at' => ['nullable', 'required_with:result', 'date'],
                'result_reference' => ['nullable', 'string', 'max:255'],
                'result_validity' => ['nullable', 'string', 'max:255'],
            ]),
            default => null,
        };

        if ($updates === null) {
            return back()->with('error', 'There is nothing to record at this stage.');
        }

        $application->update($updates);

        ActivityLogger::log('Visa Assistance', 'RECORD_STAGE', "Recorded {$application->status} on {$application->reference}");

        return back()->with('success', $application->status_label.' recorded.');
    }

    /**
     * The client either takes travel insurance (provider and policy number)
     * or declines it.
     *
     * @return array<string, mixed>
     */
    private function insuranceRecord(Request $request): array
    {
        if ($request->boolean('insurance_declined')) {
            return [
                'insurance_declined' => true,
                'insurance_included' => false,
                'insurance_provider' => null,
                'insurance_policy_number' => null,
            ];
        }

        $validated = $request->validate([
            'insurance_provider' => ['required', 'string', 'max:255'],
            'insurance_policy_number' => ['required', 'string', 'max:255'],
        ]);

        return $validated + ['insurance_declined' => false, 'insurance_included' => true];
    }

    /**
     * Take a payment on the file. It can be taken at any stage; the Payment
     * stage waits until the balance is cleared.
     */
    public function recordPayment(Request $request, VisaApplication $application): RedirectResponse
    {
        $balance = $application->outstandingBalance();

        if ($balance <= 0) {
            return back()->with('error', 'This file has no balance to pay.');
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$balance],
        ]);

        $paid = (float) $application->amount_paid + (float) $validated['amount'];

        $application->update([
            'amount_paid' => $paid,
            'payment_type' => $paid >= (float) $application->total_amount ? 'full' : 'deposit',
        ]);

        ActivityLogger::log('Visa Assistance', 'PAYMENT', "Recorded {$application->currency} ".number_format((float) $validated['amount'], 2)." on {$application->reference}");

        return back()->with('success', $application->outstandingBalance() > 0
            ? 'Payment recorded. Balance: '.$application->currency.' '.number_format($application->outstandingBalance(), 2).'.'
            : 'Payment recorded. The file is fully paid.');
    }

    /**
     * The Acknowledgment of Documents receipt: what the counter received from
     * the client, for the client to sign.
     */
    public function acknowledgement(VisaApplication $application): View
    {
        $application->load(['applicants', 'documents.applicant']);

        return view('visa-assistance.applications.acknowledgement', ['application' => $application]);
    }

    /**
     * Cancel a counter file.
     */
    public function cancel(VisaApplication $application): RedirectResponse
    {
        $application->update(['status' => 'cancelled']);

        ActivityLogger::log('Visa Assistance', 'CANCEL', "Cancelled counter file {$application->reference}");

        return back()->with('success', "File {$application->reference} cancelled.");
    }

    /**
     * Remove a counter file and everything filed under it.
     */
    public function destroy(VisaApplication $application): RedirectResponse
    {
        $reference = $application->reference;

        DocumentStorage::disk()->deleteDirectory("visa/{$reference}");
        $application->delete();

        ActivityLogger::log('Visa Assistance', 'DELETE', "Deleted counter file {$reference}");

        return redirect()->route('visa.applications.index')
            ->with('success', "Counter file {$reference} deleted.");
    }

    /**
     * Add an applicant to a counter file. Group e-Visa files carry several.
     */
    public function storeApplicant(Request $request, VisaApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'passport_number' => ['nullable', 'string', 'max:100'],
            'passport_expiry_date' => ['nullable', 'date'],
            'passport_country' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $nextNumber = ((int) $application->applicants()->max('applicant_number')) + 1;

        $application->applicants()->create($validated + [
            'applicant_number' => $nextNumber,
            'is_primary' => $nextNumber === 1,
        ]);

        ActivityLogger::log('Visa Assistance', 'ADD_APPLICANT', "Added applicant #{$nextNumber} to {$application->reference}");

        return back()->with('success', "Applicant #{$nextNumber} added.");
    }

    /**
     * Remove an applicant from a counter file.
     */
    public function destroyApplicant(VisaApplication $application, VisaApplicant $applicant): RedirectResponse
    {
        if ($applicant->visa_application_id !== $application->id) {
            return back()->with('error', 'That applicant does not belong to this file.');
        }

        $applicant->delete();

        ActivityLogger::log('Visa Assistance', 'REMOVE_APPLICANT', "Removed an applicant from {$application->reference}");

        return back()->with('success', 'Applicant removed.');
    }

    /**
     * File a document against a counter file or one of its applicants.
     */
    public function storeDocument(Request $request, VisaApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', VisaApplicationDocument::DOCUMENT_TYPES)],
            'visa_applicant_id' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $applicantId = $validated['visa_applicant_id'] ?? null;
        if ($applicantId && ! $application->applicants()->whereKey($applicantId)->exists()) {
            return back()->with('error', 'That applicant does not belong to this file.');
        }

        $file = $request->file('file');
        $folder = "visa/{$application->reference}";
        $path = $file->store($folder, DocumentStorage::diskName());

        $application->documents()->create([
            'visa_applicant_id' => $applicantId,
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
        ]);

        ActivityLogger::log('Visa Assistance', 'UPLOAD_DOCUMENT', "Filed a {$validated['document_type']} document on {$application->reference}");

        return back()->with('success', 'Document filed.');
    }

    /**
     * Download a filed document. Reachable only through the visa middleware.
     */
    public function downloadDocument(VisaApplicationDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a file this staff member can see (their own, or any for admins).
        abort_unless($document->application, 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /**
     * The fee breakdown the intake form fills from: per-person visit visa
     * prices by country, and the e-Visa starting price per person.
     *
     * @return array{countryRates: Collection<int, VisaPricingTier>, eVisaRate: float}
     */
    private function priceList(): array
    {
        return [
            'countryRates' => VisaPricingTier::countryRates()->get(),
            'eVisaRate' => (float) (VisaPricingTier::published()
                ->forService('e_visa')
                ->orderBy('sort_order')
                ->value('service_fee') ?? 0),
        ];
    }

    /**
     * Validation rules, narrowed to the fields a service actually collects.
     *
     * @return array<string, mixed>
     */
    private function rulesFor(string $serviceType): array
    {
        $rules = [
            'service_type' => ['required', 'in:'.implode(',', VisaApplication::SERVICE_TYPES)],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'visa_fee' => ['nullable', 'numeric', 'min:0'],
            'insurance_fee' => ['nullable', 'numeric', 'min:0'],
            'etravel_fee' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'payment_type' => ['nullable', 'in:'.implode(',', VisaApplication::PAYMENT_TYPES)],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        if ($serviceType === 'visit_visa') {
            $rules['destination_country'] = ['required', 'string', 'max:255'];
            $rules['purpose'] = ['required', 'in:'.implode(',', VisaApplication::PURPOSES)];
            $rules['processing_speed'] = ['required', 'in:'.implode(',', VisaApplication::PROCESSING_SPEEDS)];
            $rules['insurance_included'] = ['nullable', 'boolean'];
            $rules['etravel_reference'] = ['nullable', 'string', 'max:255'];
        }

        if ($serviceType === 'e_visa') {
            $rules['applicant_type'] = ['required', 'in:'.implode(',', VisaApplication::APPLICANT_TYPES)];
        }

        if ($serviceType === 'passporting') {
            $rules['passport_type'] = ['required', 'in:'.implode(',', VisaApplication::PASSPORT_TYPES)];
            $rules['embassy_country'] = [
                'nullable',
                'required_if:passport_type,foreign',
                'string',
                'max:255',
            ];
            $rules['appointment_at'] = ['nullable', 'date'];
        }

        return $rules;
    }
}
