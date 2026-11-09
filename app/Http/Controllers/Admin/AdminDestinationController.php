<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Services\ActivityLogger;
use App\Support\DestinationImageStorage;
use Illuminate\Http\Request;
use RuntimeException;

class AdminDestinationController extends Controller
{
    private const PHOTO_MESSAGES = [
        'image_file.required' => 'Choose a photo for the destination.',
        'image_file.image' => 'The photo must be an image file.',
        'image_file.mimes' => 'Use a JPG, PNG or WebP photo.',
        'image_file.max' => 'The photo is larger than 5 MB.',
        'image_file.uploaded' => 'The photo could not be uploaded. Check that it is under 5 MB and try again.',
    ];

    /**
     * The photo upload: an image, up to 5 MB. A new destination must have one ('required'); an existing one
     * keeps its current photo unless a new file is sent ('nullable').
     *
     * @return list<string>
     */
    private function photoRules(string $presence): array
    {
        return [$presence, 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    /**
     * The form sends a currency and an amount; the website shows one text, e.g. "₱12,000" or "$2,499".
     * A plain starting_price (older callers) is still accepted as written.
     */
    private function composePrice(Request $request): void
    {
        if (! $request->filled('price_amount')) {
            return;
        }

        $request->validate([
            'price_currency' => 'required|in:PHP,USD',
            'price_amount' => 'numeric|min:0|max:999999999',
        ]);

        $amount = (float) $request->input('price_amount');
        $request->merge([
            'starting_price' => ($request->input('price_currency') === 'USD' ? '$' : '₱')
                .number_format($amount, floor($amount) == $amount ? 0 : 2),
        ]);
    }

    public function index()
    {
        $destinations = Destination::orderBy('name', 'asc')->paginate(15);

        return view('admin.destinations.index', compact('destinations'));
    }

    public function create()
    {
        return view('admin.destinations.create');
    }

    public function store(Request $request)
    {
        $this->composePrice($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'starting_price' => 'required|string|max:255',
            'description' => 'required|string',
            'image_file' => $this->photoRules('required'),
            'is_featured' => 'boolean',
        ], self::PHOTO_MESSAGES);

        $validated['is_featured'] = $request->has('is_featured');

        try {
            $validated['image'] = DestinationImageStorage::store($request->file('image_file'));
        } catch (RuntimeException) {
            return back()->withInput()->withErrors(['image_file' => 'The photo could not be uploaded. Please try again.']);
        }

        unset($validated['image_file']);

        $destination = Destination::create($validated);

        ActivityLogger::log('Destinations', 'CREATE', "Created new destination '{$destination->name}'");

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination created successfully!');
    }

    public function edit(Destination $destination)
    {
        return view('admin.destinations.edit', compact('destination'));
    }

    public function update(Request $request, Destination $destination)
    {
        $this->composePrice($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'location' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'starting_price' => 'required|string|max:255',
            'description' => 'required|string',
            'image_file' => $this->photoRules('nullable'),
            'is_featured' => 'boolean',
        ], self::PHOTO_MESSAGES);

        $validated['is_featured'] = $request->has('is_featured');
        $previous = $destination->image;

        if ($request->hasFile('image_file')) {
            try {
                $validated['image'] = DestinationImageStorage::store($request->file('image_file'));
            } catch (RuntimeException) {
                return back()->withInput()->withErrors(['image_file' => 'The photo could not be uploaded. Please try again.']);
            }
        }

        unset($validated['image_file']);

        $destination->update($validated);

        // The old photo goes only once the new one is saved, and only if the admin uploaded it.
        if (isset($validated['image']) && $validated['image'] !== $previous) {
            DestinationImageStorage::delete($previous);
        }

        ActivityLogger::log('Destinations', 'UPDATE', "Updated destination details for '{$destination->name}'");

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination updated successfully!');
    }

    public function destroy(Destination $destination)
    {
        $name = $destination->name;
        $image = $destination->image;
        $destination->delete();

        DestinationImageStorage::delete($image);

        ActivityLogger::log('Destinations', 'DELETE', "Deleted destination '{$name}'");

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination deleted successfully!');
    }

    public function toggleFeatured(Request $request, Destination $destination)
    {
        $destination->update(['is_featured' => ! $destination->is_featured]);

        $statusStr = $destination->is_featured ? 'Featured ★' : 'Normal ☆';
        ActivityLogger::log('Destinations', 'TOGGLE_FEATURED', "Toggled destination '{$destination->name}' to {$statusStr}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_featured' => (bool) $destination->is_featured,
                'message' => 'Destination featured status updated!',
            ]);
        }

        return back()->with('success', 'Destination featured status updated!');
    }
}
