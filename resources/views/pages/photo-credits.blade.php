@extends('layouts.app')

@section('title', 'Photo Credits — AMEGA Travel and Tours Services')

@section('content')
    <section class="pt-32 pb-20 sm:pt-36 sm:pb-24 section-gradient-light">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-14">
                <span class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary font-subheading font-bold text-xs tracking-widest uppercase mb-3 border border-primary/20">
                    Attribution
                </span>
                <h1 class="font-heading font-black text-4xl sm:text-5xl text-dark mt-2 tracking-tight">Photo Credits</h1>
                <p class="font-body text-dark/70 text-base sm:text-lg mt-4 leading-relaxed">
                    The destination photographs on our home page come from Wikimedia Commons and are used under the
                    licences below. Our tour flyers, gallery photos and brand imagery are our own.
                </p>
            </div>

            @if (empty($photos))
                <p class="text-center text-dark/60">No credited photographs are currently in use.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($photos as $photo)
                        <li class="flex items-center gap-4 sm:gap-6 bg-white rounded-2xl border border-gray-200/80 shadow-sm p-4 sm:p-5">
                            <img
                                src="{{ asset($photo['path']) }}"
                                alt="{{ $photo['place'] }}"
                                loading="lazy"
                                class="w-24 h-16 sm:w-32 sm:h-20 rounded-xl object-cover shrink-0"
                            >
                            <div class="min-w-0">
                                <p class="font-heading font-bold text-dark text-base sm:text-lg">{{ $photo['place'] }}</p>
                                <p class="font-body text-sm text-dark/70 mt-0.5">
                                    by {{ $photo['author'] }}
                                </p>
                                <p class="font-body text-xs text-dark/50 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                                    @if ($photo['licence_url'])
                                        <a href="{{ $photo['licence_url'] }}" rel="license noopener" target="_blank" class="text-primary hover:underline">{{ $photo['license'] }}</a>
                                    @else
                                        <span>{{ $photo['license'] }}</span>
                                    @endif
                                    @if ($photo['source'])
                                        <a href="{{ $photo['source'] }}" rel="noopener" target="_blank" class="hover:underline">Source</a>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endsection
