<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-navy-900">
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
{{-- Brand-navy page with a single white sign-in card centred on it. The h1
     leaves off `font-heading`: app.css forces weight 900 on h1.font-heading. --}}
<body class="min-h-full font-body text-slate-900 antialiased selection:bg-accent selection:text-slate-900 bg-gradient-to-br from-navy-700 via-navy-800 to-navy-950 flex flex-col relative overflow-x-hidden">

    <!-- Soft light from the top, and a faint grid for texture -->
    <div class="fixed inset-0 bg-[radial-gradient(circle_700px_at_50%_-120px,rgba(255,255,255,0.14),transparent)] pointer-events-none" aria-hidden="true"></div>
    <div class="fixed inset-0 opacity-[0.05] bg-[linear-gradient(to_right,#ffffff_1px,transparent_1px),linear-gradient(to_bottom,#ffffff_1px,transparent_1px)] bg-[size:2.5rem_2.5rem] pointer-events-none" aria-hidden="true"></div>

    <header class="relative z-10 w-full px-4 sm:px-10 lg:px-12 py-5 sm:py-6 flex items-center justify-between gap-4">
        <a href="{{ route('home') }}" class="inline-flex items-center rounded-lg transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 focus-visible:ring-offset-navy-800">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED WHITE.png') }}"
                 alt="Amega Travel and Tours Services"
                 class="h-9 sm:h-11 w-auto object-contain">
        </a>

        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 min-h-11 px-1 rounded-lg text-sm font-semibold text-white/80 hover:text-white transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent">
            <span>Return to website</span>
            <i data-lucide="arrow-right" class="w-4 h-4 transition-transform motion-safe:group-hover:translate-x-0.5"></i>
        </a>
    </header>

    <main class="relative z-10 flex-1 flex items-center justify-center w-full px-4 py-8">
        <div class="w-full max-w-[420px]"
             x-data="{
                 showPassword: false,
                 capsLock: false,
                 isSubmitting: false,
                 checkCapsLock(e) {
                     this.capsLock = e.getModifierState && e.getModifierState('CapsLock');
                 }
             }">

            <div class="bg-white rounded-2xl shadow-2xl shadow-navy-950/40 p-6 sm:p-8">
                <div class="flex items-center justify-center w-12 h-12 mx-auto rounded-xl bg-navy-50 text-navy-700">
                    <i data-lucide="lock-keyhole" class="w-6 h-6"></i>
                </div>

                <div class="text-center mt-4 mb-6">
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Admin sign in</h1>
                    <p class="text-sm text-slate-600 mt-1.5">Enter your administrative credentials to continue.</p>
                </div>

                @if (session('error'))
                    <div class="mb-5 p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-start gap-2.5" role="alert">
                        <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 shrink-0 mt-0.5"></i>
                        <div class="font-medium leading-relaxed">{{ session('error') }}</div>
                    </div>
                @endif

                @if (session('status') || session('success'))
                    <div class="mb-5 p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-start gap-2.5" role="status">
                        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                        <div class="font-medium leading-relaxed">{{ session('status') ?? session('success') }}</div>
                    </div>
                @endif

                @if (session('info'))
                    <div class="mb-5 p-3.5 rounded-lg bg-navy-50 border border-navy-100 text-navy-800 text-sm flex items-start gap-2.5" role="status">
                        <i data-lucide="info" class="w-4 h-4 text-navy-600 shrink-0 mt-0.5"></i>
                        <div class="font-medium leading-relaxed">{{ session('info') }}</div>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('admin.login') }}"
                      @submit="isSubmitting = true"
                      class="space-y-4">
                    @csrf

                    <div>
                        <label for="admin_email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email address</label>
                        <input id="admin_email"
                               type="email"
                               name="email"
                               value="{{ old('email') }}"
                               required
                               autofocus
                               autocomplete="email"
                               placeholder="admin@amegatravel.com"
                               @error('email') aria-invalid="true" aria-describedby="admin_email_error" @enderror
                               class="w-full h-11 px-3.5 rounded-lg bg-white border text-slate-900 placeholder-slate-400 text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-navy-500/30 focus:border-navy-600 transition-colors {{ $errors->has('email') ? 'border-rose-400' : 'border-slate-300' }}">
                        @error('email')
                            <p id="admin_email_error" class="mt-1.5 text-sm text-rose-700 flex items-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <div>
                        <label for="admin_password" class="block text-sm font-semibold text-slate-700 mb-1.5">Password</label>
                        <div class="relative">
                            <input id="admin_password"
                                   :type="showPassword ? 'text' : 'password'"
                                   type="password"
                                   name="password"
                                   required
                                   autocomplete="current-password"
                                   placeholder="Enter your password"
                                   @keydown="checkCapsLock($event)"
                                   @keyup="checkCapsLock($event)"
                                   @error('password') aria-invalid="true" aria-describedby="admin_password_error" @enderror
                                   class="w-full h-11 pl-3.5 pr-12 rounded-lg bg-white border text-slate-900 placeholder-slate-400 text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-navy-500/30 focus:border-navy-600 transition-colors {{ $errors->has('password') ? 'border-rose-400' : 'border-slate-300' }}">

                            <button type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 w-11 flex items-center justify-center rounded-r-lg text-slate-500 hover:text-navy-700 transition-colors cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500"
                                    :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                    aria-label="Show password">
                                <i data-lucide="eye" class="w-4 h-4" x-show="!showPassword"></i>
                                <i data-lucide="eye-off" class="w-4 h-4" x-show="showPassword" style="display: none;"></i>
                            </button>
                        </div>

                        <div x-show="capsLock"
                             class="mt-1.5 px-2.5 py-1.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-center gap-1.5"
                             role="status"
                             style="display: none;">
                            <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                            <span>Caps Lock is on</span>
                        </div>

                        @error('password')
                            <p id="admin_password_error" class="mt-1.5 text-sm text-rose-700 flex items-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <label class="inline-flex items-center gap-2.5 min-h-11 cursor-pointer select-none">
                        <input type="checkbox"
                               name="remember"
                               class="w-4 h-4 rounded border-slate-300 text-navy-700 focus:ring-navy-500/40 focus:ring-offset-0 cursor-pointer">
                        <span class="text-sm text-slate-700">Remember me on this device</span>
                    </label>

                    <button type="submit"
                            :disabled="isSubmitting"
                            class="w-full h-11 px-4 rounded-lg bg-navy-700 hover:bg-navy-800 text-white font-heading font-semibold text-sm transition-colors duration-150 shadow-sm flex items-center justify-center gap-2 cursor-pointer disabled:opacity-70 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy-500 focus-visible:ring-offset-2">
                        <span x-show="!isSubmitting" class="flex items-center gap-2">
                            <span>Sign in</span>
                            <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </span>
                        <span x-show="isSubmitting" class="flex items-center gap-2" style="display: none;">
                            <svg class="motion-safe:animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Signing in...</span>
                        </span>
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-white/80 flex items-center justify-center gap-1.5">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                <span>Authorized operations access only</span>
            </p>
        </div>
    </main>

    <footer class="relative z-10 w-full px-4 py-6 text-center text-xs text-white/70">
        &copy; {{ date('Y') }} Amega Travel and Tours Services. All rights reserved.
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
        });
    </script>
</body>
</html>
