@php
    /**
     * What search engines and link previews read: the page's address, the preview card shown when the
     * page is shared on Facebook or Messenger, the browser-tab icon, and (home page only) the business
     * details Google shows in its search and Maps panel. Pages set title, meta_description and,
     * optionally, og_image.
     */
    $seoTitle = trim($__env->yieldContent('title', 'AMEGA Travel and Tours Services | Visa, Immigration, Ticketing & Tours in Angeles City, Pampanga'));
    $seoDescription = trim($__env->yieldContent('meta_description', 'AMEGA Travel and Tours Services in Balibago, Angeles City, Pampanga: airline ticketing, local and international tour packages, visa assistance, BI immigration services, passporting and SRRV. Since 2001.'));
    $seoImage = trim($__env->yieldContent('og_image', asset('images/og-image.jpg')));
    $seoUrl = url()->current();
@endphp
<link rel="canonical" href="{{ $seoUrl }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="AMEGA Travel and Tours Services">
<meta property="og:locale" content="en_PH">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

@if (request()->routeIs('home'))
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            'name' => 'AMEGA Travel and Tours Services',
            'url' => url('/'),
            'logo' => asset('apple-touch-icon.png'),
            'image' => asset('images/og-image.jpg'),
            'description' => $seoDescription,
            'telephone' => '+639911032928',
            'foundingDate' => '2001',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Unit 1 & 2 Astrofield Bldg, Mitchell Avenue, Balibago',
                'addressLocality' => 'Angeles City',
                'addressRegion' => 'Pampanga',
                'addressCountry' => 'PH',
            ],
            'openingHoursSpecification' => [
                ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '08:30', 'closes' => '17:30'],
                ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Saturday', 'opens' => '08:30', 'closes' => '15:30'],
            ],
            'hasMap' => 'https://maps.app.goo.gl/EvDJo4FiAPTJU8rN8',
            'sameAs' => ['https://www.facebook.com/amegatravel'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endif
