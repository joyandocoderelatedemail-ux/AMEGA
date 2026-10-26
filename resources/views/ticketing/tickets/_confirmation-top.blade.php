{{-- Part of the booking confirmation (_confirmation.blade.php): The booking itself: header, route and schedule, booked flight, restrictions, package and services. --}}
        <!-- Voucher Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 border-b border-gray-100 pb-6">
            <div class="flex items-center gap-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA" class="theme-logo-light h-10 sm:h-12 w-auto object-contain">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="AMEGA" class="theme-logo-dark h-10 sm:h-12 w-auto object-contain print:hidden">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-widest text-primary block">Amega Travel &amp; Tours</span>
                    <h1 class="text-xl sm:text-2xl font-heading font-black text-dark tracking-tight">Official Ticket Booking Confirmation</h1>
                </div>
            </div>

            <div class="sm:text-right space-y-1">
                <div class="text-[10px] font-bold uppercase tracking-wider text-dark/50">Ticket Reference</div>
                <div class="font-mono text-lg sm:text-xl font-black text-primary">{{ $ticket->booking_reference }}</div>
                @php
                    // The badge previously rendered emerald for every status,
                    // so a pending or cancelled booking looked confirmed.
                    $statusBadge = match ($ticket->status) {
                        \App\Models\TicketBooking::STATUS_ISSUED => 'bg-emerald-100 text-emerald-800',
                        \App\Models\TicketBooking::STATUS_CONFIRMED => 'bg-blue-100 text-blue-800',
                        \App\Models\TicketBooking::STATUS_CANCELLED => 'bg-rose-100 text-rose-800',
                        default => 'bg-amber-100 text-amber-800',
                    };
                @endphp
                <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider {{ $statusBadge }}">
                    Status: {{ ucfirst($ticket->status) }}
                </div>
                @if ($ticket->isQuotation())
                    <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 ml-1">
                        Quotation
                    </div>
                @endif
            </div>
        </div>

        <!-- Route & Schedule Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 rounded-2xl bg-gray-50 border border-gray-200">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Travel Type</span>
                <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                    <i data-lucide="{{ $ticket->travel_type === 'international' ? 'globe' : 'palmtree' }}" class="w-3.5 h-3.5 text-primary"></i>
                    {{ ucfirst($ticket->travel_type) }} Tour
                </span>
                @if($ticket->travel_class)
                    <span class="text-[10px] font-bold text-primary block mt-0.5 uppercase">{{ str_replace('_', ' ', $ticket->travel_class) }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Trip Route</span>
                @if ($ticket->multiCityLegs() !== [])
                    <ol class="mt-0.5 space-y-0.5">
                        @foreach ($ticket->multiCityLegs() as $leg)
                            <li class="text-xs font-bold text-dark">{{ $loop->iteration }}. {{ $leg['from'] ?: '?' }} &rarr; {{ $leg['to'] ?: '?' }}</li>
                        @endforeach
                    </ol>
                @else
                    <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                        {{ $ticket->origin }} &rarr; {{ $ticket->destination }}
                    </span>
                @endif
                @if($ticket->arrival_airport)
                    <span class="text-[10px] text-dark/50 block">Airport: {{ $ticket->arrival_airport }}</span>
                @endif
                @if($ticket->preferred_airline)
                    <span class="text-[10px] text-dark/50 block">Airline: {{ $ticket->preferred_airline }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Schedule</span>
                @if ($ticket->multiCityLegs() !== [])
                    <ol class="mt-0.5 space-y-0.5">
                        @foreach ($ticket->multiCityLegs() as $leg)
                            <li class="text-xs font-bold text-dark">{{ $loop->iteration }}. {{ $leg['date']?->format('M d, Y') ?? 'Date to be confirmed' }}</li>
                        @endforeach
                    </ol>
                @else
                    <span class="text-xs font-bold text-dark block mt-0.5">
                        Depart: {{ $ticket->departure_date->format('M d, Y') }}
                        @if($ticket->return_date)
                            <br>Return: {{ $ticket->return_date->format('M d, Y') }}
                        @endif
                    </span>
                @endif
                @if($ticket->preferred_flight_time && $ticket->preferred_flight_time !== 'anytime')
                    <span class="text-[10px] text-dark/50 capitalize block">Time: {{ $ticket->preferred_flight_time }}</span>
                @endif
            </div>
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Passengers Count</span>
                <span class="text-xs font-bold text-dark block mt-0.5">
                    {{ $ticket->total_passengers }} Pax ({{ $ticket->adults_count }} Adt, {{ $ticket->children_count }} Chd, {{ $ticket->infants_count }} Inf)
                </span>
                <span class="text-[10px] text-dark/50 block mt-0.5 uppercase font-bold">{{ str_replace('_', ' ', $ticket->trip_type) }}</span>
            </div>
        </div>

        <!-- Booked Flight: what staff found on the airline's site -->
        @if($ticket->hasBookedFlight())
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-5 rounded-2xl bg-white border border-gray-200">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">{{ $ticket->isIssued() ? 'Ticketed Airline' : 'Booked Airline' }}</span>
                    <span class="text-xs font-bold text-dark flex items-center gap-1 mt-0.5">
                        <i data-lucide="plane" class="w-3.5 h-3.5 text-primary"></i>
                        {{ $ticket->airline?->label() ?? 'Not recorded' }}
                    </span>
                    @if($ticket->airline_pnr)
                        <span class="text-[10px] text-dark/50 block">PNR: <span class="font-mono font-bold text-dark">{{ $ticket->airline_pnr }}</span></span>
                    @endif
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Departing Flight</span>
                    <span class="text-xs font-mono font-bold text-dark block mt-0.5">{{ $ticket->flight_number ?? '—' }}</span>
                    @if($ticket->departure_time)
                        <span class="text-[10px] text-dark/50 block">
                            {{ \App\Models\TicketBooking::formatFlightTime($ticket->departure_time) }}
                            @if($ticket->arrival_time) &rarr; {{ \App\Models\TicketBooking::formatFlightTime($ticket->arrival_time) }} @endif
                        </span>
                    @endif
                </div>
                @if($ticket->trip_type === 'round_trip')
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-dark/40 block">Return Flight</span>
                        <span class="text-xs font-mono font-bold text-dark block mt-0.5">{{ $ticket->return_flight_number ?? '—' }}</span>
                        @if($ticket->return_departure_time)
                            <span class="text-[10px] text-dark/50 block">
                                {{ \App\Models\TicketBooking::formatFlightTime($ticket->return_departure_time) }}
                                @if($ticket->return_arrival_time) &rarr; {{ \App\Models\TicketBooking::formatFlightTime($ticket->return_arrival_time) }} @endif
                            </span>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        <!-- Airline Restrictions: shown on the voucher, editable here at any time -->
        <div x-data="{ editing: {{ $errors->has('airline_restrictions*') ? 'true' : 'false' }}, items: @js(old('airline_restrictions', $ticket->airline_restrictions ?? [])) }"
             class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-3 {{ empty($ticket->airline_restrictions) ? 'print:hidden' : '' }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 flex items-center gap-1.5">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    Airline Restrictions
                </span>
                @unless ($readonly)
                <button type="button" x-show="!editing" @click="editing = true; if (!items.length) items.push(''); $nextTick(() => window.lucide && window.lucide.createIcons())"
                        class="print:hidden inline-flex items-center gap-1 text-[11px] font-bold text-primary hover:underline">
                    <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                    <span>{{ empty($ticket->airline_restrictions) ? 'Add restrictions' : 'Edit' }}</span>
                </button>
                @endunless
            </div>

            <div x-show="!editing">
                @if (! empty($ticket->airline_restrictions))
                    <ul class="space-y-1">
                        @foreach ($ticket->airline_restrictions as $restriction)
                            <li class="text-xs text-dark/80 flex items-start gap-2"><span class="text-amber-600">•</span><span>{{ $restriction }}</span></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-dark/50">No airline restrictions noted on this booking.</p>
                @endif
            </div>

            @unless ($readonly)
            <form x-show="editing" x-cloak method="POST" action="{{ route('ticketing.tickets.restrictions', $ticket) }}" class="space-y-2 print:hidden">
                @csrf
                @method('PUT')
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-center gap-2">
                        <input type="text" name="airline_restrictions[]" x-model="items[idx]" maxlength="500"
                               placeholder="e.g. Non-refundable"
                               class="flex-1 min-w-0 px-3 py-2 rounded-lg bg-white border border-gray-200 text-dark text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="button" @click="items.splice(idx, 1)" class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 shrink-0" title="Remove restriction">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </template>
                @error('airline_restrictions*')
                    <p class="text-[11px] font-bold text-rose-600">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                    <button type="button" @click="items.push(''); $nextTick(() => window.lucide && window.lucide.createIcons())"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-dashed border-gray-300 text-[11px] font-bold text-dark/70 hover:border-primary hover:text-primary">
                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                        <span>Add a restriction</span>
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="editing = false; items = @js($ticket->airline_restrictions ?? [])" class="px-3 py-1.5 rounded-lg bg-white border border-gray-200 text-[11px] font-bold text-dark/70 hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-primary text-white text-[11px] font-bold hover:bg-navy">Save Restrictions</button>
                    </div>
                </div>
            </form>
            @endunless
        </div>

        <!-- Package Details if selected -->
        @if($ticket->isCustomPackage() || !empty($ticket->custom_package_specs))
            <div class="p-5 rounded-2xl bg-amber-50/70 border border-amber-200 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-amber-200/60 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-sm shrink-0">
                            <i data-lucide="sparkles" class="w-4 h-4"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Customized Package Specifications</span>
                            <span class="text-sm font-bold text-amber-950">{{ $ticket->package_name ?: 'Custom Tour Package' }}</span>
                        </div>
                    </div>
                    @if(!empty($ticket->custom_package_specs['estimated_budget']))
                        <div class="sm:text-right">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800/70 block">Target Budget</span>
                            <span class="text-xs font-bold text-primary font-mono">&#8369;{{ number_format((float)$ticket->custom_package_specs['estimated_budget'], 2) }}</span>
                        </div>
                    @endif
                </div>

                @php $withHotel = $ticket->custom_package_specs['has_hotel'] ?? true; @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    @unless ($withHotel)
                        <div class="col-span-2 sm:col-span-3">
                            <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel</span>
                            <span class="font-bold text-dark/50 block">No hotel in this package</span>
                        </div>
                    @else
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Hotel / Location</span>
                        <span class="font-bold text-dark block">{{ $ticket->custom_package_specs['hotel_name'] ?? 'To be arranged' }}</span>
                        @if(!empty($ticket->custom_package_specs['preferred_hotel']))
                            <span class="text-[10px] text-dark/60 block">{{ $ticket->custom_package_specs['preferred_hotel'] }}</span>
                        @endif
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Bedding &amp; Breakfast</span>
                        <span class="font-semibold text-dark block">{{ $ticket->custom_package_specs['bed_config'] ?? 'Standard' }}</span>
                        <span class="text-[10px] font-bold block mt-0.5 {{ !empty($ticket->custom_package_specs['has_breakfast']) ? 'text-emerald-700' : 'text-dark/50' }}">
                            {{ !empty($ticket->custom_package_specs['has_breakfast']) ? '✓ Breakfast Included' : 'No Breakfast' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">Stay Dates</span>
                        <span class="font-semibold text-dark block">
                            {{ !empty($ticket->custom_package_specs['check_in_date']) ? \Carbon\Carbon::parse($ticket->custom_package_specs['check_in_date'])->format('M d, Y') : '—' }}
                            @if(!empty($ticket->custom_package_specs['check_out_date']))
                                &rarr; {{ \Carbon\Carbon::parse($ticket->custom_package_specs['check_out_date'])->format('M d, Y') }}
                            @endif
                        </span>
                    </div>
                    @endunless
                    <div>
                        <span class="text-[10px] text-amber-900/60 font-bold uppercase block">{{ $withHotel ? 'Policies & Transfer' : 'Transfer' }}</span>
                        @if ($withHotel)
                            <span class="text-[10px] font-semibold text-dark block">
                                {{ ($ticket->custom_package_specs['smoking_preference'] ?? '') === 'smoking' ? '🚬 Smoking' : '🚭 Non-Smoking' }}
                                • {{ !empty($ticket->custom_package_specs['pet_friendly']) ? '🐾 Pets OK' : 'No Pets' }}
                            </span>
                        @endif
                        <span class="text-[10px] font-bold block mt-0.5 {{ !empty($ticket->custom_package_specs['has_transportation']) ? 'text-primary' : 'text-dark/50' }}">
                            {{ !empty($ticket->custom_package_specs['has_transportation']) ? '🚗 Transport: ' . ($ticket->custom_package_specs['transportation_type'] ?? 'Arranged') : 'No Transport' }}
                        </span>
                    </div>
                </div>

                @if(!empty($ticket->custom_package_specs['special_requests']))
                    <div class="pt-2 border-t border-amber-200/50 text-xs">
                        <span class="text-[10px] font-bold text-amber-900/60 uppercase block">Special Requests</span>
                        <p class="text-dark/80 text-xs mt-0.5">{{ $ticket->custom_package_specs['special_requests'] }}</p>
                    </div>
                @endif
            </div>
        @elseif($ticket->package_name)
            <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center">
                        <i data-lucide="package" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 block">Tour Package</span>
                        <span class="text-xs font-bold text-amber-950">{{ $ticket->package_name }}</span>
                    </div>
                </div>
                @if($ticket->travelPackage)
                    <span class="text-xs font-bold text-primary">{{ $ticket->travelPackage->price }}</span>
                @endif
            </div>
        @endif

        <!-- Phase 2 International Services & Emergency Contact Banner -->
        @if($ticket->travel_type === 'international' || $ticket->has_insurance || !empty($ticket->selected_services))
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-5 rounded-2xl bg-blue-50/40 border border-blue-100 text-xs">
                <!-- Insurance & Add-on Services -->
                <div class="space-y-2">
                    <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Insurance &amp; Concierge Services</span>
                    @if($ticket->has_insurance)
                        <div class="flex items-center gap-1.5 text-emerald-800 font-bold">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                            <span>Travel Insurance: <span>{{ \App\Models\InsurancePlan::labelFor($ticket->insurance_plan) }}</span> Active</span>
                        </div>
                    @endif
                    @if(!empty($ticket->selected_services))
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach($ticket->selected_services as $srv)
                                <span class="px-2 py-0.5 rounded-lg bg-white border border-blue-200 text-dark/80 text-[10px] font-bold">
                                    ✓ {{ ucwords(str_replace('_', ' ', $srv)) }}
                                    @if($price = $ticket->extraPriceLabel($srv))
                                        <span class="font-mono text-dark/50">· {{ $price }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Emergency Contact -->
                @if($ticket->emergency_contact_name)
                    <div class="space-y-1">
                        <span class="font-bold text-dark uppercase tracking-wider text-[10px] block">Emergency Contact Person</span>
                        <div class="font-bold text-dark">{{ $ticket->emergency_contact_name }} ({{ $ticket->emergency_contact_relationship }})</div>
                        <div class="text-dark/60 font-semibold">{{ $ticket->emergency_contact_phone }} {{ $ticket->emergency_contact_email ? '• ' . $ticket->emergency_contact_email : '' }}</div>
                    </div>
                @endif
            </div>
        @endif
