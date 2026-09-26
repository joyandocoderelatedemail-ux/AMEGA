@extends('layouts.ticketing')

@section('title', 'New Ticket Booking Wizard - AMEGA')

@section('content')
<div class="max-w-5xl mx-auto space-y-6"
     x-data="bookingWizard({
         destinations: {{ Js::from($destinations) }},
         domesticDestinations: {{ Js::from($domesticDestinations ?? []) }},
         internationalDestinations: {{ Js::from($internationalDestinations ?? []) }},
         packages: {{ Js::from($packages) }},
         clientSearchUrl: {{ Js::from(route('ticketing.clients.search')) }},
         clientRegisterUrl: {{ Js::from(route('ticketing.clients.create')) }},
         preselectedClient: {{ Js::from($preselectedClient ?? null) }},
         pendingTicket: {{ Js::from($pendingTicket ?? null) }},
         pendingSaveUrl: {{ Js::from(route('ticketing.tickets.pending.store')) }}
     })">
    
    <!-- Top Step Bar & Progress Header -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-100 shadow-sm space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-heading font-extrabold uppercase tracking-wider whitespace-nowrap"
                          :class="formData.travel_type === 'international' ? 'bg-accent/15 text-dark' : 'bg-primary/10 text-primary'">
                        <i :data-lucide="formData.travel_type === 'international' ? 'globe' : 'palmtree'" class="w-3.5 h-3.5"></i>
                        <span x-text="formData.travel_type === 'international' ? 'International Tour Booking' : 'Domestic Tour Booking'"></span>
                    </span>

                    <span x-show="draftSaved" class="text-[11px] font-bold text-emerald-600 flex items-center gap-1 whitespace-nowrap">
                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        <span>Draft Saved</span>
                    </span>

                    <span x-show="pendingSavedAt" x-cloak class="text-[11px] font-bold text-amber-700 flex items-center gap-1 whitespace-nowrap"
                          title="Continue it later from the Ticket Directory">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        <span x-text="'Pending · saved ' + pendingSavedAt"></span>
                    </span>
                </div>

                <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight mt-1.5">
                    <span x-text="activeStepTitles[stepIndex]"></span>
                </h1>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 sm:gap-3">
                <button type="button" @click="clearDraft()" x-show="hasDraft" title="Clear saved draft and start fresh"
                        class="text-[11px] font-bold text-dark/40 hover:text-rose-600 underline transition-colors whitespace-nowrap">
                    Reset Form
                </button>

                @if (($pendingCount ?? 0) > 0)
                    <a href="{{ route('ticketing.tickets.index') }}#pending-tickets"
                       title="Tickets saved as pending, waiting on requirements"
                       class="inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-700 hover:text-amber-900 whitespace-nowrap">
                        <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                        Pending ({{ $pendingCount }})
                    </a>
                @endif

                <!-- Save as pending: the whole form is kept on the server, then the wizard is cleared for the next client -->
                <button type="button" @click="savePending()" :disabled="pendingSaving || isSubmitting"
                        title="Save this ticket as pending and start a new one. Continue it later from the Ticket Directory."
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-gray-200 text-dark/70 font-bold text-xs whitespace-nowrap hover:border-gray-300 hover:text-dark transition-colors disabled:opacity-50">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <span x-text="pendingSaving ? 'Saving…' : 'Save as Pending'">Save as Pending</span>
                </button>

                <!-- A quote needs only the route, date and client: no documents or passport checks -->
                <button type="button" @click="saveAsQuotation()" x-show="stepIndex > 0" :disabled="isSubmitting"
                        title="Save the trip as a quotation without documents or passenger checks"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-primary/30 text-primary font-bold text-xs whitespace-nowrap hover:bg-primary/5 transition-colors disabled:opacity-50">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span>Save as Quotation</span>
                </button>


                <div class="flex items-center gap-2 whitespace-nowrap">
                    <span class="text-xs font-bold uppercase tracking-wider text-dark/40">Step</span>
                    <span class="w-8 h-8 rounded-full bg-primary text-white font-heading font-extrabold text-sm flex items-center justify-center shadow-md shadow-primary/30" x-text="stepIndex + 1"></span>
                    <span class="text-xs font-bold text-dark/40" x-text="'of ' + totalSteps"></span>
                </div>
            </div>
        </div>

        <!-- Progress Steps Indicators -->
        <div class="grid gap-1.5" :style="'grid-template-columns: repeat(' + totalSteps + ', minmax(0, 1fr))'">
            <template x-for="(step, idx) in activeSteps" :key="idx">
                <div class="flex flex-col gap-1.5 cursor-pointer group" @click="goToStep(stepSequence[idx])">
                    <div class="h-2 rounded-full transition-all duration-300"
                         :class="{
                             'bg-accent': stepIndex === idx,
                             'bg-primary': stepIndex > idx,
                             'bg-gray-200': stepIndex < idx
                         }"></div>
                    <span class="text-[9px] font-bold uppercase tracking-wider truncate hidden lg:block"
                          :class="{
                              'text-accent font-extrabold': stepIndex === idx,
                              'text-primary font-bold': stepIndex > idx,
                              'text-dark/40': stepIndex < idx
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
            <p class="text-[11px] text-rose-800/80 pt-1">
                Only need a price for the client? Use <button type="button" @click="saveAsQuotation()" class="font-bold underline hover:text-rose-900">Save as Quotation</button> &mdash; documents and passport checks are not needed for a quote.
            </p>
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
        <input type="hidden" name="pending_ticket_id" :value="pendingId || ''">

        <!-- Hidden input for JSON/State -->
        <input type="hidden" name="save_as_quotation" :value="quotationMode ? 1 : 0">
        <input type="hidden" name="travel_type" x-model="formData.travel_type">
        <input type="hidden" name="package_type" x-model="formData.package_type">
        <input type="hidden" name="total_passengers" x-model="formData.total_passengers">
        <input type="hidden" name="custom_hotel_name" :value="formData.custom_hotel_name">
        <input type="hidden" name="custom_preferred_hotel" :value="formData.custom_preferred_hotel">
        <input type="hidden" name="custom_has_breakfast" :value="formData.custom_has_breakfast ? '1' : '0'">
        <input type="hidden" name="custom_bed_config" :value="formData.custom_bed_config">
        <input type="hidden" name="custom_check_in_date" :value="formData.custom_check_in_date">
        <input type="hidden" name="custom_check_out_date" :value="formData.custom_check_out_date">
        <input type="hidden" name="custom_smoking_preference" :value="formData.custom_smoking_preference">
        <input type="hidden" name="custom_pet_friendly" :value="formData.custom_pet_friendly ? '1' : '0'">
        <input type="hidden" name="custom_has_transportation" :value="formData.custom_has_transportation ? '1' : '0'">
        <input type="hidden" name="custom_transportation_type" :value="formData.custom_transportation_type">
        <input type="hidden" name="custom_special_requests" :value="formData.custom_special_requests">
        <input type="hidden" name="custom_estimated_budget" :value="formData.custom_estimated_budget">

        <!-- ========================================================================= -->
        <!-- STEP 1: TRAVEL TYPE &AMP; PASSENGERS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 1" class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Travel Type & Passengers'">Step 1: Travel Type &amp; Passengers</span></h2>
                    <p class="text-xs text-dark/50">These selections determine which travel documents are required, so they are collected first.</p>
                </div>

                <!-- Travellers: pick every registered client on this trip; each fills a passenger slot by age -->
                <div class="p-5 rounded-2xl border-2 space-y-3 transition-colors"
                     :class="formData.clients.length ? 'border-emerald-300 bg-emerald-50/20' : 'border-primary/20 bg-primary/5'">
                    <input type="hidden" name="client_user_id" :value="formData.clients.length ? formData.clients[0].id : ''">

                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-primary block">Travellers</span>
                            <p class="text-[11px] text-dark/50 mt-0.5">Add every registered client on this trip. The first is the booker. Each is set automatically as Adult (12+), Child (2&ndash;11) or Infant (under 2) from their birth date.</p>
                        </div>
                        <a :href="registerClientHref"
                           class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-primary/30 text-primary font-bold text-xs hover:bg-primary/5 transition-colors shrink-0">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            <span>Register client</span>
                        </a>
                    </div>

                    <!-- Selected travellers -->
                    <div x-show="formData.clients.length > 0" class="space-y-2">
                        <template x-for="(c, cIdx) in formData.clients" :key="c.id">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-white border border-emerald-200">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <i data-lucide="user-check" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                        <span class="text-sm font-bold text-dark truncate" x-text="c.name"></span>
                                        <span x-show="c.passenger_type" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                              :class="c.passenger_type === 'adult' ? 'bg-blue-100 text-blue-800' : (c.passenger_type === 'child' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                                              x-text="c.passenger_type"></span>
                                        <span x-show="!c.passenger_type" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-dark/60">Birth date needed</span>
                                        <span x-show="cIdx === 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary">Booker</span>
                                    </div>
                                    <p class="text-[11px] text-dark/60 mt-0.5 truncate"
                                       x-text="[clientAgeLabel(c), c.email, c.phone, c.passport_number ? 'Passport ' + c.passport_number : null].filter(Boolean).join(' · ')"></p>
                                    <div class="flex flex-wrap gap-1.5 mt-1.5">
                                        <span x-show="c.has_passport_scan" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">Passport scan on file</span>
                                        <span x-show="c.has_government_id_scan" class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">ID scan on file</span>
                                    </div>
                                    <!-- No birth date on the profile: the category comes from the date staff enter -->
                                    <div x-show="!c.date_of_birth" class="flex flex-wrap items-center gap-2 mt-2">
                                        <span class="text-[11px] font-semibold text-amber-700">No birth date on profile &mdash; enter it to set Adult, Child or Infant:</span>
                                        <input type="date" :max="new Date().toISOString().split('T')[0]"
                                               @change="setClientBirthDate(c, $event.target.value)"
                                               :aria-label="'Birth date of ' + c.name"
                                               class="px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-dark text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                </div>
                                <button type="button" @click="removeClient(c)"
                                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors shrink-0">
                                    <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                    <span>Remove</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Search -->
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 text-dark/40 absolute left-3.5 top-3"></i>
                        <input type="search" x-model="clientQuery" @input.debounce.300ms="searchClients()" @keydown.enter.prevent
                               :placeholder="formData.clients.length ? 'Add another traveller: name, email, phone or passport number' : 'Search by name, email, phone or passport number'"
                               aria-label="Search registered clients"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">

                        <div x-show="clientResults.length > 0" class="mt-2 rounded-xl border border-gray-200 bg-white divide-y divide-gray-100 overflow-hidden">
                            <template x-for="c in clientResults" :key="c.id">
                                <button type="button" @click="selectClient(c)" :disabled="isClientPicked(c)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-primary/5 transition-colors flex items-center justify-between gap-3 disabled:opacity-50 disabled:hover:bg-white disabled:cursor-default">
                                    <span class="min-w-0">
                                        <span class="block text-xs font-bold text-dark truncate" x-text="c.name"></span>
                                        <span class="block text-[11px] text-dark/50 truncate"
                                              x-text="[clientAgeLabel(c), c.email, c.phone, c.passport_number ? 'Passport ' + c.passport_number : null].filter(Boolean).join(' · ')"></span>
                                    </span>
                                    <span class="text-[11px] font-bold shrink-0"
                                          :class="isClientPicked(c) ? 'text-emerald-700' : 'text-primary'"
                                          x-text="isClientPicked(c) ? 'Added' : (clientType(c) ? 'Add as ' + clientTypeLabel(c) : 'Add')"></span>
                                </button>
                            </template>
                        </div>

                        <p x-show="clientSearching" class="text-[11px] text-dark/50 mt-2">Searching&hellip;</p>
                        <p x-show="clientSearched && !clientSearching && clientResults.length === 0" class="text-[11px] text-dark/60 mt-2">
                            No registered client matches &ldquo;<span x-text="clientQuery"></span>&rdquo;.
                            <a :href="registerClientHref" class="font-bold text-primary hover:underline">Register them</a>, or continue without a client for a quick quotation.
                        </p>
                    </div>
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

                <!-- Navigation -->
                <div class="flex items-center justify-end pt-4 border-t border-gray-100">
                    <button type="button" @click="validateStep1() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md">
                        <span>Continue to Document Requirements</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>


        <!-- ========================================================================= -->
        <!-- STEP 3: DESTINATION &AMP; PACKAGE -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 3" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Destination & Package'">Step 3: Destination &amp; Package</span></h2>
                    <p class="text-xs text-dark/50">Choose the destination and, optionally, attach an existing travel package.</p>
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
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                               :class="formData.package_type === 'without_package' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-gray-200 bg-white hover:border-gray-300'">
                            <input type="radio" name="_package_type_radio" value="without_package" @change="selectPackageOption('without_package')" class="sr-only">
                            <div class="w-5 h-5 rounded-full border-2 border-primary flex items-center justify-center shrink-0 mt-0.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="formData.package_type === 'without_package'"></span>
                            </div>
                            <div>
                                <span class="font-bold text-xs text-dark block">Flight Only / Custom Route</span>
                                <span class="text-[11px] text-dark/50">Custom flight ticketing without hotel</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all"
                               :class="formData.package_type === 'with_package' && formData.travel_package_id !== 'custom' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-gray-200 bg-white hover:border-gray-300'">
                            <input type="radio" name="_package_type_radio" value="with_package" @change="selectPackageOption('with_package')" class="sr-only">
                            <div class="w-5 h-5 rounded-full border-2 border-primary flex items-center justify-center shrink-0 mt-0.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="formData.package_type === 'with_package' && formData.travel_package_id !== 'custom'"></span>
                            </div>
                            <div>
                                <span class="font-bold text-xs text-dark block">Ready-Made Tour Package</span>
                                <span class="text-[11px] text-dark/50">Pre-bundled packages from catalog</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-all relative overflow-hidden"
                               :class="isCustomPackage ? 'border-accent bg-accent/10 ring-2 ring-accent/30' : 'border-gray-200 bg-white hover:border-accent/40'">
                            <input type="radio" name="_package_type_radio" value="custom_package" @change="selectPackageOption('custom_package')" class="sr-only">
                            <div class="w-5 h-5 rounded-full border-2 border-accent flex items-center justify-center shrink-0 mt-0.5">
                                <span class="w-2.5 h-2.5 rounded-full bg-accent-dark" x-show="isCustomPackage"></span>
                            </div>
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs text-dark block">Customized Package</span>
                                    <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-accent text-dark">Step 4</span>
                                </div>
                                <span class="text-[11px] text-dark/60 block mt-0.5">Tailor hotel, bed, breakfast &amp; transport</span>
                            </div>
                        </label>
                    </div>

                    <!-- Package Dropdown if selected -->
                    <div x-show="formData.package_type === 'with_package' || isCustomPackage" class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-dark/70">Select Active Package</label>
                            <span x-show="isCustomPackage" class="text-[10px] font-bold text-primary flex items-center gap-1">
                                <i data-lucide="sparkles" class="w-3 h-3 text-accent"></i>
                                <span>Custom Package Mode</span>
                            </span>
                        </div>
                        <select name="travel_package_id" x-model="formData.travel_package_id" @change="onPackageSelect($event)"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- Choose Travel Package --</option>
                            <option value="custom" class="font-bold text-primary bg-accent/20">✨ Customized Package (Configure Custom Itinerary in Step 4)</option>
                            <template x-for="pkg in activePackages" :key="pkg.id">
                                <option :value="pkg.id" x-text="pkg.title + ' (' + (pkg.destination ? pkg.destination.name : 'All') + ' - ₱' + Number(pkg.price).toLocaleString() + ')'"></option>
                            </template>
                        </select>

                        <!-- Customized Package Active Banner -->
                        <div x-show="isCustomPackage" class="p-4 rounded-xl bg-accent/15 border border-accent/40 text-dark space-y-1.5">
                            <div class="flex items-center gap-2">
                                <i data-lucide="sparkles" class="w-4 h-4 text-primary shrink-0"></i>
                                <span class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark">Customized Package Enabled</span>
                            </div>
                            <p class="text-xs text-dark/70 leading-relaxed">
                                You have selected to customize this package. When you click <strong>Continue to Customize Package</strong> below, <strong>Step 4: Customize Package Specifications</strong> will open, allowing you to configure hotel, bedding, breakfast, smoking/pet rules, and ground transportation.
                            </p>
                        </div>

                        <!-- Selected Package Configuration Highlights (for Ready-Made) -->
                        <div x-show="selectedPackage && !isCustomPackage" class="p-4 rounded-xl bg-white border border-primary/20 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-primary flex items-center gap-1.5">
                                    <i data-lucide="package" class="w-3.5 h-3.5"></i>
                                    <span x-text="selectedPackage?.title"></span>
                                </span>
                                <span class="text-xs font-extrabold text-navy" x-text="'₱' + formatNumber(selectedPackage?.price)"></span>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-gray-100 text-[11px]">
                                <div x-show="selectedPackage?.hotel_name || selectedPackage?.preferred_hotel" class="text-dark/70">
                                    <span class="font-bold text-dark block">Hotel:</span>
                                    <span x-text="selectedPackage?.hotel_name || selectedPackage?.preferred_hotel"></span>
                                </div>
                                <div x-show="selectedPackage?.bed_config" class="text-dark/70">
                                    <span class="font-bold text-dark block">Bedding:</span>
                                    <span class="capitalize" x-text="(selectedPackage?.bed_config || '') + ' Bed'"></span>
                                </div>
                                <div>
                                    <span class="font-bold text-dark block">Breakfast:</span>
                                    <span :class="selectedPackage?.has_breakfast ? 'text-emerald-600 font-bold' : 'text-dark/40'" x-text="selectedPackage?.has_breakfast ? 'Included' : 'Not included'"></span>
                                </div>
                                <div>
                                    <span class="font-bold text-dark block">Transport:</span>
                                    <span :class="selectedPackage?.has_transportation ? 'text-emerald-600 font-bold' : 'text-dark/40'" x-text="selectedPackage?.has_transportation ? (selectedPackage?.transportation_type || 'Included') : 'None'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="validateStep3() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                        <span x-text="isCustomPackage ? 'Continue to Customize Package (Step 4)' : 'Continue to Trip Details'"></span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 4: CUSTOMIZE PACKAGE SPECIFICATIONS (WHEN CUSTOM PACKAGE IS SELECTED) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 6" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full bg-accent/20 text-dark text-[10px] font-heading font-black uppercase tracking-wider flex items-center gap-1">
                                <i data-lucide="sparkles" class="w-3 h-3 text-primary"></i>
                                <span>Custom Package Architect</span>
                            </span>
                            <span class="text-xs text-dark/40 font-bold" x-text="'Destination: ' + (formData.destination || 'Selected Destination')"></span>
                        </div>
                        <h2 class="text-lg font-heading font-bold text-dark mt-1"><span x-text="'Step ' + (stepIndex + 1) + ': Customize Package Specifications'">Step 4: Customize Package Specifications</span></h2>
                        <p class="text-xs text-dark/50">Configure client accommodation, bedding setup, daily breakfast, smoking &amp; pet rules, and ground transportation.</p>
                    </div>

                    <div class="p-2.5 rounded-2xl bg-gray-50 border border-gray-200 text-right shrink-0">
                        <span class="text-[10px] uppercase font-bold text-dark/40 block">Trip Manifest</span>
                        <span class="text-xs font-bold text-primary" x-text="formData.total_passengers + ' Pax (' + formData.adults_count + 'A, ' + formData.children_count + 'C, ' + formData.infants_count + 'I)'"></span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left 2 Cols: Configuration Controls -->
                    <div class="lg:col-span-2 space-y-6">

                        <!-- 1. Accommodation & Hotel Specs -->
                        <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-200/60 pb-2">
                                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <i data-lucide="hotel" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-heading font-bold text-dark">Hotel &amp; Room Specifications</h3>
                                    <p class="text-[11px] text-dark/50">Property name, preferred hotel brand or location, and room layout</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Hotel Name</label>
                                    <input type="text" x-model="formData.custom_hotel_name" @input="saveDraft()"
                                           placeholder="e.g. Shangri-La, Shinjuku Prince Hotel"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Preferred Hotel / Location</label>
                                    <input type="text" x-model="formData.custom_preferred_hotel" @input="saveDraft()"
                                           placeholder="e.g. Beachfront Station 1, Near Train Station"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>

                            <!-- Bed Configuration & Breakfast -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Bed Configuration *</label>
                                    <select x-model="formData.custom_bed_config" @change="saveDraft()"
                                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="Single Bed">Single Bed (1 Pax)</option>
                                        <option value="Twin Beds">Twin Beds (2 Separate Beds)</option>
                                        <option value="Double Bed">Double Bed (1 Full Bed)</option>
                                        <option value="Queen Bed">Queen Bed</option>
                                        <option value="King Bed">King Bed</option>
                                        <option value="Family Setup">Family Setup (Multiple Beds / Connecting)</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">With Breakfast *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="formData.custom_has_breakfast = true; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="formData.custom_has_breakfast ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            <span>w/ Breakfast</span>
                                        </button>
                                        <button type="button" @click="formData.custom_has_breakfast = false; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="!formData.custom_has_breakfast ? 'bg-gray-800 text-white border-gray-800 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                            <span>Room Only</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Check-in & Check-out -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Check-In Date</label>
                                    <input type="date" x-model="formData.custom_check_in_date" @change="saveDraft()"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Check-Out Date</label>
                                    <input type="date" x-model="formData.custom_check_out_date" @change="saveDraft()"
                                           :min="formData.custom_check_in_date"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>

                            <!-- Smoking & Pet Policy -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-gray-200/60">
                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Smoking Policy *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="formData.custom_smoking_preference = 'non_smoking'; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="formData.custom_smoking_preference === 'non_smoking' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                            <span>Non-Smoking</span>
                                        </button>
                                        <button type="button" @click="formData.custom_smoking_preference = 'smoking'; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="formData.custom_smoking_preference === 'smoking' ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="cigarette" class="w-3.5 h-3.5"></i>
                                            <span>Smoking Allowed</span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Pet Policy *</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button type="button" @click="formData.custom_pet_friendly = true; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="formData.custom_pet_friendly ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="paw-print" class="w-3.5 h-3.5"></i>
                                            <span>Pet-Friendly</span>
                                        </button>
                                        <button type="button" @click="formData.custom_pet_friendly = false; saveDraft()"
                                                class="px-3 py-2 rounded-xl border text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer"
                                                :class="!formData.custom_pet_friendly ? 'bg-gray-800 text-white border-gray-800 shadow-sm' : 'bg-white text-dark/70 border-gray-200 hover:border-gray-300'">
                                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                                            <span>No Pets</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Transportation & Ground Logistics -->
                        <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-200/60 pb-2">
                                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <i data-lucide="car" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-heading font-bold text-dark">Transportation &amp; Transfers</h3>
                                    <p class="text-[11px] text-dark/50">Ground transfers, airport pickup/drop-off, private vans, or car rental</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <label class="flex items-center gap-3 p-3 rounded-xl bg-white border border-gray-200 cursor-pointer">
                                    <input type="checkbox" x-model="formData.custom_has_transportation" @change="saveDraft()" class="w-4 h-4 rounded text-primary focus:ring-primary border-gray-300">
                                    <div>
                                        <span class="text-xs font-bold text-dark block">Include Transportation / Airport Transfers</span>
                                        <span class="text-[10px] text-dark/50">Tick if this custom package includes dedicated vehicle services</span>
                                    </div>
                                </label>

                                <div x-show="formData.custom_has_transportation" class="pt-2">
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Transportation Type / Description</label>
                                    <select x-model="formData.custom_transportation_type" @change="saveDraft()"
                                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="Roundtrip Airport Transfer">Roundtrip Airport Transfer (Van/Car)</option>
                                        <option value="Private Chauffeured Van">Private Chauffeured Van (Dedicated Full-Day)</option>
                                        <option value="Dedicated Tour Bus">Dedicated Tour Bus (Group)</option>
                                        <option value="Speedboat & Land Transfer">Speedboat &amp; Land Transfer (Island Destinations)</option>
                                        <option value="Self-Drive Car Rental">Self-Drive Car Rental</option>
                                        <option value="Public Transit Pass">Public Transit Pass / Rail Card</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Special Requests & Estimated Budget -->
                        <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-200/60 pb-2">
                                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <i data-lucide="message-square" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-heading font-bold text-dark">Special Requests &amp; Package Budget</h3>
                                    <p class="text-[11px] text-dark/50">Room preferences, dietary requirements, and client target budget</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Special Requests (Optional)</label>
                                    <textarea rows="2" x-model="formData.custom_special_requests" @input="saveDraft()"
                                              placeholder="e.g. High floor ocean view, early check-in at 11 AM, honeymoon bed setup, halal meals..."
                                              class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Target / Estimated Package Budget (₱)</label>
                                    <input type="number" step="0.01" min="0" x-model.number="formData.custom_estimated_budget" @input="saveDraft()"
                                           placeholder="e.g. 50000.00"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Right Col: Live Custom Package Preview Card -->
                    <div class="space-y-4">
                        <div class="sticky top-6 rounded-3xl p-6 bg-gradient-to-br from-navy to-[#001f3f] text-white space-y-5 shadow-lg">
                            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                                <span class="px-2.5 py-1 rounded-lg bg-accent text-dark text-[10px] font-heading font-extrabold uppercase tracking-wider flex items-center gap-1">
                                    <i data-lucide="sparkles" class="w-3 h-3"></i>
                                    <span>Live Package Summary</span>
                                </span>
                                <span class="text-xs font-mono font-bold text-accent" x-text="formData.total_passengers + ' Pax'"></span>
                            </div>

                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-white/50 block">Destination</span>
                                <div class="text-base font-heading font-black text-white mt-0.5" x-text="formData.destination || 'Destination to be confirmed'"></div>
                            </div>

                            <!-- Highlights Grid -->
                            <div class="space-y-2.5 text-xs border-t border-white/10 pt-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="hotel" class="w-3.5 h-3.5 text-accent"></i> Hotel:</span>
                                    <span class="font-bold text-white text-right truncate max-w-[140px]" x-text="formData.custom_hotel_name || formData.custom_preferred_hotel || 'To Be Arranged'"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="bed" class="w-3.5 h-3.5 text-accent"></i> Bedding:</span>
                                    <span class="font-bold text-white" x-text="formData.custom_bed_config"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="coffee" class="w-3.5 h-3.5 text-accent"></i> Breakfast:</span>
                                    <span class="font-bold" :class="formData.custom_has_breakfast ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_has_breakfast ? 'Included ✓' : 'No Breakfast'"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="cigarette" class="w-3.5 h-3.5 text-accent"></i> Smoking:</span>
                                    <span class="font-bold" :class="formData.custom_smoking_preference === 'non_smoking' ? 'text-emerald-400' : 'text-amber-400'" x-text="formData.custom_smoking_preference === 'non_smoking' ? 'Non-Smoking' : 'Smoking'"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="paw-print" class="w-3.5 h-3.5 text-accent"></i> Pet Friendly:</span>
                                    <span class="font-bold" :class="formData.custom_pet_friendly ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_pet_friendly ? 'Allowed ✓' : 'No Pets'"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="car" class="w-3.5 h-3.5 text-accent"></i> Transfer:</span>
                                    <span class="font-bold text-right truncate max-w-[130px]" :class="formData.custom_has_transportation ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_has_transportation ? formData.custom_transportation_type : 'None'"></span>
                                </div>

                                <div x-show="formData.custom_check_in_date" class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5 text-accent"></i> Dates:</span>
                                    <span class="font-bold text-white text-xs" x-text="formData.custom_check_in_date + (formData.custom_check_out_date ? ' to ' + formData.custom_check_out_date : '')"></span>
                                </div>
                            </div>

                            <!-- Budget Pill -->
                            <div class="p-3.5 rounded-2xl bg-white/10 border border-white/10 flex items-center justify-between">
                                <span class="text-[11px] font-bold text-white/70">Estimated Budget:</span>
                                <div class="font-mono text-base font-black text-accent" x-text="'₱' + formatNumber(formData.custom_estimated_budget || 0)"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back to Destination &amp; Package</span>
                    </button>
                    <button type="button" @click="validateStepCustom() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                        <span>Continue to Trip &amp; Flight Specifications</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 4: TRIP &AMP; FLIGHT SPECIFICATIONS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 4" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Trip & Flight Specifications'">Step 4: Trip &amp; Flight Specifications</span></h2>
                    <p class="text-xs text-dark/50">Configure flight times, route, and schedule dates.</p>
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
                    <button type="button" @click="validateStep4() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Passenger Details</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 5: PASSENGER INFORMATION MANIFEST -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 5" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Passenger Information Manifest'">Step 5: Passenger Information Manifest</span></h2>
                    <p class="text-xs text-dark/50">Biographical details for each passenger on the booking.</p>
                </div>

                <!-- Passenger Details Cards -->
                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border border-gray-200 bg-gray-50/40 space-y-4">
                            <div class="flex items-center justify-between border-b border-gray-200/80 pb-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                    <span class="font-heading font-bold text-xs text-dark" x-text="'Passenger #' + (idx + 1) + ' (' + p.passenger_type.toUpperCase() + ')'"></span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span x-show="p.client_id" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-100 text-emerald-800">Registered client</span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider"
                                          :class="p.passenger_type === 'adult' ? 'bg-blue-100 text-blue-800' : (p.passenger_type === 'child' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800')"
                                          x-text="p.passenger_type"></span>
                                </div>
                            </div>

                            <input type="hidden" :name="'passengers[' + idx + '][passenger_type]'" :value="p.passenger_type">
                            <input type="hidden" :name="'passengers[' + idx + '][client_user_id]'" :value="p.client_id || ''">

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
                    <button type="button" @click="validateStep5() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Passport Validation</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 2: PASSPORT VALIDATION & UPLOADS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 2" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Passport Validation & Upload'">Step 2: Passport Validation &amp; Upload</span></h2>
                    <p class="text-xs text-dark/50" x-text="formData.travel_type === 'international'
                        ? 'Upload each passenger\'s passport scan — required for international travel.'
                        : 'Upload each passenger\'s passport scan or ID. A passport is required for foreign nationals.'">Upload each passenger's passport scan and travel documents.</p>
                </div>

                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border-2 space-y-3 transition-all"
                             :class="p.passport_file_name ? 'border-emerald-300 bg-emerald-50/20' : (p.passport_warning ? 'border-rose-300 bg-rose-50/20' : 'border-gray-200 bg-white')">
                            
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                <span class="font-bold text-xs text-dark" x-text="passengerLabel(p, idx)"></span>
                                <span x-show="p.passport_number" class="text-[11px] text-dark/50" x-text="'Passport ' + p.passport_number"></span>
                            </div>

                            <!-- Real-time Warning Banner if <= 6 months -->
                            <div x-show="p.passport_warning" class="p-3 rounded-xl bg-rose-100/70 border border-rose-300 flex items-center gap-2">
                                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-700 shrink-0"></i>
                                <span class="text-xs font-bold text-rose-900">
                                    Warning: Passport must be renewed before travel. Validity is less than six (6) months from departure.
                                </span>
                            </div>

                            <!-- Passport scan — required for international travel and foreign nationals -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-dark/70">Passport Scan</span>
                                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                      :class="passportScanStatus(p).tone"
                                      x-text="passportScanStatus(p).upload"></span>
                            </div>
                            <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-colors"
                                 :class="p.passport_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                <input type="hidden" :name="'passengers[' + idx + '][use_profile_passport]'" :value="(p.client_id && p.use_profile_passport && !p.passport_file_name) ? 1 : 0">
                                <p x-show="p.client_id && p.use_profile_passport && !p.passport_file_name" class="text-[11px] font-semibold text-emerald-700 mb-2">
                                    Using the passport scan on the client's profile. Upload a file below only to use a different one.
                                </p>
                                <input type="file" :name="'passengers[' + idx + '][passport_file]'" accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf"
                                       @change="onFileChange($event, p, 'passport_file_name')"
                                       class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-navy cursor-pointer">
                                <div x-show="p.passport_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1">
                                    <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                    <span x-text="'Attached: ' + p.passport_file_name"></span>
                                </div>
                            </div>

                            <!-- Government ID — required for Filipino adults on domestic travel -->
                            <div x-show="formData.travel_type === 'domestic' && p.passenger_type === 'adult' && p.nationality_type === 'filipino'"
                                 class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-dark/70">Valid Government ID</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                          :class="(p.government_id_file_name || (p.client_id && p.use_profile_government_id)) ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/60'"
                                          x-text="p.government_id_file_name ? '✓ Attached' : ((p.client_id && p.use_profile_government_id) ? '✓ On client profile' : 'Optional')"></span>
                                </div>
                                <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-all"
                                     :class="p.government_id_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                    <input type="hidden" :name="'passengers[' + idx + '][use_profile_government_id]'" :value="(p.client_id && p.use_profile_government_id && !p.government_id_file_name) ? 1 : 0">
                                    <p x-show="p.client_id && p.use_profile_government_id && !p.government_id_file_name" class="text-[11px] font-semibold text-emerald-700 mb-2">
                                        Using the ID scan on the client's profile. Upload a file below only to use a different one.
                                    </p>
                                    <input type="file" :name="'passengers[' + idx + '][government_id_file]'" accept="image/jpeg,image/png,image/jpg,application/pdf"
                                           @change="onFileChange($event, p, 'government_id_file_name')"
                                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <div x-show="p.government_id_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1.5">
                                        <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                        <span x-text="'Attached: ' + p.government_id_file_name"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Birth Certificate — required for infants, and for children without a school ID -->
                            <div x-show="p.passenger_type === 'infant' || p.passenger_type === 'child'" class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-dark/70">Birth Certificate</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                          :class="p.birth_cert_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/60'"
                                          x-text="p.birth_cert_file_name ? '✓ Attached' : 'Optional'"></span>
                                </div>
                                <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-all"
                                     :class="p.birth_cert_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                    <input type="file" :name="'passengers[' + idx + '][birth_cert_file]'" accept="image/jpeg,image/png,image/jpg,application/pdf"
                                           @change="onFileChange($event, p, 'birth_cert_file_name')"
                                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <div x-show="p.birth_cert_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1.5">
                                        <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                        <span x-text="'Attached: ' + p.birth_cert_file_name"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- School ID — alternative to the birth certificate for children -->
                            <div x-show="formData.travel_type === 'domestic' && p.passenger_type === 'child'" class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-dark/70">School ID <span class="font-normal text-dark/40">(alternative to birth certificate)</span></span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                          :class="p.school_id_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-200 text-dark/70'"
                                          x-text="p.school_id_file_name ? '✓ Attached' : 'If applicable'"></span>
                                </div>
                                <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-all"
                                     :class="p.school_id_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                    <input type="file" :name="'passengers[' + idx + '][school_id_file]'" accept="image/jpeg,image/png,image/jpg,application/pdf"
                                           @change="onFileChange($event, p, 'school_id_file_name')"
                                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <div x-show="p.school_id_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1.5">
                                        <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                        <span x-text="'Attached: ' + p.school_id_file_name"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Emigration Exit Clearance — foreign nationals staying beyond six months -->
                            <div x-show="p.nationality_type === 'foreign_national' && (parseInt(p.stay_duration_months) || 0) > 6" class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-dark/70">Emigration Exit Clearance (ECC)</span>
                                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full"
                                          :class="p.exit_clearance_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/60'"
                                          x-text="p.exit_clearance_file_name ? '✓ Attached' : 'Optional'"></span>
                                </div>
                                <div class="border-2 border-dashed rounded-xl p-4 text-center cursor-pointer hover:bg-gray-50/80 transition-all"
                                     :class="p.exit_clearance_file_name ? 'border-emerald-300' : 'border-gray-300'">
                                    <input type="file" :name="'passengers[' + idx + '][exit_clearance_file]'" accept="image/jpeg,image/png,image/jpg,application/pdf"
                                           @change="onFileChange($event, p, 'exit_clearance_file_name')"
                                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <div x-show="p.exit_clearance_file_name" class="text-xs font-bold text-emerald-700 mt-2 truncate flex items-center justify-center gap-1.5">
                                        <i data-lucide="file-check" class="w-3.5 h-3.5"></i>
                                        <span x-text="'Attached: ' + p.exit_clearance_file_name"></span>
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
                    <button type="button" @click="validateStep2() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Destination</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 5: VISA REQUIREMENTS (Already Has Visa, Needs Assistance, Not Required) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 7" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Visa Requirements & Assistance'">Step 7: Visa Requirements &amp; Assistance</span></h2>
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
                    <button type="button" @click="validateStep7() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Travel Insurance</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 6: TRAVEL INSURANCE -->
        <!-- ========================================================================= -->
        <div x-show="formData.travel_type === 'international' && currentStep === 8" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Travel Insurance Protection'">Step 8: Travel Insurance Protection</span></h2>
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
        <div x-show="currentStep === 9" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Optional Travel Concierge Services'">Step 9: Optional Travel Concierge Services</span></h2>
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
        <div x-show="currentStep === 10" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Contact & Representative Details'">Step 10: Contact Details</span></h2>
                    <p class="text-xs text-dark/50">Booker contact details, plus an emergency contact for international travel.</p>
                </div>

                <div x-show="formData.travel_type === 'international'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                    <button type="button" @click="validateStep10() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Special Requests</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 9: SPECIAL REQUESTS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 11" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Special Requests & Seating Preferences'">Step 11: Special Requests &amp; Seating Preferences</span></h2>
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
        <!-- DOCUMENT VERIFICATION (folded into the Review step) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 12" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Document Verification</h2>
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
                                              :class="passportScanStatus(p).tone"
                                              x-text="passportScanStatus(p).review"></span>
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

            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 12: REVIEW & QUOTATION -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 12" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-100 shadow-sm space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Review, Verification & Quotation'">Step 12: Review &amp; Quotation Review</span></h2>
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

                <!-- Package Summary (Ready-made or Custom) -->
                <!-- Ready-Made Tour Package Card -->
                <div x-show="formData.package_type === 'with_package' && selectedPackage && !isCustomPackage" class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                            <i data-lucide="package" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Selected Ready-Made Tour Package</span>
                            <span class="text-sm font-bold text-amber-950" x-text="selectedPackage ? selectedPackage.title : ''"></span>
                            <span class="text-xs text-amber-800/70 block" x-text="selectedPackage && selectedPackage.duration ? selectedPackage.duration : ''"></span>
                        </div>
                    </div>
                    <div class="sm:text-right" x-show="selectedPackage && selectedPackage.price">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/60 block">Package Price</span>
                        <span class="text-sm font-mono font-bold text-primary" x-text="selectedPackage ? selectedPackage.price : ''"></span>
                    </div>
                </div>

                <!-- Custom Package Specifications Card -->
                <div x-show="isCustomPackage" class="p-6 rounded-3xl bg-amber-50/60 border border-amber-200/80 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-amber-200/60 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Customized Package Architecture</span>
                                <h4 class="font-heading font-bold text-sm text-amber-950" x-text="formData.custom_hotel_name || formData.custom_preferred_hotel ? ('Custom Hotel: ' + (formData.custom_hotel_name || formData.custom_preferred_hotel)) : 'Customized Itinerary & Logistics'"></h4>
                            </div>
                        </div>
                        <div class="sm:text-right" x-show="formData.custom_estimated_budget">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70 block">Target Budget</span>
                            <span class="font-mono text-sm font-bold text-primary">₱<span x-text="formatNumber(formData.custom_estimated_budget)"></span></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel / Location</span>
                            <span class="font-bold text-dark block truncate" x-text="formData.custom_hotel_name || 'Standard / Arranged'"></span>
                            <span class="text-[10px] text-dark/60 block truncate" x-text="formData.custom_preferred_hotel || 'Any Preferred Area'"></span>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Bedding &amp; Breakfast</span>
                            <span class="font-semibold text-dark block" x-text="formData.custom_bed_config || 'Standard Double'"></span>
                            <span class="text-[10px] font-bold" :class="formData.custom_has_breakfast ? 'text-emerald-700' : 'text-dark/40'"
                                  x-text="formData.custom_has_breakfast ? '✓ Breakfast Included' : 'No Breakfast'"></span>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Stay Dates</span>
                            <span class="font-semibold text-dark block text-[11px]">
                                <span x-text="formData.custom_check_in_date || 'TBD'"></span>
                                <span x-show="formData.custom_check_out_date" x-text="' → ' + formData.custom_check_out_date"></span>
                            </span>
                            <span class="text-[10px] text-dark/50 block" x-text="formData.total_passengers + ' Pax Accommodated'"></span>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Policies &amp; Logistics</span>
                            <span class="text-[11px] font-medium text-dark block">
                                <span x-text="formData.custom_smoking_preference === 'smoking' ? '🚬 Smoking' : '🚭 Non-Smoking'"></span> •
                                <span x-text="formData.custom_pet_friendly ? '🐾 Pets OK' : 'No Pets'"></span>
                            </span>
                            <span class="text-[10px] font-bold block mt-0.5" :class="formData.custom_has_transportation ? 'text-primary' : 'text-dark/40'"
                                  x-text="formData.custom_has_transportation ? ('🚗 Transfer: ' + (formData.custom_transportation_type || 'Private')) : 'No Ground Transfer'"></span>
                        </div>
                    </div>

                    <div x-show="formData.custom_special_requests" class="p-3 rounded-xl bg-white border border-amber-100 text-xs">
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Special Requests</span>
                        <p class="text-dark/80 text-xs mt-0.5 font-medium" x-text="formData.custom_special_requests"></p>
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
                                          :class="passportScanStatus(p).tone"
                                          x-text="'Passport: ' + passportScanStatus(p).review"></span>
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
                    <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center gap-3">
                    <button type="button" @click="saveAsQuotation()" :disabled="isSubmitting"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-2xl bg-white border border-primary/30 text-primary font-bold text-xs hover:bg-primary/5 transition-colors disabled:opacity-50">
                        <i data-lucide="file-text" class="w-4 h-4"></i>
                        <span>Save as Quotation</span>
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
        </div>

    </form>
</div>

<script>
function bookingWizard(config) {
    const DRAFT_KEY = 'amega_ticket_booking_draft_v2';
    // Which pending ticket the form in this browser belongs to, so saving again updates it.
    const PENDING_KEY = 'amega_ticket_pending_id';

    return {
        currentStep: 1,
        destinations: config.destinations || [],
        domesticDestinations: config.domesticDestinations || [],
        internationalDestinations: config.internationalDestinations || [],
        packages: config.packages || [],
        draftSaved: false,
        hasDraft: false,
        isSubmitting: false,
        quotationMode: false,
        pendingId: null,
        pendingSaving: false,
        pendingSavedAt: '',

        // Client picker (top of Step 1)
        clientSearchUrl: config.clientSearchUrl,
        clientRegisterUrl: config.clientRegisterUrl,
        clientQuery: '',
        clientResults: [],
        clientSearching: false,
        clientSearched: false,
        clientSearchToken: 0,

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
            total_passengers: 0,
            adults_count: 0,
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

            // Custom Package Specifications (Step 4)
            custom_hotel_name: '',
            custom_preferred_hotel: '',
            custom_has_breakfast: true,
            custom_bed_config: 'Queen Bed',
            custom_check_in_date: '',
            custom_check_out_date: '',
            custom_smoking_preference: 'non_smoking',
            custom_pet_friendly: false,
            custom_has_transportation: true,
            custom_transportation_type: 'Roundtrip Airport Transfer',
            custom_special_requests: '',
            custom_estimated_budget: '',

            estimated_fare: 0,
            taxes_amount: 0,
            visa_assistance_fee: 0,
            insurance_fee: 0,
            other_charges: 0,
            total_amount: 0,
            clients: [],
            // Empty until a client is picked or a traveller is added with the + buttons.
            passengers: []
        },

        get isCustomPackage() {
            return this.formData.package_type === 'custom_package' || this.formData.travel_package_id === 'custom';
        },

        get stepSequence() {
            const hasCustom = this.isCustomPackage;
            if (this.formData.travel_type === 'international') {
                return hasCustom
                    ? [1, 2, 3, 6, 4, 5, 7, 8, 9, 10, 11, 12]
                    : [1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12];
            }
            return hasCustom
                ? [1, 2, 3, 6, 4, 5, 10, 12]
                : [1, 2, 3, 4, 5, 10, 12];
        },

        get stepIndex() {
            const i = this.stepSequence.indexOf(this.currentStep);
            return i === -1 ? 0 : i;
        },

        get totalSteps() {
            return this.stepSequence.length;
        },

        get activeSteps() {
            const hasCustom = this.isCustomPackage;
            if (this.formData.travel_type === 'international') {
                return hasCustom
                    ? ['Travel Type', 'Documents', 'Destination', 'Customize Package', 'Trip Details', 'Passengers', 'Visa', 'Insurance', 'Services', 'Emergency', 'Requests', 'Review']
                    : ['Travel Type', 'Documents', 'Destination', 'Trip Details', 'Passengers', 'Visa', 'Insurance', 'Services', 'Emergency', 'Requests', 'Review'];
            }
            return hasCustom
                ? ['Travel Type', 'Documents', 'Destination', 'Customize Package', 'Trip Details', 'Passengers', 'Contact', 'Review']
                : ['Travel Type', 'Documents', 'Destination', 'Trip Details', 'Passengers', 'Contact', 'Review'];
        },

        get activeStepTitles() {
            return this.stepSequence.map((stepId, idx) => {
                const num = idx + 1;
                switch (stepId) {
                    case 1: return `Step ${num} - Travel Type & Passengers`;
                    case 2: return `Step ${num} - Travel Document Uploads`;
                    case 3: return `Step ${num} - Destination & Package`;
                    case 6: return `Step ${num} - Customize Package Specifications`;
                    case 4: return `Step ${num} - Trip & Flight Specifications`;
                    case 5: return `Step ${num} - Passenger Information Manifest`;
                    case 7: return `Step ${num} - Visa Requirements & Assistance`;
                    case 8: return `Step ${num} - Travel Insurance Protection`;
                    case 9: return `Step ${num} - Optional Travel Concierge Services`;
                    case 10: return `Step ${num} - Contact Details`;
                    case 11: return `Step ${num} - Special Requests & Seating`;
                    case 12: return `Step ${num} - Review, Verification & Quotation`;
                    default: return `Step ${num}`;
                }
            });
        },

        get activePackages() {
            const isIntl = this.formData.travel_type === 'international';
            return this.packages.filter(p => {
                if (isIntl) {
                    if (p.package_type === 'international') return true;
                    if (p.destination && p.destination.type === 'international') return true;
                    if (p.destination && p.destination.type === 'domestic') return false;
                    return true;
                } else {
                    if (p.package_type === 'domestic') return true;
                    if (p.destination && p.destination.type === 'domestic') return true;
                    if (p.destination && p.destination.type === 'international') return false;
                    return true;
                }
            });
        },

        get selectedPackage() {
            if (!this.formData.travel_package_id) return null;
            return this.packages.find(p => p.id == this.formData.travel_package_id) || null;
        },

        init() {
            this.loadDraft();

            // Continuing a ticket saved as pending: its form, on its step.
            if (config.pendingTicket) {
                this.restorePending(config.pendingTicket);
            } else if (this.hasDraft) {
                try { this.pendingId = parseInt(localStorage.getItem(PENDING_KEY), 10) || null; } catch (e) { /* storage unavailable */ }
            }

            // Coming back from "Register client": that client wins over any draft.
            if (config.preselectedClient) {
                this.selectClient(config.preselectedClient);
            }

            // Age categories are measured on the departure date.
            this.$watch('formData.departure_date', () => this.reclassifyClients());

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
                            p.government_id_file_name = '';
                            p.birth_cert_file_name = '';
                            p.school_id_file_name = '';
                            p.exit_clearance_file_name = '';
                        });
                    }
                    // Drafts from before multi-client support carried a single client.
                    if (parsed.client && !parsed.clients) {
                        parsed.clients = [Object.assign({}, parsed.client, { passenger_type: 'adult' })];
                        if (parsed.passengers && parsed.passengers[0]) parsed.passengers[0].client_id = parsed.client.id;
                    }
                    delete parsed.client;
                    if (!Array.isArray(parsed.clients)) parsed.clients = [];

                    // Merge with current formData
                    Object.assign(this.formData, parsed);
                    this.hasDraft = true;
                }
            } catch (e) {
                // ignore
            }
        },

        get registerClientHref() {
            const typed = this.clientQuery.trim();
            return this.clientRegisterUrl + (typed ? '?name=' + encodeURIComponent(typed) : '');
        },

        async searchClients() {
            const term = this.clientQuery.trim();
            const token = ++this.clientSearchToken;

            if (term.length < 2) {
                this.clientResults = [];
                this.clientSearched = false;
                this.clientSearching = false;
                return;
            }

            this.clientSearching = true;

            try {
                const response = await fetch(this.clientSearchUrl + '?q=' + encodeURIComponent(term), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = response.ok ? await response.json() : { clients: [] };

                // Ignore an older search that finished after a newer one.
                if (token !== this.clientSearchToken) return;

                this.clientResults = data.clients || [];
            } catch (e) {
                if (token !== this.clientSearchToken) return;
                this.clientResults = [];
            }

            this.clientSearching = false;
            this.clientSearched = true;
        },

        // Fare categories by age on the travel date (mirrors TicketPassenger::typeForAge).
        ageInYears(dateOfBirth) {
            if (!dateOfBirth) return null;
            const born = new Date(dateOfBirth + 'T00:00:00');
            const on = this.formData.departure_date ? new Date(this.formData.departure_date + 'T00:00:00') : new Date();
            if (isNaN(born) || isNaN(on)) return null;
            let age = on.getFullYear() - born.getFullYear();
            const birthdayPassed = on.getMonth() > born.getMonth() || (on.getMonth() === born.getMonth() && on.getDate() >= born.getDate());
            if (!birthdayPassed) age--;
            return age;
        },

        typeForAge(age) {
            if (age >= 12) return 'adult';
            if (age >= 2) return 'child';
            return 'infant';
        },

        clientType(client) {
            const age = this.ageInYears(client.date_of_birth);
            return age === null ? null : this.typeForAge(age);
        },

        clientTypeLabel(client) {
            return { adult: 'Adult', child: 'Child', infant: 'Infant' }[this.clientType(client)] || '';
        },

        clientAgeLabel(client) {
            const age = this.ageInYears(client.date_of_birth);
            if (age === null) return 'No birth date';
            const when = this.formData.departure_date ? ' on departure' : '';
            if (age < 2) {
                const born = new Date(client.date_of_birth + 'T00:00:00');
                const on = this.formData.departure_date ? new Date(this.formData.departure_date + 'T00:00:00') : new Date();
                const months = Math.max(0, (on.getFullYear() - born.getFullYear()) * 12 + on.getMonth() - born.getMonth() - (on.getDate() < born.getDate() ? 1 : 0));
                return months + (months === 1 ? ' month' : ' months') + when;
            }
            return age + ' yrs' + when;
        },

        isClientPicked(client) {
            return this.formData.clients.some(c => c.id === client.id);
        },

        countFieldFor(type) {
            return { adult: 'adults_count', child: 'children_count', infant: 'infants_count' }[type];
        },

        // Add a registered client as a traveller: the first one is also the booker.
        selectClient(client) {
            if (this.isClientPicked(client)) return;

            const traveller = Object.assign({}, client, { passenger_type: this.clientType(client) });
            this.formData.clients.push(traveller);

            if (this.formData.clients.length === 1) {
                this.fillBookerFromClient(traveller);
            }

            // Without a birth date the client waits for staff to enter one.
            if (traveller.passenger_type) this.placeClient(traveller);

            this.clientQuery = '';
            this.clientResults = [];
            this.clientSearched = false;
            this.saveDraft();
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        fillBookerFromClient(client) {
            this.formData.contact_name = client.name || '';
            this.formData.contact_email = client.email || '';
            this.formData.contact_phone = client.phone || '';

            ['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone', 'emergency_contact_email'].forEach(field => {
                if (client[field]) this.formData[field] = client[field];
            });
        },

        // Put the client in an empty passenger slot of their category, adding a slot when none is free.
        placeClient(client) {
            const isFreeSlot = p => p.passenger_type === client.passenger_type && !p.client_id && !(p.first_name || '').trim() && !(p.last_name || '').trim();

            let slot = this.formData.passengers.find(isFreeSlot);
            if (!slot) {
                this.formData[this.countFieldFor(client.passenger_type)]++;
                this.recalculatePassengers();
                slot = this.formData.passengers.find(isFreeSlot);
            }

            this.fillPassengerFromClient(slot, client);
        },

        fillPassengerFromClient(p, client) {
            p.client_id = client.id;
            p.first_name = client.first_name || '';
            p.middle_name = client.middle_name || '';
            p.last_name = client.last_name || '';
            p.suffix = client.suffix || '';
            p.gender = client.gender || '';
            p.date_of_birth = client.date_of_birth || '';
            p.nationality_type = client.is_filipino ? 'filipino' : 'foreign_national';
            p.passport_number = client.passport_number || '';
            p.passport_expiry_date = client.passport_expiry || '';
            p.use_profile_passport = !!client.has_passport_scan;
            p.use_profile_government_id = !!client.has_government_id_scan;
            this.checkPassportValidity(p);
        },

        // Free the client's passenger slot.
        detachClient(client) {
            const index = this.formData.passengers.findIndex(p => p.client_id === client.id);
            if (index === -1) return;

            const p = this.formData.passengers[index];
            const countField = this.countFieldFor(p.passenger_type);

            this.formData.passengers.splice(index, 1);
            this.formData[countField]--;

            this.recalculatePassengers();
        },

        removeClient(client) {
            this.detachClient(client);
            const wasBooker = this.formData.clients[0] && this.formData.clients[0].id === client.id;
            this.formData.clients = this.formData.clients.filter(c => c.id !== client.id);

            if (wasBooker && this.formData.clients.length) {
                this.fillBookerFromClient(this.formData.clients[0]);
            }

            this.saveDraft();
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        // Move a client to the category their age puts them in.
        setClientType(client, type) {
            if (client.passenger_type === type) return;
            this.detachClient(client);
            client.passenger_type = type;
            this.placeClient(client);
            this.saveDraft();
        },

        // A birth date entered for a client whose profile has none; it is saved to their profile with the booking.
        setClientBirthDate(client, dateOfBirth) {
            if (!dateOfBirth) return;
            client.date_of_birth = dateOfBirth;

            const slot = this.formData.passengers.find(p => p.client_id === client.id);
            if (slot) slot.date_of_birth = dateOfBirth;

            if (client.passenger_type) {
                this.setClientType(client, this.clientType(client));
            } else {
                client.passenger_type = this.clientType(client);
                this.placeClient(client);
            }

            this.saveDraft();
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        reclassifyClients() {
            this.formData.clients.forEach(c => {
                if (c.date_of_birth) this.setClientType(c, this.clientType(c));
            });
        },

        /**
         * Save the whole form on the server as a pending ticket, then clear
         * the wizard for the next client. The ticket is continued later from
         * the Ticket Directory, on the same step with everything filled in.
         */
        async savePending() {
            this.pendingSaving = true;

            try {
                const response = await fetch(config.pendingSaveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        draft_id: this.pendingId,
                        step: this.currentStep,
                        payload: JSON.parse(JSON.stringify(this.formData)),
                    }),
                });

                if (!response.ok) throw new Error('save failed');

                const saved = await response.json();

                // Start the next client on an empty form.
                try {
                    localStorage.removeItem(DRAFT_KEY);
                    localStorage.removeItem(PENDING_KEY);
                } catch (e) { /* storage unavailable */ }

                window.location.href = saved.redirect;
            } catch (e) {
                alert('The ticket could not be saved as pending. Check the connection and try again.');
                this.pendingSaving = false;
            }
        },

        restorePending(pending) {
            const payload = pending.payload || {};

            // Uploaded files cannot travel with a saved form; they are attached again.
            (payload.passengers || []).forEach(p => {
                ['passport_file_name', 'visa_file_name', 'passport_photo_file_name', 'supporting_doc_file_name',
                    'government_id_file_name', 'birth_cert_file_name', 'school_id_file_name', 'exit_clearance_file_name']
                    .forEach(field => { p[field] = ''; });
            });

            Object.assign(this.formData, payload);
            this.pendingId = pending.id;
            this.pendingSavedAt = pending.saved_at;
            this.hasDraft = true;
            try { localStorage.setItem(PENDING_KEY, String(pending.id)); } catch (e) { /* storage unavailable */ }

            if (this.stepSequence.includes(pending.step)) {
                this.currentStep = pending.step;
            }

            this.saveDraft();
        },

        clearDraft() {
            if (confirm('Are you sure you want to clear the saved draft and reset the form?')) {
                localStorage.removeItem(DRAFT_KEY);
                localStorage.removeItem(PENDING_KEY);
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

        selectPackageOption(option) {
            this.formData.package_type = option;
            if (option === 'custom_package') {
                this.formData.travel_package_id = 'custom';
            } else if (option === 'without_package') {
                this.formData.travel_package_id = '';
            } else if (option === 'with_package' && this.formData.travel_package_id === 'custom') {
                this.formData.travel_package_id = '';
            }
            this.saveDraft();
        },

        onPackageSelect(e) {
            const pkgId = e.target.value;
            if (pkgId === 'custom') {
                this.formData.package_type = 'custom_package';
                this.formData.travel_package_id = 'custom';
            } else if (pkgId) {
                this.formData.package_type = 'with_package';
                this.formData.travel_package_id = pkgId;
                const pkg = this.packages.find(p => p.id == pkgId);
                if (pkg) {
                    if (pkg.destination) {
                        this.formData.destination = pkg.destination.name;
                        this.formData.destination_city = pkg.destination.name;
                    }
                }
            } else {
                this.formData.travel_package_id = '';
            }
            this.saveDraft();
        },

        validateStepCustom() {
            if (this.formData.custom_check_in_date && this.formData.custom_check_out_date) {
                if (new Date(this.formData.custom_check_out_date) < new Date(this.formData.custom_check_in_date)) {
                    alert('Check-out date cannot be earlier than check-in date.');
                    return false;
                }
            }
            // Auto-sync flight departure and return dates if not set yet
            if (this.formData.custom_check_in_date && !this.formData.departure_date) {
                this.formData.departure_date = this.formData.custom_check_in_date;
            }
            if (this.formData.custom_check_out_date && !this.formData.return_date) {
                this.formData.return_date = this.formData.custom_check_out_date;
            }
            // Auto-sync budget with estimated fare if not set
            if (this.formData.custom_estimated_budget) {
                const b = parseFloat(this.formData.custom_estimated_budget);
                if (b > 0 && (!this.formData.estimated_fare || this.formData.estimated_fare == 0)) {
                    this.formData.estimated_fare = b;
                    this.calculateGrandTotal();
                }
            }
            this.saveDraft();
            return true;
        },

        goToStep(step) {
            // Only allow jumping back to a step already passed.
            if (this.stepSequence.indexOf(step) <= this.stepIndex) {
                this.currentStep = step;
            }
        },

        nextStep() {
            const seq = this.stepSequence;
            if (this.stepIndex < seq.length - 1) {
                this.currentStep = seq[this.stepIndex + 1];
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        prevStep() {
            if (this.stepIndex > 0) {
                this.currentStep = this.stepSequence[this.stepIndex - 1];
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        },

        incrementPassenger(field) {
            this.formData[field]++;
            this.recalculatePassengers();
            this.saveDraft();
        },

        decrementPassenger(field) {

            // A slot held by a picked client goes when that client is removed.
            const type = { adults_count: 'adult', children_count: 'child', infants_count: 'infant' }[field];
            const heldByClients = this.formData.passengers.filter(p => p.client_id && p.passenger_type === type).length;
            if (this.formData[field] <= heldByClients) {
                alert('Each ' + type + ' slot is filled by a registered client. Remove the client from Travellers instead.');
                return;
            }

            if (this.formData[field] > 0) {
                this.formData[field]--;
                this.recalculatePassengers();
                this.saveDraft();
            }
        },

        recalculatePassengers() {
            const adults = parseInt(this.formData.adults_count) || 0;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;
            this.formData.total_passengers = adults + children + infants;

            // Client passengers stay in their own category; the others fill the
            // remaining slots in order, taking the category of the slot.
            const linked = { adult: [], child: [], infant: [] };
            const unlinked = [];
            this.formData.passengers.forEach(p => {
                if (p.client_id && linked[p.passenger_type]) {
                    linked[p.passenger_type].push(p);
                } else {
                    unlinked.push(p);
                }
            });

            const targetList = [];
            [['adult', adults], ['child', children], ['infant', infants]].forEach(([type, count]) => {
                const slots = linked[type].slice(0, count);
                while (slots.length < count) {
                    slots.push(this.createPassengerObj(unlinked.shift(), type));
                }
                targetList.push(...slots);
            });

            this.formData.passengers = targetList;
            this.calculateGrandTotal();
        },

        createPassengerObj(existing, type) {
            if (existing) {
                existing.passenger_type = type;
                return existing;
            }
            return {
                client_id: null,
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
                government_id_file_name: '',
                birth_cert_file_name: '',
                school_id_file_name: '',
                exit_clearance_file_name: '',
            };
        },

        passengerLabel(p, idx) {
            const name = [p.first_name, p.last_name].filter(Boolean).join(' ').trim();
            if (name) return name;
            const type = { adult: 'Adult', child: 'Child', infant: 'Infant' }[p.passenger_type] || '';
            return 'Passenger #' + (idx + 1) + (type ? ' · ' + type : '');
        },

        // Mirrors the server rule: a passport scan is required for international
        // travel and for foreign nationals, whose passport is their identity document.
        passportRequired(p) {
            return this.formData.travel_type === 'international' || p.nationality_type === 'foreign_national';
        },

        passportScanStatus(p) {
            if (p.passport_file_name) {
                return { upload: '✓ Attached', review: 'Uploaded', tone: 'bg-emerald-100 text-emerald-800' };
            }
            if (p.client_id && p.use_profile_passport) {
                return { upload: '✓ On client profile', review: 'On profile', tone: 'bg-emerald-100 text-emerald-800' };
            }
            if (this.passportRequired(p)) {
                return { upload: 'Required', review: 'Missing', tone: 'bg-rose-100 text-rose-800' };
            }
            return { upload: 'Optional', review: 'Not required', tone: 'bg-gray-100 text-dark/60' };
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

        /**
         * Save the trip as a quotation and jump straight to the booking
         * agreement, without the document or manifest checks.
         */
        saveAsQuotation() {
            if (!this.formData.origin || !this.formData.origin.trim() ||
                !this.formData.destination || !this.formData.destination.trim() ||
                !this.formData.departure_date) {
                alert('A quotation still needs the route and departure date. Fill in Trip & Flight Specifications first.');
                this.currentStep = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            if (!this.formData.contact_name || !this.formData.contact_name.trim()) {
                alert('A quotation needs a client name to address it to.');
                this.currentStep = 10;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return;
            }

            this.quotationMode = true;
            this.isSubmitting = true;

            this.$nextTick(() => {
                document.getElementById('bookingWizardForm').requestSubmit();
            });
        },

        // STEP VALIDATIONS
        validateStep1() {
            const missingBirthDate = this.formData.clients.find(c => !c.date_of_birth);
            if (missingBirthDate) {
                alert('Step 1: Enter the birth date of ' + missingBirthDate.name + ' so they can be booked as Adult, Child or Infant.');
                return false;
            }

            const adults = parseInt(this.formData.adults_count) || 0;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;

            if (adults < 1) {
                alert(adults + children + infants === 0
                    ? 'Step 1: Pick the travellers from registered clients, or add them with the + buttons.'
                    : 'Step 1: A booking needs at least one adult passenger.');
                return false;
            }
            if (infants > adults) {
                alert('Step 1: Each infant must be accompanied by an adult.');
                return false;
            }
            this.formData.total_passengers = adults + children + infants;
            return true;
        },


        validateStep3() {
            if (this.formData.travel_type === 'international') {
                if (!this.formData.destination_country || !this.formData.destination_country.trim()) {
                    alert('Step 3: Please specify the Destination Country.');
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.destination_city || !this.formData.destination_city.trim()) {
                    alert('Step 3: Please specify the Destination City.');
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.arrival_airport || !this.formData.arrival_airport.trim()) {
                    alert('Step 3: Please specify the Arrival Airport.');
                    this.currentStep = 3;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }
            return true;
        },

        validateStep4() {
            if (!this.formData.origin || !this.formData.origin.trim()) {
                alert('Step 4: Please provide Origin Airport / City.');
                this.currentStep = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.destination || !this.formData.destination.trim()) {
                alert('Step 4: Please provide Destination.');
                this.currentStep = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.departure_date) {
                alert('Step 4: Please select a Departure Date.');
                this.currentStep = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            const today = new Date().toISOString().split('T')[0];
            if (this.formData.departure_date < today) {
                alert('Step 4: Departure date cannot be in the past.');
                this.currentStep = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            if (this.formData.trip_type === 'round_trip') {
                if (!this.formData.return_date) {
                    alert('Step 4: Please select a Return Date for Round Trip bookings.');
                    this.currentStep = 4;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (this.formData.return_date <= this.formData.departure_date) {
                    alert('Step 4: Return date must be later than departure date.');
                    this.currentStep = 4;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }
            return true;
        },

        validateStep5() {
            const adults = parseInt(this.formData.adults_count) || 0;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;
            const total = parseInt(this.formData.total_passengers) || 0;

            if ((adults + children + infants) !== total) {
                alert('Step 5: The sum of Adults, Children, and Infants must equal Total Passengers.');
                this.currentStep = 5;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                if (!p.first_name || !p.first_name.trim()) {
                    alert('Step 5: Please fill in First Name for Passenger #' + num);
                    this.currentStep = 5;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.last_name || !p.last_name.trim()) {
                    alert('Step 5: Please fill in Last Name for Passenger #' + num);
                    this.currentStep = 5;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.date_of_birth) {
                    alert('Step 5: Please provide Date of Birth for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                    this.currentStep = 5;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                const age = this.ageInYears(p.date_of_birth);
                const ageType = age === null ? p.passenger_type : this.typeForAge(age);
                if (ageType !== p.passenger_type) {
                    const label = t => t.charAt(0).toUpperCase() + t.slice(1);
                    alert('Step 5: Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ') is booked as ' + label(p.passenger_type) + ', but their date of birth makes them ' + label(ageType) + ' on the departure date. Adjust the Adults / Children / Infants count in Step 1.');
                    this.currentStep = 5;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!p.gender) {
                    alert('Step 5: Please select Gender for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                    this.currentStep = 5;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (this.formData.travel_type === 'international') {
                    if (!p.passport_number || !p.passport_number.trim()) {
                        alert('Step 5: Passport Number is required for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!p.passport_expiry_date) {
                        alert('Step 5: Passport Expiration Date is required for Passenger #' + num + ' (' + p.first_name + ' ' + p.last_name + ')');
                        this.currentStep = 5;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    this.checkPassportValidity(p);
                }
            }
            return true;
        },

        validateStep2() {
            // Documents in Step 2 are optional
            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                const name = (p.first_name + ' ' + p.last_name).trim() || ('Passenger #' + num);

                const fileInput = document.querySelector('input[name="passengers[' + i + '][passport_file]"]');
                const hasFileInDOM = fileInput && fileInput.files && fileInput.files.length > 0;

                if (!hasFileInDOM && p.passport_file_name) {
                    p.passport_file_name = '';
                }

                if (this.formData.departure_date && p.passport_expiry_date) {
                    const dep = new Date(this.formData.departure_date);
                    const exp = new Date(p.passport_expiry_date);
                    const sixMonths = new Date(dep);
                    sixMonths.setMonth(sixMonths.getMonth() + 6);

                    if (exp < sixMonths) {
                        alert('Step 2: Passenger #' + num + ' (' + name + ') passport expires on ' + p.passport_expiry_date + ', which is less than six (6) months from departure (' + this.formData.departure_date + '). Passport must be renewed before international travel.');
                        this.currentStep = 2;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }
            }
            return true;
        },

        validateStep7() {
            for (let i = 0; i < this.formData.passengers.length; i++) {
                const p = this.formData.passengers[i];
                const num = i + 1;
                const name = (p.first_name + ' ' + p.last_name).trim() || ('Passenger #' + num);

                if (p.visa_status === 'already_has_visa') {
                    const visaInput = document.querySelector('input[name="passengers[' + i + '][visa_file]"]');
                    const hasVisaFile = visaInput && visaInput.files && visaInput.files.length > 0;
                    if (!hasVisaFile && !p.visa_file_name) {
                        alert('Step 7: Passenger #' + num + ' (' + name + ') has "Already Has Visa" selected. Please upload a Visa Copy.');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasVisaFile && p.visa_file_name) {
                        p.visa_file_name = '';
                        alert('Step 7: Please re-select the Visa Copy for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }

                if (p.visa_status === 'needs_assistance') {
                    const photoInput = document.querySelector('input[name="passengers[' + i + '][passport_photo_file]"]');
                    const hasPhoto = photoInput && photoInput.files && photoInput.files.length > 0;
                    if (!hasPhoto && !p.passport_photo_file_name) {
                        alert('Step 7: Passenger #' + num + ' (' + name + ') requested Visa Assistance. Please upload a 2x2 Passport Photo.');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasPhoto && p.passport_photo_file_name) {
                        p.passport_photo_file_name = '';
                        alert('Step 7: Please re-select the 2x2 Passport Photo for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }

                    const docInput = document.querySelector('input[name="passengers[' + i + '][supporting_doc_file]"]');
                    const hasDoc = docInput && docInput.files && docInput.files.length > 0;
                    if (!hasDoc && !p.supporting_doc_file_name) {
                        alert('Step 7: Passenger #' + num + ' (' + name + ') requested Visa Assistance. Please upload Supporting Documents (COE / Bank Cert).');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                    if (!hasDoc && p.supporting_doc_file_name) {
                        p.supporting_doc_file_name = '';
                        alert('Step 7: Please re-select Supporting Documents for Passenger #' + num + ' (' + name + ').');
                        this.currentStep = 7;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        return false;
                    }
                }
            }
            return true;
        },

        validateStep10() {
            if (this.formData.travel_type === 'international') {
                if (!this.formData.emergency_contact_name || !this.formData.emergency_contact_name.trim()) {
                    alert('Step 10: Please provide the Emergency Contact Full Name.');
                    this.currentStep = 10;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.emergency_contact_relationship || !this.formData.emergency_contact_relationship.trim()) {
                    alert('Step 10: Please specify Relationship to Passenger.');
                    this.currentStep = 10;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
                if (!this.formData.emergency_contact_phone || !this.formData.emergency_contact_phone.trim()) {
                    alert('Step 10: Please provide the Emergency Contact Mobile / Phone Number.');
                    this.currentStep = 10;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                    return false;
                }
            }

            // Booker contact validation required by backend
            if (!this.formData.contact_name || !this.formData.contact_name.trim()) {
                alert('Step 10: Please provide the Booker Contact Name.');
                this.currentStep = 10;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.contact_email || !this.formData.contact_email.trim()) {
                alert('Step 10: Please provide the Booker Contact Email.');
                this.currentStep = 10;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }
            if (!this.formData.contact_phone || !this.formData.contact_phone.trim()) {
                alert('Step 10: Please provide the Booker Mobile / Phone Number.');
                this.currentStep = 10;
                window.scrollTo({ top: 0, behavior: 'smooth' });
                return false;
            }

            return true;
        },

        validateStep12() {
            return this.validateStep2() && this.validateStep7();
        },

        validateSubmission(e) {
            // Quotations are saved on the trip details alone; the document and
            // manifest rules are applied when the booking is completed.
            if (this.quotationMode) {
                return true;
            }

            if (this.formData.travel_type === 'international') {
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
                if (!this.validateStep2()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep7()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
                if (!this.validateStep10()) {
                    e.preventDefault();
                    this.isSubmitting = false;
                    return false;
                }
            } else {
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
                if (!this.formData.contact_name || !this.formData.contact_email || !this.formData.contact_phone) {
                    alert('Please provide Booker Contact Name, Email, and Phone Number.');
                    this.currentStep = 10;
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
