<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminDestinationController extends Controller
{
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'starting_price' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|string',
            'is_featured' => 'boolean',
        ]);

        $validated['is_featured'] = $request->has('is_featured');

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'type' => 'required|in:domestic,international',
            'starting_price' => 'required|string|max:255',
            'description' => 'required|string',
            'image' => 'required|string',
            'is_featured' => 'boolean',
        ]);

        $validated['is_featured'] = $request->has('is_featured');

        $destination->update($validated);

        ActivityLogger::log('Destinations', 'UPDATE', "Updated destination details for '{$destination->name}'");

        return redirect()->route('admin.destinations.index')
            ->with('success', 'Destination updated successfully!');
    }

    public function destroy(Destination $destination)
    {
        $name = $destination->name;
        $destination->delete();

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
