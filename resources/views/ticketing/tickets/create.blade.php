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
         companyMembersUrl: {{ Js::from(route('ticketing.companies.members', '__ID__')) }},
         passportUploadUrl: {{ Js::from(route('ticketing.clients.passport', ['client' => '__CLIENT__'])) }},
         governmentIdUploadUrl: {{ Js::from(route('ticketing.clients.government-id', ['client' => '__CLIENT__'])) }},
         preselectedClient: {{ Js::from($preselectedClient ?? null) }},
         pendingTicket: {{ Js::from($pendingTicket ?? null) }},
         pendingSaveUrl: {{ Js::from(route('ticketing.tickets.pending.store')) }},
         createUrl: {{ Js::from(route('ticketing.tickets.create')) }},
         airlines: {{ Js::from($airlines ?? []) }},
         insurancePlans: {{ Js::from($insurancePlans ?? []) }}
     })">
    
    <!-- A document the server would refuse, caught as it is picked -->
    <div x-show="uploadProblem" x-cloak role="alert" class="flex items-start justify-between gap-3 p-4 rounded-xl bg-rose-50 border border-rose-200 text-sm text-rose-800">
        <span><span class="font-bold">File not attached.</span> <span x-text="uploadProblem"></span></span>
        <button type="button" @click="uploadProblem = ''" class="font-bold underline shrink-0">Dismiss</button>
    </div>

    <!-- Top Step Bar & Progress Header -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-gray-200 shadow-card space-y-6">
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

                <!-- Throws the whole form away and starts the next client on an empty one -->
                <button type="button" @click="cancelTransaction()" :disabled="isSubmitting || pendingSaving"
                        title="Cancel this transaction and clear the form"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white border border-rose-200 text-rose-700 font-bold text-xs whitespace-nowrap hover:bg-rose-50 transition-colors disabled:opacity-50">
                    <i data-lucide="x-circle" class="w-4 h-4"></i>
                    <span>Cancel Transaction</span>
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

    <!-- Every problem on the current step at once; each links to its field -->
    <div id="wizard-errors" x-show="errorCount > 0" x-cloak tabindex="-1" role="alert"
         class="rounded-2xl bg-rose-50 border border-rose-200 p-4 sm:p-5 focus:outline-none">
        <div class="flex items-center gap-2 text-rose-800">
            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0"></i>
            <p class="text-xs font-bold" x-text="errorCount === 1 ? 'Fix this to continue:' : 'Fix these ' + errorCount + ' things to continue:'"></p>
        </div>
        <ul class="mt-2 space-y-1 pl-6 list-disc text-rose-700">
            <template x-for="(message, key) in errors" :key="key">
                <li><button type="button" @click="focusError(key)" class="text-left text-xs font-semibold hover:underline" x-text="message"></button></li>
            </template>
        </ul>
    </div>

    <!-- Main Wizard Form Container -->
    <form method="POST" action="{{ route('ticketing.tickets.store') }}" enctype="multipart/form-data" novalidate id="bookingWizardForm" @submit="validateSubmission($event)" @keydown.enter="onEnterKey($event)">
        @csrf
        <input type="hidden" name="pending_ticket_id" :value="pendingId || ''">

        <!-- Hidden input for JSON/State -->
        <input type="hidden" name="save_as_quotation" :value="quotationMode ? 1 : 0">
        <input type="hidden" name="travel_type" x-model="formData.travel_type">
        <input type="hidden" name="package_type" x-model="formData.package_type">
        <input type="hidden" name="total_passengers" x-model="formData.total_passengers">
        <input type="hidden" name="custom_has_hotel" :value="formData.custom_has_hotel ? '1' : '0'">
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
        <input type="hidden" name="custom_special_requests" :value="formData.custom_has_special_requests ? formData.custom_special_requests : ''">
        <input type="hidden" name="custom_estimated_budget" :value="formData.custom_estimated_budget">

        <!-- ========================================================================= -->
        <!-- STEP 1: TRAVEL TYPE &AMP; PASSENGERS -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 1" class="space-y-6">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Travel Type & Passengers'">Step 1: Travel Type &amp; Passengers</span></h2>
                    <!-- Pax counter: automatic, from the travellers picked below (each placed by age) -->
                    <div class="inline-flex items-stretch self-start sm:self-auto shrink-0 rounded-lg border border-gray-200 bg-white overflow-hidden text-xs divide-x divide-gray-200"
                         aria-live="polite" aria-label="Passenger count">
                        <span class="flex items-center gap-1.5 px-3 py-1.5 bg-navy-700 text-white font-semibold">
                            <i data-lucide="users" class="w-3.5 h-3.5"></i>
                            <span x-text="formData.total_passengers + ' Pax'">0 Pax</span>
                        </span>
                        <template x-for="part in [[formData.adults_count, 'Adult', 'Adults'], [formData.children_count, 'Child', 'Children'], [formData.infants_count, 'Infant', 'Infants']]" :key="part[1]">
                            <span class="flex items-center gap-1 px-3 py-1.5" :class="part[0] ? 'text-slate-700' : 'text-slate-400'">
                                <span class="font-bold" x-text="part[0]"></span>
                                <span x-text="part[0] === 1 ? part[1] : part[2]"></span>
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Travellers: pick every registered client on this trip; each fills a passenger slot by age -->
                <div class="p-5 rounded-2xl border-2 space-y-3 transition-colors"
                     :class="formData.clients.length ? 'border-emerald-300 bg-emerald-50/20' : 'border-primary/20 bg-primary/5'">
                    <input type="hidden" name="client_user_id" :value="formData.clients.length ? formData.clients[0].id : ''">
                    <input type="hidden" name="corporate_account_id" :value="formData.corporate_account_id || ''">

                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-primary block">Travellers</span>
                        </div>
                        <a :href="registerClientHref"
                           class="inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-primary/30 text-primary font-bold text-xs hover:bg-primary/5 transition-colors shrink-0">
                            <i data-lucide="user-plus" class="w-4 h-4"></i>
                            <span>Register client</span>
                        </a>
                    </div>

                    <!-- A corporate booking: the company its travellers came from -->
                    <div x-show="formData.corporate_account_id" x-cloak class="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-xl bg-white border border-slate-200">
                        <span class="flex items-center gap-2 text-xs text-slate-700 min-w-0">
                            <i data-lucide="building-2" class="w-4 h-4 text-navy-700 shrink-0"></i>
                            <span class="truncate">Corporate booking for <strong class="text-slate-900" x-text="formData.corporate_name"></strong></span>
                        </span>
                        <button type="button" @click="clearCorporate()" class="text-[11px] font-semibold text-slate-500 hover:text-rose-600 shrink-0">Not corporate</button>
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
                                               class="px-2.5 py-1 rounded-lg bg-white border border-amber-300 text-dark text-[11px] font-bold focus:outline-none focus:ring-2 focus:ring-primary"
                                               :data-error-key="'clients.' + c.id" :class="errors['clients.' + c.id] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['clients.' + c.id] ? 'true' : null">
                                        <x-ticketing.field-error key="'clients.' + c.id" />
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
                               :placeholder="formData.clients.length ? 'Add another traveller: name, email, phone, passport or company' : 'Search by name, email, phone, passport number or company'"
                               aria-label="Search registered clients"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">

                        <!-- Corporate accounts: open one to add its travellers together -->
                        <div x-show="companyResults.length > 0 && !company" class="mt-2 rounded-xl border border-gray-200 bg-white divide-y divide-gray-100 overflow-hidden">
                            <template x-for="co in companyResults" :key="'co' + co.id">
                                <button type="button" @click="openCompany(co)"
                                        class="w-full text-left px-4 py-2.5 hover:bg-primary/5 transition-colors flex items-center justify-between gap-3">
                                    <span class="flex items-center gap-2.5 min-w-0">
                                        <i data-lucide="building-2" class="w-4 h-4 text-navy-700 shrink-0"></i>
                                        <span class="min-w-0">
                                            <span class="block text-xs font-bold text-dark truncate" x-text="co.name"></span>
                                            <span class="block text-[11px] text-dark/50" x-text="'Company · ' + co.members_count + (co.members_count === 1 ? ' member' : ' members')"></span>
                                        </span>
                                    </span>
                                    <span class="text-[11px] font-bold text-primary shrink-0">Choose travellers</span>
                                </button>
                            </template>
                        </div>

                        <!-- The company's members: tick who travels -->
                        <div x-show="company" x-cloak class="mt-2 rounded-xl border border-slate-200 bg-white overflow-hidden">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-slate-100 bg-slate-50">
                                <span class="flex items-center gap-2 text-xs font-bold text-slate-900 min-w-0">
                                    <i data-lucide="building-2" class="w-4 h-4 text-navy-700 shrink-0"></i>
                                    <span class="truncate" x-text="company?.name"></span>
                                </span>
                                <span class="flex items-center gap-3">
                                    <button type="button" x-show="company?.members.length" @click="toggleAllMembers()" class="text-[11px] font-bold text-primary hover:underline">Select all</button>
                                    <button type="button" @click="company = null" class="text-[11px] font-semibold text-slate-500 hover:text-slate-800">Close</button>
                                </span>
                            </div>
                            <p x-show="company?.loading" class="px-4 py-3 text-[11px] text-dark/50">Loading the company's travellers&hellip;</p>
                            <p x-show="company && !company.loading && !company.members.length" class="px-4 py-3 text-[11px] text-dark/60">This company has no travellers on file yet.</p>
                            <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                                <template x-for="m in (company?.members || [])" :key="'m' + m.id">
                                    <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-slate-50" :class="isClientPicked(m) && 'opacity-50 cursor-default'">
                                        <input type="checkbox" :value="m.id" x-model.number="company.selected" :disabled="isClientPicked(m)"
                                               class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-700">
                                        <span class="min-w-0">
                                            <span class="block text-xs font-bold text-dark truncate" x-text="m.name"></span>
                                            <span class="block text-[11px] text-dark/50 truncate"
                                                  x-text="isClientPicked(m) ? 'Already added' : [clientAgeLabel(m), m.passport_number ? 'Passport ' + m.passport_number : null].filter(Boolean).join(' · ')"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-t border-slate-100">
                                <a :href="company ? clientRegisterUrl + '?corporate=' + company.id : '#'" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-primary hover:underline">
                                    <i data-lucide="user-plus" class="w-3.5 h-3.5"></i> Register an employee
                                </a>
                                <button type="button" @click="addCompanyMembers()" :disabled="!company?.selected.length"
                                        class="h-9 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white text-xs font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                                        x-text="'Add selected (' + (company?.selected.length || 0) + ')'">Add selected</button>
                            </div>
                        </div>

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
                        <p x-show="clientSearched && !clientSearching && clientResults.length === 0 && companyResults.length === 0" class="text-[11px] text-dark/60 mt-2">
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
                        </div>
                        <div class="mt-4 space-y-1">
                            <h3 class="font-heading font-bold text-base text-dark">Domestic Philippine Tours</h3>
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
                        </div>
                        <div class="mt-4 space-y-1">
                            <h3 class="font-heading font-bold text-base text-dark">International Tour Booking</h3>
                        </div>
                    </label>
                </div>

                <!-- What the pax count needs: at least one adult, and an adult for each infant -->
                <div data-error-key="adults_count" tabindex="-1" class="focus:outline-none">
                    <x-ticketing.field-error key="'adults_count'" />
                    <x-ticketing.field-error key="'infants_count'" />
                </div>
                <!-- Hidden counters for POST -->
                <input type="hidden" name="adults_count" :value="formData.adults_count">
                <input type="hidden" name="children_count" :value="formData.children_count">
                <input type="hidden" name="infants_count" :value="formData.infants_count">

                <!-- Navigation -->
                <div class="flex items-center justify-end pt-4 border-t border-gray-100">
                    <button type="button" @click="checkStep(1) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md">
                        <span>Continue to Document Requirements</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>


        <!-- ========================================================================= -->
        <!-- STEP 3: DESTINATION &AMP; FLIGHT (trip & flight merged in) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 3" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Destination & Flight'">Step 3: Destination &amp; Flight</span></h2>
                </div>

                <!-- Package Option Selector: chosen first, the rest of the step follows from it -->
                <div class="space-y-4">
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
                                    <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-accent text-dark">Next step</span>
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
                                data-error-key="travel_package_id" :aria-invalid="errors['travel_package_id'] ? 'true' : null"
                                :class="errors['travel_package_id'] ? '!border-rose-400 !bg-rose-50/60' : ''"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">-- Choose Travel Package --</option>
                            <option value="custom" class="font-bold text-primary bg-accent/20">✨ Customized Package (configure it in the next step)</option>
                            <template x-for="pkg in activePackages" :key="pkg.id">
                                <option :value="pkg.id" x-text="pkg.title + ' (' + (pkg.destination ? pkg.destination.name : 'All') + (pkg.price ? ' - ' + pkg.price : '') + ')'"></option>
                            </template>
                        </select>
                        <x-ticketing.field-error key="'travel_package_id'" />

                        <!-- Customized Package Active Banner -->
                        <div x-show="isCustomPackage" class="p-4 rounded-xl bg-accent/15 border border-accent/40 text-dark space-y-1.5">
                            <div class="flex items-center gap-2">
                                <i data-lucide="sparkles" class="w-4 h-4 text-primary shrink-0"></i>
                                <span class="text-xs font-heading font-extrabold uppercase tracking-wider text-dark">Customized Package Enabled</span>
                            </div>
                            <p class="text-xs text-dark/70 leading-relaxed">
                                You have selected to customize this package. When you click <strong>Continue to Customize Package</strong> below, the <strong>Customize Package</strong> step opens, allowing you to configure hotel, bedding, breakfast, smoking/pet rules, and ground transportation.
                            </p>
                        </div>

                        <!-- Selected Package Configuration Highlights (for Ready-Made) -->
                        <div x-show="selectedPackage && !isCustomPackage" x-data="{ open: false }" class="p-4 rounded-xl bg-white border border-primary/20 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs font-bold text-primary flex items-center gap-1.5 min-w-0">
                                    <i data-lucide="package" class="w-3.5 h-3.5 shrink-0"></i>
                                    <span class="truncate" x-text="selectedPackage?.title"></span>
                                </span>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="text-xs font-extrabold text-navy" x-text="selectedPackage?.price || ''"></span>
                                    <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="selected-package-details"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-primary/20 text-[11px] font-bold text-primary hover:bg-primary/5 transition-colors">
                                        <span x-text="open ? 'Hide details' : 'Show details'">Show details</span>
                                        <i class="fa-solid fa-chevron-down text-[9px] transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-gray-100 text-[11px]">
                                <div x-show="selectedPackage?.hotel_name || selectedPackage?.preferred_hotel" class="text-dark/70">
                                    <span class="font-bold text-dark block">Hotel:</span>
                                    <span x-text="selectedPackage?.hotel_name || selectedPackage?.preferred_hotel"></span>
                                </div>
                                <div x-show="selectedPackage?.bed_config" class="text-dark/70">
                                    <span class="font-bold text-dark block">Bedding:</span>
                                    <span class="capitalize" x-text="/bed$/i.test(selectedPackage?.bed_config || '') ? selectedPackage.bed_config : (selectedPackage?.bed_config || '') + ' Bed'"></span>
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

                            {{-- Everything the package carries (as on the review step); fields it leaves empty are not listed. --}}
                            <div id="selected-package-details" x-show="open" x-cloak class="pt-3 border-t border-gray-100 space-y-3">
                                <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2.5">
                                    <template x-for="[label, value] in packageDetails(selectedPackage)" :key="label">
                                        <div class="min-w-0">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-dark/50" x-text="label"></dt>
                                            <dd class="text-xs font-semibold text-dark break-words" x-text="value"></dd>
                                        </div>
                                    </template>
                                </dl>
                                <template x-for="[label, value] in packageNotes(selectedPackage)" :key="label">
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-dark/50" x-text="label"></p>
                                        <p class="text-xs text-dark/80 whitespace-pre-line" x-text="value"></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- International Destination Fields (Prompt Step 1: Destination Country, City, Airport, Airline) -->
                <div x-show="formData.travel_type === 'international' && !awaitingPackage" class="space-y-5 pt-4 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-heading font-bold text-sm text-dark">International Destination Details</h3>
                            <p class="text-xs text-dark/50">Specify the destination country, city, arrival airport, and preferred airline</p>
                        </div>
                    </div>

                    <!-- Popular Preset Quick Select (not while a package sets the destination) -->
                    <div x-show="!packageLock.destination_city">
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
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="destination_country" :readonly="packageLock.destination_country" :tabindex="packageLock.destination_country ? -1 : null"
                                   :class="(errors['destination_country'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.destination_country ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['destination_country'] ? 'true' : null">
                            <x-ticketing.field-error key="'destination_country'" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Destination City *</label>
                            <input type="text" name="destination_city" x-model="formData.destination_city"
                                   @input="onCityInput()"
                                   placeholder="e.g. Tokyo, Paris, Dubai"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="destination_city" :readonly="packageLock.destination_city" :tabindex="packageLock.destination_city ? -1 : null"
                                   :class="(errors['destination_city'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.destination_city ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['destination_city'] ? 'true' : null">
                            <x-ticketing.field-error key="'destination_city'" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Arrival Airport *</label>
                            <input type="text" name="arrival_airport" x-model="formData.arrival_airport" list="airport-options" autocomplete="off"
                                   @input="saveDraft()"
                                   placeholder="Type a city, airport or code, e.g. Tokyo or NRT"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="arrival_airport" :readonly="packageLock.arrival_airport" :tabindex="packageLock.arrival_airport ? -1 : null"
                                   :class="(errors['arrival_airport'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.arrival_airport ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['arrival_airport'] ? 'true' : null">
                            <x-ticketing.field-error key="'arrival_airport'" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Preferred Airline (Optional)</label>
                            <input type="text" name="preferred_airline" x-model="formData.preferred_airline"
                                   @input="saveDraft()"
                                   placeholder="e.g. PAL, Emirates, Singapore Airlines"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   :readonly="packageLock.preferred_airline" :tabindex="packageLock.preferred_airline ? -1 : null"
                                   :class="packageLock.preferred_airline ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : ''">
                        </div>
                    </div>
                </div>

                <!-- Trip & Flight (formerly its own step); waits for the package when Ready-Made is chosen -->
                <div x-show="!awaitingPackage" class="space-y-6 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                        <h3 class="font-heading font-bold text-sm text-dark">Trip &amp; Flight</h3>
                        <span x-show="packageLock.any" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-[10px] font-bold text-dark/60">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                            <span>Set by the package</span>
                        </span>
                    </div>

                    <!-- Trip Type (One Way, Round Trip, Multi-City) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-dark/70">Trip Type *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3" :class="packageLock.trip_type ? 'pointer-events-none opacity-70' : ''" :aria-disabled="packageLock.trip_type">
                            <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.trip_type === 'round_trip' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="trip_type" value="round_trip" x-model="formData.trip_type" @change="saveDraft()" :tabindex="packageLock.trip_type ? -1 : 0" class="sr-only">
                                <i data-lucide="repeat" class="w-4 h-4"></i>
                                <span class="text-xs">Round Trip</span>
                            </label>

                            <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.trip_type === 'one_way' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="trip_type" value="one_way" x-model="formData.trip_type" @change="saveDraft()" :tabindex="packageLock.trip_type ? -1 : 0" class="sr-only">
                                <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                <span class="text-xs">One Way</span>
                            </label>

                            <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.trip_type === 'multi_city' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="trip_type" value="multi_city" x-model="formData.trip_type" @change="saveDraft()" :tabindex="packageLock.trip_type ? -1 : 0" class="sr-only">
                                <i data-lucide="git-branch" class="w-4 h-4"></i>
                                <span class="text-xs">Multi-City</span>
                            </label>
                        </div>
                    </div>

                    <!-- Preferred Flight Time (Anytime, Morning, Afternoon, Evening) -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-dark/70">Preferred Flight Time *</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" :class="packageLock.preferred_flight_time ? 'pointer-events-none opacity-70' : ''" :aria-disabled="packageLock.preferred_flight_time">
                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.preferred_flight_time === 'anytime' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="preferred_flight_time" value="anytime" x-model="formData.preferred_flight_time" @change="saveDraft()" :tabindex="packageLock.preferred_flight_time ? -1 : 0" class="sr-only">
                                <i data-lucide="clock" class="w-3.5 h-3.5"></i>
                                <span class="text-xs">Anytime</span>
                            </label>

                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.preferred_flight_time === 'morning' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="preferred_flight_time" value="morning" x-model="formData.preferred_flight_time" @change="saveDraft()" :tabindex="packageLock.preferred_flight_time ? -1 : 0" class="sr-only">
                                <i data-lucide="sunrise" class="w-3.5 h-3.5"></i>
                                <span class="text-xs">Morning</span>
                            </label>

                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.preferred_flight_time === 'afternoon' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="preferred_flight_time" value="afternoon" x-model="formData.preferred_flight_time" @change="saveDraft()" :tabindex="packageLock.preferred_flight_time ? -1 : 0" class="sr-only">
                                <i data-lucide="sun" class="w-3.5 h-3.5"></i>
                                <span class="text-xs">Afternoon</span>
                            </label>

                            <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition-all"
                                   :class="formData.preferred_flight_time === 'evening' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                <input type="radio" name="preferred_flight_time" value="evening" x-model="formData.preferred_flight_time" @change="saveDraft()" :tabindex="packageLock.preferred_flight_time ? -1 : 0" class="sr-only">
                                <i data-lucide="moon" class="w-3.5 h-3.5"></i>
                                <span class="text-xs">Evening</span>
                            </label>
                        </div>
                    </div>

                    <!-- Origin & Destination -->
                    @php
                        // The origin dropdown: airports grouped by country, the Philippines first, each saved as "City (CODE)".
                        $originGroups = collect(config('airports', []))
                            ->map(fn ($airport, $code) => ['value' => "{$airport[0]} ({$code})", 'label' => "{$airport[0]} ({$code}) - {$airport[1]}", 'country' => $airport[2]])
                            ->groupBy('country')
                            ->sortBy(fn ($airports, $country) => $country === 'Philippines' ? '' : $country)
                            ->map(fn ($airports) => $airports->sortBy('label')->pluck('label', 'value')->all());
                        $originOptions = $originGroups->flatMap(fn ($airports) => array_keys($airports))->values()->all();
                    @endphp
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Origin Airport / City *</label>
                            {{-- Flight only and a customized package pick the origin from the airport list; a ready package keeps its own. --}}
                            <template x-if="formData.package_type !== 'with_package' || isCustomPackage">
                                <select name="origin" x-model="formData.origin" @change="saveDraft()" data-origin-select
                                        class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                        data-error-key="origin" :class="errors['origin'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['origin'] ? 'true' : null">
                                    <option value="">Select origin airport / city</option>
                                    {{-- A value saved earlier that is not on the list stays selectable. --}}
                                    <template x-if="formData.origin && ! @js($originOptions).includes(formData.origin)">
                                        <option :value="formData.origin" x-text="formData.origin"></option>
                                    </template>
                                    @foreach ($originGroups as $country => $airports)
                                        <optgroup label="{{ $country }}">
                                            @foreach ($airports as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </template>
                            <template x-if="formData.package_type === 'with_package' && ! isCustomPackage">
                                <input type="text" name="origin" x-model="formData.origin" @input="saveDraft()" list="airport-options" autocomplete="off"
                                       placeholder="Type a city, airport or code, e.g. Manila or MNL"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                       data-error-key="origin" :readonly="packageLock.origin" :tabindex="packageLock.origin ? -1 : null"
                                       :class="(errors['origin'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.origin ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['origin'] ? 'true' : null">
                            </template>
                            <x-ticketing.field-error key="'origin'" />
                            {{-- Shared by the origin and arrival airport fields. Suggestions only: an airport that is not listed can still be typed in. --}}
                            <datalist id="airport-options">
                                @foreach (config('airports', []) as $code => [$city, $airport, $country])
                                    <option value="{{ $city }} ({{ $code }})">{{ $airport }}, {{ $country }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Destination *</label>
                            <input type="text" name="destination" x-model="formData.destination" @input="saveDraft()"
                                   placeholder="e.g. Tokyo, Coron, Paris"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="destination" :readonly="packageLock.destination" :tabindex="packageLock.destination ? -1 : null"
                                   :class="(errors['destination'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.destination ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['destination'] ? 'true' : null">
                            <x-ticketing.field-error key="'destination'" />
                        </div>
                    </div>

                    <!-- Travel Dates -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Departure Date *</label>
                            <input type="date" name="departure_date" x-model="formData.departure_date" @change="saveDraft()"
                                   :min="new Date().toISOString().split('T')[0]"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="departure_date" :readonly="packageLock.departure_date" :tabindex="packageLock.departure_date ? -1 : null"
                                   :class="(errors['departure_date'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.departure_date ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['departure_date'] ? 'true' : null">
                            <x-ticketing.field-error key="'departure_date'" />
                            <span class="text-[10px] text-dark/40 mt-1 block">Departure date cannot be in the past</span>
                        </div>

                        <div x-show="formData.trip_type === 'round_trip'">
                            <label class="block text-xs font-bold text-dark/70 mb-1">Return Date *</label>
                            <input type="date" name="return_date" x-model="formData.return_date" @change="saveDraft()"
                                   :min="formData.departure_date || new Date().toISOString().split('T')[0]"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="return_date" :readonly="packageLock.return_date" :tabindex="packageLock.return_date ? -1 : null"
                                   :class="(errors['return_date'] ? '!border-rose-400 !bg-rose-50/60' : '') + (packageLock.return_date ? ' cursor-not-allowed pointer-events-none !bg-gray-100 text-dark/60' : '')" :aria-invalid="errors['return_date'] ? 'true' : null">
                            <x-ticketing.field-error key="'return_date'" />
                            <span class="text-[10px] text-dark/40 mt-1 block">Must be later than departure date</span>
                        </div>
                    </div>

                    <!-- Multi-City Segments Builder if Multi-City selected -->
                    <div x-show="formData.trip_type === 'multi_city'" class="space-y-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center justify-between">
                            <h4 class="font-heading font-bold text-xs text-dark uppercase tracking-wider">Multi-City Segments</h4>
                            <button type="button" x-show="!packageLock.segments" @click="addSegment()" class="px-2.5 py-1 rounded-lg bg-gray-100 hover:bg-gray-200 text-xs font-bold text-dark transition-colors">
                                + Add Segment
                            </button>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(seg, sidx) in formData.multi_city_segments" :key="sidx">
                                <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                    <input type="text" :name="'multi_city_segments[' + sidx + '][from]'" x-model="seg.from" placeholder="From (e.g. MNL)" :readonly="packageLock.segments" :class="packageLock.segments ? 'cursor-not-allowed !bg-gray-100 text-dark/60' : ''" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                    <input type="text" :name="'multi_city_segments[' + sidx + '][to]'" x-model="seg.to" placeholder="To (e.g. NRT)" :readonly="packageLock.segments" :class="packageLock.segments ? 'cursor-not-allowed !bg-gray-100 text-dark/60' : ''" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                    <input type="date" :name="'multi_city_segments[' + sidx + '][date]'" x-model="seg.date" class="px-2.5 py-1.5 rounded-lg bg-white border border-gray-200 text-xs">
                                    <button type="button" x-show="!packageLock.segments" @click="removeSegment(sidx)" class="text-rose-600 hover:text-rose-800 text-xs font-bold justify-self-end">Remove</button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Fare search: no airline offers a fare API, so this links out to each
                         airline's own site and records the flight chosen there. -->
                    <div class="space-y-4 pt-4 border-t border-gray-100" id="fare-search">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div>
                                <h3 class="font-heading font-bold text-sm text-dark">Search Fares</h3>
                            </div>
                            <a href="{{ route('ticketing.airlines.index') }}" target="_blank" rel="noopener"
                               class="shrink-0 text-xs font-bold text-primary hover:underline">Manage airlines</a>
                        </div>

                        <div x-show="airlines.some(a => a.booking_url || a.agent_portal_url)" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <template x-for="airline in airlines.filter(a => a.booking_url || a.agent_portal_url)" :key="airline.id">
                                <div class="p-3 rounded-xl border bg-white flex flex-col gap-2 transition-colors"
                                     :class="String(formData.airline_id) === String(airline.id) ? 'border-primary ring-1 ring-primary/20' : 'border-gray-200'">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-bold text-dark truncate" x-text="airline.name"></span>
                                        <span class="font-mono text-[11px] text-dark/50" x-text="airline.code || ''"></span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <a x-show="airline.booking_url" :href="airline.booking_url" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-white text-[11px] font-bold hover:bg-navy transition-colors">
                                            <i data-lucide="external-link" class="w-3 h-3"></i>
                                            <span>Search site</span>
                                        </a>
                                        <a x-show="airline.agent_portal_url" :href="airline.agent_portal_url" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-gray-200 text-[11px] font-bold text-dark hover:border-primary transition-colors">
                                            <i data-lucide="key-round" class="w-3 h-3"></i>
                                            <span>Agent portal</span>
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <p x-show="!airlines.length" class="text-xs text-dark/50">No airlines are set up yet. Add them under <a href="{{ route('ticketing.airlines.index') }}" class="font-bold text-primary hover:underline">Airlines</a>.</p>

                        <!-- The flight chosen on the airline's site -->
                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 space-y-3">
                            <h4 class="font-heading font-bold text-xs text-dark uppercase tracking-wider">Selected Flight</h4>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="airline_id" class="block text-xs font-bold text-dark/70 mb-1">Airline *</label>
                                    <select id="airline_id" name="airline_id" x-model="formData.airline_id" @change="saveDraft()"
                                            data-error-key="airline_id" :class="errors['airline_id'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['airline_id'] ? 'true' : null"
                                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Not chosen yet</option>
                                        <template x-for="airline in airlines" :key="airline.id">
                                            <option :value="airline.id" x-text="airline.name" :selected="String(formData.airline_id) === String(airline.id)"></option>
                                        </template>
                                    </select>
                                    <x-ticketing.field-error key="'airline_id'" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                                <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider sm:pb-3">Departing</span>
                                <div>
                                    <label for="flight_number" class="block text-xs font-bold text-dark/70 mb-1">Flight No. *</label>
                                    <input id="flight_number" type="text" name="flight_number" data-error-key="flight_number" :class="errors['flight_number'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['flight_number'] ? 'true' : null" x-model="formData.flight_number" @input="saveDraft()" maxlength="20"
                                           placeholder="e.g. 5J 5054" autocomplete="off"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-mono font-semibold uppercase placeholder:normal-case focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'flight_number'" />
                                </div>
                                <div>
                                    <label for="departure_time" class="block text-xs font-bold text-dark/70 mb-1">Departs *</label>
                                    <input id="departure_time" type="time" name="departure_time" data-error-key="departure_time" :class="errors['departure_time'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['departure_time'] ? 'true' : null" x-model="formData.departure_time" @change="saveDraft()"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'departure_time'" />
                                </div>
                                <div>
                                    <label for="arrival_time" class="block text-xs font-bold text-dark/70 mb-1">Arrives *</label>
                                    <input id="arrival_time" type="time" name="arrival_time" data-error-key="arrival_time" :class="errors['arrival_time'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['arrival_time'] ? 'true' : null" x-model="formData.arrival_time" @change="saveDraft()"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'arrival_time'" />
                                </div>
                            </div>

                            <div x-show="formData.trip_type === 'round_trip'" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                                <span class="text-[11px] font-bold text-dark/60 uppercase tracking-wider sm:pb-3">Returning</span>
                                <div>
                                    <label for="return_flight_number" class="block text-xs font-bold text-dark/70 mb-1">Flight No. *</label>
                                    <input id="return_flight_number" type="text" name="return_flight_number" data-error-key="return_flight_number" :class="errors['return_flight_number'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['return_flight_number'] ? 'true' : null" x-model="formData.return_flight_number" @input="saveDraft()" maxlength="20"
                                           placeholder="e.g. 5J 5055" autocomplete="off"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-mono font-semibold uppercase placeholder:normal-case focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'return_flight_number'" />
                                </div>
                                <div>
                                    <label for="return_departure_time" class="block text-xs font-bold text-dark/70 mb-1">Departs *</label>
                                    <input id="return_departure_time" type="time" name="return_departure_time" data-error-key="return_departure_time" :class="errors['return_departure_time'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['return_departure_time'] ? 'true' : null" x-model="formData.return_departure_time" @change="saveDraft()"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'return_departure_time'" />
                                </div>
                                <div>
                                    <label for="return_arrival_time" class="block text-xs font-bold text-dark/70 mb-1">Arrives *</label>
                                    <input id="return_arrival_time" type="time" name="return_arrival_time" data-error-key="return_arrival_time" :class="errors['return_arrival_time'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['return_arrival_time'] ? 'true' : null" x-model="formData.return_arrival_time" @change="saveDraft()"
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                    <x-ticketing.field-error key="'return_arrival_time'" />
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
                    <button type="button" @click="checkStep(3) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                        <span>Continue to Airline Restrictions</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 13: AIRLINE RESTRICTIONS (optional, editable list; shown after the flight is chosen) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 13" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Airline Restrictions'">Airline Restrictions</span></h2>
                </div>

                <div class="flex items-center gap-3 p-4 rounded-2xl bg-gray-50 border border-gray-200">
                    <i data-lucide="plane" class="w-4 h-4 text-primary shrink-0"></i>
                    <span class="text-xs text-dark/70">
                        <span x-show="selectedAirline">Restrictions for <span class="font-bold text-dark" x-text="selectedAirline ? selectedAirline.name + (selectedAirline.code ? ' (' + selectedAirline.code + ')' : '') : ''"></span></span>
                        <span x-show="!selectedAirline">No airline chosen in the flight step yet. You can still note the restrictions here.</span>
                    </span>
                </div>

                <!-- Common restrictions, one click to add -->
                <div class="space-y-2">
                    <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">Quick add</span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="preset in restrictionPresets" :key="preset">
                            <button type="button" @click="addRestriction(preset)"
                                    :disabled="formData.airline_restrictions.includes(preset)"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-[11px] font-bold transition-colors disabled:opacity-40 disabled:cursor-default"
                                    :class="formData.airline_restrictions.includes(preset) ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-dark/70 hover:border-primary hover:text-primary'">
                                <i data-lucide="plus" class="w-3 h-3"></i>
                                <span x-text="preset"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- The restrictions on this booking, each one editable -->
                <div class="space-y-2">
                    <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">Restrictions on this booking</span>
                    <p x-show="!formData.airline_restrictions.length" class="text-xs text-dark/50">None added yet.</p>
                    <template x-for="(restriction, idx) in formData.airline_restrictions" :key="idx">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[11px] font-bold flex items-center justify-center shrink-0" x-text="idx + 1"></span>
                            <input type="text" name="airline_restrictions[]" x-model="formData.airline_restrictions[idx]" @input="saveDraft()" maxlength="500"
                                   placeholder="e.g. Rebooking allowed up to 24 hours before departure"
                                   class="flex-1 min-w-0 px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                            <button type="button" @click="removeRestriction(idx)" class="p-2 rounded-lg text-rose-500 hover:bg-rose-50 shrink-0" title="Remove restriction">
                                <i data-lucide="x" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </template>
                    <button type="button" @click="addRestriction('')" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border border-dashed border-gray-300 text-xs font-bold text-dark/70 hover:border-primary hover:text-primary transition-colors">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Add a restriction</span>
                    </button>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-gray-100 hover:bg-gray-200 text-dark font-heading font-bold text-xs transition-colors cursor-pointer">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="checkStep(13) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
                        <span x-text="isCustomPackage ? 'Continue to Customize Package' : 'Continue to Passenger Details'"></span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 4: CUSTOMIZE PACKAGE SPECIFICATIONS (WHEN CUSTOM PACKAGE IS SELECTED) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 6" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
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
                                <div class="flex-1">
                                    <h3 class="text-xs font-heading font-bold text-dark">Hotel &amp; Room Specifications <span class="font-normal text-dark/40">(optional)</span></h3>
                                    <p class="text-[11px] text-dark/50">Only when the package includes a hotel stay</p>
                                </div>
                                <select @change="formData.custom_has_hotel = $event.target.value === 'yes'; saveDraft()" aria-label="Hotel accommodation"
                                        class="shrink-0 px-3 py-2 rounded-xl bg-white border border-gray-200 text-dark text-xs font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="no" :selected="!formData.custom_has_hotel">No hotel</option>
                                    <option value="yes" :selected="formData.custom_has_hotel">Include hotel accommodation</option>
                                </select>
                            </div>

                            <div x-show="formData.custom_has_hotel" class="space-y-4">
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
                                           class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                           data-error-key="custom_check_out_date" :class="errors['custom_check_out_date'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['custom_check_out_date'] ? 'true' : null">
                                    <x-ticketing.field-error key="'custom_check_out_date'" />
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
                        </div>

                        <!-- 2. Transportation & Ground Logistics -->
                        <div class="p-5 rounded-2xl bg-gray-50 border border-gray-200 space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-200/60 pb-2">
                                <div class="w-7 h-7 rounded-lg bg-primary/10 text-primary flex items-center justify-center">
                                    <i data-lucide="car" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <h3 class="text-xs font-heading font-bold text-dark">Transportation &amp; Transfers <span class="font-normal text-dark/40">(optional)</span></h3>
                                    <p class="text-[11px] text-dark/50">Ground transfers, airport pickup/drop-off, private vans, or car rental</p>
                                </div>
                            </div>

                            <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Transportation</label>
                                    <select x-model="formData.custom_transportation_type" @change="formData.custom_has_transportation = formData.custom_transportation_type !== ''; saveDraft()"
                                            class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">No transportation</option>
                                        <option value="Roundtrip Airport Transfer">Roundtrip Airport Transfer (Van/Car)</option>
                                        <option value="Private Chauffeured Van">Private Chauffeured Van (Dedicated Full-Day)</option>
                                        <option value="Dedicated Tour Bus">Dedicated Tour Bus (Group)</option>
                                        <option value="Speedboat & Land Transfer">Speedboat &amp; Land Transfer (Island Destinations)</option>
                                        <option value="Self-Drive Car Rental">Self-Drive Car Rental</option>
                                        <option value="Public Transit Pass">Public Transit Pass / Rail Card</option>
                                    </select>
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
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Special Requests <span class="font-normal text-dark/40">(optional)</span></label>
                                    <select @change="formData.custom_has_special_requests = $event.target.value === 'yes'; saveDraft()" aria-label="Special requests"
                                            class="w-full sm:w-auto mb-2 px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="no" :selected="!formData.custom_has_special_requests">None</option>
                                        <option value="yes" :selected="formData.custom_has_special_requests">Add special requests</option>
                                    </select>
                                    <textarea x-show="formData.custom_has_special_requests" rows="2" x-model="formData.custom_special_requests" @input="saveDraft()"
                                              placeholder="e.g. High floor ocean view, early check-in at 11 AM, honeymoon bed setup, halal meals..."
                                              class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-dark/70 mb-1">Target / Estimated Package Budget (₱) <span class="font-normal text-dark/40">(optional)</span></label>
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
                                    <span class="font-bold text-white text-right truncate max-w-[140px]" x-text="formData.custom_has_hotel ? (formData.custom_hotel_name || formData.custom_preferred_hotel || 'To Be Arranged') : 'No hotel'"></span>
                                </div>

                                <div x-show="formData.custom_has_hotel" class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="bed" class="w-3.5 h-3.5 text-accent"></i> Bedding:</span>
                                    <span class="font-bold text-white" x-text="formData.custom_bed_config"></span>
                                </div>

                                <div x-show="formData.custom_has_hotel" class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="coffee" class="w-3.5 h-3.5 text-accent"></i> Breakfast:</span>
                                    <span class="font-bold" :class="formData.custom_has_breakfast ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_has_breakfast ? 'Included ✓' : 'No Breakfast'"></span>
                                </div>

                                <div x-show="formData.custom_has_hotel" class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="cigarette" class="w-3.5 h-3.5 text-accent"></i> Smoking:</span>
                                    <span class="font-bold" :class="formData.custom_smoking_preference === 'non_smoking' ? 'text-emerald-400' : 'text-amber-400'" x-text="formData.custom_smoking_preference === 'non_smoking' ? 'Non-Smoking' : 'Smoking'"></span>
                                </div>

                                <div x-show="formData.custom_has_hotel" class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="paw-print" class="w-3.5 h-3.5 text-accent"></i> Pet Friendly:</span>
                                    <span class="font-bold" :class="formData.custom_pet_friendly ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_pet_friendly ? 'Allowed ✓' : 'No Pets'"></span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-white/60 flex items-center gap-1.5"><i data-lucide="car" class="w-3.5 h-3.5 text-accent"></i> Transfer:</span>
                                    <span class="font-bold text-right truncate max-w-[130px]" :class="formData.custom_has_transportation ? 'text-emerald-400' : 'text-white/40'" x-text="formData.custom_has_transportation ? formData.custom_transportation_type : 'None'"></span>
                                </div>

                                <div x-show="formData.custom_has_hotel && formData.custom_check_in_date" class="flex items-center justify-between">
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
                        <span>Back to Destination &amp; Flight</span>
                    </button>
                    <button type="button" @click="checkStep(6) && applyCustomPackage() && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs hover:bg-navy transition-colors shadow-md cursor-pointer">
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
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Passenger Information Manifest'">Step 5: Passenger Information Manifest</span></h2>
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
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary"
                                           :data-error-key="'passengers.' + idx + '.first_name'" :class="errors['passengers.' + idx + '.first_name'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.first_name'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.first_name'" />
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
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary"
                                           :data-error-key="'passengers.' + idx + '.last_name'" :class="errors['passengers.' + idx + '.last_name'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.last_name'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.last_name'" />
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
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary"
                                           :data-error-key="'passengers.' + idx + '.date_of_birth'" :class="errors['passengers.' + idx + '.date_of_birth'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.date_of_birth'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.date_of_birth'" />
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Gender *</label>
                                    <select :name="'passengers[' + idx + '][gender]'" x-model="p.gender" @change="saveDraft()"
                                            class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs focus:ring-1 focus:ring-primary"
                                            :data-error-key="'passengers.' + idx + '.gender'" :class="errors['passengers.' + idx + '.gender'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.gender'] ? 'true' : null">
                                        <option value="">-- Select --</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <x-ticketing.field-error key="'passengers.' + idx + '.gender'" />
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
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Passport Number<span x-show="passportRequired(p)"> *</span></label>
                                    <input type="text" :name="'passengers[' + idx + '][passport_number]'" x-model="p.passport_number" @input="saveDraft()"
                                           placeholder="e.g. P1234567A"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-mono font-bold focus:ring-1 focus:ring-primary"
                                           :data-error-key="'passengers.' + idx + '.passport_number'" :class="errors['passengers.' + idx + '.passport_number'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.passport_number'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.passport_number'" />
                                </div>
                            </div>

                            <!-- Passport Expiration Date & 6-month Realtime Warning -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                                <div>
                                    <label class="block text-[11px] font-bold text-dark/70 mb-1">Passport Expiration Date<span x-show="passportRequired(p)"> *</span><span x-show="!passportRequired(p)" class="font-normal text-dark/45"> (optional: flies on a government ID)</span></label>
                                    <input type="date" :name="'passengers[' + idx + '][passport_expiry_date]'" x-model="p.passport_expiry_date" @change="checkPassportValidity(p); saveDraft();"
                                           class="w-full px-3 py-2 rounded-xl bg-white border border-gray-200 text-xs font-semibold focus:ring-1 focus:ring-primary"
                                           :data-error-key="'passengers.' + idx + '.passport_expiry_date'" :class="errors['passengers.' + idx + '.passport_expiry_date'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.passport_expiry_date'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.passport_expiry_date'" />
                                </div>

                                <!-- Passport Alert Box: a blank label line keeps it level with the date input -->
                                <div>
                                    <span class="hidden sm:block text-[11px] font-bold mb-1 select-none" aria-hidden="true">&nbsp;</span>
                                    <div x-show="passportTooShort(p)" class="w-full px-2.5 py-2 rounded-xl bg-rose-50 border border-rose-200 flex items-start gap-2">
                                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                                        <div class="text-[11px] font-bold text-rose-800 leading-tight">
                                            Passport must be renewed before travel. (At least 6 months validity required from departure).
                                        </div>
                                    </div>
                                    <div x-show="p.passport_expiry_date && formData.departure_date && !passportTooShort(p)" class="w-full px-2.5 py-2 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-2">
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
                    <button type="button" @click="checkStep(5) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
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
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Passport Validation & Upload'">Step 2: Passport Validation &amp; Upload</span></h2>
                </div>

                <div class="space-y-4">
                    <template x-for="(p, idx) in formData.passengers" :key="idx">
                        <div class="p-5 rounded-2xl border-2 space-y-3 transition-all"
                             :class="passportScanStatus(p).state === 'uploaded' ? 'border-emerald-300 bg-emerald-50/20' : (passportTooShort(p) ? 'border-rose-300 bg-rose-50/20' : 'border-gray-200 bg-white')">
                            
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center" x-text="idx + 1"></span>
                                <span class="font-bold text-xs text-dark" x-text="passengerLabel(p, idx)"></span>
                                <span x-show="p.passport_number" class="text-[11px] text-dark/50" x-text="'Passport ' + p.passport_number"></span>
                            </div>

                            <!-- Real-time Warning Banner if <= 6 months -->
                            <div x-show="passportTooShort(p)" class="p-3 rounded-xl bg-rose-100/70 border border-rose-300 flex items-center gap-2">
                                <i data-lucide="alert-octagon" class="w-4 h-4 text-rose-700 shrink-0"></i>
                                <span class="text-xs font-bold text-rose-900">
                                    Warning: Passport must be renewed before travel. Validity is less than six (6) months from departure.
                                </span>
                            </div>

                            <!-- Passport scan — required for international travel and foreign nationals -->
                            <x-ticketing.scan-panel doc="passport" title="Passport Scan" label="Passport" required-text="Passport document required to continue" />

                            <!-- Government ID — required for Filipino adults on domestic travel -->
                            <div x-show="formData.travel_type === 'domestic' && p.passenger_type === 'adult' && p.nationality_type === 'filipino'"
                                 class="space-y-1.5">
                                <x-ticketing.scan-panel doc="government_id" title="Valid Government ID" label="Government ID" required-text="Government ID required. You can upload it later" />
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
                    <button type="button" @click="checkStep(2) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Destination</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- STEP 10: VISA, CONTACT & EXTRAS (visa, insurance, services and requests were once separate steps) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 10" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': ' + (formData.travel_type === 'international' ? 'Visa, Contact & Extras' : 'Contact & Extras')">Step 5: Visa, Contact &amp; Extras</span></h2>
                </div>

                <!-- Contact -->
                <div class="space-y-4">
                    <h3 class="font-heading font-bold text-sm text-dark">Contact</h3>
                    <div x-show="formData.travel_type === 'international'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Emergency Contact Full Name *</label>
                            <input type="text" name="emergency_contact_name" x-model="formData.emergency_contact_name" @input="saveDraft()"
                                   placeholder="e.g. Maria Santos"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="emergency_contact_name" :class="errors['emergency_contact_name'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['emergency_contact_name'] ? 'true' : null">
                            <x-ticketing.field-error key="'emergency_contact_name'" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Relationship to Passenger *</label>
                            <input type="text" name="emergency_contact_relationship" x-model="formData.emergency_contact_relationship" @input="saveDraft()"
                                   placeholder="e.g. Spouse, Parent, Sibling"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="emergency_contact_relationship" :class="errors['emergency_contact_relationship'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['emergency_contact_relationship'] ? 'true' : null">
                            <x-ticketing.field-error key="'emergency_contact_relationship'" />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-dark/70 mb-1">Mobile / Phone Number *</label>
                            <input type="text" name="emergency_contact_phone" x-model="formData.emergency_contact_phone" @input="saveDraft()"
                                   placeholder="e.g. +63 917 123 4567"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary"
                                   data-error-key="emergency_contact_phone" :class="errors['emergency_contact_phone'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['emergency_contact_phone'] ? 'true' : null">
                            <x-ticketing.field-error key="'emergency_contact_phone'" />
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
                        {{-- A corporate booking: the company it is for (picked with the travellers in Step 1). --}}
                        <div x-show="formData.corporate_account_id" x-cloak class="flex items-center gap-2.5 px-3 py-2 rounded-lg bg-white border border-gray-200">
                            <i data-lucide="building-2" class="w-4 h-4 text-primary shrink-0"></i>
                            <div class="min-w-0">
                                <span class="text-[10px] text-dark/50 block">Company:</span>
                                <span class="text-xs font-bold text-dark block truncate" x-text="formData.corporate_name"></span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <span class="text-[10px] text-dark/50 block">Booker Name:</span>
                                <input type="text" name="contact_name" x-model="formData.contact_name" class="w-full px-2.5 py-1.5 rounded-lg border text-xs font-bold"
                                       data-error-key="contact_name" :class="errors['contact_name'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['contact_name'] ? 'true' : null">
                                <x-ticketing.field-error key="'contact_name'" />
                            </div>
                            <div>
                                <span class="text-[10px] text-dark/50 block">Booker Email:</span>
                                <input type="email" name="contact_email" x-model="formData.contact_email" class="w-full px-2.5 py-1.5 rounded-lg border text-xs"
                                       data-error-key="contact_email" :class="errors['contact_email'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['contact_email'] ? 'true' : null">
                                <x-ticketing.field-error key="'contact_email'" />
                            </div>
                            <div>
                                <span class="text-[10px] text-dark/50 block">Booker Phone:</span>
                                <input type="text" name="contact_phone" x-model="formData.contact_phone" class="w-full px-2.5 py-1.5 rounded-lg border text-xs font-bold"
                                       data-error-key="contact_phone" :class="errors['contact_phone'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['contact_phone'] ? 'true' : null">
                                <x-ticketing.field-error key="'contact_phone'" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visa (international) -->
                <div x-show="formData.travel_type === 'international'" class="space-y-4 pt-4 border-t border-gray-100">
                    <div>
                        <h3 class="font-heading font-bold text-sm text-dark">Visa</h3>
                        <p class="text-xs text-dark/50">Whether each passenger needs a visa, with the documents that go with it.</p>
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
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <label class="flex items-center gap-2.5 p-3 rounded-xl border cursor-pointer transition-all"
                                           :class="p.visa_status === 'already_has_visa' ? 'border-primary bg-primary/5 font-bold text-primary' : 'border-gray-200 bg-white text-dark/70'">
                                        <input type="radio" :name="'passengers[' + idx + '][visa_status]'" value="already_has_visa" x-model="p.visa_status" @change="saveDraft()" class="sr-only">
                                        <i data-lucide="file-check-2" class="w-4 h-4"></i>
                                        <span class="text-xs">Already Has Visa</span>
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
                                    <label class="block text-xs font-bold text-dark">Upload Visa Copy <span class="font-normal text-dark/40">(can be added later)</span></label>
                                    <input type="file" :name="'passengers[' + idx + '][visa_file]'" accept="image/jpeg,image/png,image/webp,image/jpg,application/pdf"
                                           @change="onFileChange($event, p, 'visa_file_name')"
                                           class="w-full text-xs text-dark/70 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-primary file:text-white hover:file:bg-navy cursor-pointer"
                                           :data-error-key="'passengers.' + idx + '.visa_file'" :class="errors['passengers.' + idx + '.visa_file'] ? '!border-rose-400 !bg-rose-50/60' : ''" :aria-invalid="errors['passengers.' + idx + '.visa_file'] ? 'true' : null">
                                    <x-ticketing.field-error key="'passengers.' + idx + '.visa_file'" />
                                    <div x-show="p.visa_file_name" class="text-[11px] font-bold text-emerald-700" x-text="'Attached: ' + p.visa_file_name"></div>
                                </div>

                            </div>
                        </template>
                    </div>
                </div>

                <!-- Optional extras (international and domestic): collapsed unless something is already chosen -->
                <div class="space-y-3 pt-4 border-t border-gray-100">
                    <div>
                        <h3 class="font-heading font-bold text-sm text-dark">Optional Extras</h3>
                        <p class="text-xs text-dark/50">Skip these unless the client asked for them.</p>
                    </div>
                    <details x-show="insurancePlans.length" class="group rounded-2xl border border-gray-200 bg-white" x-init="$el.open = formData.has_insurance">
                        <summary class="flex items-center justify-between gap-3 p-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center gap-3 min-w-0">
                                <i data-lucide="shield-check" class="w-4 h-4 text-primary shrink-0"></i>
                                <span class="min-w-0">
                                    <span class="font-heading font-bold text-sm text-dark block">Travel Insurance</span>
                                    <span class="text-[11px] text-dark/50 block truncate" x-text="formData.has_insurance ? (insurancePlanName(formData.insurance_plan) || 'Included') : 'Not included'"></span>
                                </span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 shrink-0 transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="px-4 pb-4 space-y-4">
                            <!-- Insurance Checkbox -->
                            <label class="flex items-center gap-3 p-5 rounded-2xl border-2 cursor-pointer transition-all"
                                   :class="formData.has_insurance ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 bg-white'">
                                <input type="checkbox" name="has_insurance" value="1" x-model="formData.has_insurance" @change="applyInsurancePlan(); saveDraft()" class="w-5 h-5 rounded text-primary focus:ring-primary">
                                <div>
                                    <span class="font-heading font-bold text-sm text-dark block">Include Travel Insurance Protection</span>
                                    <span class="text-xs text-dark/60" x-text="formData.travel_type === 'international' ? 'Covers international emergency medical expenses, luggage delay, and flight cancellations.' : 'Covers emergency medical expenses, luggage delay, and flight cancellations.'">Covers emergency medical expenses, luggage delay, and flight cancellations.</span>
                                </div>
                            </label>

                            <!-- Insurance Plans Selection if enabled -->
                            <div x-show="formData.has_insurance" class="space-y-4 pt-2">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">Select Insurance Coverage Plan:</span>
                                    @if (auth()->user()->can('prices.manage'))
                                        <a href="{{ route('ticketing.insurance-plans.index') }}" target="_blank" rel="noopener" class="shrink-0 text-xs font-bold text-primary hover:underline">Manage plans</a>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <template x-for="plan in insurancePlans" :key="plan.key">
                                        <label class="p-5 rounded-2xl border-2 cursor-pointer transition-all flex flex-col justify-between relative"
                                               :class="formData.insurance_plan === plan.key ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-gray-200 bg-white'">
                                            <input type="radio" name="insurance_plan" :value="plan.key" x-model="formData.insurance_plan" @change="applyInsurancePlan(); saveDraft()" class="sr-only">
                                            <span x-show="plan.is_popular" class="absolute -top-2.5 right-4 bg-accent text-dark text-[9px] font-bold px-2 py-0.5 rounded-full uppercase">Most Popular</span>
                                            <div>
                                                <div class="font-heading font-bold text-sm text-dark" x-text="plan.name"></div>
                                                <div class="text-primary font-bold text-xs mt-0.5" x-text="'₱' + formatNumber(plan.price_per_pax) + ' / pax'"></div>
                                                <ul class="text-[11px] text-dark/60 space-y-1 mt-3">
                                                    <template x-for="line in (plan.coverage || [])" :key="line">
                                                        <li x-text="'• ' + line"></li>
                                                    </template>
                                                </ul>
                                            </div>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </details>
                    <details class="group rounded-2xl border border-gray-200 bg-white" x-init="$el.open = formData.selected_services.length > 0">
                        <summary class="flex items-center justify-between gap-3 p-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center gap-3 min-w-0">
                                <i data-lucide="sparkles" class="w-4 h-4 text-primary shrink-0"></i>
                                <span class="min-w-0">
                                    <span class="font-heading font-bold text-sm text-dark block">Concierge Services</span>
                                    <span class="text-[11px] text-dark/50 block truncate" x-text="formData.selected_services.length ? formData.selected_services.length + ' selected' : 'None selected'"></span>
                                </span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 shrink-0 transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="px-4 pb-4 space-y-4">
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

                            <x-ticketing.extra-pricing source="formData.selected_services" options="availableServices" heading="Pricing for the selected services" />
                        </div>
                    </details>
                    <details class="group rounded-2xl border border-gray-200 bg-white" x-init="$el.open = (formData.special_requests_list.length > 0 || !!formData.special_requests)">
                        <summary class="flex items-center justify-between gap-3 p-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                            <span class="flex items-center gap-3 min-w-0">
                                <i data-lucide="accessibility" class="w-4 h-4 text-primary shrink-0"></i>
                                <span class="min-w-0">
                                    <span class="font-heading font-bold text-sm text-dark block">Special Requests &amp; Seating</span>
                                    <span class="text-[11px] text-dark/50 block truncate" x-text="[formData.special_requests_list.length ? formData.special_requests_list.length + ' selected' : null, formData.special_requests ? 'notes added' : null].filter(Boolean).join(', ') || 'None'"></span>
                                </span>
                            </span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-dark/40 shrink-0 transition-transform group-open:rotate-180"></i>
                        </summary>
                        <div class="px-4 pb-4 space-y-4">
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

                            <x-ticketing.extra-pricing source="formData.special_requests_list" options="specialRequestOptions" heading="Pricing for the selected requests" />

                            <div>
                                <label class="block text-xs font-bold text-dark/70 mb-1">Additional Instructions &amp; Seating Notes</label>
                                <textarea name="special_requests" x-model="formData.special_requests" rows="3" @input="saveDraft()"
                                          placeholder="Any specific seat rows, baggage weights, connecting airline assistance, or wheelchair escort details..."
                                          class="w-full px-3.5 py-2.5 rounded-xl bg-gray-50 border border-gray-200 text-xs focus:ring-2 focus:ring-primary"></textarea>
                            </div>
                        </div>
                    </details>
                </div>

                <!-- Navigation -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-gray-200 text-dark/70 font-bold text-xs hover:bg-gray-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Back</span>
                    </button>
                    <button type="button" @click="checkStep(10) && nextStep()" class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl bg-primary text-white font-heading font-bold text-xs uppercase tracking-wider hover:bg-navy transition-all shadow-md">
                        <span>Continue to Review</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- DOCUMENT VERIFICATION (folded into the Review step) -->
        <!-- ========================================================================= -->
        <div x-show="currentStep === 12" class="space-y-6" style="display: none;">
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark">Document Verification</h2>
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
                                                  :class="p.visa_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                  x-text="p.visa_file_name ? 'Uploaded' : 'Upload later'"></span>
                                        </template>
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 uppercase">Assistance Req.</span>
                                        </template>
                                    </td>

                                    <!-- Passport Photo 2x2 -->
                                    <td class="p-3 text-center">
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                  :class="p.passport_photo_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                  x-text="p.passport_photo_file_name ? 'Uploaded' : 'Upload later'"></span>
                                        </template>
                                        <template x-if="p.visa_status !== 'needs_assistance'">
                                            <span class="text-dark/30">—</span>
                                        </template>
                                    </td>

                                    <!-- Supporting Docs -->
                                    <td class="p-3 text-center">
                                        <template x-if="p.visa_status === 'needs_assistance'">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                                  :class="p.supporting_doc_file_name ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                  x-text="p.supporting_doc_file_name ? 'Uploaded' : 'Upload later'"></span>
                                        </template>
                                        <template x-if="p.visa_status !== 'needs_assistance'">
                                            <span class="text-dark/30">—</span>
                                        </template>
                                    </td>

                                    <!-- Insurance Policy -->
                                    <td class="p-3 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase"
                                              :class="formData.has_insurance ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-dark/40'"
                                              x-text="formData.has_insurance ? ('Covered (' + (insurancePlanName(formData.insurance_plan) || 'plan') + ')') : 'None'"></span>
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
            <div class="bg-white rounded-3xl p-6 sm:p-10 border border-gray-200 shadow-card space-y-6">
                <div class="border-b border-gray-100 pb-4">
                    <h2 class="text-lg font-heading font-bold text-dark"><span x-text="'Step ' + (stepIndex + 1) + ': Review, Verification & Quotation'">Step 12: Review &amp; Quotation Review</span></h2>
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
                            <div x-show="!orderedLegs.length" class="text-sm font-heading font-bold text-white flex items-center gap-2 mt-0.5">
                                <span x-text="formData.origin"></span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-accent"></i>
                                <span x-text="formData.destination || (formData.destination_city + ', ' + formData.destination_country)"></span>
                            </div>
                            <ol x-show="orderedLegs.length" class="mt-0.5 space-y-0.5">
                                <template x-for="(leg, i) in orderedLegs" :key="i">
                                    <li class="text-sm font-heading font-bold text-white" x-text="(i + 1) + '. ' + (leg.from || '?') + ' → ' + (leg.to || '?')"></li>
                                </template>
                            </ol>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider">Dates</div>
                            <div x-show="!orderedLegs.length" class="text-xs font-bold text-white mt-0.5">
                                Dep: <span x-text="formData.departure_date"></span>
                                <span x-show="formData.trip_type === 'round_trip'" x-text="' | Ret: ' + formData.return_date"></span>
                            </div>
                            <ol x-show="orderedLegs.length" class="mt-0.5 space-y-0.5">
                                <template x-for="(leg, i) in orderedLegs" :key="i">
                                    <li class="text-xs font-bold text-white" x-text="(i + 1) + '. ' + (leg.date || 'Date to be set')"></li>
                                </template>
                            </ol>
                        </div>

                        <div>
                            <div class="text-[10px] font-bold text-white/50 uppercase tracking-wider">Emergency Contact</div>
                            <div class="text-xs font-bold text-white mt-0.5 truncate" x-text="formData.emergency_contact_name || 'N/A'"></div>
                            <div class="text-[10px] text-white/60" x-text="formData.emergency_contact_phone"></div>
                        </div>
                    </div>
                </div>

                <!-- Booked Flight (from the fare search in Destination & Flight) -->
                <div x-show="selectedAirline || formData.flight_number" class="p-5 rounded-2xl bg-gray-50 border border-gray-200 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Airline</span>
                        <span class="text-xs font-bold text-dark" x-text="selectedAirline ? selectedAirline.name : 'Not chosen'"></span>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Departing</span>
                        <span class="text-xs font-bold text-dark font-mono uppercase" x-text="formData.flight_number || '—'"></span>
                        <span x-show="formData.departure_time" class="text-[11px] text-dark/60 block" x-text="formData.departure_time + (formData.arrival_time ? ' → ' + formData.arrival_time : '')"></span>
                    </div>
                    <div x-show="formData.trip_type === 'round_trip'">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Returning</span>
                        <span class="text-xs font-bold text-dark font-mono uppercase" x-text="formData.return_flight_number || '—'"></span>
                        <span x-show="formData.return_departure_time" class="text-[11px] text-dark/60 block" x-text="formData.return_departure_time + (formData.return_arrival_time ? ' → ' + formData.return_arrival_time : '')"></span>
                    </div>
                </div>

                <!-- Airline Restrictions -->
                <div x-show="formData.airline_restrictions.some(r => r && r.trim())" class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Airline Restrictions</span>
                        <button type="button" @click="goToStep(13)" class="text-[11px] font-bold text-primary hover:underline">Edit</button>
                    </div>
                    <ul class="space-y-1">
                        <template x-for="(restriction, idx) in formData.airline_restrictions.filter(r => r && r.trim())" :key="idx">
                            <li class="text-xs text-dark/80 flex items-start gap-2"><span class="text-amber-600">•</span><span x-text="restriction"></span></li>
                        </template>
                    </ul>
                </div>

                <!-- Package Summary (Ready-made or Custom) -->
                <!-- Ready-Made Tour Package Card -->
                <div x-show="formData.package_type === 'with_package' && selectedPackage && !isCustomPackage" x-data="{ open: false }" class="rounded-2xl bg-amber-50/70 border border-amber-200">
                  <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
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
                    <div class="flex items-center gap-4 sm:justify-end">
                        <div class="sm:text-right" x-show="selectedPackage && selectedPackage.price">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/60 block">Package Price</span>
                            <span class="text-sm font-mono font-bold text-primary" x-text="selectedPackage ? selectedPackage.price : ''"></span>
                        </div>
                        <button type="button" @click="open = !open" :aria-expanded="open" aria-controls="review-package-details"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white border border-amber-200 text-xs font-bold text-amber-900 hover:bg-amber-100 transition-colors">
                            <span x-text="open ? 'Hide details' : 'Details'">Details</span>
                            <i class="fa-solid fa-chevron-down text-[10px] transition-transform" :class="open ? 'rotate-180' : ''" aria-hidden="true"></i>
                        </button>
                    </div>
                  </div>

                  {{-- Everything the package carries; fields it leaves empty are not listed. --}}
                  <div id="review-package-details" x-show="open" x-cloak class="px-5 pb-5 pt-4 border-t border-amber-200/70 space-y-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-2.5">
                        <template x-for="[label, value] in packageDetails(selectedPackage)" :key="label">
                            <div class="min-w-0">
                                <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70" x-text="label"></dt>
                                <dd class="text-xs font-semibold text-amber-950 break-words" x-text="value"></dd>
                            </div>
                        </template>
                    </dl>
                    <template x-for="[label, value] in packageNotes(selectedPackage)" :key="label">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70" x-text="label"></p>
                            <p class="text-xs text-amber-950 whitespace-pre-line" x-text="value"></p>
                        </div>
                    </template>
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
                                <h4 class="font-heading font-bold text-sm text-amber-950" x-text="formData.custom_has_hotel && (formData.custom_hotel_name || formData.custom_preferred_hotel) ? ('Custom Hotel: ' + (formData.custom_hotel_name || formData.custom_preferred_hotel)) : 'Customized Itinerary & Logistics'"></h4>
                            </div>
                        </div>
                        <div class="sm:text-right" x-show="formData.custom_estimated_budget">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70 block">Target Budget</span>
                            <span class="font-mono text-sm font-bold text-primary">₱<span x-text="formatNumber(formData.custom_estimated_budget)"></span></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div x-show="!formData.custom_has_hotel" class="col-span-2 sm:col-span-3 p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel</span>
                            <span class="font-bold text-dark/50 block">No hotel in this package</span>
                        </div>
                        <div x-show="formData.custom_has_hotel" class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel / Location</span>
                            <span class="font-bold text-dark block truncate" x-text="formData.custom_hotel_name || 'Standard / Arranged'"></span>
                            <span class="text-[10px] text-dark/60 block truncate" x-text="formData.custom_preferred_hotel || 'Any Preferred Area'"></span>
                        </div>

                        <div x-show="formData.custom_has_hotel" class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Bedding &amp; Breakfast</span>
                            <span class="font-semibold text-dark block" x-text="formData.custom_bed_config || 'Standard Double'"></span>
                            <span class="text-[10px] font-bold" :class="formData.custom_has_breakfast ? 'text-emerald-700' : 'text-dark/40'"
                                  x-text="formData.custom_has_breakfast ? '✓ Breakfast Included' : 'No Breakfast'"></span>
                        </div>

                        <div x-show="formData.custom_has_hotel" class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Stay Dates</span>
                            <span class="font-semibold text-dark block text-[11px]">
                                <span x-text="formData.custom_check_in_date || 'TBD'"></span>
                                <span x-show="formData.custom_check_out_date" x-text="' → ' + formData.custom_check_out_date"></span>
                            </span>
                            <span class="text-[10px] text-dark/50 block" x-text="formData.total_passengers + ' Pax Accommodated'"></span>
                        </div>

                        <div class="p-3 rounded-xl bg-white border border-amber-100">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Policies &amp; Logistics</span>
                            <span x-show="formData.custom_has_hotel" class="text-[11px] font-medium text-dark block">
                                <span x-text="formData.custom_smoking_preference === 'smoking' ? '🚬 Smoking' : '🚭 Non-Smoking'"></span> •
                                <span x-text="formData.custom_pet_friendly ? '🐾 Pets OK' : 'No Pets'"></span>
                            </span>
                            <span class="text-[10px] font-bold block mt-0.5" :class="formData.custom_has_transportation ? 'text-primary' : 'text-dark/40'"
                                  x-text="formData.custom_has_transportation ? ('🚗 Transfer: ' + (formData.custom_transportation_type || 'Private')) : 'No Ground Transfer'"></span>
                        </div>
                    </div>

                    <div x-show="formData.custom_has_special_requests && formData.custom_special_requests" class="p-3 rounded-xl bg-white border border-amber-100 text-xs">
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

                    <!-- Fare per passenger type: the agent types the price for one person, the subtotal is worked out -->
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                            <span class="text-xs font-bold text-dark/70 uppercase tracking-wider">Fare per passenger</span>
                            <span class="text-[11px] text-dark/50">The rows follow the travellers. Enter the price for one person or the total for all of them; the other is worked out for you.</span>
                        </div>
                        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                            <table class="w-full text-xs text-left">
                                <thead class="bg-gray-100 text-dark/60 uppercase text-[10px] font-bold">
                                    <tr>
                                        <th class="p-3">Passenger type</th>
                                        <th class="p-3 w-28">Passengers</th>
                                        <th class="p-3 w-44">Price each (₱)</th>
                                        <th class="p-3 w-44">Total price (₱)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="type in fareTypes()" :key="type">
                                        <tr>
                                            <td class="p-3 font-bold text-dark" x-text="fareTypeLabel(type)"></td>
                                            <td class="p-3"><span class="font-mono font-bold text-dark" x-text="fareQty(type)"></span></td>
                                            <td class="p-3">
                                                <input type="number" step="0.01" min="0" :name="'fare_prices[' + type + ']'" placeholder="0.00"
                                                       :value="(formData.fare_prices || {})[type]" @input="setFarePrice(type, $event.target.value)"
                                                       :aria-label="'Price each, ' + fareTypeLabel(type)"
                                                       class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                                <button type="button" x-show="packageAirfare() > 0 && PACKAGE_AIRFARE_TYPES.includes(type) && !(formData.fare_auto || {})[type]"
                                                        @click="usePackageAirfare(type)" class="mt-1 text-[10px] font-bold text-primary hover:underline">Use package airfare</button>
                                            </td>
                                            <td class="p-3">
                                                {{-- Or the price for all of them: the price each is worked out from it, and this total is kept exactly. --}}
                                                <input type="number" step="0.01" min="0" :name="'fare_totals[' + type + ']'" placeholder="0.00"
                                                       :value="fareTotalInput(type)" @input="setFareTotal(type, $event.target.value)"
                                                       :aria-label="'Total price, ' + fareTypeLabel(type)"
                                                       class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold text-right focus:outline-none focus:ring-2 focus:ring-primary">
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="!fareTypes().length">
                                        <td colspan="4" class="p-3 text-center text-[11px] text-dark/50">Add the travellers in Step 1; a row appears for each passenger type.</td>
                                    </tr>

                                    <!-- The ready-made package: price per person and for everyone, counted in the total -->
                                    <tr x-show="hasReadyMadePackage()" class="bg-slate-50/60" data-error-key="package_amount">
                                        <td class="p-3 font-bold text-dark">
                                            <label for="package_amount" x-text="'Tour Package – ' + (selectedPackage?.title || '')"></label>
                                            <span x-show="packageAirfare() > 0" class="block text-[10px] font-normal text-dark/50"
                                                  x-text="'Hotel, tours & transfers (airfare ₱' + formatNumber(packageAirfare()) + ' per person is in the fare rows)'"></span>
                                            <span x-show="selectedPackage && (selectedPackage.price_currency || 'PHP') !== 'PHP'" class="block text-[10px] font-semibold text-amber-700">
                                                Priced in <span x-text="selectedPackage?.price_currency"></span>: enter the amount in pesos.
                                            </span>
                                            <button type="button" x-show="!formData.package_amount_auto && (selectedPackage?.price_currency || 'PHP') === 'PHP'"
                                                    @click="formData.package_amount_auto = true; calculateGrandTotal(); saveDraft()"
                                                    class="block text-[10px] font-bold text-primary hover:underline">Use package price</button>
                                        </td>
                                        <td class="p-3"><span class="font-mono font-bold text-dark" x-text="totalPax()"></span> <span class="text-[11px] text-dark/50">pax</span></td>
                                        <td class="p-3">
                                            <input type="number" step="0.01" min="0" placeholder="0.00" aria-label="Package price each"
                                                   :value="packagePriceEach()" @input="setPackagePriceEach($event.target.value)"
                                                   class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                        </td>
                                        <td class="p-3">
                                            <input type="number" step="0.01" min="0" id="package_amount" name="package_amount" x-model.number="formData.package_amount" placeholder="0.00"
                                                   @input="formData.package_amount_auto = false; calculateGrandTotal(); saveDraft()" aria-label="Package total price"
                                                   class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold text-right focus:outline-none focus:ring-2 focus:ring-primary">
                                            <x-ticketing.field-error key="'package_amount'" />
                                        </td>
                                    </tr>

                                    <!-- The Amega Service Fee, for the booking as a whole -->
                                    <tr class="bg-slate-50/60">
                                        <td class="p-3 font-bold text-dark"><label for="service_fee">Amega Service Fee</label></td>
                                        <td class="p-3 text-[11px] text-dark/50">Per booking</td>
                                        <td class="p-3">
                                            <input type="number" step="0.01" min="0" id="service_fee" name="service_fee" x-model.number="formData.service_fee" @input="calculateGrandTotal(); saveDraft()"
                                                   placeholder="0.00" class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold text-dark" x-text="formatNumber(parseFloat(formData.service_fee) || 0)"></td>
                                    </tr>

                                    <!-- Corporate bookings: a service tax of 1 to 10% on the Amega Service Fee -->
                                    <tr x-show="formData.corporate_account_id" x-cloak class="bg-slate-50/60">
                                        <td class="p-3 font-bold text-dark">
                                            <label for="service_tax_rate">Service Tax</label>
                                            <span class="block text-[10px] font-normal text-dark/50">Corporate · of the Amega Service Fee</span>
                                        </td>
                                        <td class="p-3 text-[11px] text-dark/50">1 – 10%</td>
                                        <td class="p-3">
                                            <div class="relative">
                                                <input type="number" step="0.01" min="1" max="10" id="service_tax_rate" name="service_tax_rate" x-model.number="formData.service_tax_rate"
                                                       :disabled="!formData.corporate_account_id" @input="calculateGrandTotal(); saveDraft()"
                                                       placeholder="0" class="w-full pl-3 pr-8 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-dark/40">%</span>
                                            </div>
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold text-dark" x-text="formatNumber(serviceTax())"></td>
                                    </tr>
                                    <!-- PWD / Senior Citizen: a discount on some of the passengers, never a passenger type -->
                                    <tr class="bg-slate-50/60" data-error-key="pwd_sc_count">
                                        <td class="p-3 font-bold text-dark">
                                            <label for="pwd_sc_count" x-text="pwdLabel() + ' Discount'"></label>
                                            <span class="block text-[10px] font-normal text-dark/50" x-text="formData.pwd_sc_mode === 'amount' ? 'Count × peso amount each' : 'Count × (fare + package per person) × rate'"></span>
                                            <p id="pwd_sc_notice" x-show="pwdNotice" x-cloak class="text-[10px] font-semibold text-rose-600" role="alert"
                                               x-text="'Cannot exceed total passengers (' + totalPax() + ').'"></p>
                                        </td>
                                        <td class="p-3">
                                            <input type="number" min="0" step="1" id="pwd_sc_count" name="pwd_sc_count" :max="totalPax()"
                                                   :value="formData.pwd_sc_count" @input="setPwdCount($event.target.value)" @blur="$event.target.value = formData.pwd_sc_count"
                                                   :aria-invalid="pwdNotice ? 'true' : null" aria-describedby="pwd_sc_notice" aria-label="Number of PWD / senior citizen passengers"
                                                   class="w-20 px-2.5 py-1.5 rounded-lg bg-white border text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary"
                                                   :class="pwdNotice ? 'border-rose-400' : 'border-gray-200'">
                                        </td>
                                        <td class="p-3">
                                            {{-- A rate of the price per person, or a peso amount off each PWD / SC passenger. --}}
                                            <input type="hidden" name="pwd_sc_mode" :value="formData.pwd_sc_mode">
                                            <div class="flex items-center gap-1.5">
                                                <div class="inline-flex shrink-0 rounded-lg border border-gray-200 bg-gray-50 p-0.5" role="group" aria-label="Discount as a percentage or a peso amount">
                                                    <button type="button" @click="setPwdMode('percent')" :aria-pressed="formData.pwd_sc_mode !== 'amount'"
                                                            class="px-2 py-1 rounded-md text-[11px] font-bold" :class="formData.pwd_sc_mode !== 'amount' ? 'bg-primary text-white' : 'text-dark/60'">%</button>
                                                    <button type="button" @click="setPwdMode('amount')" :aria-pressed="formData.pwd_sc_mode === 'amount'"
                                                            class="px-2 py-1 rounded-md text-[11px] font-bold" :class="formData.pwd_sc_mode === 'amount' ? 'bg-primary text-white' : 'text-dark/60'">₱</button>
                                                </div>
                                                <div class="relative flex-1 min-w-0" x-show="formData.pwd_sc_mode !== 'amount'">
                                                    <input type="number" min="0" max="100" step="0.01" id="pwd_sc_rate" name="pwd_sc_rate" aria-label="Discount rate (%)"
                                                           :value="formData.pwd_sc_rate" @input="setPwdRate($event.target.value)"
                                                           class="w-full pl-3 pr-8 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-dark/40">%</span>
                                                </div>
                                                <div class="relative flex-1 min-w-0" x-show="formData.pwd_sc_mode === 'amount'" x-cloak>
                                                    <input type="number" min="0" step="0.01" id="pwd_sc_amount" name="pwd_sc_amount" placeholder="0.00" aria-label="Discount per PWD / SC passenger (₱)"
                                                           :value="formData.pwd_sc_amount" @input="setPwdAmount($event.target.value)"
                                                           class="w-full px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-xs font-mono font-bold focus:outline-none focus:ring-2 focus:ring-primary">
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 text-right font-mono font-bold" :class="pwdDiscount() > 0 ? 'text-emerald-700' : 'text-dark'"
                                            x-text="(pwdDiscount() > 0 ? '-' : '') + formatNumber(pwdDiscount())"></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-gray-50">
                                        <td colspan="3" class="p-3 font-bold uppercase text-[10px] text-dark/70">Subtotal (fare, <span x-show="hasReadyMadePackage()">package, </span>Amega fee<span x-show="formData.corporate_account_id"> &amp; service tax</span><span x-show="pwdDiscount() > 0">, less the discount</span>)</td>
                                        <td class="p-3 text-right font-mono font-black text-dark" x-text="formatNumber(fareTotal() + (hasReadyMadePackage() ? (parseFloat(formData.package_amount) || 0) : 0) + (parseFloat(formData.service_fee) || 0) + serviceTax() - pwdDiscount())"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <p x-show="packageAirfare() > 0" class="text-[11px] font-semibold text-emerald-700"
                           x-text="'Airfare from the package: ₱' + formatNumber(packageAirfare()) + ' per person, filled in for adults and children. Change it if the fare differs; infants are priced by hand.'"></p>
                        <p x-show="formData.package_type === 'with_package' && selectedPackage?.airfare_inclusion === 'included' && packageAirfare() <= 0" class="text-[11px] font-semibold text-emerald-700">
                            Airfare is included in the package price; enter a fare here only if it is charged on top.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                        {{-- The fare is the subtotal of the fare per passenger above; nothing fills it in. --}}
                        <input type="hidden" name="estimated_fare" :value="fareTotal()">

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Taxes / Fees</label>
                            <input type="number" step="0.01" name="taxes_amount" x-model.number="formData.taxes_amount" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Visa Assist Fee</label>
                            <input type="number" step="0.01" name="visa_assistance_fee" x-model.number="formData.visa_assistance_fee" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Insurance Fee</label>
                            <input type="number" step="0.01" name="insurance_fee" x-model.number="formData.insurance_fee" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Other Charges</label>
                            <input type="number" step="0.01" name="other_charges" x-model.number="formData.other_charges" @input="calculateGrandTotal()"
                                   placeholder="0.00" class="w-full px-3 py-2 rounded-xl bg-white border text-xs font-mono font-bold">
                        </div>


                        <div>
                            <label class="block text-[11px] font-bold text-dark/70 mb-1">Services &amp; Requests</label>
                            {{-- No name: worked out from the Paid choices in Contact &amp; Extras, and again on the server. --}}
                            <div class="w-full px-3 py-2 rounded-xl bg-gray-100 border border-gray-200 text-xs font-mono font-bold text-dark/70" x-text="formatNumber(formData.extras_amount)"></div>
                        </div>
                    </div>

                    <!-- Every service and request picked, free or charged -->
                    <div x-show="selectedExtras().length" class="space-y-2">
                        <span class="text-xs font-bold text-dark/70 uppercase tracking-wider block">Services &amp; requests</span>
                        <div class="rounded-xl border border-gray-200 bg-white divide-y divide-gray-100">
                            <template x-for="item in selectedExtras()" :key="item.key">
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs">
                                    <span class="font-bold text-dark" x-text="item.label"></span>
                                    <span class="font-mono font-bold" :class="item.paid ? 'text-dark' : 'text-emerald-700'"
                                          x-text="item.paid ? '₱' + formatNumber(item.price) : 'Free'"></span>
                                </div>
                            </template>
                            <div class="flex items-center justify-between gap-3 px-3 py-2 text-xs bg-gray-50">
                                <span class="font-bold uppercase text-[10px] text-dark/70">Services &amp; requests subtotal</span>
                                <span class="font-mono font-black text-dark" x-text="'₱' + formatNumber(formData.extras_amount)"></span>
                            </div>
                        </div>
                        <p class="text-[11px] text-dark/50">Change what is free or charged under Contact &amp; Extras.</p>
                    </div>

                    <!-- The total: everything above, less the PWD / SC discount -->
                    <div class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-white border border-gray-200">
                        <span class="text-xs font-bold uppercase tracking-wider text-dark/70">
                            Total Amount
                            <span x-show="pwdDiscount() > 0" class="block text-[10px] font-semibold normal-case tracking-normal text-emerald-700"
                                  x-text="'After the ' + pwdLabel() + ' discount of ₱' + formatNumber(pwdDiscount())"></span>
                        </span>
                        <span class="font-mono text-lg font-black text-dark">₱<span x-text="formatNumber(formData.total_amount)"></span></span>
                        <input type="hidden" name="total_amount" :value="formData.total_amount">
                    </div>
                </div>

                <!-- Agent acknowledgement: required before the ticket is issued -->
                <label data-error-key="agent_acknowledged" tabindex="-1"
                       class="flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition-colors focus:outline-none"
                       :class="errors['agent_acknowledged'] ? 'border-rose-400 bg-rose-50/60' : (acknowledged ? 'border-primary bg-primary/5' : 'border-gray-200 bg-gray-50 hover:bg-gray-100/70')">
                    <input type="checkbox" name="agent_acknowledged" value="1" x-model="acknowledged" @change="refreshErrors()"
                           class="mt-0.5 w-4 h-4 rounded text-primary focus:ring-primary" :aria-invalid="errors['agent_acknowledged'] ? 'true' : null">
                    <span class="text-xs text-dark leading-relaxed">
                        I acknowledge that I have reviewed all the information in this booking and that it is correct.
                        I, <strong>{{ auth()->user()->name }}</strong>, will be held accountable for any incorrect information.
                    </span>
                </label>
                <x-ticketing.field-error key="'agent_acknowledged'" />

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
                    <button type="submit" :disabled="isSubmitting || !acknowledged" :title="acknowledged ? '' : 'Tick the acknowledgement above first'"
                            class="inline-flex items-center gap-2 px-8 py-3.5 rounded-2xl bg-accent text-dark font-heading font-black text-xs sm:text-sm uppercase tracking-wider hover:bg-accent-dark shadow-xl shadow-accent/25 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer">
                        <span x-show="!isSubmitting" class="inline-flex items-center gap-2">
                            <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                            <span>Submit for Approval</span>
                        </span>
                        <span x-show="isSubmitting" class="inline-flex items-center gap-2" style="display: none;">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-dark" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Submitting for approval...</span>
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
    // The last file each passport / government ID input accepted, so a rejected or cancelled pick can put it back.
    const keptScanFiles = new WeakMap();
    const SCAN_LABELS = { passport: 'passport', government_id: 'government ID' };
    // Which pending ticket the form in this browser belongs to, so saving again updates it.
    const PENDING_KEY = 'amega_ticket_pending_id';
    // PWD / Senior Citizen discount: what it is taken from per person, and the rate it starts at
    // (the same as TicketBooking::PWD_SC_DISCOUNTABLE and PWD_SC_DEFAULT_RATE on the server).
    const PWD_SC_DISCOUNTABLE = @js(\App\Models\TicketBooking::PWD_SC_DISCOUNTABLE);
    const PWD_SC_DEFAULT_RATE = @js(\App\Models\TicketBooking::PWD_SC_DEFAULT_RATE);
    // The fare rows a package's included airfare fills in (infants are priced by hand).
    const PACKAGE_AIRFARE_TYPES = ['adult', 'child'];
    // The step the form was on, so a page refresh returns to it instead of step 1.
    const STEP_KEY = 'amega_ticket_booking_step';

    return {
        currentStep: 1,
        // The PWD / SC count was more than the passengers and was brought down (the message under it).
        pwdNotice: false,
        // The agent's acknowledgement on the review step; never saved with a draft.
        acknowledged: false,
        destinations: config.destinations || [],
        domesticDestinations: config.domesticDestinations || [],
        internationalDestinations: config.internationalDestinations || [],
        packages: config.packages || [],
        airlines: config.airlines || [],

        // Problems found by the last step check, keyed by field (see errorsForStep).
        errors: {},
        errorStep: null,
        errorCheck: null,
        // The plans the admin offers (Admin > Contents > Travel Insurance).
        insurancePlans: config.insurancePlans || [],
        draftSaved: false,
        hasDraft: false,
        isSubmitting: false,
        quotationMode: false,
        pendingId: null,
        pendingSaving: false,
        pendingSavedAt: '',
        uploadProblem: '',

        // Client picker (top of Step 1)
        clientSearchUrl: config.clientSearchUrl,
        clientRegisterUrl: config.clientRegisterUrl,
        companyMembersUrl: config.companyMembersUrl,
        // Corporate accounts found by the search, and the one whose travellers are being picked.
        companyResults: [],
        company: null,
        scanUploadUrls: { passport: config.passportUploadUrl, government_id: config.governmentIdUploadUrl },
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
            { key: 'sim_card', label: 'SIM Card', desc: 'Local high-speed data connectivity', icon: 'smartphone' },
            { key: 'pocket_wifi', label: 'Pocket WiFi', desc: 'Unlimited shared portable router', icon: 'wifi' },
            { key: 'forex_assistance', label: 'Forex Assistance', desc: 'Currency exchange support', icon: 'banknote' },
            { key: 'meet_and_greet', label: 'Meet & Greet', desc: 'VIP airport assistance & escort', icon: 'smile' },
            { key: 'e_travel', label: 'E-Travel', desc: 'Online travel registration assistance', icon: 'qr-code' },
            { key: 'arrival_card', label: 'Arrival Card', desc: 'Arrival card filled in before you land', icon: 'file-text' },
            { key: 'flight_delays', label: 'Flight Delays', desc: 'Delay assistance & rebooking support', icon: 'timer' },
        ],

        // Common fare rules, added to the booking with one click and then editable.
        restrictionPresets: [
            'Non-refundable',
            'No name changes allowed',
            'Date change fee applies',
            'Hand-carry only (7 kg), no checked baggage',
            'Checked baggage must be purchased separately',
            'Name must match the passport / valid ID exactly',
            'No-show forfeits the whole ticket',
            'Unaccompanied minors not accepted',
            'Pregnant passengers need a medical certificate',
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
            // The flight found on the airline's site (fare search)
            airline_id: '',
            flight_number: '',
            departure_time: '',
            arrival_time: '',
            return_flight_number: '',
            return_departure_time: '',
            return_arrival_time: '',
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
            insurance_plan: ((config.insurancePlans || []).find(plan => plan.is_popular) || (config.insurancePlans || [])[0] || {}).key || '',
            selected_services: [],
            special_requests_list: [],
            // Free or paid, per selected service / request: { key: { mode: 'free'|'paid', price } }
            extras_pricing: {},
            // Price for one person, by passenger type
            fare_prices: { adult: '', child: '', infant: '' },
            // A total typed for a passenger type (the price each is then worked out from it).
            fare_totals: { adult: '', child: '', infant: '' },
            // Which fare rows still follow the package's airfare (until the agent types their own).
            fare_auto: { adult: true, child: true },
            // PWD / Senior Citizen: a discount on this many of the passengers, at this rate (%).
            pwd_sc_count: 0,
            pwd_sc_rate: PWD_SC_DEFAULT_RATE,
            // 'percent' (the rate) or 'amount' (pesos off each PWD / SC passenger).
            pwd_sc_mode: 'percent',
            pwd_sc_amount: '',
            special_requests: '',
            airline_restrictions: [],

            // Custom Package Specifications. Hotel, transport and special requests are each
            // optional: a custom package includes none of them until staff add it.
            custom_has_hotel: false,
            custom_has_special_requests: false,
            custom_hotel_name: '',
            custom_preferred_hotel: '',
            custom_has_breakfast: true,
            custom_bed_config: 'Queen Bed',
            custom_check_in_date: '',
            custom_check_out_date: '',
            custom_smoking_preference: 'non_smoking',
            custom_pet_friendly: false,
            custom_has_transportation: false,
            custom_transportation_type: '',
            custom_special_requests: '',
            custom_estimated_budget: '',

            estimated_fare: 0,
            taxes_amount: 0,
            visa_assistance_fee: 0,
            insurance_fee: 0,
            other_charges: 0,
            service_fee: 0,
            // The ready-made package's price: per person x travellers until the agent sets it.
            package_amount: 0,
            package_amount_auto: true,
            extras_amount: 0,
            total_amount: 0,
            clients: [],
            // A corporate booking: the company its travellers were picked from.
            corporate_account_id: null,
            corporate_name: '',
            service_tax_rate: '',
            // Empty until a client is picked or a traveller is added with the + buttons.
            passengers: []
        },

        get isCustomPackage() {
            return this.formData.package_type === 'custom_package' || this.formData.travel_package_id === 'custom';
        },

        /**
         * Panel ids in the order they are shown. Destination & Flight (3) and
         * Visa, Contact & Extras (10) each absorbed steps that used to stand
         * alone; Airline Restrictions (13) follows the flight it belongs to.
         */
        get stepSequence() {
            return this.isCustomPackage ? [1, 2, 3, 13, 6, 5, 10, 12] : [1, 2, 3, 13, 5, 10, 12];
        },

        get stepIndex() {
            const i = this.stepSequence.indexOf(this.currentStep);
            return i === -1 ? 0 : i;
        },

        get totalSteps() {
            return this.stepSequence.length;
        },

        get activeSteps() {
            const labels = {
                1: 'Travellers', 2: 'Documents', 3: 'Destination & Flight', 13: 'Restrictions', 6: 'Customize Package',
                5: 'Passengers', 10: this.formData.travel_type === 'international' ? 'Visa & Contact' : 'Contact & Extras', 12: 'Review',
            };
            return this.stepSequence.map(step => labels[step]);
        },

        get activeStepTitles() {
            const titles = {
                1: 'Travel Type & Passengers',
                2: 'Travel Document Uploads',
                3: 'Destination & Flight',
                13: 'Airline Restrictions',
                6: 'Customize Package Specifications',
                5: 'Passenger Information Manifest',
                10: this.formData.travel_type === 'international' ? 'Visa, Contact & Extras' : 'Contact & Extras',
                12: 'Review, Verification & Quotation',
            };
            return this.stepSequence.map((step, idx) => `Step ${idx + 1} - ${titles[step]}`);
        },

        get errorCount() {
            return Object.keys(this.errors).length;
        },

        /**
         * The Trip & Flight fields a picked ready-made package sets, which then
         * cannot be changed here. A field the package leaves empty (e.g. no fixed
         * dates, or dates already past) stays editable so the booking can be made.
         */
        /** The package's terms as label / value rows for the review step; empty ones are left out. */
        packageDetails(pkg) {
            if (!pkg) {
                return [];
            }
            const labels = {
                category: { domestic: 'Domestic', short_haul: 'International: short haul', long_haul: 'International: long haul' },
                airfare: { included: 'Airfare included', not_included: 'Airfare not included', optional: 'Optional add-on' },
                trip: { round_trip: 'Round Trip', one_way: 'One Way', multi_city: 'Multi-City' },
                time: { anytime: 'Anytime', morning: 'Morning', afternoon: 'Afternoon', evening: 'Evening' },
                cabin: { economy: 'Economy', premium_economy: 'Premium Economy', business: 'Business', first_class: 'First Class' },
            };
            const money = amount => parseFloat(amount) > 0 ? (pkg.price_currency === 'USD' ? '$' : '₱') + Number(amount).toLocaleString() : '';
            const day = value => (value || '').substring(0, 10);
            const range = (from, to) => [day(from), day(to)].filter(Boolean).join(' – ');
            const route = pkg.trip_type === 'multi_city' && Array.isArray(pkg.multi_city_segments)
                ? pkg.multi_city_segments.map(seg => (seg.from || '?') + ' → ' + (seg.to || '?')).join(', ')
                : [pkg.origin_airport, pkg.flight_destination].filter(Boolean).join(' → ');

            return [
                ['Destination', pkg.destination?.name],
                ['Category', labels.category[pkg.category]],
                ['Capacity', pkg.number_of_pax ? pkg.number_of_pax + ' pax' : ''],
                ['Price per person', pkg.price],
                ['Airfare', [labels.airfare[pkg.airfare_inclusion], money(pkg.airfare_amount)].filter(Boolean).join(' · ')],
                ['Trip', labels.trip[pkg.trip_type]],
                ['Route', route],
                ['Flight dates', pkg.trip_type === 'round_trip' ? range(pkg.departure_date, pkg.return_date) : day(pkg.departure_date)],
                ['Flight time', labels.time[pkg.preferred_flight_time]],
                ['Airline', pkg.airline?.name],
                ['Cabin class', labels.cabin[pkg.cabin_class]],
                ['Baggage', pkg.baggage_allowance],
                ['Hotel', [pkg.hotel_name, pkg.preferred_hotel].filter(Boolean).join(' · ')],
                ['Bed', pkg.bed_config],
                ['Breakfast', pkg.has_breakfast ? 'Included' : 'Not included'],
                ['Hotel dates', range(pkg.check_in_date, pkg.check_out_date)],
                ['Smoking', pkg.smoking_preference === 'smoking' ? 'Smoking' : 'Non-smoking'],
                ['Pets', pkg.pet_friendly ? 'Allowed' : 'Not allowed'],
                ['Transport', pkg.has_transportation ? (pkg.transportation_type || 'Included') : 'None'],
            ].filter(([, value]) => value);
        },

        /** The package's longer texts (inclusions, itinerary, remarks…) for the review step. */
        packageNotes(pkg) {
            if (!pkg) {
                return [];
            }
            return [
                ['Inclusions', pkg.inclusions],
                ['Exclusions', pkg.exclusions],
                ['Itinerary', pkg.itinerary],
                ['Special requests', pkg.special_requests],
                ['Remarks', pkg.remarks],
            ].filter(([, value]) => value && String(value).trim());
        },

        /**
         * The multi-city legs in travel order (by date when every leg has one,
         * otherwise as entered), the same order the ticket and emails use.
         */
        get orderedLegs() {
            if (this.formData.trip_type !== 'multi_city') {
                return [];
            }
            const legs = (this.formData.multi_city_segments || []).filter(leg => (leg.from || '').trim() || (leg.to || '').trim());
            return legs.every(leg => leg.date) ? [...legs].sort((a, b) => a.date.localeCompare(b.date)) : legs;
        },

        /** Ready-Made Tour Package is chosen but no package has been picked yet. */
        get awaitingPackage() {
            return this.formData.package_type === 'with_package' && !this.isCustomPackage && !this.selectedPackage;
        },

        get packageLock() {
            const pkg = this.formData.package_type === 'with_package' && !this.isCustomPackage ? this.selectedPackage : null;
            const day = value => (value || '').substring(0, 10);
            const today = new Date().toISOString().split('T')[0];
            const dated = !!pkg && day(pkg.departure_date) !== '' && day(pkg.departure_date) >= today;
            return {
                any: !!pkg && !!(pkg.trip_type || pkg.preferred_flight_time || pkg.origin_airport || pkg.flight_destination || pkg.destination || dated),
                trip_type: !!pkg?.trip_type,
                preferred_flight_time: !!pkg?.preferred_flight_time,
                origin: !!pkg?.origin_airport,
                destination: !!(pkg?.flight_destination || pkg?.destination),
                departure_date: dated,
                return_date: dated && day(pkg.return_date) > day(pkg.departure_date),
                segments: pkg?.trip_type === 'multi_city' && Array.isArray(pkg.multi_city_segments) && pkg.multi_city_segments.length > 0,
                destination_country: !!pkg?.destination?.location,
                destination_city: !!(pkg?.destination?.name || pkg?.flight_destination),
                arrival_airport: !!pkg?.flight_destination,
                preferred_airline: !!pkg?.airline?.name,
            };
        },

        get selectedAirline() {
            return this.airlines.find(a => String(a.id) === String(this.formData.airline_id)) || null;
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
            this.dropUnavailableInsurance();

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

            // Fewer passengers: the PWD / SC count comes down with them, and the total follows.
            ['total_passengers', 'adults_count', 'children_count', 'infants_count'].forEach(field => this.$watch('formData.' + field, () => this.checkPwdCount()));

            // Messages clear as soon as the field they point at is fixed.
            this.$watch('formData', () => this.refreshErrors());

            this.$watch('currentStep', (step) => {
                try { localStorage.setItem(STEP_KEY, JSON.stringify({ step, pendingId: this.pendingId || null })); } catch (e) { /* storage unavailable */ }
                // The package dates start from the flight dates chosen a step earlier.
                if (step === 6) {
                    this.formData.custom_check_in_date = this.formData.custom_check_in_date || this.formData.departure_date;
                    this.formData.custom_check_out_date = this.formData.custom_check_out_date || this.formData.return_date;
                }
                if (step === 10 || step === 12) {
                    this.calculateGrandTotal();
                }
                this.$nextTick(() => {
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                });
            });

            this.restoreStep();

            this.calculateGrandTotal();
        },

        /**
         * After a refresh, reopen the step the form was on. Only for the same form:
         * the saved step must belong to this draft (and to this pending ticket, if any)
         * and still be part of the wizard for the travel type chosen.
         */
        restoreStep() {
            if (!this.hasDraft) {
                return;
            }
            let saved = null;
            try { saved = JSON.parse(localStorage.getItem(STEP_KEY) || 'null'); } catch (e) { /* storage unavailable */ }
            if (!saved || (saved.pendingId || null) !== (this.pendingId || null)) {
                return;
            }
            if (this.stepSequence.includes(saved.step)) {
                this.currentStep = saved.step;
            }
        },

        /** Travel insurance is chosen under its own section, not as a concierge service; a saved form may still list it. */
        dropRetiredServices() {
            this.formData.selected_services = (this.formData.selected_services || []).filter(key => key !== 'travel_insurance');
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
                            p.passport_upload = '';
                            p.passport_error = '';
                            p.government_id_upload = '';
                            p.government_id_error = '';
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
                    this.upgradePwdDiscount(parsed);
                    this.upgradeFareAuto(parsed);
                    this.upgradeCustomPackage(parsed);
                    this.dropRetiredServices();
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
                this.companyResults = [];
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
                const data = response.ok ? await response.json() : { clients: [], companies: [] };

                // Ignore an older search that finished after a newer one.
                if (token !== this.clientSearchToken) return;

                this.clientResults = data.clients || [];
                this.companyResults = data.companies || [];
            } catch (e) {
                if (token !== this.clientSearchToken) return;
                this.clientResults = [];
                this.companyResults = [];
            }

            this.clientSearching = false;
            this.clientSearched = true;
        },

        /** Open a company's member list, to tick the travellers on this booking. */
        async openCompany(company) {
            this.company = { ...company, members: [], selected: [], loading: true };
            try {
                const response = await fetch(this.companyMembersUrl.replace('__ID__', company.id), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = response.ok ? await response.json() : { members: [] };
                this.company.members = data.members || [];
            } catch (e) {
                this.company.members = [];
            }
            this.company.loading = false;
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        toggleAllMembers() {
            const free = this.company.members.filter(m => !this.isClientPicked(m)).map(m => m.id);
            this.company.selected = this.company.selected.length === free.length ? [] : free;
        },

        /** Add the ticked members as travellers, and the company's contact as the booker. */
        addCompanyMembers() {
            const company = this.company;
            company.members.filter(m => company.selected.includes(m.id)).forEach(m => this.selectClient(m));

            this.formData.corporate_account_id = company.id;
            this.formData.corporate_name = company.name;
            if (company.contact_person || company.contact_email || company.contact_phone) {
                this.formData.contact_name = company.contact_person || company.name;
                this.formData.contact_email = company.contact_email || this.formData.contact_email;
                this.formData.contact_phone = company.contact_phone || this.formData.contact_phone;
            }

            this.company = null;
            this.companyResults = [];
            this.saveDraft();
            this.$nextTick(() => { if (typeof lucide !== 'undefined') lucide.createIcons(); });
        },

        clearCorporate() {
            this.formData.corporate_account_id = null;
            this.formData.corporate_name = '';
            this.formData.service_tax_rate = '';
            this.calculateGrandTotal();
            this.saveDraft();
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
                    localStorage.removeItem(STEP_KEY);
                } catch (e) { /* storage unavailable */ }

                window.location.href = saved.redirect;
            } catch (e) {
                alert('The ticket could not be saved as pending. Check the connection and try again.');
                this.pendingSaving = false;
            }
        },

        addRestriction(text) {
            if (!Array.isArray(this.formData.airline_restrictions)) {
                this.formData.airline_restrictions = [];
            }
            if (text && this.formData.airline_restrictions.includes(text)) {
                return;
            }
            this.formData.airline_restrictions.push(text);
            this.saveDraft();
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },

        removeRestriction(idx) {
            this.formData.airline_restrictions.splice(idx, 1);
            this.saveDraft();
        },

        // A draft from before hotel and special requests became optional: include what it filled in.
        upgradeCustomPackage(saved) {
            if (!('custom_has_hotel' in saved)) {
                this.formData.custom_has_hotel = !!(this.formData.custom_hotel_name || this.formData.custom_preferred_hotel);
            }
            if (!('custom_has_special_requests' in saved)) {
                this.formData.custom_has_special_requests = !!this.formData.custom_special_requests;
            }
            // Visa assistance is no longer offered on the ticket: an earlier draft's passenger gets a choice that exists.
            (this.formData.passengers || []).forEach(p => {
                if (p.visa_status === 'needs_assistance') p.visa_status = 'visa_not_required';
            });
        },

        restorePending(pending) {
            const payload = pending.payload || {};

            // Uploaded files cannot travel with a saved form; they are attached again.
            (payload.passengers || []).forEach(p => {
                ['passport_file_name', 'visa_file_name', 'passport_photo_file_name', 'supporting_doc_file_name',
                    'government_id_file_name', 'birth_cert_file_name', 'school_id_file_name', 'exit_clearance_file_name']
                    .forEach(field => { p[field] = ''; });
                p.passport_upload = '';
                p.passport_error = '';
                p.government_id_upload = '';
                p.government_id_error = '';
            });

            Object.assign(this.formData, payload);
            this.upgradePwdDiscount(payload);
            this.upgradeFareAuto(payload);
            this.upgradeCustomPackage(payload);
            this.dropRetiredServices();
            this.pendingId = pending.id;
            this.pendingSavedAt = pending.saved_at;
            this.hasDraft = true;
            try { localStorage.setItem(PENDING_KEY, String(pending.id)); } catch (e) { /* storage unavailable */ }

            // Tickets paused before the steps were merged may name a step that
            // now lives inside another one.
            const mergedInto = { 4: 3, 7: 10, 8: 10, 9: 10, 11: 10 };
            const step = mergedInto[pending.step] || pending.step;
            if (this.stepSequence.includes(step)) {
                this.currentStep = step;
            }

            this.saveDraft();
        },

        /**
         * Abandon the transaction: forget the saved draft and open an empty
         * wizard. A ticket already saved as pending stays in the Ticket Directory.
         */
        cancelTransaction() {
            if (this.isSubmitting || this.pendingSaving) return;

            const kept = this.pendingId ? ' The copy saved as pending in the Ticket Directory is kept.' : '';
            if (!confirm('Cancel this transaction? Everything entered on this form will be cleared.' + kept)) return;

            try {
                localStorage.removeItem(DRAFT_KEY);
                localStorage.removeItem(PENDING_KEY);
                localStorage.removeItem(STEP_KEY);
            } catch (e) { /* storage unavailable */ }

            window.location.href = config.createUrl;
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
                    this.applyPackageFlight(pkg);
                    this.formData.package_amount = 0;
                    this.formData.package_amount_auto = true;
                    this.formData.fare_auto = Object.fromEntries(PACKAGE_AIRFARE_TYPES.map(type => [type, true]));
                    this.calculateGrandTotal();
                }
            } else {
                this.formData.travel_package_id = '';
            }
            this.saveDraft();
        },

        /**
         * A ready-made package carries the flight terms it is sold with: fill
         * them in so staff only add what changes per booking (flight numbers,
         * times, fare). An airline switched off in the wizard is left unset.
         */
        applyPackageFlight(pkg) {
            if (pkg.airline_id && this.airlines.some(a => String(a.id) === String(pkg.airline_id))) {
                this.formData.airline_id = String(pkg.airline_id);
            }
            if (pkg.origin_airport) {
                this.formData.origin = pkg.origin_airport;
            }
            if (pkg.cabin_class) {
                this.formData.travel_class = pkg.cabin_class;
            }
            if (pkg.trip_type) {
                this.formData.trip_type = pkg.trip_type;
            }
            if (pkg.preferred_flight_time) {
                this.formData.preferred_flight_time = pkg.preferred_flight_time;
            }
            if (pkg.flight_destination) {
                this.formData.destination = pkg.flight_destination;
            }
            // International destination details: the destination's country and city,
            // the flight destination as the arrival airport, and the package's airline.
            if (pkg.destination?.location) {
                this.formData.destination_country = pkg.destination.location;
            }
            if (pkg.destination?.name || pkg.flight_destination) {
                this.formData.destination_city = pkg.destination?.name || pkg.flight_destination;
            }
            if (pkg.flight_destination) {
                this.formData.arrival_airport = pkg.flight_destination;
            }
            if (pkg.airline?.name) {
                this.formData.preferred_airline = pkg.airline.name;
            }
            // Fixed package dates, unless they have already passed (the wizard would reject them).
            const today = new Date().toISOString().split('T')[0];
            const packageDate = value => (value || '').substring(0, 10);
            if (packageDate(pkg.departure_date) >= today) {
                this.formData.departure_date = packageDate(pkg.departure_date);
                if (packageDate(pkg.return_date) > this.formData.departure_date) {
                    this.formData.return_date = packageDate(pkg.return_date);
                }
            }
            // A multi-city package brings its route; each leg's date is set for this booking.
            if (pkg.trip_type === 'multi_city' && Array.isArray(pkg.multi_city_segments) && pkg.multi_city_segments.length) {
                this.formData.multi_city_segments = pkg.multi_city_segments.map(seg => ({ from: seg.from || '', to: seg.to || '', date: '' }));
            }
            if (pkg.baggage_allowance) {
                this.addRestriction('Baggage: ' + pkg.baggage_allowance);
            }
        },

        customPackageErrors() {
            const errors = {};
            if (this.formData.custom_has_hotel && this.formData.custom_check_in_date && this.formData.custom_check_out_date
                && this.formData.custom_check_out_date < this.formData.custom_check_in_date) {
                errors.custom_check_out_date = 'Check-out cannot be earlier than check-in.';
            }
            return errors;
        },

        /** The package budget is only a note: prices come from the quotation's own figures. */
        applyCustomPackage() {
            this.saveDraft();
            return true;
        },

        goToStep(step) {
            // Only allow jumping back to a step already passed.
            if (this.stepSequence.indexOf(step) <= this.stepIndex) {
                this.clearErrors();
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
                this.clearErrors();
                this.currentStep = this.stepSequence[this.stepIndex - 1];
                window.scrollTo({ top: 0, behavior: 'smooth' });
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
                passport_profile_name: '',
                passport_upload: '',
                passport_error: '',
                government_id_profile_name: '',
                government_id_upload: '',
                government_id_error: '',
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

        /** The passport scan is the one document the agent cannot go on without. */
        documentErrors() {
            const errors = {};
            this.formData.passengers.forEach((p, idx) => {
                if (this.passportRequired(p) && this.scanStatus(p, 'passport').state !== 'uploaded') {
                    errors['passengers.' + idx + '.passport_file'] = this.passengerName(p, idx) + ': upload the passport scan to continue.';
                }
            });
            return errors;
        },

        scanStatus(p, doc) {
            if (p[doc + '_file_name']) {
                return { state: 'uploaded', file: p[doc + '_file_name'], review: 'Uploaded', tone: 'bg-emerald-100 text-emerald-800' };
            }
            if (p.client_id && p['use_profile_' + doc]) {
                return { state: 'uploaded', file: p[doc + '_profile_name'] || 'Saved on the client’s profile', review: 'On profile', tone: 'bg-emerald-100 text-emerald-800' };
            }
            if (doc === 'passport' ? this.passportRequired(p) : this.governmentIdRequired(p)) {
                return { state: 'missing', review: doc === 'passport' ? 'Required' : 'Upload later', tone: 'bg-amber-100 text-amber-800' };
            }
            return { state: 'optional', review: 'Not required', tone: 'bg-gray-100 text-dark/60' };
        },

        passportScanStatus(p) {
            return this.scanStatus(p, 'passport');
        },

        // Mirrors the server rule: a government ID scan is required for Filipino adults on domestic travel.
        governmentIdRequired(p) {
            return this.formData.travel_type === 'domestic' && p.passenger_type === 'adult' && p.nationality_type === 'filipino';
        },

        /** Why a scan cannot be used: the same limits the server puts on a client's scan. */
        scanFileError(file) {
            if (!/\.(jpe?g|png|webp|pdf)$/i.test(file.name)) return 'Upload a JPG, PNG, WEBP or PDF file.';
            if (file.size > 5 * 1024 * 1024) return 'The file is larger than 5 MB.';
            return '';
        },

        /**
         * A passport or government ID file was picked (`doc`). A registered
         * client's scan is saved to their profile right away, replacing the old
         * one only once the server has stored the new one; anyone else's is held
         * in the form until the booking is created. Either way a rejected file
         * leaves what was there.
         */
        async onScanChosen(e, p, doc) {
            const input = e.target;
            const file = input.files && input.files[0];
            const kept = keptScanFiles.get(input);
            const label = SCAN_LABELS[doc];
            const hadScan = this.scanStatus(p, doc).state === 'uploaded';
            const restoreKept = () => {
                try {
                    const held = new DataTransfer();
                    if (kept) held.items.add(kept);
                    input.files = held.files;
                } catch (err) {
                    input.value = '';
                }
            };

            p[doc + '_error'] = '';

            if (!file) {
                restoreKept();
                return;
            }

            const problem = this.scanFileError(file);
            const rejected = reason => {
                p[doc + '_error'] = reason + (hadScan ? ' The current ' + label + ' is unchanged.' : '');
            };

            if (!p.client_id) {
                if (problem) {
                    restoreKept();
                    rejected(problem);
                    return;
                }
                keptScanFiles.set(input, file);
                p[doc + '_file_name'] = file.name;
                this.saveDraft();
                return;
            }

            if (problem) {
                input.value = '';
                rejected(problem);
                return;
            }

            const body = new FormData();
            body.append(doc + '_photo', file);
            p[doc + '_upload'] = 'uploading';

            try {
                const response = await fetch(this.scanUploadUrls[doc].replace('__CLIENT__', p.client_id), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body,
                });
                const data = await response.json().catch(() => ({}));
                const invalid = data.errors && data.errors[doc + '_photo'];

                if (!response.ok) {
                    rejected((invalid && invalid[0]) || data.message || 'The ' + label + ' could not be uploaded. Try again.');
                } else {
                    p['use_profile_' + doc] = true;
                    p[doc + '_profile_name'] = data.file_name || file.name;
                    p[doc + '_file_name'] = '';
                }
            } catch (err) {
                rejected('Could not reach the server. Check the connection and try again.');
            } finally {
                p[doc + '_upload'] = '';
                input.value = '';
                this.saveDraft();
            }
        },

        /**
         * Whether the passport runs out less than six months after departure. Worked out live from the
         * current departure date, so the badges agree with the check on Continue even when the
         * traveller was picked before the date was entered.
         */
        passportTooShort(passenger) {
            // A Filipino on a domestic flight needs no passport, so no six-month warning either.
            if (!this.passportRequired(passenger)) return false;
            if (!passenger.passport_expiry_date || !this.formData.departure_date) return false;

            const sixMonthsAfterDep = new Date(this.formData.departure_date);
            sixMonthsAfterDep.setMonth(sixMonthsAfterDep.getMonth() + 6);

            return new Date(passenger.passport_expiry_date) < sixMonthsAfterDep;
        },

        checkPassportValidity(passenger) {
            passenger.passport_warning = this.passportTooShort(passenger);
        },

        onFileChange(e, passenger, fieldName) {
            // Refuse a file the server would refuse, now, while the other attachments are still in place.
            const picked = e.target.files && e.target.files[0];
            const problem = picked ? this.scanFileError(picked) : '';
            if (problem) {
                e.target.value = '';
                passenger[fieldName] = '';
                this.uploadProblem = picked.name + ': ' + problem;
                return;
            }
            this.uploadProblem = '';

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
            this.calculateGrandTotal();
        },

        toggleSpecialRequest(key) {
            const idx = this.formData.special_requests_list.indexOf(key);
            if (idx > -1) {
                this.formData.special_requests_list.splice(idx, 1);
            } else {
                this.formData.special_requests_list.push(key);
            }
            this.calculateGrandTotal();
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
            // The fare is the per-passenger prices in the quotation (a package's airfare fills them in).
            this.syncPackageAirfare();
            this.formData.estimated_fare = Math.round(this.fareTotal() * 100) / 100;
            const fare = this.formData.estimated_fare;
            const taxes = parseFloat(this.formData.taxes_amount) || 0;
            const visa = parseFloat(this.formData.visa_assistance_fee) || 0;
            const ins = parseFloat(this.formData.insurance_fee) || 0;
            const other = parseFloat(this.formData.other_charges) || 0;
            const serviceFee = parseFloat(this.formData.service_fee) || 0;
            const serviceTax = this.serviceTax();
            this.syncPackageAmount();
            const packagePrice = this.hasReadyMadePackage() ? (parseFloat(this.formData.package_amount) || 0) : 0;
            this.formData.extras_amount = this.extrasTotal();
            const subtotal = fare + packagePrice + taxes + visa + ins + other + serviceFee + serviceTax + this.formData.extras_amount;
            // The same sum as TicketBooking::pwdScDiscountFor on the server: the total shown is what is saved.
            this.formData.total_amount = Math.round(Math.max(0, subtotal - this.pwdDiscount()) * 100) / 100;
        },

        // ---- PWD / Senior Citizen discount (a discount, not a passenger type) ----

        totalPax() {
            return parseInt(this.formData.total_passengers) || 0;
        },

        pwdLabel() {
            return this.formData.travel_type === 'international' ? 'PWD' : 'PWD / Senior Citizen';
        },

        pwdCount() {
            return Math.min(Math.max(parseInt(this.formData.pwd_sc_count) || 0, 0), this.totalPax());
        },

        pwdRate() {
            const rate = parseFloat(this.formData.pwd_sc_rate);
            return isNaN(rate) ? 0 : Math.min(Math.max(rate, 0), 100);
        },

        /**
         * The price of one person the discount applies to: the adult fare each (the average fare
         * with no adults) plus the package per person. Mirrors TicketBooking::pwdScPerPerson.
         */
        pwdPerPerson() {
            const pax = Math.max(1, this.totalPax());
            const parts = {
                fare: this.fareQty('adult') > 0 ? this.fareSubtotal('adult') / this.fareQty('adult') : this.fareTotal() / pax,
                package: (this.hasReadyMadePackage() ? (parseFloat(this.formData.package_amount) || 0) : 0) / pax,
            };
            return PWD_SC_DISCOUNTABLE.reduce((sum, part) => sum + (parts[part] || 0), 0);
        },

        /** Discount = count × price per person × rate. */
        pwdDiscount() {
            const count = this.pwdCount();
            if (count === 0) return 0;
            if (this.formData.pwd_sc_mode === 'amount') {
                return Math.round(count * Math.max(parseFloat(this.formData.pwd_sc_amount) || 0, 0) * 100) / 100;
            }
            if (this.pwdRate() === 0) return 0;
            return Math.round(count * this.pwdPerPerson() * this.pwdRate()) / 100;
        },

        /** What each PWD / SC passenger gets off, for the labels: "20%" or "₱600.00". */
        pwdEach() {
            return this.formData.pwd_sc_mode === 'amount' ? '₱' + this.formatNumber(this.formData.pwd_sc_amount) : this.pwdRate() + '%';
        },

        setPwdMode(mode) {
            this.formData.pwd_sc_mode = mode === 'amount' ? 'amount' : 'percent';
            this.calculateGrandTotal();
            this.saveDraft();
        },

        setPwdAmount(value) {
            this.formData.pwd_sc_amount = value;
            this.calculateGrandTotal();
            this.saveDraft();
        },

        setPwdCount(value) {
            const wanted = Math.max(parseInt(value) || 0, 0);
            this.pwdNotice = wanted > this.totalPax();
            this.formData.pwd_sc_count = Math.min(wanted, this.totalPax());
            this.calculateGrandTotal();
            this.saveDraft();
        },

        setPwdRate(value) {
            this.formData.pwd_sc_rate = value;
            this.calculateGrandTotal();
            this.saveDraft();
        },

        /** The passengers changed: the PWD / SC count can be no more than them. */
        checkPwdCount() {
            const count = parseInt(this.formData.pwd_sc_count) || 0;
            this.pwdNotice = count > this.totalPax();
            if (this.pwdNotice) this.formData.pwd_sc_count = this.totalPax();
            this.calculateGrandTotal();
        },

        /**
         * A form saved when PWD / SC was a fare of its own: its count becomes the discount count
         * (at the usual rate), and the old PWD fare is dropped.
         */
        upgradePwdDiscount(saved) {
            const prices = this.formData.fare_prices || {};
            const totals = this.formData.fare_totals || {};
            delete prices.pwd_sc;
            delete totals.pwd_sc;
            if (!saved || saved.pwd_sc_rate === undefined || saved.pwd_sc_rate === null || saved.pwd_sc_rate === '') {
                this.formData.pwd_sc_rate = PWD_SC_DEFAULT_RATE;
            }
            if (this.formData.pwd_sc_mode !== 'amount') this.formData.pwd_sc_mode = 'percent';
            this.formData.pwd_sc_count = Math.min(Math.max(parseInt(this.formData.pwd_sc_count) || 0, 0), this.totalPax());
        },

        /** The package price per person, from the package total. */
        packagePriceEach() {
            const total = parseFloat(this.formData.package_amount) || 0;
            const pax = this.totalPax();
            return total > 0 && pax > 0 ? (Math.round(total / pax * 100) / 100).toFixed(2) : '';
        },

        /** Typing the price per person sets the package total for every passenger. */
        setPackagePriceEach(value) {
            const each = parseFloat(value);
            this.formData.package_amount = isNaN(each) ? 0 : Math.round(each * this.totalPax() * 100) / 100;
            this.formData.package_amount_auto = false;
            this.calculateGrandTotal();
            this.saveDraft();
        },

        /** Corporate bookings: the service tax rate (1 to 10%) of the Amega Service Fee. */
        serviceTax() {
            const rate = parseFloat(this.formData.service_tax_rate) || 0;
            if (!this.formData.corporate_account_id || rate <= 0) return 0;
            return Math.round((parseFloat(this.formData.service_fee) || 0) * Math.min(rate, 10)) / 100;
        },

        hasReadyMadePackage() {
            return this.formData.package_type === 'with_package' && !this.isCustomPackage && !!this.selectedPackage;
        },

        /**
         * The package's price for the whole party: its price per person times the
         * travellers, kept in step until the agent types their own amount. A
         * package priced in another currency is left for the agent to enter in pesos.
         */
        syncPackageAmount() {
            if (!this.hasReadyMadePackage()) {
                this.formData.package_amount = 0;
                this.formData.package_amount_auto = true;
                return;
            }
            const pkg = this.selectedPackage;
            if (this.formData.package_amount_auto && (pkg.price_currency || 'PHP') === 'PHP') {
                const pax = parseInt(this.formData.total_passengers) || 0;
                // The airfare in the package price is in the fare rows, so the package row is the rest.
                const each = Math.max((parseFloat(pkg.price_amount) || 0) - this.packageAirfare(), 0);
                this.formData.package_amount = Math.round(each * pax * 100) / 100;
            }
        },

        /** The airfare per person a ready-made package includes in its price (pesos), or 0. */
        packageAirfare() {
            const pkg = this.hasReadyMadePackage() ? this.selectedPackage : null;
            if (!pkg || pkg.airfare_inclusion !== 'included' || (pkg.price_currency || 'PHP') !== 'PHP') return 0;
            return Math.max(parseFloat(pkg.airfare_amount) || 0, 0);
        },

        /**
         * The Adult and Child fare rows follow the package's airfare until the agent types their own
         * price; with no package airfare they are cleared again.
         */
        syncPackageAirfare() {
            const airfare = this.packageAirfare();
            const auto = this.formData.fare_auto || {};
            PACKAGE_AIRFARE_TYPES.forEach(type => {
                if (!auto[type]) return;
                this.formData.fare_prices = { ...(this.formData.fare_prices || {}), [type]: airfare > 0 ? airfare.toFixed(2) : '' };
                this.formData.fare_totals = { ...(this.formData.fare_totals || {}), [type]: '' };
            });
        },

        /** Back to the package's airfare on a row the agent changed. */
        usePackageAirfare(type) {
            this.formData.fare_auto = { ...(this.formData.fare_auto || {}), [type]: true };
            this.calculateGrandTotal();
            this.saveDraft();
        },

        /** A form saved before the airfare filled itself in: a row follows the package only while empty. */
        upgradeFareAuto(saved) {
            if (saved && saved.fare_auto) return;
            const prices = this.formData.fare_prices || {};
            const totals = this.formData.fare_totals || {};
            this.formData.fare_auto = Object.fromEntries(PACKAGE_AIRFARE_TYPES.map(type => [type, !prices[type] && !totals[type]]));
        },

        // ---- Fare per passenger type ----

        /** A saved form may name a plan the admin has since switched off; fall back to one on offer. */
        dropUnavailableInsurance() {
            const f = this.formData;
            if (!this.insurancePlans.length) {
                f.has_insurance = false;
                return;
            }
            if (!this.insurancePlans.some(p => p.key === f.insurance_plan)) {
                f.insurance_plan = (this.insurancePlans.find(p => p.is_popular) || this.insurancePlans[0]).key;
            }
        },

        insurancePlanName(key) {
            const plan = this.insurancePlans.find(p => p.key === key);
            return plan ? plan.name : '';
        },

        /**
         * Picking a plan fills the Insurance Fee with its price for every
         * passenger; clearing the tick takes that amount back out. A fee typed
         * by hand is left alone when the tick is cleared.
         */
        applyInsurancePlan() {
            const plan = this.insurancePlans.find(p => p.key === this.formData.insurance_plan);
            const fee = plan ? Math.round(parseFloat(plan.price_per_pax) * (parseInt(this.formData.total_passengers) || 0) * 100) / 100 : 0;
            if (this.formData.has_insurance) {
                this.formData.insurance_fee = fee;
            } else if (parseFloat(this.formData.insurance_fee) === fee) {
                this.formData.insurance_fee = 0;
            }
            this.calculateGrandTotal();
        },

        /** The passenger types on this booking, from the travellers: a row each in the fare table. */
        fareTypes() {
            return ['adult', 'child', 'infant'].filter(type => this.fareQty(type) > 0);
        },

        fareTypeLabel(type) {
            return { adult: 'Adult', child: 'Child', infant: 'Infant' }[type];
        },

        /** How many passengers pay each fare. PWD / SC is a discount: it never changes these. */
        fareQty(type) {
            return {
                adult: parseInt(this.formData.adults_count) || 0,
                child: parseInt(this.formData.children_count) || 0,
                infant: parseInt(this.formData.infants_count) || 0,
            }[type] || 0;
        },

        farePrice(type) {
            return parseFloat((this.formData.fare_prices || {})[type]) || 0;
        },

        /** A total typed for the type, or null when the price each was typed. */
        fareTypedTotal(type) {
            const typed = (this.formData.fare_totals || {})[type];
            return typed === '' || typed === null || typed === undefined ? null : (parseFloat(typed) || 0);
        },

        fareSubtotal(type) {
            const typed = this.fareTypedTotal(type);
            return typed !== null ? Math.round(typed * 100) / 100 : Math.round(this.fareQty(type) * this.farePrice(type) * 100) / 100;
        },

        /** What the Total price box shows: what was typed there, or price each × passengers. */
        fareTotalInput(type) {
            const typed = (this.formData.fare_totals || {})[type];
            if (typed !== '' && typed !== null && typed !== undefined) return typed;
            const subtotal = this.fareSubtotal(type);
            return subtotal > 0 ? subtotal.toFixed(2) : '';
        },

        setFareTotal(type, total) {
            this.formData.fare_auto = { ...(this.formData.fare_auto || {}), [type]: false };
            const qty = this.fareQty(type);
            const amount = parseFloat(total);
            this.formData.fare_totals = { ...(this.formData.fare_totals || {}), [type]: total };
            this.formData.fare_prices = {
                ...(this.formData.fare_prices || {}),
                [type]: total === '' ? '' : (qty > 0 && !isNaN(amount) ? (Math.round(amount / qty * 100) / 100).toString() : ''),
            };
            this.calculateGrandTotal();
            this.saveDraft();
        },

        fareTotal() {
            return ['adult', 'child', 'infant'].reduce((sum, type) => sum + this.fareSubtotal(type), 0);
        },

        setFarePrice(type, price) {
            this.formData.fare_auto = { ...(this.formData.fare_auto || {}), [type]: false };
            this.formData.fare_prices = { ...(this.formData.fare_prices || {}), [type]: price };
            // Typing the price each again makes the total follow it.
            this.formData.fare_totals = { ...(this.formData.fare_totals || {}), [type]: '' };
            this.calculateGrandTotal();
            this.saveDraft();
        },

        /** Every service and request picked, with what it costs. */
        selectedExtras() {
            const options = [...this.availableServices, ...this.specialRequestOptions];
            return [...this.formData.selected_services, ...this.formData.special_requests_list].map(key => ({
                key,
                label: this.extraLabel(options, key),
                paid: this.extraMode(key) === 'paid',
                price: parseFloat(this.extraPrice(key)) || 0,
            }));
        },

        // ---- Free / paid concierge services and special requests ----

        extraLabel(options, key) {
            return (options.find(option => option.key === key) || {}).label || key;
        },

        extraMode(key) {
            return ((this.formData.extras_pricing || {})[key] || {}).mode || 'free';
        },

        extraPrice(key) {
            const price = ((this.formData.extras_pricing || {})[key] || {}).price;
            return price === undefined || price === null ? '' : price;
        },

        setExtra(key, changes) {
            const current = (this.formData.extras_pricing || {})[key] || { mode: 'free', price: '' };
            this.formData.extras_pricing = { ...(this.formData.extras_pricing || {}), [key]: { ...current, ...changes } };
            this.calculateGrandTotal();
            this.refreshErrors();
            this.saveDraft();
        },

        setExtraMode(key, mode) {
            this.setExtra(key, { mode });
        },

        setExtraPrice(key, price) {
            this.setExtra(key, { price });
        },

        /** The selected services and requests marked Paid, with the price typed for each. */
        paidExtras() {
            return [...this.formData.selected_services, ...this.formData.special_requests_list]
                .filter(key => this.extraMode(key) === 'paid')
                .map(key => ({ key, price: parseFloat(this.extraPrice(key)) || 0 }));
        },

        extrasTotal() {
            return this.paidExtras().reduce((sum, extra) => sum + extra.price, 0);
        },

        extrasErrors() {
            const errors = {};
            this.paidExtras().filter(extra => extra.price <= 0).forEach(extra => {
                const label = this.extraLabel([...this.availableServices, ...this.specialRequestOptions], extra.key);
                errors['extras.' + extra.key] = label + ': enter the price, or mark it Free.';
            });
            return errors;
        },

        formatNumber(num) {
            return Number(num || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        /**
         * Save the trip as a quotation and jump straight to the booking
         * agreement, without the document or manifest checks.
         */
        saveAsQuotation() {
            // A quote needs only the route, the date and someone to address it to.
            const routeErrors = () => {
                const all = this.tripErrors();
                return Object.fromEntries(['origin', 'destination', 'departure_date']
                    .filter(key => all[key]).map(key => [key, all[key]]));
            };
            if (!this.showErrors(3, routeErrors)) {
                return;
            }
            if (!this.showErrors(10, () => this.filled(this.formData.contact_name) ? {} : { contact_name: 'A quotation needs a client name to address it to.' })) {
                return;
            }

            this.quotationMode = true;
            this.isSubmitting = true;

            this.$nextTick(() => {
                document.getElementById('bookingWizardForm').requestSubmit();
            });
        },

        // STEP CHECKS
        //
        // Each step lists every problem at once, keyed by the field it belongs
        // to, instead of stopping at the first one. Keys match the fields'
        // data-error-key so the first problem can be scrolled to.

        filled(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        },

        passengerName(p, idx) {
            return [p.first_name, p.last_name].filter(Boolean).join(' ').trim() || ('Passenger #' + (idx + 1));
        },

        /** An uploaded file is still attached (a restored form keeps only the file name). */
        hasFile(inputName) {
            const input = document.querySelector('input[name="' + inputName + '"]');
            return !!(input && input.files && input.files.length);
        },

        /** The review step: a ready-made package needs its price, and the agent's acknowledgement. */
        reviewErrors() {
            const errors = {};
            if (this.hasReadyMadePackage() && !(parseFloat(this.formData.package_amount) > 0)) {
                errors.package_amount = 'Enter the package price in pesos.';
            }
            if (!this.acknowledged) {
                errors.agent_acknowledged = 'Tick the acknowledgement to confirm the information is correct.';
            }
            return errors;
        },

        errorsForStep(step) {
            switch (step) {
                case 1: return this.travellerErrors();
                case 2: this.forgetDetachedUploads(); return this.documentErrors();
                // Waiting for a ready-made package: only the package is asked for (the rest is hidden).
                case 3: return this.awaitingPackage ? this.tripErrors() : { ...this.destinationErrors(), ...this.tripErrors() };
                case 6: return this.customPackageErrors();
                case 5: return this.manifestErrors();
                case 10: return { ...this.contactErrors(), ...this.visaErrors(), ...this.extrasErrors() };
                case 12: return this.reviewErrors();
                default: return {};
            }
        },

        /** Check one step; on failure stay there with every problem shown. */
        checkStep(step) {
            return this.showErrors(step, () => this.errorsForStep(step));
        },

        showErrors(step, check) {
            const errors = check();
            if (!Object.keys(errors).length) {
                this.clearErrors();
                return true;
            }
            this.errors = errors;
            this.errorStep = step;
            this.errorCheck = check;
            this.currentStep = step;
            this.$nextTick(() => this.focusError(Object.keys(errors)[0]));
            return false;
        },

        refreshErrors() {
            if (!this.errorCheck) {
                return;
            }
            const errors = this.errorCheck();
            if (Object.keys(errors).length) {
                this.errors = errors;
            } else {
                this.clearErrors();
            }
        },

        clearErrors() {
            this.errors = {};
            this.errorStep = null;
            this.errorCheck = null;
        },

        /** Bring a field into view; fall back to the list when it has no visible field. */
        focusError(key) {
            const field = document.querySelector('[data-error-key="' + key + '"]');
            const target = field && field.offsetParent !== null ? field : document.getElementById('wizard-errors');
            if (!target) {
                return;
            }
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.focus({ preventScroll: true });
        },

        travellerErrors() {
            const errors = {};
            this.formData.clients.filter(c => !c.date_of_birth).forEach(c => {
                errors['clients.' + c.id] = 'Enter the birth date of ' + c.name + ' so they can be booked as Adult, Child or Infant.';
            });

            const adults = parseInt(this.formData.adults_count) || 0;
            const children = parseInt(this.formData.children_count) || 0;
            const infants = parseInt(this.formData.infants_count) || 0;

            if (adults < 1) {
                errors.adults_count = adults + children + infants === 0
                    ? 'Add the travellers from the registered clients above; the pax count follows them.'
                    : 'A booking needs at least one adult passenger.';
            } else if (infants > adults) {
                errors.infants_count = 'Each infant must be accompanied by an adult.';
            }

            this.formData.total_passengers = adults + children + infants;
            return errors;
        },

        destinationErrors() {
            const errors = {};
            if (this.formData.travel_type !== 'international') {
                return errors;
            }
            if (!this.filled(this.formData.destination_country)) errors.destination_country = 'Enter the destination country.';
            if (!this.filled(this.formData.destination_city)) errors.destination_city = 'Enter the destination city.';
            if (!this.filled(this.formData.arrival_airport)) errors.arrival_airport = 'Enter the arrival airport.';
            return errors;
        },

        tripErrors() {
            // Trip & Flight stays hidden until a ready-made package is picked; ask for the package first.
            if (this.awaitingPackage) {
                return { travel_package_id: 'Pick a ready-made package.' };
            }
            const errors = {};
            const f = this.formData;
            const today = new Date().toISOString().split('T')[0];

            if (!this.filled(f.origin)) errors.origin = 'Enter the origin airport or city.';
            if (!this.filled(f.destination)) errors.destination = 'Enter the destination.';

            if (!f.departure_date) {
                errors.departure_date = 'Pick the departure date.';
            } else if (f.departure_date < today) {
                errors.departure_date = 'The departure date cannot be in the past.';
            }

            if (f.trip_type === 'round_trip') {
                if (!f.return_date) {
                    errors.return_date = 'Pick the return date for a round trip.';
                } else if (f.departure_date && f.return_date <= f.departure_date) {
                    errors.return_date = 'The return date must be after the departure date.';
                }
            }

            // The flight booked on the airline's site (the voucher and reminders show it).
            if (!this.filled(f.airline_id)) errors.airline_id = 'Pick the airline.';
            if (!this.filled(f.flight_number)) errors.flight_number = 'Enter the departing flight number.';
            if (!f.departure_time) errors.departure_time = 'Enter the departure time.';
            if (!f.arrival_time) errors.arrival_time = 'Enter the arrival time.';
            if (f.trip_type === 'round_trip') {
                if (!this.filled(f.return_flight_number)) errors.return_flight_number = 'Enter the returning flight number.';
                if (!f.return_departure_time) errors.return_departure_time = 'Enter the return departure time.';
                if (!f.return_arrival_time) errors.return_arrival_time = 'Enter the return arrival time.';
            }
            return errors;
        },

        manifestErrors() {
            const errors = {};
            const f = this.formData;
            const total = (parseInt(f.adults_count) || 0) + (parseInt(f.children_count) || 0) + (parseInt(f.infants_count) || 0);
            if (total !== (parseInt(f.total_passengers) || 0)) {
                errors.total_passengers = 'The Adults, Children and Infants counts do not add up to the passenger total. Adjust them in Step 1.';
            }

            const label = t => t.charAt(0).toUpperCase() + t.slice(1);

            f.passengers.forEach((p, idx) => {
                const key = field => 'passengers.' + idx + '.' + field;
                const who = this.passengerName(p, idx);

                if (!this.filled(p.first_name)) errors[key('first_name')] = 'Passenger #' + (idx + 1) + ': enter the first name.';
                if (!this.filled(p.last_name)) errors[key('last_name')] = 'Passenger #' + (idx + 1) + ': enter the last name.';

                if (!p.date_of_birth) {
                    errors[key('date_of_birth')] = who + ': enter the date of birth.';
                } else {
                    const age = this.ageInYears(p.date_of_birth);
                    const ageType = age === null ? p.passenger_type : this.typeForAge(age);
                    if (ageType !== p.passenger_type) {
                        errors[key('date_of_birth')] = who + ' is booked as ' + label(p.passenger_type) + ', but this birth date makes them ' + label(ageType) + ' on the departure date. Adjust the counts in Step 1.';
                    }
                }

                if (!p.gender) errors[key('gender')] = who + ': select the gender.';

                // A passport is required for international travel and for foreign
                // nationals (the same rule as the Step 2 scan and the server).
                const needsPassport = this.passportRequired(p);
                if (needsPassport && !this.filled(p.passport_number)) errors[key('passport_number')] = who + ': enter the passport number.';

                // The expiration date (and the six months) only when a passport is needed: a Filipino on
                // a domestic flight travels on a government ID.
                if (!needsPassport) {
                    // Nothing more about the passport.
                } else if (!p.passport_expiry_date) {
                    errors[key('passport_expiry_date')] = who + ': enter the passport expiration date.';
                } else if (f.departure_date) {
                    if (this.passportTooShort(p)) {
                        errors[key('passport_expiry_date')] = who + '’s passport expires on ' + p.passport_expiry_date + ', less than six months after departure. It must be renewed before travel.';
                    }
                }
                this.checkPassportValidity(p);
            });
            return errors;
        },

        /** A restored form remembers file names but not the files; drop names with nothing behind them. */
        forgetDetachedUploads() {
            this.formData.passengers.forEach((p, idx) => {
                if (p.passport_file_name && !this.hasFile('passengers[' + idx + '][passport_file]')) {
                    p.passport_file_name = '';
                }
            });
        },

        visaErrors() {
            const errors = {};
            if (this.formData.travel_type !== 'international') {
                return errors;
            }

            // The visa uploads do not hold the booking up: they can be added on the
            // ticket page later, and the ticket is not issued until they are. All
            // that is done here is to forget a file name with no file behind it
            // (a restored form keeps the name, not the file).
            const uploads = [
                ['visa_file', 'visa_file_name'],
                ['passport_photo_file', 'passport_photo_file_name'],
                ['supporting_doc_file', 'supporting_doc_file_name'],
            ];

            this.formData.passengers.forEach((p, idx) => {
                uploads.forEach(([fileField, nameField]) => {
                    if (p[nameField] && !this.hasFile('passengers[' + idx + '][' + fileField + ']')) {
                        p[nameField] = '';
                    }
                });
            });
            return errors;
        },

        contactErrors() {
            const errors = {};
            const f = this.formData;
            if (f.travel_type === 'international') {
                if (!this.filled(f.emergency_contact_name)) errors.emergency_contact_name = 'Enter the emergency contact’s full name.';
                if (!this.filled(f.emergency_contact_relationship)) errors.emergency_contact_relationship = 'Enter their relationship to the passenger.';
                if (!this.filled(f.emergency_contact_phone)) errors.emergency_contact_phone = 'Enter the emergency contact’s phone number.';
            }
            if (!this.filled(f.contact_name)) errors.contact_name = 'Enter the booker’s name.';
            if (!this.filled(f.contact_email)) errors.contact_email = 'Enter the booker’s email.';
            if (!this.filled(f.contact_phone)) errors.contact_phone = 'Enter the booker’s phone number.';
            return errors;
        },

        /** Continue from the current step, the same as its Continue button. */
        continueFromStep() {
            if (!this.checkStep(this.currentStep)) {
                return;
            }
            if (this.currentStep === 6 && !this.applyCustomPackage()) {
                return;
            }
            this.nextStep();
        },

        /**
         * Enter in a field must never submit the wizard: the form is the whole
         * booking, so it would be saved (and the ticket page opened) from any step.
         * Before the last step Enter means Continue; on the last step the booking
         * is only completed with the Confirm button. Fields that handle Enter
         * themselves (e.g. the client search) have already called preventDefault.
         */
        onEnterKey(e) {
            const target = e.target;
            if (e.defaultPrevented || target.tagName === 'TEXTAREA' || target.tagName === 'BUTTON' || target.tagName === 'A' || target.isContentEditable) {
                return;
            }
            e.preventDefault();
            if (this.stepIndex < this.stepSequence.length - 1) {
                this.continueFromStep();
            }
        },

        validateSubmission(e) {
            // Quotations are saved on the trip details alone; the document and
            // manifest rules are applied when the booking is completed.
            if (this.quotationMode) {
                return true;
            }

            // Only the last step completes a booking. Anything else that submits
            // the form early is treated as Continue instead.
            if (this.stepIndex < this.stepSequence.length - 1) {
                e.preventDefault();
                this.isSubmitting = false;
                this.continueFromStep();
                return false;
            }

            // Walk the steps in order and stop on the first one with problems.
            for (const step of this.stepSequence) {
                if (!this.checkStep(step)) {
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
