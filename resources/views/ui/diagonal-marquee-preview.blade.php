<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Diagonal Marquee — Component Preview</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white">
    {{-- Default deck (the component's own fallback images) --}}
    <x-diagonal-marquee>
        <div class="relative z-20 text-center px-6">
            <h1 class="font-heading text-5xl sm:text-7xl font-black text-primary drop-shadow-sm">AMEGA</h1>
            <p class="font-subheading mt-3 text-sm tracking-widest uppercase text-dark/70">Diagonal Marquee — default deck</p>
        </div>
    </x-diagonal-marquee>

    {{-- Fed from the database, shorter, faster, opposite tilt --}}
    <x-diagonal-marquee
        :cards="$destinations"
        :angle="18"
        :base-speed="70"
        height="h-[70vh]"
        card-class="h-[220px] w-[300px]"
        fade-from="from-primary"
        class="bg-primary"
    >
        <div class="relative z-20 text-center px-6">
            <h2 class="font-heading text-4xl sm:text-5xl font-black text-white drop-shadow">Destinations from the database</h2>
            <p class="font-subheading mt-3 text-sm tracking-widest uppercase text-white/70">:cards="$destinations" · angle 18° · 70s</p>
        </div>
    </x-diagonal-marquee>
</body>
</html>
