<section id="gallery" class="py-20 sm:py-24 section-gradient-light relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-semibold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Travel Moments & Destinations
            </span>
            <h2 class="font-heading text-4xl sm:text-5xl font-bold text-dark mt-2">Travel Moments & Destinations</h2>
            <p class="text-dark/70 text-base sm:text-lg mt-4 max-w-3xl mx-auto font-normal leading-relaxed">
                A glimpse into the incredible experiences and beautiful moments captured with AMEGA travelers.
            </p>
        </div>

        <!-- Video Reels Showcase -->
        <div class="mb-14 animate-on-scroll">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-1 h-7 bg-accent rounded-full"></div>
                <h3 class="font-heading text-2xl font-bold text-dark">Featured Video Reels</h3>
                <span class="text-xs text-primary font-semibold tracking-widest uppercase px-3 py-1 rounded-full bg-primary/5 border border-primary/20">TikTok & Reels</span>
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
                        <video controls muted loop playsinline preload="metadata" poster="{{ asset($reel['poster']) }}" class="w-full h-full object-cover">
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

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-4 gap-3">
            @foreach ($items as $index => $item)
                <div onclick="previewImage('{{ asset($item->image) }}', '{{ addslashes($item->title) }}')" class="gallery-item relative rounded-xl overflow-hidden aspect-square cursor-pointer animate-on-scroll shadow-sm group/gal" style="transition-delay: {{ ($index % 4) * 0.08 }}s">
                    <img src="{{ asset($item->image) }}" alt="{{ $item->title }}" loading="lazy" class="w-full h-full object-cover">
                    <div class="gallery-overlay absolute inset-0 bg-gradient-to-t from-primary/90 via-primary/40 to-transparent opacity-0 transition-opacity duration-300 flex flex-col justify-between p-3.5 rounded-xl">
                        <div class="flex justify-end">
                            <span class="w-7 h-7 rounded-full bg-accent text-dark flex items-center justify-center text-xs shadow-md">🔍</span>
                        </div>
                        <span class="text-white font-heading font-semibold text-xs drop-shadow-sm">{{ $item->title }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>