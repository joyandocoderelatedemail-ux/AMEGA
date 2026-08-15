<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class AdminAgentController extends Controller
{
    /**
     * Display a listing of all registered Travel Agent staff accounts.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'agent')->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $agents = $query->paginate(15)->withQueryString();

        return view('admin.agents.index', compact('agents'));
    }

    /**
     * Show form to create a new Travel Agent account.
     */
    public function create()
    {
        return view('admin.agents.create');
    }

    /**
     * Store a new Travel Agent account in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'allowed_pages' => 'nullable|array',
        ]);

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));
        $validated['role'] = 'agent';
        $validated['account_category'] = 'Staff Agent';
        $validated['password'] = bcrypt($validated['password']);
        $validated['allowed_pages'] = $validated['allowed_pages'] ?? ['dashboard', 'bookings', 'inquiries', 'users', 'packages', 'destinations'];

        $agent = User::create($validated);

        ActivityLogger::log('Agents', 'CREATE', "Registered new Travel Agent account for '{$agent->name}' ({$agent->email})");

        return redirect()->route('admin.agents.index')->with('success', 'Travel Agent account created successfully!');
    }

    /**
     * Show form to edit an existing Agent account & permissions.
     */
    public function edit(User $agent)
    {
        return view('admin.agents.edit', compact('agent'));
    }

    /**
     * Update Travel Agent account details & page permissions.
     */
    public function update(Request $request, User $agent)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,'.$agent->id,
            'phone' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'allowed_pages' => 'nullable|array',
        ]);

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));

        if (! empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['allowed_pages'] = $validated['allowed_pages'] ?? [];

        $agent->update($validated);

        $pagesStr = implode(', ', $agent->allowed_pages ?? []);
        ActivityLogger::log('Agents', 'UPDATE_PERMISSIONS', "Updated page permissions for Agent '{$agent->name}': [{$pagesStr}]");

        return redirect()->route('admin.agents.index')->with('success', 'Travel Agent account and permissions updated successfully!');
    }

    /**
     * Remove the specified Travel Agent account.
     */
    public function destroy(User $agent)
    {
        if ($agent->isAdmin()) {
            return back()->with('error', 'Cannot delete an administrator account from agent directory.');
        }

        $name = $agent->name;
        $agent->delete();

        ActivityLogger::log('Agents', 'DELETE', "Deleted Travel Agent account for '{$name}'");

        return redirect()->route('admin.agents.index')->with('success', 'Travel Agent account deleted successfully.');
    }
}
