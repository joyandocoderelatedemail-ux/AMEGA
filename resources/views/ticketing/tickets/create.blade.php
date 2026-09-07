@extends('layouts.ticketing')

@section('title', 'New Ticket Booking Wizard - AMEGA')

@section('content')
<div class="max-w-5xl mx-auto space-y-6"
     x-data="bookingWizard({
         destinations: {{ Js::from($destinations) }},
         domesticDestinations: {{ Js::from($domesticDestinations ?? []) }},
         internationalDestinations: {{ Js::from($internationalDestinations ?? []) }},
         packages: {{ Js::from($packages) }}
     })">
    
    <!-- Top Step Bar & Progress Header -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider"
                          :class="formData.travel_type === 'international' ? 'bg-accent/15 text-dark' : 'bg-primary/10 text-primary'">
                        <i :data-lucide="formData.travel_type === 'international' ? 'globe' : 'palmtree'" class="w-3.5 h-3.5"></i>
                        <span x-text="formData.travel_type === 'international' ? 'International Tour Booking' : 'Domestic Tour Booking'"></span>
                    </span>

                    <span x-show="draftSaved" class="text-[11px] font-bold text-emerald-600 flex items-center gap-1">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        <span>Draft Saved</span>
                    </span>
                </div>

                <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight mt-1.5">
                    <span x-text="activeStepTitles[currentStep - 1]"></span>
                </h1>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" @click="clearDraft()" x-show="hasDraft" title="Clear saved draft and start fresh"
                        class="text-[11px] font-bold text-dark/40 hover:text-rose-600 underline transition-colors">
                    Reset Form
                </button>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
                    <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30" x-text="currentStep"></span>
                    <span class="text-xs font-bold text-dark/40" x-text="'of ' + totalSteps"></span>
                </div>
            </div>
        </div>

        <!-- Progress Steps Indicators -->
        <div class="grid gap-1.5" :style="'grid-template-columns: repeat(' + totalSteps + ', minmax(0, 1fr))'">
            <template x-for="(step, idx) in activeSteps" :key="idx">
                <div class="flex flex-col gap-1.5 cursor-pointer group" @click="goToStep(idx + 1)">
                    <div class="h-2 rounded-full transition-all duration-300"
                         :class="{
                             'bg-accent': currentStep === (idx + 1),
                             'bg-primary': currentStep > (idx + 1),
                             'bg-gray-200': currentStep < (idx + 1)
                         }"></div>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block"
                          :class="{
                              'text-accent font-extrabold': currentStep === (idx + 1),
                              'text-primary font-bold': currentStep > (idx + 1),
                              'text-dark/40': currentStep < (idx + 1)
                          }" x-text="step"></span>
                </div>
            </template>
        </div>
    </div>

    <!-- Server Validation Error Alerts -->
    @if ($errors->any())
        <div class="bg-rose-50 border-2 border-rose-300 rounded-3xl p-6 shadow-sm space-y-3">
            <div class="flex items-center gap-2.5 text-rose-800 font-heading font-extrabold text-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 shrink-0"></i>
                <span>Please correct the following errors before issuing the ticket:</span>
            </div>
            <ul class="list-disc list-inside text-xs text-rose-700 font-semibold space-y-1 pl-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-300 text-rose-800 text-xs font-bold flex items-center gap-2">
            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Main Wizard Form Container -->
    <form method="POST" action="{{ route('ticketing.tickets.store') }}" enctype="multipart/form-data" novalidate id="bookingWizardForm" @submit="validateSubmission($event)">
        @csrf

        <!-- Hidden input for JSON/State -->
        <input type="hidden" name="travel_type" x-model="formData.travel_type">
        <input type="hidden" name="package_type" x-model="formData.package_type">
        <input type="hidden" name="total_passengers" x-model="formData.total_passengers">

        <!-- ========================================================================= -->
        <!-- STEP 1: DESTINATION & CATEGORY (Domestic & International) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 1" x-transition.opacity.duration.300ms class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 1: Destination &amp; Category</h2>
                    <p class="text-xs text-dark/50">Choose between Domestic Philippine Tours or International Tour Booking</p>
                </div>

                <!-- Category Selection Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Option 1: Domestic -->
                    <label class="relative flex flex-col p-6 rounded-3xl border-2 cursor-pointer transition-all hover:shadow-md"
                           :class="formData.travel_type === 'domestic' ? 'border-primary bg-primary/5 ring-2 ring-primary/20 shadow-md' : 'border-gray-200 hover:border-gray-300 bg-white'">
                        <input type="radio" name="_travel_type_radio" value="domestic" x-model="formData.travel_type" @change="onTravelTypeChange('domestic')" class="sr-only">
                        <div class="flex items-start justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-primary text-white flex items-center justify-center shadow-md">
                                <i data-lucide="palmtree" class="w-6 h-6"></i>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">
                                Active • Phase 1
                            </span>
                        </div>
                        <div class="mt-4 space-y-1">
                            <h3 class="font-heading font-bold text-base text-dark">Domestic Philippine Tours</h3>
                            <p class="text-xs text-dark/60 leading-relaxed">Local flight ticketing, island packages, and domestic travel requirements across the Philippines.</p>
                        </div>
                    </label>

                    <!-- Option 2: International (Phase 2 Active) -->
                    <label class="relative flex flex-col p-6 rounded-3xl border-2 cursor-pointer transition-all hover:shadow-md"
                           :class="formData.travel_type === 'international' ? 'border-accent bg-accent/5 ring-2 ring-accent/30 shadow-md' : 'border-gray-200 hover:border-gray-300 bg-white'">
                        <input type="radio" name="_travel_type_radio" value="international" x-model="formData.travel_type" @change="onTravelTypeChange('international')" class="sr-only">
                        <div class="flex items-start justify-between">
                            <div class="w-12 h-12 rounded-2xl bg-accent text-dark flex items-center justify-center shadow-md">
                                <i data-lucide="globe-2" class="w-6 h-6"></i>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-blue-100 text-blue-800">
                                International • Phase 2
                            </span>
                        </div>
                        <div class="mt-4 space-y-1">
                            <h3 class="font-heading font-bold text-base text-dark">International Tour Booking</h3>
                            <p class="text-xs text-dark/60 leading-relaxed">Worldwide flight ticketing, visa assistance, 6-month passport verification, international insurance &amp; add-on services.</p>
                        </div>
                    </label>
                </div>

                <!-- International Destination Fields (Prompt Step 1: Destination Country, City, Airport, Airline) -->
                <div x-show="formData.travel_type === 'international'" class="space-y-5 pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-heading font-bold text-sm text-dark">International Destination Details</h3>
                            <p class="text-xs text-dark/50">Specify the destination country, city, arrival airport, and preferred airline</p>
                        </div>
                    </div>

                    <!-- Popular Preset Quick Select -->
                    <div>
                        <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider block mb-2">Quick Select Popular Destination:</span>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="pdest in popularDestinations" :key="pdest.city">
                                <button type="button" @click="selectPresetDestination(pdest)"
                                        class="px-3 py-1.5 rounded-xl border text-xs font-bold transition-all"
                                        :class="formData.destination_city === pdest.city ? 'border-primary bg-primary text-white shadow-sm' : 'border-gray-200 hover:border-primary bg-gray-50 text-dark'">
                                    <span x-text="pdest.flag + ' ' + pdest.city + ', ' + pdest.country"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Destination Country *</label>
                            <input type="text" name="destination_country" x-model="formData.destination_country"
                                   @input="saveDraft()"
                                   placeholder="e.g. Japan, France, UAE"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Destination City *</label>
                            <input type="text" name="destination_city" x-model="formData.destination_city"
                                   @input="onCityInput()"
                                   placeholder="e.g. Tokyo, Paris, Dubai"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Arrival Airport *</label>
                            <input type="text" name="arrival_airport" x-model="formData.arrival_airport"
                                   @input="saveDraft()"
                                   placeholder="e.g. NRT (Narita), CDG (Paris)"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Preferred Airline (Optional)</label>
                            <input type="text" name="preferred_airline" x-model="formData.preferred_airline"
                                   @input="saveDraft()"
                                   placeholder="e.g. PAL, Emirates, Singapore Airlines"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>

                <!-- Package Option Selector -->
                <div class="space-y-4 pt-4 border-t border-gray-100">
                    <h3 class="font-heading font-bold text-sm text-dark">Package Option</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="flex items-center gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                               :class="formData.package_type === 'without_package' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-gray-200 bg-white'">
                            <input type="radio" name="_package_type_radio" value="without_package" x-model="formData.package_type" class="sr-only">
                            <div class="w-5 h-5 rounded-full border-2 border-primary flex items-center justify-center">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="formData.package_type === 'without_package'"></span>
                            </div>
                            <div>
                                <span class="font-bold text-xs text-dark block">Flight Only / Customized Route</span>
                                <span class="text-[11px] text-dark/50">Custom agent quotation &amp; ticketing</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                               :class="formData.package_type === 'with_package' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-gray-200 bg-white'">
                            <input type="radio" name="_package_type_radio" value="with_package" x-model="formData.package_type" class="sr-only">
                            <div class="w-5 h-5 rounded-full border-2 border-primary flex items-center justify-center">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="formData.package_type === 'with_package'"></span>
                            </div>
                            <div>
                                <span class="font-bold text-xs text-dark block">Pre-bundled Tour Package</span>
                                <span class="text-[11px] text-dark/50">Includes hotel, itinerary &amp; excursions</span>
                            </div>
                        </label>
                    </div>

                    <!-- Package Dropdown if selected -->
                    <div x-show="formData.package_type === 'with_package'" class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                        <label class="block text-xs font-bold text-dark/70 mb-1">Select Active Package</label>
                        <select name="travel_package_id" x-model="formData.travel_package_id" @change="onPackageSelect($event)"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- Choose Travel Package --</option>
                            <template x-for="pkg in activePackages" :key="pkg.id">
                                <option :value="pkg.id" x-text="pkg.title + ' (' + (pkg.destination ? pkg.destination.name : 'All') + ' - ' + pkg.price + ')'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-end pt-4 border-t border-gray-100">
                    <button type="button" @click="validateStep1() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Trip Details</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 2: TRIP DETAILS (Trip Type, Class, Dates, Flight Time) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 2" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 2: Trip &amp; Flight Specifications</h2>
                    <p class="text-xs text-dark/50">Configure trip type, travel class, flight times, and schedule dates</p>
                </div>

                <!-- Trip Type (One Way, Round Trip, Multi-City) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-dark/70">Trip Type *</label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.trip_type === 'round_trip' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="trip_type" value="round_trip" x-model="formData.trip_type" @change="saveDraft()" class="sr-only">
                            <i data-lucide="repeat" class="w-4 h-4"></i>
                            <span class="text-xs">Round Trip</span>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.trip_type === 'one_way' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="trip_type" value="one_way" x-model="formData.trip_type" @change="saveDraft()" class="sr-only">
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            <span class="text-xs">One Way</span>
                        </label>

                        <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.trip_type === 'multi_city' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="trip_type" value="multi_city" x-model="formData.trip_type" @change="saveDraft()" class="sr-only">
                            <i data-lucide="git-branch" class="w-4 h-4"></i>
                            <span class="text-xs">Multi-City</span>
                        </label>
                    </div>
                </div>

                <!-- Travel Class (Economy, Premium Economy, Business, First Class) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-dark/70">Travel Class *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.travel_class === 'economy' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="travel_class" value="economy" x-model="formData.travel_class" @change="saveDraft()" class="sr-only">
                            <span class="text-xs">Economy</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.travel_class === 'premium_economy' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="travel_class" value="premium_economy" x-model="formData.travel_class" @change="saveDraft()" class="sr-only">
                            <span class="text-xs">Premium Economy</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.travel_class === 'business' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="travel_class" value="business" x-model="formData.travel_class" @change="saveDraft()" class="sr-only">
                            <span class="text-xs">Business</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.travel_class === 'first_class' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="travel_class" value="first_class" x-model="formData.travel_class" @change="saveDraft()" class="sr-only">
                            <span class="text-xs">First Class</span>
                        </label>
                    </div>
                </div>

                <!-- Preferred Flight Time (Anytime, Morning, Afternoon, Evening) -->
                <div class="space-y-2">
                    <label class="block text-xs font-bold text-dark/70">Preferred Flight Time *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.preferred_flight_time === 'anytime' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="preferred_flight_time" value="anytime" x-model="formData.preferred_flight_time" @change="saveDraft()" class="sr-only">
                            <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                            <span class="text-xs">Anytime</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.preferred_flight_time === 'morning' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="preferred_flight_time" value="morning" x-model="formData.preferred_flight_time" @change="saveDraft()" class="sr-only">
                            <i data-lucide="sunrise" class="w-3.5 h-3.5"></i>
                            <span class="text-xs">Morning</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.preferred_flight_time === 'afternoon' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="preferred_flight_time" value="afternoon" x-model="formData.preferred_flight_time" @change="saveDraft()" class="sr-only">
                            <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                            <span class="text-xs">Afternoon</span>
                        </label>

                        <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                               :class="formData.preferred_flight_time === 'evening' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                            <input type="radio" name="preferred_flight_time" value="evening" x-model="formData.preferred_flight_time" @change="saveDraft()" class="sr-only">
                            <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                            <span class="text-xs">Evening</span>
                        </label>
                    </div>
                </div>

                <!-- Origin & Destination -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Origin Airport / City *</label>
                        <input type="text" name="origin" x-model="formData.origin" @input="saveDraft()"
                               placeholder="e.g. Manila (MNL) or Clark (CRK)"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Destination *</label>
                        <input type="text" name="destination" x-model="formData.destination" @input="saveDraft()"
                               placeholder="e.g. Tokyo, Coron, Paris"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <!-- Travel Dates -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Departure Date *</label>
                        <input type="date" name="departure_date" x-model="formData.departure_date" @change="saveDraft()"
                               :min="new Date().toISOString().split('T')[0]"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-dark/40 mt-1 block">Departure date cannot be in the past</span>
                    </div>

                    <div x-show="formData.trip_type === 'round_trip'">
                        <label class="block text-xs font-bold text-dark/70 mb-1">Return Date *</label>
                        <input type="date" name="return_date" x-model="formData.return_date" @change="saveDraft()"
                               :min="formData.departure_date || new Date().toISOString().split('T')[0]"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-dark/40 mt-1 block">Must be later than departure date</span>
                    </div>
                </div>

                <!-- Multi-City Segments Builder if Multi-City selected -->
                <div x-show="formData.trip_type === 'multi_city'" class="space-y-3 pt-3 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <h4 class="font-heading font-bold text-xs text-dark uppercase tracking-wider">Multi-City Segments</h4>
                        <button type="button" @click="addSegment()" class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-bold text-dark transition-colors">
                            + Add Segment
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(seg, sidx) in formData.multi_city_segments" :key="sidx">
                            <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                <input type="text" :name="'multi_city_segments[' + sidx + '][from]'" x-model="seg.from" placeholder="From (e.g. MNL)" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                <input type="text" :name="'multi_city_segments[' + sidx + '][to]'" x-model="seg.to" placeholder="To (e.g. NRT)" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                <input type="date" :name="'multi_city_segments[' + sidx + '][date]'" x-model="seg.date" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                <button type="button" @click="removeSegment(sidx)" class="text-rose-600 hover:text-rose-800 text-xs font-bold justify-self-end">Remove</button>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep2() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Passenger Details</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 3: PASSENGER INFORMATION (Counts & Manifest Details) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 3" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 3: Passenger Information Manifest</h2>
                    <p class="text-xs text-dark/50">Specify passenger counts and fill in biographical details (Adults + Children + Infants = Total)</p>
                </div>

                <!-- Passenger Count Adjusters -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 p-5 rounded-2xl bg-gray-50 border border-gray-200">
                    <!-- Total Badge -->
                    <div class="flex flex-col justify-center">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40">Total Manifest</span>
                        <div class="text-2xl font-heading font-black text-primary mt-0.5" x-text="formData.total_passengers + ' Pax'"></div>
                    </div>

                    <!-- Adults -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white border border-gray-200">
                        <div>
                            <span class="text-xs font-bold text-dark block">Adults (12+)</span>
                            <span class="text-[10px] text-dark/40">Min 1</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="decrementPassenger('adults_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">-</button>
                            <span class="w-6 text-center font-bold text-xs" x-text="formData.adults_count"></span>
                            <button type="button" @click="incrementPassenger('adults_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">+</button>
                        </div>
                    </div>

                    <!-- Children -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white border border-gray-200">
                        <div>
                            <span class="text-xs font-bold text-dark block">Children (2-11)</span>
                            <span class="text-[10px] text-dark/40">Reduced fare</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="decrementPassenger('children_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">-</button>
                            <span class="w-6 text-center font-bold text-xs" x-text="formData.children_count"></span>
                            <button type="button" @click="incrementPassenger('children_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">+</button>
                        </div>
                    </div>

                    <!-- Infants -->
                    <div class="flex items-center justify-between p-3 rounded-xl bg-white border border-gray-200">
                        <div>
                            <span class="text-xs font-bold text-dark block">Infants (0-2)</span>
                            <span class="text-[10px] text-dark/40">Lap infant</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="decrementPassenger('infants_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">-</button>
                            <span class="w-6 text-center font-bold text-xs" x-text="formData.infants_count"></span>
                            <button type="button" @click="incrementPassenger('infants_count')" class="w-7 h-7 rounded-lg bg-gray-100 hover:bg-gray-200 font-bold text-xs flex items-center justify-center">+</button>
                        </div>
                    </div>
                </div>

                <!-- Hidden counters for POST -->
                <input type="hidden" name="adults_count" :value="formData.adults_count">
                <input type="hidden" name="children_count" :value="formData.children_count">
                <input type="hidden" name="infants_count" :value="formData.infants_count">

                <!-- Passenger Details Cards -->
                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50/40 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-200/80 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <span class="font-heading font-bold text-xs text-dark" x-text="'Passenger #' + (idx + 1) + ' (' + p.passenger_type.toUpperCase() + ')'"></span>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                      :class="p.passenger_type === 'adult' ? 'bg-blue-100 text-blue-800' : (p.passenger_type === 'child' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                                      x-text="p.passenger_type"></span>
                            </div>

                            <input type="hidden" :name="'passengers[' + idx + '][passenger_type]'" :value="p.passenger_type">

                            <!-- Names -->
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">First Name *</label>
                                    <input type="text" :name="'passengers[' + idx + '][first_name]'" x-model="p.first_name" @input="saveDraft()"
                                           placeholder="e.g. Juan"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Middle Name</label>
                                    <input type="text" :name="'passengers[' + idx + '][middle_name]'" x-model="p.middle_name" @input="saveDraft()"
                                           placeholder="Optional"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Last Name *</label>
                                    <input type="text" :name="'passengers[' + idx + '][last_name]'" x-model="p.last_name" @input="saveDraft()"
                                           placeholder="e.g. Dela Cruz"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Suffix</label>
                                    <input type="text" :name="'passengers[' + idx + '][suffix]'" x-model="p.suffix" @input="saveDraft()"
                                           placeholder="Jr., III, etc."
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary">
                                </div>
                            </div>

                            <!-- DOB, Gender, Nationality, Passport Info -->
                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Date of Birth *</label>
                                    <input type="date" :name="'passengers[' + idx + '][date_of_birth]'" x-model="p.date_of_birth" @change="saveDraft()"
                                           :max="new Date().toISOString().split('T')[0]"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary">
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Gender *</label>
                                    <select :name="'passengers[' + idx + '][gender]'" x-model="p.gender" @change="saveDraft()"
                                            class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary">
                                        <option value="">-- Select --</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Nationality *</label>
                                    <select :name="'passengers[' + idx + '][nationality_type]'" x-model="p.nationality_type" @change="saveDraft()"
                                            class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary">
                                        <option value="filipino">Filipino (PH)</option>
                                        <option value="foreign_national">Foreign National</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Passport Number *</label>
                                    <input type="text" :name="'passengers[' + idx + '][passport_number]'" x-model="p.passport_number" @input="saveDraft()"
                                           placeholder="e.g. P1234567A"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-mono font-bold focus:ring-1 focus:ring-primary">
                                </div>
                            </div>

                            <!-- Passport Expiration Date & 6-month Realtime Warning -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Passport Expiration Date *</label>
                                    <input type="date" :name="'passengers[' + idx + '][passport_expiry_date]'" x-model="p.passport_expiry_date" @change="checkPassportValidity(p); saveDraft();"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary">
                                </div>

                                <!-- Passport Alert Box -->
                                <div class="flex items-center">
                                    <div x-show="p.passport_warning" class="w-full p-2.5 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-2">
                                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                                        <div class="text-[11px] font-bold text-rose-800 leading-tight">
                                            Passport must be renewed before travel. (At least 6 months validity required from departure).
                                        </div>
                                    </div>
                                    <div x-show="!p.passport_warning && p.passport_expiry_date" class="w-full p-2.5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-2">
                                        <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                        <span class="text-[11px] font-bold text-emerald-800">Valid Passport (&gt; 6 months before travel)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep3() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Passport Validation</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 4: PASSPORT VALIDATION & UPLOADS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 4" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 4: Passport Validation &amp; Upload</h2>
                    <p class="text-xs text-dark/50">Mandatory passport photo/scan upload and 6-month validity rule verification</p>
                </div>

                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border-2 space-y-3 transition-all"
                             :class="p.passport_file_name ? 'border-emerald-300 bg-emerald-50/20' : (p.passport_warning ? 'border-rose-300 bg-rose-50/20' : 'border-gray-200 bg-white')">
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <span class="font-bold text-xs text-dark" x-text="p.first_name + ' ' + p.last_name + ' (' + (p.passport_number || 'No Passport #') + ')'"></span>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                      :class="p.passport_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                      x-text="p.passport_file_name ? '✓ Passport Attached' : 'Upload Required *'"></span>
                            </div>

                            <!-- Real-time Warning Banner if <= 6 months -->
                            <div x-show="p.passport_warning" class="p-3 rounded-xl bg-rose-100/70 border border-rose-300 flex items-center gap-2">
                                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-700 shrink-0"></i>
                                <span class="text-xs font-bold text-rose-900">
                                    Warning: Passport must be renewed before travel. Validity is less than six (6) months from departure.
                                </span>
                            </div>

                            <!-- Upload Box with Drag-and-drop -->
                            <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-colors"
                                 :class="p.passport_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                <input type="file" :name="'passengers[' + idx + '][passport_file]'" accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf"
                                       @change="onFileChange($event, p, 'passport_file_name')"
                                       class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-navy cursor-pointer">
                                <div x-show="p.passport_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1">
                                    <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                    <span x-text="'Attached: ' + p.passport_file_name"></span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep4() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Visa Requirements</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 5: VISA REQUIREMENTS (Already Has Visa, Needs Assistance, Not Required) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 5" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 5: Visa Requirements &amp; Assistance</h2>
                    <p class="text-xs text-dark/50">Determine whether the destination requires a visa and capture visa documents</p>
                </div>

                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50/50 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <span class="font-bold text-xs text-dark" x-text="p.first_name + ' ' + p.last_name"></span>
                                </div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Visa Assessment</span>
                            </div>

                            <!-- Visa Status Radio Group -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="p.visa_status === 'already_has_visa' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                    <input type="radio" :name="'passengers[' + idx + '][visa_status]'" value="already_has_visa" x-model="p.visa_status" @change="saveDraft()" class="sr-only">
                                    <i data-lucide="file-check-2" class="w-4 h-4"></i>
                                    <span class="text-xs">Already Has Visa</span>
                                </label>

                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="p.visa_status === 'needs_assistance' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                    <input type="radio" :name="'passengers[' + idx + '][visa_status]'" value="needs_assistance" x-model="p.visa_status" @change="saveDraft()" class="sr-only">
                                    <i data-lucide="help-circle" class="w-4 h-4"></i>
                                    <span class="text-xs">Needs Visa Assistance</span>
                                </label>

                                <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                       :class="p.visa_status === 'visa_not_required' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                    <input type="radio" :name="'passengers[' + idx + '][visa_status]'" value="visa_not_required" x-model="p.visa_status" @change="saveDraft()" class="sr-only">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                    <span class="text-xs">Visa Not Required</span>
                                </label>
                            </div>

                            <!-- If "Already Has Visa" -> Require Visa copy upload -->
                            <div x-show="p.visa_status === 'already_has_visa'" class="p-4 rounded-xl bg-white border border-gray-200 space-y-2">
                                <label class="block text-xs font-bold text-dark">Upload Visa Copy *</label>
                                <input type="file" :name="'passengers[' + idx + '][visa_file]'" accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf"
                                       @change="onFileChange($event, p, 'visa_file_name')"
                                       class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-navy cursor-pointer">
                                <div x-show="p.visa_file_name" class="text-[11px] font-bold text-emerald-700" x-text="'Attached: ' + p.visa_file_name"></div>
                            </div>

                            <!-- If "Needs Visa Assistance" -> Collect type, stay, purpose + Upload photo & supporting docs -->
                            <div x-show="p.visa_status === 'needs_assistance'" class="p-4 rounded-xl bg-white border border-gray-200 space-y-3">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-bold text-dark/70 mb-1">Visa Type *</label>
                                        <input type="text" :name="'passengers[' + idx + '][visa_assistance_type]'" x-model="p.visa_assistance_type" placeholder="e.g. Tourist Single Entry"
                                               class="w-full px-3 py-1.5 rounded-lg border border-gray-200 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-dark/70 mb-1">Intended Stay (Days) *</label>
                                        <input type="number" :name="'passengers[' + idx + '][intended_stay_days]'" x-model="p.intended_stay_days" min="1" placeholder="e.g. 15"
                                               class="w-full px-3 py-1.5 rounded-lg border border-gray-200 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-bold text-dark/70 mb-1">Purpose of Travel *</label>
                                        <input type="text" :name="'passengers[' + idx + '][purpose_of_travel]'" x-model="p.purpose_of_travel" placeholder="e.g. Holiday Tourism, Family Visit"
                                               class="w-full px-3 py-1.5 rounded-lg border border-gray-200 text-xs">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                    <div>
                                        <label class="block text-xs font-bold text-dark mb-1">Upload Passport Photo (2x2 white bg) *</label>
                                        <input type="file" :name="'passengers[' + idx + '][passport_photo_file]'" accept="image/*"
                                               @change="onFileChange($event, p, 'passport_photo_file_name')"
                                               class="w-full text-xs text-dark/70 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white">
                                        <div x-show="p.passport_photo_file_name" class="text-[10px] font-bold text-emerald-700 mt-1" x-text="'Photo: ' + p.passport_photo_file_name"></div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-dark mb-1">Upload Supporting Documents (COE / Bank Cert) *</label>
                                        <input type="file" :name="'passengers[' + idx + '][supporting_doc_file]'" accept="image/*,application/pdf"
                                               @change="onFileChange($event, p, 'supporting_doc_file_name')"
                                               class="w-full text-xs text-dark/70 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white">
                                        <div x-show="p.supporting_doc_file_name" class="text-[10px] font-bold text-emerald-700 mt-1" x-text="'Doc: ' + p.supporting_doc_file_name"></div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </template>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep5() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Travel Insurance</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 6: TRAVEL INSURANCE -->
        <!-- ========================================================================= -->
        <div x-show="formData.travel_type === 'international' && currentStep === 6" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 6: Travel Insurance</h2>
                    <p class="text-xs text-dark/50">Comprehensive medical, trip disruption, and emergency travel insurance coverage</p>
                </div>

                <!-- Insurance Checkbox -->
                <label class="flex items-center gap-3 p-5 rounded-2xl border-2 cursor-pointer transition-all"
                       :class="formData.has_insurance ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 bg-white'">
                    <input type="checkbox" name="has_insurance" value="1" x-model="formData.has_insurance" @change="saveDraft()" class="w-5 h-5 rounded text-primary focus:ring-primary">
                    <div>
                        <span class="font-heading font-bold text-sm text-dark block">Include Travel Insurance Protection</span>
                        <span class="text-xs text-dark/60">Covers international emergency medical expenses, luggage delay, and flight cancellations.</span>
                    </div>
                </label>

                <!-- Insurance Plans Selection if enabled -->
                <div x-show="formData.has_insurance" class="space-y-4 pt-2">
                    <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">Select Insurance Coverage Plan:</span>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Basic -->
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                               :class="formData.insurance_plan === 'basic' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200 bg-white'">
                            <input type="radio" name="insurance_plan" value="basic" x-model="formData.insurance_plan" @change="saveDraft()" class="sr-only">
                            <div>
                                <div class="font-heading font-bold text-sm text-dark">Basic Plan</div>
                                <div class="text-primary font-bold text-xs mt-0.5">₱950 / pax</div>
                                <ul class="text-[11px] text-dark/60 space-y-1 mt-3">
                                    <li>• Up to $25,000 Medical</li>
                                    <li>• Emergency Evacuation</li>
                                    <li>• 24/7 Hotline</li>
                                </ul>
                            </div>
                        </label>

                        <!-- Standard (Popular) -->
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between relative"
                               :class="formData.insurance_plan === 'standard' ? 'border-accent bg-accent/5 ring-1 ring-accent' : 'border-gray-200 bg-white'">
                            <input type="radio" name="insurance_plan" value="standard" x-model="formData.insurance_plan" @change="saveDraft()" class="sr-only">
                            <span class="absolute -top-2.5 right-4 bg-accent text-dark text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">Most Popular</span>
                            <div>
                                <div class="font-heading font-bold text-sm text-dark">Standard Plan</div>
                                <div class="text-primary font-bold text-xs mt-0.5">₱1,850 / pax</div>
                                <ul class="text-[11px] text-dark/60 space-y-1 mt-3">
                                    <li>• Up to $50,000 Medical</li>
                                    <li>• Trip Cancellation Coverage</li>
                                    <li>• Baggage Loss Protection</li>
                                </ul>
                            </div>
                        </label>

                        <!-- Premium -->
                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between"
                               :class="formData.insurance_plan === 'premium' ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200 bg-white'">
                            <input type="radio" name="insurance_plan" value="premium" x-model="formData.insurance_plan" @change="saveDraft()" class="sr-only">
                            <div>
                                <div class="font-heading font-bold text-sm text-dark">Premium Plan</div>
                                <div class="text-primary font-bold text-xs mt-0.5">₱3,200 / pax</div>
                                <ul class="text-[11px] text-dark/60 space-y-1 mt-3">
                                    <li>• Up to $100,000 Global Medical</li>
                                    <li>• Zero Deductible / All Risks</li>
                                    <li>• Flight Delay &amp; Concierge</li>
                                </ul>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Optional Services</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 7: OPTIONAL SERVICES (Multi-select) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 7" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 7: Optional Travel Services</h2>
                    <p class="text-xs text-dark/50">Allow selecting add-on concierge services for the international tour</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <template x-for="service in availableServices" :key="service.key">
                        <label class="p-4 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between gap-3"
                               :class="formData.selected_services.includes(service.key) ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'">
                            <div class="flex items-start justify-between">
                                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-primary">
                                    <i :data-lucide="service.icon" class="w-5 h-5"></i>
                                </div>
                                <input type="checkbox" :name="'selected_services[]'" :value="service.key"
                                       @change="toggleService(service.key); saveDraft();"
                                       :checked="formData.selected_services.includes(service.key)"
                                       class="rounded text-primary focus:ring-primary w-4 h-4">
                            </div>
                            <div>
                                <span class="font-bold text-xs text-dark block" x-text="service.label"></span>
                                <span class="text-[10px] text-dark/50 block mt-0.5" x-text="service.desc"></span>
                            </div>
                        </label>
                    </template>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Emergency Contact</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 8: EMERGENCY CONTACT -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 8" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 8: Emergency Contact</h2>
                    <p class="text-xs text-dark/50">Primary emergency contact for passengers while traveling abroad</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Emergency Contact Full Name *</label>
                        <input type="text" name="emergency_contact_name" x-model="formData.emergency_contact_name" @input="saveDraft()"
                               placeholder="e.g. Maria Santos"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Relationship to Passenger *</label>
                        <input type="text" name="emergency_contact_relationship" x-model="formData.emergency_contact_relationship" @input="saveDraft()"
                               placeholder="e.g. Spouse, Parent, Sibling"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Mobile / Phone Number *</label>
                        <input type="text" name="emergency_contact_phone" x-model="formData.emergency_contact_phone" @input="saveDraft()"
                               placeholder="e.g. +63 917 123 4567"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-dark/70 mb-1">Email Address (Optional)</label>
                        <input type="email" name="emergency_contact_email" x-model="formData.emergency_contact_email" @input="saveDraft()"
                               placeholder="e.g. maria.santos@example.com"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <!-- Primary Booker Details Confirmation -->
                <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                    <span class="text-xs font-bold text-dark block uppercase tracking-wider text-[10px]">Booking Agent / Client Contact:</span>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <span class="text-[10px] text-dark/50 block">Booker Name:</span>
                            <input type="text" name="contact_name" x-model="formData.contact_name" class="w-full px-2.5 py-1.5 rounded-lg border text-xs font-bold">
                        </div>
                        <div>
                            <span class="text-[10px] text-dark/50 block">Booker Email:</span>
                            <input type="email" name="contact_email" x-model="formData.contact_email" class="w-full px-2.5 py-1.5 rounded-lg border text-xs">
                        </div>
                        <div>
                            <span class="text-[10px] text-dark/50 block">Booker Phone:</span>
                            <input type="text" name="contact_phone" x-model="formData.contact_phone" class="w-full px-2.5 py-1.5 rounded-lg border text-xs font-bold">
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep8() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Special Requests</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 9: SPECIAL REQUESTS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 9" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 9: Special Requests &amp; Assistance</h2>
                    <p class="text-xs text-dark/50">Configure airline accessibility, dietary meals, and seating preferences</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <template x-for="req in specialRequestOptions" :key="req.key">
                        <label class="p-3.5 rounded-2xl border cursor-pointer transition-all flex items-center gap-3"
                               :class="formData.special_requests_list.includes(req.key) ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 bg-white hover:border-gray-300'">
                            <input type="checkbox" :name="'special_requests_list[]'" :value="req.key"
                                   @change="toggleSpecialRequest(req.key); saveDraft();"
                                   :checked="formData.special_requests_list.includes(req.key)"
                                   class="rounded text-primary focus:ring-primary w-4 h-4">
                            <span class="text-xs font-bold text-dark" x-text="req.label"></span>
                        </label>
                    </template>
                </div>

                <div>
                    <label class="block text-xs font-bold text-dark/70 mb-1">Additional Instructions &amp; Seating Notes</label>
                    <textarea name="special_requests" x-model="formData.special_requests" rows="3" @input="saveDraft()"
                              placeholder="Any specific seat rows, baggage weights, connecting airline assistance, or wheelchair escort details..."
                              class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Document Checklist</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 10: DOCUMENT CHECKLIST (Live Upload Verification Matrix) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 10" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 10: Document Checklist Verification</h2>
                    <p class="text-xs text-dark/50">Comprehensive real-time status matrix for all passenger travel credentials</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left border border-gray-200 rounded-2xl overflow-hidden">
                        <thead class="bg-gray-100 text-dark/70 uppercase text-[10px] font-bold">
                            <tr>
                                <th class="p-3">Passenger</th>
                                <th class="p-3 text-center">Passport Scan</th>
                                <th class="p-3 text-center">Visa Document</th>
                                <th class="p-3 text-center">Passport Photo (2x2)</th>
                                <th class="p-3 text-center">Supporting Docs</th>
                                <th class="p-3 text-center">Insurance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <template x-for="(p, idx) in formData.passengers" :key="idx">
                                <tr>
                                    <td class="p-3">
                                        <div class="font-bold text-dark" x-text="p.first_name + ' ' + p.last_name"></div>
                                        <div class="text-[10px] text-dark/40" x-text="'Passport: ' + (p.passport_number || 'N/A')"></div>
                                    </td>

                                    <!-- Passport -->
                                    <td class="p-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                              :class="p.passport_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                              x-text="p.passport_file_name ? 'Uploaded' : 'Missing'"></span>
                                    </td>

                                    <!-- Visa -->
                                    <td class="p-3 text-center">
                                        <template x-if="p.visa_status === 'visa_not_required'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-dark/50 uppercase">Not Required</span>
                                        </template>
                                        <template x-if="p.visa_status === 'already_has_visa'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                  :class="p.visa_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                                  x-text="p.visa_file_name ? 'Uploaded' : 'Missing'"></span>
                                        </template>
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase">Assistance Req.</span>
                                        </template>
                                    </td>

                                    <!-- Passport Photo 2x2 -->
                                    <td class="p-3 text-center">
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                  :class="p.passport_photo_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                                  x-text="p.passport_photo_file_name ? 'Uploaded' : 'Missing'"></span>
                                        </template>
                                        <template x-if="p.visa_status !== 'needs_assistance'">
                                            <span class="text-dark/30">—</span>
                                        </template>
                                    </td>

                                    <!-- Supporting Docs -->
                                    <td class="p-3 text-center">
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                  :class="p.supporting_doc_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                                  x-text="p.supporting_doc_file_name ? 'Uploaded' : 'Missing'"></span>
                                        </template>
                                        <template x-if="p.visa_status !== 'needs_assistance'">
                                            <span class="text-dark/30">—</span>
                                        </template>
                                    </td>

                                    <!-- Insurance Policy -->
                                    <td class="p-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                              :class="formData.has_insurance ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40'"
                                              x-text="formData.has_insurance ? ('Covered (' + formData.insurance_plan + ')') : 'None'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep10() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Review &amp; Pricing Summary</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 11: BOOKING SUMMARY & PRICING (Final Confirmation) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === (formData.travel_type === 'international' ? 11 : 6)" x-transition.opacity.duration.300ms class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Step 11: Booking Summary &amp; Quotation Review</h2>
                    <p class="text-xs text-dark/50">Review all travel specifications, pricing breakdowns, and confirmed passenger manifest</p>
                </div>

                <!-- Trip Header Card -->
                <div class="p-6 rounded-3xl bg-navy text-white space-y-4 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-white/15 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-1 rounded-lg bg-accent text-dark text-xs font-heading font-extrabold uppercase">
                                <span x-text="formData.travel_type === 'international' ? 'International Tour' : 'Domestic Tour'"></span>
                            </span>
                            <span class="text-xs text-white/80 font-bold" x-text="formData.trip_type.replace('_', ' ').toUpperCase()"></span>
                            <span class="text-xs text-white/60" x-text="'• ' + (formData.travel_class ? formData.travel_class.replace('_', ' ').toUpperCase() : 'ECONOMY')"></span>
                        </div>
                        <span class="text-xs font-bold text-accent" x-text="formData.total_passengers + ' Total Passenger(s)'"></span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider">Route</div>
                            <div class="text-sm font-heading font-bold text-white flex items-center gap-2 mt-0.5">
                                <span x-text="formData.origin"></span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-accent"></i>
                                <span x-text="formData.destination || (formData.destination_city + ', ' + formData.destination_country)"></span>
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider">Dates</div>
                            <div class="text-xs font-bold text-white mt-0.5">
                                Dep: <span x-text="formData.departure_date"></span>
                                <span x-show="formData.trip_type === 'round_trip'" x-text="' | Ret: ' + formData.return_date"></span>
                            </div>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider">Emergency Contact</div>
                            <div class="text-xs font-bold text-white mt-0.5 truncate" x-text="formData.emergency_contact_name || 'N/A'"></div>
                            <div class="text-[10px] text-white/60" x-text="formData.emergency_contact_phone"></div>
                        </div>
                    </div>
                </div>

                <!-- Manifest Overview -->
                <div class="space-y-3">
                    <h3 class="font-heading font-bold text-sm text-dark">Passenger Manifest</h3>
                    <div class="divide-y divide-gray-100 border border-gray-200 rounded-2xl overflow-hidden">
                        <template x-for="(p, idx) in formData.passengers" :key="idx">
                            <div class="p-4 bg-white flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div>
                                    <span class="font-bold text-xs text-dark" x-text="(idx + 1) + '. ' + p.first_name + ' ' + p.last_name"></span>
                                    <span class="text-[10px] text-dark/50 ml-2" x-text="'Passport: ' + (p.passport_number || 'N/A')"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase"
                                          :class="p.passport_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                          x-text="p.passport_file_name ? 'Passport ✓' : 'Missing'"></span>
                                    <span x-show="p.visa_status" class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-50 text-blue-800"
                                          x-text="p.visa_status === 'already_has_visa' ? 'Has Visa' : (p.visa_status === 'needs_assistance' ? 'Visa Assist' : 'Visa Free')"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Pricing & Quotation Breakdown (Prompt Step 11: Estimated Fare, Taxes, Visa Fee, Insurance Fee, Other Charges, Grand Total) -->
                <div class="space-y-4 p-5 rounded-2xl bg-gray-50 border border-gray-200">
                    <h3 class="font-heading font-bold text-sm text-dark">Quotation &amp; Fare Estimation Breakdown</h3>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Estimated Fare (₱)</label>
                            <input type="number" step="0.01" name="estimated_fare" x-model.number="formData.estimated_fare" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Taxes / Fees (₱)</label>
                            <input type="number" step="0.01" name="taxes_amount" x-model.number="formData.taxes_amount" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Visa Assist Fee (₱)</label>
                            <input type="number" step="0.01" name="visa_assistance_fee" x-model.number="formData.visa_assistance_fee" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Insurance Fee (₱)</label>
                            <input type="number" step="0.01" name="insurance_fee" x-model.number="formData.insurance_fee" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Other Charges (₱)</label>
                            <input type="number" step="0.01" name="other_charges" x-model.number="formData.other_charges" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>
                    </div>

                    <!-- Grand Total Banner -->
                    <div class="p-4 rounded-xl bg-primary text-white flex items-center justify-between">
                        <span class="font-heading font-extrabold text-sm uppercase tracking-wider">Estimated Grand Total:</span>
                        <div class="font-mono text-xl font-black text-accent">
                            ₱<span x-text="formatNumber(formData.total_amount)"></span>
                        </div>
                        <input type="hidden" name="total_amount" :value="formData.total_amount">
                    </div>
                </div>

                <!-- Navigation Controls -->
                <div class="flex items-center justify-between pt-6 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="submit" :disabled="isSubmitting"
                            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-accent text-dark font-heading font-black text-xs sm:text-sm uppercase tracking-wider hover:bg-accent-dark shadow-xl shadow-accent/25 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                        <span x-show="!isSubmitting" class="inline-flex items-center gap-2">
                            <i data-lucide="check-circle-2" class="w-4 h-4"></i>
                            <span x-text="formData.travel_type === 'international' ? 'Confirm &amp; Issue International Ticket' : 'Confirm &amp; Issue Domestic Ticket'"></span>
                        </span>
                        <span x-show="isSubmitting" class="inline-flex items-center gap-2" style="display: none;">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Processing &amp; Issuing Ticket...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<script>
function bookingWizard(config) {
    const DRAFT_KEY = 'amega_ticket_booking_draft_v2';

    return {
        currentStep: 1,
        destinations: config.destinations || [],
        domesticDestinations: config.domesticDestinations || [],
        internationalDestinations: config.internationalDestinations || [],
        packages: config.packages || [],
        draftSaved: false,
        hasDraft: false,
        isSubmitting: false,

        popularDestinations: [
            { flag: '🇯🇵', city: 'Tokyo', country: 'Japan', airport: 'NRT (Narita)' },
            { flag: '🇰🇷', city: 'Seoul', country: 'South Korea', airport: 'ICN (Incheon)' },
            { flag: '🇫🇷', city: 'Paris', country: 'France', airport: 'CDG (Charles de Gaulle)' },
            { flag: '🇨🇭', city: 'Interlaken', country: 'Switzerland', airport: 'ZRH (Zurich)' },
            { flag: '🇮🇩', city: 'Bali', country: 'Indonesia', airport: 'DPS (Ngurah Rai)' },
            { flag: '🇦🇪', city: 'Dubai', country: 'United Arab Emirates', airport: 'DXB (Dubai Int.)' },
            { flag: '🇸🇬', city: 'Singapore', country: 'Singapore', airport: 'SIN (Changi)' },
            { flag: '🇹🇭', city: 'Bangkok', country: 'Thailand', airport: 'BKK (Suvarnabhumi)' },
        ],

        availableServices: [
            { key: 'hotel_booking', label: 'Hotel Booking', desc: 'Pre-vetted boutique or luxury hotels', icon: 'building' },
            { key: 'airport_transfer', label: 'Airport Transfer', desc: 'Private chauffeured airport pickup', icon: 'car' },
            { key: 'tour_package', label: 'Tour Package', desc: 'Curated day tours & sightseeing', icon: 'compass' },
            { key: 'travel_insurance', label: 'Travel Insurance', desc: 'Comprehensive medical protection', icon: 'shield-check' },
            { key: 'sim_card', label: 'SIM Card', desc: 'Local high-speed data connectivity', icon: 'smartphone' },
            { key: 'pocket_wifi', label: 'Pocket WiFi', desc: 'Unlimited shared portable router', icon: 'wifi' },
            { key: 'forex_assistance', label: 'Forex Assistance', desc: 'Currency exchange support', icon: 'banknote' },
            { key: 'meet_and_greet', label: 'Meet & Greet', desc: 'VIP airport assistance & escort', icon: 'smile' },
        ],

        specialRequestOptions: [
            { key: 'wheelchair_assistance', label: 'Wheelchair Assistance' },
            { key: 'special_meals', label: 'Special Meals (Halal / Veg)' },
            { key: 'medical_assistance', label: 'Medical / Mobility Support' },
            { key: 'senior_assistance', label: 'Senior Citizen Escort' },
            { key: 'extra_baggage', label: 'Extra Baggage Allowance' },
            { key: 'preferred_seat', label: 'Preferred Seating (Aisle/Window)' },
            { key: 'other', label: 'Other Custom Requests' },
        ],

        formData: {
            travel_type: 'international',
            package_type: 'without_package',
            travel_package_id: '',
            origin: 'Manila (MNL)',
            destination: '',
            destination_country: '',
            destination_city: '',
            arrival_airport: '',
            preferred_airline: '',
            trip_type: 'round_trip',
            travel_class: 'economy',
            preferred_flight_time: 'anytime',
            departure_date: '',
            return_date: '',
            multi_city_segments: [],
            total_passengers: 1,
            adults_count: 1,
            children_count: 0,
            infants_count: 0,
            contact_name: '{{ Auth::user()->name }}',
            contact_email: '{{ Auth::user()->email }}',
            contact_phone: '{{ Auth::user()->phone ?? "" }}',
            emergency_contact_name: '',
            emergency_contact_relationship: '',
            emergency_contact_phone: '',
            emergency_contact_email: '',
            has_insurance: false,
            insurance_plan: 'standard',
            selected_services: [],
            special_requests_list: [],
            special_requests: '',
            estimated_fare: 0,
            taxes_amount: 0,
            visa_assistance_fee: 0,
            insurance_fee: 0,
            other_charges: 0,
            total_amount: 0,
            passengers: [
                {
                    passenger_type: 'adult',
                    nationality_type: 'filipino',
                    first_name: '',
                    middle_name: '',
                    last_name: '',
                    suffix: '',
                    date_of_birth: '',
                    gender: '',
                    passport_number: '',
                    passport_expiry_date: '',
                    passport_warning: false,
                    visa_status: 'visa_not_required',
                    visa_assistance_type: '',
                    intended_stay_days: 15,
                    purpose_of_travel: 'Tourism',
                    passport_file_name: '',
                    visa_file_name: '',
                    passport_photo_file_name: '',
                    supporting_doc_file_name: '',
                    govid_file_name: '',
                    birthcert_file_name: '',
                    schoolid_file_name: '',
                }
            ]
        },

        get totalSteps() {
            return this.formData.travel_type === 'international' ? 11 : 6;
        },

        get activeSteps() {
            if (this.formData.travel_type === 'international') {
                return ['Destination', 'Trip Details', 'Passengers', 'Passport', 'Visa', 'Insurance', 'Services', 'Emergency', 'Requests', 'Checklist', 'Summary'];
            }
            return ['Travel Type', 'Package', 'Trip Info', 'Passengers', 'Documents', 'Review'];
        },

        get activeStepTitles() {
            if (this.formData.travel_type === 'international') {
                return [
                    'Step 1 – International Destination',
                    'Step 2 – Trip & Flight Details',
                    'Step 3 – Passenger Information Manifest',
                    'Step 4 – Mandatory Passport Validation (6-Month Rule)',
                    'Step 5 – Visa Requirements & Assistance',
                    'Step 6 – Travel Insurance Protection',
                    'Step 7 – Optional Travel Concierge Services',
                    'Step 8 – Emergency Contact Information',
                    'Step 9 – Special Requests & Seating',
                    'Step 10 – Verification Document Checklist',
                    'Step 11 – Booking Summary & Quotation'
                ];
            }
            return [
                'Select Travel Category',
                'Package Selection Option',
                'Trip Route & Travel Dates',
                'Passenger Information Manifest',
                'Required Verification Documents',
                'Review Booking & Issue Ticket'
            ];
        },

        get activePackages() {
            if (this.formData.travel_type === 'international') {
                return this.packages.filter(p => p.package_type === 'international' || (p.destination && p.destination.type === 'international'));
            }
            return this.packages.filter(p => p.package_type === 'domestic' || (p.destination && p.destination.type === 'domestic'));
        },

        init() {
            this.loadDraft();

            this.$watch('currentStep', () => {
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            });

            this.calculateGrandTotal();
        },

        saveDraft() {
            try {
                // Do not store file input references in localStorage
                const draft = JSON.parse(JSON.stringify(this.formData));
                localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
                this.draftSaved = true;
                this.hasDraft = true;
                setTimeout(() => { this.draftSaved = false; }, 2000);
            } catch (e) {
                // localstorage error ignore
            }
        },

        loadDraft() {
            try {
                const saved = localStorage.getItem(DRAFT_KEY);
                if (saved) {
                    const parsed = JSON.parse(saved);
                    // Reset file names because HTML file inputs cannot be restored from localStorage
                    if (parsed.passengers && Array.isArray(parsed.passengers)) {
                        parsed.passengers.forEach(p => {
                            p.passport_file_name = '';
                            p.visa_file_name = '';
                            p.passport_photo_file_name = '';
                            p.supporting_doc_file_name = '';
                            p.govid_file_name = '';
                            p.birthcert_file_name = '';
                            p.schoolid_file_name = '';
                        });
                    }
                    // Merge with current formData
                    Object.assign(this.formData, parsed);
                    this.hasDraft = true;
                }
            } catch (e) {
                // ignore
            }
        },

        clearDraft() {
            if (confirm('Are you sure you want to clear the saved draft and reset the form?')) {
                localStorage.removeItem(DRAFT_KEY);
                window.location.reload();
            }
        },

        onTravelTypeChange(type) {
            this.formData.travel_type = type;
            if (type === 'international') {
                if (!this.formData.destination_country) {
                    this.selectPresetDestination(this.popularDestinations[0]);
                }
            }
            this.saveDraft();
        },

        selectPresetDestination(pdest) {
            this.formData.destination_country = pdest.country;
            this.formData.destination_city = pdest.city;
            this.formData.arrival_airport = pdest.airport;
            this.formData.destination = pdest.city + ', ' + pdest.country;
            this.saveDraft();
        },

        onCityInput() {
            if (this.formData.destination_city && this.formData.destination_country) {
                this.formData.destination = this.formData.destination_city + ', ' + this.formData.destination_country;
            } else {
                this.formData.destination = this.formData.destination_city;
            }
            this.saveDraft();
        },

        onPackageSelect(e) {
            const pkgId = e.target.value;
            if (pkgId) {
                const pkg = this.packages.find(p => p.id == pkgId);
                if (pkg) {
                    if (pkg.destination) {
                        this.formData.destination = pkg.destination.name;
                        this.formData.destination_city = pkg.destination.name;
                    }
                }
            }
            this.saveDraft();
        },

        goToStep(step) {
            if (step <= this.currentStep) {
                this.currentStep = step;
            }
        },

        nextStep() {
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        prevStep() {
            if (this.currentStep > 1) {
                this.currentStep--;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        incrementPassenger(field) {
            this.formData[field]++;
            this.recalculatePassengers();
            this.saveDraft();
        },

        decrementPassenger(field) {
            if (field === 'adults_count' && this.formData[field] <= 1) return;
            if (this.formData[field] > 0) {
                this.formData[field]--;
                this.recalculatePassengers();
                this.saveDraft();
            }
        },

        recalculatePassengers() {
            const adults = parseInt(this.formData.adults_count) || 1;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;
            this.formData.total_passengers = adults + children + infants;

            const currentList = this.formData.passengers;
            const targetList = [];

            let index = 0;
            for (let i = 0; i < adults; i++) {
                targetList.push(this.createPassengerObj(currentList[index], 'adult'));
                index++;
            }
            for (let i = 0; i < children; i++) {
                targetList.push(this.createPassengerObj(currentList[index], 'child'));
                index++;
            }
            for (let i = 0; i < infants; i++) {
                targetList.push(this.createPassengerObj(currentList[index], 'infant'));
                index++;
            }

            this.formData.passengers = targetList;
            this.calculateGrandTotal();
        },

        createPassengerObj(existing, type) {
            if (existing) {
                existing.passenger_type = type;
                return existing;
            }
            return {
                passenger_type: type,
                nationality_type: 'filipino',
                first_name: '',
                middle_name: '',
                last_name: '',
                suffix: '',
                date_of_birth: '',
                gender: '',
                passport_number: '',
                passport_expiry_date: '',
                passport_warning: false,
                visa_status: 'visa_not_required',
                visa_assistance_type: '',
                intended_stay_days: 15,
                purpose_of_travel: 'Tourism',
                passport_file_name: '',
                visa_file_name: '',
                passport_photo_file_name: '',
                supporting_doc_file_name: '',
                govid_file_name: '',
                birthcert_file_name: '',
                schoolid_file_name: '',
            };
        },

        checkPassportValidity(passenger) {
            if (!passenger.passport_expiry_date || !this.formData.departure_date) {
                passenger.passport_warning = false;
                return;
            }

            const dep = new Date(this.formData.departure_date);
            const exp = new Date(passenger.passport_expiry_date);
            
            // Required: 6 months (approx 180 days) after departure
            const sixMonthsAfterDep = new Date(dep);
            sixMonthsAfterDep.setMonth(sixMonthsAfterDep.getMonth() + 6);

            passenger.passport_warning = exp < sixMonthsAfterDep;
        },

        onFileChange(e, passenger, fieldName) {
            if (e.target.files && e.target.files.length > 0) {
                passenger[fieldName] = e.target.files[0].name;
            } else {
                passenger[fieldName] = '';
            }
            this.saveDraft();
        },

        toggleService(key) {
            const idx = this.formData.selected_services.indexOf(key);
            if (idx > -1) {
                this.formData.selected_services.splice(idx, 1);
            } else {
                this.formData.selected_services.push(key);
            }
        },

        toggleSpecialRequest(key) {
            const idx = this.formData.special_requests_list.indexOf(key);
            if (idx > -1) {
                this.formData.special_requests_list.splice(idx, 1);
            } else {
                this.formData.special_requests_list.push(key);
            }
        },

        addSegment() {
            this.formData.multi_city_segments.push({ from: '', to: '', date: '' });
            this.saveDraft();
        },

        removeSegment(idx) {
            this.formData.multi_city_segments.splice(idx, 1);
            this.saveDraft();
        },

        calculateGrandTotal() {
            const fare = parseFloat(this.formData.estimated_fare) || 0;
            const taxes = parseFloat(this.formData.taxes_amount) || 0;
            const visa = parseFloat(this.formData.visa_assistance_fee) || 0;
            const ins = parseFloat(this.formData.insurance_fee) || 0;
            const other = parseFloat(this.formData.other_charges) || 0;
            this.formData.total_amount = fare + taxes + visa + ins + other;
        },

        formatNumber(num) {
            return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        // STEP VALIDATIONS
        validateStep1() {
            if (this.formData.travel_type === 'international') {
                if (!this.formData.destination_country || !this.formData.destination_country.trim()) {
                    alert('Step 1: Please specify the Destination Country.');
                    this.currentStep = 1;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.destination_city || !this.formData.destination_city.trim()) {
                    alert('Step 1: Please specify the Destination City.');
                    this.currentStep = 1;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.arrival_airport || !this.formData.arrival_airport.trim()) {
                    alert('Step 1: Please specify the Arrival Airport.');
                    this.currentStep = 1;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }
            return true;
        },

        validateStep2() {
            if (!this.formData.origin || !this.formData.origin.trim()) {
                alert('Step 2: Please provide Origin Airport / City.');
                this.currentStep = 2;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.destination || !this.formData.destination.trim()) {
                alert('Step 2: Please provide Destination.');
                this.currentStep = 2;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.departure_date) {
                alert('Step 2: Please select a Departure Date.');
                this.currentStep = 2;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            const today = new Date().toISOString().split('T')[0];
            if (this.formData.departure_date < today) {
                alert('Step 2: Departure date cannot be in the past.');
                this.currentStep = 2;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            if (this.formData.trip_type === 'round_trip') {
                if (!this.formData.return_date) {
                    alert('Step 2: Please select a Return Date for Round Trip bookings.');
                    this.currentStep = 2;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (this.formData.return_date <= this.formData.departure_date) {
                    alert('Step 2: Return date must be later than departure date.');
                    this.currentStep = 2;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }
            return true;
        },

        validateStep3() {
            const adults = parseInt(this.formData.adults_count) || 0;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;
            const total = parseInt(this.formData.total_passengers) || 0;

            if ((adults + children + infants) !== total) {
                alert('Step 3: The sum of Adults, Children, and Infants must equal Total Passengers.');
                this.currentStep = 3;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                if (!p.first_name || !p.first_name.trim()) {
                    alert('Step 3: Please fill in First Name for Passenger #' + num);
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.last_name || !p.last_name.trim()) {
                    alert('Step 3: Please fill in Last Name for Passenger #' + num);
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.date_of_birth) {
                    alert('Step 3: Please provide Date of Birth for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.gender) {
                    alert('Step 3: Please select Gender for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (this.formData.travel_type === 'international') {
                    if (!p.passport_number || !p.passport_number.trim()) {
                        alert('Step 3: Passport Number is required for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                        this.currentStep = 3;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!p.passport_expiry_date) {
                        alert('Step 3: Passport Expiration Date is required for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                        this.currentStep = 3;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    this.checkPassportValidity(p);
                }
            }
            return true;
        },

        validateStep4() {
            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                const name = (p.first_name + ' ' + p.last_name).trim() || ('Passenger #' + num);

                const fileInput = document.querySelector('input[name="passengers[' + i + '][passport_file]"]');
                const hasFileInDOM = fileInput && fileInput.files && fileInput.files.length > 0;

                if (!hasFileInDOM && !p.passport_file_name) {
                    alert('Step 4: Mandatory Passport photo/scan upload is missing for Passenger #' + num + ' (' + name + ').');
                    this.currentStep = 4;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }

                if (!hasFileInDOM && p.passport_file_name) {
                    p.passport_file_name = '';
                    alert('Step 4: Please re-select the Passport photo/scan for Passenger #' + num + ' (' + name + '). Browsers do not keep files across page reloads.');
                    this.currentStep = 4;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }

                if (this.formData.departure_date && p.passport_expiry_date) {
                    const dep = new Date(this.formData.departure_date);
                    const exp = new Date(p.passport_expiry_date);
                    const sixMonths = new Date(dep);
                    sixMonths.setMonth(sixMonths.getMonth() + 6);

                    if (exp < sixMonths) {
                        alert('Step 4: Passenger #' + num + ' (' + name + ') passport expires on ' + p.passport_expiry_date + ', which is less than six (6) months from departure (' + this.formData.departure_date + '). Passport must be renewed before international travel.');
                        this.currentStep = 4;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }
            }
            return true;
        },

        validateStep5() {
            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                const name = (p.first_name + ' ' + p.last_name).trim() || ('Passenger #' + num);

                if (p.visa_status === 'already_has_visa') {
                    const visaInput = document.querySelector('input[name="passengers[' + i + '][visa_file]"]');
                    const hasVisaFile = visaInput && visaInput.files && visaInput.files.length > 0;
                    if (!hasVisaFile && !p.visa_file_name) {
                        alert('Step 5: Passenger #' + num + ' (' + name + ') has "Already Has Visa" selected. Please upload a Visa Copy.');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasVisaFile && p.visa_file_name) {
                        p.visa_file_name = '';
                        alert('Step 5: Please re-select the Visa Copy for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }

                if (p.visa_status === 'needs_assistance') {
                    const photoInput = document.querySelector('input[name="passengers[' + i + '][passport_photo_file]"]');
                    const hasPhoto = photoInput && photoInput.files && photoInput.files.length > 0;
                    if (!hasPhoto && !p.passport_photo_file_name) {
                        alert('Step 5: Passenger #' + num + ' (' + name + ') requested Visa Assistance. Please upload a 2x2 Passport Photo.');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasPhoto && p.passport_photo_file_name) {
                        p.passport_photo_file_name = '';
                        alert('Step 5: Please re-select the 2x2 Passport Photo for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }

                    const docInput = document.querySelector('input[name="passengers[' + i + '][supporting_doc_file]"]');
                    const hasDoc = docInput && docInput.files && docInput.files.length > 0;
                    if (!hasDoc && !p.supporting_doc_file_name) {
                        alert('Step 5: Passenger #' + num + ' (' + name + ') requested Visa Assistance. Please upload Supporting Documents (COE / Bank Cert).');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasDoc && p.supporting_doc_file_name) {
                        p.supporting_doc_file_name = '';
                        alert('Step 5: Please re-select Supporting Documents for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }
            }
            return true;
        },

        validateStep8() {
            if (this.formData.travel_type === 'international') {
                if (!this.formData.emergency_contact_name || !this.formData.emergency_contact_name.trim()) {
                    alert('Step 8: Please provide the Emergency Contact Full Name.');
                    this.currentStep = 8;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.emergency_contact_relationship || !this.formData.emergency_contact_relationship.trim()) {
                    alert('Step 8: Please specify Relationship to Passenger.');
                    this.currentStep = 8;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.emergency_contact_phone || !this.formData.emergency_contact_phone.trim()) {
                    alert('Step 8: Please provide the Emergency Contact Mobile / Phone Number.');
                    this.currentStep = 8;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }

            // Booker contact validation required by backend
            if (!this.formData.contact_name || !this.formData.contact_name.trim()) {
                alert('Step 8: Please provide the Booker Contact Name.');
                this.currentStep = 8;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.contact_email || !this.formData.contact_email.trim()) {
                alert('Step 8: Please provide the Booker Contact Email.');
                this.currentStep = 8;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.contact_phone || !this.formData.contact_phone.trim()) {
                alert('Step 8: Please provide the Booker Mobile / Phone Number.');
                this.currentStep = 8;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            return true;
        },

        validateStep10() {
            return this.validateStep4() && this.validateStep5();
        },

        validateSubmission(e) {
            if (this.formData.travel_type === 'international') {
                if (!this.validateStep1()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep2()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep3()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep4()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep5()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep8()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
            } else {
                if (!this.validateStep2()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep3()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.formData.contact_name || !this.formData.contact_email || !this.formData.contact_phone) {
                    alert('Please provide Booker Contact Name, Email, and Phone Number.');
                    this.currentStep = 8;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
            }

            this.isSubmitting = true;
            return true;
        }
    };
}
</script>
@endsection
