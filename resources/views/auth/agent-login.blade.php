@extends('layouts.app')

@section('title', 'Travel Agent Portal Login - AMEGA Travel & Tours')

@section('content')
<section class="min-h-screen pt-28 pb-16 flex items-center justify-center section-gradient-cool relative overflow-hidden">
    <div class="section-dots"></div>
    <div class="max-w-md w-full mx-auto px-4 sm:px-6 relative z-10">
        
        <!-- Logo & Header -->
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block mb-4">
                <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel & Tours" class="h-14 w-auto mx-auto object-contain">
            </a>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-800 font-bold text-[11px] uppercase tracking-wider mb-2 border border-emerald-300">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Official Agent Portal
            </span>
            <h1 class="font-heading text-3xl font-bold text-dark mt-1">Travel Agent Login</h1>
            <p class="text-dark/60 text-sm mt-1">Sign in with your assigned Travel Agent credentials</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-3xl p-8 shadow-xl border border-gray-100 relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-emerald-500 via-accent to-primary"></div>

            @if (session('status'))
                <div class="mb-4 p-4 rounded-2xl bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 p-4 rounded-2xl bg-rose-50 text-rose-700 text-xs font-semibold border border-rose-200">
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('agent.login.submit') }}" class="space-y-5">
                @csrf

                <!-- Email Address -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-2">Agent Email Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-dark/40">
                            <i data-lucide="user-check" class="w-4 h-4 text-emerald-600"></i>
                        </span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full pl-10 pr-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="agent@amegatravel.com">
                    </div>
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-dark/70 mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-dark/40">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input id="password" type="password" name="password" required
                               class="w-full pl-10 pr-4 py-3 rounded-xl bg-gray-50 border border-gray-200 text-dark text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition-all"
                               placeholder="••••••••">
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-xs text-dark/70 font-medium">Keep me logged in</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>Access Agent Dashboard</span>
                </button>
            </form>
        </div>
    </div>
</section>
@endsection
