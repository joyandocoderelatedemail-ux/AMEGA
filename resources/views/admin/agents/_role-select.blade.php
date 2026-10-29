{{-- The one Role choice on the staff forms: a staff role, Custom (permissions only) or Administrator. Expects $current ("", "admin", "custom" or "role:<id>"). --}}
            @php $staffRoles = \App\Models\StaffRole::orderByDesc('is_system')->orderBy('name')->get(); @endphp
            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200">
                <label for="access_role" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Role *</label>
                <select id="access_role" name="access_role" x-model="accessRole" required class="w-full sm:w-1/2 px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="" disabled>Choose a role</option>
                    @foreach ($staffRoles as $staffRole)
                        <option value="role:{{ $staffRole->id }}" @selected(old('access_role', $current) === 'role:'.$staffRole->id)>{{ $staffRole->name }}</option>
                    @endforeach
                    <option value="custom" @selected(old('access_role', $current) === 'custom')>Custom (choose the permissions below)</option>
                    <option value="admin" @selected(old('access_role', $current) === 'admin')>Administrator (everything)</option>
                </select>
                @foreach ($staffRoles as $staffRole)
                    <p class="text-[11px] text-dark/50 mt-1" x-show="accessRole === 'role:{{ $staffRole->id }}'" x-cloak>{{ $staffRole->description }}</p>
                @endforeach
                <p class="text-[11px] text-dark/50 mt-1" x-show="accessRole === 'admin'" x-cloak>Can open and change everything, approve every desk, and add or remove staff.</p>
                <p class="text-[11px] text-dark/50 mt-1" x-show="accessRole === 'custom'" x-cloak>No role: only the permissions ticked below.</p>
                @error('access_role') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
            </div>
