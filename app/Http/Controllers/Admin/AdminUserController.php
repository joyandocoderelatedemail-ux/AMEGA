<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CustomPackageInquiry;
use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use App\Services\ClientAccountService;
use App\Services\ClientProfileService;
use App\Support\DocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AdminUserController extends Controller
{
    /**
     * Display a listing of all registered client accounts.
     */
    public function index(Request $request)
    {
        if ($request->has('sync_desk_clients')) {
            $syncedCount = ClientAccountService::syncAllDeskClients();

            return redirect()->route('admin.users.index')
                ->with('success', "Desk client accounts synchronized successfully ({$syncedCount} records processed).");
        }

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
     * Display the specified registered account details with complete client service history.
     */
    public function show(User $user)
    {
        $user->load('bookings.travelPackage');

        // 1. Ticketing History (Flight bookings & Quotations)
        $ticketBookings = TicketBooking::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->email) {
                $q->orWhere('contact_email', $user->email);
            }
        })->latest()->get();

        // 2. Visa Assistance Applications (Visit visa, e-visa, passporting)
        $visaApplications = VisaApplication::where(function ($q) use ($user) {
            if ($user->email) {
                $q->where('client_email', $user->email);
            }
            if ($user->name) {
                $q->orWhere('client_name', 'like', "%{$user->name}%");
            }
        })->with('applicants')->latest()->get();

        // 3. Immigration Counter Records (BI Client Sheets & Extensions)
        $immigrationRecords = ImmigrationClient::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->email) {
                $q->orWhere('email', $user->email);
            }
            if ($user->passport_number) {
                $q->orWhere('passport_number', $user->passport_number);
            }
        })->with(['extensions', 'documents'])->latest()->get();

        // 4. Custom Package Inquiries
        $customInquiries = CustomPackageInquiry::where(function ($q) use ($user) {
            $q->where('user_id', $user->id);
            if ($user->email) {
                $q->orWhere('client_email', $user->email);
            }
        })->latest()->get();

        // 5. SRRV Applications
        $srrvApplications = SrrvApplication::where(function ($q) use ($user) {
            if ($user->email) {
                $q->where('retiree_email', $user->email);
            }
            if ($user->name) {
                $q->orWhere('retiree_name', 'like', "%{$user->name}%");
            }
        })->latest()->get();

        // 6. CRM Leads & Opportunities
        $crmLeads = CrmLead::where(function ($q) use ($user) {
            if ($user->email) {
                $q->where('client_email', $user->email);
            }
            if ($user->phone) {
                $q->orWhere('client_phone', $user->phone);
            }
        })->latest()->get();

        return view('admin.users.show', compact(
            'user',
            'ticketBookings',
            'visaApplications',
            'immigrationRecords',
            'customInquiries',
            'srrvApplications',
            'crmLeads'
        ));
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
            'role' => 'required|in:client,agent,admin,ticketing,visa_assistance,srrv',
            // Only clients travel; staff accounts can be created without one.
            'date_of_birth' => 'required_if:role,client|nullable|date|before:today',
        ] + ClientProfileService::registrationRules());

        $role = auth()->user()->isAdmin() ? $validated['role'] : 'client';

        $client = ClientProfileService::register(Arr::except($validated, ['role']), $request, $role);

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
            'role' => 'required|in:client,agent,admin,ticketing,visa_assistance,srrv',
            'allowed_pages' => 'nullable|array',
        ] + ClientProfileService::travelProfileRules());

        if (! auth()->user()->isAdmin()) {
            $validated['role'] = $user->role;
            unset($validated['allowed_pages']);
        }

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));

        $user->update(ClientProfileService::withoutUploads($validated));
        ClientProfileService::storeUploads($request, $user);

        ActivityLogger::log('Users', 'UPDATE', "Updated profile details and permissions for '{$user->name}'");

        return redirect()->route($user->isStaff() && auth()->user()->isAdmin() ? 'admin.agents.index' : 'admin.users.index')->with('success', 'User account and permissions updated successfully!');
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
        $wasStaff = $user->isStaff() && auth()->user()->isAdmin();

        // Clear the stored identity documents too, rather than leaving a deleted
        // client's photo and ID scan orphaned on the private disk.
        foreach ([$user->profile_photo, $user->government_id_photo, $user->passport_photo] as $path) {
            if ($path && DocumentStorage::disk()->exists($path)) {
                DocumentStorage::disk()->delete($path);
            }
        }

        $user->delete();

        ActivityLogger::log('Users', 'DELETE', "Deleted {$role} account for '{$name}' ({$email})");

        return redirect()->route($wasStaff ? 'admin.agents.index' : 'admin.users.index')->with('success', 'User account deleted successfully.');
    }
}
