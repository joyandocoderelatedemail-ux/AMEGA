@php
    /**
     * The printed Ticketing quotation: the trip and its price, on AMEGA's letterhead, with how long the offer
     * holds and that the airfare is not guaranteed until ticketed. A quotation is not a booking, so it is never
     * titled a Booking Agreement (that is drawn up once the client agrees).
     *
     * @var \App\Models\TicketBooking $ticket
     */
    $peso = fn (float $amount): string => ($amount < 0 ? '-' : '').'₱'.number_format(abs($amount), 2);
    $label = fn (?string $value): string => ucwords(str_replace('_', ' ', (string) $value));

    // The price, line by line, as the voucher lists it.
    $fareLines = [];
    foreach ($ticket->fareTypeLabels() as $type => $typeLabel) {
        $row = $ticket->fare_breakdown[$type] ?? null;
        if ($row && (int) $row['qty'] > 0 && (float) $row['price'] > 0) {
            $fareLines[] = [$typeLabel.' airfare', (int) $row['qty'], (float) $row['price'], (float) $row['subtotal']];
        }
    }
    if ($fareLines === [] && (float) $ticket->estimated_fare > 0) {
        $fareLines[] = ['Airfare', 1, (float) $ticket->estimated_fare, (float) $ticket->estimated_fare];
    }
    foreach ([
        'Tour package'.($ticket->package_name ? ' ('.$ticket->package_name.')' : '') => $ticket->package_amount,
        'Taxes and surcharges' => $ticket->taxes_amount,
        'Visa assistance' => $ticket->visa_assistance_fee,
        'Travel insurance' => $ticket->insurance_fee,
        'Other charges' => $ticket->other_charges,
        'Amega service fee' => $ticket->service_fee,
        'Service tax' => $ticket->service_tax_amount,
        'Services and requests' => $ticket->extras_amount,
    ] as $name => $amount) {
        if ((float) $amount > 0) {
            $fareLines[] = [$name, 1, (float) $amount, (float) $amount];
        }
    }
    $discount = (float) $ticket->pwd_sc_discount;

    // Whatever the lines do not account for (a lump-sum price or an agreed adjustment), so they add up to the total.
    $rest = round((float) $ticket->total_amount + $discount - array_sum(array_column($fareLines, 3)), 2);
    if (abs($rest) >= 0.01) {
        $fareLines[] = [$rest > 0 ? 'Other items' : 'Adjustment', 1, $rest, $rest];
    }

    // Services and requests asked for, with what each costs (or that it is free).
    $extras = collect([...($ticket->selected_services ?? []), ...($ticket->special_requests_list ?? [])])
        ->mapWithKeys(fn ($key): array => [$label($key) => $ticket->extraPriceLabel($key)]);

    $party = collect(['adult' => (int) $ticket->adults_count, 'child' => (int) $ticket->children_count, 'infant' => (int) $ticket->infants_count])
        ->filter()->map(fn (int $count, string $type): string => $count.' '.\Illuminate\Support\Str::plural($type, $count))->implode(', ');
    $legs = collect($ticket->multiCityLegs());
    $time = fn (?string $value): string => $value ? \Carbon\Carbon::parse($value)->format('g:i A') : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quotation - {{ $ticket->booking_reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: "Open Sans", "Segoe UI", Arial, Helvetica, sans-serif; color: #1F2937; font-size: 9.5pt; line-height: 1.45; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; align-items: center; gap: 8px; font-family: Arial, Helvetica, sans-serif; }
        .toolbar a, .toolbar button { font-family: inherit; font-size: 12px; font-weight: bold; padding: 8px 14px; border-radius: 6px; border: 1px solid #003B95; background: #003B95; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar a.ghost { background: #fff; color: #003B95; }

        .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 12mm 16mm 10mm; background: #fff; box-shadow: 0 4px 24px rgba(0,0,0,.18); display: flex; flex-direction: column; }
        .content { flex: 1; }

        .letterhead { display: flex; align-items: center; gap: 12px; }
        .letterhead img { height: 44px; }
        .letterhead .tagline { font-size: 8.5pt; font-style: italic; color: #003B95; border-left: 1.5px solid #003B95; padding-left: 10px; }
        .letterhead .ref { margin-left: auto; font-size: 8pt; color: #6B7280; white-space: nowrap; }

        h1 { text-align: center; font-size: 17pt; letter-spacing: 3px; color: #003B95; margin: 8mm 0 6mm; }

        .meta { display: flex; justify-content: space-between; gap: 10mm; margin-bottom: 5mm; }
        .box-title { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #6B7280; letter-spacing: .5px; margin-bottom: 1mm; }
        .client-name { font-size: 12pt; font-weight: bold; color: #111827; }
        table.facts td { padding: .4mm 0; vertical-align: top; }
        table.facts td.k { color: #6B7280; padding-right: 5mm; white-space: nowrap; }
        table.facts td.v { font-weight: bold; }

        .section { margin-bottom: 5mm; }
        .section-title { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; color: #003B95; border-bottom: 1.5px solid #003B95; padding-bottom: 1mm; margin-bottom: 2mm; }
        table.trip { width: 100%; border-collapse: collapse; }
        table.trip td { padding: 1mm 0; vertical-align: top; }
        table.trip td.k { width: 34mm; color: #6B7280; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #003B95; color: #fff; font-size: 7.5pt; text-transform: uppercase; text-align: left; padding: 2mm 2.5mm; }
        table.items td { border-bottom: 1px solid #E5E7EB; padding: 2mm 2.5mm; vertical-align: top; }
        .num, table.items th.num { text-align: right; white-space: nowrap; }
        table.totals { width: 80mm; margin: 2mm 0 0 auto; border-collapse: collapse; }
        table.totals td { padding: 1.2mm 2.5mm; }
        table.totals tr.grand td { border-top: 1.5px solid #111827; font-size: 12pt; font-weight: bold; color: #003B95; }

        .validity { border: 1px solid #FCD34D; background: #FFFBEB; padding: 3mm; margin: 4mm 0; font-size: 8.5pt; }
        .validity strong { color: #92400E; }
        ol.terms { margin: 0; padding-left: 5mm; font-size: 8pt; color: #374151; }
        ol.terms li { margin-bottom: 1mm; }

        .signatures { display: flex; gap: 14mm; margin-top: 12mm; }
        .signatures div { flex: 1; text-align: center; }
        .signatures .line { border-bottom: 1px solid #111827; min-height: 8mm; font-weight: bold; text-transform: uppercase; padding-bottom: 1mm; }
        .signatures .caption { margin-top: 1.5mm; font-size: 8pt; }

        .footer { text-align: center; font-size: 7.5pt; color: #4B5563; border-top: 1px solid #E5E7EB; padding-top: 3mm; margin-top: 6mm; line-height: 1.5; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; min-height: 290mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ route('ticketing.tickets.show', $ticket) }}" class="ghost">&larr; Back to quotation</a>
    <button type="button" onclick="window.print()">Print quotation</button>
</div>

<div class="sheet">
    <div class="content">
        <div class="letterhead">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
            <span class="tagline">Endless Possibilities in Travel and Tourism</span>
            <span class="ref">Ticketing · {{ $ticket->booking_reference }}</span>
        </div>

        <h1>QUOTATION</h1>

        <div class="meta">
            <div>
                <div class="box-title">Prepared for</div>
                <div class="client-name">{{ $ticket->contact_name }}</div>
                @foreach ([$ticket->contact_email, $ticket->contact_phone] as $line)
                    @if ($line) <div>{{ $line }}</div> @endif
                @endforeach
            </div>
            <table class="facts">
                <tr><td class="k">Quotation No.</td><td class="v">{{ $ticket->booking_reference }}</td></tr>
                <tr><td class="k">Date</td><td class="v">{{ $ticket->created_at->format('F j, Y') }}</td></tr>
                <tr><td class="k">Valid until</td><td class="v">{{ $ticket->quote_valid_until?->format('F j, Y') ?? '—' }}</td></tr>
                <tr><td class="k">Prepared by</td><td class="v">{{ $ticket->createdBy?->name ?? 'Amega Travel and Tours' }}</td></tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Trip</div>
            <table class="trip">
                <tr><td class="k">Route</td><td><strong>{{ $ticket->origin }} → {{ $ticket->destination }}</strong></td></tr>
                <tr><td class="k">Trip</td><td>{{ $label($ticket->trip_type) }} · {{ $label($ticket->travel_type) }}{{ $ticket->travel_class ? ' · '.$label($ticket->travel_class) : '' }}</td></tr>
                <tr><td class="k">Dates</td><td>Depart {{ $ticket->departure_date?->format('D, M j, Y') }}{{ $ticket->return_date ? ' · Return '.$ticket->return_date->format('D, M j, Y') : '' }}</td></tr>
                @if ($legs->isNotEmpty())
                    <tr><td class="k">Legs</td><td>@foreach ($legs as $leg){{ $leg['from'] ?? '' }} → {{ $leg['to'] ?? '' }}{{ ! empty($leg['date']) ? ' ('.\Carbon\Carbon::parse($leg['date'])->format('M j').')' : '' }}@if (! $loop->last)<br>@endif @endforeach</td></tr>
                @endif
                @if ($ticket->airline || $ticket->flight_number)
                    <tr><td class="k">Flight</td><td>{{ collect([$ticket->airline?->name, $ticket->flight_number, $ticket->departure_time ? $time($ticket->departure_time).($ticket->arrival_time ? ' – '.$time($ticket->arrival_time) : '') : null])->filter()->implode(' · ') }}</td></tr>
                @endif
                <tr><td class="k">Travellers</td><td>{{ $party ?: $ticket->total_passengers.' passenger(s)' }}</td></tr>
                @if ($ticket->package_name)
                    <tr><td class="k">Package</td><td>{{ $ticket->package_name }}</td></tr>
                @endif
            </table>
        </div>

        <div class="section">
            <div class="section-title">Price</div>
            <table class="items">
                <thead><tr><th>Description</th><th class="num" style="width: 14mm;">Qty</th><th class="num" style="width: 30mm;">Unit price</th><th class="num" style="width: 32mm;">Amount</th></tr></thead>
                <tbody>
                    @forelse ($fareLines as [$name, $qty, $unit, $amount])
                        <tr><td>{{ $name }}</td><td class="num">{{ $qty }}</td><td class="num">{{ $peso($unit) }}</td><td class="num">{{ $peso($amount) }}</td></tr>
                    @empty
                        <tr><td colspan="4">To be priced.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <table class="totals">
                @if ($discount > 0)
                    <tr><td>{{ $ticket->pwdScDiscountLabel() }}</td><td class="num">{{ $peso(-$discount) }}</td></tr>
                @endif
                <tr class="grand"><td>Total</td><td class="num">{{ $peso((float) $ticket->total_amount) }}</td></tr>
            </table>
        </div>

        @if ($extras->isNotEmpty())
            <div class="section">
                <div class="section-title">Services and requests</div>
                <table class="trip">
                    @foreach ($extras as $name => $price)
                        <tr><td>{{ $name }}</td><td class="num">{{ $price ?? 'Included' }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif

        <div class="validity">
            <strong>This quotation is valid until {{ $ticket->quote_valid_until?->format('F j, Y') ?? 'confirmed by your agent' }}.</strong>
            Airfares are subject to change and seat availability, and are not guaranteed until full payment is received and the ticket is issued.
            Book &amp; Buy (instant purchase) fares cannot be held: they must be paid in full and ticketed at the time of booking.
        </div>

        <div class="box-title">Terms</div>
        <ol class="terms">
            <li>This is a quotation, not a booking or a ticket. Seats are not reserved until the booking is confirmed and paid.</li>
            <li>Prices are in Philippine Pesos and are based on the fares and rates available on the date of this quotation.</li>
            <li>Airline rules on baggage, changes, cancellations and refunds apply once the ticket is issued.</li>
            <li>To proceed, confirm with your agent before the validity date. A Booking Agreement is then prepared for your signature.</li>
        </ol>

        <div class="signatures">
            <div><div class="line">{{ $ticket->createdBy?->name }}</div><div class="caption">Prepared by, Amega Travel and Tours</div></div>
            <div><div class="line">{{ $ticket->contact_name }}</div><div class="caption">Conforme (client's signature and date)</div></div>
        </div>
    </div>

    <div class="footer">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703 &nbsp;|&nbsp; sales@amegatravelandtour.com<br>
        www.amegatravelandtour.com &nbsp;|&nbsp; Facebook: @AmegaTravel
    </div>
</div>

</body>
</html>
