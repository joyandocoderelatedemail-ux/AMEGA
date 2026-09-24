<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmNote;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CrmSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminCrmController extends Controller
{
    /**
     * Display the CRM pipeline dashboard (Kanban & Table list).
     */
    public function index(Request $request, CrmSyncService $syncService): View
    {
        // Auto-sync if database has 0 CRM leads yet or explicit sync requested
        if (CrmLead::count() === 0 || $request->has('sync_now')) {
            $syncService->syncAll();
        }

        // Base query for calculations and listing
        $query = CrmLead::with(['assignedUser', 'notes.user'])->latest();

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('client_phone', 'like', "%{$search}%")
                    ->orWhere('reference_code', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // Service Type filter
        if ($serviceType = $request->input('service_type')) {
            $query->where('service_type', $serviceType);
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Assigned Agent filter
        if ($assignedTo = $request->input('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        // Clone query for leads collection before stage filter
        $filteredLeads = (clone $query)->get();

        // Group leads by stage for Kanban board
        $stages = [
            CrmLead::STAGE_NEW => $filteredLeads->where('stage', CrmLead::STAGE_NEW)->values(),
            CrmLead::STAGE_CONTACTED => $filteredLeads->where('stage', CrmLead::STAGE_CONTACTED)->values(),
            CrmLead::STAGE_QUOTED => $filteredLeads->where('stage', CrmLead::STAGE_QUOTED)->values(),
            CrmLead::STAGE_WON => $filteredLeads->where('stage', CrmLead::STAGE_WON)->values(),
            CrmLead::STAGE_LOST => $filteredLeads->where('stage', CrmLead::STAGE_LOST)->values(),
        ];

        // Column value totals
        $stageTotals = [];
        foreach ($stages as $stageKey => $leadsInStage) {
            $stageTotals[$stageKey] = $leadsInStage->sum('estimated_value');
        }

        // Global Pipeline KPI Metrics (calculated from all CRM leads)
        $allLeads = CrmLead::all();
        $activeStages = [CrmLead::STAGE_NEW, CrmLead::STAGE_CONTACTED, CrmLead::STAGE_QUOTED];

        $activeLeads = $allLeads->whereIn('stage', $activeStages);
        $totalPipelineValue = $activeLeads->sum('estimated_value');
        $activeDealsCount = $activeLeads->count();

        $wonLeads = $allLeads->where('stage', CrmLead::STAGE_WON);
        $wonDealsCount = $wonLeads->count();
        $wonDealsValue = $wonLeads->sum('estimated_value');

        $lostLeads = $allLeads->where('stage', CrmLead::STAGE_LOST);
        $lostDealsCount = $lostLeads->count();

        $closedDealsCount = $wonDealsCount + $lostDealsCount;
        $conversionRate = $closedDealsCount > 0
            ? round(($wonDealsCount / $closedDealsCount) * 100, 1)
            : ($allLeads->count() > 0 ? round(($wonDealsCount / $allLeads->count()) * 100, 1) : 0.0);

        // Paginated list for Table View
        $tableLeads = (clone $query)->paginate(15)->withQueryString();

        // Agents list for assignment
        $agents = User::whereIn('role', ['admin', 'agent'])
            ->orderBy('name')
            ->get();

        return view('admin.crm.index', [
            'stages' => $stages,
            'stageTotals' => $stageTotals,
            'tableLeads' => $tableLeads,
            'agents' => $agents,
            'metrics' => [
                'totalPipelineValue' => $totalPipelineValue,
                'activeDealsCount' => $activeDealsCount,
                'wonDealsCount' => $wonDealsCount,
                'wonDealsValue' => $wonDealsValue,
                'lostDealsCount' => $lostDealsCount,
                'conversionRate' => $conversionRate,
                'totalLeads' => $allLeads->count(),
            ],
            'viewMode' => $request->input('view', 'kanban'),
        ]);
    }

    /**
     * Store a newly created CRM lead.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'service_type' => 'required|string|in:custom_tour,ready_package,flight_ticket,visa_assistance,srrv,general',
            'source' => 'required|string|in:website,walk_in,phone,facebook,whatsapp,referral,portal',
            'title' => 'required|string|max:255',
            'destination' => 'nullable|string|max:255',
            'travel_date' => 'nullable|date',
            'number_of_pax' => 'nullable|integer|min:1',
            'estimated_value' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stage' => 'required|string|in:new,contacted,quoted,won,lost',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $validated['reference_code'] = CrmLead::generateReferenceCode();
        $validated['currency'] = $validated['currency'] ?? 'PHP';
        $validated['number_of_pax'] = $validated['number_of_pax'] ?? 1;
        $validated['estimated_value'] = $validated['estimated_value'] ?? 0;

        if (in_array($validated['stage'], [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])) {
            $validated['closed_at'] = now();
        }

        $lead = CrmLead::create($validated);

        // Record initial note if provided
        if (! empty($validated['notes'])) {
            CrmNote::create([
                'crm_lead_id' => $lead->id,
                'user_id' => Auth::id(),
                'action_type' => 'note',
                'content' => $validated['notes'],
            ]);
        }

        ActivityLogger::log('CRM', 'CREATE_LEAD', "Created CRM lead {$lead->reference_code} for {$lead->client_name} ({$lead->title})");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead {$lead->reference_code} created successfully.",
                'lead' => $lead->load('assignedUser'),
            ]);
        }

        return redirect()->route('admin.crm.index')->with('success', "Lead {$lead->reference_code} created successfully.");
    }

    /**
     * Display lead details with activity log.
     */
    public function show(CrmLead $lead): JsonResponse|View
    {
        $lead->load(['assignedUser', 'notes.user']);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'lead' => $lead,
            ]);
        }

        return view('admin.crm.show', ['lead' => $lead]);
    }

    /**
     * Update lead details.
     */
    public function update(Request $request, CrmLead $lead): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'service_type' => 'required|string|in:custom_tour,ready_package,flight_ticket,visa_assistance,srrv,general',
            'title' => 'required|string|max:255',
            'destination' => 'nullable|string|max:255',
            'travel_date' => 'nullable|date',
            'number_of_pax' => 'nullable|integer|min:1',
            'estimated_value' => 'nullable|numeric|min:0',
            'priority' => 'required|string|in:low,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $lead->update($validated);

        ActivityLogger::log('CRM', 'UPDATE_LEAD', "Updated CRM lead details for {$lead->reference_code}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead {$lead->reference_code} updated successfully.",
                'lead' => $lead->load('assignedUser'),
            ]);
        }

        return redirect()->route('admin.crm.index')->with('success', "Lead {$lead->reference_code} updated successfully.");
    }

    /**
     * Update lead sales stage (e.g. from Kanban drag-and-drop or select menu).
     */
    public function updateStage(Request $request, CrmLead $lead, CrmSyncService $syncService): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'stage' => 'required|string|in:new,contacted,quoted,won,lost',
            'lost_reason' => 'nullable|string|max:255',
        ]);

        $oldStage = $lead->stage;
        $newStage = $validated['stage'];

        if ($oldStage === $newStage) {
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'No stage change.']);
            }

            return redirect()->back();
        }

        $updateData = ['stage' => $newStage];
        if (in_array($newStage, [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])) {
            $updateData['closed_at'] = now();
            if ($newStage === CrmLead::STAGE_LOST && ! empty($validated['lost_reason'])) {
                $updateData['lost_reason'] = $validated['lost_reason'];
            }
        } else {
            $updateData['closed_at'] = null;
        }

        $lead->update($updateData);

        // Propagate stage update back to source model (e.g. CustomPackageInquiry, TicketBooking, Inquiry)
        $syncService->syncLeadToSource($lead);

        // Record automated status change note in CRM history
        $userName = Auth::user() ? Auth::user()->name : 'Staff';
        $oldLabel = CrmLead::STAGES[$oldStage]['label'] ?? $oldStage;
        $newLabel = CrmLead::STAGES[$newStage]['label'] ?? $newStage;

        CrmNote::create([
            'crm_lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'action_type' => 'status_change',
            'content' => "Stage advanced from \"{$oldLabel}\" to \"{$newLabel}\" by {$userName}.",
        ]);

        ActivityLogger::log('CRM', 'UPDATE_STAGE', "Moved lead {$lead->reference_code} from '{$oldStage}' to '{$newStage}'");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead {$lead->reference_code} moved to {$newLabel}.",
                'lead' => $lead->fresh(['assignedUser']),
            ]);
        }

        return redirect()->back()->with('success', "Lead {$lead->reference_code} moved to {$newLabel}.");
    }

    /**
     * Add a follow-up note, call log, or email record to a lead.
     */
    public function storeNote(Request $request, CrmLead $lead): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'action_type' => 'required|string|in:note,call,email,meeting',
        ]);

        $note = CrmNote::create([
            'crm_lead_id' => $lead->id,
            'user_id' => Auth::id(),
            'action_type' => $validated['action_type'],
            'content' => $validated['content'],
        ]);

        ActivityLogger::log('CRM', 'ADD_NOTE', "Recorded {$validated['action_type']} log on lead {$lead->reference_code}");

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Activity recorded successfully.',
                'note' => $note->load('user'),
            ]);
        }

        return redirect()->back()->with('success', 'Activity note logged successfully.');
    }

    /**
     * Trigger manual sync of all desk inquiries and bookings into CRM.
     */
    public function sync(CrmSyncService $syncService): RedirectResponse
    {
        $count = $syncService->syncAll();

        ActivityLogger::log('CRM', 'SYNC', "Synchronized {$count} new records into the CRM pipeline");

        return redirect()->route('admin.crm.index')->with('success', "CRM synchronized successfully. {$count} new records processed.");
    }

    /**
     * Delete a CRM lead.
     */
    public function destroy(CrmLead $lead): RedirectResponse|JsonResponse
    {
        $ref = $lead->reference_code;
        $name = $lead->client_name;

        $lead->delete();

        ActivityLogger::log('CRM', 'DELETE_LEAD', "Deleted CRM lead {$ref} ({$name})");

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead {$ref} deleted successfully.",
            ]);
        }

        return redirect()->route('admin.crm.index')->with('success', "Lead {$ref} deleted successfully.");
    }
}
