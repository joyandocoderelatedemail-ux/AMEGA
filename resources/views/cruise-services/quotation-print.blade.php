{{-- The cruise quotation, to print or save as PDF for the client: who it is for, the cruise, the
     day-by-day itinerary and the package cost. Opens with the print dialog after "Save and print". --}}
@php
    $days = collect($booking->itinerary ?? [])->filter(fn ($day) => filled($day['description'] ?? null) || filled($day['date'] ?? null))->values();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <title>Cruise Quotation {{ $booking->reference }} - {{ $booking->client_name }}</title>
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
        th { background: #f1f5f9; font-weight: bold; }
        .info th { width: 34%; }
        .days th { width: 30mm; }
        td.num { text-align: right; white-space: nowrap; width: 40mm; }
        tr.total th, tr.total td { font-weight: bold; background: #f1f5f9; }
        tr { break-inside: avoid; }
        .footer { margin-top: 8mm; padding-top: 3mm; border-top: 1px solid #cbd5e1; text-align: center; font-size: 8.5pt; color: #334155; line-height: 1.5; }
        .toolbar { position: fixed; top: 8px; right: 8px; display: flex; gap: 6px; }
        .toolbar a, .toolbar button { padding: 8px 14px; font: bold 13px Arial, sans-serif; cursor: pointer; border-radius: 6px; border: 1px solid #003B95; background: #fff; color: #003B95; text-decoration: none; }
        .toolbar button { background: #003B95; color: #fff; }
        @media print { body { background: #fff; } main { padding: 0; max-width: none; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="{{ route('cruise-services.bookings.show', ['booking' => $booking, 'step' => 'quotation']) }}">Back to the booking</a>
    <button type="button" onclick="window.print()">Print</button>
</div>
<main>
    <div class="head">
        <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel and Tours Services">
        <div>
            <h1>Cruise Quotation</h1>
            <p><strong>{{ $booking->reference }}</strong></p>
            <p>{{ ($booking->quotation_at ?? now())->format('F j, Y') }}</p>
        </div>
    </div>

    <h2>Prepared for</h2>
    <table class="info">
        <tr><th>Client</th><td>{{ $booking->client_name }}</td></tr>
        @if ($booking->contact_number)
            <tr><th>Contact number</th><td>{{ $booking->contact_number }}</td></tr>
        @endif
        <tr><th>Guests</th><td>{{ $booking->guestsLabel() ?? '—' }}</td></tr>
    </table>

    <h2>Cruise</h2>
    <table class="info">
        <tr><th>Destination</th><td>{{ $booking->destination ?: '—' }}</td></tr>
        @if ($booking->cruiseLabel())
            <tr><th>Cruise line and ship</th><td>{{ $booking->cruiseLabel() }}</td></tr>
        @endif
        <tr><th>Sailing</th><td>{{ $booking->departure_date?->format('l, F j, Y') ?? '—' }}{{ $booking->return_date ? ' to '.$booking->return_date->format('l, F j, Y') : '' }}</td></tr>
    </table>

    @if ($days->isNotEmpty())
        <h2>Day-by-day itinerary</h2>
        <table class="days">
            @foreach ($days as $i => $day)
                <tr>
                    <th>Day {{ $i + 1 }}@if (! empty($day['date']))<br><span style="font-weight: normal;">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, M j') }}</span>@endif</th>
                    <td>{!! nl2br(e($day['description'] ?? '')) !!}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>Package cost ({{ $booking->currency }})</h2>
    <table>
        @foreach ($booking->costLines() as $line => $value)
            <tr><td>{{ $line }}</td><td class="num">{{ $booking->money($value) }}</td></tr>
        @endforeach
        <tr class="total"><th>Total package cost</th><td class="num">{{ $booking->money($booking->total_amount) }}</td></tr>
    </table>

    <footer class="footer">
        Prepared by {{ $booking->quotationBy?->name ?? $booking->creator?->name ?? auth()->user()?->name }}, Amega Travel and Tours Services<br>
        Unit 1 Astrofield Bldg, Mitchell Avenue, Balibago, Angeles City, Pampanga<br>
        0992 922 5733 &nbsp;|&nbsp; 0991 103 2928 &nbsp;|&nbsp; info@amegatravelandtours.com<br>
        www.amegatravelandtour.com
    </footer>
</main>
@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
