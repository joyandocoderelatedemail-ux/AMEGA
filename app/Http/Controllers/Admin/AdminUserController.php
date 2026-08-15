<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminUserController extends Controller
{
    /**
     * Display a listing of all registered client accounts.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'client')->latest();

        if ($request->filled('category')) {
            $query->where('account_category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('passport_number', 'like', "%{$search}%")
                    ->orWhere('government_id_number', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Display the specified registered account details.
     */
    public function show(User $user)
    {
        $user->load('bookings');

        return view('admin.users.show', compact('user'));
    }

    /**
     * Show form to manually input a new client record.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Store a new client record in storage.
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
            'address' => 'required|string|max:500',
            'nationality' => 'required|string|max:255',
            'account_category' => 'required|string|max:255',
            'passport_number' => 'nullable|string|max:255',
            'passport_expiry_date' => 'nullable|date',
            'government_id_number' => 'nullable|string|max:255',
            'emergency_contact_person' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'role' => 'required|in:client,agent,admin',
        ]);

        if (! auth()->user()->isAdmin()) {
            $validated['role'] = 'client';
        }

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));
        $validated['password'] = bcrypt(Str::random(16));

        $client = User::create($validated);

        ActivityLogger::log('Users', 'CREATE', "Created new client profile for '{$client->name}' ({$client->email})");

        return redirect()->route('admin.users.index')->with('success', 'Client profile record created successfully!');
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'nationality' => 'nullable|string|max:255',
            'account_category' => 'required|string|max:255',
            'role' => 'required|in:client,agent,admin',
            'allowed_pages' => 'nullable|array',
        ]);

        if (! auth()->user()->isAdmin()) {
            $validated['role'] = $user->role;
            unset($validated['allowed_pages']);
        }

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));

        $user->update($validated);

        ActivityLogger::log('Users', 'UPDATE', "Updated profile details and permissions for '{$user->name}'");

        return redirect()->route('admin.users.index')->with('success', 'User account and permissions updated successfully!');
    }

    /**
     * Remove the specified user account from storage.
     */
    public function destroy(User $user)
    {
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the main system administrator account.');
        }

        $name = $user->name;
        $email = $user->email;
        $role = ucfirst($user->role);
        $user->delete();

        ActivityLogger::log('Users', 'DELETE', "Deleted {$role} account for '{$name}' ({$email})");

        return redirect()->route('admin.users.index')->with('success', 'User account deleted successfully.');
    }
}
