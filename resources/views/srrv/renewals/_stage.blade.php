@php
    /**
     * The current step of an annual ID renewal: what to do, and what still stands
     * between the renewal and the next step. Moving itself happens in the Status
     * card (or Record Release once the new ID is ready).
     */
    $button = 'inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-primary text-white font-bold text-xs rounded-xl hover:bg-primary-light transition-all';
    $status = $renewal->status;
@endphp

@if (! in_array($status, ['closed', 'cancelled'], true))
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

        @case ('ready_for_submission')
            <p class="text-xs text-dark/70">Ready to submit: the fees are approved, so move it to Submitted to PRA with its date and remarks in the Status card.</p>
            @break

        @case ('submitted_to_pra')
            <p class="text-xs text-dark/70">
                Submitted to the PRA{{ $renewal->submitted_at ? ' on '.$renewal->submitted_at->format('M d, Y') : '' }}@if ($renewal->submitted_remarks): {{ $renewal->submitted_remarks }}@endif.
                The PRA fee (USD {{ number_format((float) $renewal->fee_amount, 2) }}) and the service fee go to the cashier once approved.
            </p>
            @break

        @case ('payment_confirmed')
            <p class="text-xs text-dark/70">Both dues are fully paid. Move it on when the new ID is ready at the PRA office.</p>
            @break

        @case ('new_id_ready')
            <p class="text-xs text-dark/70">
                The new ID is ready at the PRA office{{ $renewal->card_expiry_date ? ', valid until '.$renewal->card_expiry_date->format('M d, Y') : '' }}.
                Notify the retiree below, then release the card in person.
            </p>
            {{-- The new card's expiry, as printed on it: set here, at this step (it starts two years from arrival). --}}
            <form method="POST" action="{{ route('srrv.renewals.card-expiry', $renewal) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label for="card_expiry_date" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">New ID expiry</label>
                    <input id="card_expiry_date" type="date" name="card_expiry_date" required
                           value="{{ old('card_expiry_date', $renewal->card_expiry_date?->toDateString() ?? now()->addYears(2)->toDateString()) }}"
                           class="px-3 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <button type="submit" class="{{ $button }}">Save expiry</button>
                @error('card_expiry_date') <p class="w-full text-[11px] font-semibold text-rose-600">{{ $message }}</p> @enderror
            </form>
            @break

        @case ('released_to_client')
            <p class="text-xs text-dark/70">
                Released to {{ $renewal->collected_by_name ?: 'the retiree' }}{{ $renewal->collected_at ? ' on '.$renewal->collected_at->format('M d, Y') : '' }}. Close the file from the Status card once everything is filed.
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
