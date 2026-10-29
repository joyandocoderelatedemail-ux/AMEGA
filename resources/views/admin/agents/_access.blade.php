{{--
    A staff member's access: a role (a named set of permissions, kept under Users > Roles & Permissions)
    plus any extra permissions of their own. Expects $agent (null on create).
    An account still set up the old way (ticked pages) opens with its current permissions as extras,
    so saving keeps exactly what it has.
--}}
@php
    $roles = \App\Models\StaffRole::orderByDesc('is_system')->orderBy('name')->get();
    $legacy = $agent && $agent->staff_role_id === null && $agent->getAttribute('permissions') === null;
    $currentRole = old('staff_role_id', $agent?->staff_role_id);
    $currentExtra = old('permissions', $legacy ? $agent->effectivePermissions() : ($agent?->getAttribute('permissions') ?? []));
@endphp

<input type="hidden" name="access_form" value="1">

<div class="space-y-4" x-data="{ roleId: @js((string) ($currentRole ?? '')), roles: @js($roles->mapWithKeys(fn ($r) => [(string) $r->id => ['description' => $r->description, 'count' => count($r->permissionKeys())]])) }">
    @if ($legacy)
        <div class="flex items-start gap-2.5 p-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 text-xs text-amber-900">
            <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
            <p>This account was set up before roles. Its current access is ticked below as extra permissions, so saving keeps it exactly. Pick a role to simplify it.</p>
        </div>
    @endif

    @if ($withRole ?? true)
    <label class="block">
        <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Role</span>
        <select name="staff_role_id" x-model="roleId"
                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <option value="">No role (only the extra permissions below)</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((string) $currentRole === (string) $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        <span class="block text-[11px] text-dark/50 mt-1" x-show="roleId && roles[roleId]" x-text="roles[roleId] ? roles[roleId].description + ' (' + roles[roleId].count + ' permissions)' : ''"></span>
        @error('staff_role_id') <span class="block text-xs text-rose-600 mt-1">{{ $message }}</span> @enderror
    </label>
    @endif

    <details class="group rounded-xl border border-gray-200 bg-white" @if ($legacy || ! empty($currentExtra) || $errors->has('permissions*') || ! ($withRole ?? true)) open @endif>
        <summary class="flex items-center justify-between px-4 py-3 cursor-pointer list-none">
            <span>
                <span class="block text-xs font-bold text-dark">Extra permissions</span>
                <span class="block text-[11px] text-dark/50">On top of the role, for this person only.</span>
            </span>
            <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 group-open:rotate-180 transition-transform"></i>
        </summary>
        <div class="px-4 pb-4">
            @include('admin.roles._grid', ['selected' => $currentExtra])
        </div>
    </details>

    <p class="text-[11px] text-dark/50">Nothing chosen means no access at all. <a href="{{ route('admin.roles.index') }}" class="font-semibold text-emerald-700 hover:underline">Manage roles</a></p>
</div>
