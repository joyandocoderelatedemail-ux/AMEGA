@php
    $app = $application ?? null;
    $currentService = old('service_type', $app->service_type ?? $serviceType ?? 'renewal_application');
    $currentClass = old('visa_class', $app->visa_class ?? 'classic');
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

<div x-data="{ service: '{{ $currentService }}', visaClass: '{{ $currentClass }}' }" class="space-y-6">

    <!-- Job -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Job</h2>
        <p class="text-[11px] text-dark/50 mb-4">Three jobs share this desk. The choice decides the pipeline.</p>

        @if($app)
            <input type="hidden" name="service_type" value="{{ $app->service_type }}">
            <div class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-primary/10 border border-primary/20">
                <i data-lucide="lock" class="w-4 h-4 text-primary"></i>
                <span class="text-xs font-bold text-primary uppercase tracking-wider">{{ $app->service_label }}</span>
            </div>
            <p class="text-[10px] text-dark/45 mt-2">The job is fixed once a file is open.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {{-- Re-stamping returns here once its flow is defined. --}}
                @foreach([
                    'renewal_application' => ['New SRRV Application', 'A retiree applying for the SRRV, with oath taking at the PRA', 'file-badge'],
                ] as $key => [$label, $desc, $icon])
                    <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                           :class="service === '{{ $key }}' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                        <input type="radio" name="service_type" value="{{ $key }}" x-model="service"
                               class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                        <div>
                            <div class="text-xs font-bold text-dark flex items-center gap-1.5">
                                <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5 text-primary"></i>
                                <span>{{ $label }}</span>
                            </div>
                            <p class="text-[10px] text-dark/50 mt-0.5">{{ $desc }}</p>
                        </div>
                    </label>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Class -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Visa Class</h2>
        <p class="text-[11px] text-dark/50 mb-4">Decided by who the retiree is, not by which one they want.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'classic' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="classic" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Classic</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Not a government employee and not military.</p>
                </div>
            </label>
            <label class="flex items-start gap-3 p-3.5 rounded-xl bg-white border cursor-pointer transition-all"
                   :class="visaClass === 'courtesy' ? 'border-primary ring-2 ring-primary/20' : 'border-gray-200 hover:border-gray-300'">
                <input type="radio" name="visa_class" value="courtesy" x-model="visaClass"
                       class="mt-0.5 w-4 h-4 text-primary border-gray-300 focus:ring-primary">
                <div>
                    <div class="text-xs font-bold text-dark">SRRV Courtesy</div>
                    <p class="text-[10px] text-dark/50 mt-0.5">Government employees and military, aged 50 and above.</p>
                </div>
            </label>
        </div>
    </div>

    <!-- Retiree -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-4">Retiree</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="retiree_name" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Full Name *</label>
                <input id="retiree_name" type="text" name="retiree_name" required
                       value="{{ old('retiree_name', $app->retiree_name ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="retiree_email" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Email</label>
                <input id="retiree_email" type="email" name="retiree_email"
                       value="{{ old('retiree_email', $app->retiree_email ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The file is sent on by email.</p>
            </div>
            <div>
                <label for="retiree_phone" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Phone</label>
                <input id="retiree_phone" type="text" name="retiree_phone"
                       value="{{ old('retiree_phone', $app->retiree_phone ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="date_of_birth" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Date of Birth</label>
                <input id="date_of_birth" type="date" name="date_of_birth"
                       value="{{ old('date_of_birth', optional($app->date_of_birth ?? null)->format('Y-m-d')) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">Courtesy requires age 50 or above.</p>
            </div>
            <div>
                <label for="nationality" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Nationality</label>
                <input id="nationality" type="text" name="nationality"
                       value="{{ old('nationality', $app->nationality ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="srrv_card_number" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">SRRV Card Number</label>
                <input id="srrv_card_number" type="text" name="srrv_card_number"
                       value="{{ old('srrv_card_number', $app->srrv_card_number ?? '') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>

    <!-- Requirements -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Requirements</h2>
        <p class="text-[11px] text-dark/50 mb-4">
            The standard PRA checklist applies to both classes. The extra proof depends on the class.
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label for="investment_amount" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Investment Amount</label>
                <input id="investment_amount" type="number" step="0.01" min="0" name="investment_amount"
                       value="{{ old('investment_amount', $app->investment_amount ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">The deposit the visa rests on.</p>
            </div>
            <div>
                <label for="copies_submitted" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Copies Submitted</label>
                <input id="copies_submitted" type="number" min="1" max="10" name="copies_submitted"
                       value="{{ old('copies_submitted', $app->copies_submitted ?? 4) }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                <p class="text-[10px] text-dark/45 mt-1">Four copies of everything is the standing rule.</p>
            </div>
        </div>

        <!-- Classic proofs -->
        <div x-show="visaClass === 'classic'" x-cloak class="space-y-2.5">
            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                <input type="checkbox" name="police_clearance_received" value="1"
                       {{ old('police_clearance_received', $app->police_clearance_received ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                <span class="text-xs font-semibold text-dark">Police clearance received</span>
            </label>
            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                <input type="checkbox" name="pension_proof_received" value="1"
                       {{ old('pension_proof_received', $app->pension_proof_received ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                <span class="text-xs font-semibold text-dark">Proof of pension received</span>
            </label>
            <p class="text-[10px] text-dark/45">Both sit on top of the standard checklist for Classic.</p>
        </div>

        <!-- Courtesy proof -->
        <div x-show="visaClass === 'courtesy'" x-cloak class="space-y-2.5">
            <label class="flex items-center gap-2.5 p-3 rounded-xl bg-gray-50 border border-gray-200 cursor-pointer">
                <input type="checkbox" name="military_service_proof_received" value="1"
                       {{ old('military_service_proof_received', $app->military_service_proof_received ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-primary">
                <span class="text-xs font-semibold text-dark">Proof of military service received</span>
            </label>
            <p class="text-[10px] text-dark/45">The document that unlocks Courtesy.</p>
        </div>
    </div>

    <!-- Fees -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <h2 class="font-heading text-sm font-extrabold text-dark mb-1">Fees</h2>
        <p class="text-[11px] text-dark/50 mb-4">Paid in full at this stage &mdash; no deposit.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="service_fee" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Service Fee</label>
                <input id="service_fee" type="number" step="0.01" min="0" name="service_fee"
                       value="{{ old('service_fee', $app->service_fee ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="amount_paid" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Amount Paid</label>
                <input id="amount_paid" type="number" step="0.01" min="0" name="amount_paid"
                       value="{{ old('amount_paid', $app->amount_paid ?? '0') }}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label for="currency" class="block text-[10px] font-bold uppercase tracking-wider text-dark/50 mb-1">Currency</label>
                <select id="currency" name="currency"
                        class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                    @foreach(['USD', 'PHP'] as $code)
                        <option value="{{ $code }}" {{ old('currency', $app->currency ?? 'USD') === $code ? 'selected' : '' }}>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <!-- Remarks -->
    <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-6">
        <label for="remarks" class="block font-heading text-sm font-extrabold text-dark mb-1">Remarks</label>
        <p class="text-[11px] text-dark/50 mb-3">Recorded against the file.</p>
        <textarea id="remarks" name="remarks" rows="3"
                  class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">{{ old('remarks', $app->remarks ?? '') }}</textarea>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ $app ? route('srrv.applications.show', $app) : route('srrv.applications.index') }}"
           class="px-5 py-2.5 bg-gray-100 text-dark/70 font-bold text-xs rounded-xl hover:bg-gray-200 transition-all">Cancel</a>
        <button type="submit"
                class="px-6 py-2.5 bg-primary text-white font-heading font-bold text-xs rounded-xl hover:bg-primary-light transition-all shadow-sm inline-flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4"></i>
            <span>{{ $app ? 'Save Changes' : 'Open Retiree File' }}</span>
        </button>
    </div>
</div>
