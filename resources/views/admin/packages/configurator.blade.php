@extends('layouts.admin')

@section('title', 'Package Configurator (Ready-Made & Custom) - AMEGA Admin')
@section('page_title', 'Package Configurator')

@section('content')
<div x-data="{
    activeTab: '{{ request('tab', 'ready-made') }}',
    
    // Ready-Made Form State for Live Preview
    readyMade: {
        title: '{{ old('title', '') }}',
        destination_id: '{{ old('destination_id', '') }}',
        category: '{{ old('category', 'domestic') }}',
        duration: '{{ old('duration', '3 Days / 2 Nights') }}',
        price_amount: '{{ old('price_amount', '') }}',
        price_currency: '{{ old('price_currency', 'PHP') }}',
        hotel_name: '{{ old('hotel_name', '') }}',
        preferred_hotel: '{{ old('preferred_hotel', '') }}',
        has_breakfast: {{ old('has_breakfast') ? 'true' : 'false' }},
        bed_config: '{{ old('bed_config', 'Queen Bed') }}',
        check_in_date: '{{ old('check_in_date', '') }}',
        check_out_date: '{{ old('check_out_date', '') }}',
        smoking_preference: '{{ old('smoking_preference', 'non_smoking') }}',
        pet_friendly: {{ old('pet_friendly') ? 'true' : 'false' }},
        has_transportation: {{ old('has_transportation') ? 'true' : 'false' }},
        transportation_type: '{{ old('transportation_type', 'Roundtrip Airport Transfer') }}',
        number_of_pax: {{ old('number_of_pax', 2) }},
        special_requests: '{{ old('special_requests', '') }}'
    },

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

    <!-- Top Banner & Navigation Header -->
    <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold text-primary mb-1">
                <i data-lucide="sliders" class="w-4 h-4"></i>
                <span class="uppercase tracking-wider">Tour Package Architecture</span>
            </div>
            <h1 class="font-heading text-2xl font-bold text-dark">Package Configurator</h1>
            <p class="text-xs text-dark/60 mt-1">Configure ready-made packages to save directly into the catalog, or build tailor-made custom packages with hotel, bed, smoking, pet, and transportation rules.</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('admin.packages.index') }}" class="px-4 py-2.5 rounded-xl border border-gray-200 text-dark font-bold text-xs hover:bg-gray-50 transition-all flex items-center gap-1.5">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span>Packages Directory</span>
            </a>
            <a href="{{ route('ticketing.tickets.create') }}" class="px-4 py-2.5 rounded-xl bg-navy text-white font-bold text-xs hover:bg-primary transition-all flex items-center gap-1.5 shadow-sm">
                <i data-lucide="ticket" class="w-4 h-4"></i>
                <span>Ticket Booking Wizard</span>
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

    <!-- Mode Selector Tabs -->
    <div class="flex flex-wrap items-center gap-2 bg-gray-100/80 p-1.5 rounded-2xl max-w-2xl border border-gray-200/60">
        <button type="button" @click="activeTab = 'ready-made'"
                class="flex-1 py-2.5 px-4 rounded-xl text-xs font-heading font-extrabold uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="activeTab === 'ready-made' ? 'bg-primary text-white shadow-md' : 'text-dark/60 hover:text-dark hover:bg-white/50'">
            <i data-lucide="package-check" class="w-4 h-4"></i>
            <span>Ready-Made Package</span>
        </button>

        <button type="button" @click="activeTab = 'custom'"
                class="flex-1 py-2.5 px-4 rounded-xl text-xs font-heading font-extrabold uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="activeTab === 'custom' ? 'bg-accent text-dark shadow-md' : 'text-dark/60 hover:text-dark hover:bg-white/50'">
            <i data-lucide="sparkles" class="w-4 h-4"></i>
            <span>Customize Package</span>
        </button>

        <button type="button" @click="activeTab = 'inquiries'"
                class="py-2.5 px-4 rounded-xl text-xs font-heading font-extrabold uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="activeTab === 'inquiries' ? 'bg-white text-dark shadow-md border border-gray-200' : 'text-dark/60 hover:text-dark hover:bg-white/50'">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i>
            <span>Quotations &amp; Inquiries</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-navy text-white">{{ $recentInquiries->total() }}</span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: READY-MADE PACKAGE BUILDER (SAVE TO CATALOG) -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'ready-made'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Left 2 Cols: Form -->
            <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                    <div>
                        <h2 class="font-heading text-lg font-bold text-dark">Build &amp; Save Ready-Made Package</h2>
                        <p class="text-xs text-dark/50">Packages created here are saved into the catalog and immediately available in Step 3 of the Ticket Wizard.</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-100 text-emerald-800 border border-emerald-200">Catalog Template</span>
                </div>

                <form method="POST" action="{{ route('admin.packages.configurator.ready-made') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf

                    <!-- Package Basic Info -->
                    <div class="space-y-4">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="info" class="w-3.5 h-3.5"></i>
                            <span>1. Core Package Details</span>
                        </h3>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Title *</label>
                            <input type="text" name="title" x-model="readyMade.title" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="e.g. Boracay Island 4D3N Luxury Beach Escape">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Destination</label>
                                <select name="destination_id" x-model="readyMade.destination_id" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">None / Standalone Tour</option>
                                    @foreach($destinations as $dest)
                                        <option value="{{ $dest->id }}">{{ $dest->name }} ({{ $dest->type === 'domestic' ? 'Domestic' : 'International' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Category *</label>
                                <select name="category" x-model="readyMade.category" required class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="domestic">Domestic Island</option>
                                    <option value="short_haul">Short Haul (Asia)</option>
                                    <option value="long_haul">Long Haul (Europe/USA)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Duration *</label>
                                <input type="text" name="duration" x-model="readyMade.duration" required
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. 4 Days / 3 Nights">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Starting Price (Per Person) *</label>
                                <div class="flex gap-2">
                                    <select name="price_currency" x-model="readyMade.price_currency" class="w-24 px-2 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-bold">
                                        <option value="PHP">₱ PHP</option>
                                        <option value="USD">$ USD</option>
                                    </select>
                                    <input type="number" step="0.01" min="0" name="price_amount" x-model="readyMade.price_amount" required
                                           class="flex-1 px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                           placeholder="15000">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5"># Pax (Standard Capacity) *</label>
                                <input type="number" min="1" max="50" name="number_of_pax" x-model="readyMade.number_of_pax" required
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Status *</label>
                                <select name="status" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary font-bold">
                                    <option value="active" selected>Active (Available in Wizard)</option>
                                    <option value="draft">Draft (Hidden)</option>
                                    <option value="sold_out">Sold Out</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Hotel & Accommodation Configuration -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="hotel" class="w-3.5 h-3.5"></i>
                            <span>2. Hotel &amp; Room Configuration</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Assigned / Partner Hotel</label>
                                <input type="text" name="hotel_name" x-model="readyMade.hotel_name"
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. Henann Regency Resort &amp; Spa">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Preferred Hotel / Location</label>
                                <input type="text" name="preferred_hotel" x-model="readyMade.preferred_hotel"
                                       class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                       placeholder="e.g. Station 2 Beachfront / Deluxe Sea View">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Bed Configuration</label>
                                <select name="bed_config" x-model="readyMade.bed_config" class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="Single Bed">1x Single Bed</option>
                                    <option value="Twin Beds">2x Twin Beds</option>
                                    <option value="Double Bed">1x Double Bed</option>
                                    <option value="Queen Bed">1x Queen Bed</option>
                                    <option value="King Bed">1x King Bed</option>
                                    <option value="Family Bed (2 Queen Beds)">Family Suite (2x Queen Beds)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Check-In Date (Optional)</label>
                                <input type="date" name="check_in_date" x-model="readyMade.check_in_date"
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Check-Out Date (Optional)</label>
                                <input type="date" name="check_out_date" x-model="readyMade.check_out_date"
                                       class="w-full px-3.5 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <!-- Amenities & Room Rules Toggles -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                            <!-- With Breakfast -->
                            <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all"
                                   :class="readyMade.has_breakfast ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-gray-200 bg-gray-50/50'">
                                <input type="checkbox" name="has_breakfast" value="1" x-model="readyMade.has_breakfast" class="w-4 h-4 rounded text-primary">
                                <div>
                                    <span class="text-xs font-bold text-dark block">With Breakfast</span>
                                    <span class="text-[10px] text-dark/50">Daily breakfast included</span>
                                </div>
                            </label>

                            <!-- Non-Smoking / Smoking -->
                            <div class="p-3 rounded-2xl border border-gray-200 bg-gray-50/50 space-y-1.5">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/60 block">Smoking Policy</span>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-1.5 text-xs font-semibold text-dark cursor-pointer">
                                        <input type="radio" name="smoking_preference" value="non_smoking" x-model="readyMade.smoking_preference" class="text-primary">
                                        <span>Non-Smoking</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 text-xs font-semibold text-dark cursor-pointer">
                                        <input type="radio" name="smoking_preference" value="smoking" x-model="readyMade.smoking_preference" class="text-primary">
                                        <span>Smoking</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Pet Friendly -->
                            <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all"
                                   :class="readyMade.pet_friendly ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-gray-200 bg-gray-50/50'">
                                <input type="checkbox" name="pet_friendly" value="1" x-model="readyMade.pet_friendly" class="w-4 h-4 rounded text-primary">
                                <div>
                                    <span class="text-xs font-bold text-dark block">Pet Friendly</span>
                                    <span class="text-[10px] text-dark/50">Pets permitted</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Transportation & Logistics -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="car" class="w-3.5 h-3.5"></i>
                            <span>3. Transportation &amp; Special Requests</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Transportation Toggle -->
                            <div class="space-y-2">
                                <label class="flex items-center gap-3 p-3.5 rounded-2xl border cursor-pointer transition-all"
                                       :class="readyMade.has_transportation ? 'border-primary bg-primary/5 ring-1 ring-primary/30' : 'border-gray-200 bg-gray-50/50'">
                                    <input type="checkbox" name="has_transportation" value="1" x-model="readyMade.has_transportation" class="w-4 h-4 rounded text-primary">
                                    <div>
                                        <span class="text-xs font-bold text-dark block">With Transportation</span>
                                        <span class="text-[10px] text-dark/50">Airport transfers or chauffeured vehicle</span>
                                    </div>
                                </label>

                                <div x-show="readyMade.has_transportation" class="pt-1">
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Transportation Type</label>
                                    <select name="transportation_type" x-model="readyMade.transportation_type" class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="Roundtrip Airport Transfer">Roundtrip Airport Transfer (Shared / Van)</option>
                                        <option value="Private Airport Transfer">Private VIP Airport Transfer</option>
                                        <option value="Private Chauffeur Van (Daily)">Private Chauffeur Van (Daily Tour)</option>
                                        <option value="Tour Bus / Coach">Tour Bus / Tourist Coach</option>
                                        <option value="Self-drive Car Rental">Self-drive Car Rental</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Special Requests -->
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Special Requests &amp; Room Notes</label>
                                <textarea name="special_requests" x-model="readyMade.special_requests" rows="3"
                                          class="w-full px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                          placeholder="e.g. High floor room, connecting rooms requested, early check-in subject to availability..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Inclusions & Itinerary -->
                    <div class="space-y-4 pt-6 border-t border-gray-100">
                        <h3 class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark/40 flex items-center gap-1.5">
                            <i data-lucide="list-checks" class="w-3.5 h-3.5"></i>
                            <span>4. Package Inclusions &amp; Photo</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Inclusions</label>
                                <textarea name="inclusions" rows="3"
                                          class="w-full px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                          placeholder="• Hotel accommodation&#10;• Daily breakfast&#10;• Roundtrip airport transfer&#10;• Island hopping tour"></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Exclusions</label>
                                <textarea name="exclusions" rows="3"
                                          class="w-full px-4 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                          placeholder="• Personal expenses &amp; tips&#10;• Travel insurance&#10;• Environmental / port fees"></textarea>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Package Photo (Optional)</label>
                            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"
                                   class="w-full text-xs text-dark/70 bg-gray-50 border border-gray-200 rounded-xl p-2 file:mr-3 file:px-4 file:py-2 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:text-xs file:cursor-pointer">
                            <p class="text-[10px] text-dark/45 mt-1">If no photo is uploaded, a high-quality default package banner will be assigned automatically.</p>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" name="is_featured" value="1" checked id="is_featured_ready" class="w-4 h-4 rounded text-primary">
                            <label for="is_featured_ready" class="text-xs font-bold text-dark cursor-pointer">Mark as Featured on Public Tours &amp; Packages Page</label>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="pt-6 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="reset" class="px-5 py-2.5 rounded-xl border border-gray-200 text-dark text-xs font-bold hover:bg-gray-50">Reset Form</button>
                        <button type="submit" class="px-7 py-3 rounded-2xl bg-primary text-white font-heading font-extrabold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md flex items-center gap-2 cursor-pointer">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Save Ready-Made Package to Catalog</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right 1 Col: Live Configuration Summary Card -->
            <div class="space-y-6">
                <div class="bg-navy text-white rounded-3xl p-6 shadow-xl space-y-5 sticky top-20">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <span class="text-[10px] font-heading font-extrabold uppercase tracking-wider text-accent">Live Configuration Card</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/10 uppercase" x-text="readyMade.category"></span>
                    </div>

                    <div>
                        <h3 class="font-heading font-black text-lg text-white leading-tight" x-text="readyMade.title || 'Untitled Ready-Made Package'"></h3>
                        <p class="text-xs text-white/60 mt-1 flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-accent"></i>
                            <span x-text="readyMade.duration"></span>
                            <span class="text-white/30">&bull;</span>
                            <i data-lucide="users" class="w-3.5 h-3.5 text-accent"></i>
                            <span x-text="readyMade.number_of_pax + ' Pax'"></span>
                        </p>
                    </div>

                    <!-- Price Block -->
                    <div class="p-4 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-between">
                        <span class="text-xs text-white/70 font-semibold">Starting Price:</span>
                        <div class="font-mono text-xl font-black text-accent">
                            <span x-text="readyMade.price_currency === 'USD' ? '$' : '₱'"></span>
                            <span x-text="Number(readyMade.price_amount || 0).toLocaleString()"></span>
                            <span class="text-[10px] font-sans font-normal text-white/60">/pax</span>
                        </div>
                    </div>

                    <!-- Configuration Badges -->
                    <div class="space-y-2.5 text-xs">
                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="hotel" class="w-3.5 h-3.5 text-accent"></i> Hotel:</span>
                            <span class="font-bold text-white truncate max-w-[150px]" x-text="readyMade.hotel_name || 'Partner Hotel'"></span>
                        </div>

                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="coffee" class="w-3.5 h-3.5 text-accent"></i> Breakfast:</span>
                            <span class="font-bold" :class="readyMade.has_breakfast ? 'text-emerald-400' : 'text-white/40'" x-text="readyMade.has_breakfast ? 'Included ✓' : 'Not Included'"></span>
                        </div>

                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="bed" class="w-3.5 h-3.5 text-accent"></i> Bed Config:</span>
                            <span class="font-bold text-white" x-text="readyMade.bed_config"></span>
                        </div>

                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="cigarette" class="w-3.5 h-3.5 text-accent"></i> Smoking:</span>
                            <span class="font-bold uppercase text-[11px]" :class="readyMade.smoking_preference === 'non_smoking' ? 'text-emerald-400' : 'text-amber-400'" x-text="readyMade.smoking_preference === 'non_smoking' ? 'Non-Smoking' : 'Smoking'"></span>
                        </div>

                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="paw-print" class="w-3.5 h-3.5 text-accent"></i> Pets Allowed:</span>
                            <span class="font-bold" :class="readyMade.pet_friendly ? 'text-emerald-400' : 'text-white/40'" x-text="readyMade.pet_friendly ? 'Pet-Friendly ✓' : 'No Pets'"></span>
                        </div>

                        <div class="flex items-center justify-between py-1.5 border-b border-white/10">
                            <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="bus" class="w-3.5 h-3.5 text-accent"></i> Transportation:</span>
                            <span class="font-bold truncate max-w-[140px]" :class="readyMade.has_transportation ? 'text-emerald-400' : 'text-white/40'" x-text="readyMade.has_transportation ? readyMade.transportation_type : 'None'"></span>
                        </div>
                    </div>

                    <!-- Step 3 Integration Notice -->
                    <div class="p-3.5 rounded-2xl bg-white/[0.07] border border-white/10 text-[11px] text-white/70 space-y-1">
                        <div class="font-bold text-accent flex items-center gap-1">
                            <i data-lucide="zap" class="w-3.5 h-3.5"></i>
                            <span>Instant Ticketing Integration</span>
                        </div>
                        <p>Once saved, this package will immediately show up under <strong>Step 3: Destination &amp; Package</strong> in the Ticket Booking Wizard.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: CUSTOMIZE PACKAGE CONFIGURATOR (FOR CLIENT QUOTATION) -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'custom'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" class="space-y-6" style="display: none;">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Customizer Form -->
            <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
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

    <!-- ========================================================================= -->
    <!-- TAB 3: CUSTOM INQUIRIES & QUOTATIONS DIRECTORY -->
    <!-- ========================================================================= -->
    <div x-show="activeTab === 'inquiries'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" class="space-y-6" style="display: none;">
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div>
                    <h2 class="font-heading text-lg font-bold text-dark">Custom Package Quotations &amp; Inquiries</h2>
                    <p class="text-xs text-dark/50">List of all tailor-made customer travel packages configured by staff.</p>
                </div>
                <button type="button" @click="activeTab = 'custom'" class="px-4 py-2 bg-accent text-dark font-bold text-xs rounded-xl hover:bg-accent-dark transition-all flex items-center gap-1.5 shadow-sm cursor-pointer">
                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                    <span>New Custom Quotation</span>
                </button>
            </div>

            @if($recentInquiries->isEmpty())
                <div class="text-center py-12 space-y-3">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 text-dark/40 flex items-center justify-center mx-auto">
                        <i data-lucide="clipboard-x" class="w-6 h-6"></i>
                    </div>
                    <h4 class="font-heading font-bold text-sm text-dark">No Custom Quotations Yet</h4>
                    <p class="text-xs text-dark/50 max-w-sm mx-auto">Click the button below to build your first tailored package quotation with hotel, bed, smoking, pet, and transportation preferences.</p>
                    <button type="button" @click="activeTab = 'custom'" class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold text-xs hover:bg-navy transition-all cursor-pointer">
                        Configure Custom Package
                    </button>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-gray-200 text-dark font-extrabold uppercase tracking-wider">
                                <th class="pb-3 px-3">Reference #</th>
                                <th class="pb-3 px-3">Client</th>
                                <th class="pb-3 px-3">Destination</th>
                                <th class="pb-3 px-3">Hotel &amp; Room</th>
                                <th class="pb-3 px-3"># Pax</th>
                                <th class="pb-3 px-3">Budget</th>
                                <th class="pb-3 px-3">Status</th>
                                <th class="pb-3 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($recentInquiries as $inq)
                                <tr class="hover:bg-gray-50/70 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-primary">
                                        <a href="{{ route('admin.packages.custom-inquiries.show', $inq) }}" class="hover:underline">
                                            {{ $inq->reference_number }}
                                        </a>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-bold text-dark">{{ $inq->client_name }}</div>
                                        <div class="text-[11px] text-dark/50">{{ $inq->client_email }}</div>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-semibold text-dark">{{ $inq->destination_name ?: 'Flexible' }}</div>
                                        <span class="text-[10px] uppercase font-bold text-dark/40">{{ $inq->travel_type }}</span>
                                    </td>
                                    <td class="py-3 px-3">
                                        <div class="font-semibold text-dark truncate max-w-[150px]">{{ $inq->hotel_name ?: ($inq->preferred_hotel ?: 'Standard') }}</div>
                                        <div class="text-[10px] text-dark/50">{{ $inq->bed_config }} &bull; {{ $inq->has_breakfast ? 'w/ Bfast' : 'No Bfast' }}</div>
                                    </td>
                                    <td class="py-3 px-3 font-bold text-dark">
                                        {{ $inq->number_of_pax }} Pax
                                    </td>
                                    <td class="py-3 px-3 font-mono font-bold text-dark">
                                        {{ $inq->formatted_budget }}
                                    </td>
                                    <td class="py-3 px-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border {{ $inq->status_badge_class }}">
                                            {{ $inq->status }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('admin.packages.custom-inquiries.show', $inq) }}"
                                               class="p-1.5 rounded-lg border border-gray-200 text-dark/70 hover:bg-gray-100 hover:text-dark transition-all"
                                               title="View Quotation Card">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </a>
                                            <form method="POST" action="{{ route('admin.packages.custom-inquiries.destroy', $inq) }}"
                                                  onsubmit="return confirm('Delete this custom quotation inquiry?');"
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1.5 rounded-lg border border-gray-200 text-rose-600 hover:bg-rose-50 transition-all cursor-pointer" title="Delete">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    {{ $recentInquiries->links() }}
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
