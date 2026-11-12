<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Airline;
use App\Models\CustomPackageInquiry;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPackageConfiguratorController extends Controller
{
    /** Where this configurator lives. */
    protected string $routePrefix = 'admin.packages';

    protected string $viewPrefix = 'admin.packages';

    /**
     * Display the package configurator for ready-made and custom packages.
     */
    public function index(Request $request)
    {
        $destinations = Destination::orderBy('name')->get();
        $airlines = Airline::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'code', 'is_active']);
        $recentPackages = TravelPackage::with('destination')->latest()->take(6)->get();
        $recentInquiries = CustomPackageInquiry::with(['destination', 'user'])->latest()->paginate(10);

        return view($this->viewPrefix.'.configurator', compact('destinations', 'airlines', 'recentPackages', 'recentInquiries'));
    }

    /**
     * Store and save a customized package inquiry/quotation.
     */
    public function storeCustom(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'nullable|string|max:50',
            'destination_id' => 'nullable|exists:destinations,id',
            'destination_name' => 'nullable|string|max:255',
            'travel_type' => 'required|string|in:domestic,international',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date|after_or_equal:check_in_date',
            'duration' => 'nullable|string|max:255',
            'number_of_pax' => 'required|integer|min:1',
            'adults_count' => 'nullable|integer|min:1',
            'children_count' => 'nullable|integer|min:0',
            'infants_count' => 'nullable|integer|min:0',

            // Hotel & Room Configuration
            'hotel_name' => 'nullable|string|max:255',
            'preferred_hotel' => 'nullable|string|max:255',
            'has_breakfast' => 'nullable|boolean',
            'bed_config' => 'nullable|string|max:255',
            'smoking_preference' => 'nullable|string|in:non_smoking,smoking',
            'pet_friendly' => 'nullable|boolean',

            // Transportation & Logistics
            'has_transportation' => 'nullable|boolean',
            'transportation_type' => 'nullable|string|max:255',
            'special_requests' => 'nullable|string',

            // Pricing & Notes
            'estimated_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|in:PHP,USD',
            'agent_notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['has_breakfast'] = $request->boolean('has_breakfast');
        $validated['pet_friendly'] = $request->boolean('pet_friendly');
        $validated['has_transportation'] = $request->boolean('has_transportation');
        $validated['currency'] = $validated['currency'] ?? 'PHP';
        $validated['status'] = 'pending';

        // Auto-resolve destination name if destination_id was picked
        if (! empty($validated['destination_id']) && empty($validated['destination_name'])) {
            $dest = Destination::find($validated['destination_id']);
            if ($dest) {
                $validated['destination_name'] = $dest->name.($dest->country ? ", {$dest->country}" : '');
            }
        }

        $inquiry = CustomPackageInquiry::create($validated);

        ActivityLogger::log('Packages', 'CREATE', "Configured custom package inquiry '{$inquiry->reference_number}' for {$inquiry->client_name}", ['inquiry_id' => $inquiry->id]);

        return redirect()->route($this->routePrefix.'.custom-inquiries.show', $inquiry)
            ->with('success', "Custom package inquiry #{$inquiry->reference_number} configured and saved successfully!");
    }

    /**
     * Show custom package inquiry details and quotation card.
     */
    public function showCustom(CustomPackageInquiry $inquiry)
    {
        $inquiry->load(['destination', 'user']);

        return view($this->viewPrefix.'.custom-inquiry-show', compact('inquiry'));
    }

    /**
     * Update custom package inquiry status.
     */
    public function updateCustomStatus(Request $request, CustomPackageInquiry $inquiry)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,quoted,booked,cancelled',
            'agent_notes' => 'nullable|string',
            'estimated_budget' => 'nullable|numeric|min:0',
        ]);

        $inquiry->update($validated);

        ActivityLogger::log('Packages', 'UPDATE', "Updated status for custom package {$inquiry->reference_number} to {$inquiry->status}", ['inquiry_id' => $inquiry->id]);

        return back()->with('success', "Custom package inquiry #{$inquiry->reference_number} updated successfully!");
    }

    /**
     * Delete custom package inquiry.
     */
    public function destroyCustom(CustomPackageInquiry $inquiry)
    {
        $ref = $inquiry->reference_number;
        $inquiry->delete();

        ActivityLogger::log('Packages', 'DELETE', "Deleted custom package inquiry {$ref}");

        return redirect()->route('admin.packages.index', ['tab' => 'custom'])
            ->with('success', "Custom package quotation #{$ref} deleted.");
    }
}
