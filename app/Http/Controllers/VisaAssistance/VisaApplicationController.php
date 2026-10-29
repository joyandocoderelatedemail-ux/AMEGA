<?php

namespace App\Http\Controllers\VisaAssistance;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisaApplicant;
use App\Models\VisaApplication;
use App\Models\VisaApplicationDocument;
use App\Models\VisaPricingTier;
use App\Notifications\FileStatusChangedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\VisaStageDoneNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientMemory;
use App\Services\ClientNotifier;
use App\Services\DeskApproval;
use App\Support\AmountInWords;
use App\Support\DocumentStorage;
use App\Support\VisaChecklist;
use App\Support\VisaPlaybook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
                'email' => $client->real_email,
                'phone' => $client->phone,
                'passport' => $client->passport_number,
                'url' => route('visa.applications.create', ['client' => $client->id]),
            ]);

        return response()->json(['files' => $files, 'clients' => $clients]);
    }

    /**
     * A service's open files, before anything is started: each opens as a read-only preview on one
     * page, and New goes on to start one. Staff other than admins see their own files.
     */
    public function services(string $service): View
    {
        $files = VisaApplication::where('service_type', $service)
            ->whereNotIn('status', ['released', 'cancelled'])
            ->latest()
            ->limit(200)
            ->get();

        return view('visa-assistance.services.list', [
            'service' => $service,
            'label' => VisaApplication::SERVICE_LABELS[$service],
            'files' => $files,
            // Non-Immigrant picks the country first; E-Visa and Passporting open their own form.
            'newUrl' => $service === 'visit_visa'
                ? route('visa.visit.new')
                : route('visa.applications.create', ['service_type' => $service]),
        ]);
    }

    /**
     * Add a new client: which visa, or which country, comes first, because the
     * form is different for each. The Philippines opens the BI extension of
     * stay; any other country opens the visit visa form for that destination.
     */
    public function start(): View
    {
        $countries = VisaPricingTier::countryRates()->get()->unique('country')->values();

        return view('visa-assistance.visit-visa.start', compact('countries'));
    }

    /**
     * Show the intake form. The service type decides which fields appear.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $serviceType = $request->input('service_type', 'visit_visa');

        // Opened for one service (from the New Counter File menu): the form is that
        // service's only, without the picker. Opened without one, staff choose.
        $serviceLocked = in_array($serviceType, VisaApplication::SERVICE_TYPES, true) && $request->filled('service_type');

        if (! in_array($serviceType, VisaApplication::SERVICE_TYPES, true)) {
            $serviceType = 'visit_visa';
        }

        // Visit Visa, named from the menu, opens the client folders. Chosen for a
        // country (?country=, empty for one not on the list), it opens that form.
        if ($serviceLocked && $serviceType === 'visit_visa' && ! $request->has('country')) {
            return redirect()->route('visa.visit.new');
        }

        // Opened for a registered client picked from the search.
        $client = $request->filled('client')
            ? User::where('role', 'client')->find($request->integer('client'))
            : null;

        return view('visa-assistance.applications.create', [
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

        $destinationCountry = $validated['destination_country'] ?? null;
        $processingSpeed = $validated['processing_speed'] ?? 'regular';
        [$checklistKey, $purpose] = $serviceType === 'visit_visa' ? $this->visaType($validated) : [null, null];

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
            'purpose' => $purpose,
            'checklist_key' => $checklistKey,
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
            // A new file is opened unpaid: the down payment is taken at the Payment step.
            'payment_type' => $validated['payment_type'] ?? ((float) ($validated['amount_paid'] ?? 0) > 0 ? 'full' : 'deposit'),
            'remarks' => $validated['remarks'] ?? null,
        ]);

        ActivityLogger::log('Visa Assistance', 'CREATE', "Opened counter file {$application->reference} ({$application->service_type}) for {$application->client_name}");

        $client = ClientAccountService::findOrCreateClient([
            'name' => $application->client_name,
            'email' => $application->client_email,
            'phone' => $application->client_phone,
        ]);

        // The client is the first applicant; group members are added after.
        $application->addClientAsApplicant($client);

        DeskApproval::saved($application, $request->user(), 'Visa Assistance', (string) $application->client_name, 'admin.visa-approvals.index');

        return redirect()->route('visa.applications.show', $application)
            ->with('success', "Counter file {$application->reference} opened successfully.".($application->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
    }

    /**
     * Display the counter file: pipeline, applicants, documents and money.
     */
    public function show(Request $request, VisaApplication $application): View
    {
        $application->load(['applicants.documents', 'documents', 'creator']);

        // Opened from a service list or the approvals page: the whole file, read-only, on one page.
        if ($request->boolean('preview')) {
            return view('visa-assistance.applications.preview', ['application' => $application]);
        }

        $stages = $application->stages();
        $currentIndex = array_search($application->status, $stages, true);
        $currentIndex = $application->isReleasedVisit() ? count($stages) - 1 : ($currentIndex === false ? 0 : $currentIndex);
        $isVisitResult = $application->service_type === 'visit_visa' && $application->status === 'result';

        return view('visa-assistance.applications.show', [
            'application' => $application,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $isVisitResult ? 'released' : ($stages[$currentIndex + 1] ?? null),
            // A Non-Immigrant file is final only once released; its last step still waits on the release.
            'isFinal' => $application->service_type === 'visit_visa' ? $application->isReleasedVisit() : $currentIndex >= count($stages) - 1,
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
        $validated = $request->validate($this->rulesFor($application->service_type), self::MESSAGES);

        $destinationCountry = $validated['destination_country'] ?? null;
        $processingSpeed = $validated['processing_speed'] ?? 'regular';
        [$checklistKey, $purpose] = $application->service_type === 'visit_visa' ? $this->visaType($validated) : [null, null];

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
            'purpose' => $purpose ?? $application->purpose,
            'checklist_key' => $application->service_type === 'visit_visa' ? $checklistKey : $application->checklist_key,
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

        DeskApproval::saved($application, $request->user(), 'Visa Assistance', (string) $application->client_name, 'admin.visa-approvals.index', $application->wasChanged());

        ActivityLogger::log('Visa Assistance', 'UPDATE', "Updated counter file {$application->reference}");

        return redirect()->route('visa.applications.show', $application)
            ->with('success', 'Counter file updated.'.($application->isAwaitingApproval() ? ' It goes to the admin for approval.' : ''));
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
        $releasing = $application->service_type === 'visit_visa' && $application->status === 'result';

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

        ActivityLogger::log('Visa Assistance', 'ADVANCE', "Counter file {$application->reference} advanced from {$from} to {$next}");

        $this->notifyStatus($application, $application->stageLabel($next), $next === 'released'
            ? 'Your documents are ready. Please visit our office to collect them.'
            : null);

        return back()->with('success', 'File moved to '.$application->stageLabel($next).'.');
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
        $isVisit = $application->service_type === 'visit_visa';

        // The drafted forms are uploaded before they can be recorded as drafted.
        if ($isVisit && $application->status === 'drafting' && $request->input('action') === 'drafted' && $application->draftedForms()->isEmpty()) {
            return back()->with('error', 'Upload the drafted forms first, so the file keeps a copy.');
        }

        $updates = match ($application->status) {
            'agreement' => ['agreement_signed_at' => now()],
            'acknowledged' => ['acknowledgement_signed_at' => now()],
            // Documents (Non-Immigrant): the Acknowledgment is signed at the same step.
            'requirements' => $isVisit && $request->input('action') === 'acknowledged' ? ['acknowledgement_signed_at' => now()] : null,
            'drafting' => match ($isVisit ? $request->input('action') : null) {
                'drafted' => ['drafted_by' => Auth::id(), 'drafted_at' => now()],
                default => null,
            },
            'lodged' => $isVisit
                ? $request->validate([
                    'lodged_at' => ['required', 'date'],
                    'embassy_reference' => ['nullable', 'string', 'max:255'],
                    'appointment_at' => ['nullable', 'date'],
                    'biometrics_at' => ['nullable', 'date'],
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
            'result' => $request->validate([
                'result' => ['required', 'in:'.implode(',', VisaApplication::RESULTS)],
                'result_at' => ['required', 'date'],
                'result_reference' => ['nullable', 'string', 'max:255'],
                'result_validity' => ['nullable', 'string', 'max:255'],
            ]),
            'insurance' => $this->insuranceRecord($request),
            'etravel' => $request->validate([
                'etravel_reference' => ['required', 'string', 'max:255'],
            ]),
            'appointment' => $request->validate([
                'appointment_at' => ['required', 'date'],
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
        if ($held = $application->approvalBlocker('printed')) {
            return back()->with('error', $held);
        }

        $variant = $application->service_type === 'visit_visa' ? $application->checklist() : null;

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
        $variant = $application->service_type === 'visit_visa' ? $application->checklist() : null;
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
     * Tick or untick one of the country playbook's tasks on the current stage.
     */
    public function toggleTask(Request $request, VisaApplication $application, string $task): RedirectResponse|JsonResponse
    {
        if ($application->service_type !== 'visit_visa' || ! array_key_exists($task, VisaPlaybook::tasks($application->destination_country, (string) $application->status))) {
            return $request->expectsJson()
                ? response()->json(['message' => 'That step is not part of this stage.'], 422)
                : back()->with('error', 'That step is not part of this stage.');
        }

        $progress = $application->playbook_progress ?? [];

        if (isset($progress[$task])) {
            unset($progress[$task]);
        } else {
            $progress[$task] = now()->toIso8601String();
        }

        $application->update(['playbook_progress' => $progress]);

        // Ticked from the step's checklist without a reload: say whether it is now done.
        return $request->expectsJson()
            ? response()->json(['done' => isset($progress[$task]), 'open' => count($application->openTasks((string) $application->status))])
            : back();
    }

    /**
     * Ask the client for another document the embassy or portal requested.
     */
    public function storeRequest(Request $request, VisaApplication $application): RedirectResponse
    {
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
        if ($application->service_type !== 'visit_visa') {
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
    public function recordPayment(Request $request, VisaApplication $application): RedirectResponse
    {
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

        $paid = (float) $application->amount_paid + (float) $validated['amount'];

        $application->update([
            'amount_paid' => $paid,
            'payment_type' => $paid >= (float) $application->total_amount ? 'full' : 'deposit',
        ]);

        ActivityLogger::log('Visa Assistance', 'PAYMENT', "Recorded {$application->currency} ".number_format((float) $validated['amount'], 2)." on {$application->reference}");

        ClientNotifier::send($application->client_email, $application->client_name, new PaymentReceivedNotification(
            $this->serviceName($application), $application->reference, (string) $application->client_name, (string) $application->currency,
            (float) $validated['amount'], (float) $application->amount_paid, $application->outstandingBalance(),
        ));

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
        if ($held = $application->approvalBlocker('printed')) {
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
        if ($held = $application->approvalBlocker('printed')) {
            return back()->with('error', $held);
        }

        $application->load('applicants');

        $client = User::where('role', 'client')
            ->where(fn ($query) => $query
                ->when($application->client_email, fn ($match) => $match->where('email', $application->client_email))
                ->orWhere('name', $application->client_name))
            ->first();
        $primary = $application->applicants->firstWhere('is_primary', true) ?? $application->applicants->first();
        $variant = $application->service_type === 'visit_visa' ? $application->checklist() : null;

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
                default => ($variant['label'] ?? ucfirst((string) $application->purpose)).' visa',
            }),
            'country' => mb_strtoupper((string) ($application->destination_country ?: $application->embassy_country)),
            'applicants' => max(1, $application->applicants->count()),
            'amountInWords' => AmountInWords::pesos((float) $application->total_amount),
            'preparedBy' => Auth::user()?->name,
        ]);
    }

    /**
     * Cancel a counter file.
     */
    public function cancel(VisaApplication $application): RedirectResponse
    {
        $application->update(['status' => 'cancelled']);

        ActivityLogger::log('Visa Assistance', 'CANCEL', "Cancelled counter file {$application->reference}");

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

        if ($serviceType === 'visit_visa') {
            $rules['destination_country'] = ['required', 'string', 'max:255'];
            $rules['checklist_key'] = ['nullable', 'string', 'max:100'];
            $rules['purpose'] = ['required_without:checklist_key', 'nullable', 'in:'.implode(',', VisaApplication::PURPOSES)];
            $rules['processing_speed'] = ['nullable', 'in:'.implode(',', VisaApplication::PROCESSING_SPEEDS)];
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

    /**
     * Email the client that their file has moved on.
     */
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
            default => 'visa application',
        };
    }
}
