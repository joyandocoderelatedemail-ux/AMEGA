<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CorporateAccount;
use App\Models\CustomPackageInquiry;
use App\Models\ImmigrationClient;
use App\Models\SrrvApplication;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\ActivityLogger;
use App\Services\ClientProfileService;
use App\Services\ClientRecords;
use App\Services\CorporateAccountService;
use App\Support\DocumentStorage;
use App\Support\PhilippineLocations;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUserController extends Controller
{
    /**
     * Display a listing of all registered client accounts.
     */
    public function index(Request $request)
    {
        $query = User::where('role', 'client')->with('corporateAccount')->latest();

        if ($request->filled('category')) {
            $query->where('account_category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $query->clientSearch((string) $request->input('search'), includeCompany: true);
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    /**
     * Display the specified registered account details with complete client service history.
     */
    public function show(User $user)
    {
        $this->onlyClientsUnlessAdmin($user);

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

        return view('admin.users.show', compact(
            'user',
            'ticketBookings',
            'visaApplications',
            'immigrationRecords',
            'customInquiries',
            'srrvApplications'
        ));
    }

    /**
     * Show form to manually input a new client record.
     */
    public function create(Request $request)
    {
        // Arriving from a company's page ("Add member") starts the form on that company.
        $presetCorporate = CorporateAccount::find($request->query('corporate'));

        return view('admin.users.create', [
            'corporates' => CorporateAccount::orderBy('company_name')->get(['id', 'company_name']),
            'presetCorporate' => $presetCorporate,
        ]);
    }

    /**
     * Store a new client record in storage.
     */
    public function store(Request $request)
    {
        // This form registers travelers only. Staff accounts have their own page,
        // so a submitted role is never trusted.
        ClientProfileService::normalizeNumbers($request);

        $validated = $request->validate(ClientProfileService::withNumbersOnly(
            ClientProfileService::registrationRules() + CorporateAccountService::memberRules($request)
        ));

        $company = CorporateAccountService::resolve($validated);
        $attributes = Arr::except($validated, ['corporate_mode', 'corporate']);
        $attributes['corporate_account_id'] = $company?->id;

        $client = ClientProfileService::register($attributes, $request, 'client');
        $this->saveResidency($request, $client);

        ActivityLogger::log('Users', 'CREATE', "Created new client profile for '{$client->name}' ({$client->email})".($company ? " under corporate account '{$company->company_name}'" : ''));

        // A member added from a company's page goes back to that company.
        if ($company && $request->boolean('from_corporate')) {
            return redirect()->route('admin.corporates.show', $company)->with('success', "{$client->name} was added to {$company->company_name}.");
        }

        return redirect()->route('admin.users.index')->with('success', 'Client profile record created successfully!');
    }

    public function edit(User $user)
    {
        $this->onlyClientsUnlessAdmin($user);

        // A staff account is edited on the staff form (details, role and permissions together).
        if ($user->isStaff()) {
            return redirect()->route('admin.agents.edit', $user);
        }

        return view('admin.users.edit', [
            'user' => $user,
            'corporates' => CorporateAccount::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->onlyClientsUnlessAdmin($user);

        ClientProfileService::normalizeNumbers($request);

        $validated = $request->validate(ClientProfileService::withNumbersOnly([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'no_email' => 'nullable|boolean',
            'no_phone' => 'nullable|boolean',
            'email' => 'required_unless:no_email,1|nullable|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'required_unless:no_phone,1|nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'nationality' => 'nullable|string|max:255',
            // An account saved under an older category may keep it; a change picks from the current list.
            'account_category' => ['required', Rule::in([...User::ACCOUNT_CATEGORIES, $user->account_category])],
            'role' => 'required|in:client,agent,admin,ticketing,visa_assistance,srrv,cashier',
            'allowed_pages' => 'nullable|array',
            'allowed_pages.*' => ['string', Rule::in(array_keys(User::PAGE_PERMISSIONS))],
        ] + PhilippineLocations::rules() + ClientProfileService::travelProfileRules() + CorporateAccountService::memberRules($request, requireCompany: false)));

        if (! auth()->user()->isAdmin()) {
            $validated['role'] = $user->role;
            unset($validated['allowed_pages']);
        }

        // Staff sign in with their email, so only a client can go without one.
        if ($validated['role'] !== 'client' && ! empty($validated['no_email'])) {
            throw ValidationException::withMessages(['email' => 'Staff accounts need an email address to sign in.']);
        }

        $validated = ClientProfileService::applyContactChoices($validated, $user);

        // Companies apply to clients only; a staff account never carries one.
        if ($validated['role'] === 'client') {
            $validated['corporate_account_id'] = CorporateAccountService::resolve($validated)?->id;
        }
        $validated = Arr::except($validated, ['corporate_mode', 'corporate']);
        if ($validated['role'] !== 'client') {
            unset($validated['corporate_account_id']);
        }

        $validated['name'] = trim($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));

        $user->update(PhilippineLocations::composeAddress(ClientProfileService::withoutUploads($validated)));
        ClientProfileService::storeUploads($request, $user);
        if ($user->isClient()) {
            $this->saveResidency($request, $user);
        }

        ActivityLogger::log('Users', 'UPDATE', "Updated profile details and permissions for '{$user->name}'");

        return redirect()->route($user->isStaff() && auth()->user()->isAdmin() ? 'admin.agents.index' : 'admin.users.index')->with('success', 'User account and permissions updated successfully!');
    }

    /**
     * Remove the specified user account from storage.
     */
    public function destroy(User $user)
    {
        $this->onlyClientsUnlessAdmin($user);

        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', 'Cannot delete the main system administrator account.');
        }

        $name = $user->name;
        $email = $user->email;
        $role = ucfirst($user->role);
        $wasStaff = $user->isStaff() && auth()->user()->isAdmin();

        // A client goes with every service recorded for them, at every desk. Those can include
        // other staff members' files, so only an admin may delete a client who has any.
        $deleted = [];
        if ($user->role === 'client') {
            if (! auth()->user()->isAdmin() && ClientRecords::summary($user) !== []) {
                return back()->with('error', "{$name} has service records (bookings, visa, immigration or SRRV files). Only an admin can delete a client with service records.");
            }

            $deleted = ClientRecords::deleteAll($user, fn () => $user->delete());
        } else {
            $user->delete();
        }

        // Clear the stored identity documents too, rather than leaving a deleted
        // client's photo and ID scan orphaned on the private disk.
        foreach ([$user->profile_photo, $user->government_id_photo, $user->passport_photo, $user->stamps_photo, $user->arrival_stamp_photo] as $path) {
            if ($path && DocumentStorage::disk()->exists($path)) {
                DocumentStorage::disk()->delete($path);
            }
        }

        $records = collect($deleted)->map(fn (int $count, string $label) => "{$count} {$label}")->implode(', ');
        ActivityLogger::log('Users', 'DELETE', "Deleted {$role} account for '{$name}' ({$email})".($records ? " with their service records: {$records}" : ''));

        if ($records) {
            return redirect()->route('admin.users.index')->with('success', "Client deleted, with their service records: {$records}.");
        }

        return redirect()->route($wasStaff ? 'admin.agents.index' : 'admin.users.index')->with('success', 'User account deleted successfully.');
    }

    /**
     * The client directory is for clients. Staff with that page cannot open, change or delete a
     * staff or admin account by putting its number in the address; only an admin manages those.
     */
    private function onlyClientsUnlessAdmin(User $user): void
    {
        abort_unless(auth()->user()->isAdmin() || $user->isClient(), 404);
    }

    /**
     * The client's Visa & Residency details as staff entered or corrected them (the desks keep them
     * current as files are saved). A field left empty here clears it.
     */
    private function saveResidency(Request $request, User $client): void
    {
        if (! $request->has('residency')) {
            return;
        }

        $residency = $request->validate([
            'residency' => ['nullable', 'array'],
            'residency.srrv_number' => ['nullable', 'string', 'max:100'],
            'residency.srrv_visa_class' => ['nullable', Rule::in(['classic', 'courtesy'])],
            'residency.srrv_issued_on' => ['nullable', 'date'],
            'residency.acr_number' => ['nullable', 'string', 'max:100'],
            'residency.acr_valid_until' => ['nullable', 'date'],
            'residency.ssrn' => ['nullable', 'string', 'max:100'],
            'residency.place_of_birth' => ['nullable', 'string', 'max:255'],
            'residency.passport_place' => ['nullable', 'string', 'max:255'],
        ])['residency'] ?? [];

        $client->forceFill(collect($residency)->map(fn ($value) => filled($value) ? trim((string) $value) : null)->all())->save();
    }
}
