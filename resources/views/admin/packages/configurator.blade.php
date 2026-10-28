@extends('layouts.admin')

@section('title', 'Custom Package for a Client - AMEGA Admin')
@section('page_title', 'Custom Package')

@section('content')
{{-- One client's tailor-made trip, saved as a quotation to follow up under Packages → Custom Requests.
     Website packages are made with New Package → Tour package for the website. --}}
<div x-data="{
    // Custom Package Form State for Live Preview
    custom: {
        client_name: '{{ old('client_name', '') }}',
        client_email: '{{ old('client_email', '') }}',
        client_phone: '{{ old('client_phone', '') }}',
        destination_name: '{{ old('destination_name', '') }}',
        travel_type: '{{ old('travel_type', 'domestic') }}',
        check_in_date: '{{ old('check_in_date', '') }}',
        check_out_date: '{{ old('check_out_date', '') }}',
        duration: '{{ old('duration', '') }}',
        number_of_pax: {{ old('number_of_pax', 2) }},
        adults_count: {{ old('adults_count', 2) }},
        children_count: {{ old('children_count', 0) }},
        infants_count: {{ old('infants_count', 0) }},
        hotel_name: '{{ old('hotel_name', '') }}',
        preferred_hotel: '{{ old('preferred_hotel', '') }}',
        has_breakfast: {{ old('has_breakfast') ? 'true' : 'false' }},
        bed_config: '{{ old('bed_config', 'Twin Beds') }}',
        smoking_preference: '{{ old('smoking_preference', 'non_smoking') }}',
        pet_friendly: {{ old('pet_friendly') ? 'true' : 'false' }},
        has_transportation: {{ old('has_transportation') ? 'true' : 'false' }},
        transportation_type: '{{ old('transportation_type', 'Private Van Transfer') }}',
        special_requests: '{{ old('special_requests', '') }}',
        estimated_budget: '{{ old('estimated_budget', '') }}',
        currency: '{{ old('currency', 'PHP') }}'
    },

    syncCustomPax() {
        this.custom.number_of_pax = (parseInt(this.custom.adults_count) || 0) + (parseInt(this.custom.children_count) || 0) + (parseInt(this.custom.infants_count) || 0);
    }
}" class="space-y-6">

    <div class="bg-white rounded-3xl p-6 border border-gray-200 shadow-card flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-primary mb-1">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
                <span class="uppercase tracking-wider">Travel Packages</span>
            </div>
            <h1 class="font-heading text-2xl font-bold text-dark">Custom Package for a Client</h1>
            <p class="text-xs text-dark/60 mt-1">Build one client's trip (hotel, room, transport and budget) and save it as a quotation to follow up under Custom Requests.</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.packages.index', ['tab' => 'custom']) }}" class="px-4 py-2.5 rounded-xl border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 transition-all flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Custom Requests</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-3 shadow-sm">
            <i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-600 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1.5 shadow-sm">
            <div class="font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside text-rose-700 pl-4 space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Customizer Form -->
            <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-heading text-lg font-bold text-dark">Tailor-Made Package Configurator</h2>
                        <p class="text-xs text-dark/50">Build a bespoke travel and hotel configuration for a client inquiry and generate a trackable quotation.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-accent/20 text-accent-dark border border-accent/30">Custom Quotation</span>
                </div>

                <form method="POST" action="{{ route('admin.packages.configurator.custom') }}" class="space-y-6">
                    @csrf

                    <!-- Client & Lead Info -->
                    <div class="space-y-4">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="user" class="w-3.5 h-3.5"></i>
                            <span>1. Client &amp; Traveler Information</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Client Full Name *</label>
                                <input type="text" name="client_name" x-model="custom.client_name" required
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. Maria Santos">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Email Address *</label>
                                <input type="email" name="client_email" x-model="custom.client_email" required
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="maria@example.com">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Contact Phone</label>
                                <input type="text" name="client_phone" x-model="custom.client_phone"
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="0917-123-4567">
                            </div>
                        </div>
                    </div>

                    <!-- Destination & Party Size -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="map-pin" class="w-3.5 h-3.5"></i>
                            <span>2. Destination &amp; # Pax Party Mix</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Travel Type *</label>
                                <select name="travel_type" x-model="custom.travel_type" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-bold">
                                    <option value="domestic">Domestic Travel</option>
                                    <option value="international">International Travel</option>
                                </select>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination City / Country / Island *</label>
                                <input type="text" name="destination_name" x-model="custom.destination_name" required
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. Coron, Palawan or Tokyo &amp; Kyoto, Japan">
                            </div>
                        </div>

                        <!-- Pax Breakdown -->
                        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                            <span class="text-xs font-bold text-dark block">Passenger (# Pax) Breakdown</span>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[11px] font-semibold text-dark/60 mb-1">Adults (18+)</label>
                                    <input type="number" min="1" max="50" name="adults_count" x-model="custom.adults_count" @input="syncCustomPax()"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-dark/60 mb-1">Children (2-17)</label>
                                    <input type="number" min="0" max="50" name="children_count" x-model="custom.children_count" @input="syncCustomPax()"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold text-dark/60 mb-1">Infants (&lt;2)</label>
                                    <input type="number" min="0" max="50" name="infants_count" x-model="custom.infants_count" @input="syncCustomPax()"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-primary mb-1">Total # Pax</label>
                                    <input type="number" name="number_of_pax" x-model="custom.number_of_pax" readonly
                                           class="w-full px-3 py-2 rounded-xl bg-primary/10 border border-primary/20 text-primary text-xs font-black">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Check-In Date</label>
                                <input type="date" name="check_in_date" x-model="custom.check_in_date"
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Check-Out Date</label>
                                <input type="date" name="check_out_date" x-model="custom.check_out_date"
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Trip Duration</label>
                                <input type="text" name="duration" x-model="custom.duration"
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. 5 Days / 4 Nights">
                            </div>
                        </div>
                    </div>

                    <!-- Hotel & Custom Accommodation Specs -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="bed-double" class="w-3.5 h-3.5"></i>
                            <span>3. Hotel, Room &amp; Bed Configuration</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Hotel Category / Star Rating</label>
                                <input type="text" name="hotel_name" x-model="custom.hotel_name"
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. 4-Star Boutique Resort or 5-Star Luxury">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Preferred Hotel / Chain</label>
                                <input type="text" name="preferred_hotel" x-model="custom.preferred_hotel"
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. The Bellevue Resort or Shangri-La">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Bed Configuration *</label>
                                <select name="bed_config" x-model="custom.bed_config" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="Single Bed">1x Single Bed</option>
                                    <option value="Twin Beds">2x Twin / Single Beds</option>
                                    <option value="Double Bed">1x Double Bed</option>
                                    <option value="Queen Bed">1x Queen Bed</option>
                                    <option value="King Bed">1x King Bed</option>
                                    <option value="Family Bed (2 Queen Beds)">Family Suite (2x Queen Beds)</option>
                                    <option value="Custom / Multiple Rooms">Custom / Multiple Rooms</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Smoking Room Policy</label>
                                <select name="smoking_preference" x-model="custom.smoking_preference" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="non_smoking">Non-Smoking Room (Strict)</option>
                                    <option value="smoking">Smoking Room / Balcony Allowed</option>
                                </select>
                            </div>

                            <div class="flex items-center gap-3 pt-6">
                                <label class="flex items-center gap-2.5 text-xs font-bold text-dark cursor-pointer">
                                    <input type="checkbox" name="has_breakfast" value="1" x-model="custom.has_breakfast" class="w-4 h-4 rounded text-primary">
                                    <span>With Breakfast (Included)</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-6 pt-2">
                            <label class="flex items-center gap-2.5 text-xs font-bold text-dark cursor-pointer">
                                <input type="checkbox" name="pet_friendly" value="1" x-model="custom.pet_friendly" class="w-4 h-4 rounded text-primary">
                                <span>Traveling with Pet(s) (Requires Pet-Friendly Accommodation)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Transportation & Special Requests -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="plane-takeoff" class="w-3.5 h-3.5"></i>
                            <span>4. Transportation, Budget &amp; Special Requests</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all"
                                       :class="custom.has_transportation ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-gray-200 bg-gray-50/50'">
                                    <input type="checkbox" name="has_transportation" value="1" x-model="custom.has_transportation" class="w-4 h-4 rounded text-primary">
                                    <div>
                                        <span class="text-xs font-bold text-dark block">With Transportation Service</span>
                                        <span class="text-[10px] text-dark/50">Transfers or chauffeured van</span>
                                    </div>
                                </label>

                                <div x-show="custom.has_transportation" class="pt-1">
                                    <select name="transportation_type" x-model="custom.transportation_type" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="Roundtrip Airport Transfer">Roundtrip Airport Transfer</option>
                                        <option value="Private Airport Transfer">Private VIP Airport Transfer</option>
                                        <option value="Private Chauffeur Van (Daily)">Private Chauffeur Van (Daily)</option>
                                        <option value="Car Rental">Self-drive Car Rental</option>
                                        <option value="Tour Bus / Coach">Tour Bus / Group Coach</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Client Target Budget / Quotation</label>
                                <div class="flex gap-2">
                                    <select name="currency" x-model="custom.currency" class="w-24 px-2 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-bold">
                                        <option value="PHP">₱ PHP</option>
                                        <option value="USD">$ USD</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" name="estimated_budget" x-model="custom.estimated_budget"
                                           class="flex-1 px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="e.g. 45000">
                                </div>
                                <span class="text-[10px] text-dark/40 mt-1 block">Optional total target budget for quotation</span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Client Special Requests (Dietary, Accessibility, Setup)</label>
                            <textarea name="special_requests" x-model="custom.special_requests" rows="3"
                                      class="w-full px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                      placeholder="e.g. Vegetarian breakfast, anniversary cake in room, ground floor room for senior citizen, pet dog is a small 4kg poodle..."></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Agent Internal Notes (Suppliers, Flight PNR, Price Margin)</label>
                            <textarea name="agent_notes" rows="2"
                                      class="w-full px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                      placeholder="Internal agent notes (not shown to customer)..."></textarea>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="reset" class="px-5 py-2.5 rounded-xl border border-gray-200 text-dark text-xs font-bold hover:bg-gray-50">Reset Form</button>
                        <button type="submit" class="px-7 py-3 rounded-2xl bg-accent text-dark font-heading font-black text-xs uppercase tracking-wider hover:bg-accent-dark transition-all shadow-md flex items-center gap-2 cursor-pointer">
                            <i data-lucide="file-check" class="w-4 h-4"></i>
                            <span>Save Custom Package Quotation</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right 1 Col: Live Custom Quotation Preview Card -->
            <div class="space-y-6">
                <div class="bg-white rounded-3xl p-6 border-2 border-accent/40 shadow-xl space-y-5 sticky top-20">
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                        <span class="text-[10px] font-heading font-extrabold uppercase tracking-wider text-accent-dark flex items-center gap-1">
                            <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                            <span>Custom Quotation Card</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-dark uppercase" x-text="custom.travel_type"></span>
                    </div>

                    <div>
                        <span class="text-[11px] font-bold text-dark/40 uppercase block">Client Name</span>
                        <h3 class="font-heading font-black text-lg text-dark leading-tight" x-text="custom.client_name || 'Prospective Traveler'"></h3>
                        <p class="text-xs text-dark/60 mt-0.5 truncate" x-text="custom.client_email || 'client@example.com'"></p>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-100 text-xs space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-dark/50">Destination:</span>
                            <span class="font-bold text-dark truncate max-w-[140px]" x-text="custom.destination_name || 'Custom Destination'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-dark/50">Travelers:</span>
                            <span class="font-bold text-dark" x-text="custom.number_of_pax + ' Pax (' + custom.adults_count + 'A, ' + custom.children_count + 'C, ' + custom.infants_count + 'I)'"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-dark/50">Duration / Dates:</span>
                            <span class="font-bold text-dark" x-text="custom.duration || (custom.check_in_date ? custom.check_in_date : 'Flexible Dates')"></span>
                        </div>
                    </div>

                    <!-- Hotel Specs -->
                    <div class="space-y-2 text-xs">
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="hotel" class="w-3.5 h-3.5 text-primary"></i> Hotel:</span>
                            <span class="font-bold text-dark truncate max-w-[140px]" x-text="custom.preferred_hotel || custom.hotel_name || 'To Be Arranged'"></span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="coffee" class="w-3.5 h-3.5 text-primary"></i> Breakfast:</span>
                            <span class="font-bold" :class="custom.has_breakfast ? 'text-emerald-700' : 'text-dark/40'" x-text="custom.has_breakfast ? 'Included ✓' : 'No Breakfast'"></span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="bed" class="w-3.5 h-3.5 text-primary"></i> Bed Config:</span>
                            <span class="font-bold text-dark" x-text="custom.bed_config"></span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="cigarette" class="w-3.5 h-3.5 text-primary"></i> Smoking:</span>
                            <span class="font-bold" x-text="custom.smoking_preference === 'non_smoking' ? 'Non-Smoking' : 'Smoking'"></span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="paw-print" class="w-3.5 h-3.5 text-primary"></i> Pet Friendly:</span>
                            <span class="font-bold" :class="custom.pet_friendly ? 'text-emerald-700' : 'text-dark/40'" x-text="custom.pet_friendly ? 'Yes (Pet Allowed)' : 'No'"></span>
                        </div>
                        <div class="flex items-center justify-between py-1 border-b border-gray-100">
                            <span class="text-dark/60 flex items-center gap-1"><i data-lucide="car" class="w-3.5 h-3.5 text-primary"></i> Transport:</span>
                            <span class="font-bold truncate max-w-[130px]" :class="custom.has_transportation ? 'text-emerald-700' : 'text-dark/40'" x-text="custom.has_transportation ? custom.transportation_type : 'None'"></span>
                        </div>
                    </div>

                    <!-- Target Budget -->
                    <div class="p-3.5 rounded-2xl bg-accent/15 border border-accent/30 flex items-center justify-between">
                        <span class="text-xs font-bold text-dark/70">Estimated Budget:</span>
                        <div class="font-mono text-base font-black text-dark">
                            <span x-text="custom.currency === 'USD' ? '$' : '₱'"></span>
                            <span x-text="Number(custom.estimated_budget || 0).toLocaleString()"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

</div>
@endsection
