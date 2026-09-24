<?php

namespace App\Http\Controllers\Srrv;

use App\Http\Controllers\Controller;
use App\Models\SrrvApplication;
use App\Models\SrrvApplicationDocument;
use App\Models\SrrvPricingTier;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Support\DocumentStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The SRRV retiree file workflow: the renewal application and re-stamping jobs
 * worked through the Philippine Retirement Authority.
 *
 * Milestones here are stamped when a file ENTERS a stage, because the stages are
 * states the file sits in ("lodged", "awaiting release") rather than actions the
 * counter completes. The visa counter works the other way round: its stages are
 * actions, so it stamps on the way out.
 */
class SrrvApplicationController extends Controller
{
    /**
     * Which timestamps a stage records on entry.
     *
     * @var array<string, list<string>>
     */
    private const STAGE_MILESTONES = [
        'lodged' => ['email_sent_at', 'lodged_at'],
        'processing' => ['payment_in_full_at'],
        'awaiting_release' => ['oath_at'],
        'released' => ['released_at'],
    ];

    /**
     * Display the retiree file directory with filters.
     */
    public function index(Request $request): View
    {
        $query = SrrvApplication::withCount('documents')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('retiree_name', 'like', "%{$search}%")
                    ->orWhere('retiree_email', 'like', "%{$search}%")
                    ->orWhere('srrv_card_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        if ($request->filled('visa_class')) {
            $query->where('visa_class', $request->input('visa_class'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $applications = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => SrrvApplication::count(),
            'classic' => SrrvApplication::where('visa_class', 'classic')->count(),
            'courtesy' => SrrvApplication::where('visa_class', 'courtesy')->count(),
            'open' => SrrvApplication::whereNotIn('status', ['released', 'cancelled'])->count(),
            'awaitingOath' => SrrvApplication::whereNull('oath_at')
                ->whereNotNull('payment_in_full_at')
                ->count(),
        ];

        return view('srrv.applications.index', compact('applications', 'stats'));
    }

    /**
     * Show the intake form.
     */
    public function create(Request $request): View
    {
        $serviceType = $request->input('service_type', 'renewal_application');

        if (! in_array($serviceType, SrrvApplication::SERVICE_TYPES, true)) {
            $serviceType = 'renewal_application';
        }

        return view('srrv.applications.create', [
            'serviceType' => $serviceType,
            'pricing' => SrrvPricingTier::published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Store a newly opened retiree file.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $application = SrrvApplication::create($this->attributesFrom($validated) + [
            'reference' => SrrvApplication::generateReference(),
            'created_by' => Auth::id(),
            'status' => 'pending',
        ]);

        ActivityLogger::log('SRRV', 'CREATE', "Opened SRRV file {$application->reference} ({$application->visa_class}) for {$application->retiree_name}");

        ClientAccountService::findOrCreateClient([
            'name' => $application->retiree_name,
            'email' => $application->retiree_email,
            'phone' => $application->retiree_phone,
            'nationality' => $application->nationality,
        ]);

        return redirect()->route('srrv.applications.show', $application)
            ->with('success', "SRRV file {$application->reference} opened successfully.");
    }

    /**
     * Display the retiree file.
     */
    public function show(SrrvApplication $application): View
    {
        $application->load(['documents', 'renewals', 'creator']);

        $stages = $application->stages();
        $currentIndex = array_search($application->status, $stages, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return view('srrv.applications.show', [
            'application' => $application,
            'stages' => $stages,
            'currentIndex' => $currentIndex,
            'nextStage' => $stages[$currentIndex + 1] ?? null,
            'isFinal' => $currentIndex >= count($stages) - 1,
            'documentTypes' => SrrvApplicationDocument::DOCUMENT_TYPES,
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(SrrvApplication $application): View
    {
        return view('srrv.applications.edit', [
            'application' => $application,
            'pricing' => SrrvPricingTier::published()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Update a retiree file.
     */
    public function update(Request $request, SrrvApplication $application): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $application->update($this->attributesFrom($validated));

        ActivityLogger::log('SRRV', 'UPDATE', "Updated SRRV file {$application->reference}");

        return redirect()->route('srrv.applications.show', $application)
            ->with('success', 'SRRV file updated.');
    }

    /**
     * Advance the file one step along its pipeline.
     */
    public function advance(SrrvApplication $application): RedirectResponse
    {
        $stages = $application->stages();
        $current = array_search($application->status, $stages, true);

        if ($current === false) {
            return back()->with('error', 'This file is not on an active pipeline.');
        }

        if ($current >= count($stages) - 1) {
            return back()->with('error', 'This file is already at its final stage.');
        }

        $next = $stages[$current + 1];

        // Entering a stage is what records its milestone.
        $milestones = [];
        foreach (self::STAGE_MILESTONES[$next] ?? [] as $column) {
            $milestones[$column] = now();
        }

        $application->update(['status' => $next] + $milestones);

        ActivityLogger::log('SRRV', 'ADVANCE', "SRRV file {$application->reference} advanced to {$next}");

        return back()->with('success', 'File advanced to '.str_replace('_', ' ', $next).'.');
    }

    /**
     * Cancel a retiree file.
     */
    public function cancel(SrrvApplication $application): RedirectResponse
    {
        $application->update(['status' => 'cancelled']);

        ActivityLogger::log('SRRV', 'CANCEL', "Cancelled SRRV file {$application->reference}");

        return back()->with('success', "File {$application->reference} cancelled.");
    }

    /**
     * Remove a retiree file and everything filed under it.
     */
    public function destroy(SrrvApplication $application): RedirectResponse
    {
        $reference = $application->reference;

        DocumentStorage::disk()->deleteDirectory("srrv/{$reference}");
        $application->delete();

        ActivityLogger::log('SRRV', 'DELETE', "Deleted SRRV file {$reference}");

        return redirect()->route('srrv.applications.index')
            ->with('success', "SRRV file {$reference} deleted.");
    }

    /**
     * File a document against a retiree file.
     */
    public function storeDocument(Request $request, SrrvApplication $application): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'in:'.implode(',', SrrvApplicationDocument::DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store("srrv/{$application->reference}", DocumentStorage::diskName());

        $application->documents()->create([
            'document_type' => $validated['document_type'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'status' => 'uploaded',
        ]);

        ActivityLogger::log('SRRV', 'UPLOAD_DOCUMENT', "Filed a {$validated['document_type']} document on {$application->reference}");

        return back()->with('success', 'Document filed.');
    }

    /**
     * Download a filed document. Reachable only through the SRRV middleware.
     */
    public function downloadDocument(SrrvApplicationDocument $document): StreamedResponse|RedirectResponse
    {
        if (! DocumentStorage::disk()->exists($document->file_path)) {
            return back()->with('error', 'The requested document could not be found.');
        }

        return DocumentStorage::disk()->download($document->file_path, $document->original_name);
    }

    /**
     * Map validated input onto model attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributesFrom(array $validated): array
    {
        return [
            'service_type' => $validated['service_type'],
            'visa_class' => $validated['visa_class'],
            'retiree_name' => $validated['retiree_name'],
            'retiree_email' => $validated['retiree_email'] ?? null,
            'retiree_phone' => $validated['retiree_phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'nationality' => $validated['nationality'] ?? null,
            'srrv_card_number' => $validated['srrv_card_number'] ?? null,
            'investment_amount' => (float) ($validated['investment_amount'] ?? 0),
            'police_clearance_received' => ! empty($validated['police_clearance_received']),
            'pension_proof_received' => ! empty($validated['pension_proof_received']),
            'military_service_proof_received' => ! empty($validated['military_service_proof_received']),
            // "Four copies of everything" is the standing rule.
            'copies_submitted' => (int) ($validated['copies_submitted'] ?? 4),
            'service_fee' => (float) ($validated['service_fee'] ?? 0),
            'amount_paid' => (float) ($validated['amount_paid'] ?? 0),
            'currency' => $validated['currency'] ?? 'USD',
            'remarks' => $validated['remarks'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'service_type' => ['required', 'in:'.implode(',', SrrvApplication::SERVICE_TYPES)],
            'visa_class' => ['required', 'in:'.implode(',', SrrvApplication::VISA_CLASSES)],
            'retiree_name' => ['required', 'string', 'max:255'],
            'retiree_email' => ['nullable', 'email', 'max:255'],
            'retiree_phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'srrv_card_number' => ['nullable', 'string', 'max:100'],
            'investment_amount' => ['nullable', 'numeric', 'min:0'],
            'police_clearance_received' => ['nullable', 'boolean'],
            'pension_proof_received' => ['nullable', 'boolean'],
            'military_service_proof_received' => ['nullable', 'boolean'],
            'copies_submitted' => ['nullable', 'integer', 'min:1', 'max:10'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
