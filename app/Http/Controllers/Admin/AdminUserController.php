<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CorporateAccount;
use App\Models\CruiseBooking;
use App\Models\CustomPackageInquiry;
use App\Models\ImmigrationClient;
use App\Models\LocalTourBooking;
use App\Models\SrrvApplication;
use App\Models\SrrvRenewal;
use App\Models\SrrvServiceRequest;
use App\Models\TicketBooking;
use App\Models\User;
use App\Models\VisaApplication;
use App\Rules\NotAlreadyRegistered;
use App\Services\ActivityLogger;
use App\Services\ClientProfileService;
use App\Services\ClientRecords;
use App\Services\CorporateAccountService;
use App\Support\DateRange;
use App\Support\DocumentStorage;
use App\Support\PhilippineLocations;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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

        // The date range picked on the page ("Added"): it limits the list and the per-day counts.
        $range = DateRange::fromRequest($request);
        $range->apply($query);
        $users = $query->paginate(15)->withQueryString();

        $added = $range->apply(User::where('role', 'client'));

        return view('admin.users.index', [
            'users' => $users,
            'totalClients' => User::where('role', 'client')->count(),
            'addedCount' => (clone $added)->count(),
            // Clients added on each day of the range, newest day first; days with none are left out.
            'addedPerDay' => $added->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->groupBy('day')->orderByDesc('day')->pluck('total', 'day'),
            'rangeLabel' => $range->label(),
        ]);
    }

    /**
     * Display the specified registered account details with complete client service history.
     */
    /**
     * The client's files at the other desks, newest first: SRRV (applications, ID renewals, service requests),
     * Local Tours and Cruise Services. Matched by the client's account, email, or exact name.
     *
     * @return Collection<int, array{desk: string, service: string, reference: string, status: string, date: mixed, url: string}>
     */
    private function otherServices(User $user, $srrvApplications)
    {
        $email = mb_strtolower(trim((string) $user->real_email));
        $name = mb_strtolower(trim((string) $user->name));
        $match = fn ($query, string $emailColumn, string $nameColumn, bool $byAccount = false) => $query->where(function ($q) use ($email, $name, $emailColumn, $nameColumn, $byAccount, $user) {
            $q->whereRaw('1 = 0')
                ->when($byAccount, fn ($q) => $q->orWhere('user_id', $user->id))
                ->when($email !== '', fn ($q) => $q->orWhereRaw("LOWER(TRIM({$emailColumn})) = ?", [$email]))
                ->when($name !== '', fn ($q) => $q->orWhereRaw("LOWER(TRIM({$nameColumn})) = ?", [$name]));
        });
        $status = fn ($file) => $file->status_label ?: ucfirst(str_replace('_', ' ', (string) $file->status));

        return collect()
            ->concat($srrvApplications->map(fn ($file) => ['desk' => 'SRRV', 'service' => ucfirst((string) $file->visa_class).' SRRV application', 'reference' => $file->reference,
                'status' => $status($file), 'date' => $file->created_at, 'url' => route('srrv.applications.edit', $file)]))
            ->concat($match(SrrvRenewal::query(), 'retiree_email', 'retiree_name')->get()->map(fn ($file) => ['desk' => 'SRRV', 'service' => ucfirst((string) $file->visa_class).' ID Renewal',
                'reference' => $file->reference, 'status' => $status($file), 'date' => $file->created_at, 'url' => route('srrv.renewals.show', $file)]))
            ->concat($match(SrrvServiceRequest::query(), 'retiree_email', 'retiree_name')->get()->map(fn ($file) => ['desk' => 'SRRV', 'service' => $file->typeLabel(),
                'reference' => $file->reference, 'status' => $status($file), 'date' => $file->created_at, 'url' => route('srrv.requests.show', $file)]))
            ->concat($match(LocalTourBooking::query(), 'contact_email', 'lead_guest_name', true)->get()->map(fn ($file) => ['desk' => 'Local Tours',
                'service' => 'Local tour'.($file->tour ? ': '.str_replace('_', ' ', (string) $file->tour) : ''), 'reference' => $file->reference, 'status' => $status($file),
                'date' => $file->created_at, 'url' => route('local-tours.bookings.show', $file)]))
            ->concat(CruiseBooking::query()->where(fn ($q) => $q->where('user_id', $user->id)->when($name !== '', fn ($q) => $q->orWhereRaw('LOWER(TRIM(client_name)) = ?', [$name])))
                ->get()->map(fn ($file) => ['desk' => 'Cruise', 'service' => 'Cruise'.($file->cruise_line ? ': '.$file->cruise_line : ''), 'reference' => $file->reference,
                    'status' => $status($file), 'date' => $file->created_at, 'url' => route('cruise-services.bookings.show', $file)]))
            ->sortByDesc('date')->values();
    }

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

        $otherServices = $this->otherServices($user, $srrvApplications);

        return view('admin.users.show', compact(
            'user',
            'ticketBookings',
            'visaApplications',
            'immigrationRecords',
            'customInquiries',
            'srrvApplications',
            'otherServices'
        ));
    }

    /**
     * Show form to manually input a new client record.
     */
    public function create(Request $request)
    {
        // Arriving from a company's page ("Add member") starts the form on that company.
        $presetCorporate = CorporateAccount::find($request->query('corporate'));

        // "Register this client" on Admin > Bookings: the form starts from what the customer typed on the website.
        $websiteBooking = Booking::whereNull('user_id')->find($request->query('website_booking'));
        $prefill = [];
        if ($websiteBooking) {
            // Already registered (the same email or number): the booking goes on that record instead.
            $existing = collect([
                (new NotAlreadyRegistered(NotAlreadyRegistered::EMAIL))->existing($websiteBooking->customer_email),
                (new NotAlreadyRegistered(NotAlreadyRegistered::PHONE))->existing($websiteBooking->customer_phone),
            ])->first(fn (?User $user) => $user?->role === 'client');

            if ($existing) {
                $websiteBooking->update(['user_id' => $existing->id]);

                return redirect()->route('admin.bookings.index')
                    ->with('success', "{$existing->name} is already registered, so booking {$websiteBooking->booking_reference} is now on their record. You can book it in Ticketing.");
            }

            $name = trim($websiteBooking->customer_name);
            $prefill = [
                'first_name' => str_contains($name, ' ') ? Str::beforeLast($name, ' ') : $name,
                'last_name' => str_contains($name, ' ') ? Str::afterLast($name, ' ') : '',
                'email' => $websiteBooking->customer_email,
                'phone' => substr(preg_replace('/\D/', '', (string) $websiteBooking->customer_phone), 0, 15),
            ];
        }

        return view('admin.users.create', [
            'corporates' => CorporateAccount::orderBy('company_name')->get(['id', 'company_name', 'kind']),
            'presetCorporate' => $presetCorporate,
            'websiteBooking' => $websiteBooking,
            'prefill' => $prefill,
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
        ), CorporateAccountService::memberMessages($request));

        $company = CorporateAccountService::resolve($validated);
        $attributes = Arr::except($validated, ['corporate_mode', 'corporate']);
        $attributes['corporate_account_id'] = $company?->id;

        $client = ClientProfileService::register($attributes, $request, 'client');
        $this->saveResidency($request, $client);

        ActivityLogger::log('Users', 'CREATE', "Created new client profile for '{$client->name}' ({$client->email})".($company ? " under corporate account '{$company->company_name}'" : ''));

        // Registered from a website booking: the booking goes on this client's record, ready for Ticketing.
        if ($request->filled('website_booking') && ($websiteBooking = Booking::whereNull('user_id')->find($request->integer('website_booking')))) {
            $websiteBooking->update(['user_id' => $client->id]);

            return redirect()->route('admin.bookings.index')
                ->with('success', "{$client->name} is registered. You can now book {$websiteBooking->booking_reference} in Ticketing.");
        }

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
            'corporates' => CorporateAccount::orderBy('company_name')->get(['id', 'company_name', 'kind']),
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

        $validated = ClientProfileService::normalizeFamily(ClientProfileService::applyContactChoices($validated, $user));

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
        foreach ([$user->profile_photo, $user->government_id_photo, ...$user->passportScanPaths(), $user->stamps_photo, $user->arrival_stamp_photo] as $path) {
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
            'residency.pra_id_valid_until' => ['nullable', 'date'],
            'residency.acr_number' => ['nullable', 'string', 'max:100'],
            'residency.acr_valid_until' => ['nullable', 'date'],
            'residency.ssrn' => ['nullable', 'string', 'max:100'],
            'residency.place_of_birth' => ['nullable', 'string', 'max:255'],
            'residency.passport_place' => ['nullable', 'string', 'max:255'],
        ])['residency'] ?? [];

        $client->forceFill(collect($residency)->map(fn ($value) => filled($value) ? trim((string) $value) : null)->all())->save();
    }
}
