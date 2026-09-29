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
                   class="w-full px-3.5 py-2.5 rounded-xl bg-white border {{ $errors->has('last_name') ? 'border-rose-400 bg-rose-50/60' : 'border-gray-200' }} text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
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

<p data-duplicate-warning="name" hidden role="status" class="mt-1.5 text-[11px] font-semibold text-amber-700">
            <span class="flex items-start gap-1.5">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-px"></i>
                <span data-duplicate-text></span>
            </span>
        </p>

<!-- Contact & Address -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div>
        <label for="email" class="block text-[11px] font-bold text-dark/70 mb-1">Email Address *</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-white border {{ $errors->has('email') ? 'border-rose-400 bg-rose-50/60' : 'border-gray-200' }} text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="juan@example.com">
        <p data-duplicate-warning="email" hidden role="status" class="mt-1.5 text-[11px] font-semibold text-amber-700">
            <span class="flex items-start gap-1.5">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-px"></i>
                <span data-duplicate-text></span>
            </span>
        </p>
    </div>
    <div>
        <label for="phone" class="block text-[11px] font-bold text-dark/70 mb-1">Phone Number *</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone') }}" required
               class="w-full px-3.5 py-2.5 rounded-xl bg-white border {{ $errors->has('phone') ? 'border-rose-400 bg-rose-50/60' : 'border-gray-200' }} text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
               placeholder="+63 912 345 6789">
        <p data-duplicate-warning="phone" hidden role="status" class="mt-1.5 text-[11px] font-semibold text-amber-700">
            <span class="flex items-start gap-1.5">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0 mt-px"></i>
                <span data-duplicate-text></span>
            </span>
        </p>
    </div>
    <div>
        <label for="nationality" class="block text-[11px] font-bold text-dark/70 mb-1">Citizenship / Nationality *</label>
        <x-country-select name="nationality" :value="old('nationality', \App\Support\Countries::DEFAULT)" required />
    </div>
</div>

<div>
    <label for="address" class="block text-[11px] font-bold text-dark/70 mb-1">Residential Address *</label>
    <input id="address" type="text" name="address" value="{{ old('address') }}" required
           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
           placeholder="House/Unit No., Street, Barangay, City, Province">
</div>

<script>
    // Warns as soon as a name, email or number already belongs to a registered
    // client. Saving is still refused by the server; this just says so early.
    (function () {
        var url = @js($duplicateCheckUrl ?? route('admin.users.check-duplicate'));
        var timers = {};
        var token = {};

        function el(id) { return document.getElementById(id); }

        function show(field, message) {
            var warning = document.querySelector('[data-duplicate-warning="' + field + '"]');
            if (!warning) { return; }
            warning.hidden = !message;
            warning.querySelector('[data-duplicate-text]').textContent = message || '';
            var boxes = field === 'name' ? ['first_name', 'last_name'] : [field];
            boxes.forEach(function (id) {
                var box = el(id);
                if (box) { box.classList.toggle('!border-amber-400', !!message); }
            });
            if (message && window.lucide) { window.lucide.createIcons(); }
        }

        function check(field, params) {
            clearTimeout(timers[field]);
            timers[field] = setTimeout(function () {
                var mine = token[field] = (token[field] || 0) + 1;
                fetch(url + '?' + new URLSearchParams(Object.assign({ field: field }, params)), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }).then(function (r) { return r.ok ? r.json() : null; })
                  .then(function (data) { if (data && mine === token[field]) { show(field, data.message); } })
                  .catch(function () {});
            }, 400);
        }

        function watchEmail() {
            var value = el('email').value.trim();
            if (!/^\S+@\S+\.\S+$/.test(value)) { show('email', null); return; }
            check('email', { value: value });
        }

        function watchPhone() {
            var value = el('phone').value.trim();
            if (value.replace(/\D/g, '').length < 7) { show('phone', null); return; }
            check('phone', { value: value });
        }

        function watchName() {
            var first = el('first_name').value.trim(), last = el('last_name').value.trim();
            if (!first || !last) { show('name', null); return; }
            check('name', {
                first_name: first,
                middle_name: el('middle_name').value.trim(),
                last_name: last,
                suffix: el('suffix').value
            });
        }

        el('email').addEventListener('input', watchEmail);
        el('phone').addEventListener('input', watchPhone);
        ['first_name', 'middle_name', 'last_name'].forEach(function (id) { el(id).addEventListener('input', watchName); });
        el('suffix').addEventListener('change', watchName);

        // A form sent back with errors already holds the values; check them too.
        watchEmail(); watchPhone(); watchName();
    })();
</script>
