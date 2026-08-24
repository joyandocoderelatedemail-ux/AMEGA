@extends('layouts.app')

@section('title', 'Travel Packages Directory - Amega Travel and Tours Services')

@section('content')
<!-- Hero Header -->
<section class="relative pt-32 pb-20 bg-navy text-white overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/30 via-navy to-navy"></div>
    <div class="section-dots opacity-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-accent/20 text-accent font-subheading font-bold text-xs tracking-widest uppercase mb-4 border border-accent/30">
            <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
            Explore the World with AMEGA
        </span>
        <h1 class="font-heading font-black text-4xl sm:text-5xl lg:text-6xl text-white tracking-tight">
            International &amp; Domestic Travel Packages
        </h1>
        <p class="font-body font-normal text-white/80 text-base sm:text-lg mt-4 max-w-3xl mx-auto leading-relaxed">
            All-inclusive tour packages tailored for unforgettable memories, premium hotels, guided sightseeing, and hassle-free travel.
        </p>

        <!-- Search & Filter Bar -->
        <form method="GET" action="{{ route('packages.index') }}" class="mt-10 max-w-4xl mx-auto bg-white/10 backdrop-blur-xl p-4 sm:p-5 rounded-3xl border border-white/20 shadow-2xl flex flex-col sm:flex-row gap-3">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-white/50">
                    <i data-lucide="search" class="w-5 h-5"></i>
                </span>
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search packages (e.g. Japan, Korea, Bali)..." 
                       class="w-full pl-11 pr-4 py-3 rounded-2xl bg-white/10 border border-white/20 text-white placeholder-white/50 text-sm focus:outline-none focus:ring-2 focus:ring-accent">
            </div>

            <select name="category" class="px-4 py-3 rounded-2xl bg-white/10 border border-white/20 text-white text-sm focus:outline-none focus:ring-2 focus:ring-accent">
                <option value="" class="text-dark">All Categories</option>
                <option value="short_haul" class="text-dark" {{ request('category') === 'short_haul' ? 'selected' : '' }}>Short Haul (Asia)</option>
                <option value="long_haul" class="text-dark" {{ request('category') === 'long_haul' ? 'selected' : '' }}>Long Haul (Europe/USA)</option>
                <option value="domestic" class="text-dark" {{ request('category') === 'domestic' ? 'selected' : '' }}>Domestic Islands</option>
            </select>

            <button type="submit" class="px-8 py-3 bg-[#005ADA] text-white font-heading font-extrabold text-sm rounded-2xl hover:bg-[#003B95] transition-all duration-300 shadow-lg shrink-0">
                Find Packages
            </button>
        </form>
    </div>
</section>

<!-- Packages Directory Grid -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        @if(request('search') || request('category'))
            <div class="mb-8 flex items-center justify-between">
                <p class="text-dark/70 text-sm font-semibold">
                    Showing results {{ request('search') ? 'for "' . request('search') . '"' : '' }}
                </p>
                <a href="{{ route('packages.index') }}" class="text-xs text-primary font-bold hover:underline">Clear Filters</a>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse ($packages as $index => $pkg)
                <div class="group card-lift rounded-3xl overflow-hidden bg-white border border-gray-100 shadow-md flex flex-col justify-between" style="transition-delay: {{ $index * 0.05 }}s">
                    <div>
                        <div class="relative h-60 img-zoom overflow-hidden">
                            <img src="{{ asset($pkg->image) }}" alt="{{ $pkg->title }}" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent"></div>
                            
                            <div class="absolute top-4 left-4 flex items-center gap-2">
                                <span class="text-xs font-bold tracking-widest uppercase px-3 py-1.5 rounded-full bg-accent text-dark shadow-sm">
                                    {{ str_replace('_', ' ', $pkg->category) }}
                                </span>
                            </div>

                            <div class="absolute bottom-4 left-4 right-4 flex items-center justify-between text-white">
                                <span class="bg-navy/80 backdrop-blur-md text-xs font-bold px-3 py-1.5 rounded-full border border-white/20">
                                    {{ $pkg->duration }}
                                </span>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="flex items-center gap-1 mb-2">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="w-4 h-4 {{ $i < $pkg->rating ? 'text-accent' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>

                            <h3 class="font-heading text-xl font-bold text-dark mb-2 group-hover:text-primary transition-colors">
                                <a href="{{ route('packages.show', $pkg) }}">{{ $pkg->title }}</a>
                            </h3>

                            <p class="text-dark/60 text-sm leading-relaxed mb-4 line-clamp-2">
                                {{ $pkg->description }}
                            </p>

                            @if($pkg->available_dates)
                                <div class="flex items-center gap-1.5 text-xs text-dark/50 font-medium mb-4">
                                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-primary"></i>
                                    <span>{{ $pkg->available_dates }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="p-6 pt-0">
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                            <div>
                                <span class="text-[10px] font-subheading font-bold text-brand-red-dark uppercase tracking-wider block">Starting Price</span>
                                <span class="font-heading text-xl font-black text-brand-red">{{ $pkg->price }}</span>
                            </div>
                            <a href="{{ route('packages.show', $pkg) }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 bg-[#005ADA] text-white text-xs font-bold rounded-full hover:bg-[#003B95] transition-all duration-300 shadow-md">
                                <span>View Details</span>
                                <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 text-dark/40 flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="search-x" class="w-8 h-8"></i>
                    </div>
                    <h3 class="font-heading text-xl font-bold text-dark">No travel packages found</h3>
                    <p class="text-dark/50 text-sm mt-1">Try adjusting your search criteria or filters.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $packages->links() }}
        </div>
    </div>
</section>
@endsection
