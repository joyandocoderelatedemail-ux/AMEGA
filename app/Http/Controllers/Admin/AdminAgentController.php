<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffRole;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\StaffActivityService;
use App\Support\DateRange;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAgentController extends Controller
{
    /**
     * An agent who works the Immigration Counter and nothing else. Not a role of its own: the
     * account is an agent allowed only that module, which lands on the counter at login.
     */
    public const IMMIGRATION_AGENT = 'immigration_agent';

    /** Staff sign in with name@this: the registration form asks only for the name. */
    public const STAFF_EMAIL_DOMAIN = 'amegatravel.com';

    /**
     * Staff roles listed on the directory, keyed by role with their display label.
     *
     * @var array<string, string>
     */
    public const STAFF_ROLES = [
        'agent' => 'Travel Agent',
        'ticketing' => 'Ticketing Officer',
        'visa_assistance' => 'Visa Assistance Officer',
        'srrv' => 'SRRV Officer',
        'cashier' => 'Cashier',
        'admin' => 'Administrator',
    ];

    /**
     * Display a listing of every staff account, optionally narrowed to one role.
     */
    public function index(Request $request)
    {
        $query = User::whereIn('role', array_keys(self::STAFF_ROLES))->latest();

        // The directory, for staff given it: names, roles and contacts. Admin accounts and what each
        // person did (their files, money and activity) are for admins only.
        if (! $request->user()->isAdmin()) {
            $query->where('role', '!=', 'admin');
        }

        if (array_key_exists($request->input('role'), self::STAFF_ROLES)) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // The date range picked on the page (App\Support\DateRange).
        DateRange::fromRequest($request)->apply($query);
        $agents = $query->paginate(15)->withQueryString();

        return view('admin.agents.index', [
            'agents' => $agents,
            'staffRoles' => self::STAFF_ROLES,
        ]);
    }

    /**
     * Show form to create a new staff account.
     */
    public function create()
    {
        return view('admin.agents.create');
    }

    /**
     * Store a new staff account. Travel Agents are limited to the pages ticked
     * for them; the other roles have fixed access.
     */
    public function store(Request $request)
    {
        $this->readRoleChoice($request);

        // The form takes only the name before the @; the company domain is added here, so the
        // account always signs in as name@amegatravel.com (a full address is still accepted).
        if ($request->filled('email_name')) {
            $name = strtolower(trim(Str::before((string) $request->input('email_name'), '@')));
            $request->merge(['email_name' => $name, 'email' => $name === '' ? null : $name.'@'.self::STAFF_EMAIL_DOMAIN]);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email_name' => ['nullable', 'regex:/^[a-z0-9._-]+$/', 'max:64'],
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'required|string|max:255',
            'password' => 'required|string|min:6',
            'role' => ['nullable', Rule::in([...array_keys(self::STAFF_ROLES), self::IMMIGRATION_AGENT])],
            'allowed_pages' => 'nullable|array',
            'allowed_pages.*' => ['string', Rule::in(array_keys(User::PAGE_PERMISSIONS))],
        ], [
            'email_name.regex' => 'Use only letters, numbers, dots, dashes and underscores before the @.',
        ]);
        unset($validated['email_name']);

        $immigrationAgent = ($validated['role'] ?? null) === self::IMMIGRATION_AGENT;

        $validated['name'] = Str::squish($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));
        $validated['role'] = $immigrationAgent ? 'agent' : ($validated['role'] ?? 'agent');
        $validated['account_category'] = 'Staff Agent';
        $validated['password'] = bcrypt($validated['password']);
        $validated['allowed_pages'] = match (true) {
            $immigrationAgent => ['immigration'],
            // Nothing ticked means no access (least privilege), the same as saving an edit with nothing ticked.
            $validated['role'] === 'agent' => $validated['allowed_pages'] ?? [],
            default => null,
        };

        $staff = User::create($validated);

        // The Immigration Agent preset sets its own access; any other agent (or cashier) takes the role and permissions chosen.
        if ($request->boolean('access_form') && ($staff->isAgent() || $staff->isCashier()) && ! $immigrationAgent) {
            $this->saveAccess($request, $staff);
        }

        $roleLabel = $immigrationAgent ? 'Immigration Agent' : self::STAFF_ROLES[$staff->role];

        ActivityLogger::log('Agents', 'CREATE', "Registered new {$roleLabel} account for '{$staff->name}' ({$staff->email})");

        return redirect()->route('admin.agents.index')->with('success', "{$roleLabel} account created successfully!");
    }

    /**
     * Show form to edit an existing Agent account & permissions.
     */
    /**
     * One staff account and what they did in the chosen period: the desk
     * files they opened, tickets issued and sold, and their activity log.
     */
    public function show(Request $request, User $agent, StaffActivityService $staffActivity): View
    {
        abort_unless($agent->isStaff(), 404);
        // What a person did (their files, the money they took, their activity) is for admins only.
        abort_unless($request->user()->isAdmin(), 403);

        $range = DateRange::fromRequest($request);

        return view('admin.agents.show', [
            'agent' => $agent,
            'range' => $range,
            'staffRoles' => self::STAFF_ROLES,
        ] + $staffActivity->forStaff($agent, $range));
    }

    /**
     * Save a staff member's role and extra permissions, and log who changed whose access (before -> after).
     * The pages they can open are kept in allowed_pages too, for anything that still reads it.
     */
    private function saveAccess(Request $request, User $staff): void
    {
        $validated = $request->validate([
            'staff_role_id' => ['nullable', 'integer', 'exists:staff_roles,id'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);

        $before = $staff->effectivePermissions();

        $staff->forceFill([
            'staff_role_id' => $validated['staff_role_id'] ?? null,
            'permissions' => Permissions::clean($validated['permissions'] ?? []),
        ])->save();
        $staff->refresh();
        $staff->forceFill(['allowed_pages' => $staff->grantedPages()])->save();

        $role = $staff->staffRole?->name ?? 'no role';
        ActivityLogger::log('Access', 'UPDATE_ACCESS', "Set the access of '{$staff->name}' to {$role}"
            .AdminStaffRoleController::diff($before, $staff->effectivePermissions()));
    }

    /**
     * The forms' one Role field: "admin", "custom" (permissions only) or "role:<id>" (a staff role). It
     * sets the kind of account (admin, cashier, or staff) and the role together.
     */
    private function readRoleChoice(Request $request): void
    {
        if (! $request->filled('access_role')) {
            return;
        }

        $choice = (string) $request->input('access_role');
        $staffRole = str_starts_with($choice, 'role:') ? StaffRole::find((int) substr($choice, 5)) : null;
        abort_if(! in_array($choice, ['admin', 'custom'], true) && ! $staffRole, 422, 'Choose a role.');

        $request->merge([
            'access_form' => $choice === 'admin' ? 0 : 1,
            'role' => match (true) {
                $choice === 'admin' => 'admin',
                $staffRole?->name === 'Cashier' => 'cashier',
                default => 'agent',
            },
            'staff_role_id' => $staffRole?->id,
        ]);
    }

    /** What the Role field shows for this person: their role, Administrator, or Custom. */
    public static function roleChoiceOf(User $staff): string
    {
        return match (true) {
            $staff->isAdmin() => 'admin',
            $staff->staff_role_id !== null => 'role:'.$staff->staff_role_id,
            default => 'custom',
        };
    }

    /** Edit any staff account: details, login, role and permissions, on one form. */
    public function edit(User $agent)
    {
        abort_unless($agent->isStaff(), 404);

        return view('admin.agents.edit', ['agent' => $agent, 'currentAccessRole' => self::roleChoiceOf($agent)]);
    }

    /**
     * Update a staff account's details, and its role and permissions.
     */
    public function update(Request $request, User $agent)
    {
        abort_unless($agent->isStaff(), 404);
        $this->readRoleChoice($request);

        // Never leave the office without an admin, and never take away your own admin access.
        $newRole = $request->input('role');
        if ($request->filled('access_role') && $agent->isAdmin() && $newRole !== 'admin') {
            if ($agent->is($request->user())) {
                return back()->withInput()->with('error', 'You cannot take away your own administrator access. Ask another admin.');
            }
            if (User::where('role', 'admin')->count() <= 1) {
                return back()->withInput()->with('error', 'This is the only administrator. Make someone else an administrator first.');
            }
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:users,email,'.$agent->id,
            'phone' => 'required|string|max:255',
            'password' => 'nullable|string|min:6',
            'allowed_pages' => 'nullable|array',
            'allowed_pages.*' => ['string', Rule::in(array_keys(User::PAGE_PERMISSIONS))],
        ]);

        $validated['name'] = Str::squish($validated['first_name'].' '.($validated['middle_name'] ?? '').' '.$validated['last_name'].' '.($validated['suffix'] ?? ''));

        if (! empty($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        } else {
            unset($validated['password']);
        }

        if ($request->filled('access_role') && $newRole !== $agent->role) {
            $from = self::STAFF_ROLES[$agent->role] ?? $agent->role;
            $agent->forceFill(['role' => $newRole])->save();
            ActivityLogger::log('Access', 'CHANGE_ACCOUNT_TYPE', "Changed '{$agent->name}' from {$from} to ".(self::STAFF_ROLES[$newRole] ?? $newRole));
        }

        if ($request->filled('access_role') && $newRole === 'admin') {
            // An administrator has every permission; a role or extras would mean nothing.
            unset($validated['allowed_pages']);
            $agent->update($validated);
            $agent->forceFill(['staff_role_id' => null, 'permissions' => null, 'allowed_pages' => null])->save();
        } elseif ($request->boolean('access_form')) {
            unset($validated['allowed_pages']);
            $agent->update($validated);
            $this->saveAccess($request, $agent);
        } else {
            $validated['allowed_pages'] = $validated['allowed_pages'] ?? [];
            $agent->update($validated);

            $pagesStr = implode(', ', $agent->allowed_pages ?? []);
            ActivityLogger::log('Agents', 'UPDATE_PERMISSIONS', "Updated page permissions for Agent '{$agent->name}': [{$pagesStr}]");
        }

        return redirect()->route('admin.agents.index')->with('success', "{$agent->name}'s account, role and permissions are saved.");
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
