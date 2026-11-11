@php
    /**
     * A group's details: its name, its leader and how to reach them. A group is kept like a company
     * (App\Models\CorporateAccount, kind 'group') without the company-only fields.
     *
     * @var \App\Models\CorporateAccount|null $account
     */
    $account = $account ?? null;
    $prefix = $prefix ?? '';
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    // Nested under "corporate" on the client form (the same fields a company sends), bare on the group's own form.
    $field = fn (string $key) => $prefix === '' ? $key : "{$prefix}[{$key}]";
    $value = fn (string $key) => old($prefix === '' ? $key : "{$prefix}.{$key}", $account?->{$key});
    $id = fn (string $key) => 'group_'.$key;
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label for="{{ $id('company_name') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Group Name *</label>
        <input id="{{ $id('company_name') }}" type="text" name="{{ $field('company_name') }}" value="{{ $value('company_name') }}" maxlength="255"
               class="{{ $inputClass }}" placeholder="e.g. Santos Family, St. Mary's Class of 2010, Reyes Barkada">
    </div>
    {{-- The leader is picked from the registered clients (or typed in); picking one fills their phone and email. --}}
    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4"
         x-data="groupLeaderPicker(@js(route('admin.corporates.leader-search')), @js((string) $value('contact_person')), @js((string) $value('contact_phone')), @js((string) $value('contact_email')))">
        <div class="sm:col-span-2 relative" @click.outside="open = false">
            <label for="{{ $id('contact_person') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Group Leader <span class="font-normal text-dark/40">(optional)</span></label>
            <input id="{{ $id('contact_person') }}" type="text" name="{{ $field('contact_person') }}" x-model="name" maxlength="255" autocomplete="off"
                   @input.debounce.300ms="search()" @focus="if (results.length) open = true" @keydown.escape="open = false"
                   class="{{ $inputClass }}" placeholder="Search registered clients by name, email or phone">
            <div x-show="open" x-cloak class="absolute left-0 right-0 z-20 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-60 overflow-y-auto">
                <template x-for="client in results" :key="client.id">
                    <button type="button" @click="pick(client)" class="w-full text-left px-3.5 py-2 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                        <span class="block text-xs font-semibold text-dark" x-text="client.name"></span>
                        <span class="block text-[11px] text-dark/50" x-text="[client.phone, client.email].filter(Boolean).join(' · ')"></span>
                    </button>
                </template>
                <p x-show="!loading && results.length === 0" class="px-3.5 py-2 text-[11px] text-dark/50">No registered client matches. The name typed is kept as the leader.</p>
            </div>
        </div>
        <div>
            <label for="{{ $id('contact_phone') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Leader's Phone <span class="font-normal text-dark/40">(optional)</span></label>
            <input id="{{ $id('contact_phone') }}" type="text" name="{{ $field('contact_phone') }}" x-model="phone" maxlength="50"
                   class="{{ $inputClass }}">
        </div>
        <div>
            <label for="{{ $id('contact_email') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Leader's Email <span class="font-normal text-dark/40">(optional)</span></label>
            <input id="{{ $id('contact_email') }}" type="email" name="{{ $field('contact_email') }}" x-model="email" maxlength="255"
                   class="{{ $inputClass }}">
        </div>
    </div>
    <div class="sm:col-span-2">
        <label for="{{ $id('notes') }}" class="block text-[11px] font-bold text-dark/70 mb-1">Notes <span class="font-normal text-dark/40">(optional)</span></label>
        <textarea id="{{ $id('notes') }}" name="{{ $field('notes') }}" rows="2" maxlength="2000"
                  class="{{ $inputClass }}" placeholder="Anything staff should know about the group">{{ $value('notes') }}</textarea>
    </div>
</div>

@once
    <script>
        function groupLeaderPicker(url, name, phone, email) {
            return {
                name, phone, email,
                results: [], open: false, loading: false,
                async search() {
                    const term = this.name.trim();
                    if (term.length < 2) { this.results = []; this.open = false; return; }
                    this.loading = true;
                    try {
                        const response = await fetch(url + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
                        this.results = response.ok ? (await response.json()).clients : [];
                        this.open = true;
                    } catch (e) {
                        this.results = [];
                    } finally {
                        this.loading = false;
                    }
                },
                pick(client) {
                    this.name = client.name || '';
                    this.phone = client.phone || '';
                    this.email = client.email || '';
                    this.open = false;
                },
            };
        }
    </script>
@endonce
