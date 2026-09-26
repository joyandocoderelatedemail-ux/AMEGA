@php
    /**
     * The current step of an annual renewal: what to do, and what still stands
     * between the renewal and the next step. Collection waits for full payment.
     */
    $button = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    $status = $renewal->status;
@endphp

@if (! in_array($status, ['cancelled', 'collected'], true))
<div class="rounded-2xl bg-white border shadow-sm p-6 mb-6 {{ $blocker ? 'border-amber-200' : 'border-emerald-200' }}">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Current step</p>
            <h2 class="font-heading text-base font-extrabold text-dark mt-0.5">{{ $renewal->status_label }}</h2>
        </div>
        @if ($blocker)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-bold">
                <i data-lucide="clock" class="w-3.5 h-3.5"></i> To do before moving on
            </span>
        @else
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                <i data-lucide="check" class="w-3.5 h-3.5"></i> Done &mdash; ready to move on
            </span>
        @endif
    </div>

    @switch ($status)
        @case ('pending')
            <p class="text-xs text-dark/70 mb-3">Collect the retiree's existing SRRV ID with a photocopy, fill in the online form, and take their signature and thumb mark. No appearance or oath is needed for a renewal.</p>
            <form method="POST" action="{{ route('srrv.renewals.stage', $renewal) }}" class="flex flex-wrap items-center gap-4">
                @csrf
                <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                    <input type="checkbox" name="id_and_photocopy_received" value="1" @checked($renewal->id_and_photocopy_received) class="w-4 h-4 rounded text-primary border-gray-300">
                    SRRV ID + photocopy received
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                    <input type="checkbox" name="form_filled_online" value="1" @checked($renewal->form_filled_online) class="w-4 h-4 rounded text-primary border-gray-300">
                    Online form filled in
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-dark">
                    <input type="checkbox" name="signature_thumbmark_taken" value="1" @checked($renewal->signature_thumbmark_at) class="w-4 h-4 rounded text-primary border-gray-300">
                    Signature + thumb mark taken
                </label>
                <button type="submit" class="{{ $button }}">Save</button>
            </form>
            @break

        @case ('documented')
            <p class="text-xs text-dark/70">
                {{ ucfirst((string) $renewal->visa_class) }} renewal: USD {{ number_format(\App\Models\SrrvRenewal::feeForClass($renewal->visa_class), 2) }}
                &times; {{ $renewal->years_paid }} {{ Str::plural('year', $renewal->years_paid) }} =
                <strong>{{ $renewal->currency }} {{ number_format((float) $renewal->fee_amount, 2) }}</strong>.
                Email or submit the file to PRA, then move it on.
            </p>
            @break

        @case ('email_sent')
            <p class="text-xs text-dark/70">Submitted to PRA. Move it on once PRA is processing it.</p>
            @break

        @case ('processing')
            <p class="text-xs text-dark/70">PRA is processing the renewal. Move it on when it's ready at the PRA office; the client is told then.</p>
            @break

        @case ('ready_for_collection')
            <p class="text-xs text-dark/70">
                Ready at the PRA office. The client collects in person once the renewal fee is <strong>fully paid</strong>:
                fee {{ $renewal->currency }} {{ number_format((float) $renewal->fee_amount, 2) }},
                paid {{ number_format((float) $renewal->amount_paid, 2) }},
                balance <strong class="{{ $renewal->outstandingBalance() > 0 ? 'text-rose-600' : 'text-emerald-700' }}">{{ number_format($renewal->outstandingBalance(), 2) }}</strong>.
            </p>
            @break
    @endswitch

    @if ($blocker)
        <p class="mt-4 pt-3 border-t border-gray-100 text-[11px] font-semibold text-amber-800 flex items-start gap-1.5">
            <i data-lucide="info" class="w-3.5 h-3.5 shrink-0 mt-0.5"></i>
            <span>{{ $blocker }}</span>
        </p>
    @endif
</div>
@endif
