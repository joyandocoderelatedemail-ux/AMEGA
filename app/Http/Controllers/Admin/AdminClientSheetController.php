<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\ImmigrationClientExtension;
use App\Models\ImmigrationOverstay;
use App\Models\ImmigrationRestamping;
use App\Models\TouristVisaApplication;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientProfileService;
use App\Support\DocumentStorage;
use App\Support\ImmigrationServices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class AdminClientSheetController extends Controller
{
    /** How many matches of each kind the type-ahead shows. */
    private const LOOKUP_LIMIT = 6;

    /**
     * The counter screen: look a client up by name, passport, email or mobile.
     * While staff type, only the results are sent back, to refresh the list in place.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('passport'));
        $searched = $search !== '';
        $flaggedOnly = $request->boolean('flagged');

        $matches = $searched
            ? ImmigrationClient::search($search)->orderBy('last_name')->orderBy('given_name')->limit(25)->get()
            : collect();

        // Clients registered elsewhere (client list, ticketing desk) who have no sheet yet.
        $clientsWithoutSheet = $searched
            ? User::clientSearch($search)->whereDoesntHave('immigrationClients')->orderBy('name')->limit(8)->get()
            : collect();

        $recentClients = $searched
            ? collect()
            : ImmigrationClient::query()
                ->when($flaggedOnly, fn ($query) => $query->flagged())
                ->latest()
                ->limit($flaggedOnly ? 50 : 12)
                ->get();

        $flaggedCount = ImmigrationClient::flagged()->count();

        $data = compact('search', 'searched', 'matches', 'clientsWithoutSheet', 'recentClients', 'flaggedOnly', 'flaggedCount');

        if ($request->ajax()) {
            return view('admin.client-sheets.listing', $data);
        }

        return view('admin.client-sheets.index', $data);
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
     * Name the folder: find the client (an existing sheet opens their folder, a
     * registered client starts one filled from their profile) or add a new one.
     */
    public function start(Request $request)
    {
        return view('admin.client-sheets.start', [
            'service' => ImmigrationServices::find($request->input('service')),
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

    public function store(Request $request)
    {
        $validated = $this->validateClient($request);
        $overstay = $this->validateOverstay($request);
        $restamping = $this->validateRestamping($request);

        // A new client at the counter is also a client account in the admin client list.
        $client = new ImmigrationClient($validated);
        $client->user_id = ClientAccountService::clientForSheet($client)->id;
        $client->save();

        $this->syncDocuments($client, $request);
        $cases = $this->syncOverstay($client, $request, $overstay, $client->visa_expiry_date)
            .$this->syncRestamping($client, $request, $restamping);
        $extended = $this->usesLedger($request) ? $this->extendVisa($client, $this->syncExtensions($client, $request), true) : '';

        ActivityLogger::log('Client Sheets', 'CREATE', "Created client information sheet for '{$client->full_name}'");

        return $this->afterSave($request, $client, 'Client sheet created.'.$cases.$extended);
    }

    public function edit(Request $request, ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions', 'overstays', 'restampings']);
        $service = ImmigrationServices::find($request->input('service'));

        return view('admin.client-sheets.edit', [
            'client' => $clientSheet,
            'service' => $service,
            'overstay' => $service['key'] === 'visa_overstaying' ? $this->overstayCase($clientSheet) : null,
            'restamping' => $service['key'] === 're_stamping_amendment' ? $this->restampingCase($clientSheet) : null,
        ]);
    }

    public function update(Request $request, ImmigrationClient $clientSheet)
    {
        $validated = $this->validateClient($request);
        $overstay = $this->validateOverstay($request);
        $restamping = $this->validateRestamping($request);
        $expiryBefore = $clientSheet->visa_expiry_date;

        // A date typed into step 2 by hand wins over the one the new extension would give.
        $expiryUntouched = $clientSheet->visa_expiry_date?->toDateString() === ($validated['visa_expiry_date'] ?? null);

        $clientSheet->fill($validated);
        $clientSheet->user_id = ClientAccountService::clientForSheet($clientSheet)->id;
        $clientSheet->save();

        $this->syncDocuments($clientSheet, $request);
        $cases = $this->syncOverstay($clientSheet, $request, $overstay, $expiryBefore)
            .$this->syncRestamping($clientSheet, $request, $restamping);
        $extended = $this->usesLedger($request) ? $this->extendVisa($clientSheet, $this->syncExtensions($clientSheet, $request), $expiryUntouched) : '';

        ActivityLogger::log('Client Sheets', 'UPDATE', "Updated client information sheet for '{$clientSheet->full_name}'");

        return $this->afterSave($request, $clientSheet, 'Client sheet updated.'.$cases.$extended);
    }

    /**
     * The last step of the counter process: "Save & print" goes straight to
     * the print page with the print dialog open; "Save only" stays on the record.
     */
    private function afterSave(Request $request, ImmigrationClient $client, string $message): RedirectResponse
    {
        if ($request->input('then') === 'print') {
            return redirect()->route('admin.client-sheets.print', ['clientSheet' => $client, 'autoprint' => 1]);
        }

        return redirect()->route('admin.client-sheets.edit', array_filter(['client_sheet' => $client->id, 'service' => $this->serviceParam($request)]))
            ->with('success', $message.' You can print it now.');
    }

    /** The service to carry in a URL: none for Visa Extension, which is what a sheet opens in. */
    private function serviceParam(Request $request): ?string
    {
        $key = ImmigrationServices::find($request->input('service'))['key'];

        return $key === ImmigrationServices::DEFAULT ? null : $key;
    }

    /**
     * Re Stamping has no ledger step, so its form sends no ledger rows; writing the
     * ledger from it would clear the rows on file.
     */
    private function usesLedger(Request $request): bool
    {
        return ImmigrationServices::find($request->input('service'))['key'] !== 're_stamping_amendment';
    }

    /** The client's re-stamping case in progress, or a new one. */
    private function restampingCase(ImmigrationClient $client): ImmigrationRestamping
    {
        return $client->openRestamping() ?? new ImmigrationRestamping(['immigration_client_id' => $client->id]);
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
            'restamping.released_on' => 'nullable|date|after_or_equal:restamping.filed_on',
        ], [
            'restamping.applications.required' => 'Tick what the client is applying for in step 3: transfer, amendment, or a failure to check, encode or stamp.',
        ], [
            'restamping.arrival_date' => 'latest date of arrival',
            'restamping.filed_on' => 'date filed',
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

        $case = $client->openRestamping() ?? new ImmigrationRestamping([
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
            'checklist' => array_values(array_unique($data['checklist'] ?? [])),
            'filed_on' => $data['filed_on'] ?? null,
            'released_on' => $data['released_on'] ?? null,
        ]);

        $this->syncRequirementFiles($case, $request, 'restamping', $data, ImmigrationRestamping::allItems(), "immigration/{$client->id}/restamping");

        $isNew = ! $case->exists;
        $case->save();

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
    private function overstayCase(ImmigrationClient $client): ImmigrationOverstay
    {
        return $client->openOverstay()
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
            'overstay.released_on' => 'nullable|date|after_or_equal:overstay.filed_on',
        ], [], [
            'overstay.expired_on' => 'authorized stay expiry',
            'overstay.departure_date' => 'departure date',
            'overstay.filed_on' => 'date filed',
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

        $case = $client->openOverstay() ?? new ImmigrationOverstay([
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
            'checklist' => array_values(array_unique($data['checklist'] ?? [])),
            'filed_on' => $data['filed_on'] ?? null,
            'released_on' => $data['released_on'] ?? null,
        ]);

        $this->syncRequirementFiles($case, $request, 'overstay', $data, ImmigrationOverstay::CHECKLIST, "immigration/{$client->id}/overstay");

        $isNew = ! $case->exists;
        $case->save();

        ActivityLogger::log('Immigration', $isNew ? 'OVERSTAY_OPEN' : 'OVERSTAY_UPDATE', "Overstay case for '{$client->full_name}' ".($isNew ? 'opened' : 'updated'));

        return $case->isOpen() ? ' Overstay case saved.' : ' Overstay case closed as released.';
    }

    /**
     * The populated sheet, laid out to match the printed AMEGA form.
     */
    public function print(ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions']);

        ActivityLogger::log('Client Sheets', 'PRINT', "Printed client information sheet for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.print', ['client' => $clientSheet]);
    }

    /**
     * An empty sheet to hand a walk-in client to fill in by hand.
     */
    public function blank()
    {
        return view('admin.client-sheets.print', ['client' => new ImmigrationClient]);
    }

    public function destroy(ImmigrationClient $clientSheet)
    {
        $name = $clientSheet->full_name;
        $clientSheet->delete();

        ActivityLogger::log('Client Sheets', 'DELETE', "Deleted client information sheet for '{$name}'");

        return redirect()->route('admin.client-sheets.index')->with('success', 'Client sheet deleted.');
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
        ]);

        unset($validated['documents'], $validated['extensions']);

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
    public function printBiForm(ImmigrationClient $clientSheet, string $form)
    {
        $clientSheet->load('extensions');
        $latest = $clientSheet->extensions->sortByDesc('sequence')->first();
        // An overstay case in progress marks the CGAF overstayed and ticks the checklist.
        $overstay = $clientSheet->openOverstay();
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
                ['Back to the client sheet', route('admin.client-sheets.edit', array_filter(['client_sheet' => $clientSheet->id, 'service' => $overstay ? 'visa_overstaying' : null]))],
                $form === 'checklist'
                    ? ['Application form (CGAF)', route('admin.client-sheets.bi-form', [$clientSheet, 'cgaf'])]
                    : ['Checklist & claim slip', route('admin.client-sheets.bi-form', [$clientSheet, 'checklist'])],
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
    public function printReconsideration(ImmigrationClient $clientSheet)
    {
        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the BI request for reconsideration for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.reconsideration', [
            'client' => $clientSheet,
            'overstay' => $this->overstayCase($clientSheet),
            'blank' => false,
            'links' => [
                ['Back to the client sheet', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'service' => 'visa_overstaying'])],
                ['Application form (CGAF)', route('admin.client-sheets.bi-form', [$clientSheet, 'cgaf'])],
                ['Checklist & claim slip', route('admin.client-sheets.bi-form', [$clientSheet, 'checklist'])],
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
    public function printRestampingForm(ImmigrationClient $clientSheet)
    {
        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the IRD application form for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.restamping-form', [
            'client' => $clientSheet,
            'restamping' => $this->restampingCase($clientSheet),
            'blank' => false,
            'links' => $this->restampingLinks($clientSheet),
        ]);
    }

    /**
     * The BI checklist for a transfer of admission status or an amendment of admission,
     * ticked from the re-stamping case.
     */
    public function printRestampingChecklist(ImmigrationClient $clientSheet, string $kind)
    {
        ActivityLogger::log('Client Sheets', 'PRINT', "Printed the {$kind} checklist for '{$clientSheet->full_name}'");

        return view('admin.client-sheets.restamping-checklist', [
            'kind' => $kind,
            'client' => $clientSheet,
            'restamping' => $this->restampingCase($clientSheet),
            'blank' => false,
            'links' => $this->restampingLinks($clientSheet),
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
    private function restampingLinks(ImmigrationClient $clientSheet): array
    {
        return [
            ['Back to the client sheet', route('admin.client-sheets.edit', ['client_sheet' => $clientSheet->id, 'service' => 're_stamping_amendment'])],
            ['Application form', route('admin.client-sheets.restamping-form', $clientSheet)],
            ['Transfer checklist', route('admin.client-sheets.restamping-checklist', [$clientSheet, 'transfer'])],
            ['Amendment checklist', route('admin.client-sheets.restamping-checklist', [$clientSheet, 'amendment'])],
        ];
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

            // The length stays with the row; a row saved before the length was recorded keeps none.
            if (filled($row['duration'] ?? null)) {
                $attributes['duration'] = $row['duration'];
            }

            $extension = $client->extensions()->updateOrCreate(['sequence' => $sequence], $attributes);

            if ($extension->wasRecentlyCreated) {
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
        $expiry = $client->visa_expiry_date;
        $applied = false;

        foreach ($added as $extension) {
            if (! $allowed || $extension->extension_date === null) {
                continue;
            }

            $until = ImmigrationClientExtension::runsUntil($expiry ?? $extension->extension_date, $extension->duration);

            if ($until !== null) {
                $expiry = $until;
                $applied = true;
            }
        }

        if (! $applied) {
            return '';
        }

        $client->forceFill([
            'visa_expiry_date' => $expiry,
            'is_expired' => false,
            'has_penalty' => false,
            'expiry_reminder_sent_for' => null,
        ])->save();

        ActivityLogger::log('Client Sheets', 'EXTEND', "Visa of '{$client->full_name}' extended to {$expiry->format('M d, Y')}");

        return ' The visa is now valid until '.$expiry->format('F j, Y').'.';
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
            'email' => $user->email,
            'mobile_number' => $user->phone,
            'address' => $user->address,
            'nationality' => $user->nationality,
            'date_of_birth' => $user->date_of_birth,
            'passport_number' => $user->passport_number,
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
