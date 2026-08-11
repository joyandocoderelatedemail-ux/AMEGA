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

            <div class="animate-on-scroll space-y-8">
                <div class="rounded-2xl overflow-hidden shadow-sm border border-gray-100 h-64">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d61650.93966193514!2d120.52723912167968!3d15.1406872!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3396f0a1d3e3e6b7%3A0xb0f7e5a3d2c1f8e9!2sAngeles%2C%20Pampanga!5e0!3m2!1sen!2sph!4v1" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="AMEGA Office Location"></iframe>
                </div>

                <div class="space-y-5">
                    @php
                        $contactInfo = [
                            ['Location', 'Angeles City, Pampanga, Philippines', 'map-pin'],
                            ['Email', 'info@amegatravel.com', 'mail'],
                            ['Phone', '+63 (XXX) XXX-XXXX', 'phone'],
                            ['Business Hours', 'Mon - Fri: 9:00 AM - 7:00 PM / Sat: 10:00 AM - 4:00 PM', 'clock'],
                        ];
                    @endphp

                    @foreach ($contactInfo as [$label, $value, $icon])
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center shrink-0">
                                <i data-lucide="{{ $icon }}" class="w-5 h-5 text-primary"></i>
                            </div>
                            <div>
                                <p class="font-heading font-semibold text-dark text-sm">{{ $label }}</p>
                                <p class="text-dark/50 text-sm">{{ $value }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center gap-4">
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="Facebook">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="Instagram">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="X (Twitter)">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center hover:bg-primary hover:text-white transition-all duration-200" aria-label="LinkedIn">
                        <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.262-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
