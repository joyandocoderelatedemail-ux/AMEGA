<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\ActivityLogger;
use App\Support\PackageImageStorage;
use Illuminate\Http\Request;
use RuntimeException;

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
            // A numeric amount is preferred; the legacy display string is still
            // accepted so older forms and imports keep working.
            'price' => 'nullable|string|max:255|required_without:price_amount',
            'price_amount' => 'nullable|numeric|min:0|required_without:price',
            'price_currency' => 'nullable|in:PHP,USD',
            'rating' => 'required|integer|min:1|max:5',
            // Either an uploaded photo or a hand-typed path is enough.
            'image' => 'nullable|string|max:255|required_without:image_file',
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'required_without:image'],
            'description' => 'required|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'itinerary' => 'nullable|string',
            'available_dates' => 'nullable|string',
            'category' => 'required|string',
            'status' => 'required|in:active,draft,sold_out',
            'is_featured' => 'boolean',
            'package_type' => 'nullable|string|in:ready_made,custom',
            'hotel_name' => 'nullable|string|max:255',
            'preferred_hotel' => 'nullable|string|max:255',
            'has_breakfast' => 'nullable|boolean',
            'bed_config' => 'nullable|string|max:255',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date',
            'smoking_preference' => 'nullable|string|in:non_smoking,smoking',
            'pet_friendly' => 'nullable|boolean',
            'has_transportation' => 'nullable|boolean',
            'transportation_type' => 'nullable|string|max:255',
            'number_of_pax' => 'nullable|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        $validated['is_featured'] = $request->has('is_featured');
        $validated['has_breakfast'] = $request->boolean('has_breakfast');
        $validated['pet_friendly'] = $request->boolean('pet_friendly');
        $validated['has_transportation'] = $request->boolean('has_transportation');
        $validated['package_type'] = $validated['package_type'] ?? 'ready_made';

        $image = $this->resolveImagePath($request);

        if ($image === null) {
            return back()->withInput()->with('error', 'The package photo could not be uploaded. Please try again.');
        }

        $validated['image'] = $image;

        unset($validated['image_file']);

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
            // A numeric amount is preferred; the legacy display string is still
            // accepted so older forms and imports keep working.
            'price' => 'nullable|string|max:255|required_without:price_amount',
            'price_amount' => 'nullable|numeric|min:0|required_without:price',
            'price_currency' => 'nullable|in:PHP,USD',
            'rating' => 'required|integer|min:1|max:5',
            // Both are optional here: omitting them keeps the current photo.
            'image' => 'nullable|string|max:255',
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'description' => 'required|string',
            'inclusions' => 'nullable|string',
            'exclusions' => 'nullable|string',
            'itinerary' => 'nullable|string',
            'available_dates' => 'nullable|string',
            'category' => 'required|string',
            'status' => 'required|in:active,draft,sold_out',
            'is_featured' => 'boolean',
            'package_type' => 'nullable|string|in:ready_made,custom',
            'hotel_name' => 'nullable|string|max:255',
            'preferred_hotel' => 'nullable|string|max:255',
            'has_breakfast' => 'nullable|boolean',
            'bed_config' => 'nullable|string|max:255',
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date',
            'smoking_preference' => 'nullable|string|in:non_smoking,smoking',
            'pet_friendly' => 'nullable|boolean',
            'has_transportation' => 'nullable|boolean',
            'transportation_type' => 'nullable|string|max:255',
            'number_of_pax' => 'nullable|integer|min:1',
            'special_requests' => 'nullable|string',
        ]);

        $validated['is_featured'] = $request->has('is_featured');
        $validated['has_breakfast'] = $request->boolean('has_breakfast');
        $validated['pet_friendly'] = $request->boolean('pet_friendly');
        $validated['has_transportation'] = $request->boolean('has_transportation');

        $image = $this->resolveImagePath($request, $package->image);

        if ($image === null) {
            return back()->withInput()->with('error', 'The package photo could not be uploaded. Please try again.');
        }

        $validated['image'] = $image;

        unset($validated['image_file']);

        $package->update($validated);

        ActivityLogger::log('Packages', 'UPDATE', "Updated travel package '{$package->title}'", ['package_id' => $package->id]);

        return redirect()->route('admin.packages.index')
            ->with('success', 'Travel package updated successfully!');
    }

    /**
     * Work out which image path a package should be saved with.
     *
     * A freshly uploaded photo wins over a hand-typed path, which in turn wins
     * over whatever the package already had. Replacing a photo deletes the file
     * it superseded, so the uploads folder does not fill up with orphans.
     *
     * @return string|null the path to persist, or null when the upload failed
     */
    protected function resolveImagePath(Request $request, ?string $current = null): ?string
    {
        $typed = trim((string) $request->input('image', ''));

        if ($request->hasFile('image_file')) {
            try {
                $path = PackageImageStorage::store($request->file('image_file'));
            } catch (RuntimeException) {
                return null;
            }
        } elseif ($typed !== '' && $typed !== $current) {
            $path = $typed;
        } else {
            return $current;
        }

        if ($path !== $current) {
            PackageImageStorage::delete($current);
        }

        return $path;
    }

    public function destroy(TravelPackage $package)
    {
        $title = $package->title;
        $image = $package->image;

        $package->delete();

        // Drop the photo too, but only when this app uploaded it.
        PackageImageStorage::delete($image);

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
