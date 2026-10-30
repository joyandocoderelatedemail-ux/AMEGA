<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationPetition;
use App\Models\ImmigrationRestamping;
use App\Models\TouristVisaApplication;
use App\Models\User;
use App\Notifications\ImmigrationApprovalRequestedNotification;
use App\Notifications\PassportPickupNotification;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientNotifier;
use App\Services\ClientProfileService;
use App\Services\VisaExtensionService;
use App\Support\Approvals;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use App\Support\ImmigrationCases;
use App\Support\ImmigrationServices;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class AdminClientSheetController extends Controller
{
    /** How many matches of each kind the type-ahead shows. */
    private const LOOKUP_LIMIT = 6;

    private const FOLDER_LIST_PER_PAGE = 25;

    /** The service case written by this save, so the folder reopens on it. */
    private ?int $savedCaseId = null;

    private ?Model $savedCase = null;

    /** Cases and extension rows saved by an agent in this request, which now wait for an admin. */
    private array $needsApproval = [];

    /** The 13(A) form fields kept as text. */
    private const PETITION_TEXT_DETAILS = [
        'middle_name', 'other_name_1', 'other_name_2', 'landline', 'occupation', 'country_of_birth', 'place_of_birth',
        'present_status', 'visa_type', 'passport_place', 'representative_name', 'accreditation_no',
        'abroad_street', 'abroad_city', 'abroad_country',
        'child_1_name', 'child_2_name',
        'petitioner_last', 'petitioner_first', 'petitioner_middle', 'petitioner_street', 'petitioner_city', 'petitioner_province',
        'petitioner_landline', 'petitioner_mobile', 'petitioner_email',
        'reference_name', 'reference_street', 'reference_city', 'reference_province', 'reference_landline', 'reference_mobile', 'reference_email',
        'acr_number', 'crn', 'visa_status',
        // Dual citizenship petition (BI Form 2014-01-005)
        'name_ph', 'spouse_name', 'birth_place', 'father_name', 'father_citizenship', 'father_acquired',
        'mother_name', 'mother_citizenship', 'mother_acquired', 'lost_country', 'passport_country', 'oath_place', 'icr_number',
        'dep_1_name', 'dep_1_sex', 'dep_2_name', 'dep_2_sex', 'dep_3_name', 'dep_3_sex', 'birth_proof',
        // ACR I-Card application (BI Form 2014-08-006)
        'name_prefix', 'name_suffix', 'official_receipt_no', 'acr_place', 'visa_number', 'consulate', 'admission_status',
        'length_of_stay', 'previous_acr_number', 'previous_acr_place', 'previous_subject_of', 'race', 'blood_type',
        'eye_color', 'hair_color', 'build', 'complexion', 'marks', 'spouse_nationality', 'rp_src_number', 'rp_src_type', 'tin',
        // Downgrading of visa (letter request) and ECC (ARD04.QF.003)
        'reason_detail', 'company_name', 'company_address', 'company_signatory', 'guardian_name', 'leaving_reason', 'destination', 'airline',
        'birth_province', 'birth_city', 'destination_country', 'destination_city',
    ];

    /** The 13(A) form's yes/no boxes, and what the checklist depends on. */
    private const PETITION_FLAGS = ['by_representative', 'nbi_needed', 'annex_a', 'leaving_soon', 'registered_alien', 'name_variance', 'parents_married', 'with_dependents', 'dependent_alien'];

    /**
     * The client folders now live on the Cases page, so the old list sends staff there with
     * whatever they searched or filtered.
     */
    public function index(Request $request)
    {
        $service = (string) $request->input('service');
        $kind = collect(ImmigrationCases::kinds())->search(fn (array $kind) => $kind['service'] === $service);

        return redirect()->route('admin.immigration.cases', array_filter([
            'service' => $kind !== false ? $kind : ($service === 'visa_extension' ? 'visa_extension' : 'folders'),
            'q' => trim((string) $request->input('passport')),
            'flagged' => $request->boolean('flagged') ? 1 : null,
        ]));
    }

    /**
     * Type-ahead matches for the counter search box: client sheets, then
     * registered clients who have no sheet yet.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['sheets' => [], 'clients' => []]);
        }

        $sheets = ImmigrationClient::search($term)
            ->orderBy('last_name')
            ->orderBy('given_name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (ImmigrationClient $sheet) => [
                'name' => $sheet->full_name,
                'passport' => $sheet->passport_number,
                'nationality' => $sheet->nationality,
                'flagged' => $sheet->isFlagged(),
                'url' => route('admin.client-sheets.edit', array_filter(['client_sheet' => $sheet->id, 'service' => $this->serviceParam($request)])),
            ]);

        $clients = User::clientSearch($term)
            ->whereDoesntHave('immigrationClients')
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (User $client) => [
                'name' => $client->full_name,
                'passport' => $client->passport_number,
                'contact' => collect([$client->email, $client->phone])->filter()->implode(' · '),
                'url' => route('admin.client-sheets.create', array_filter(['client' => $client->id, 'service' => $request->input('service')])),
            ]);

        return response()->json(['sheets' => $sheets, 'clients' => $clients]);
    }

    /**
     * A service's active applications, before anything is started: each one opens as a read-only
     * preview, and "New" goes on to name the client. Staff other than admins see their own files.
     */
    public function applications(Request $request)
    {
        $service = ImmigrationServices::find($request->input('service'));
        $user = $request->user();
        $kind = collect(ImmigrationCases::kinds())->search(fn (array $info) => $info['service'] === $service['key']);

        // Visa Extension has no case of its own: each client with extensions is one row, standing as their latest one does.
        $range = DateRange::fromRequest($request);
        $extensions = $service['key'] === 'visa_extension'
            ? ImmigrationClient::whereHas('extensions')->with('extensions')->orderBy('last_name')->orderBy('given_name')->limit(200)->get()
                // The latest extension recorded within the date range picked on the page.
                ->filter(fn (ImmigrationClient $client) => $range->contains($client->extensions->sortByDesc('sequence')->first()?->created_at))
                ->map(fn (ImmigrationClient $client) => [
                    'client' => $client,
                    'extension' => $client->extensions->sortByDesc('sequence')->first(),
                    'url' => route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'preview' => 1]),
                ])
            : collect();

        return view('admin.client-sheets.applications', [
            'service' => $service,
            'cases' => $kind === false ? collect() : ImmigrationCases::search($kind, 'open', '', 200, $user->isAdmin() ? null : $user->id)
                ->filter(fn (array $case) => $range->contains($case['opened']))->values(),
            'extensions' => $extensions->values(),
        ]);
    }

    /**
     * Name the folder: find the client (an existing sheet opens their folder, a
     * registered client starts one filled from their profile) or add a new one.
     */
    public function start(Request $request)
    {
        return view('admin.client-sheets.start', [
            'service' => ImmigrationServices::find($request->input('service')),
            'folders' => ImmigrationClient::orderBy('last_name')
                ->orderBy('given_name')
                ->paginate(self::FOLDER_LIST_PER_PAGE)
                ->withQueryString(),
        ]);
    }

    /**
     * A client the counter has never seen: they go on the client list first, then
     * their folder opens with step 1 filled from what was just typed.
     */
    public function startNewClient(Request $request)
    {
        $service = ImmigrationServices::find($request->input('service'));
        $rules = Arr::only(ClientProfileService::registrationRules(), [
            'first_name', 'middle_name', 'last_name', 'suffix', 'no_email', 'no_phone', 'email', 'phone', 'nationality',
        ]);

        $validated = $request->validate($rules);
        $client = ClientProfileService::register($validated + ['account_category' => 'Individual'], $request);

        ActivityLogger::log('Immigration', 'REGISTER_CLIENT', "Added client '{$client->name}' at the immigration counter for {$service['label']}");

        return redirect()->route('admin.client-sheets.create', ['client' => $client->id, 'service' => $service['key']])
            ->with('success', "{$client->name} is added to the client list. Their details are filled in below.");
    }

    public function create(Request $request)
    {
        $registered = $request->filled('client')
            ? User::where('role', 'client')->find($request->integer('client'))
            : null;

        $client = $registered
            ? $this->fromRegisteredClient($registered)
            : new ImmigrationClient([
                'passport_number' => trim((string) $request->input('passport')),
                ...$this->namePrefill((string) $request->input('name')),
            ]);

        // The page speaks for the service the agent picked from the Services menu.
        $service = ImmigrationServices::find($request->input('service'));

        return view('admin.client-sheets.create', compact('client', 'service'));
    }

    /**
     * A save starts with nothing carried over: the controller can serve more than one request
     * (in tests, or a long-running server), and what one save queued for the admin is its own.
     */
    private function startSave(): void
    {
        $this->savedCaseId = null;
        $this->savedCase = null;
        $this->needsApproval = [];
    }

    public function store(Request $request)
    {
        $this->startSave();
        $validated = $this->validateClient($request);
        $overstay = $this->validateOverstay($request);
        $restamping = $this->validateRestamping($request);
        $petition = $this->validatePetition($request);

        // A new client at the counter is also a client account in the admin client list.
        $client = new ImmigrationClient($validated);
        $client->user_id = ClientAccountService::clientForSheet($client)->id;
        $client->save();

        $this->syncDocuments($client, $request);
        $cases = $this->syncOverstay($client, $request, $overstay, $client->visa_expiry_date)
            .$this->syncRestamping($client, $request, $restamping)
            .$this->syncPetition($client, $request, $petition);
        $extended = $this->usesLedger($request) ? $this->extendVisa($client, $this->syncExtensions($client, $request), true) : '';

        ActivityLogger::log('Client Sheets', 'CREATE', "Created client information sheet for '{$client->full_name}'");

        return $this->afterSave($request, $client, 'Client sheet created.'.$cases.$extended);
    }

    /**
     * Email the client their passport pick-up notice (the same text staff copy for a text blast).
     */
    public function pickupNotice(Request $request, ImmigrationClient $clientSheet): RedirectResponse
    {
        if (! ClientNotifier::canReceive($clientSheet->email)) {
            return back()->with('error', "{$clientSheet->full_name} has no email address on file. Copy the notice and send it by text or Messenger.");
        }

        if (! ClientNotifier::send($clientSheet->email, $clientSheet->full_name, new PassportPickupNotification($clientSheet, $request->user()->name))) {
            return back()->with('error', "The pick-up notice could not be emailed to {$clientSheet->email}. Please try again.");
        }

        ActivityLogger::log('Immigration', 'PICKUP_NOTICE', "Emailed the passport pick-up notice to {$clientSheet->full_name} ({$clientSheet->email})");

        return back()->with('success', "Pick-up notice emailed to {$clientSheet->email}.");
    }

    public function edit(Request $request, ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions', 'overstays', 'restampings', 'petitions']);
        $service = ImmigrationServices::find($request->input('service'));
        $caseId = $request->integer('case') ?: null;

        $overstay = $service['key'] === 'visa_overstaying' ? $this->overstayCase($clientSheet, $caseId) : null;
        $restamping = $service['key'] === 're_stamping_amendment' ? $this->restampingCase($clientSheet, $caseId) : null;
        $petition = ($type = ImmigrationPetition::typeFor($service['key'])) ? $this->petitionCase($clientSheet, $type, $caseId) : null;

        // A returning client's new case starts from their previous one, so only what changed is edited.
        $current = $overstay ?? $restamping ?? $petition;
        $copiedFrom = $current ? $this->startFromPrevious($current, $clientSheet, $request) : null;

        return view('admin.client-sheets.edit', [
            'client' => $clientSheet,
            'service' => $service,
            'overstay' => $overstay,
            'restamping' => $restamping,
            'petition' => $petition,
            'copiedFrom' => $copiedFrom,
            'cases' => ImmigrationCases::forClient($clientSheet),
            // A file opened from the approvals page or an application list is read-only, on one page.
            'preview' => $request->boolean('preview'),
        ]);
    }

    /**
     * Fill a new, unsaved case with the answers on the client's latest earlier case of the same service,
     * unless the agent asked to start blank. Nothing is saved until they save the folder; an open case is
     * never touched. Returns what it was filled from, for the notice, or null.
     *
     * @return array{reference: string, label: string, released_on: ?string, blank_url: string}|null
     */
    private function startFromPrevious(Model $case, ImmigrationClient $client, Request $request): ?array
    {
        if ($case->exists || $request->boolean('blank')) {
            return null;
        }

        $kind = ImmigrationCases::kindOf($case);
        $previous = ImmigrationCases::previousFor($client, $kind);

        if ($previous === null) {
            return null;
        }

        $case->fill($previous->carryForward());

        return [
            'reference' => $previous->reference(),
            'label' => ImmigrationCases::kinds()[$kind]['label'],
            'released_on' => $previous->released_on?->format('M d, Y'),
            'blank_url' => $request->fullUrlWithQuery(['blank' => 1]),
        ];
    }

    public function update(Request $request, ImmigrationClient $clientSheet)
    {
        $this->startSave();
        $validated = $this->validateClient($request);
        $overstay = $this->validateOverstay($request);
        $restamping = $this->validateRestamping($request);
        $petition = $this->validatePetition($request);
        $expiryBefore = $clientSheet->visa_expiry_date;

        // A date typed into step 2 by hand wins over the one the new extension would give.
        $expiryUntouched = $clientSheet->visa_expiry_date?->toDateString() === ($validated['visa_expiry_date'] ?? null);

        $clientSheet->fill($validated);
        $clientSheet->user_id = ClientAccountService::clientForSheet($clientSheet)->id;
        $clientSheet->save();

        $this->syncDocuments($clientSheet, $request);
        $cases = $this->syncOverstay($clientSheet, $request, $overstay, $expiryBefore)
            .$this->syncRestamping($clientSheet, $request, $restamping)
            .$this->syncPetition($clientSheet, $request, $petition);
        $extended = $this->usesLedger($request) ? $this->extendVisa($clientSheet, $this->syncExtensions($clientSheet, $request), $expiryUntouched) : '';

        ActivityLogger::log('Client Sheets', 'UPDATE', "Updated client information sheet for '{$clientSheet->full_name}'");

        return $this->afterSave($request, $clientSheet, 'Client sheet updated.'.$cases.$extended);
    }

    /**
     * The last step of the counter process: "Save & print" goes straight to the print
     * page with the print dialog open: the client sheet for Visa Extension, the service's
     * own BI form for the other services. "Save only" stays on the record.
     */
    private function afterSave(Request $request, ImmigrationClient $client, string $message): RedirectResponse
    {
        $this->notifyApprovers($client, $request);
        $message .= $this->approvalNote();

        // What an agent just saved waits for an admin, so it is not printed straight away.
        $waiting = ! $request->user()->hasPermission('immigration.approve') && ($this->savedCase ? ! $this->savedCase->isCleared() : $this->needsApproval !== []);

        if ($request->input('then') === 'print' && ! $waiting) {
            $case = ['case' => $this->savedCaseId, 'autoprint' => 1];
            $key = ImmigrationServices::find($request->input('service'))['key'];
            $petitionType = ImmigrationPetition::typeFor($key);

            return match (true) {
                $this->savedCaseId === null || $key === 'visa_extension' => redirect()->route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]),
                $key === 'visa_overstaying' => redirect()->route('admin.client-sheets.reconsideration', ['clientSheet' => $client] + $case),
                $key === 're_stamping_amendment' => redirect()->route('admin.client-sheets.restamping-form', ['clientSheet' => $client] + $case),
                $petitionType !== null => redirect()->route('admin.client-sheets.petition-form', ['clientSheet' => $client, 'type' => $petitionType] + $case),
                default => redirect()->route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]),
            };
        }

        return redirect()->route('admin.client-sheets.edit', array_filter(['client_sheet' => $client->id, 'service' => $this->serviceParam($request), 'case' => $this->savedCaseId]))
            ->with('success', $message.($waiting ? ' You can print it once the admin approves it.' : ' You can print it now.'));
    }

    /**
     * The payment option beside a case's fee: full, a downpayment, terms or none. Anything but
     * none needs a fee, and a downpayment or terms payment cannot be more than the fee.
     *
     * @return array<string, mixed>
     */
    private function paymentRules(Request $request, string $key): array
    {
        $fee = (float) $request->input("{$key}.fee");
        $type = $request->input("{$key}.payment_type");

        return [
            "{$key}.payment_type" => ['nullable', 'in:'.implode(',', array_keys(ImmigrationPetition::PAYMENT_TYPES)), function (string $attribute, mixed $value, Closure $fail) use ($fee): void {
                if (in_array($value, ['full', 'downpayment', 'terms'], true) && $fee <= 0) {
                    $fail('Enter the fee first: a payment option needs an amount to pay.');
                }
            }],
            "{$key}.amount_paid" => ['nullable', 'numeric', 'min:0', 'max:99999999', "required_if:{$key}.payment_type,downpayment", function (string $attribute, mixed $value, Closure $fail) use ($fee, $type): void {
                if (in_array($type, ['downpayment', 'terms'], true) && $fee > 0 && (float) $value > $fee) {
                    $fail('The amount paid cannot be more than the fee.');
                }
            }],
            "{$key}.terms_count" => ['nullable', 'integer', 'min:2', 'max:36', "required_if:{$key}.payment_type,terms"],
        ];
    }

    /**
     * A case an admin has not approved yet (or has returned) is not printed by the agent who saved
     * it: they go back to the case with the reason. Admins print any file.
     */
    private function heldForApproval(Request $request, Model $case, ImmigrationClient $client): ?RedirectResponse
    {
        if ($request->user()->hasPermission('immigration.approve') || ! $case->exists || $case->isCleared()) {
            return null;
        }

        $service = ImmigrationCases::kinds()[ImmigrationCases::kindOf($case)]['service'];

        return redirect()->route('admin.client-sheets.edit', ['client_sheet' => $client->id, 'service' => $service, 'case' => $case->id])
            ->with('error', $case->isReturned()
                ? 'The admin returned this file'.($case->review_note ? ': '.$case->review_note : '.').' Correct it and save to send it again before printing.'
                : 'This file is waiting for an admin to approve it. You can print it once it is approved.');
    }

    /** The case the form was opened on, if any. */
    private function caseId(Request $request): ?int
    {
        return $request->integer('case_id') ?: null;
    }

    /** The service to carry in a URL: none for Visa Extension, which is what a sheet opens in. */
    private function serviceParam(Request $request): ?string
    {
        $key = ImmigrationServices::find($request->input('service'))['key'];

        return $key === ImmigrationServices::DEFAULT ? null : $key;
    }

    /**
     * Only Visa Extension and Visa Overstaying have the ledger step. The other services'
     * forms send no ledger rows, and writing the ledger from them would clear the rows on file.
     */
    private function usesLedger(Request $request): bool
    {
        return in_array(ImmigrationServices::find($request->input('service'))['key'], ['visa_extension', 'visa_overstaying'], true);
    }

    /** The client's 13(A) case of a type in progress, or a new one. */
    private function petitionCase(ImmigrationClient $client, string $type, ?int $id = null): ImmigrationPetition
    {
        return ($id ? $client->petitions()->where('type', $type)->find($id) : null)
            ?? $client->openPetition($type)
            ?? new ImmigrationPetition(['immigration_client_id' => $client->id, 'type' => $type]);
    }

    /**
     * The 13(A) steps, checked before anything is saved.
     *
     * @return array<string, mixed>|null
     */
    private function validatePetition(Request $request): ?array
    {
        $type = ImmigrationPetition::typeFor(ImmigrationServices::find($request->input('service'))['key']);

        if ($type === null) {
            return null;
        }

        $items = implode(',', array_keys(ImmigrationPetition::allItems()));
        $text = 'nullable|string|max:255';
        $rules = [
            'petition.details' => 'nullable|array',
            'petition.details.sex' => 'nullable|in:M,F',
            'petition.details.application_kind' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::APPLICATION_KINDS)),
            'petition.details.transactions' => 'nullable|array',
            'petition.details.transactions.*' => 'in:'.implode(',', array_keys(ImmigrationPetition::TRANSACTIONS)),
            'petition.details.new_address' => 'nullable|string|max:500',
            'petition.details.remarks' => 'nullable|string|max:500',
            'petition.details.birth_proof' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::BIRTH_PROOFS)),
            'petition.details.father_acquired' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::ACQUIRED_BY)),
            'petition.details.mother_acquired' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::ACQUIRED_BY)),
            'petition.details.visa_category' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::DOWNGRADE_CATEGORIES)),
            'petition.details.renewal_category' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::RENEWAL_CATEGORIES)),
            'petition.details.downgrade_reason' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::DOWNGRADE_REASONS)),
            'petition.details.letter_by' => 'nullable|in:applicant,company',
            'petition.details.ecc_kind' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::ECC_KINDS)),
            'petition.checklist' => 'nullable|array',
            'petition.checklist.*' => 'in:'.$items,
            'petition.files' => 'nullable|array',
            'petition.files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'petition.remove_files' => 'nullable|array',
            'petition.remove_files.*' => 'in:'.$items,
            'petition.filed_on' => 'nullable|date',
            'petition.fee' => 'nullable|numeric|min:0|max:99999999',
            ...$this->paymentRules($request, 'petition'),
            'petition.released_on' => 'nullable|date|after_or_equal:petition.filed_on',
        ];

        foreach (self::PETITION_TEXT_DETAILS as $key) {
            $rules["petition.details.{$key}"] = $text;
        }
        foreach (ImmigrationPetition::DATE_DETAILS as $key) {
            $rules["petition.details.{$key}"] = 'nullable|date';
        }
        foreach (self::PETITION_FLAGS as $key) {
            $rules["petition.details.{$key}"] = 'nullable|boolean';
        }

        $validated = $request->validate($rules, [], [
            'petition.filed_on' => 'date filed',
            'petition.fee' => 'fee',
            'petition.payment_type' => 'payment option',
            'petition.amount_paid' => 'amount paid',
            'petition.terms_count' => 'number of terms',
            'petition.released_on' => 'date released',
            'petition.files.*' => 'requirement file',
        ]);

        return ['type' => $type] + ($validated['petition'] ?? []);
    }

    /**
     * An ECC's place of birth and next destination are picked as country, province and city,
     * and printed on the BI form as one line each ("City, Province, Country"). A case saved
     * before the pickers keeps the line it had until a place is picked.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function withEccPlaces(array $details, ImmigrationPetition $case): array
    {
        $line = fn (array $parts): string => implode(', ', array_filter($parts));

        foreach ([
            'place_of_birth' => ['birth_city', 'birth_province', 'country_of_birth'],
            'destination' => ['destination_city', 'destination_country'],
        ] as $key => $parts) {
            $composed = $line(array_map(fn (string $part) => $details[$part] ?? null, $parts));

            if ($composed !== '') {
                $details[$key] = $composed;
            } elseif (filled($case->detail($key))) {
                $details[$key] = $case->detail($key);
            }
        }

        return $details;
    }

    /**
     * Write the 13(A) case: the form's details, the checklist ticks and files, and the
     * filing dates. A release date closes it.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function syncPetition(ImmigrationClient $client, Request $request, ?array $data): string
    {
        if ($data === null) {
            return '';
        }

        $case = ($this->caseId($request) ? $client->petitions()->where('type', $data['type'])->find($this->caseId($request)) : null)
            ?? $client->openPetition($data['type']) ?? new ImmigrationPetition([
                'immigration_client_id' => $client->id,
                'created_by' => $request->user()?->id,
                'type' => $data['type'],
            ]);

        $details = collect($data['details'] ?? [])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->reject(fn ($value) => $value === null || $value === '' || $value === [])
            ->all();

        foreach (self::PETITION_FLAGS as $flag) {
            $details[$flag] = (bool) ($details[$flag] ?? false);
        }
        if (empty($details['by_representative'])) {
            unset($details['representative_name'], $details['accreditation_no']);
        }
        if ($data['type'] === 'ecc') {
            $details = $this->withEccPlaces($details, $case);
        }
        // Kept in one order, so saving the same details again is not mistaken for a change.
        ksort($details);

        $case->fill([
            'details' => $details,
            'checklist' => collect($data['checklist'] ?? [])->unique()->sort()->values()->all(),
            'filed_on' => $data['filed_on'] ?? null,
            'fee' => $data['fee'] ?? null,
            'released_on' => array_key_exists('released_on', $data) ? $data['released_on'] : $case->released_on,
        ]);

        $this->settleCase($case, $request, $data);

        $this->syncRequirementFiles($case, $request, 'petition', $data, ImmigrationPetition::CHECKLISTS[$data['type']], "immigration/{$client->id}/13a");

        $this->routeForApproval($case, $request);
        $isNew = ! $case->exists;
        $case->save();

        $this->savedCaseId = $case->id;
        $this->savedCase = $case;

        $title = ImmigrationPetition::TITLES[$data['type']];
        ActivityLogger::log('Immigration', $isNew ? 'PETITION_OPEN' : 'PETITION_UPDATE', "{$title} case for '{$client->full_name}' ".($isNew ? 'opened' : 'updated'));

        $kind = in_array($data['type'], ['downgrade', 'ecc'], true) ? $title : '13(A)';

        return $case->isOpen() ? " {$kind} case saved." : " {$kind} case closed as released.";
    }

    /** The client's re-stamping case in progress, or a new one. */
    private function restampingCase(ImmigrationClient $client, ?int $id = null): ImmigrationRestamping
    {
        return ($id ? $client->restampings()->find($id) : null)
            ?? $client->openRestamping()
            ?? new ImmigrationRestamping(['immigration_client_id' => $client->id]);
    }

    /**
     * The Re Stamping / Amendment steps, checked before anything is saved.
     *
     * @return array<string, mixed>|null
     */
    private function validateRestamping(Request $request): ?array
    {
        if (ImmigrationServices::find($request->input('service'))['key'] !== 're_stamping_amendment') {
            return null;
        }

        $items = implode(',', array_keys(ImmigrationRestamping::allItems()));

        return $request->validate([
            'restamping.applications' => 'required|array|min:1',
            'restamping.applications.*' => 'in:'.implode(',', array_keys(ImmigrationRestamping::APPLICATIONS)),
            'restamping.middle_name' => 'nullable|string|max:255',
            'restamping.old_passport_number' => 'nullable|string|max:255',
            'restamping.landline' => 'nullable|string|max:255',
            'restamping.arrival_date' => 'nullable|date',
            'restamping.flight_number' => 'nullable|string|max:20',
            'restamping.bibs_number' => 'nullable|string|max:20',
            'restamping.admission_status' => 'nullable|string|max:40',
            'restamping.reason' => 'nullable|string|max:2000',
            'restamping.passport_lost' => 'nullable|boolean',
            'restamping.balikbayan' => 'nullable|boolean',
            'restamping.with_filipino_spouse' => 'nullable|boolean',
            'restamping.checklist' => 'nullable|array',
            'restamping.checklist.*' => 'in:'.$items,
            'restamping.files' => 'nullable|array',
            'restamping.files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'restamping.remove_files' => 'nullable|array',
            'restamping.remove_files.*' => 'in:'.$items,
            'restamping.filed_on' => 'nullable|date',
            'restamping.fee' => 'nullable|numeric|min:0|max:99999999',
            ...$this->paymentRules($request, 'restamping'),
            'restamping.released_on' => 'nullable|date|after_or_equal:restamping.filed_on',
        ], [
            'restamping.applications.required' => 'Tick what the client is applying for in step 3: transfer, amendment, or a failure to check, encode or stamp.',
        ], [
            'restamping.arrival_date' => 'latest date of arrival',
            'restamping.filed_on' => 'date filed',
            'restamping.fee' => 'fee',
            'restamping.payment_type' => 'payment option',
            'restamping.amount_paid' => 'amount paid',
            'restamping.terms_count' => 'number of terms',
            'restamping.released_on' => 'date released',
            'restamping.files.*' => 'requirement file',
        ])['restamping'];
    }

    /**
     * Write the re-stamping case: the form's extra fields, the checklist ticks and files,
     * and the filing dates. A release date closes it.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function syncRestamping(ImmigrationClient $client, Request $request, ?array $data): string
    {
        if ($data === null) {
            return '';
        }

        $case = ($this->caseId($request) ? $client->restampings()->find($this->caseId($request)) : null)
            ?? $client->openRestamping() ?? new ImmigrationRestamping([
                'immigration_client_id' => $client->id,
                'created_by' => $request->user()?->id,
            ]);

        $balikbayan = (bool) ($data['balikbayan'] ?? false);

        $case->fill([
            'applications' => array_values(array_intersect(array_keys(ImmigrationRestamping::APPLICATIONS), $data['applications'])),
            'middle_name' => $data['middle_name'] ?? null,
            'old_passport_number' => $data['old_passport_number'] ?? null,
            'landline' => $data['landline'] ?? null,
            'arrival_date' => $data['arrival_date'] ?? null,
            'flight_number' => $data['flight_number'] ?? null,
            'bibs_number' => $data['bibs_number'] ?? null,
            'admission_status' => $data['admission_status'] ?? null,
            'reason' => $data['reason'] ?? null,
            'passport_lost' => (bool) ($data['passport_lost'] ?? false),
            'balikbayan' => $balikbayan,
            'with_filipino_spouse' => $balikbayan && ($data['with_filipino_spouse'] ?? false),
            'checklist' => collect($data['checklist'] ?? [])->unique()->sort()->values()->all(),
            'filed_on' => $data['filed_on'] ?? null,
            'fee' => $data['fee'] ?? null,
            'released_on' => array_key_exists('released_on', $data) ? $data['released_on'] : $case->released_on,
        ]);

        $this->settleCase($case, $request, $data);

        $this->syncRequirementFiles($case, $request, 'restamping', $data, ImmigrationRestamping::allItems(), "immigration/{$client->id}/restamping");

        $this->routeForApproval($case, $request);
        $isNew = ! $case->exists;
        $case->save();

        $this->savedCaseId = $case->id;
        $this->savedCase = $case;

        ActivityLogger::log('Immigration', $isNew ? 'RESTAMPING_OPEN' : 'RESTAMPING_UPDATE', "Re-stamping case for '{$client->full_name}' ".($isNew ? 'opened' : 'updated'));

        return $case->isOpen() ? ' Re-stamping case saved.' : ' Re-stamping case closed as released.';
    }

    /**
     * Apply a case's requirement uploads and removals to its `documents` map. A new
     * upload replaces the file on record; files are kept on the private disk.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $items
     */
    private function syncRequirementFiles(Model $case, Request $request, string $input, array $data, array $items, string $folder): void
    {
        $documents = $case->documents ?? [];

        foreach ($data['remove_files'] ?? [] as $item) {
            if (isset($documents[$item]['path'])) {
                DocumentStorage::disk()->delete($documents[$item]['path']);
            }
            unset($documents[$item]);
        }

        foreach ($request->file("{$input}.files", []) as $item => $upload) {
            if (! array_key_exists($item, $items)) {
                continue;
            }

            if (isset($documents[$item]['path'])) {
                DocumentStorage::disk()->delete($documents[$item]['path']);
            }

            $documents[$item] = [
                'path' => $upload->store($folder, DocumentStorage::diskName()),
                'name' => $upload->getClientOriginalName(),
                'size' => $upload->getSize(),
                'mime' => $upload->getClientMimeType(),
            ];
        }

        $case->documents = $documents ?: null;
    }

    /** The client's overstay case in progress, or a new one starting from the visa's expiry. */
    private function overstayCase(ImmigrationClient $client, ?int $id = null): ImmigrationOverstay
    {
        return ($id ? $client->overstays()->find($id) : null)
            ?? $client->openOverstay()
            ?? new ImmigrationOverstay(['immigration_client_id' => $client->id, 'expired_on' => $client->visa_expiry_date]);
    }

    /**
     * The Visa Overstaying steps, checked before anything is saved.
     *
     * @return array<string, mixed>|null
     */
    private function validateOverstay(Request $request): ?array
    {
        if (ImmigrationServices::find($request->input('service'))['key'] !== 'visa_overstaying') {
            return null;
        }

        $items = implode(',', array_keys(ImmigrationOverstay::CHECKLIST));

        return $request->validate([
            'overstay.expired_on' => 'nullable|date',
            'overstay.reason_failed' => 'nullable|string|max:2000',
            'overstay.reason_stay' => 'nullable|string|max:2000',
            'overstay.departure_date' => 'nullable|date',
            'overstay.destination' => 'nullable|string|max:255',
            'overstay.by_representative' => 'nullable|boolean',
            'overstay.representative_name' => 'nullable|string|max:255',
            'overstay.representative_accreditation_no' => 'nullable|string|max:255',
            'overstay.representative_relationship' => 'nullable|string|max:255',
            'overstay.checklist' => 'nullable|array',
            'overstay.checklist.*' => 'in:'.$items,
            'overstay.files' => 'nullable|array',
            'overstay.files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'overstay.remove_files' => 'nullable|array',
            'overstay.remove_files.*' => 'in:'.$items,
            'overstay.filed_on' => 'nullable|date',
            'overstay.fee' => 'nullable|numeric|min:0|max:99999999',
            ...$this->paymentRules($request, 'overstay'),
            'overstay.released_on' => 'nullable|date|after_or_equal:overstay.filed_on',
        ], [], [
            'overstay.expired_on' => 'authorized stay expiry',
            'overstay.departure_date' => 'departure date',
            'overstay.filed_on' => 'date filed',
            'overstay.fee' => 'fee',
            'overstay.payment_type' => 'payment option',
            'overstay.amount_paid' => 'amount paid',
            'overstay.terms_count' => 'number of terms',
            'overstay.released_on' => 'date released',
            'overstay.files.*' => 'requirement file',
        ])['overstay'] ?? [];
    }

    /**
     * Write the overstay case: its letter, checklist ticks and files, and filing dates.
     * A release date closes it, so the next lapse starts a new case.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function syncOverstay(ImmigrationClient $client, Request $request, ?array $data, ?Carbon $expiryBefore): string
    {
        if ($data === null) {
            return '';
        }

        $case = ($this->caseId($request) ? $client->overstays()->find($this->caseId($request)) : null)
            ?? $client->openOverstay() ?? new ImmigrationOverstay([
                'immigration_client_id' => $client->id,
                'created_by' => $request->user()?->id,
            ]);

        $representative = (bool) ($data['by_representative'] ?? false);

        $case->fill([
            'expired_on' => $data['expired_on'] ?? $expiryBefore,
            'reason_failed' => $data['reason_failed'] ?? null,
            'reason_stay' => $data['reason_stay'] ?? null,
            'departure_date' => $data['departure_date'] ?? null,
            'destination' => $data['destination'] ?? null,
            'by_representative' => $representative,
            'representative_name' => $representative ? ($data['representative_name'] ?? null) : null,
            'representative_accreditation_no' => $representative ? ($data['representative_accreditation_no'] ?? null) : null,
            'representative_relationship' => $representative ? ($data['representative_relationship'] ?? null) : null,
            'checklist' => collect($data['checklist'] ?? [])->unique()->sort()->values()->all(),
            'filed_on' => $data['filed_on'] ?? null,
            'fee' => $data['fee'] ?? null,
            'released_on' => array_key_exists('released_on', $data) ? $data['released_on'] : $case->released_on,
        ]);

        $this->settleCase($case, $request, $data);

        $this->syncRequirementFiles($case, $request, 'overstay', $data, ImmigrationOverstay::CHECKLIST, "immigration/{$client->id}/overstay");

        $this->routeForApproval($case, $request);
        $isNew = ! $case->exists;
        $case->save();

        $this->savedCaseId = $case->id;
        $this->savedCase = $case;

        ActivityLogger::log('Immigration', $isNew ? 'OVERSTAY_OPEN' : 'OVERSTAY_UPDATE', "Overstay case for '{$client->full_name}' ".($isNew ? 'opened' : 'updated'));

        return $case->isOpen() ? ' Overstay case saved.' : ' Overstay case closed as released.';
    }

    /**
     * The populated sheet, laid out to match the printed AMEGA form.
     */
    public function print(Request $request, ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions']);

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed client information sheet for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.print', [
            'client' => $clientSheet,
            'backUrl' => ImmigrationServices::folderUrl($clientSheet, $request->input('service'), $request->integer('case') ?: null),
        ]);
    }

    /**
     * An empty sheet to hand a walk-in client to fill in by hand.
     */
    public function blank()
    {
        return view('admin.client-sheets.print', ['client' => new ImmigrationClient]);
    }

    public function destroy(Request $request, ImmigrationClient $clientSheet)
    {
        $name = $clientSheet->full_name;
        $clientSheet->delete();

        ActivityLogger::log('Client Sheets', 'DELETE', "Deleted client information sheet for '{$name}'");

        // Back to the application list of the service the folder was opened from.
        return redirect()->route('admin.client-sheets.applications', array_filter(['service' => $this->serviceParam($request)]))
            ->with('success', 'Client sheet deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClient(Request $request): array
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'given_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'mobile_number' => 'nullable|string|max:255',
            'height' => 'nullable|string|max:255',
            'weight' => 'nullable|string|max:255',
            'civil_status' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'passport_number' => 'nullable|string|max:255',
            'visa_expiry_date' => 'nullable|date',
            'is_expired' => 'boolean',
            'has_penalty' => 'boolean',
            'needs_attention' => 'boolean',
            'status_note' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'documents' => 'nullable|array',
            'documents.*.reference_number' => 'nullable|string|max:255',
            'documents.*.date_paid' => 'nullable|date',
            'documents.*.ssrn_number' => 'nullable|string|max:255',
            'documents.*.validity' => 'nullable|string|max:255',
            // Steps 3-5 take a scan of the document; kept privately, opened only through the counter.
            'documents.*.file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'documents.*.remove_file' => 'nullable|boolean',
            'extensions' => 'nullable|array',
            'extensions.*.soa_or_number' => 'nullable|string|max:255',
            'extensions.*.extension_date' => 'nullable|date',
            'extensions.*.details' => 'nullable|string',
            'extensions.*.amount_paid' => 'nullable|numeric|min:0',
            'extensions.*.annual_report' => 'nullable|string|max:255',
            'extensions.*.refund' => 'nullable|numeric|min:0',
            'extensions.*.duration' => 'nullable|string|max:20',
            // Before saving the agent acknowledges the dates and details; the payment is for the extensions added now.
            'acknowledgement_required' => 'nullable|boolean',
            'acknowledged' => 'accepted_if:acknowledgement_required,1',
            'extension_payment' => 'nullable|array',
            'extension_payment.payment_type' => 'nullable|in:'.implode(',', array_keys(ImmigrationPetition::PAYMENT_TYPES)),
            'extension_payment.amount_received' => 'nullable|numeric|min:0|max:99999999|required_if:extension_payment.payment_type,downpayment',
            'extension_payment.terms_count' => 'nullable|integer|min:2|max:36|required_if:extension_payment.payment_type,terms',
        ], [
            'acknowledged.accepted_if' => 'Tick the acknowledgement that the dates and details are correct before saving.',
        ], [
            'extension_payment.payment_type' => 'payment option',
            'extension_payment.amount_received' => 'amount received',
            'extension_payment.terms_count' => 'number of terms',
        ]);

        unset($validated['documents'], $validated['extensions'], $validated['acknowledgement_required'], $validated['acknowledged'], $validated['extension_payment']);

        // Unticked checkboxes are absent from the request rather than false
        $validated['is_expired'] = $request->has('is_expired');
        $validated['has_penalty'] = $request->has('has_penalty');
        $validated['needs_attention'] = $request->has('needs_attention');

        return $validated;
    }

    /**
     * Write the ACR I-Card, CRTV and Annual Report steps: the scan and the date paid.
     * Fields the form no longer shows (ref. number, SSRN, validity) keep what is on
     * file unless they are sent. A step left completely empty drops its record.
     */
    private function syncDocuments(ImmigrationClient $client, Request $request): void
    {
        foreach (array_keys(ImmigrationClientDocument::TYPES) as $type) {
            $row = $request->input("documents.{$type}", []);
            $existing = $client->documents()->where('document_type', $type)->first();
            $attributes = [];

            foreach (['reference_number', 'date_paid', 'ssrn_number', 'validity'] as $field) {
                $attributes[$field] = array_key_exists($field, $row) ? $row[$field] : $existing?->{$field};
            }

            $attributes += [
                'file_path' => $existing?->file_path,
                'original_name' => $existing?->original_name,
                'file_size' => $existing?->file_size,
                'mime_type' => $existing?->mime_type,
            ];

            $upload = $request->file("documents.{$type}.file");

            if ($upload || ! empty($row['remove_file'])) {
                if ($existing?->file_path) {
                    DocumentStorage::disk()->delete($existing->file_path);
                }

                $attributes = array_merge($attributes, $upload ? [
                    'file_path' => $upload->store("immigration/{$client->id}", DocumentStorage::diskName()),
                    'original_name' => $upload->getClientOriginalName(),
                    'file_size' => $upload->getSize(),
                    'mime_type' => $upload->getClientMimeType(),
                ] : ['file_path' => null, 'original_name' => null, 'file_size' => null, 'mime_type' => null]);
            }

            if (collect($attributes)->every(fn ($value): bool => blank($value))) {
                $client->documents()->where('document_type', $type)->delete();

                continue;
            }

            $client->documents()->updateOrCreate(['document_type' => $type], $attributes);
        }
    }

    /**
     * The BI's CGAF or documentary checklist for an extension of stay, filled from the
     * client sheet: the same 8.5 x 13 layouts, with nothing stored twice.
     */
    public function printBiForm(Request $request, ImmigrationClient $clientSheet, string $form)
    {
        $clientSheet->load('extensions');
        $latest = $clientSheet->extensions->sortByDesc('sequence')->first();
        // Printed from an overstay folder, the case marks the CGAF overstayed and ticks the
        // checklist. From the Visa Extension folder it is a plain extension of stay.
        $fromOverstay = $request->filled('case') || $request->input('service') === 'visa_overstaying';
        $overstay = match (true) {
            $request->filled('case') => $clientSheet->overstays()->find($request->integer('case')),
            $fromOverstay => $clientSheet->openOverstay(),
            default => null,
        };
        // A file waiting for an admin, or returned by one, is not printed by the agent who saved it.
        if ($overstay) {
            if ($held = $this->heldForApproval($request, $overstay, $clientSheet)) {
                return $held;
            }
        } elseif (! $request->user()->hasPermission('immigration.approve') && $clientSheet->extensions->contains(fn (ImmigrationClientExtension $row) => ! $row->isCleared())) {
            return redirect()->route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id])
                ->with('error', 'The new extension is waiting for an admin to approve it. You can print the forms once it is approved.');
        }
        $folder = array_filter(['service' => $overstay || $fromOverstay ? 'visa_overstaying' : null, 'case' => $overstay?->id]);
        $profile = $clientSheet->user_id ? User::find($clientSheet->user_id) : null;
        $number = fn (?string $value) => preg_match('/\d+(\.\d+)?/', (string) $value, $match) ? $match[0] : null;
        $band = $clientSheet->validity_band;

        $application = new TouristVisaApplication([
            'application_method' => 'personal',
            'application_mode' => $band && in_array($band['key'], ['express', 'expired'], true) ? 'express' : 'regular',
            'months_requested' => match (true) {
                $latest === null => null,
                str_contains((string) $latest->duration, '2 month') => 2,
                default => 1,
            },
            'last_name' => $clientSheet->last_name,
            'first_name' => $clientSheet->given_name,
            'date_of_birth' => $clientSheet->date_of_birth,
            'sex' => ['male' => 'M', 'female' => 'F'][strtolower((string) $profile?->gender)] ?? null,
            'citizenship' => $clientSheet->nationality,
            'height_cm' => $number($clientSheet->height),
            'weight_kg' => $number($clientSheet->weight),
            'civil_status' => array_key_exists(strtolower((string) $clientSheet->civil_status), TouristVisaApplication::CIVIL_STATUSES) ? strtolower($clientSheet->civil_status) : null,
            'address_street' => $clientSheet->address,
            'mobile_number' => $clientSheet->mobile_number,
            'email' => $clientSheet->email,
            'passport_number' => $clientSheet->passport_number,
            'passport_validity' => $profile?->passport_expiry,
            'latest_extension_validity' => $clientSheet->visa_expiry_date,
            'overstayed' => $overstay !== null || $clientSheet->is_expired,
            'checklist' => $overstay ? array_values(array_filter(ImmigrationOverstay::BI_CHECKLIST_ITEMS, $overstay->hasChecked(...))) : [],
            'filed_on' => $overstay?->filed_on ?? now(),
        ]);

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the BI {$form} for '{$clientSheet->full_name}'");

        return view($form === 'checklist' ? 'visa-assistance.tourist-visa.print-checklist' : 'visa-assistance.tourist-visa.print-form', [
            'application' => $application,
            'blank' => false,
            'links' => [
                ['Back to the application', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'preview' => 1] + $folder)],
                $form === 'checklist'
                    ? ['Application form (CGAF)', route('admin.client-sheets.bi-form', ['clientSheet' => $clientSheet, 'form' => 'cgaf'] + $folder)]
                    : ['Checklist & claim slip', route('admin.client-sheets.bi-form', ['clientSheet' => $clientSheet, 'form' => 'checklist'] + $folder)],
            ],
        ]);
    }

    /**
     * The BI forms blank, for a client to fill in by hand.
     */
    public function printBlankBiForm(string $form)
    {
        return view($form === 'checklist' ? 'visa-assistance.tourist-visa.print-checklist' : 'visa-assistance.tourist-visa.print-form', [
            'application' => new TouristVisaApplication,
            'blank' => true,
            'links' => [['Back to the counter', route('admin.immigration.dashboard')]],
        ]);
    }

    /**
     * The BI's Request for Reconsideration on the Updating and Extension of Authorized
     * Stay (BI Form 2014-01-011), filled from the client sheet and its overstay case.
     */
    public function printReconsideration(Request $request, ImmigrationClient $clientSheet)
    {
        $overstay = $this->overstayCase($clientSheet, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $overstay, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the BI request for reconsideration for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.reconsideration', [
            'client' => $clientSheet,
            'overstay' => $overstay,
            'blank' => false,
            'links' => [
                ['Back to the application', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'preview' => 1, 'service' => 'visa_overstaying'] + array_filter(['case' => $overstay->id]))],
                ['Application form (CGAF)', route('admin.client-sheets.bi-form', ['clientSheet' => $clientSheet, 'form' => 'cgaf'] + array_filter(['case' => $overstay->id]))],
                ['Checklist & claim slip', route('admin.client-sheets.bi-form', ['clientSheet' => $clientSheet, 'form' => 'checklist'] + array_filter(['case' => $overstay->id]))],
            ],
        ]);
    }

    /**
     * The request for reconsideration blank, for a client to fill in by hand.
     */
    public function printBlankReconsideration()
    {
        return view('admin.client-sheets.reconsideration', [
            'client' => new ImmigrationClient,
            'overstay' => new ImmigrationOverstay,
            'blank' => true,
            'links' => [['Back to the counter', route('admin.immigration.dashboard')]],
        ]);
    }

    /**
     * The IRD Consolidated General Application Form (BI Form IRD01.QF.006), filled from
     * the client sheet and its re-stamping case.
     */
    public function printRestampingForm(Request $request, ImmigrationClient $clientSheet)
    {
        $restamping = $this->restampingCase($clientSheet, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $restamping, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the IRD application form for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.restamping-form', [
            'client' => $clientSheet,
            'restamping' => $restamping,
            'blank' => false,
            'links' => $this->restampingLinks($clientSheet, $restamping),
        ]);
    }

    /**
     * The BI checklist for a transfer of admission status or an amendment of admission,
     * ticked from the re-stamping case.
     */
    public function printRestampingChecklist(Request $request, ImmigrationClient $clientSheet, string $kind)
    {
        $restamping = $this->restampingCase($clientSheet, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $restamping, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the {$kind} checklist for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.restamping-checklist', [
            'kind' => $kind,
            'client' => $clientSheet,
            'restamping' => $restamping,
            'blank' => false,
            'links' => $this->restampingLinks($clientSheet, $restamping),
        ]);
    }

    /**
     * The re-stamping form or a checklist blank, to fill in by hand.
     */
    public function printBlankRestamping(string $form)
    {
        $links = [
            ['Back to the counter', route('admin.immigration.dashboard')],
            ['Application form', route('admin.client-sheets.restamping-blank', 'form')],
            ['Transfer checklist', route('admin.client-sheets.restamping-blank', 'transfer')],
            ['Amendment checklist', route('admin.client-sheets.restamping-blank', 'amendment')],
        ];
        $data = ['client' => new ImmigrationClient, 'restamping' => new ImmigrationRestamping, 'blank' => true, 'links' => $links];

        return $form === 'form'
            ? view('admin.client-sheets.restamping-form', $data)
            : view('admin.client-sheets.restamping-checklist', $data + ['kind' => $form]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function restampingLinks(ImmigrationClient $clientSheet, ImmigrationRestamping $restamping): array
    {
        $case = array_filter(['case' => $restamping->id]);

        return [
            ['Back to the application', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'preview' => 1, 'service' => 're_stamping_amendment'] + $case)],
            ['Application form', route('admin.client-sheets.restamping-form', ['clientSheet' => $clientSheet] + $case)],
            ['Transfer checklist', route('admin.client-sheets.restamping-checklist', ['clientSheet' => $clientSheet, 'kind' => 'transfer'] + $case)],
            ['Amendment checklist', route('admin.client-sheets.restamping-checklist', ['clientSheet' => $clientSheet, 'kind' => 'amendment'] + $case)],
        ];
    }

    /**
     * The 13(A) form, filled from the client sheet and its case: the immigrant visa CGAF
     * (BOC04.QF.001) for a probationary or permanent case, the ACR I-Card renewal form
     * (BI Form 2015-08-002) for a renewal.
     */
    public function printPetitionForm(Request $request, ImmigrationClient $clientSheet, string $type)
    {
        $petition = $this->petitionCase($clientSheet, $type, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $petition, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the 13(A) {$type} form for '{$clientSheet->full_name}'");

        return view($this->petitionFormView($type), [
            'client' => $clientSheet,
            'petition' => $petition,
            'blank' => false,
            'links' => $this->petitionLinks($clientSheet, $petition),
        ]);
    }

    /**
     * The ACR I-Card application (BI Form 2014-08-006), filed with a 13(A) probationary
     * or permanent application, filled from the client sheet and its case.
     */
    public function printPetitionAcr(Request $request, ImmigrationClient $clientSheet, string $type)
    {
        $petition = $this->petitionCase($clientSheet, $type, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $petition, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the ACR I-Card application for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.petition-acr', [
            'client' => $clientSheet,
            'petition' => $petition,
            'blank' => false,
            'links' => $this->petitionLinks($clientSheet, $petition),
        ]);
    }

    /**
     * The BI checklist for the 13(A) case, ticked from what is in hand.
     */
    public function printPetitionChecklist(Request $request, ImmigrationClient $clientSheet, string $type)
    {
        $petition = $this->petitionCase($clientSheet, $type, $request->integer('case') ?: null);

        if ($held = $this->heldForApproval($request, $petition, $clientSheet)) {
            return $held;
        }

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the 13(A) {$type} checklist for '{$clientSheet->full_name}'");

        return view($this->petitionChecklistView($type), [
            'client' => $clientSheet,
            'petition' => $petition,
            'blank' => false,
            'links' => $this->petitionLinks($clientSheet, $petition),
        ]);
    }

    /**
     * A 13(A) form or checklist blank, to fill in by hand.
     */
    public function printBlankPetition(string $type, string $form)
    {
        $petition = new ImmigrationPetition(['type' => $type]);
        $data = [
            'client' => new ImmigrationClient,
            'petition' => $petition,
            'blank' => true,
            'links' => [
                ['Back to the counter', route('admin.immigration.dashboard')],
                $form === 'form'
                    ? ['Checklist', route('admin.client-sheets.petition-blank', [$type, 'checklist'])]
                    : ['Application form', route('admin.client-sheets.petition-blank', [$type, 'form'])],
            ],
        ];

        if ($form === 'checklist') {
            return view($this->petitionChecklistView($type), $data);
        }

        if ($form === 'acr') {
            return view('admin.client-sheets.petition-acr', $data);
        }

        return view($this->petitionFormView($type), $data);
    }

    /** The BI form each petition type files on. */
    private function petitionFormView(string $type): string
    {
        return match ($type) {
            'renewal' => 'admin.client-sheets.petition-renewal',
            'dual' => 'admin.client-sheets.petition-dual',
            'downgrade' => 'admin.client-sheets.petition-downgrade-letter',
            'ecc' => 'admin.client-sheets.petition-ecc',
            default => 'admin.client-sheets.petition-cgaf',
        };
    }

    /** The checklist each petition type is filed with. */
    private function petitionChecklistView(string $type): string
    {
        return match ($type) {
            'dual' => 'admin.client-sheets.petition-dual-checklist',
            'downgrade' => 'admin.client-sheets.petition-downgrade-checklist',
            'ecc' => 'admin.client-sheets.petition-ecc-checklist',
            default => 'admin.client-sheets.petition-checklist',
        };
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function petitionLinks(ImmigrationClient $clientSheet, ImmigrationPetition $petition): array
    {
        $case = array_filter(['case' => $petition->id]);

        return [
            ['Back to the application', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'preview' => 1, 'service' => $petition->serviceKey()] + $case)],
            [ImmigrationPetition::FORM_LABELS[$petition->type] ?? 'Application form', route('admin.client-sheets.petition-form', ['clientSheet' => $clientSheet, 'type' => $petition->type] + $case)],
            ['Checklist', route('admin.client-sheets.petition-checklist', ['clientSheet' => $clientSheet, 'type' => $petition->type] + $case)],
            ...(in_array($petition->type, ['spouse', 'permanent'], true)
                ? [['ACR I-Card application', route('admin.client-sheets.petition-acr', ['clientSheet' => $clientSheet, 'type' => $petition->type] + $case)]]
                : []),
        ];
    }

    /**
     * Open a file uploaded for a 13(A) requirement. Reachable only through the counter.
     */
    public function petitionFile(ImmigrationClient $clientSheet, ImmigrationPetition $petition, string $item)
    {
        $document = $petition->documentFor($item);

        abort_unless($document && DocumentStorage::disk()->exists($document['path']), 404);

        return DocumentStorage::disk()->response($document['path'], $document['name']);
    }

    /**
     * Open a file uploaded for a re-stamping requirement. Reachable only through the counter.
     */
    public function restampingFile(ImmigrationClient $clientSheet, ImmigrationRestamping $restamping, string $item)
    {
        $document = $restamping->documentFor($item);

        abort_unless($document && DocumentStorage::disk()->exists($document['path']), 404);

        return DocumentStorage::disk()->response($document['path'], $document['name']);
    }

    /**
     * Open a file uploaded for an overstay requirement. Reachable only through the counter.
     */
    public function overstayFile(ImmigrationClient $clientSheet, ImmigrationOverstay $overstay, string $item)
    {
        $document = $overstay->documentFor($item);

        abort_unless($document && DocumentStorage::disk()->exists($document['path']), 404);

        return DocumentStorage::disk()->response($document['path'], $document['name']);
    }

    /**
     * Open a document's scan in the browser. Reachable only through the counter.
     */
    public function documentFile(ImmigrationClient $clientSheet, string $type)
    {
        $document = $clientSheet->documentFor($type);

        abort_unless($document?->file_path && DocumentStorage::disk()->exists($document->file_path), 404);

        return DocumentStorage::disk()->response($document->file_path, $document->original_name);
    }

    /**
     * Write the ten-row extension ledger, dropping rows the agent left empty.
     */
    /**
     * Write the ten-row ledger, dropping rows left empty. Returns the rows added by this
     * save (not the ones that were already on file), in ledger order.
     *
     * @return list<ImmigrationClientExtension>
     */
    private function syncExtensions(ImmigrationClient $client, Request $request): array
    {
        $added = [];
        $firstNew = true;

        foreach (range(1, ImmigrationClient::LEDGER_ROWS) as $sequence) {
            $row = $request->input("extensions.{$sequence}", []);

            $attributes = [
                'soa_or_number' => $row['soa_or_number'] ?? null,
                'extension_date' => $row['extension_date'] ?? null,
                'details' => $row['details'] ?? null,
                'amount_paid' => $row['amount_paid'] ?? null,
                'annual_report' => $row['annual_report'] ?? null,
                'refund' => $row['refund'] ?? null,
            ];

            if (collect($attributes)->every(fn ($value): bool => blank($value))) {
                $client->extensions()->where('sequence', $sequence)->delete();

                continue;
            }

            $existing = $client->extensions()->where('sequence', $sequence)->first();

            // A new row with money or an SOA / OR # is an extension done today, so it never goes
            // in without a date (a dateless row cannot move the visa).
            if (! $existing && blank($attributes['extension_date'])) {
                $attributes['extension_date'] = now()->toDateString();
            }

            // The length stays with the row; a row saved before the length was recorded keeps none.
            if (filled($row['duration'] ?? null)) {
                $attributes['duration'] = $row['duration'];
            }

            $extension = $client->extensions()->updateOrCreate(['sequence' => $sequence], $attributes);

            // A new row records how it is paid (a downpayment or terms amount sits on the first one), and
            // goes to an admin. So does a row an agent changed, or one the admin returned.
            $isNew = $extension->wasRecentlyCreated;
            if ($isNew) {
                $extension->fill($this->extensionPayment($extension, $request, $firstNew));
                $firstNew = false;
            }
            if ($isNew || $extension->wasChanged() || $extension->isReturned()) {
                $this->routeExtensionForApproval($extension, $request);
                $extension->save();
            }

            // A row counts as added when it is new, or when it was saved without a date and now has one.
            if ($isNew || ($existing && $existing->extension_date === null && $extension->extension_date !== null)) {
                $added[] = $extension;
            }
        }

        return $added;
    }

    /**
     * A finished extension moves the visa on: each new ledger row with a date and a
     * length pushes the expiry forward from the current one (or from the extension date
     * when no expiry is on file), and the expired and penalty marks are cleared. The
     * expiry reminder starts over for the new date.
     *
     * @param  list<ImmigrationClientExtension>  $added
     */
    private function extendVisa(ImmigrationClient $client, array $added, bool $allowed): string
    {
        // A date typed into step 2 by hand wins: these rows never move the visa, now or once approved.
        if (! $allowed) {
            foreach ($added as $extension) {
                $extension->forceFill(['visa_extended_at' => now()])->save();
            }

            return '';
        }

        // Only an approved row moves the visa; an agent's rows move it when the admin approves them.
        $until = VisaExtensionService::extend($client, array_filter($added, fn (ImmigrationClientExtension $row): bool => $row->isCleared()));

        if ($until === null) {
            return $added !== [] && $this->needsApproval !== [] ? ' The visa moves on once the admin approves the extension.' : '';
        }

        return ' The visa is now valid until '.$until->format('F j, Y').'.';
    }

    /**
     * How a new ledger row is paid, from the payment panel under the ledger. Full takes the row's
     * whole amount and none takes nothing; a downpayment or terms amount sits on the first new row.
     *
     * @return array<string, mixed>
     */
    private function extensionPayment(ImmigrationClientExtension $extension, Request $request, bool $first): array
    {
        $type = $request->input('extension_payment.payment_type');

        if (! array_key_exists((string) $type, ImmigrationPetition::PAYMENT_TYPES)) {
            return [];
        }

        return [
            'payment_type' => $type,
            'terms_count' => $type === 'terms' ? $request->input('extension_payment.terms_count') : null,
            'amount_received' => match ($type) {
                'full' => $extension->amount_paid ?? 0,
                'none' => 0,
                default => $first ? ($request->input('extension_payment.amount_received') ?: 0) : 0,
            },
        ];
    }

    /** The counter's sole approver has their own save approved on the spot; anyone else's waits for an approver. */
    private function routeExtensionForApproval(ImmigrationClientExtension $extension, Request $request): void
    {
        $user = $request->user();

        if (Approvals::selfApproves($user, 'immigration')) {
            $extension->approveBy($user);

            return;
        }

        $extension->submitForApproval($user);
        $this->needsApproval[] = $extension;
    }

    /**
     * The payment option, then who checks the case: an admin's own save is approved on the spot,
     * an agent's waits for an admin (and is emailed to them once the save is done).
     *
     * @param  array<string, mixed>  $data
     */
    private function settleCase(Model $case, Request $request, array $data): void
    {
        $case->fill($case::paymentFrom($data));
    }

    /**
     * Who checks the case, decided just before it is saved (after its files are applied). A new
     * case, or one whose details, dates, payment or files changed, goes to the admin (an admin's
     * own save is approved on the spot). Saving it unchanged keeps where it stands, so an approved
     * case stays approved; a returned one is sent back, since saving it is the agent's correction.
     */
    private function routeForApproval(Model $case, Request $request): void
    {
        $user = $request->user();
        $changed = ! $case->exists || $this->reallyChanged($case);

        if (Approvals::selfApproves($user, 'immigration')) {
            if ($changed || ! $case->isCleared() || $case->approval_status === null) {
                $case->approveBy($user);
            }

            return;
        }

        if ($changed || $case->isReturned()) {
            $case->submitForApproval($user);
            $this->needsApproval[] = $case;
        }
    }

    /**
     * Whether saving would change what is on file. Lists and details stored as JSON count as the
     * same when they hold the same things, whatever order they were sent in.
     */
    private function reallyChanged(Model $case): bool
    {
        $normalise = function (mixed $value) use (&$normalise): mixed {
            if (! is_array($value)) {
                return $value;
            }
            $value = array_map($normalise, $value);
            array_is_list($value) ? sort($value) : ksort($value);

            return $value;
        };

        foreach (array_keys($case->getDirty()) as $attribute) {
            $before = $case->getRawOriginal($attribute);
            $after = $case->getAttribute($attribute);

            if (is_array($after) || $case->hasCast($attribute, ['array', 'json'])) {
                $before = is_string($before) ? json_decode($before, true) : $before;
                if ($normalise($before ?? []) == $normalise($after ?? [])) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    /** Email the admins what an agent just saved. A failed email never stops the save: it is on the approvals page either way. */
    private function notifyApprovers(ImmigrationClient $client, Request $request): void
    {
        if ($this->needsApproval === []) {
            return;
        }

        $items = collect($this->needsApproval)->map(fn (Model $item): string => $item instanceof ImmigrationClientExtension
            ? 'Visa Extension, row '.$item->sequence.($item->amount_paid !== null ? ' · PHP '.number_format((float) $item->amount_paid, 2) : '')
            : $item->reference().' · '.ImmigrationCases::kinds()[ImmigrationCases::kindOf($item)]['label'].($item->fee !== null ? ' · fee PHP '.number_format((float) $item->fee, 2) : ''))
            ->all();

        try {
            Notification::send(
                Approvals::recipients('immigration', $request->user()),
                new ImmigrationApprovalRequestedNotification($client, $items, $request->user()->name),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function approvalNote(): string
    {
        return $this->needsApproval === [] ? '' : ' Sent to the admin for approval.';
    }

    /**
     * Start a new sheet from a registered client's profile, so staff only add
     * what the immigration counter needs on top.
     */
    private function fromRegisteredClient(User $user): ImmigrationClient
    {
        $name = filled($user->first_name) || filled($user->last_name)
            ? ['given_name' => $user->first_name, 'last_name' => $user->last_name]
            : $this->namePrefill((string) $user->name);

        return new ImmigrationClient([
            ...$name,
            'email' => $user->real_email,
            'mobile_number' => $user->phone,
            'address' => $user->address,
            'nationality' => $user->nationality,
            'date_of_birth' => $user->date_of_birth,
            'passport_number' => $user->passport_number,
            // The sheet keeps the BI's wording ("Married").
            'civil_status' => User::CIVIL_STATUSES[$user->civil_status] ?? null,
            'height' => $user->height,
            'weight' => $user->weight,
        ]);
    }

    /**
     * Start a new sheet from a name typed into the search: the last word is
     * taken as the surname.
     *
     * @return array{given_name?: string, last_name?: string}
     */
    private function namePrefill(string $typed): array
    {
        $parts = preg_split('/\s+/', trim($typed), -1, PREG_SPLIT_NO_EMPTY);

        if ($parts === []) {
            return [];
        }

        $lastName = count($parts) > 1 ? array_pop($parts) : '';

        return ['given_name' => implode(' ', $parts), 'last_name' => $lastName];
    }
}
