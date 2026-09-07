@extends('layouts.ticketing')

@section('title', 'Generate Booking Agreement - ' . $ticket->booking_reference)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="agreementForm()">
    
    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="inline-flex items-center gap-1.5 text-xs font-heading font-bold text-dark/60 hover:text-primary transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Back to Ticket #{{ $ticket->booking_reference }}</span>
            </a>
            <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight mt-1">Generate Official Booking Agreement</h1>
            <p class="text-xs text-dark/50">Auto-completed form based on Step 4 of the Booking Workflow. Agent sets custom pricing and terms.</p>
        </div>

        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-800 text-xs font-bold">
            <i data-lucide="file-signature" class="w-4 h-4"></i>
            <span>Ticket: {{ $ticket->booking_reference }}</span>
        </div>
    </div>

    <!-- Main Agreement Editor Form -->
    <form action="{{ route('ticketing.agreements.store', $ticket) }}" method="POST" class="space-y-6">
        @csrf

        <!-- 1. Client & Contact Information (Auto-completed) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-xs">1</span>
                    <h2 class="font-heading font-bold text-base text-dark">Client &amp; Contact Details</h2>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">
                    Auto-Filled from Manifest
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-bold text-dark/70 mb-1">Name(s) of Passengers / Booker *</label>
                    <input type="text" name="client_names" value="{{ old('client_names', $passengerNames) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="e.g. Juan Dela Cruz, Maria Clara Santos">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Agreement Date *</label>
                    <input type="date" name="agreement_date" value="{{ old('agreement_date', date('Y-m-d')) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Phone / Cell No.</label>
                    <input type="text" name="contact_phone" value="{{ old('contact_phone', $ticket->contact_phone) }}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Email Address</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $ticket->contact_email) }}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Home / Hotel Address</label>
                    <input type="text" name="home_hotel_address" value="{{ old('home_hotel_address') }}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                           placeholder="e.g. Balibago, Angeles City / Henann Resort Boracay">
                </div>
            </div>
        </div>

        <!-- 2. Flight & Carrier Matrix (10 Columns matching Official Form) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-xs">2</span>
                    <h2 class="font-heading font-bold text-base text-dark">Flight &amp; Travel Schedule Matrix</h2>
                </div>
                <button type="button" @click="addSegment()" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark text-xs font-bold transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Add Segment Row</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border border-gray-200 rounded-xl overflow-hidden">
                    <thead class="bg-gray-100 text-dark/70 font-bold uppercase text-[10px]">
                        <tr>
                            <th class="p-2 border-r border-gray-200">Carrier</th>
                            <th class="p-2 border-r border-gray-200">Flt.#</th>
                            <th class="p-2 border-r border-gray-200">Class</th>
                            <th class="p-2 border-r border-gray-200">Day</th>
                            <th class="p-2 border-r border-gray-200">Month</th>
                            <th class="p-2 border-r border-gray-200">From</th>
                            <th class="p-2 border-r border-gray-200">To</th>
                            <th class="p-2 border-r border-gray-200">Dep.</th>
                            <th class="p-2 border-r border-gray-200">Arr.</th>
                            <th class="p-2 border-r border-gray-200">Status</th>
                            <th class="p-2 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="(seg, idx) in segments" :key="idx">
                            <tr class="hover:bg-gray-50/70">
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][carrier]'" x-model="seg.carrier" placeholder="e.g. PAL / 5J"
                                           class="w-full px-2 py-1 bg-transparent border-0 font-semibold focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][flight_number]'" x-model="seg.flight_number" placeholder="5J 560"
                                           class="w-20 px-2 py-1 bg-transparent border-0 font-mono font-semibold focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][flight_class]'" x-model="seg.flight_class" placeholder="Econ"
                                           class="w-16 px-2 py-1 bg-transparent border-0 focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][day]'" x-model="seg.day" placeholder="15"
                                           class="w-16 px-2 py-1 bg-transparent border-0 focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][month]'" x-model="seg.month" placeholder="OCT"
                                           class="w-16 px-2 py-1 bg-transparent border-0 uppercase font-semibold focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][from_location]'" x-model="seg.from_location" placeholder="MNL"
                                           class="w-20 px-2 py-1 bg-transparent border-0 font-semibold focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][to_location]'" x-model="seg.to_location" placeholder="MPH"
                                           class="w-20 px-2 py-1 bg-transparent border-0 font-semibold focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][departure_time]'" x-model="seg.departure_time" placeholder="08:30"
                                           class="w-20 px-2 py-1 bg-transparent border-0 focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][arrival_time]'" x-model="seg.arrival_time" placeholder="09:45"
                                           class="w-20 px-2 py-1 bg-transparent border-0 focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 border-r border-gray-200">
                                    <input type="text" :name="'flight_segments[' + idx + '][flight_status]'" x-model="seg.flight_status" placeholder="HK / OK"
                                           class="w-24 px-2 py-1 bg-transparent border-0 font-bold text-emerald-700 focus:ring-1 focus:ring-primary rounded">
                                </td>
                                <td class="p-1.5 text-center">
                                    <button type="button" @click="removeSegment(idx)" class="text-rose-500 hover:text-rose-700 p-1">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Conditions & Inclusions Toggles (Checkboxes from template) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-xs">3</span>
                <h2 class="font-heading font-bold text-base text-dark">Conditions &amp; Inclusions</h2>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs font-semibold">
                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="has_baggage" value="1" checked class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>With Baggage</span>
                </label>

                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="is_non_refundable" value="1" checked class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>Non Refundable</span>
                </label>

                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="is_non_rebookable" value="1" class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>Non Rebookable</span>
                </label>

                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="has_meals" value="1" class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>With Meals</span>
                </label>

                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="with_rebooking_charge" value="1" checked class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>With Rebooking Charge</span>
                </label>

                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-gray-200 bg-gray-50/50 cursor-pointer hover:bg-gray-100/70">
                    <input type="checkbox" name="with_airport_transfer" value="1" class="rounded text-primary focus:ring-primary w-4 h-4">
                    <span>With Airport Transfer</span>
                </label>
            </div>
        </div>

        <!-- 4. Agent Pricing & Quotation Matrix (Agent sets how much) -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-5">
            <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-accent text-dark flex items-center justify-center font-bold text-xs">4</span>
                    <div>
                        <h2 class="font-heading font-bold text-base text-dark">Agent Pricing &amp; Quotation Matrix</h2>
                        <span class="text-[10px] text-dark/50">Agent decides custom pricing, package rates, and inclusions breakdown</span>
                    </div>
                </div>
                <button type="button" @click="addPricingRow()" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-gray-100 hover:bg-gray-200 text-dark text-xs font-bold transition-colors">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>Add Pricing Line</span>
                </button>
            </div>

            <!-- 3-Column Pricing Grid matching template -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left border border-gray-200 rounded-xl overflow-hidden">
                    <thead class="bg-primary text-white font-heading font-bold uppercase text-[11px]">
                        <tr>
                            <th class="p-3 w-1/3">AIRFARE / ITEM</th>
                            <th class="p-3 w-1/3">PRICE / DETAILS</th>
                            <th class="p-3 w-1/6 text-center">NO OF PAX</th>
                            <th class="p-3 w-1/6 text-right">TOTAL AMOUNT</th>
                            <th class="p-3 w-10 text-center"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template x-for="(item, idx) in pricing" :key="idx">
                            <tr>
                                <td class="p-2">
                                    <input type="text" :name="'pricing_items[' + idx + '][airfare_description]'" x-model="item.airfare_description"
                                           placeholder="e.g. MNL-MPH Round Trip Economy" required
                                           class="w-full px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 font-semibold focus:outline-none focus:ring-1 focus:ring-primary">
                                </td>
                                <td class="p-2">
                                    <input type="text" :name="'pricing_items[' + idx + '][price_details]'" x-model="item.price_details"
                                           placeholder="e.g. ₱7,500/pax + 20kg Baggage"
                                           class="w-full px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 focus:outline-none focus:ring-1 focus:ring-primary">
                                </td>
                                <td class="p-2 text-center">
                                    <input type="number" :name="'pricing_items[' + idx + '][pax_count]'" x-model.number="item.pax_count" min="1" max="50"
                                           @input="calculateGrandTotal()"
                                           class="w-16 px-2 py-2 text-center rounded-lg bg-gray-50 border border-gray-200 font-bold focus:outline-none focus:ring-1 focus:ring-primary">
                                </td>
                                <td class="p-2 text-right">
                                    <div class="relative">
                                        <span class="absolute left-2 top-2 text-dark/40 font-bold">₱</span>
                                        <input type="number" step="0.01" :name="'pricing_items[' + idx + '][amount]'" x-model.number="item.amount"
                                               @input="calculateGrandTotal()"
                                               placeholder="0.00"
                                               class="w-full pl-6 pr-3 py-2 text-right rounded-lg bg-gray-50 border border-gray-200 font-mono font-bold focus:outline-none focus:ring-1 focus:ring-primary">
                                    </div>
                                </td>
                                <td class="p-2 text-center">
                                    <button type="button" @click="removePricingRow(idx)" class="text-rose-500 hover:text-rose-700 p-1">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot class="bg-gray-50 font-bold text-dark border-t-2 border-gray-200">
                        <tr>
                            <td colspan="3" class="p-3 text-right uppercase text-xs tracking-wider">Calculated Grand Total:</td>
                            <td class="p-3 text-right text-base font-mono font-black text-primary">
                                ₱<span x-text="formatNumber(grandTotal)"></span>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Terms & Conditions / Remarks -->
            <div class="pt-2">
                <label class="block text-xs font-bold text-dark/70 mb-1">Payment Terms &amp; Special Conditions</label>
                <textarea name="payment_terms" rows="2"
                          class="w-full px-3.5 py-2 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                          placeholder="e.g. 50% down payment required to lock booking. Balance payable 7 days prior to departure. Non-refundable once ticket is issued."></textarea>
            </div>
        </div>

        <!-- 5. Signatures & Issuance Footer -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-4">
            <div class="border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-xs">5</span>
                <h2 class="font-heading font-bold text-base text-dark">Agreement Signatories</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Handling Agent Name *</label>
                    <input type="text" name="agent_name" value="{{ old('agent_name', $currentUser->name ?? 'Amega Staff') }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Passenger / Client Name *</label>
                    <input type="text" name="passenger_client_name" value="{{ old('passenger_client_name', $ticket->contact_name) }}" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="px-5 py-3 rounded-2xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-100 transition-colors">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-accent text-dark font-heading font-extrabold text-xs uppercase tracking-wider hover:bg-accent-dark transition-all shadow-lg shadow-accent/25">
                <i data-lucide="file-check-2" class="w-4 h-4"></i>
                <span>Generate Official Agreement Document</span>
            </button>
        </div>
    </form>
</div>

<script>
function agreementForm() {
    return {
        segments: @json($flightSegments),
        pricing: @json($pricingItems),
        grandTotal: 0,

        init() {
            this.calculateGrandTotal();
        },

        addSegment() {
            this.segments.push({
                carrier: '',
                flight_number: '',
                flight_class: '',
                day: '',
                month: '',
                from_location: '',
                to_location: '',
                departure_time: '',
                arrival_time: '',
                flight_status: '',
            });
        },

        removeSegment(index) {
            if (this.segments.length > 1) {
                this.segments.splice(index, 1);
            }
        },

        addPricingRow() {
            this.pricing.push({
                airfare_description: '',
                price_details: '',
                pax_count: 1,
                amount: 0,
            });
            this.calculateGrandTotal();
        },

        removePricingRow(index) {
            if (this.pricing.length > 1) {
                this.pricing.splice(index, 1);
                this.calculateGrandTotal();
            }
        },

        calculateGrandTotal() {
            let sum = 0;
            this.pricing.forEach(item => {
                const amt = parseFloat(item.amount) || 0;
                const pax = parseInt(item.pax_count) || 1;
                sum += amt;
            });
            this.grandTotal = sum;
        },

        formatNumber(num) {
            return Number(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
    };
}
</script>
@endsection
