{{--
    The fee and how it is paid: in full, or in terms (2 to 12 payments). Payments
    are recorded as they come in, and the terms show how far they cover.
    Lives inside the form's Alpine scope (payType, terms, total, plan, peso).
--}}
@php
    use App\Models\TouristVisaApplication as TVA;
    use App\Models\TouristVisaPayment;

    $statusTone = [
        'paid' => ['label' => 'Paid', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'dot' => 'bg-emerald-500'],
        'partial' => ['label' => 'Partly paid', 'class' => 'bg-amber-50 text-amber-800 ring-amber-600/20', 'dot' => 'bg-amber-500'],
        'unpaid' => ['label' => 'Unpaid', 'class' => 'bg-amber-50 text-amber-800 ring-amber-600/20', 'dot' => 'bg-amber-500'],
        'none' => ['label' => 'No fee set', 'class' => 'bg-slate-100 text-slate-600 ring-slate-500/20', 'dot' => 'bg-slate-400'],
    ];
    $peso = fn ($amount) => '₱'.number_format((float) $amount, 2);
    $status = $app->exists ? $app->paymentStatus() : 'none';
    $payments = $app->exists ? $app->payments : collect();
    $installments = $app->exists ? $app->installments() : [];
@endphp

<section id="payment" class="scroll-mt-32 {{ $card }}">
    <div class="{{ $sectionHead }}">
        <h2 class="{{ $sectionTitle }}">Fee &amp; Payment</h2>
        <p class="{{ $sectionNote }}">The total fee, paid in full or in terms. Record each payment as it comes in.</p>
    </div>

    <div class="p-4 sm:p-5 space-y-5">
        <div class="grid grid-cols-1 lg:grid-cols-[14rem_1fr] gap-5">
            <div>
                <label for="total_fee" class="{{ $label }}">Total Fee</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500 pointer-events-none">₱</span>
                    <input type="number" step="0.01" min="0" id="total_fee" name="total_fee" x-model.number="total"
                           value="{{ old('total_fee', (float) $app->total_fee ?: '') }}" placeholder="0.00"
                           class="{{ $input }} pl-7 font-mono">
                </div>
            </div>

            <fieldset>
                <legend class="{{ $label }}">How will it be paid?</legend>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (TVA::PAYMENT_TYPES as $key => $text)
                        <label class="{{ $choice }}">
                            <input type="radio" name="payment_type" value="{{ $key }}" x-model="payType" class="{{ $radio }}">
                            {{ $text }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>

        <div x-show="payType === 'terms'" x-cloak class="grid grid-cols-1 sm:grid-cols-[14rem_1fr] gap-4 items-end">
            <div>
                <label for="terms_count" class="{{ $label }}">Pay in how many times?</label>
                <select id="terms_count" name="terms_count" x-model.number="terms" class="{{ $input }}">
                    <option value="">Select</option>
                    @foreach (range(TVA::MIN_TERMS, TVA::MAX_TERMS) as $count)
                        <option value="{{ $count }}" @selected((int) old('terms_count', $app->terms_count) === $count)>{{ $count }} payments</option>
                    @endforeach
                </select>
            </div>
            <p class="text-sm font-semibold text-slate-700 pb-2.5" x-show="plan" x-text="plan"></p>
        </div>

        @if ($app->exists)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-px rounded-lg border border-slate-200 bg-slate-200 overflow-hidden">
                <div class="bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Total fee</div>
                    <div class="mt-1 text-lg font-bold text-slate-900 tabular-nums">{{ $peso($app->total_fee) }}</div>
                </div>
                <div class="bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Paid</div>
                    <div class="mt-1 text-lg font-bold text-slate-900 tabular-nums">{{ $peso($app->amountPaid()) }}</div>
                </div>
                <div class="bg-white p-4">
                    <div class="text-xs font-semibold text-slate-500">Balance</div>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-lg font-bold text-slate-900 tabular-nums">{{ $peso($app->balance()) }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset {{ $statusTone[$status]['class'] }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $statusTone[$status]['dot'] }}" aria-hidden="true"></span>{{ $statusTone[$status]['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            @if ($installments)
                <div>
                    <p class="text-sm font-semibold text-slate-900 mb-2">Terms</p>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach ($installments as $term)
                            <div class="rounded-lg border p-3 {{ $term['status'] === 'paid' ? 'border-emerald-600/40 bg-emerald-50' : 'border-slate-200' }}">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-slate-500">Payment {{ $term['number'] }}</span>
                                    @if ($term['status'] === 'paid')
                                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    @endif
                                </div>
                                <div class="mt-1 text-sm font-bold text-slate-900 tabular-nums">{{ $peso($term['amount']) }}</div>
                                <div class="mt-0.5 text-xs {{ $term['status'] === 'partial' ? 'text-amber-700 font-semibold' : 'text-slate-500' }}">
                                    {{ ['paid' => 'Paid', 'partial' => $peso($term['paid']).' paid', 'unpaid' => 'Not paid yet'][$term['status']] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($payments->isNotEmpty())
                <div>
                    <p class="text-sm font-semibold text-slate-900 mb-2">Payments recorded</p>
                    <div class="relative overflow-x-auto rounded-lg border border-slate-200">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Date</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Method</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Reference</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold text-slate-500 text-right">Amount</th>
                                    <th class="px-3 py-2.5 text-xs font-semibold text-slate-500">Recorded by</th>
                                    @if (auth()->user()->isAdmin())
                                        <th class="px-3 py-2.5"><span class="sr-only">Remove</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td class="px-3 py-2.5 whitespace-nowrap text-slate-700">{{ $payment->paid_on->format('M j, Y') }}</td>
                                        <td class="px-3 py-2.5 text-slate-700">{{ $payment->method_label }}</td>
                                        <td class="px-3 py-2.5 text-slate-500">{{ $payment->reference ?: '—' }}</td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-slate-900 tabular-nums">{{ $peso($payment->amount) }}</td>
                                        <td class="px-3 py-2.5 text-slate-500">{{ $payment->recordedBy?->name ?? '—' }}</td>
                                        @if (auth()->user()->isAdmin())
                                            <td class="px-3 py-2.5 text-right">
                                                <button type="submit" form="payment-delete-{{ $payment->id }}" title="Remove this payment" aria-label="Remove this payment"
                                                        onclick="return confirm('Remove this payment? The balance goes back up.')"
                                                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-rose-600 hover:bg-rose-50">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        <!-- Record a payment with this save -->
        <div class="rounded-lg border border-slate-200 p-3 sm:p-4">
            <p class="text-sm font-semibold text-slate-900">{{ $app->exists ? 'Record a payment' : 'Payment received now' }}</p>
            <p class="mt-0.5 text-sm text-slate-500">Leave the amount empty when nothing was paid.</p>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="payment_amount" class="{{ $label }}">Amount</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-slate-500 pointer-events-none">₱</span>
                        <input type="number" step="0.01" min="0" id="payment_amount" name="payment_amount" value="{{ old('payment_amount') }}" placeholder="0.00" class="{{ $input }} pl-7 font-mono">
                    </div>
                </div>
                <div>
                    <label for="payment_method" class="{{ $label }}">Method</label>
                    <select id="payment_method" name="payment_method" class="{{ $input }}">
                        @foreach (TouristVisaPayment::METHODS as $key => $text)
                            <option value="{{ $key }}" @selected(old('payment_method', 'cash') === $key)>{{ $text }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="payment_paid_on" class="{{ $label }}">Date paid</label>
                    <input type="date" id="payment_paid_on" name="payment_paid_on" value="{{ old('payment_paid_on', now()->toDateString()) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="payment_reference" class="{{ $label }}">Reference <span class="font-normal text-slate-400">(optional)</span></label>
                    <input id="payment_reference" name="payment_reference" value="{{ old('payment_reference') }}" placeholder="OR no. or transaction no." class="{{ $input }}">
                </div>
            </div>
        </div>
    </div>
</section>
