<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Models\VisaPayment;
use App\Models\VisaPricingTier;
use App\Notifications\ClientCaseNotification;
use App\Notifications\FileStatusChangedNotification;
use App\Notifications\VisaEditRequestNotification;
use App\Notifications\VisaStageDoneNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientMemory;
use App\Services\ClientNotifier;
use App\Services\DeskApproval;
use App\Services\VisaPaymentRecorder;
use App\Support\AmountInWords;
use App\Support\Approvals;
use App\Support\CaseProgress;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use App\Support\FormSpec;
use App\Support\ServiceList;
use App\Support\VisaChecklist;
use App\Support\VisaWorksheets;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Plainer wording for the Non-Immigrant visa type, which is a checklist or a purpose. */
    private const MESSAGES = ['purpose.required_without' => 'Choose the visa type.'];

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

        // The date range picked on the page (App\Support\DateRange).
        DateRange::fromRequest($request)->apply($query);
        $applications = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => VisaApplication::count(),
            'visitVisas' => VisaApplication::where('service_type', 'visit_visa')->count(),
            'eVisas' => VisaApplication::where('service_type', 'e_visa')->count(),
            'passporting' => VisaApplication::where('service_type', 'passporting')->count(),
            'citizenship' => VisaApplication::where('service_type', 'citizenship')->count(),
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
            ->map(fn (User $client) => $this->lookupClient($client, $this->stepService($request)));

        return response()->json(['files' => $files, 'clients' => $clients]);
    }

    /**
     * A service's files, before anything is started: each opens as a read-only preview on one page, and
     * New goes on to start one. The files the admin is holding (waiting for approval, or returned) come
     * first whatever their status, then the work in progress, then the released ones. Staff other than
     * admins see their own files.
     */
    public function services(Request $request, string $service): View
    {
        $files = VisaApplication::where('service_type', $service)
            ->where('status', '!=', 'cancelled')
            // Opened within the date range picked on the page.
            ->when(true, fn ($query) => DateRange::fromRequest($request)->apply($query))
            ->orderForList(['released'])
            ->latest()
            ->limit(200)
            ->get();

        return view('visa-assistance.services.list', [
            'service' => $service,
            'label' => VisaApplication::SERVICE_LABELS[$service],
            'rows' => $files->map(fn (VisaApplication $file) => ServiceList::denied($file->status === 'released' && $file->result === 'denied', ServiceList::row(
                (string) $file->client_name,
                $file->reference.($file->destination_country ? ' · '.$file->destination_country : ''),
                $file->approval_status,
                $file->status_label,
                // As on the Immigration list: green when approved or released, red when denied, navy once the application
                // is with the embassy or portal, amber while the counter still has work to do.
                CaseProgress::tone(match (true) {
                    $file->result === 'denied' => 'denied',
                    $file->status === 'released', $file->result === 'approved' => CaseProgress::RELEASED,
                    in_array($file->status, ['lodged', 'result'], true) => 'submitted',
                    default => 'preparing',
                }),
                $file->status === 'released',
                $file->review_note,
                route('visa.applications.show', [$file, 'preview' => 1]),
                $file->created_at,
            ))),
            // Non-Immigrant picks the country first; E-Visa and Passporting open their own form.
            'newUrl' => in_array($service, VisaApplication::STEP_SERVICES, true)
                ? route('visa.visit.new', $service === 'visit_visa' ? [] : ['service' => $service])
                : route('visa.applications.create', ['service_type' => $service]),
        ]);
    }

    /**
     * A new Non-Immigrant file starts, as at the Immigration counter, on the client folders: the clients who already
     * have a Non-Immigrant file, with a search on top (existing files, then registered clients), and Add a new client.
     * Picking one opens the wizard (wizard()) with the client filled in.
     */
    public function start(Request $request): View|RedirectResponse
    {
        // An old link that already names the client or the country goes straight on to the wizard.
        if ($request->filled('client') || $request->filled('country')) {
            return redirect()->route('visa.visit.file', array_filter($request->only(['client', 'country', 'service']), 'filled'));
        }

        $service = $this->stepService($request);
        $emails = VisaApplication::where('service_type', $service)->whereNotNull('client_email')->select('client_email');
        $clients = User::where('role', 'client')->whereIn('email', $emails)->orderBy('name')->paginate(15);

        return view('visa-assistance.visit-visa.start', ['clients' => $clients, 'serviceType' => $service, 'serviceLabel' => VisaApplication::SERVICE_LABELS[$service]]);
    }

    /**
     * The new Non-Immigrant file, step by step like the Immigration counter's: Step 0 Overview, then the client, the
     * country and visa type, the fees, and Send for Admin Approval (an admin opens the file straight away). It posts to
     * store(). ?client= fills in a registered client; ?country= picks the destination.
     */
    public function wizard(Request $request): View
    {
        $client = $request->filled('client') ? User::where('role', 'client')->find($request->integer('client')) : null;

        return view($this->stepService($request) !== 'visit_visa' ? 'visa-assistance.visit-visa.e-visa-wizard' : 'visa-assistance.visit-visa.wizard', [
            'countryRates' => VisaPricingTier::countryRates()->get()->unique('country')->values(),
            'serviceType' => $this->stepService($request),
            'serviceLabel' => VisaApplication::SERVICE_LABELS[$this->stepService($request)],
            'picked' => $client ? $this->lookupClient($client, $this->stepService($request)) : null,
            'prefillCountry' => trim((string) $request->input('country')),
            'rushFee' => VisaPricingTier::amountFor('visit_visa', 'Rush'),
        ]);
    }

    /** The service a new step-by-step file is for: E-Visa on request, otherwise Non-Immigrant. */
    private function stepService(Request $request): string
    {
        return in_array($request->input('service'), ['e_visa', 'passporting', 'citizenship'], true) ? $request->input('service') : 'visit_visa';
    }

    /** @return array{id: int, name: string, email: ?string, phone: ?string, passport: ?string, url: string} */
    private function lookupClient(User $client, string $service = 'visit_visa'): array
    {
        return [
            'id' => $client->id,
            'name' => $client->full_name,
            'email' => $client->real_email,
            'phone' => $client->phone,
            'passport' => $client->passport_number,
            'url' => route('visa.visit.file', array_filter(['client' => $client->id, 'service' => $service === 'visit_visa' ? null : $service])),
        ];
    }

    /**
     * Show the intake form. The service type decides which fields appear.
     */
    public function create(Request $request): View|RedirectResponse
    {
        // No service named: the picker, for e-Visa and passporting (a Non-Immigrant file has its own wizard).
        $serviceType = $request->input('service_type', 'passporting');

        // Opened for one service (from the New Counter File menu): the form is that
        // service's only, without the picker. Opened without one, staff choose.
        $serviceLocked = in_array($serviceType, VisaApplication::SERVICE_TYPES, true) && $request->filled('service_type');

        if (! in_array($serviceType, VisaApplication::SERVICE_TYPES, true)) {
            $serviceType = 'passporting';
        }

        // A new Non-Immigrant or E-Visa file is opened with its own wizard (Step 0 Overview first); this form is for passporting.
        if (in_array($serviceType, VisaApplication::STEP_SERVICES, true)) {
            return redirect()->route($request->filled('client') || $request->filled('country') ? 'visa.visit.file' : 'visa.visit.new', array_filter([...$request->only(['client', 'country']), 'service' => $serviceType === 'visit_visa' ? null : $serviceType], 'filled'));
        }

        // Opened for a registered client picked from the search.
        $client = $request->filled('client')
            ? User::where('role', 'client')->find($request->integer('client'))
            : null;

        return view('visa-assistance.applications.create', [
            'clientId' => $client?->id,
            'serviceType' => $serviceType,
            'serviceLocked' => $serviceLocked,
            'prefillCountry' => trim((string) $request->input('country')),
            'prefillClient' => $client ? [
                'client_name' => $client->full_name,
                'client_email' => $client->real_email,
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

        $validated = $request->validate($this->rulesFor($serviceType), self::MESSAGES);

        $destinationCountry = $serviceType === 'citizenship' ? 'USA' : ($validated['destination_country'] ?? null);
        $processingSpeed = $validated['processing_speed'] ?? 'regular';
        [$checklistKey, $purpose] = $serviceType === 'visit_visa' ? $this->visaType($validated) : [null, $serviceType === 'citizenship' ? $validated['claim_type'] : null];

        // The destination's draft form (the country's application form, see VisaWorksheets): filled in on this
        // same page before the file can be sent to the admin, not left for later. A destination with no form
        // (not every country has one yet) has nothing to fill in here. The wizard shows one panel per possible
        // destination, each under its own "form_<worksheet>" key, so only the one resolved here is read or
        // required; an unseen, hidden panel's blank fields never reach the file.
        $isEVisa = in_array($serviceType, ['e_visa', 'passporting', 'citizenship'], true);
        $worksheet = $serviceType === 'visit_visa' ? VisaWorksheets::resolve($destinationCountry, $checklistKey, $purpose) : null;
        $draftForm = [];

        if ($worksheet !== null) {
            $prefix = 'form_'.$worksheet;
            $draftForm = FormSpec::clean($request->validate(VisaWorksheets::rules($worksheet, $prefix))[$prefix] ?? null);

            // "nullable" (needed so a half-filled form still validates) would stop a rule closure on the same key
            // from ever running when the field is absent, so the "it must be filled in" check is done by hand.
            if ($draftForm === []) {
                throw ValidationException::withMessages([$prefix => 'Fill in the draft form before sending it to admin.']);
            }
        }

        // The rush surcharge comes from the counter's published fee schedule,
        // never from the form.
        $rushFee = (in_array($serviceType, VisaApplication::STEP_SERVICES, true) && $processingSpeed === 'rush')
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
            // An E-Visa or Passporting file is opened at the end of Step 1, so it starts on Step 2, the service agreement.
            'status' => $isEVisa ? 'agreement' : 'pending',
            'applicant_type' => $validated['applicant_type'] ?? 'individual',
            'client_name' => $validated['client_name'],
            'client_email' => $validated['client_email'] ?? null,
            'client_phone' => $validated['client_phone'] ?? null,
            'destination_country' => $destinationCountry,
            'purpose' => $purpose,
            'checklist_key' => $checklistKey,
            // Australia and New Zealand are handled without an embassy appearance.
            'requires_appearance' => $destinationCountry
                ? ! in_array($destinationCountry, VisaApplication::NO_APPEARANCE_COUNTRIES, true)
                : true,
            'processing_speed' => $processingSpeed,
            'passport_type' => $validated['passport_type'] ?? null,
            'us_citizen_parent' => $validated['us_citizen_parent'] ?? null,
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
            // Money comes in through the cashier: only an admin may enter an amount already paid.
            'amount_paid' => $request->user()->isAdmin() ? (float) ($validated['amount_paid'] ?? 0) : 0.0,
            // A new file is opened unpaid: the down payment is taken at the Payment step.
            'payment_type' => $validated['payment_type'] ?? ((float) ($validated['amount_paid'] ?? 0) > 0 ? 'full' : 'deposit'),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('Visa Assistance', 'CREATE', "Opened transaction {$application->reference} ({$application->service_type}) for {$application->client_name}");

        $client = ClientAccountService::findOrCreateClient([
            'name' => $application->client_name,
            'email' => $application->client_email,
            'phone' => $application->client_phone,
        ]);

        // The client is the first applicant; group members are added after. Their draft form, filled in on the
        // same page, is saved with them, so the file already has it when it reaches the admin.
        $applicant = $application->addClientAsApplicant($client);
        if ($draftForm !== []) {
            $applicant->update(['form_details' => $draftForm]);
        }

        // "New file from this one": the earlier file's other applicants, and (if ticked) its documents, carry over.
        if ($request->filled('copy_from')) {
            $this->carryOver($request, $application, $applicant);
        }

        // An E-Visa goes to the admin later, at Step 4 with its fee; the others are sent as they are opened.
        if (! $isEVisa) {
            DeskApproval::saved($application, $request->user(), 'Visa Assistance', (string) $application->client_name, 'admin.visa-approvals.index');
        }

        return redirect()->route('visa.applications.show', $application)
            ->with('success', "Transaction {$application->reference} opened successfully.".($application->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
    }

    /**
     * "New file from this one": open the new-file wizard with an earlier file's details filled in (the client, the
     * service's own fields, the applicants' draft form answers), so the agent edits what changed instead of typing it
     * all again. Nothing is saved here; the new file is saved as any other. Fees, payments, signatures, results, dates
     * and documents are not carried (documents only if the agent ticks it in the wizard).
     */
    public function copy(VisaApplication $application): RedirectResponse
    {
        $application->load('applicants');
        $primary = $application->applicants->firstWhere('is_primary', true) ?? $application->applicants->first();

        $input = array_filter([
            'client_name' => $application->client_name,
            'client_email' => $application->client_email,
            'client_phone' => $application->client_phone,
            'destination_country' => $application->service_type === 'passporting' ? null : $application->destination_country,
            'checklist_key' => $application->service_type === 'visit_visa' ? $application->checklist_key : null,
            'purpose' => $application->service_type === 'visit_visa' ? $application->purpose : null,
            'processing_speed' => $application->service_type === 'visit_visa' ? $application->processing_speed : null,
            'pax' => $application->service_type === 'visit_visa' ? max(1, $application->applicants->count()) : null,
            'passport_type' => $application->passport_type,
            'embassy_country' => $application->embassy_country,
            'claim_type' => $application->service_type === 'citizenship' ? $application->purpose : null,
            'us_citizen_parent' => $application->us_citizen_parent,
            'remarks' => $application->remarks,
            'copy_from' => $application->id,
        ], fn ($value) => filled($value));

        // The draft form answers go under every destination's form: each form reads only the questions it asks, so a
        // different destination keeps the answers that match and leaves the rest blank.
        $answers = (array) ($primary?->form_details ?? []);
        if ($answers !== []) {
            foreach (array_values(array_unique([...array_values(VisaWorksheets::COUNTRIES), 'australia_business'])) as $worksheet) {
                $input['form_'.$worksheet] = $answers;
            }
        }

        return redirect()->route('visa.visit.file', array_filter(['service' => $application->service_type === 'visit_visa' ? null : $application->service_type], 'filled'))
            ->withInput($input);
    }

    /**
     * The file a new one was copied from: only one this person may see, for the same service.
     */
    private function copySource(Request $request, string $serviceType): ?VisaApplication
    {
        $source = VisaApplication::find($request->integer('copy_from'));

        return $source && $source->service_type === $serviceType ? $source : null;
    }

    /**
     * Put the copied file's other applicants (with their draft forms), the primary one's details the client record
     * lacks, and, if the agent ticked it, its documents onto the new file.
     */
    private function carryOver(Request $request, VisaApplication $application, VisaApplicant $primary): void
    {
        $source = $this->copySource($request, $application->service_type);

        if (! $source) {
            return;
        }

        $source->load(['applicants', 'documents']);
        $sourcePrimary = $source->applicants->firstWhere('is_primary', true) ?? $source->applicants->first();
        $carried = ['first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'gender', 'nationality', 'passport_number', 'passport_expiry_date', 'passport_country', 'remarks'];
        $map = [];

        if ($sourcePrimary) {
            // What the client record does not hold (a passport, a birth date) comes from the earlier file.
            $primary->update(collect($sourcePrimary->only($carried))->filter(fn ($value, $key) => filled($value) && blank($primary->{$key}))->all());
            $map[$sourcePrimary->id] = $primary->id;
        }

        foreach ($source->applicants->where('id', '!=', $sourcePrimary?->id) as $member) {
            $copy = $application->applicants()->create($member->only($carried) + [
                'applicant_number' => ((int) $application->applicants()->max('applicant_number')) + 1,
                'is_primary' => false,
                'form_details' => $member->form_details,
            ]);
            $map[$member->id] = $copy->id;
        }

        if ($request->boolean('copy_documents')) {
            foreach ($source->documents->where('document_type', '!=', 'signed_acknowledgement') as $document) {
                $disk = DocumentStorage::disk();

                if (! $disk->exists($document->file_path)) {
                    continue;
                }

                $path = "visa/{$application->reference}/".basename($document->file_path);
                $disk->copy($document->file_path, $path);
                $application->documents()->create([
                    'visa_applicant_id' => $document->visa_applicant_id ? ($map[$document->visa_applicant_id] ?? null) : null,
                    'document_type' => $document->document_type,
                    'remarks' => $document->remarks,
                    'file_path' => $path,
                    'original_name' => $document->original_name,
                    'file_size' => $document->file_size,
                    'mime_type' => $document->mime_type,
                    'status' => 'uploaded',
                ]);
            }
        }

        ActivityLogger::log('Visa Assistance', 'COPY', "Opened {$application->reference} from {$source->reference}".($request->boolean('copy_documents') ? ' with its documents' : ''));
    }

    /**
     * Display the counter file: pipeline, applicants, documents and money.
     */
    public function show(Request $request, VisaApplication $application): View
    {
        $application->load(['applicants.documents', 'documents', 'creator', 'payments.receivedBy']);

        // Opened from a service list or the approvals page: the whole file, read-only, on one page.
        if ($request->boolean('preview')) {
            return view('visa-assistance.applications.preview', ['application' => $application]);
        }

        $stages = $application->stages();
        $currentIndex = array_search($application->status, $stages, true);
        $currentIndex = $application->isReleasedVisit() ? count($stages) - 1 : ($currentIndex === false ? 0 : $currentIndex);
        $isVisitResult = $application->usesSteps() && $application->status === 'result';

        return view('visa-assistance.applications.show', [
            'application' => $application,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $isVisitResult ? 'released' : ($stages[$currentIndex + 1] ?? null),
            // A Non-Immigrant file is final only once released; its last step still waits on the release.
            'isFinal' => $application->usesSteps() ? $application->isReleasedVisit() : $currentIndex >= count($stages) - 1,
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
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        // Already sent: it waits for the admin, who approves it or returns it to be corrected.
        if ($application->sentAndWaitingFor($request->user())) {
            return redirect()->route('visa.applications.show', $application)
                ->with('error', "{$application->reference} was already sent and is waiting for the admin to approve it, so nothing was saved. You can change it again if the admin returns it.");
        }

        $validated = $request->validate($this->rulesFor($application->service_type), self::MESSAGES);

        $destinationCountry = $application->service_type === 'citizenship' ? 'USA' : ($validated['destination_country'] ?? null);
        $processingSpeed = $validated['processing_speed'] ?? 'regular';
        [$checklistKey, $purpose] = $application->service_type === 'visit_visa' ? $this->visaType($validated) : [null, $application->service_type === 'citizenship' ? ($validated['claim_type'] ?? $application->purpose) : null];

        $rushFee = ($application->usesSteps() && $processingSpeed === 'rush')
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
            'purpose' => $purpose ?? $application->purpose,
            'checklist_key' => $application->usesSteps() ? $checklistKey : $application->checklist_key,
            'requires_appearance' => $destinationCountry
                ? ! in_array($destinationCountry, VisaApplication::NO_APPEARANCE_COUNTRIES, true)
                : true,
            'processing_speed' => $processingSpeed,
            'passport_type' => $validated['passport_type'] ?? null,
            'us_citizen_parent' => $validated['us_citizen_parent'] ?? $application->us_citizen_parent,
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
            // What was paid changes only through the cashier's payments (an admin may correct it here).
            'amount_paid' => $request->user()->isAdmin() && ! $application->isFeeFirst() ? (float) ($validated['amount_paid'] ?? 0) : (float) $application->amount_paid,
            'payment_type' => $request->user()->isAdmin() && ! $application->isFeeFirst() ? ($validated['payment_type'] ?? $application->payment_type) : $application->payment_type,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        DeskApproval::saved($application, $request->user(), 'Visa Assistance', (string) $application->client_name, 'admin.visa-approvals.index', $application->wasChanged());

        ActivityLogger::log('Visa Assistance', 'UPDATE', "Updated transaction {$application->reference}");

        return redirect()->route('visa.applications.show', $application)
            ->with('success', 'Transaction updated.'.($application->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
    }

    /**
     * Advance the file one step along its service pipeline, once the current
     * stage's work is recorded on the file.
     */
    public function advance(VisaApplication $application): RedirectResponse
    {
        if ($held = $application->approvalBlocker('advanced')) {
            return back()->with('error', $held);
        }

        $stages = $application->stages();
        $current = array_search($application->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This file is not on an active pipeline.');
        }

        // A Non-Immigrant file's last step (Result & Release) moves on to "released".
        $releasing = $application->usesSteps() && $application->status === 'result';

        if ($current >= count($stages) - 1 && ! $releasing) {
            return back()->with('error', 'This file is already at its final stage.');
        }

        $application->load(['applicants', 'documents']);

        if ($blocker = $application->stageBlocker()) {
            return back()->with('error', $application->status_label.' is not done yet. '.$blocker);
        }

        $next = $releasing ? 'released' : $stages[$current + 1];
        $from = $application->status;

        $application->update(['status' => $next]);

        ActivityLogger::log('Visa Assistance', 'ADVANCE', "Transaction {$application->reference} advanced from {$from} to {$next}");

        $this->notifyStatus($application, $application->stageLabel($next), $next === 'released'
            ? ($application->result === 'denied'
                ? 'Your application was not approved. Please visit our office so we can explain the decision and what you can do next.'
                : 'Your documents are ready. Please visit our office to collect them.')
            : null);

        return back()->with('success', $next === 'released' && $application->result === 'denied' ? 'File finished.' : 'File moved to '.$application->stageLabel($next).'.');
    }

    /**
     * Notify the client by email, now, at any stage: the agent pastes the template from the Notice box (or writes
     * their own message), edits it and sends it. Branded like the Immigration notices, with the file it is about.
     */
    public function notifyClient(Request $request, VisaApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'subject.required' => 'Give the email a subject.',
            'message.required' => 'Paste the template or write the message to send.',
        ]);

        if (! ClientNotifier::canReceive($application->client_email)) {
            return back()->withInput()->with('error', "{$application->reference} has no client email address on file. Copy the message and send it by text or Messenger.");
        }

        $subject = trim($validated['subject']);
        $notification = new ClientCaseNotification($subject, trim($validated['message']), [
            'reference' => $application->reference,
            'service' => VisaApplication::SERVICE_LABELS[$application->service_type] ?? 'Visa Assistance',
            'status' => $application->status_label,
        ], $request->user()->name, 'Visa Assistance');

        if (! ClientNotifier::send($application->client_email, $application->client_name, $notification)) {
            return back()->withInput()->with('error', "The message could not be emailed to {$application->client_email}. Please try again.");
        }

        ActivityLogger::log('Visa Assistance', 'CLIENT_NOTICE', "Emailed {$application->client_name} ({$application->client_email}) about {$application->reference}: {$subject}");

        return back()->with('success', "{$application->client_name} was notified: the message was emailed to {$application->client_email}.");
    }

    /**
     * Email the client that one finished stage of their file is done.
     */
    public function notifyStage(VisaApplication $application, string $stage): RedirectResponse
    {
        if ($held = $application->approvalBlocker('sent to the client')) {
            return back()->with('error', $held);
        }

        if (! in_array($stage, $application->completedStages(), true)) {
            return back()->with('error', 'Only a finished stage can be sent to the client.');
        }

        $label = $application->stageLabel($stage);

        if (! ClientNotifier::canReceive($application->client_email)) {
            return back()->with('error', "{$application->reference} has no client email address on file.");
        }

        if (! ClientNotifier::send($application->client_email, $application->client_name, new VisaStageDoneNotification($application, $stage))) {
            return back()->with('error', "The {$label} update could not be sent to {$application->client_email}. Please try again.");
        }

        $application->update([
            'stage_notified_at' => array_merge($application->stage_notified_at ?? [], [$stage => now()->toIso8601String()]),
        ]);

        ActivityLogger::log('Visa Assistance', 'NOTIFY', "Emailed {$application->client_email} that {$label} is done on {$application->reference}");

        return back()->with('success', "Emailed {$application->client_email} that {$label} is done.");
    }

    /**
     * Record the current stage's work: the signed agreement, the insurance
     * taken or declined, the e-Travel reference, the signed acknowledgment,
     * the DFA appointment, or the lodgement and result.
     */
    public function recordStage(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $isVisit = $application->usesSteps();

        // The drafted forms are uploaded before they can be recorded as drafted.
        if ($isVisit && $application->status === 'drafting' && $request->input('action') === 'drafted' && $application->draftedForms()->isEmpty()) {
            return back()->with('error', 'Upload the drafted forms first, so the file keeps a copy.');
        }

        $updates = match ($application->status) {
            // E-Visa Step 2 also takes the fee: the form that carries a service fee saves it, the other records the signature.
            'agreement' => $application->isFeeFirst() && $request->has('service_fee') ? $this->eVisaFee($request, $application) : ['agreement_signed_at' => now()],
            'acknowledged' => ['acknowledgement_signed_at' => now()],
            // Documents (Non-Immigrant): the Acknowledgment is signed at the same step.
            'requirements' => $isVisit && $request->input('action') === 'acknowledged' ? ['acknowledgement_signed_at' => now()] : null,
            'drafting' => match ($isVisit ? $request->input('action') : null) {
                'drafted' => ['drafted_by' => Auth::id(), 'drafted_at' => now()],
                // Official Website Encoding: the details are on the embassy's website, with its reference if it gave one.
                'encoded' => ['encoded_at' => now(), 'encoded_by' => Auth::id()]
                    + $request->validate(['portal_reference' => ['nullable', 'string', 'max:100']]),
                default => null,
            },
            'lodged' => $isVisit
                ? $request->validate([
                    'lodged_at' => ['required', 'date'],
                    'embassy_reference' => ['nullable', 'string', 'max:255'],
                    // An embassy appearance needs its appointment and biometrics dates; Australia and New Zealand have none.
                    'appointment_at' => [$application->needsAppearance() ? 'required' : 'nullable', 'date'],
                    'biometrics_at' => [$application->needsAppearance() ? 'required' : 'nullable', 'date'],
                    'processing_days' => ['nullable', 'integer', 'min:1', 'max:365'],
                ])
                : $request->validate([
                    'lodged_at' => ['nullable', 'date'],
                    'embassy_reference' => ['nullable', 'string', 'max:255'],
                    'result' => ['nullable', 'in:'.implode(',', VisaApplication::RESULTS)],
                    'result_at' => ['nullable', 'required_with:result', 'date'],
                    'result_reference' => ['nullable', 'string', 'max:255'],
                    'result_validity' => ['nullable', 'string', 'max:255'],
                ]),
            // Saved while the embassy is still processing too (no result yet); releasing needs the result.
            'result' => $request->validate([
                'result' => ['nullable', 'in:'.implode(',', VisaApplication::RESULTS)],
                'result_at' => ['nullable', 'required_with:result', 'date'],
                'result_reference' => ['nullable', 'string', 'max:255'],
                'result_validity' => ['nullable', 'string', 'max:255'],
            ]),
            'insurance' => $this->insuranceRecord($request),
            'etravel' => $request->validate([
                'etravel_reference' => ['required', 'string', 'max:255'],
            ]),
            'appointment' => $request->validate([
                'appointment_at' => [$application->passport_type === 'foreign' && $application->service_type !== 'citizenship' ? 'nullable' : 'required', 'date'],
            ]),
            default => null,
        };

        if ($updates === null) {
            return back()->with('error', 'There is nothing to record at this stage.');
        }

        $application->update($updates);

        ActivityLogger::log('Visa Assistance', 'RECORD_STAGE', "Recorded {$application->status} on {$application->reference}");

        $response = back()->with('success', $application->status_label.' recorded.');

        // Submission (Non-Immigrant): "Notify client" emails the appointment and biometrics dates just saved.
        if ($isVisit && $application->status === 'lodged' && $request->boolean('notify_client')) {
            $response->with($this->notifyAppearance($application, $request->user()->name));
        }

        return $response;
    }

    /**
     * Email the client their embassy appointment and biometrics dates; the flash that says how it went.
     *
     * @return array<string, string>
     */
    private function notifyAppearance(VisaApplication $application, string $agent): array
    {
        if (! ClientNotifier::canReceive($application->client_email)) {
            return ['error' => "{$application->reference} has no client email address, so the client was not notified."];
        }

        $lines = array_filter([
            'Your '.($application->destination_country ? $application->destination_country.' ' : '').'visa application has been submitted.',
            $application->appointment_at ? 'Appointment schedule: '.$application->appointment_at->format('F j, Y') : null,
            $application->biometrics_at ? 'Biometrics date: '.$application->biometrics_at->format('F j, Y') : null,
            $application->processing_days ? "Expected processing: {$application->processing_days} days." : null,
            'Please bring your passport and the documents we advised you to bring. Contact our office if you have any questions.',
        ]);
        $subject = "Your appointment and biometrics dates - {$application->reference}";

        $sent = ClientNotifier::send($application->client_email, $application->client_name, new ClientCaseNotification($subject, implode('

', $lines), [
            'reference' => $application->reference,
            'service' => VisaApplication::SERVICE_LABELS[$application->service_type] ?? 'Visa Assistance',
            'status' => $application->status_label,
        ], $agent, 'Visa Assistance'));

        if (! $sent) {
            return ['error' => "The appointment email could not be sent to {$application->client_email}."];
        }

        ActivityLogger::log('Visa Assistance', 'CLIENT_NOTICE', "Emailed {$application->client_name} ({$application->client_email}) the appointment and biometrics dates for {$application->reference}");

        return ['success' => $application->status_label.' recorded. The client was emailed the appointment and biometrics dates.'];
    }

    /**
     * The visa type a Non-Immigrant file follows: a checklist variant of its destination
     * (which also sets the purpose), or a plain purpose where the destination has none.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: ?string, 1: string}
     *
     * @throws ValidationException
     */
    private function visaType(array $validated): array
    {
        $key = $validated['checklist_key'] ?? null;

        if (filled($key)) {
            $variant = VisaChecklist::find($validated['destination_country'] ?? null, $key);

            if ($variant === null) {
                throw ValidationException::withMessages(['checklist_key' => 'That visa type is not offered for this destination.']);
            }

            return [$key, $variant['purpose']];
        }

        if (blank($validated['purpose'] ?? null)) {
            throw ValidationException::withMessages(['checklist_key' => 'Choose the visa type.']);
        }

        return [null, $validated['purpose']];
    }

    /**
     * The country checklist printed for a file: one sheet per applicant, with what has
     * been received ticked under ORIG and COPY.
     */
    public function printChecklist(VisaApplication $application): View|RedirectResponse
    {
        if ($held = $application->printBlockerFor(Auth::user())) {
            return back()->with('error', $held);
        }

        $variant = $application->usesSteps() ? $application->checklist() : null;

        if ($variant === null) {
            return back()->with('error', 'This file has no country checklist to print.');
        }

        $application->load(['applicants', 'documents']);

        ActivityLogger::log('Visa Assistance', 'PRINT', "Printed the {$variant['label']} checklist for {$application->reference}");

        return view('visa-assistance.applications.checklist-print', [
            'application' => $application,
            'country' => $application->destination_country,
            'variant' => $variant,
            'sheets' => $application->applicants->map(fn (VisaApplicant $applicant) => ['name' => $applicant->full_name, 'owner' => 'applicant:'.$applicant->id])->all()
                ?: [['name' => $application->client_name, 'owner' => null]],
        ]);
    }

    /**
     * A blank country checklist to hand a client at the inquiry, before any file exists.
     */
    public function blankChecklist(string $key): View
    {
        $found = VisaChecklist::findByKey($key);

        abort_if($found === null, 404);

        return view('visa-assistance.applications.checklist-print', [
            'application' => null,
            'country' => $found[0],
            'variant' => $found[1],
            'sheets' => [['name' => null, 'owner' => null]],
        ]);
    }

    /**
     * Tick or untick the original or the copy of a checklist row, for an applicant or the sponsor.
     */
    public function toggleChecklist(Request $request, VisaApplication $application): RedirectResponse|JsonResponse
    {
        if ($locked = $this->releasedRefusal($application, true)) {
            return $locked;
        }

        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:40'],
            'item' => ['required', 'string', 'max:60'],
            'column' => ['required', 'in:orig,copy'],
        ]);

        if ($this->checklistOwner($application, $validated['owner'], $validated['item']) === null) {
            $message = 'That row is not on this file’s checklist.';

            return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
        }

        $progress = $application->checklist_progress ?? [];
        $on = ! ($progress[$validated['owner']][$validated['item']][$validated['column']] ?? false);
        $progress[$validated['owner']][$validated['item']][$validated['column']] = $on;

        $application->update(['checklist_progress' => $progress]);

        return $request->expectsJson() ? response()->json(['on' => $on]) : back();
    }

    /**
     * Who a checklist row belongs to, when the row is really on this file's checklist:
     * [kind, applicant id or null for the sponsor]. "_other" is an extra document an
     * applicant or the sponsor brought that is not on the sheet.
     *
     * @return array{0: string, 1: ?int}|null
     */
    private function checklistOwner(VisaApplication $application, string $owner, string $item): ?array
    {
        $variant = $application->usesSteps() ? $application->checklist() : null;
        $kind = str_starts_with($owner, 'applicant:') ? 'applicant' : ($owner === 'sponsor' ? 'sponsor' : null);
        $applicantId = $kind === 'applicant' ? (int) substr($owner, strlen('applicant:')) : null;

        $valid = $variant !== null
            && $kind !== null
            && ($kind === 'sponsor' || $application->applicants()->whereKey($applicantId)->exists())
            && ($item === '_other' || in_array($item, VisaChecklist::idsFor($variant, $kind), true));

        return $valid ? [$kind, $applicantId] : null;
    }

    /**
     * Attach a scan to a checklist row at Receiving Documents. The scan counts the
     * row as received; it is kept on the private documents disk.
     */
    public function uploadChecklistDocument(Request $request, VisaApplication $application): JsonResponse
    {
        if ($locked = $this->releasedRefusal($application, true)) {
            return $locked;
        }

        $validated = $request->validate([
            'owner' => ['required', 'string', 'max:40'],
            'item' => ['required', 'string', 'max:60'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $owner = $this->checklistOwner($application, $validated['owner'], $validated['item']);

        if ($owner === null) {
            return response()->json(['message' => 'That row is not on this file’s checklist.'], 422);
        }

        $row = collect($application->checklist()['sections'])
            ->flatMap(fn ($section) => collect($section['rows'])->flatMap(fn ($row) => [$row, ...($row['items'] ?? [])]))
            ->firstWhere('id', $validated['item']);

        $file = $request->file('file');
        $document = $application->documents()->create([
            'visa_applicant_id' => $owner[1],
            'document_type' => $row['doc'] ?? ($validated['item'] === '_other' ? 'other' : 'supporting_documents'),
            'checklist_item' => $validated['item'],
            'file_path' => $file->store("visa/{$application->reference}", DocumentStorage::diskName()),
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
        ]);

        ActivityLogger::log('Visa Assistance', 'UPLOAD_DOCUMENT', "Attached '{$document->original_name}' to {$validated['item']} on {$application->reference}");

        return response()->json(['file' => self::documentPayload($document)]);
    }

    /**
     * Keep a copy of the drafted application forms on the file.
     */
    public function storeDraftedForms(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $request->validate([
            'forms' => ['required', 'array', 'min:1', 'max:10'],
            'forms.*' => ['file', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp', 'max:10240'],
        ], ['forms.required' => 'Choose the drafted forms to upload.', 'forms.*.mimes' => 'Forms must be PDF, Word or image files.', 'forms.*.max' => 'Each form must be 10 MB or smaller.']);

        foreach ($request->file('forms') as $file) {
            $application->documents()->create([
                'visa_applicant_id' => null,
                'document_type' => 'application_form',
                'checklist_item' => VisaApplication::DRAFTED_FORM,
                'file_path' => $file->store("visa/{$application->reference}/forms", DocumentStorage::diskName()),
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getClientMimeType(),
                'status' => 'uploaded',
            ]);
        }

        ActivityLogger::log('Visa Assistance', 'UPLOAD_FORMS', 'Uploaded '.count($request->file('forms'))." drafted form(s) on {$application->reference}");

        return back()->with('success', count($request->file('forms')).' drafted '.Str::plural('form', count($request->file('forms'))).' uploaded.');
    }

    /**
     * Remove a filed document and its stored file.
     */
    public function destroyDocument(Request $request, VisaApplicationDocument $document): JsonResponse|RedirectResponse
    {
        if ($document->application && ($locked = $this->releasedRefusal($document->application, true))) {
            return $locked;
        }

        // Only from a file this staff member can see (their own, or any for admins).
        abort_unless($document->application, 404);

        DocumentStorage::disk()->delete($document->file_path);
        $document->delete();

        ActivityLogger::log('Visa Assistance', 'DELETE_DOCUMENT', "Removed '{$document->original_name}' from {$document->application->reference}");

        return $request->expectsJson() ? response()->json(['deleted' => true]) : back()->with('success', 'Document removed.');
    }

    /**
     * A filed document as the Receiving Documents screen shows it.
     *
     * @return array{id: int, name: string, size: string, url: string, delete_url: string}
     */
    public static function documentPayload(VisaApplicationDocument $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->original_name ?: basename($document->file_path),
            'size' => $document->file_size ? number_format($document->file_size / 1024, 0).' KB' : '',
            'url' => route('visa.documents.download', ['document' => $document, 'inline' => 1]),
            'delete_url' => route('visa.documents.destroy', $document),
        ];
    }

    /**
     * Ask the client for another document the embassy or portal requested.
     */
    public function storeRequest(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $validated = $request->validate(['label' => ['required', 'string', 'max:255']]);

        $application->update(['additional_requests' => [
            ...($application->additional_requests ?? []),
            ['label' => $validated['label'], 'requested_at' => now()->toIso8601String(), 'received_at' => null],
        ]]);

        ActivityLogger::log('Visa Assistance', 'DOCUMENT_REQUEST', "Requested '{$validated['label']}' on {$application->reference}");

        return back()->with('success', 'Additional document requested.');
    }

    /**
     * The client handed in a requested document.
     */
    public function receiveRequest(VisaApplication $application, int $index): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $requests = $application->additional_requests ?? [];

        if (! isset($requests[$index])) {
            return back()->with('error', 'That request was not found.');
        }

        $requests[$index]['received_at'] = now()->toIso8601String();
        $application->update(['additional_requests' => $requests]);

        return back()->with('success', 'Marked as received.');
    }

    /**
     * Insurance and e-Travel, if the client wants them. They are add-ons on a
     * Non-Immigrant file and never hold it at a stage.
     */
    public function recordAddons(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        if (! $application->usesSteps()) {
            return back()->with('error', 'Add-ons apply to Non-Immigrant files.');
        }

        if ($request->input('section') === 'etravel') {
            $updates = $request->validate(['etravel_reference' => ['required', 'string', 'max:255']]);
        } else {
            $updates = $this->insuranceRecord($request);
        }

        $application->update($updates);

        ActivityLogger::log('Visa Assistance', 'ADDON', "Recorded add-on ({$request->input('section', 'insurance')}) on {$application->reference}");

        return back()->with('success', 'Add-on recorded.');
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
    public function recordPayment(Request $request, VisaApplication $application, VisaPaymentRecorder $recorder): RedirectResponse
    {
        // The cashier takes the payments (on the Cashier desk), as for ticket bookings; an admin may record one here too.
        if (! $request->user()->isAdmin()) {
            return back()->with('error', 'Payments are recorded by the cashier. Send the client to the cashier with the reference '.$application->reference.'.');
        }

        if ($reason = VisaPaymentRecorder::blockedReason($application)) {
            return back()->with('error', $reason);
        }

        $validated = $request->validate(
            ['method' => ['nullable', 'in:'.implode(',', array_keys(VisaPayment::METHODS))]] + array_diff_key(VisaPaymentRecorder::rules(), ['method' => true]),
            VisaPaymentRecorder::messages(),
        );

        try {
            $recorder->record($application, $validated + ['method' => $validated['method'] ?? 'cash'], $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        $application->refresh();

        return back()->with('success', $application->outstandingBalance() > 0
            ? 'Payment recorded. Balance: '.$application->currency.' '.number_format($application->outstandingBalance(), 2).'.'
            : 'Payment recorded. The file is fully paid.');
    }

    /**
     * The Acknowledgment of Documents receipt: what the counter received from
     * the client, for the client to sign.
     */
    public function acknowledgement(VisaApplication $application): View|RedirectResponse
    {
        if ($held = $application->printBlockerFor(Auth::user())) {
            return back()->with('error', $held);
        }

        $application->load(['applicants', 'documents.applicant']);

        return view('visa-assistance.applications.acknowledgement', ['application' => $application]);
    }

    /**
     * The Service Engagement Agreement, filled from the file and the client's profile,
     * for the client to read and sign at the Agreement step.
     */
    public function agreement(VisaApplication $application): View|RedirectResponse
    {
        if ($held = $application->printBlockerFor(Auth::user())) {
            return back()->with('error', $held);
        }

        $application->load('applicants');

        $client = User::where('role', 'client')
            ->where(fn ($query) => $query
                ->when($application->client_email, fn ($match) => $match->where('email', $application->client_email))
                ->orWhere('name', $application->client_name))
            ->first();
        $primary = $application->applicants->firstWhere('is_primary', true) ?? $application->applicants->first();
        $variant = $application->usesSteps() ? $application->checklist() : null;

        ActivityLogger::log('Visa Assistance', 'PRINT', "Printed the service agreement for {$application->reference}");

        return view('visa-assistance.applications.agreement', [
            'application' => $application,
            'name' => mb_strtoupper($primary?->full_name ?: $application->client_name),
            'citizenship' => mb_strtoupper((string) ($primary?->nationality ?: $client?->nationality)),
            'address' => mb_strtoupper((string) ($client?->address_line
                ? collect([$client->address_line, $client->city, $client->province])->filter()->implode(', ')
                : $client?->address)),
            'visaType' => mb_strtoupper(match ($application->service_type) {
                'e_visa' => 'e-Visa',
                'passporting' => 'Passport',
                'citizenship' => 'US Citizenship',
                default => ($variant['label'] ?? ucfirst((string) $application->purpose)).' visa',
            }),
            'country' => mb_strtoupper((string) ($application->destination_country ?: $application->embassy_country)),
            'applicants' => $this->agreementApplicants($application),
            'amountInWords' => AmountInWords::pesos((float) $application->total_amount),
            'preparedBy' => Auth::user()?->name,
        ]);
    }

    /**
     * How many applicants the agreement prints. The wizard prices the travellers but saves only the client as an
     * applicant, the rest being added later, so a fee that is a whole multiple of the country's price counts them too.
     */
    private function agreementApplicants(VisaApplication $application): int
    {
        $named = max(1, $application->applicants->count());
        $price = (float) VisaPricingTier::countryRates()->where('country', $application->destination_country)->value('amount');
        $priced = $price > 0 ? ((float) $application->service_fee + (float) $application->visa_fee) / $price : 0;

        return $priced > $named && floor($priced) === $priced ? (int) $priced : $named;
    }

    /**
     * An agent asks the admin to unlock a released file, with a reason; the approvers are emailed. The file stays
     * locked until the admin approves the request (it is reopened) or declines it (with a note).
     */
    public function requestEdit(Request $request, VisaApplication $application): RedirectResponse
    {
        if (! $application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is not released, so it can be edited as it is.");
        }

        if ($request->user()->can('visa_assistance.approve')) {
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
        ActivityLogger::log('Visa Assistance', 'EDIT_REQUESTED', "Asked to unlock released {$application->reference}: {$reason}");

        try {
            Notification::send(Approvals::recipients('visa_assistance', $request->user()), new VisaEditRequestNotification('requested', $application->reference, (string) $application->client_name, $request->user()->name, $reason));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', "Edit requested for {$application->reference}. The admin will approve or decline it under Approvals.");
    }

    /** An approver opens a released file again, at the stage before Released (a pending edit request is cleared). */
    public function reopen(Request $request, VisaApplication $application): RedirectResponse
    {
        if (! $request->user()->can('visa_assistance.approve')) {
            return back()->with('error', "Only an admin or a Visa approver can reopen {$application->reference}. Use Request edit to ask the admin.");
        }

        if (! $application->isReleasedLocked()) {
            return back()->with('error', "{$application->reference} is not released.");
        }

        $application->reopen();
        ActivityLogger::log('Visa Assistance', 'REOPEN', "Reopened released {$application->reference} (back to {$application->status_label})");

        return back()->with('success', "{$application->reference} is open again, at {$application->status_label}.");
    }

    /**
     * A blank Service Engagement Agreement, to fill in by hand at the inquiry: nothing from a file, no amounts or date.
     */
    public function blankAgreement(): View
    {
        ActivityLogger::log('Visa Assistance', 'PRINT', 'Printed a blank service agreement');

        return view('visa-assistance.applications.agreement', [
            'application' => new VisaApplication,
            'blank' => true,
            'name' => '', 'citizenship' => '', 'address' => '', 'visaType' => '', 'country' => '',
            'applicants' => '', 'amountInWords' => '', 'preparedBy' => '',
        ]);
    }

    /**
     * Cancel a counter file.
     */
    public function cancel(VisaApplication $application): RedirectResponse
    {
        if ($finished = $application->finishedState()) {
            return back()->with('error', "This file is {$finished}. Its status is locked.");
        }

        $application->update(['status' => 'cancelled']);

        ActivityLogger::log('Visa Assistance', 'CANCEL', "Cancelled transaction {$application->reference}");

        $this->notifyStatus($application, 'Cancelled');

        return back()->with('success', "File {$application->reference} cancelled.");
    }

    /**
     * Remove a counter file and everything filed under it.
     */
    public function destroy(VisaApplication $application): RedirectResponse
    {
        $reference = $application->reference;

        DocumentStorage::disk()->deleteDirectory("visa/{$reference}");
        // MyISAM keeps no foreign keys: the file's payments go with it.
        $application->payments()->delete();
        $application->delete();

        ActivityLogger::log('Visa Assistance', 'DELETE', "Deleted transaction {$reference}");

        // Back to the service's own list (Non-Immigrant, E-Visa, Passporting or Citizenship), not the old file directory.
        return redirect()->route('visa.services', $application->service_type)
            ->with('success', "Transaction {$reference} deleted.");
    }

    /**
     * Add an applicant to a counter file. Group e-Visa files carry several.
     */
    public function storeApplicant(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

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

        $applicant = $application->applicants()->create($validated + [
            'applicant_number' => $nextNumber,
            'is_primary' => $nextNumber === 1,
        ]);

        // An applicant who is a registered client keeps their record current (the newest form wins).
        ClientMemory::remember($applicant->registeredClient(), [
            'date_of_birth' => $applicant->date_of_birth?->toDateString(),
            'gender' => $applicant->gender,
            'nationality' => $applicant->nationality,
            'passport_number' => $applicant->passport_number,
            'passport_expiry' => $applicant->passport_expiry_date?->toDateString(),
            'passport_country' => $applicant->passport_country,
        ]);

        ActivityLogger::log('Visa Assistance', 'ADD_APPLICANT', "Added applicant #{$nextNumber} to {$application->reference}");

        return back()->with('success', "Applicant #{$nextNumber} added.");
    }

    /**
     * Add the file's client as an applicant in one click, from their profile.
     */
    public function storeClientApplicant(VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $client = ClientAccountService::findOrCreateClient([
            'name' => $application->client_name,
            'email' => $application->client_email,
            'phone' => $application->client_phone,
        ]);

        $applicant = $application->addClientAsApplicant($client);

        ActivityLogger::log('Visa Assistance', 'ADD_APPLICANT', "Added the client as applicant #{$applicant->applicant_number} to {$application->reference}");

        return back()->with('success', "{$applicant->full_name} added as applicant #{$applicant->applicant_number}.");
    }

    /**
     * Remove an applicant from a counter file.
     */
    public function destroyApplicant(VisaApplication $application, VisaApplicant $applicant): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

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
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        $validated = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', VisaApplicationDocument::DOCUMENT_TYPES)],
            'visa_applicant_id' => ['nullable', 'integer'],
            'remarks' => ['nullable', 'string', 'max:255'],
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
            // An "other" document says what it is.
            'remarks' => $validated['document_type'] === 'other' ? ($validated['remarks'] ?? null) : null,
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
    public function downloadDocument(Request $request, VisaApplicationDocument $document): StreamedResponse|RedirectResponse
    {
        // Only from a file this staff member can see (their own, or any for admins).
        abort_unless($document->application, 404);

        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document could not be found.');
        }

        // ?inline=1 opens a scan in the browser to look at it; otherwise it downloads.
        return $request->boolean('inline')
            ? DocumentStorage::disk()->response($document->file_path, $document->original_name)
            : DocumentStorage::disk()->download($document->file_path, $document->original_name);
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

        if ($serviceType === 'e_visa') {
            $rules['destination_country'] = [request()->routeIs('visa.applications.store') ? 'required' : 'nullable', 'string', 'max:255'];
        }

        if ($serviceType === 'visit_visa') {
            $rules['destination_country'] = ['required', 'string', 'max:255'];
            $rules['checklist_key'] = ['nullable', 'string', 'max:100'];
            $rules['purpose'] = ['required_without:checklist_key', 'nullable', 'in:'.implode(',', VisaApplication::PURPOSES)];
            $rules['processing_speed'] = ['nullable', 'in:'.implode(',', VisaApplication::PROCESSING_SPEEDS)];
            $rules['insurance_included'] = ['nullable', 'boolean'];
            $rules['etravel_reference'] = ['nullable', 'string', 'max:255'];
        }

        if ($serviceType === 'e_visa') {
            $rules['applicant_type'] = ['nullable', 'in:'.implode(',', VisaApplication::APPLICANT_TYPES)];
        }

        if ($serviceType === 'citizenship') {
            $onStore = request()->routeIs('visa.applications.store');
            $rules['claim_type'] = [$onStore ? 'required' : 'nullable', 'in:'.implode(',', array_keys(VisaApplication::CLAIM_TYPES))];
            $rules['us_citizen_parent'] = [$onStore ? 'required' : 'nullable', 'string', 'max:255'];
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

    /**
     * Email the client that their file has moved on.
     */
    /**
     * A released file is locked for everyone, as a released Immigration case is: nothing on it changes until an approver
     * reopens it, or the admin approves an agent's Request edit. Payments and client notices still go through.
     */
    /**
     * Email the client the recorded result (approved, denied or withdrawn), from the Results step.
     */
    public function notifyApproved(VisaApplication $application): RedirectResponse
    {
        if (! in_array($application->result, VisaApplication::RESULTS, true)) {
            return back()->with('error', 'Record and save the result first, then email the client.');
        }

        if (! ClientNotifier::canReceive($application->client_email)) {
            return back()->with('error', "{$application->reference} has no client email address, so the client was not notified.");
        }

        $what = ($application->destination_country ? $application->destination_country.' ' : '').$this->serviceName($application);
        $headline = match ($application->result) {
            'approved' => "Good news: your {$what} has been approved.",
            'denied' => "We are sorry to tell you that your {$what} was not approved.",
            default => "Your {$what} application has been withdrawn.",
        };
        $lines = array_filter([
            $headline,
            $application->result === 'approved' && $application->result_reference ? 'Visa number: '.$application->result_reference.'.' : null,
            $application->result === 'approved' && $application->result_validity ? 'Validity: '.$application->result_validity.'.' : null,
            $application->result === 'denied' ? 'Please visit our office so we can explain the decision and what you can do next.' : 'Please visit our office so we can go over the next steps with you.',
            'Contact our office if you have any questions.',
        ]);
        $subject = "Your {$application->result} file - {$application->reference}";

        $sent = ClientNotifier::send($application->client_email, $application->client_name, new ClientCaseNotification($subject, implode('

', $lines), [
            'reference' => $application->reference,
            'service' => VisaApplication::SERVICE_LABELS[$application->service_type] ?? 'Visa Assistance',
            'status' => $application->status_label,
        ], Auth::user()->name, 'Visa Assistance'));

        if (! $sent) {
            return back()->with('error', "The email could not be sent to {$application->client_email}.");
        }

        ActivityLogger::log('Visa Assistance', 'CLIENT_NOTICE', "Emailed {$application->client_name} ({$application->client_email}) that {$application->reference} was {$application->result}");

        return back()->with('success', "The client was emailed that {$application->reference} is {$application->result}.");
    }

    /**
     * E-Visa Step 2: the service and visa fee. Changing them while the admin holds the file is refused.
     *
     * @return array<string, mixed>
     */
    private function eVisaFee(Request $request, VisaApplication $application): array
    {
        if ($application->isAwaitingApproval() || $application->approval_status === VisaApplication::APPROVAL_APPROVED) {
            throw ValidationException::withMessages(['service_fee' => 'The fee is locked once the file is with the admin or approved.']);
        }

        $validated = $request->validate([
            'service_fee' => ['required', 'numeric', 'min:0'],
            'visa_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $serviceFee = (float) $validated['service_fee'];
        $visaFee = (float) ($validated['visa_fee'] ?? 0);

        return [
            'service_fee' => $serviceFee,
            'visa_fee' => $visaFee,
            'total_amount' => $serviceFee + $visaFee + (float) $application->rush_fee + (float) $application->insurance_fee + (float) $application->etravel_fee,
        ];
    }

    /**
     * E-Visa Step 2: send the file, with its fee, to the admin. Approved, it goes to the cashier for payment;
     * returned, the agent corrects it and sends it again.
     */
    public function sendToAdmin(Request $request, VisaApplication $application): RedirectResponse
    {
        if ($locked = $this->releasedRefusal($application)) {
            return $locked;
        }

        if (! $application->isFeeFirst() || $application->status !== 'agreement') {
            return back()->with('error', 'A file is sent to the admin at the Service Agreement & Fees step.');
        }

        if ((float) $application->total_amount <= 0) {
            return back()->with('error', 'Enter the fee first.');
        }

        if ($application->isAwaitingApproval()) {
            return back()->with('error', 'This file was already sent and is waiting for the admin.');
        }

        if ($application->approval_status === VisaApplication::APPROVAL_APPROVED) {
            return back()->with('error', 'The admin already approved this file.');
        }

        // Sending always counts as a change: the admin sees the fee as it stands now.
        DeskApproval::saved($application, $request->user(), 'Visa Assistance', (string) $application->client_name, 'admin.visa-approvals.index');
        ActivityLogger::log('Visa Assistance', 'SEND', "Sent e-Visa {$application->reference} to the admin for approval");

        return back()->with('success', $application->isAwaitingApproval()
            ? 'Sent to the admin. Once approved it goes to the cashier for payment.'
            : 'Approved. It is now with the cashier for payment.');
    }

    private function releasedRefusal(VisaApplication $application, bool $json = false): RedirectResponse|JsonResponse|null
    {
        if (! $application->isReleasedLocked()) {
            return null;
        }

        $message = "{$application->reference} is released and locked, so nothing was changed. "
            .(Auth::user()?->can('visa_assistance.approve') ? 'Reopen it first.' : 'Use Request edit to ask the admin to unlock it.');

        return $json && request()->expectsJson()
            ? response()->json(['message' => $message], 423)
            : back()->with('error', $message);
    }

    private function notifyStatus(VisaApplication $application, string $stageLabel, ?string $note = null): void
    {
        // The client hears nothing until the admin has approved the file.
        if (! $application->isCleared()) {
            return;
        }

        ClientNotifier::send($application->client_email, $application->client_name, new FileStatusChangedNotification(
            $this->serviceName($application), $application->reference, (string) $application->client_name, $stageLabel, $note,
        ));
    }

    /**
     * What the client calls this service in an email.
     */
    private function serviceName(VisaApplication $application): string
    {
        return match ($application->service_type) {
            'e_visa' => 'e-Visa application',
            'passporting' => 'passport application',
            'citizenship' => 'citizenship claim',
            default => 'visa application',
        };
    }
}
