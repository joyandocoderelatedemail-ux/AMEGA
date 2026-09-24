<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomPackageInquiry;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use App\Support\PackageImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPackageConfiguratorController extends Controller
{
    /**
     * Display the package configurator for ready-made and custom packages.
     */
    public function index(Request $request)
    {
        $destinations = Destination::orderBy('name')->get();
        $recentPackages = TravelPackage::with('destination')->latest()->take(6)->get();
        $recentInquiries = CustomPackageInquiry::with(['destination', 'user'])->latest()->paginate(10);

        return view('admin.packages.configurator', compact('destinations', 'recentPackages', 'recentInquiries'));
    }

    /**
     * Store and save a new ready-made package template into the catalog.
     */
    public function storeReadyMade(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'destination_id' => 'nullable|exists:destinations,id',
            'category' => 'required|string|in:domestic,short_haul,long_haul',
            'duration' => 'required|string|max:255',
            'price_amount' => 'required|numeric|min:0',
            'price_currency' => 'nullable|in:PHP,USD',
            'rating' => 'nullable|integer|min:1|max:5',
            'status' => 'required|in:active,draft,sold_out',
            'is_featured' => 'nullable|boolean',
            'description' => 'nullable|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'itinerary' => 'nullable|string',
            'available_dates' => 'nullable|string',
            'image_file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image' => 'nullable|string|max:255',

            // Hotel & Room Configuration
            'hotel_name' => 'nullable|string|max:255',
            'preferred_hotel' => 'nullable|string|max:255',
            'has_breakfast' => 'nullable|boolean',
            'bed_config' => 'nullable|string|max:255',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date|after_or_equal:check_in_date',
            'smoking_preference' => 'nullable|string|in:non_smoking,smoking',
            'pet_friendly' => 'nullable|boolean',

            // Transportation & Logistics
            'has_transportation' => 'nullable|boolean',
            'transportation_type' => 'nullable|string|max:255',
            'number_of_pax' => 'required|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        $validated['package_type'] = 'ready_made';
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['has_breakfast'] = $request->boolean('has_breakfast');
        $validated['pet_friendly'] = $request->boolean('pet_friendly');
        $validated['has_transportation'] = $request->boolean('has_transportation');
        $validated['rating'] = $validated['rating'] ?? 5;
        $validated['price_currency'] = $validated['price_currency'] ?? 'PHP';

        // Auto-generate description if blank
        if (empty($validated['description'])) {
            $hotelDesc = $validated['hotel_name'] ? " with stay at {$validated['hotel_name']}" : '';
            $paxDesc = " for {$validated['number_of_pax']} pax";
            $validated['description'] = "All-inclusive curated ready-made package: {$validated['title']}{$hotelDesc}{$paxDesc}.";
        }

        // Resolve Image
        if ($request->hasFile('image_file')) {
            $validated['image'] = PackageImageStorage::storeUploadedFile($request->file('image_file'));
        } elseif (empty($validated['image'])) {
            // Fallback placeholder image
            $validated['image'] = 'images/packages/default-package.jpg';
        }
        unset($validated['image_file']);

        $package = TravelPackage::create($validated);

        ActivityLogger::log('Packages', 'CREATE', "Configured and saved ready-made package '{$package->title}'", ['package_id' => $package->id]);

        return redirect()->route('admin.packages.configurator', ['tab' => 'ready-made'])
            ->with('success', "Ready-made package '{$package->title}' has been saved to the active catalog and is now selectable in Step 3 of the Ticket Wizard!");
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

        return redirect()->route('admin.packages.custom-inquiries.show', $inquiry)
            ->with('success', "Custom package inquiry #{$inquiry->reference_number} configured and saved successfully!");
    }

    /**
     * Show custom package inquiry details and quotation card.
     */
    public function showCustom(CustomPackageInquiry $inquiry)
    {
        $inquiry->load(['destination', 'user']);

        return view('admin.packages.custom-inquiry-show', compact('inquiry'));
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

        return redirect()->route('admin.packages.configurator', ['tab' => 'inquiries'])
            ->with('success', "Custom package quotation #{$ref} deleted.");
    }
}
