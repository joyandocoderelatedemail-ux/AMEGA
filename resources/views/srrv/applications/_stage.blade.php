@php
    /**
     * The current stage's task on a new SRRV application: what to do, what to
     * record, and what still stands between the file and the next stage.
     * Advance is refused until $blocker is null.
     */
    $field = 'w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary';
    $label = 'block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1';
    $button = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    $status = $application->status;
@endphp

@if ($status !== 'cancelled')
<div class="rounded-2xl bg-white border shadow-sm p-6 mb-6 {{ $isFinal || ! $blocker ? 'border-emerald-200' : 'border-amber-200' }}">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Current step</p>
            <h2 class="font-heading text-base font-extrabold text-dark mt-0.5">{{ $application->status_label }}</h2>
        </div>
        @if ($isFinal)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> File complete
            </span>
        @elseif ($blocker)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                <i data-lucide="clock" class="w-3.5 h-3.5"></i> To do before advancing
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                <i data-lucide="check" class="w-3.5 h-3.5"></i> Done &mdash; ready to advance
            </span>
        @endif
    </div>

    @switch ($status)
        @case ('pending')
            <p class="text-xs text-dark/70">
                <strong>SRRV Classic</strong> is for retirees who are not government employees and not military.
                <strong>SRRV Courtesy</strong> is for government employees and military aged {{ \App\Models\SrrvApplication::COURTESY_MIN_AGE }} and above.
                This file is <strong>{{ ucfirst((string) $application->visa_class) }}</strong>@if ($application->age() !== null) &middot; retiree is {{ $application->age() }}@endif.
                Change the category or birth date with <strong>Edit</strong>.
            </p>
            @break

        @case ('requirements')
            <p class="text-xs text-dark/70 mb-3">File these in the <strong>Documents</strong> section below, or tick a class proof once you have seen the original.</p>
            <ul class="space-y-1.5 mb-3">
                @foreach ($application->documentChecklist() as $document)
                    <li class="flex items-center gap-2 text-xs {{ $document['received'] ? 'text-emerald-700' : 'text-dark/70' }}">
                        <i data-lucide="{{ $document['received'] ? 'check-circle-2' : 'circle' }}" class="w-4 h-4 {{ $document['received'] ? 'text-emerald-500' : 'text-amber-500' }}"></i>
                        <span>{{ $document['label'] }}</span>
                        <span class="text-[10px] font-bold uppercase tracking-wider {{ $document['received'] ? 'text-emerald-600' : 'text-amber-700' }}">{{ $document['received'] ? 'Received' : 'Missing' }}</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('srrv.applications.stage', $application) }}" class="flex flex-wrap items-center gap-4">
                @csrf
                @if ($application->isCourtesy())
                    <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                        <input type="checkbox" name="military_service_proof_received" value="1" @checked($application->military_service_proof_received) class="w-4 h-4 rounded text-primary border-gray-300">
                        Proof of military service seen
                    </label>
                @else
                    <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                        <input type="checkbox" name="police_clearance_received" value="1" @checked($application->police_clearance_received) class="w-4 h-4 rounded text-primary border-gray-300">
                        Police clearance seen
                    </label>
                    <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                        <input type="checkbox" name="pension_proof_received" value="1" @checked($application->pension_proof_received) class="w-4 h-4 rounded text-primary border-gray-300">
                        Proof of pension seen
                    </label>
                @endif
                <button type="submit" class="{{ $button }}">Save</button>
            </form>
            @break

        @case ('investment')
            <p class="text-xs text-dark/70 mb-3">Record the investment amount deposited for the SRRV.</p>
            <form method="POST" action="{{ route('srrv.applications.stage', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div>
                    <label for="investment_amount_stage" class="{{ $label }}">Investment Amount (USD)</label>
                    <input id="investment_amount_stage" type="number" step="0.01" min="0.01" name="investment_amount" required
                           value="{{ old('investment_amount', (float) $application->investment_amount ?: '') }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save amount</button>
            </form>
            @break

        @case ('supporting')
            <p class="text-xs text-dark/70 mb-3">Collect any additional supporting documents PRA asks for and file them below, then confirm they are complete.</p>
            @if ($application->supporting_completed_at)
                <p class="text-xs font-semibold text-emerald-700">Confirmed complete {{ $application->supporting_completed_at->format('M d, Y g:i A') }}.</p>
            @else
                <form method="POST" action="{{ route('srrv.applications.stage', $application) }}" class="m-0">
                    @csrf
                    <button type="submit" class="{{ $button }}"><i data-lucide="check" class="w-4 h-4"></i> Supporting documents complete</button>
                </form>
            @endif
            @break

        @case ('documentation')
            <p class="text-xs text-dark/70 mb-3">Lodge the file with PRA and record when, with PRA's reference.</p>
            <form method="POST" action="{{ route('srrv.applications.stage', $application) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                @csrf
                <div>
                    <label for="lodged_at" class="{{ $label }}">Lodged with PRA</label>
                    <input id="lodged_at" type="date" name="lodged_at" required value="{{ old('lodged_at', $application->lodged_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <div>
                    <label for="pra_reference" class="{{ $label }}">PRA Reference</label>
                    <input id="pra_reference" type="text" name="pra_reference" value="{{ old('pra_reference', $application->pra_reference) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save</button>
            </form>
            @break

        @case ('oath')
            <p class="text-xs text-dark/70 mb-3">The retiree takes the oath in person at PRA. Record the date.</p>
            <form method="POST" action="{{ route('srrv.applications.stage', $application) }}" class="flex flex-col sm:flex-row gap-3 sm:items-end">
                @csrf
                <div>
                    <label for="oath_at" class="{{ $label }}">Oath Taking Date</label>
                    <input id="oath_at" type="date" name="oath_at" required value="{{ old('oath_at', $application->oath_at?->format('Y-m-d')) }}" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $button }}">Save date</button>
            </form>
            @break

        @case ('awaiting_release')
            <p class="text-xs text-dark/70">
                PRA processing takes 2-3 months. The SRRV is released only when the file is <strong>fully paid</strong>:
                fee {{ $application->currency }} {{ number_format((float) $application->service_fee, 2) }},
                paid {{ number_format((float) $application->amount_paid, 2) }},
                balance <strong class="{{ $application->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ number_format($application->outstandingBalance(), 2) }}</strong>.
                Record payments in <strong>Fees</strong>.
            </p>
            @break

        @case ('released')
            <p class="text-xs text-dark/70">Released @if ($application->released_at) {{ $application->released_at->format('M d, Y') }}@endif. This file is complete.</p>
            @break

        @default
            <p class="text-xs text-dark/70">Move this file along with <strong>Advance</strong>.</p>
    @endswitch

    @if ($blocker && ! $isFinal)
        <p class="mt-4 pt-3 border-t border-gray-100 text-[11px] font-semibold text-amber-800 flex items-start gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
            <span>{{ $blocker }}</span>
        </p>
    @endif
</div>
@endif
