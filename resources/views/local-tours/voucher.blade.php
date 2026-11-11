{{-- The AMEGA booking voucher/order for a local tour, to print and release to the client. Only reachable
     once the booking is approved and paid in full (LocalTourBooking::voucherBlocker). --}}
@use('App\Support\LocalTours')
@php
    $info = $booking->tourInfo();
    $pickupTime = $booking->pickup_time ? \Illuminate\Support\Carbon::parse($booking->pickup_time)->format('g:i A') : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Booking Voucher {{ $booking->reference }} - {{ $booking->lead_guest_name }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #111; margin: 0; background: #f1f5f9; }
        main { max-width: 190mm; margin: 0 auto; background: #fff; padding: 12mm; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8mm; border-bottom: 2px solid #003B95; padding-bottom: 4mm; }
        .head img { height: 14mm; width: auto; }
        .head h1 { font-size: 15pt; margin: 0; color: #003B95; text-align: right; }
        .head p { margin: 1mm 0 0; font-size: 9.5pt; text-align: right; }
        h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: .04em; color: #003B95; margin: 6mm 0 2mm; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 2mm 2.5mm; text-align: left; vertical-align: top; font-size: 10pt; }
        th { width: 34%; background: #f1f5f9; font-weight: bold; }
        ul { margin: 0; padding-left: 5mm; }
        li { margin-bottom: 1mm; font-size: 9.5pt; }
        .sign { display: flex; justify-content: space-between; gap: 10mm; margin-top: 14mm; font-size: 9.5pt; }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: 1.5mm; text-align: center; }
        .footer { margin-top: 8mm; padding-top: 3mm; border-top: 1px solid #cbd5e1; text-align: center; font-size: 8.5pt; color: #334155; line-height: 1.5; }
        .print { position: fixed; top: 8px; right: 8px; padding: 8px 14px; font-size: 14px; cursor: pointer; }
        @media print { body { background: #fff; } main { padding: 0; max-width: none; } .print { display: none; } }
    </style>
</head>
<body>
<button class="print" onclick="window.print()">Print</button>
<main>
    <div class="head">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel and Tours Services">
        <div>
            <h1>Booking Voucher</h1>
            <p><strong>{{ $booking->reference }}</strong></p>
            <p>Issued {{ ($booking->voucher_released_at ?? now())->format('F j, Y') }}</p>
        </div>
    </div>

    <h2>Tour</h2>
    <table>
        <tr><th>Tour</th><td>{{ $booking->tourLabel() }}</td></tr>
        <tr><th>Travel date</th><td>{{ $booking->travel_date?->format('l, F j, Y') ?? '—' }}</td></tr>
        <tr><th>{{ $booking->tour === 'angeles_city' ? 'Tour guide' : 'Operator' }}</th><td>{{ $info['accreditation'] ?: $info['supplier'] }}</td></tr>
        <tr><th>Operator confirmation no.</th><td>{{ $booking->supplier_confirmation ?: '—' }}</td></tr>
        @if ($booking->attractions)
            <tr><th>Places to visit</th><td>{!! nl2br(e($booking->attractions)) !!}</td></tr>
        @endif
    </table>

    <h2>Guests</h2>
    <table>
        <tr><th>Lead guest</th><td>{{ $booking->lead_guest_name }}</td></tr>
        <tr><th>Number of guests</th><td>{{ $booking->guestsLabel() }}{{ $booking->children_ages ? ' (ages '.$booking->children_ages.')' : '' }}</td></tr>
        @if ($booking->nationality)
            <tr><th>Nationality</th><td>{{ $booking->nationality }}</td></tr>
        @endif
        <tr><th>Contact number</th><td>{{ $booking->contact_phone ?: '—' }}</td></tr>
        @if ($booking->special_requests)
            <tr><th>Special requests</th><td>{!! nl2br(e($booking->special_requests)) !!}</td></tr>
        @endif
    </table>

    <h2>Transportation</h2>
    <table>
        <tr><th>Arrangement</th><td>{{ LocalTours::TRANSPORT[$booking->transport] ?? $booking->transport }}</td></tr>
        <tr><th>Pick-up</th><td>{{ collect([$booking->pickup_location, $pickupTime])->filter()->implode(' · ') ?: '—' }}</td></tr>
        <tr><th>Drop-off</th><td>{{ $booking->dropoff_location ?: '—' }}</td></tr>
        @if ($booking->usesAmegaTransport() && $booking->driver)
            <tr><th>Driver</th><td>{{ LocalTours::driverName($booking->driver) }} · {{ LocalTours::driverPhone($booking->driver) }}{{ $booking->vehicle ? ' · '.$booking->vehicle : '' }}</td></tr>
        @endif
    </table>

    <h2>Payment</h2>
    <table>
        <tr><th>Total</th><td>₱{{ number_format((float) $booking->total_amount, 2) }}</td></tr>
        <tr><th>Status</th><td>Paid in full{{ $booking->payment_in_full_at ? ' on '.$booking->payment_in_full_at->format('F j, Y') : '' }}</td></tr>
    </table>

    <h2>Reminders</h2>
    <ul>
        <li>Please bring this voucher and the valid IDs of all guests on the day of the tour.</li>
        <li>Be at the pick-up or meeting point at least 15 minutes before the time stated.</li>
        <li>We will inform you at once of any change from the operator.</li>
        <li>For emergencies or changes on the day, call AMEGA at +63 992 922 5733.</li>
    </ul>

    <div class="sign">
        <div>{{ $booking->creator?->name ?? auth()->user()?->name }}<br>Prepared by, Amega Travel and Tours Services</div>
        <div>{{ $booking->lead_guest_name }}<br>Received by</div>
    </div>

    <footer class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703<br>
        sales@amegatravelandtour.com<br>
        www.amegatravelandtour.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </footer>
</main>
</body>
</html>
