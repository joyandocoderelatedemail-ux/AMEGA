<!-- Full Legal Name -->
<div class="space-y-3">
    <span class="text-xs font-bold uppercase tracking-wider text-primary block">Full Legal Name *</span>
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
        <div class="sm:col-span-4">
            <label for="first_name" class="block text-[11px] font-bold text-dark/70 mb-1">First / Given Name *</label>
            <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $prefill['first_name'] ?? '') }}" required
                   class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="Juan">
        </div>
        <div class="sm:col-span-3">
            <label for="middle_name" class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
            <input id="middle_name" type="text" name="middle_name" value="{{ old('middle_name') }}"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="Dela Cruz">
        </div>
        <div class="sm:col-span-3">
            <label for="last_name" class="block text-[11px] font-bold text-dark/70 mb-1">Last Name / Surname *</label>
            <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $prefill['last_name'] ?? '') }}" required
                   class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                   placeholder="Santos">
        </div>
        <div class="sm:col-span-2">
            <label for="suffix" class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
            <select id="suffix" name="suffix" class="w-full px-2.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="">None</option>
                <option value="Jr." @selected(old('suffix') === 'Jr.')>Jr.</option>
                <option value="Sr." @selected(old('suffix') === 'Sr.')>Sr.</option>
                <option value="III" @selected(old('suffix') === 'III')>III</option>
            </select>
        </div>
    </div>
</div>

<!-- Contact & Address -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
        <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Email Address *</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="juan@example.com">
    </div>
    <div>
        <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="+63 912 345 6789">
    </div>
    <div>
        <label for="nationality" class="block text-[11px] font-bold text-dark/70 mb-1">Citizenship / Nationality *</label>
        <input id="nationality" type="text" name="nationality" value="{{ old('nationality', 'Filipino') }}" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="Filipino">
    </div>
</div>

<div>
    <label for="address" class="block text-[11px] font-bold text-dark/70 mb-1">Residential Address *</label>
    <input id="address" type="text" name="address" value="{{ old('address') }}" required
           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
           placeholder="House/Unit No., Street, Barangay, City, Province">
</div>
