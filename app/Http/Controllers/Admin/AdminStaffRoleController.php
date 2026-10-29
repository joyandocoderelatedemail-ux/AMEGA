<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaffRole;
use App\Services\ActivityLogger;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Users > Roles & Permissions (admins only): the named sets of permissions staff are given. The roles
 * every install starts with can be edited but not deleted; a role still given to someone cannot be
 * deleted either. Every change is logged with what was added and taken away.
 */
class AdminStaffRoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', ['roles' => StaffRole::withCount('users')->orderByDesc('is_system')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => new StaffRole(['permissions' => []])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = StaffRole::create($this->validated($request));

        ActivityLogger::log('Access', 'CREATE_ROLE', "Created the role '{$role->name}' with: ".implode(', ', $role->permissionKeys()));

        return redirect()->route('admin.roles.index')->with('success', "The role {$role->name} is saved.");
    }

    public function edit(StaffRole $role): View
    {
        return view('admin.roles.form', ['role' => $role]);
    }

    public function update(Request $request, StaffRole $role): RedirectResponse
    {
        $before = $role->permissionKeys();
        $role->update($this->validated($request, $role));
        $after = $role->permissionKeys();

        ActivityLogger::log('Access', 'UPDATE_ROLE', "Changed the role '{$role->name}'".self::diff($before, $after).' (applies to '.$role->users()->count().' staff)');

        return redirect()->route('admin.roles.index')->with('success', "The role {$role->name} is saved. Everyone with it has the new permissions now.");
    }

    public function destroy(StaffRole $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->with('error', 'The roles every install starts with can be edited, not deleted.');
        }
        if ($role->users()->exists()) {
            return back()->with('error', "Someone still has the role {$role->name}. Give them another role first.");
        }

        $role->delete();
        ActivityLogger::log('Access', 'DELETE_ROLE', "Deleted the role '{$role->name}'");

        return redirect()->route('admin.roles.index')->with('success', "The role {$role->name} is deleted.");
    }

    /** "; added X; removed Y", or nothing when unchanged. */
    public static function diff(array $before, array $after): string
    {
        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        return ($added ? '; added '.implode(', ', $added) : '').($removed ? '; removed '.implode(', ', $removed) : '');
    }

    /** @return array{name: string, description: ?string, permissions: list<string>} */
    private function validated(Request $request, ?StaffRole $role = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('staff_roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permissions::all())],
        ]);

        return [
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'permissions' => Permissions::clean($validated['permissions'] ?? []),
        ];
    }
}
