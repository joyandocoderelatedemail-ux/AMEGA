@extends('layouts.app')

@section('title', 'Administrator Portal Login - Amega Travel and Tours Services')

@section('content')
<section class="min-h-screen pt-28 pb-16 flex items-center justify-center bg-navy relative overflow-hidden text-white">
    <!-- Background Accents -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-primary/30 via-navy to-navy"></div>
    <div class="section-dots opacity-10"></div>
    
    <div class="max-w-md w-full mx-auto px-4 sm:px-6 relative z-10">
        
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block mb-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" alt="Amega Travel and Tours Services" class="h-14 w-auto mx-auto object-contain">
            </a>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-accent/20 text-accent text-xs font-bold uppercase tracking-widest mb-3 border border-accent/30">
                <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                Management Portal Access
            </div>
            <h1 class="font-heading text-3xl font-bold text-white">Administrator Login</h1>
            <p class="text-white/60 text-sm mt-1">Authorized personnel authentication</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white/10 backdrop-blur-xl rounded-3xl p-8 shadow-2xl border border-white/20 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-accent via-primary to-accent"></div>

            @if (session('error'))
                <div class="mb-4 p-4 rounded-2xl bg-rose-500/20 text-rose-200 text-xs font-semibold border border-rose-500/30">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="admin_email" class="block text-xs font-bold uppercase tracking-wider text-white/80 mb-2">Admin Email</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-white/40">
                            <i data-lucide="user-check" class="w-4 h-4"></i>
                        </span>
                        <input id="admin_email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full pl-10 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all"
                               placeholder="admin@amegatravel.com">
                    </div>
                    @error('email')
                        <p class="mt-1 text-xs text-rose-300 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="admin_password" class="block text-xs font-bold uppercase tracking-wider text-white/80 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-white/40">
                            <i data-lucide="key-round" class="w-4 h-4"></i>
                        </span>
                        <input id="admin_password" type="password" name="password" required
                               class="w-full pl-10 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/40 text-sm focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent transition-all"
                               placeholder="••••••••">
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-rose-300 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded text-accent focus:ring-accent border-white/30 bg-white/10">
                        <span class="text-white/70 font-medium">Keep administrator session active</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3.5 px-6 rounded-full bg-accent text-dark font-heading font-extrabold text-sm hover:bg-accent-dark transition-all duration-300 shadow-xl flex items-center justify-center gap-2">
                    <i data-lucide="lock" class="w-4 h-4"></i>
                    <span>Authenticate Admin Access</span>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-white/10 text-center text-xs text-white/50">
                Authorized access only. All operations are logged.
            </div>
        </div>

        <div class="mt-6 text-center text-xs text-white/60">
            Return to <a href="{{ route('home') }}" class="text-accent font-semibold underline hover:text-white">Public Website</a>
        </div>
    </div>
</section>
@endsection
