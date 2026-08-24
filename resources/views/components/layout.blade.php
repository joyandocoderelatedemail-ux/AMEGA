@props(['title' => 'Amega Travel and Tours Services - Your Trusted Travel Agency', 'metaDescription' => 'Amega Travel and Tours Services - Discover Local and International Destinations. We make every journey simple, memorable, and worry-free.'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="theme-color" content="#003B95">

    <title>{{ $title }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,400;1,700&family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body text-dark bg-white">

    @include('partials.nav')

    <main>
        {{ $slot }}
    </main>

    @include('partials.footer')

    <button id="back-to-top" aria-label="Back to top" class="fixed bottom-8 right-8 z-50 w-12 h-12 bg-primary text-white rounded-full shadow-lg flex items-center justify-center opacity-0 invisible transition-all duration-300 hover:bg-primary-dark hover:-translate-y-1 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
        <i data-lucide="arrow-up" class="w-5 h-5"></i>
    </button>

    <!-- Global Image Lightbox Modal -->
    <div x-data="{ open: false, imgSrc: '', imgTitle: '' }"
         @open-image-modal.window="open = true; imgSrc = $event.detail.src; imgTitle = $event.detail.title || '';"
         @keydown.escape.window="open = false"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
         style="display: none;">
        
        <!-- Backdrop Click to Close -->
        <div class="absolute inset-0" @click="open = false"></div>

        <!-- Modal Container -->
        <div class="relative max-w-5xl w-full max-h-[90vh] flex flex-col items-center justify-center z-10 p-2">
            <!-- Close Button -->
            <button @click="open = false" class="absolute -top-12 right-2 text-white/80 hover:text-white bg-white/20 hover:bg-white/30 p-2.5 rounded-full backdrop-blur-md transition-all shadow-lg focus:outline-none" aria-label="Close modal">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <!-- Image Card -->
            <div class="relative rounded-2xl overflow-hidden shadow-2xl border border-white/20 bg-navy/90 max-h-[82vh] flex flex-col items-center">
                <img :src="imgSrc" :alt="imgTitle" class="w-auto h-auto max-h-[75vh] max-w-full object-contain mx-auto">
                <div x-show="imgTitle" class="w-full p-4 bg-navy text-center border-t border-white/10">
                    <h4 x-text="imgTitle" class="text-white font-heading font-bold text-base sm:text-lg"></h4>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewImage(src, title = '') {
            window.dispatchEvent(new CustomEvent('open-image-modal', {
                detail: { src, title }
            }));
        }
    </script>

    {{ $scripts ?? '' }}
</body>
</html>
