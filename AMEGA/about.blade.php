<section id="about" x-data="{ activeTab: 'accreditation' }" class="py-24 bg-white relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-6 sm:px-10 lg:px-12">
        
        <!-- Header -->
        <div class="max-w-3xl mb-16">
            <span class="text-xs font-bold tracking-[0.25em] uppercase text-amber-800 bg-amber-100/80 px-4 py-1.5 rounded-full mb-4 inline-block">
                Our Legacy & History
            </span>
            <h2 class="text-3xl sm:text-5xl font-bold font-outfit text-stone-900 tracking-tight leading-tight">
                24+ Years of Crafting Seamless <br>
                <span class="font-light italic text-stone-500 font-serif">Travel Experiences.</span>
            </h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">
            
            <!-- Left: Interactive Milestone Timeline -->
            <div class="lg:col-span-5 space-y-6 relative border-l-2 border-amber-200 pl-6 ml-2">
                
                <!-- Milestone 1 -->
                <div class="relative group">
                    <div class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-amber-600 border-4 border-white shadow-sm"></div>
                    <span class="text-xs font-bold font-outfit text-amber-800">MAY 26, 2001</span>
                    <h4 class="text-lg font-bold text-stone-900 font-outfit">Founded in Angeles City</h4>
                    <p class="text-xs text-stone-500 font-light leading-relaxed mt-1">
                        Amega Travel & Tours Services established its head office in Pampanga with a commitment to trustworthy travel services.
                    </p>
                </div>

                <!-- Milestone 2 -->
                <div class="relative group">
                    <div class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-stone-900 border-4 border-white shadow-sm"></div>
                    <span class="text-xs font-bold font-outfit text-stone-900">ACCREDITATIONS</span>
                    <h4 class="text-lg font-bold text-stone-900 font-outfit">Government Certification</h4>
                    <p class="text-xs text-stone-500 font-light leading-relaxed mt-1">
                        Secured official accreditation from Department of Tourism (DOT), Bureau of Immigration (BI), and PRA.
                    </p>
                </div>

                <!-- Milestone 3 -->
                <div class="relative group">
                    <div class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-amber-600 border-4 border-white shadow-sm"></div>
                    <span class="text-xs font-bold font-outfit text-amber-800">GLOBAL EXPANSION</span>
                    <h4 class="text-lg font-bold text-stone-900 font-outfit">International Tour Packages</h4>
                    <p class="text-xs text-stone-500 font-light leading-relaxed mt-1">
                        Expanded operations to include Japan, Korea, Europe, Asia-Pacific tours and complete visa assistance.
                    </p>
                </div>

                <!-- Milestone 4 -->
                <div class="relative group">
                    <div class="absolute -left-[31px] top-1.5 w-4 h-4 rounded-full bg-stone-900 border-4 border-white shadow-sm"></div>
                    <span class="text-xs font-bold font-outfit text-stone-900">PRESENT DAY</span>
                    <h4 class="text-lg font-bold text-stone-900 font-outfit">50,000+ Happy Travelers</h4>
                    <p class="text-xs text-stone-500 font-light leading-relaxed mt-1">
                        Continuing over two decades of excellence with customized itineraries and 24/7 dedicated support.
                    </p>
                </div>

            </div>

            <!-- Right: Tabbed Details Box -->
            <div class="lg:col-span-7">
                <!-- Tab Buttons -->
                <div class="flex items-center gap-3 border-b border-stone-200 pb-4 mb-8">
                    <button @click="activeTab = 'accreditation'" 
                            :class="activeTab === 'accreditation' ? 'border-b-2 border-stone-900 text-stone-900 font-bold' : 'text-stone-400 hover:text-stone-900'"
                            class="pb-2 text-xs uppercase tracking-wider font-outfit transition-all">
                        Official Accreditations
                    </button>
                    <button @click="activeTab = 'services'" 
                            :class="activeTab === 'services' ? 'border-b-2 border-stone-900 text-stone-900 font-bold' : 'text-stone-400 hover:text-stone-900'"
                            class="pb-2 text-xs uppercase tracking-wider font-outfit transition-all">
                        Our Scope Of Services
                    </button>
                    <button @click="activeTab = 'mission'" 
                            :class="activeTab === 'mission' ? 'border-b-2 border-stone-900 text-stone-900 font-bold' : 'text-stone-400 hover:text-stone-900'"
                            class="pb-2 text-xs uppercase tracking-wider font-outfit transition-all">
                        Why Trust Amega
                    </button>
                </div>

                <!-- Tab Content 1: Official Accreditations with Logos -->
                <div x-show="activeTab === 'accreditation'" class="space-y-6">
                    <h3 class="text-2xl font-bold font-outfit text-stone-900">Fully Licensed & Regulatory Compliant</h3>
                    <p class="text-stone-600 text-sm font-light leading-relaxed">
                        Amega Travel & Tours Services operates with full official accreditation from government agencies, ensuring complete safety, legal authorization, and peace of mind for every booking.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        
                        <!-- DOT -->
                        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-stone-200 flex items-start gap-4 hover:border-amber-400/60 hover:shadow-md transition-all">
                            <div class="w-14 h-14 rounded-xl bg-white p-2 border border-stone-200 shadow-xs shrink-0 flex items-center justify-center overflow-hidden">
                                <img src="{{ asset('images/Department_of_Tourism_(DOT).svg.webp') }}" alt="Department of Tourism" class="max-w-full max-h-full object-contain">
                            </div>
                            <div>
                                <span class="text-amber-800 font-bold text-xs uppercase tracking-wider block mb-0.5">DOT Certified</span>
                                <div class="text-stone-900 font-bold text-sm font-outfit">Department of Tourism</div>
                                <p class="text-[11px] text-stone-500 font-light mt-1">Official accreditation for domestic & international tour operations.</p>
                            </div>
                        </div>

                        <!-- BI -->
                        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-stone-200 flex items-start gap-4 hover:border-amber-400/60 hover:shadow-md transition-all">
                            <div class="w-14 h-14 rounded-xl bg-white p-2 border border-stone-200 shadow-xs shrink-0 flex items-center justify-center overflow-hidden">
                                <img src="{{ asset('images/Bureau_of_Immigration_PH_2022.png') }}" alt="Bureau of Immigration" class="max-w-full max-h-full object-contain">
                            </div>
                            <div>
                                <span class="text-stone-900 font-bold text-xs uppercase tracking-wider block mb-0.5">BI Accredited</span>
                                <div class="text-stone-900 font-bold text-sm font-outfit">Bureau of Immigration</div>
                                <p class="text-[11px] text-stone-500 font-light mt-1">Authorized entity for travel processing & document assistance.</p>
                            </div>
                        </div>

                        <!-- PRA -->
                        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-stone-200 flex items-start gap-4 hover:border-amber-400/60 hover:shadow-md transition-all">
                            <div class="w-14 h-14 rounded-xl bg-white p-2 border border-stone-200 shadow-xs shrink-0 flex items-center justify-center overflow-hidden">
                                <img src="{{ asset('images/PhilipineretirementAutorityLogo.jpg') }}" alt="Philippine Retirement Authority" class="max-w-full max-h-full object-contain">
                            </div>
                            <div>
                                <span class="text-amber-800 font-bold text-xs uppercase tracking-wider block mb-0.5">PRA Authorized</span>
                                <div class="text-stone-900 font-bold text-sm font-outfit">Philippine Retirement Authority</div>
                                <p class="text-[11px] text-stone-500 font-light mt-1">Specialized assistance for retirement travel & residency programs.</p>
                            </div>
                        </div>

                        <!-- CDC -->
                        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-stone-200 flex items-start gap-4 hover:border-amber-400/60 hover:shadow-md transition-all">
                            <div class="w-14 h-14 rounded-xl bg-white p-2 border border-stone-200 shadow-xs shrink-0 flex items-center justify-center overflow-hidden">
                                <img src="{{ asset('images/clarkdevelopmentlogo.webp') }}" alt="Clark Development Corp." class="max-w-full max-h-full object-contain">
                            </div>
                            <div>
                                <span class="text-stone-900 font-bold text-xs uppercase tracking-wider block mb-0.5">CDC Registered</span>
                                <div class="text-stone-900 font-bold text-sm font-outfit">Clark Development Corp.</div>
                                <p class="text-[11px] text-stone-500 font-light mt-1">Registered commercial partner in Central Luzon travel hub.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Tab Content 2 -->
                <div x-show="activeTab === 'services'" class="space-y-6" style="display:none;">
                    <h3 class="text-2xl font-bold font-outfit text-stone-900">Comprehensive Travel Solutions</h3>
                    <p class="text-stone-600 text-sm font-light leading-relaxed">
                        We provide end-to-end travel logistics for individual vacationers, family reunions, honeymoons, and corporate incentive trips.
                    </p>

                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-stone-700 font-medium">
                        <li class="p-3 rounded-xl bg-[#FAF8F5] border border-stone-200 flex items-center gap-2">✓ Domestic All-Inclusive Island Packages</li>
                        <li class="p-3 rounded-xl bg-[#FAF8F5] border border-stone-200 flex items-center gap-2">✓ International Flight & Hotel Bookings</li>
                        <li class="p-3 rounded-xl bg-[#FAF8F5] border border-stone-200 flex items-center gap-2">✓ Japan, Korea & Schengen Visa Assistance</li>
                        <li class="p-3 rounded-xl bg-[#FAF8F5] border border-stone-200 flex items-center gap-2">✓ Airport Van Transfers & Private Chauffeur</li>
                    </ul>
                </div>

                <!-- Tab Content 3 -->
                <div x-show="activeTab === 'mission'" class="space-y-6" style="display:none;">
                    <h3 class="text-2xl font-bold font-outfit text-stone-900">Unmatched Personal Attention</h3>
                    <p class="text-stone-600 text-sm font-light leading-relaxed">
                        Unlike automated booking engines, Amega assigns dedicated human travel specialists who guide you from initial inquiry to your safe arrival back home.
                    </p>
                </div>

            </div>

        </div>

    </div>
</section>
