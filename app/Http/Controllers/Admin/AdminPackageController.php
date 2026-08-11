<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminPackageController extends Controller
{
    public function index(Request $request)
    {
        $query = TravelPackage::with('destination');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $packages = $query->latest()->paginate(10)->withQueryString();

        return view('admin.packages.index', compact('packages'));
    }

    public function create()
    {
        $destinations = Destination::orderBy('name')->get();

        return view('admin.packages.create', compact('destinations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'destination_id' => 'nullable|exists:destinations,id',
            'duration' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'image' => 'required|string|max:255',
            'description' => 'required|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'itinerary' => 'nullable|string',
            'available_dates' => 'nullable|string',
            'category' => 'required|string',
            'status' => 'required|in:active,draft,sold_out',
            'is_featured' => 'boolean',
        ]);

        $validated['is_featured'] = $request->has('is_featured');

        $package = TravelPackage::create($validated);

        ActivityLogger::log('Packages', 'CREATE', "Created new travel package '{$package->title}'", ['package_id' => $package->id]);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Travel package created successfully!');
    }

    public function edit(TravelPackage $package)
    {
        $destinations = Destination::orderBy('name')->get();

        return view('admin.packages.edit', compact('package', 'destinations'));
    }

    public function update(Request $request, TravelPackage $package)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'destination_id' => 'nullable|exists:destinations,id',
            'duration' => 'required|string|max:255',
            'price' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'image' => 'required|string|max:255',
            'description' => 'required|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'itinerary' => 'nullable|string',
            'available_dates' => 'nullable|string',
            'category' => 'required|string',
            'status' => 'required|in:active,draft,sold_out',
            'is_featured' => 'boolean',
        ]);

        $validated['is_featured'] = $request->has('is_featured');

        $package->update($validated);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Travel package updated successfully!');
    }

    public function destroy(TravelPackage $package)
    {
        $title = $package->title;
        $package->delete();

        ActivityLogger::log('Packages', 'DELETE', "Deleted travel package '{$title}'");

        return redirect()->route('admin.packages.index')
            ->with('success', 'Travel package deleted successfully!');
    }

    public function toggleFeatured(Request $request, TravelPackage $package)
    {
        $package->update(['is_featured' => ! $package->is_featured]);

        $statusStr = $package->is_featured ? 'Featured ★' : 'Normal ☆';
        ActivityLogger::log('Packages', 'TOGGLE_FEATURED', "Toggled package '{$package->title}' to {$statusStr}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_featured' => (bool) $package->is_featured,
                'message' => 'Package featured status updated!',
            ]);
        }

        return back()->with('success', 'Package featured status updated!');
    }
}
