{{-- Admin eyes only, as on the ticketing approval: what the agent who sent this file earns on it, kept when the admin
     approves (Commissions::record). Optional; left blank, nothing is recorded. Expects $file; $onDark on the dark banner. --}}
@if (\App\Support\Commissions::visibleTo(auth()->user()))
    @php $agent = \App\Models\User::find(\App\Support\Approvals::makerOf($file))?->name; @endphp
    <label class="flex flex-wrap items-center gap-2 text-xs font-semibold">
        <span>Agent commission (₱)</span>
        <input type="number" name="commission" min="0" step="0.01" value="{{ old('commission') }}" placeholder="0.00"
               aria-label="Agent commission in pesos (only admins see this)"
               class="w-32 h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs text-slate-900 tabular-nums focus:outline-none focus:ring-2 focus:ring-navy-600">
        <span class="font-normal {{ ($onDark ?? false) ? 'text-white/70' : 'text-slate-500' }}">For {{ $agent ?? 'the agent' }}, who sent this file. Only admins see this.</span>
    </label>
    @error('commission') <p class="w-full text-xs font-semibold {{ ($onDark ?? false) ? 'text-rose-200' : 'text-rose-600' }}">{{ $message }}</p> @enderror
@endif
