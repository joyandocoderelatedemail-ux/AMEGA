@php
    $renewal = $renewal ?? null;
    $application = $application ?? $renewal?->application;
    $linkedId = old('srrv_application_id', $application?->id ?? $renewal?->srrv_application_id);
    $currentClass = old('visa_class', $renewal->visa_class ?? 'classic');
    $currentYears = (int) old('years_paid', $renewal->years_paid ?? 1);
    $errors = $errors ?? new \Illuminate\Support\ViewErrorBag;
@endphp

@if($errors->any())
    <div class="mb-6 rounded-2xl bg-rose-50 border border-rose-200 p-4" role="alert">
        <div class="flex items-start gap-3">
            <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0 mt-0.5"></i>
            <div>
                <p class="text-xs font-bold text-rose-900">Please correct the following:</p>
                <ul class="mt-1.5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li class="text-[11px] text-rose-800 leading-relaxed">&bull; {{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<div x-data="{
        visaClass: '{{ $currentClass }}',
        years: {{ $currentYears }},
        fees: {{ \Illuminate\Support\Js::from($classFees) }}
     }" class="space-y-6">

    @if($linkedId)
        <input type="hidden" name="srrv_application_id" value="{{ $linkedId }}">
    @endif

    @if($application)
        <div class="rounded-2xl bg-primary/5 border border-primary/20 p-4 flex items-center gap-3">
            <i data-lucide="link" class="w-4 h-4 text-primary shrink-0"></i>
            <div>
                <p class="text-xs font-bold text-dark">Linked to retiree file {{ $application->reference }}</p>
                <p class="text-[10px] text-dark/50 mt-0.5">{{ $application->retiree_name }} &middot; {{ ucfirst($application->visa_class) }}</p>
            </div>
        </div>
    @endif

    <!-- Which visa -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Which visa is it?</h2>
        <p class="text-[11px] text-dark/50 mb-4">The fee turns on this, nothing else.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach([
                'classic' => ['SRRV Classic', 'USD '.number_format($classFees['classic'] ?? 0, 2).' a year'],
                'courtesy' => ['SRRV Courtesy', 'USD '.number_format($classFees['courtesy'] ?? 0, 2).' a year'],
            ] as $key => [$label, $desc])
                <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                       :class="visaClass === '{{ $key }}' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                    <input type="radio" name="visa_class" value="{{ $key }}" x-model="visaClass"
                           class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                    <div>
                        <div class="text-xs font-bold text-dark">{{ $label }}</div>
                        <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                    </div>
                </label>
            @endforeach
        </div>
    </div>

    <!-- Retiree -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Retiree</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="retiree_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Full Name *</label>
                <input id="retiree_name" type="text" name="retiree_name" required
                       value="{{ old('retiree_name', $renewal->retiree_name ?? $application?->retiree_name ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="retiree_email" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Email</label>
                <input id="retiree_email" type="email" name="retiree_email"
                       value="{{ old('retiree_email', $renewal->retiree_email ?? $application?->retiree_email ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The file is sent on by email.</p>
            </div>
            <div>
                <label for="srrv_card_number" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">SRRV Card Number</label>
                <input id="srrv_card_number" type="text" name="srrv_card_number"
                       value="{{ old('srrv_card_number', $renewal->srrv_card_number ?? $application?->srrv_card_number ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>

    <!-- Years and fee -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Years and Fee</h2>
        <p class="text-[11px] text-dark/50 mb-4">
            Two years at most &mdash; the ceiling on how far ahead a retiree can pay.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="years_paid" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Years Paid *</label>
                <select id="years_paid" name="years_paid" x-model.number="years" required
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    @for($y = 1; $y <= $maxYears; $y++)
                        <option value="{{ $y }}" {{ $currentYears === $y ? 'selected' : '' }}>{{ $y }} year{{ $y > 1 ? 's' : '' }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <span class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Fee Due</span>
                <div class="px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200">
                    <span class="font-heading text-sm font-extrabold text-dark" x-text="'USD ' + (fees[visaClass] * years).toFixed(2)"></span>
                </div>
                <p class="text-[10px] text-dark/45 mt-1">Computed from the class rate. Not editable here.</p>
            </div>
        </div>
    </div>

    <!-- Documentation -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Documentation</h2>
        <div class="space-y-2.5">
            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                <input type="checkbox" name="id_and_photocopy_received" value="1"
                       {{ old('id_and_photocopy_received', $renewal->id_and_photocopy_received ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                <span class="text-xs font-semibold text-dark">Existing SRRV card and photocopy received</span>
            </label>
            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                <input type="checkbox" name="form_filled_online" value="1"
                       {{ old('form_filled_online', $renewal->form_filled_online ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                <span class="text-xs font-semibold text-dark">Form filled in online</span>
            </label>
            <p class="text-[10px] text-dark/45">No paper form at this step. Signature and thumb mark are taken from the retiree.</p>
        </div>
    </div>

    <!-- Remarks -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <label for="remarks" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
        <p class="text-[11px] text-dark/50 mb-3">Recorded against the renewal.</p>
        <textarea id="remarks" name="remarks" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('remarks', $renewal->remarks ?? '') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $renewal ? route('srrv.renewals.show', $renewal) : route('srrv.renewals.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        <button type="submit"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ $renewal ? 'Save Changes' : 'Record Renewal' }}</span>
        </button>
    </div>
</div>
