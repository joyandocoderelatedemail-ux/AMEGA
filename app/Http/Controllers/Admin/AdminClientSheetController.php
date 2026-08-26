<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImmigrationClient;
use App\Models\ImmigrationClientDocument;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminClientSheetController extends Controller
{
    /**
     * The counter screen: look a client up by passport number.
     */
    public function index(Request $request)
    {
        $passportNumber = trim((string) $request->input('passport'));
        $searched = $passportNumber !== '';
        $flaggedOnly = $request->boolean('flagged');

        $matches = $searched
            ? ImmigrationClient::matchingPassport($passportNumber)->orderBy('last_name')->limit(25)->get()
            : collect();

        $recentClients = $searched
            ? collect()
            : ImmigrationClient::query()
                ->when($flaggedOnly, fn ($query) => $query->where(fn ($sub) => $sub
                    ->where('is_expired', true)
                    ->orWhere('has_penalty', true)
                    ->orWhere('visa_expiry_date', '<', now()->startOfDay())
                ))
                ->latest()
                ->limit($flaggedOnly ? 50 : 12)
                ->get();

        $flaggedCount = ImmigrationClient::where('is_expired', true)
            ->orWhere('has_penalty', true)
            ->orWhere('visa_expiry_date', '<', now()->startOfDay())
            ->count();

        return view('admin.client-sheets.index', compact(
            'passportNumber', 'searched', 'matches', 'recentClients', 'flaggedOnly', 'flaggedCount'
        ));
    }

    public function create(Request $request)
    {
        $client = new ImmigrationClient(['passport_number' => trim((string) $request->input('passport'))]);

        return view('admin.client-sheets.create', compact('client'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateClient($request);

        $client = ImmigrationClient::create($validated);

        $this->syncDocuments($client, $request);
        $this->syncExtensions($client, $request);

        ActivityLogger::log('Client Sheets', 'CREATE', "Created client information sheet for '{$client->full_name}'");

        return redirect()->route('admin.client-sheets.edit', $client)
            ->with('success', 'Client sheet created. You can print it now.');
    }

    public function edit(ImmigrationClient $clientSheet)
    {
        $clientSheet->load(['documents', 'extensions']);

        return view('admin.client-sheets.edit', ['client' => $clientSheet]);
    }

    public function update(Request $request, ImmigrationClient $clientSheet)
    {
        $validated = $this->validateClient($request);

        $clientSheet->update($validated);

        $this->syncDocuments($clientSheet, $request);
        $this->syncExtensions($clientSheet, $request);

        ActivityLogger::log('Client Sheets', 'UPDATE', "Updated client information sheet for '{$clientSheet->full_name}'");

        return redirect()->route('admin.client-sheets.edit', $clientSheet)
            ->with('success', 'Client sheet updated.');
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
}
