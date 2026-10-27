<section id="gallery" class="py-20 sm:py-24 section-gradient-light relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Travel Moments &amp; Destinations
            </span>
            <h2 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Travel Moments &amp; Destinations</h2>
            <p class="font-body font-normal text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
                A glimpse into the incredible experiences and beautiful moments captured with AMEGA travelers.
            </p>
        </div>

        <!-- Video Reels Showcase -->
        <div class="mb-14 animate-on-scroll">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1.5 h-7 bg-accent rounded-full"></div>
                <h3 class="font-heading font-bold text-2xl text-dark">Featured Video Reels</h3>
                <span class="font-subheading font-semibold text-xs text-primary tracking-widest uppercase px-3 py-1 rounded-full bg-primary/5 border border-primary/20">TikTok &amp; Reels</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @php
                    $reels = [
                        [
                            'video' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/03.mp4',
                            'poster' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/727671140_1019259127162740_8771441488807075376_n.jpg',
                            'title' => 'Chongqing Scenic Mountain & River Tour'
                        ],
                        [
                            'video' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/04.mp4',
                            'poster' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/728225684_10163261303701819_2863079182894534645_n.jpg',
                            'title' => 'Chongqing Wulong Karst Adventure'
                        ],
                        [
                            'video' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/05.mp4',
                            'poster' => 'newassets/Amega Reel-Tiktok videos/CHONGQING/728950843_10163261303851819_2059507783333657850_n.jpg',
                            'title' => 'Chongqing Cyberpunk Cityscape'
                        ],
                    ];
                @endphp

                @foreach ($reels as $reel)
                    <div class="relative rounded-2xl overflow-hidden bg-black border border-gray-100 shadow-md group h-[400px] cursor-pointer"
                         onmouseenter="const v = this.querySelector('video'); if (v) { v.play().catch(()=>{}); }"
                         onmouseleave="const v = this.querySelector('video'); if (v) { v.pause(); }">
                        <!-- Top Title Badge -->
                        <div class="absolute top-3 left-3 right-3 z-10 flex items-center justify-between pointer-events-none transition-opacity duration-300">
                            <span class="px-3 py-1 bg-navy/90 backdrop-blur-md text-white text-xs font-bold rounded-full border border-white/20 shadow-md">
                                {{ $reel['title'] }}
                            </span>
                        </div>

                        <!-- Video Player -->
                        <video controls muted loop playsinline preload="metadata" poster="{{ \App\Support\WebImage::url($reel['poster'], \App\Support\WebImage::MARQUEE) }}" class="w-full h-full object-cover">
                            <source src="{{ asset($reel['video']) }}" type="video/mp4">
                            Your browser does not support HTML5 video.
                        </video>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $items = $galleryItems ?? \App\Models\GalleryItem::orderBy('order', 'asc')->get();
        @endphp

        {{-- The travellers' photos pegged to a sagging string: drag, click a print, use the arrows or ←/→.
             Driven by resources/js/polaroid-carousel.js; a photo loads only when its print nears the middle. --}}
        @if ($items->isNotEmpty())
            <div class="animate-on-scroll">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-1.5 h-7 bg-accent rounded-full"></div>
                    <h3 class="font-heading font-bold text-2xl text-dark">Traveller Moments</h3>
                </div>
                <div data-polaroid-carousel data-autoplay="3000" data-glide="3500" data-card-width="260" data-sag="46"
                     class="pl-root h-[480px] sm:h-[560px]" tabindex="0"
                     role="region" aria-roledescription="carousel" aria-label="Photos of AMEGA travellers">
                    <svg class="pl-string" aria-hidden="true"><path fill="none" stroke="#8a7f72" stroke-width="1.5" stroke-linecap="round" /></svg>
                    @foreach ($items as $item)
                        <div class="pl-card" data-on="0" aria-hidden="true"
                             data-title="{{ $item->title }}" data-caption="{{ $item->category ? ucwords(str_replace(['_', '-'], ' ', $item->category)) : '' }}"
                             data-full="{{ \App\Support\WebImage::url($item->image, \App\Support\WebImage::WIDE) }}">
                            <div class="pl-print">
                                <div class="pl-shot"><img data-src="{{ \App\Support\WebImage::url($item->image) }}" alt="{{ $item->title }}" decoding="async" draggable="false"></div>
                                <div class="pl-note">{{ $item->title }}</div>
                            </div>
                            <div class="pl-peg"></div>
                        </div>
                    @endforeach
                    <div class="pl-bar">
                        <div class="pl-cap" aria-hidden="true">
                            <span class="pl-title" data-pl-title></span>
                            <span class="pl-sub" data-pl-caption></span>
                        </div>
                        <span class="pl-count" aria-hidden="true"><b data-pl-index>01</b> / {{ str_pad((string) $items->count(), 2, '0', STR_PAD_LEFT) }}</span>
                        <button type="button" class="pl-btn" data-pl-prev aria-label="Previous photo">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M10 3 5 8l5 5" stroke="currentColor" stroke-width="1.4" /></svg>
                        </button>
                        <button type="button" class="pl-btn" data-pl-next aria-label="Next photo">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 3 5 5-5 5" stroke="currentColor" stroke-width="1.4" /></svg>
                        </button>
                    </div>
                    <div class="pl-sr" aria-live="polite" data-pl-live></div>
                </div>
                <p class="mt-2 text-center text-xs text-dark/50">Drag the line or use the arrows · click the photo in the middle to see it full size</p>
            </div>
        @endif
    </div>
</section>