@extends('layouts.admin')

@section('title', $corporate->company_name . ' - AMEGA Admin')
@section('page_title', $corporate->isGroup() ? 'Group' : 'Corporate Account')

@php
    // A group is kept like a company, without the company details or a credit account.
    $isGroup = $corporate->isGroup();
    $noun = $corporate->noun();
@endphp

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="bg-navy rounded-3xl p-6 sm:p-8 text-white shadow-xl border border-white/10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
            <span class="px-3 py-1 rounded-full bg-accent text-dark font-extrabold text-[10px] uppercase tracking-wider">{{ $isGroup ? 'Group' : 'Corporate' }}</span>
            <h1 class="font-heading text-2xl font-bold text-white mt-2">{{ $corporate->company_name }}</h1>
            <p class="text-xs text-white/70 mt-0.5">{{ $members->count() }} {{ Str::plural('member', $members->count()) }}@if($corporate->industry) • {{ $corporate->industry }}@endif</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('admin.corporates.edit', $corporate) }}" class="px-4 py-2.5 bg-accent text-dark font-bold text-xs rounded-xl hover:bg-accent-dark transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="edit-3" class="w-4 h-4"></i>
                <span>Edit Details</span>
            </a>
            <a href="{{ route('admin.corporates.index', $isGroup ? ['kind' => 'group'] : []) }}" class="px-4 py-2.5 bg-white/10 text-white font-bold text-xs rounded-xl hover:bg-white/20 transition-all border border-white/20 flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>{{ $isGroup ? 'All Groups' : 'All Companies' }}</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @include('admin.partials._form-errors')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Company details -->
        <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-card space-y-4 text-xs h-fit">
            <h3 class="font-heading text-base font-bold text-dark">{{ $isGroup ? 'Group Details' : 'Company Details' }}</h3>
            @foreach($isGroup ? [
                'Group Leader' => $corporate->contact_person,
                "Leader's Phone" => $corporate->contact_phone,
                "Leader's Email" => $corporate->contact_email,
            ] : [
                'Registration No.' => $corporate->registration_number,
                'TIN' => $corporate->tin,
                'Business Address' => $corporate->address,
                'Contact Person' => collect([$corporate->contact_person, $corporate->contact_position])->filter()->implode(', '),
                'Contact Email' => $corporate->contact_email,
                'Contact Phone' => $corporate->contact_phone,
            ] as $label => $value)
                <div>
                    <span class="text-dark/40 block text-[10px] font-bold uppercase tracking-wider">{{ $label }}</span>
                    <span class="font-semibold text-dark">{{ filled($value) ? $value : 'Not provided' }}</span>
                </div>
            @endforeach
            @if(filled($corporate->notes))
                <div>
                    <span class="text-dark/40 block text-[10px] font-bold uppercase tracking-wider">Notes</span>
                    <span class="font-semibold text-dark whitespace-pre-line">{{ $corporate->notes }}</span>
                </div>
            @endif

            @if (auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('admin.corporates.destroy', $corporate) }}" onsubmit="return confirm('Delete this {{ $noun }}? Its members stay registered as clients.');" class="pt-3 border-t border-gray-100">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-[11px] font-bold text-rose-600 hover:underline flex items-center gap-1">
                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                    <span>Delete {{ $noun }}</span>
                </button>
            </form>
            @endif
        </div>

        <!-- Members -->
        <div class="lg:col-span-2 space-y-6">
            @if ($isGroup)
                <div class="bg-white rounded-2xl p-5 border border-gray-200 shadow-card flex items-start gap-3 text-xs text-dark/70">
                    <i data-lucide="users-round" class="w-5 h-5 text-primary shrink-0"></i>
                    <p><strong class="text-dark">Booked together, charged as usual.</strong> When booking, search for this group and tick who is travelling. A group has no service tax and no credit account: it pays at the cashier like any other client.</p>
                </div>
            @else
            {{-- Credit account (accounts receivable): only an admin sets it. --}}
            @php
                $owed = \App\Support\CorporateReceivables::outstanding($corporate);
                $creditField = 'w-full px-3 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary';
            @endphp
            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="font-heading text-base font-bold text-dark">Credit Account (A/R)</h3>
                        <p class="text-xs text-dark/50">Lets this company's ticket bookings be charged to its account and issued before payment.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[11px] font-bold {{ $corporate->credit_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-dark/60' }}">{{ $corporate->credit_enabled ? 'Credit on' : 'No credit' }}</span>
                </div>

                @if ($owed > 0 || $corporate->credit_enabled)
                    <p class="text-xs text-dark/70">
                        Owes now: <strong class="text-dark">₱{{ number_format($owed, 2) }}</strong>
                        @if ($corporate->credit_limit !== null) of a ₱{{ number_format((float) $corporate->credit_limit, 2) }} limit @endif
                    </p>
                @endif

                {{-- The A/R pages are in the cashier portal, which only cashiers and admins can open. --}}
                @if (auth()->user()?->isAdmin() || auth()->user()?->isCashier())
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('cashier.receivables.history', $corporate) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 text-xs font-bold text-dark hover:bg-gray-50">
                            <i data-lucide="history" class="w-3.5 h-3.5"></i><span>View A/R history</span>
                        </a>
                        <a href="{{ route('cashier.receivables.show', $corporate) }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 text-xs font-bold text-dark hover:bg-gray-50">
                            <i data-lucide="wallet" class="w-3.5 h-3.5"></i><span>Open the account</span>
                        </a>
                    </div>
                @endif

                @if (auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('admin.corporates.credit', $corporate) }}" class="space-y-4">
                        @csrf
                        <label class="flex items-center gap-2 text-xs font-bold text-dark cursor-pointer">
                            <input type="checkbox" name="credit_enabled" value="1" class="w-4 h-4 rounded text-primary" @checked(old('credit_enabled', $corporate->credit_enabled))>
                            <span>Allow this company to pay on account</span>
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="credit_terms_days" class="block text-[11px] font-bold text-dark/70 mb-1">Payment terms (net days)</label>
                                <input id="credit_terms_days" type="number" name="credit_terms_days" min="1" max="365" required value="{{ old('credit_terms_days', $corporate->credit_terms_days ?: \App\Models\CorporateAccount::DEFAULT_TERMS_DAYS) }}" class="{{ $creditField }}">
                                <p class="mt-1 text-[11px] text-dark/45">A charge falls due this many days after it is made (30 = Net 30).</p>
                            </div>
                            <div>
                                <label for="credit_limit" class="block text-[11px] font-bold text-dark/70 mb-1">Credit limit (₱) <span class="font-normal text-dark/40">(blank = no limit)</span></label>
                                <input id="credit_limit" type="number" name="credit_limit" min="0" step="0.01" value="{{ old('credit_limit', $corporate->credit_limit) }}" class="{{ $creditField }}" placeholder="e.g. 200000">
                            </div>
                            <div>
                                <label for="credit_interest_rate" class="block text-[11px] font-bold text-dark/70 mb-1">Late interest (% per month) <span class="font-normal text-dark/40">(optional)</span></label>
                                <input id="credit_interest_rate" type="number" name="credit_interest_rate" min="0" max="10" step="0.01" value="{{ old('credit_interest_rate', $corporate->credit_interest_rate) }}" class="{{ $creditField }}" placeholder="e.g. 1.5">
                            </div>
                            <div>
                                <label for="credit_grace_days" class="block text-[11px] font-bold text-dark/70 mb-1">Grace period (days)</label>
                                <input id="credit_grace_days" type="number" name="credit_grace_days" min="0" max="90" value="{{ old('credit_grace_days', $corporate->credit_grace_days ?? 0) }}" class="{{ $creditField }}">
                            </div>
                        </div>
                        <p class="text-[11px] text-dark/45">The interest rate and grace period are kept on file and printed on the statement. Interest is not yet added to balances automatically.</p>
                        <button type="submit" class="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-xl hover:bg-navy transition-colors">Save credit account</button>
                    </form>
                @elseif ($corporate->credit_enabled)
                    <p class="text-xs text-dark/70">Net {{ $corporate->credit_terms_days }} days. Only an admin can change the credit account.</p>
                @else
                    <p class="text-xs text-dark/50">Only an admin can turn on a credit account.</p>
                @endif
            </div>
            @endif

            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-card space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-heading text-base font-bold text-dark">Members</h3>
                    <a href="{{ route('admin.users.create', ['corporate' => $corporate->id]) }}" class="px-4 py-2 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-dark transition-all shadow-md flex items-center gap-1.5">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                        <span>Add New Member</span>
                    </a>
                </div>

                <div class="divide-y divide-gray-50">
                    @forelse($members as $member)
                        <div class="py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('admin.users.show', $member) }}" class="font-bold text-dark text-sm hover:text-primary">{{ $member->full_name }}</a>
                                <div class="text-[11px] text-dark/50 truncate">{{ collect([$member->real_email, $member->phone])->filter()->implode(' · ') }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.corporates.members.destroy', [$corporate, $member]) }}" onsubmit="return confirm('Remove this member from the {{ $noun }}? They stay registered as a client.');" class="m-0 shrink-0">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 text-[11px] font-bold text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">Remove</button>
                            </form>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-dark/50">No members yet. Add a new client, or assign one who is already registered.</p>
                    @endforelse
                </div>
            </div>

            <!-- Assign an existing client -->
            <div class="bg-white rounded-2xl p-6 border border-gray-200 shadow-card space-y-4">
                <div>
                    <h3 class="font-heading text-base font-bold text-dark">Assign an Existing Client</h3>
                    <p class="text-[11px] text-dark/50">Search the client directory, then add them to {{ $corporate->company_name }}.</p>
                </div>
                <form method="GET" action="{{ route('admin.corporates.show', $corporate) }}" class="flex gap-2" id="assign-search-form">
                    <input type="text" name="find" value="{{ $term }}" placeholder="Name, email, phone or passport number" autocomplete="off"
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                    <button type="submit" class="px-4 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Search</button>
                </form>

                <div id="assign-results">
                @if($term !== '')
                    <div class="divide-y divide-gray-50">
                        @forelse($candidates as $candidate)
                            <div class="py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="font-bold text-dark text-sm">{{ $candidate->full_name }}</div>
                                    <div class="text-[11px] text-dark/50 truncate">
                                        {{ collect([$candidate->real_email, $candidate->phone])->filter()->implode(' · ') }}
                                        @if($candidate->corporateAccount)
                                            <span class="text-amber-700 font-semibold">&bull; Now under {{ $candidate->corporateAccount->company_name }}; adding moves them here</span>
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.corporates.members.store', $corporate) }}" class="m-0 shrink-0">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $candidate->id }}">
                                    <button type="submit" class="px-3 py-1.5 bg-primary/10 text-primary font-bold text-[11px] rounded-lg hover:bg-primary hover:text-white transition-colors">Add to {{ $noun }}</button>
                                </form>
                            </div>
                        @empty
                            <p class="py-4 text-center text-xs text-dark/50">No other client matches "{{ $term }}".</p>
                        @endforelse
                    </div>
                @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Search as the admin types: refetch this page for the typed term and swap in the result list.
    (function () {
        var form = document.getElementById('assign-search-form');
        var results = document.getElementById('assign-results');
        if (!form || !results) return;
        var input = form.elements['find'];
        var timer = null, controller = null;

        function search() {
            var term = input.value.trim();
            var url = new URL(form.action, window.location.href);
            if (term) url.searchParams.set('find', term);
            if (controller) controller.abort();
            controller = new AbortController();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('assign-results');
                    if (fresh) results.innerHTML = fresh.innerHTML;
                    history.replaceState(null, '', url);
                })
                .catch(function (e) { if (e.name !== 'AbortError') form.submit(); });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(search, 300);
        });
    })();
</script>
@endsection
