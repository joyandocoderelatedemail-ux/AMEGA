<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('order', 'asc')->get();

        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'required|string',
            'full_description' => 'nullable|string',
            'icon' => 'required|string',
            'image' => 'nullable|string',
            'badge' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $service = Service::create($validated);

        ActivityLogger::log('Services', 'CREATE', "Created core service offer '{$service->title}'");

        return redirect()->route('admin.services.index')->with('success', 'Service created successfully!');
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'short_description' => 'required|string',
            'full_description' => 'nullable|string',
            'icon' => 'required|string',
            'image' => 'nullable|string',
            'badge' => 'nullable|string',
            'order' => 'nullable|integer',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $service->update($validated);

        ActivityLogger::log('Services', 'UPDATE', "Updated core service details for '{$service->title}'");

        return redirect()->route('admin.services.index')->with('success', 'Service updated successfully!');
    }

    public function toggleStatus(Request $request, Service $service)
    {
        $service->update(['is_active' => ! $service->is_active]);

        $statusStr = $service->is_active ? 'Enabled ●' : 'Disabled ○';
        ActivityLogger::log('Services', 'TOGGLE_STATUS', "Toggled service '{$service->title}' to {$statusStr}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool) $service->is_active,
                'message' => $service->is_active ? 'Service enabled!' : 'Service disabled!',
            ]);
        }

        $statusMessage = $service->is_active ? 'Service enabled!' : 'Service disabled!';

        return back()->with('success', $statusMessage);
    }

    public function destroy(Service $service)
    {
        $title = $service->title;
        $service->delete();

        ActivityLogger::log('Services', 'DELETE', "Deleted service '{$title}'");

        return redirect()->route('admin.services.index')->with('success', 'Service deleted successfully!');
    }
}
