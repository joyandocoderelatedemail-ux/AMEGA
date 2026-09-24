<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#080C14]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#003B95">
    <meta name="robots" content="noindex, nofollow">

    <title>Admin Login &bull; Amega Travel and Tours Services</title>

    <!-- Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Icons & Micro-Interactions -->
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-body text-slate-100 antialiased selection:bg-accent selection:text-slate-900 bg-[#080C14] flex flex-col justify-between min-h-screen relative overflow-x-hidden">

    <!-- Atmospheric Grounding: Subtle Radial Core Light & Structural Blueprint -->
    <div class="fixed inset-0 bg-[radial-gradient(circle_800px_at_50%_-100px,#003B9525,transparent)] pointer-events-none"></div>
    <div class="fixed inset-0 bg-[radial-gradient(circle_600px_at_50%_120%,#A9BD0010,transparent)] pointer-events-none"></div>
    <div class="fixed inset-0 opacity-[0.025] bg-[linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)] bg-[size:2.5rem_2.5rem] pointer-events-none"></div>

    <!-- Top Utility Bar: Logo in Upper Left, Return to Website in Upper Right -->
    <header class="relative z-10 w-full px-6 sm:px-10 lg:px-12 py-6 flex items-center justify-between">
        <!-- Logo Upper Left Corner -->
        <a href="{{ route('home') }}" class="inline-flex items-center group transition-opacity hover:opacity-90">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}" 
                 alt="Amega Travel and Tours Services" 
                 class="h-10 sm:h-11 w-auto object-contain drop-shadow-sm">
        </a>

        <!-- Return to Website Upper Right Corner -->
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-xs font-medium text-slate-400 hover:text-white transition-colors group">
            <span>Return to website</span>
            <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-slate-500 group-hover:text-white group-hover:translate-x-0.5 transition-all"></i>
        </a>
    </header>

    <!-- Main Authentication Viewport -->
    <main class="relative z-10 w-full max-w-[420px] mx-auto px-6 py-6 my-auto"
          x-data="{ 
              showPassword: false, 
              capsLock: false, 
              isSubmitting: false,
              checkCapsLock(e) {
                  this.capsLock = e.getModifierState && e.getModifierState('CapsLock');
              }
          }">

        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="font-heading text-2xl sm:text-3xl font-bold text-white tracking-tight">
                Sign in to your account
            </h1>
            <p class="text-slate-400 text-sm mt-1.5">
                Enter your administrative credentials to continue
            </p>
        </div>

        <!-- Flash Messaging -->
        @if (session('error'))
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-start gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                <div class="font-medium leading-relaxed">{{ session('error') }}</div>
            </div>
        @endif

        @if (session('status') || session('success'))
            <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-start gap-2.5">
                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                <div class="font-medium leading-relaxed">{{ session('status') ?? session('success') }}</div>
            </div>
        @endif

        @if (session('info'))
            <div class="mb-5 p-3.5 rounded-xl bg-sky-500/10 border border-sky-500/20 text-sky-300 text-xs flex items-start gap-2.5">
                <i data-lucide="info" class="w-4 h-4 text-sky-400 shrink-0 mt-0.5"></i>
                <div class="font-medium leading-relaxed">{{ session('info') }}</div>
            </div>
        @endif

        <!-- Card Surface: Charcoal Panel with Hairline Border -->
        <div class="bg-[#0E1526]/90 border border-slate-800 rounded-2xl p-7 shadow-2xl backdrop-blur-md relative overflow-hidden">
            
            <!-- Subtle Top Hairline Accent Light -->
            <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-primary-light/40 to-transparent"></div>

            <form method="POST" 
                  action="{{ route('admin.login') }}" 
                  @submit="isSubmitting = true" 
                  class="space-y-4">
                @csrf

                <!-- Email Input -->
                <div>
                    <label for="admin_email" class="block text-xs font-medium text-slate-300 mb-1.5">
                        Email address
                    </label>
                    <div class="relative">
                        <input id="admin_email" 
                               type="email" 
                               name="email" 
                               value="{{ old('email') }}" 
                               required 
                               autofocus
                               autocomplete="email"
                               placeholder="admin@amegatravel.com"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-[#080C14] border border-slate-700/80 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary-light transition-all">
                    </div>
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="admin_password" class="block text-xs font-medium text-slate-300">
                            Password
                        </label>
                    </div>
                    <div class="relative">
                        <input id="admin_password" 
                               :type="showPassword ? 'text' : 'password'" 
                               name="password" 
                               required 
                               autocomplete="current-password"
                               placeholder="••••••••••••"
                               @keydown="checkCapsLock($event)"
                               @keyup="checkCapsLock($event)"
                               class="w-full pl-3.5 pr-10 py-2.5 rounded-xl bg-[#080C14] border border-slate-700/80 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-primary/40 focus:border-primary-light transition-all">
                        
                        <!-- Toggle Password Visibility -->
                        <button type="button" 
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white transition-colors focus:outline-none"
                                aria-label="Toggle password visibility">
                            <i data-lucide="eye" class="w-4 h-4" x-show="!showPassword"></i>
                            <i data-lucide="eye-off" class="w-4 h-4" x-show="showPassword" style="display: none;"></i>
                        </button>
                    </div>

                    <!-- Caps Lock Active Indicator -->
                    <div x-show="capsLock" 
                         x-transition 
                         class="mt-1.5 px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs flex items-center gap-1.5" 
                         style="display: none;">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-400 shrink-0"></i>
                        <span>Caps Lock is on</span>
                    </div>

                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-400 flex items-center gap-1">
                            <i data-lucide="alert-circle" class="w-3.5 h-3.5 shrink-0"></i>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <!-- Remember Session Option -->
                <div class="pt-1">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" 
                               name="remember" 
                               class="w-4 h-4 rounded bg-[#080C14] border-slate-700 text-primary focus:ring-primary/40 focus:ring-offset-0 transition-colors">
                        <span class="text-xs text-slate-400 hover:text-slate-200 transition-colors">
                            Remember me on this device
                        </span>
                    </label>
                </div>

                <!-- Action Button: Purposeful Brand Blue CTA -->
                <div class="pt-2">
                    <button type="submit" 
                            :disabled="isSubmitting"
                            class="w-full py-2.5 px-4 rounded-xl bg-primary hover:bg-primary-light text-white font-heading font-semibold text-sm transition-all duration-150 shadow-md shadow-primary/20 hover:shadow-primary/30 active:scale-[0.99] flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed cursor-pointer">
                        <span x-show="!isSubmitting" class="flex items-center gap-2">
                            <span>Sign in</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </span>
                        <span x-show="isSubmitting" class="flex items-center gap-2" style="display: none;">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Signing in...</span>
                        </span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Guardrail -->
        <div class="mt-6 text-center text-xs text-slate-500 flex items-center justify-center gap-1.5">
            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-slate-400"></i>
            <span>Authorized operations access only</span>
        </div>

    </main>

    <!-- Grounded Footer -->
    <footer class="relative z-10 w-full px-6 py-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <!-- Initialize Lucide Icons -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
