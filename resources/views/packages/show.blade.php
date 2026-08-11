@extends('layouts.app')

@section('title', $package->title . ' - AMEGA Travel & Tours')

@section('content')
<!-- Hero Package Header -->
<section class="relative pt-32 pb-16 bg-navy text-white overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/40 via-navy to-navy"></div>
    <div class="section-dots opacity-10"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs text-white/60 mb-6 font-medium">
            <a href="{{ route('home') }}" class="hover:text-accent">Home</a>
            <span>/</span>
            <a href="{{ route('packages.index') }}" class="hover:text-accent">Packages</a>
            <span>/</span>
            <span class="text-white font-bold truncate max-w-xs">{{ $package->title }}</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            <div class="lg:col-span-2 space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-3.5 py-1 rounded-full bg-accent text-dark text-xs font-extrabold uppercase tracking-wider">
                        {{ str_replace('_', ' ', $package->category) }}
                    </span>
                    <span class="px-3.5 py-1 rounded-full bg-white/10 backdrop-blur-md text-white text-xs font-semibold border border-white/20">
                        {{ $package->duration }}
                    </span>
                    @if($package->available_dates)
                        <span class="px-3.5 py-1 rounded-full bg-white/10 backdrop-blur-md text-accent text-xs font-medium border border-white/20">
                            🗓️ {{ $package->available_dates }}
                        </span>
                    @endif
                </div>

                <h1 class="font-heading text-3xl sm:text-4xl lg:text-5xl font-bold text-white tracking-tight">
                    {{ $package->title }}
                </h1>

                <div class="flex items-center gap-4 text-xs">
                    <div class="flex items-center gap-1">
                        @for ($i = 0; $i < 5; $i++)
                            <svg class="w-4 h-4 {{ $i < $package->rating ? 'text-accent' : 'text-gray-400' }}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                        <span class="ml-1 text-white/80 font-bold">5.0 Star Rating</span>
                    </div>
                </div>
            </div>

            <!-- Price Highlight Badge -->
            <div class="bg-white/10 backdrop-blur-xl p-6 rounded-3xl border border-white/20 text-right space-y-1">
                <span class="text-xs uppercase font-bold tracking-widest text-white/60 block">Starting All-In Rate</span>
                <div class="font-heading text-4xl font-extrabold text-accent">{{ $package->price }}</div>
                <span class="text-[11px] text-white/50 block">Per adult passenger / Twin Sharing</span>
            </div>
        </div>
    </div>
</section>

<!-- Package Details & Booking Grid -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Left 2 Columns: Image & Tabs Content -->
            <div class="lg:col-span-2 space-y-10">
                
                <!-- Main Image Preview -->
                <div onclick="previewImage('{{ asset($package->image) }}', '{{ addslashes($package->title) }}')" class="relative h-96 sm:h-[450px] rounded-3xl overflow-hidden shadow-xl cursor-pointer group border border-gray-100">
                    <img src="{{ asset($package->image) }}" alt="{{ $package->title }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center">
                        <span class="px-4 py-2 rounded-full bg-accent text-dark font-bold text-xs shadow-lg flex items-center gap-2">
                            <i data-lucide="zoom-in" class="w-4 h-4"></i>
                            View Full Resolution Poster
                        </span>
                    </div>
                </div>

                <!-- Package Overview -->
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-4">
                    <h3 class="font-heading text-2xl font-bold text-dark flex items-center gap-2">
                        <i data-lucide="info" class="w-6 h-6 text-primary"></i>
                        <span>Package Overview</span>
                    </h3>
                    <p class="text-dark/70 leading-relaxed text-sm sm:text-base font-normal">
                        {{ $package->description }}
                    </p>
                </div>

                <!-- Inclusions & Exclusions Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <!-- Inclusions -->
                    <div class="bg-emerald-50/50 rounded-3xl p-6 sm:p-7 border border-emerald-100 space-y-4">
                        <h4 class="font-heading text-lg font-bold text-emerald-900 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-emerald-500 text-white flex items-center justify-center text-xs">✓</span>
                            <span>Package Inclusions</span>
                        </h4>
                        <ul class="space-y-2 text-xs sm:text-sm text-emerald-900/80 font-medium">
                            @if($package->inclusions)
                                @foreach(explode("\n", $package->inclusions) as $inc)
                                    @if(trim($inc))
                                        <li class="flex items-start gap-2">
                                            <span class="text-emerald-600 font-bold">•</span>
                                            <span>{{ ltrim(trim($inc), '•- ') }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            @else
                                <li>• Roundtrip Economy Airfare & Taxes</li>
                                <li>• Hotel accommodation with breakfast</li>
                                <li>• Transfers & guided sightseeing</li>
                            @endif
                        </ul>
                    </div>

                    <!-- Exclusions -->
                    <div class="bg-rose-50/50 rounded-3xl p-6 sm:p-7 border border-rose-100 space-y-4">
                        <h4 class="font-heading text-lg font-bold text-rose-900 flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full bg-rose-500 text-white flex items-center justify-center text-xs">✕</span>
                            <span>Package Exclusions</span>
                        </h4>
                        <ul class="space-y-2 text-xs sm:text-sm text-rose-900/80 font-medium">
                            @if($package->exclusions)
                                @foreach(explode("\n", $package->exclusions) as $exc)
                                    @if(trim($exc))
                                        <li class="flex items-start gap-2">
                                            <span class="text-rose-500 font-bold">•</span>
                                            <span>{{ ltrim(trim($exc), '•- ') }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            @else
                                <li>• Personal expenses & optional tours</li>
                                <li>• Philippine Travel Tax (₱1,620)</li>
                            @endif
                        </ul>
                    </div>
                </div>

                <!-- Itinerary Timeline -->
                @if($package->itinerary)
                    <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm space-y-6">
                        <h3 class="font-heading text-2xl font-bold text-dark flex items-center gap-2">
                            <i data-lucide="map" class="w-6 h-6 text-primary"></i>
                            <span>Day-by-Day Travel Itinerary</span>
                        </h3>

                        <div class="space-y-4 border-l-2 border-primary/20 ml-3 pl-6">
                            @foreach(explode("\n", $package->itinerary) as $index => $day)
                                @if(trim($day))
                                    <div class="relative group">
                                        <div class="absolute -left-[31px] top-1 w-4 h-4 rounded-full bg-primary border-4 border-white shadow-sm"></div>
                                        <p class="text-dark/80 text-sm font-medium leading-relaxed">
                                            {{ trim($day) }}
                                        </p>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right 1 Column: Sticky Booking & Inquiry Form -->
            <div class="lg:sticky lg:top-28 space-y-6">
                <div class="bg-white rounded-3xl p-7 border border-gray-100 shadow-xl space-y-5">
                    <div class="text-center border-b border-gray-100 pb-5">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Instant Booking Request</span>
                        <h3 class="font-heading text-2xl font-bold text-dark mt-1">Book This Tour</h3>
                        <p class="text-xs text-dark/50 mt-1">Submit your details for official confirmation</p>
                    </div>

                    @if(session('success'))
                        <div class="p-4 rounded-2xl bg-emerald-50 text-emerald-800 text-xs font-bold border border-emerald-200">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('bookings.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="travel_package_id" value="{{ $package->id }}">

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Your Full Name</label>
                            <input type="text" name="customer_name" value="{{ Auth::user()->name ?? old('customer_name') }}" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="Full Name">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Email Address</label>
                            <input type="email" name="customer_email" value="{{ Auth::user()->email ?? old('customer_email') }}" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="you@example.com">
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Phone Number</label>
                            <input type="text" name="customer_phone" value="{{ Auth::user()->phone ?? old('customer_phone') }}" required
                                   class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                   placeholder="+63 917 123 4567">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Travel Date</label>
                                <input type="date" name="travel_date" value="{{ old('travel_date', now()->addDays(14)->format('Y-m-d')) }}" min="{{ date('Y-m-d') }}" required
                                       class="w-full px-3 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Passengers</label>
                                <input type="number" name="number_of_passengers" value="{{ old('number_of_passengers', 2) }}" min="1" max="50" required
                                       class="w-full px-3 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-1">Special Requests / Notes</label>
                            <textarea name="special_requests" rows="2"
                                      class="w-full px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-xs focus:outline-none focus:ring-2 focus:ring-primary"
                                      placeholder="Dietary requirements, room preferences, flight add-ons...">{{ old('special_requests') }}</textarea>
                        </div>

                        <button type="submit" class="w-full py-4 px-6 rounded-full bg-accent text-dark font-heading font-extrabold text-sm hover:bg-accent-dark transition-all duration-300 shadow-lg flex items-center justify-center gap-2">
                            <span>Confirm & Request Booking</span>
                            <i data-lucide="send" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
