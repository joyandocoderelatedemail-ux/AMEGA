@php
    /**
     * Shown while the Account Category above is Corporate or Group: pick the company (or the group)
     * this client travels under, or register a new one. A group works like a company (its members are
     * kept together and picked together when booking) without the company fields, a credit account or
     * the corporate service tax.
     *
     * Both kinds send the same fields (corporate_mode, corporate_account_id, corporate[...]); the half
     * that is not showing is disabled, so only one is ever sent.
     *
     * @var \Illuminate\Support\Collection $corporates  companies and groups, each with its kind
     * @var int|null $selectedCompany
     */
    $selectedCompany = old('corporate_account_id', $selectedCompany ?? null);
    $byKind = $corporates->groupBy(fn ($account) => $account->kind === 'group' ? 'group' : 'corporate');
    $has = ['corporate' => $byKind->has('corporate'), 'group' => $byKind->has('group')];
    $mode = old('corporate_mode');
    $inputClass = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $kinds = [
        'corporate' => ['title' => 'Corporate Account', 'intro' => 'This client travels under a company. Pick the company, or register a new one.', 'existing' => 'Existing company', 'new' => 'New company', 'label' => 'Company', 'empty' => '-- Select a company --'],
        'group' => ['title' => 'Group', 'intro' => 'This client travels with a group (a family, friends, a tour party). Pick the group, or start a new one. Its members are added to a booking together.', 'existing' => 'Existing group', 'new' => 'New group', 'label' => 'Group', 'empty' => '-- Select a group --'],
    ];
@endphp

<div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-4" x-cloak
     x-data="{
        kind: null,
        mode: @js($mode),
        has: @js($has),
        kinds: { Corporate: 'corporate', Group: 'group' },
        init() {
            const category = document.getElementById('account_category');
            const follow = () => {
                this.kind = this.kinds[category.value] || null;
                // Nothing to pick from yet: go straight to registering a new one.
                if (this.kind && (! this.mode || (this.mode === 'existing' && ! this.has[this.kind]))) {
                    this.mode = this.has[this.kind] ? 'existing' : 'new';
                }
            };
            follow();
            category.addEventListener('change', follow);
        },
     }"
     x-show="kind">

    @foreach ($kinds as $kind => $text)
        {{-- Disabled while the other kind (or neither) is chosen, so its fields are not sent. --}}
        <fieldset x-show="kind === '{{ $kind }}'" :disabled="kind !== '{{ $kind }}'" class="space-y-4" style="display: none">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-primary block">{{ $text['title'] }}</span>
                <p class="text-[11px] text-dark/50 mt-0.5">{{ $text['intro'] }}</p>
            </div>

            <div class="flex flex-wrap gap-4 text-xs font-bold text-dark/70">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="corporate_mode" value="existing" x-model="mode" class="text-primary focus:ring-primary" @disabled(! $has[$kind])>
                    <span>{{ $text['existing'] }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" name="corporate_mode" value="new" x-model="mode" class="text-primary focus:ring-primary">
                    <span>{{ $text['new'] }}</span>
                </label>
            </div>

            <div x-show="mode === 'existing'">
                <label for="{{ $kind }}_account_id" class="block text-[11px] font-bold text-dark/70 mb-1">{{ $text['label'] }}</label>
                <select id="{{ $kind }}_account_id" name="corporate_account_id" class="{{ $inputClass }}" :disabled="kind !== '{{ $kind }}' || mode !== 'existing'">
                    <option value="">{{ $text['empty'] }}</option>
                    @foreach ($byKind->get($kind, collect()) as $account)
                        <option value="{{ $account->id }}" @selected((string) $selectedCompany === (string) $account->id)>{{ $account->company_name }}</option>
                    @endforeach
                </select>
            </div>

            <fieldset x-show="mode === 'new'" :disabled="kind !== '{{ $kind }}' || mode !== 'new'">
                @if ($kind === 'group')
                    @include('admin.corporates._group-fields', ['prefix' => 'corporate', 'account' => null])
                @else
                    @include('admin.corporates._fields', ['prefix' => 'corporate', 'account' => null])
                @endif
            </fieldset>
        </fieldset>
    @endforeach
</div>
