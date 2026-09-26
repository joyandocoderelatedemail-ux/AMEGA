<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
                'url' => route('admin.client-sheets.edit', $sheet),
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
                'url' => route('admin.client-sheets.create', ['client' => $client->id]),
            ]);

        return response()->json(['sheets' => $sheets, 'clients' => $clients]);
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

        return view('admin.client-sheets.create', compact('client'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateClient($request);

        // A new client at the counter is also a client account in the admin client list.
        $client = new ImmigrationClient($validated);
        $client->user_id = ClientAccountService::clientForSheet($client)->id;
        $client->save();

        $this->syncDocuments($client, $request);
        $this->syncExtensions($client, $request);

        ActivityLogger::log('Client Sheets', 'CREATE', "Created client information sheet for '{$client->full_name}'");

        return $this->afterSave($request, $client, 'Client sheet created.');
    }

    public function edit(ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions']);

        return view('admin.client-sheets.edit', ['client' => $clientSheet]);
    }

    public function update(Request $request, ImmigrationClient $clientSheet)
    {
        $validated = $this->validateClient($request);

        $clientSheet->fill($validated);
        $clientSheet->user_id = ClientAccountService::clientForSheet($clientSheet)->id;
        $clientSheet->save();

        $this->syncDocuments($clientSheet, $request);
        $this->syncExtensions($clientSheet, $request);

        ActivityLogger::log('Client Sheets', 'UPDATE', "Updated client information sheet for '{$clientSheet->full_name}'");

        return $this->afterSave($request, $clientSheet, 'Client sheet updated.');
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

        return redirect()->route('admin.client-sheets.edit', $client)
            ->with('success', $message.' You can print it now.');
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
            'extensions' => 'nullable|array',
            'extensions.*.soa_or_number' => 'nullable|string|max:255',
            'extensions.*.extension_date' => 'nullable|date',
            'extensions.*.details' => 'nullable|string',
            'extensions.*.amount_paid' => 'nullable|numeric|min:0',
            'extensions.*.annual_report' => 'nullable|string|max:255',
            'extensions.*.refund' => 'nullable|numeric|min:0',
        ]);

        unset($validated['documents'], $validated['extensions']);

        // Unticked checkboxes are absent from the request rather than false
        $validated['is_expired'] = $request->has('is_expired');
        $validated['has_penalty'] = $request->has('has_penalty');
        $validated['needs_attention'] = $request->has('needs_attention');

        return $validated;
    }

    /**
     * Write the Travel Information grid, dropping columns the agent left empty.
     */
    private function syncDocuments(ImmigrationClient $client, Request $request): void
    {
        foreach (array_keys(ImmigrationClientDocument::TYPES) as $type) {
            $row = $request->input("documents.{$type}", []);

            $attributes = [
                'reference_number' => $row['reference_number'] ?? null,
                'date_paid' => $row['date_paid'] ?? null,
                'ssrn_number' => $row['ssrn_number'] ?? null,
                'validity' => $row['validity'] ?? null,
            ];

            if (collect($attributes)->every(fn ($value): bool => blank($value))) {
                $client->documents()->where('document_type', $type)->delete();

                continue;
            }

            $client->documents()->updateOrCreate(['document_type' => $type], $attributes);
        }
    }

    /**
     * Write the ten-row extension ledger, dropping rows the agent left empty.
     */
    private function syncExtensions(ImmigrationClient $client, Request $request): void
    {
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

            $client->extensions()->updateOrCreate(['sequence' => $sequence], $attributes);
        }
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
