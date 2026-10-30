{{-- Part of the booking confirmation (_confirmation.blade.php): The fare and quotation assessment. --}}
        <!-- Pricing & Quotation Breakdown if total amount set -->
        @if($ticket->total_amount > 0 || $ticket->estimated_fare > 0)
            <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Fare &amp; Quotation Assessment</span>
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 text-xs">
                    <div>
                        <span class="text-dark/50 block text-[10px]">Estimated Base Fare</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->estimated_fare, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Taxes &amp; Surcharges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->taxes_amount, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Visa Assistance</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->visa_assistance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Insurance Fee</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->insurance_fee, 2) }}</span>
                    </div>
                    <div>
                        <span class="text-dark/50 block text-[10px]">Other Charges</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->other_charges, 2) }}</span>
                    </div>
                    @if ((float) $ticket->package_amount > 0)
                        <div>
                            <span class="text-dark/50 block text-[10px]">Tour Package</span>
                            <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->package_amount, 2) }}</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-dark/50 block text-[10px]">Amega Service Fee</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->service_fee, 2) }}</span>
                    </div>
                    @if ((float) $ticket->service_tax_amount > 0)
                        <div>
                            <span class="text-dark/50 block text-[10px]">Service Tax ({{ rtrim(rtrim(number_format((float) $ticket->service_tax_rate, 2), '0'), '.') }}%)</span>
                            <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->service_tax_amount, 2) }}</span>
                        </div>
                    @endif
                    <div>
                        <span class="text-dark/50 block text-[10px]">Services &amp; Requests</span>
                        <span class="font-mono font-bold text-dark">₱{{ number_format($ticket->extras_amount, 2) }}</span>
                    </div>
                </div>
                @php
                    $fareTypeLabels = $ticket->fareTypeLabels();
                    $quotedExtras = collect([...($ticket->selected_services ?? []), ...($ticket->special_requests_list ?? [])]);
                @endphp
                @if(!empty($ticket->fare_breakdown))
                    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-gray-100 text-dark/60 uppercase text-[10px] font-bold">
                                <tr>
                                    <th class="p-2.5">Passenger type</th>
                                    <th class="p-2.5 text-right">Passengers</th>
                                    <th class="p-2.5 text-right">Price each</th>
                                    <th class="p-2.5 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($fareTypeLabels as $type => $typeLabel)
                                    @php $row = $ticket->fare_breakdown[$type] ?? null; @endphp
                                    @if($row && ((int) $row['qty'] > 0 || (float) $row['price'] > 0))
                                        <tr>
                                            <td class="p-2.5 font-bold text-dark">{{ $typeLabel }}</td>
                                            <td class="p-2.5 text-right font-mono">{{ (int) $row['qty'] }}</td>
                                            <td class="p-2.5 text-right font-mono">₱{{ number_format((float) $row['price'], 2) }}</td>
                                            <td class="p-2.5 text-right font-mono font-bold text-dark">₱{{ number_format((float) $row['subtotal'], 2) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-gray-50">
                                    <td colspan="3" class="p-2.5 font-bold uppercase text-[10px] text-dark/70">Fare subtotal</td>
                                    <td class="p-2.5 text-right font-mono font-black text-dark">₱{{ number_format((float) $ticket->estimated_fare, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
                @if($quotedExtras->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
                        @foreach($quotedExtras as $extraKey)
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                                <span class="font-bold text-dark">{{ ucwords(str_replace('_', ' ', $extraKey)) }}</span>
                                <span class="font-mono font-bold {{ ($ticket->extraPriceLabel($extraKey) ?? 'Free') === 'Free' ? 'text-emerald-700' : 'text-dark' }}">{{ $ticket->extraPriceLabel($extraKey) ?? '—' }}</span>
                            </div>
                        @endforeach
                        <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs bg-gray-50">
                            <span class="font-bold uppercase text-[10px] text-dark/70">Services &amp; requests subtotal</span>
                            <span class="font-mono font-black text-dark">₱{{ number_format((float) $ticket->extras_amount, 2) }}</span>
                        </div>
                    </div>
                @endif
                @if ((float) $ticket->pwd_sc_discount > 0)
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-emerald-700">{{ $ticket->pwdScDiscountLabel() }}</span>
                        <span class="font-mono font-bold text-emerald-700">-₱{{ number_format((float) $ticket->pwd_sc_discount, 2) }}</span>
                    </div>
                @endif
                <div class="pt-2 border-t border-gray-200 flex items-center justify-between">
                    <span class="font-bold text-dark text-xs uppercase">Grand Total Quotation:</span>
                    <span class="font-mono font-black text-primary text-base">₱{{ number_format($ticket->total_amount, 2) }}</span>
                </div>
            </div>
        @endif
