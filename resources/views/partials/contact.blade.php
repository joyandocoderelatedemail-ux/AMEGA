<section id="contact" class="py-20 sm:py-24 section-gradient-warm relative overflow-hidden">
    <div class="section-line"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Get In Touch
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2">Get In Touch</h2>
            <p class="text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto font-normal leading-relaxed">Ready to start your journey? Reach out to us and let's plan your travel or process your visa & official document request.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <div class="animate-on-scroll">
                <form action="{{ route('contact.submit') }}" method="POST" class="space-y-5">
                    @csrf
                    
                    <!-- Client Account Category Selection -->
                    <div>
                        <label for="account_category" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-2">Category / Inquiry Type *</label>
                        <select id="account_category" name="account_category" class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm font-medium">
                            <option value="Individual">Individual Traveler</option>
                            <option value="Corporate">Corporate / Group Travel</option>
                            <option value="Visa Processing Assistance">Visa Processing Assistance</option>
                            <option value="Philippine Retirement Visa (SRRV)">Philippine Retirement Visa (SRRV)</option>
                        </select>
                    </div>

                    <!-- Split Name Fields -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="first_name" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">First / Given Name *</label>
                            <input type="text" id="first_name" name="first_name" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark placeholder-dark/30 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm" placeholder="Juan">
                        </div>
                        <div>
                            <label for="last_name" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Last Name / Surname *</label>
                            <input type="text" id="last_name" name="last_name" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark placeholder-dark/30 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm" placeholder="Dela Cruz">
                        </div>
                    </div>

                    <!-- Email & Phone Number -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Email Address *</label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark placeholder-dark/30 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm" placeholder="juan@example.com">
                        </div>
                        <div>
                            <label for="phone" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Phone / Mobile Number *</label>
                            <input type="tel" id="phone" name="phone" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark placeholder-dark/30 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm" placeholder="+63 912 345 6789">
                        </div>
                    </div>

                    <!-- Message / Travel Details -->
                    <div>
                        <label for="message" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1.5">Your Message & Requirements *</label>
                        <textarea id="message" name="message" rows="4" required class="w-full px-4 py-3 rounded-xl border border-gray-200 bg-white text-dark placeholder-dark/30 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-sm resize-none" placeholder="Describe your travel dates, preferred destinations, or visa requirements..."></textarea>
                    </div>

                    <button type="submit" class="w-full sm:w-auto px-8 py-4 bg-primary text-white font-bold rounded-full hover:bg-primary-dark transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5 text-base focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                        Submit Inquiry Now
                    </button>
                </form>
            </div>

            <div class="animate-on-scroll space-y-6">
                <!-- Map Embed -->
                <div class="rounded-2xl overflow-hidden shadow-sm border border-gray-100 h-60">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3851.696614761405!2d120.5862459!3d15.1680865!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3396f27e79896a6b%3A0xae6aa524b007b56a!2sAmega%20Travel%20and%20Tours%20Services!5e0!3m2!1sen!2sph!4v1" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="AMEGA Office Location"></iframe>
                </div>

                <!-- Office Address & Hours Card -->
                <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm space-y-4">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center shrink-0 text-primary">
                            <i data-lucide="map-pin" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h4 class="font-heading font-bold text-dark text-sm">AMEGA TRAVEL AND TOURS</h4>
                            <p class="text-dark/70 text-xs sm:text-sm mt-0.5">Unit 1 & 2 Astrofield Bldg, Mitchell Avenue, Balibago, Angeles City, Pampanga</p>
                            <a href="https://maps.app.goo.gl/EvDJo4FiAPTJU8rN8" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-primary text-xs font-semibold hover:underline mt-1">
                                <span>Open in Google Maps</span>
                                <i data-lucide="external-link" class="w-3 h-3"></i>
                            </a>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex items-start gap-4">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center shrink-0 text-primary">
                            <i data-lucide="clock" class="w-5 h-5"></i>
                        </div>
                        <div class="text-xs sm:text-sm text-dark/70 space-y-1">
                            <h4 class="font-heading font-bold text-dark text-sm">Opening Hours</h4>
                            <p><span class="font-semibold text-dark">Mon - Fri:</span> 08:30 AM - 05:30 PM</p>
                            <p><span class="font-semibold text-dark">Saturday:</span> 08:30 AM - 03:30 PM <span class="text-dark/50 text-xs">(Visa Assistance via appointment)</span></p>
                        </div>
                    </div>
                </div>

                <!-- Decommissioned Number Notice -->
                <div class="p-4 rounded-xl bg-amber-50 border border-amber-200/60 text-amber-800 text-xs flex items-start gap-3">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <div>
                        <span class="font-bold">Notice:</span> Please note that we are no longer using <span class="line-through font-mono text-amber-900">0917 119 4909</span>. Please contact our department numbers below.
                    </div>
                </div>

                <!-- Department Directory -->
                <div class="space-y-3">
                    <h4 class="font-heading font-bold text-dark text-sm uppercase tracking-wider text-dark/40">Department Directory</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <!-- Ticketing -->
                        <div class="p-4 rounded-xl bg-white border border-gray-100 shadow-sm space-y-2">
                            <div class="flex items-center gap-2 text-primary font-bold text-sm">
                                <i data-lucide="ticket" class="w-4 h-4"></i>
                                <span>Ticketing</span>
                            </div>
                            <div class="text-xs space-y-1">
                                <p class="text-dark/80"><span class="font-medium text-dark/50">Globe:</span> <a href="tel:09176264925" class="hover:text-primary">0917 626 4925</a></p>
                                <p class="text-dark/80"><span class="font-medium text-dark/50">Smart:</span> <a href="tel:09202220774" class="hover:text-primary">0920 222 0774</a></p>
                                <p class="text-dark/80 flex items-center gap-1"><span class="font-medium text-dark/50">Viber:</span> <a href="tel:09929225733" class="hover:text-primary">0992 922 5733</a></p>
                                <p class="pt-1"><a href="mailto:ticketing@amegatravelandtours.com" class="text-primary font-medium hover:underline truncate block">ticketing@amegatravelandtours.com</a></p>
                            </div>
                        </div>

                        <!-- Visa Assistance -->
                        <div class="p-4 rounded-xl bg-white border border-gray-100 shadow-sm space-y-2">
                            <div class="flex items-center gap-2 text-primary font-bold text-sm">
                                <i data-lucide="file-check" class="w-4 h-4"></i>
                                <span>Visa Assistance</span>
                            </div>
                            <div class="text-xs space-y-1">
                                <p class="text-dark/80"><span class="font-medium text-dark/50">Mobile:</span> <a href="tel:09176264181" class="hover:text-primary">0917 626 4181</a></p>
                                <p class="pt-1"><a href="mailto:visas@amegatravelandtours.com" class="text-primary font-medium hover:underline truncate block">visas@amegatravelandtours.com</a></p>
                            </div>
                        </div>

                        <!-- Marketing -->
                        <div class="p-4 rounded-xl bg-white border border-gray-100 shadow-sm space-y-2">
                            <div class="flex items-center gap-2 text-primary font-bold text-sm">
                                <i data-lucide="megaphone" class="w-4 h-4"></i>
                                <span>Marketing</span>
                            </div>
                            <div class="text-xs space-y-1">
                                <p class="text-dark/80"><span class="font-medium text-dark/50">Mobile:</span> <a href="tel:09911032928" class="hover:text-primary">0991 103 2928</a></p>
                                <p class="pt-1"><a href="mailto:marketing@amegatravelandtours.com" class="text-primary font-medium hover:underline truncate block">marketing@amegatravelandtours.com</a></p>
                            </div>
                        </div>

                        <!-- General Inquiry -->
                        <div class="p-4 rounded-xl bg-white border border-gray-100 shadow-sm space-y-2">
                            <div class="flex items-center gap-2 text-primary font-bold text-sm">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                                <span>General Inquiry</span>
                            </div>
                            <div class="text-xs space-y-1">
                                <p class="text-dark/80"><span class="font-medium text-dark/50">Mobile:</span> <a href="tel:09499900663" class="hover:text-primary">0949 990 0663</a></p>
                                <p class="pt-1"><a href="mailto:aurora.amega@gmail.com" class="text-primary font-medium hover:underline truncate block">aurora.amega@gmail.com</a></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Links -->
                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs font-semibold text-dark/50 uppercase tracking-wider">Follow Us <span class="text-primary font-bold">@amegatravel</span></span>
                    <div class="flex items-center gap-3">
                        <a href="https://www.facebook.com/amegatravel" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="Facebook">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="https://www.tiktok.com/@amegatravelandtours" target="_blank" rel="noopener noreferrer" class="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="TikTok">
                            <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 1 1-5.2-1.74 2.89 2.89 0 0 1 2.31-2.83V7.63a6.33 6.33 0 0 0-5.11 6.16 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V9.69a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-2.22-1.12z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
