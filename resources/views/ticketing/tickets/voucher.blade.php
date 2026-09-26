@php
    use App\Models\TicketBooking;

    /**
     * What the paper is depends on where the booking stands, so a quotation
     * or a pending booking can never be mistaken for an issued ticket.
     */
    [$documentTitle, $stampText, $stampClass] = match (true) {
        $ticket->isQuotation() => ['Travel Quotation', 'Quotation · Not a ticket', 'pending'],
        $ticket->isCancelled() => ['Booking Voucher', 'Cancelled', 'cancelled'],
        $ticket->isIssued() => ['Ticket Voucher', 'Ticket issued', 'issued'],
        $ticket->status === TicketBooking::STATUS_CONFIRMED => ['Booking Confirmation', 'Confirmed · Not yet issued', 'confirmed'],
        default => ['Booking Summary', 'Pending confirmation', 'pending'],
    };

    $documentDate = $ticket->issued_at ?? $ticket->created_at;

    $fareLines = array_filter([
        'Base fare' => (float) $ticket->estimated_fare,
        'Taxes and surcharges' => (float) $ticket->taxes_amount,
        'Visa assistance' => (float) $ticket->visa_assistance_fee,
        'Travel insurance' => (float) $ticket->insurance_fee,
        'Other charges' => (float) $ticket->other_charges,
    ], fn (float $amount): bool => $amount > 0);

    $total = (float) $ticket->total_amount;
    $paid = (float) $ticket->amount_paid;
    $balance = $ticket->balanceDue();

    $segments = collect($ticket->multi_city_segments ?? [])
        ->filter(fn ($segment): bool => is_array($segment) && (filled($segment['from'] ?? null) || filled($segment['to'] ?? null)))
        ->values();

    $partyBreakdown = collect([
        'adult' => (int) $ticket->adults_count,
        'child' => (int) $ticket->children_count,
        'infant' => (int) $ticket->infants_count,
    ])->filter()->map(fn (int $count, string $type): string => $count.' '.Str::plural($type, $count))->implode(', ');

    $specs = $ticket->custom_package_specs ?? [];
    $peso = fn (float $amount): string => '₱'.number_format($amount, 2);
    $label = fn (?string $value): string => ucwords(str_replace('_', ' ', (string) $value));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }} - {{ $ticket->booking_reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 12mm 14mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: Arial, Helvetica, sans-serif;
            color: #111827;
            font-size: 9.5pt;
            line-height: 1.4;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 14mm 14mm 12mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
        }

        /* ---- masthead ---- */
        .masthead {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 12px;
            border-bottom: 2.5px solid #003B95;
        }
        .masthead img { height: 42px; display: block; }
        .masthead .doc { text-align: right; }
        .masthead h1 {
            margin: 0;
            font-size: 16pt;
            color: #003B95;
            letter-spacing: -0.2px;
        }
        .masthead .ref {
            font-family: "Courier New", Courier, monospace;
            font-size: 12pt;
            font-weight: bold;
            margin-top: 2px;
            white-space: nowrap;
        }
        .masthead .date { font-size: 8.5pt; color: #4B5563; }

        .stamp-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 12px 0 4px;
        }
        .stamp {
            display: inline-block;
            border: 2px solid;
            border-radius: 3px;
            padding: 3px 10px;
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }
        .stamp.issued { color: #047857; border-color: #047857; }
        .stamp.confirmed { color: #003B95; border-color: #003B95; }
        .stamp.pending { color: #B45309; border-color: #B45309; }
        .stamp.cancelled { color: #BE123C; border-color: #BE123C; }
        .payment-note { font-size: 8.5pt; color: #4B5563; }

        /* ---- sections ---- */
        section { margin-top: 14px; break-inside: avoid; page-break-inside: avoid; }
        h2 {
            margin: 0 0 6px;
            font-size: 8.5pt;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #003B95;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 5px 7px; }
        thead th {
            background: #F1F4F9;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: .5px;
            text-transform: uppercase;
            color: #374151;
            border-top: 1px solid #CBD2DD;
            border-bottom: 1px solid #CBD2DD;
        }
        tbody td { border-bottom: 1px solid #E5E7EB; }
        tbody tr { break-inside: avoid; page-break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .mono { font-family: "Courier New", Courier, monospace; font-weight: bold; }
        .muted { color: #6B7280; }
        .small { font-size: 8pt; }
        .strong { font-weight: bold; }

        /* Itinerary headline: origin → destination at a glance. */
        .route {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border: 1px solid #CBD2DD;
            border-radius: 4px;
        }
        .route .place { flex: 1; min-width: 0; }
        .route .place.to { text-align: right; }
        .route .city { font-size: 12pt; font-weight: bold; }
        .route .arrow { font-size: 14pt; color: #003B95; }

        .facts { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px 12px; margin-top: 8px; }
        .facts.one-row { grid-template-columns: none; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); }
        .facts dt { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .5px; color: #6B7280; }
        .facts dd { margin: 1px 0 0; font-weight: bold; }

        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

        .fare { width: 62%; margin-left: auto; }
        .fare td { padding: 3px 7px; }
        .fare tr.total td {
            border-top: 1.5px solid #111827;
            border-bottom: none;
            font-size: 11pt;
            font-weight: bold;
            padding-top: 7px;
        }
        .fare tr.balance td { font-weight: bold; border-bottom: none; }

        .box { border: 1px solid #E5E7EB; border-radius: 4px; padding: 8px 10px; }

        /* ---- sign-off ---- */
        .signoff {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-top: 22px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .signoff .line { border-top: 1px solid #111827; padding-top: 4px; margin-top: 24px; }
        .footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid #E5E7EB;
            font-size: 7.5pt;
            color: #6B7280;
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        /* ---- screen-only toolbar ---- */
        .toolbar {
            width: 210mm;
            margin: 18px auto 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .toolbar a, .toolbar button {
            font-family: inherit;
            font-size: 12px;
            font-weight: bold;
            padding: 8px 14px;
            border-radius: 6px;
            border: 1px solid #003B95;
            background: #003B95;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar a.ghost { background: #fff; color: #003B95; }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
            .toolbar { display: none; }
        }

        @media screen and (max-width: 900px) {
            .sheet, .toolbar { width: auto; max-width: 100%; }
            .sheet { padding: 8mm; }
            .facts, .facts.one-row { grid-template-columns: repeat(2, 1fr); grid-auto-flow: row; }
            .two-col, .signoff { grid-template-columns: 1fr; }
            .fare { width: 100%; }
        }
    </style>
</head>
<body>

@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif

<div class="toolbar">
    <button type="button" onclick="window.print()">Print voucher</button>
    <a class="ghost" href="{{ route('ticketing.tickets.show', $ticket) }}">Back to ticket</a>
</div>

<main class="sheet">

    <header class="masthead">
        <div>
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="Amega Travel and Tours Services">
        </div>
        <div class="doc">
            <h1>{{ $documentTitle }}</h1>
            <div class="ref">{{ $ticket->booking_reference }}</div>
            <div class="date">{{ $ticket->isIssued() ? 'Issued' : 'Booked' }} {{ $documentDate?->format('M j, Y') }}</div>
        </div>
    </header>

    <div class="stamp-row">
        <span class="stamp {{ $stampClass }}">{{ $stampText }}</span>
        @if ($total > 0 && ! $ticket->isCancelled())
            <span class="payment-note">
                Payment: <span class="strong">{{ $label($ticket->payment_status) }}</span>
            </span>
        @endif
    </div>

    {{-- Itinerary --}}
    <section>
        <h2>Itinerary</h2>
        <div class="route">
            <div class="place">
                <div class="small muted">From</div>
                <div class="city">{{ $ticket->origin ?: '—' }}</div>
            </div>
            <div class="arrow" aria-hidden="true">&rarr;</div>
            <div class="place to">
                <div class="small muted">To</div>
                <div class="city">{{ $ticket->destination ?: '—' }}</div>
                @if ($ticket->arrival_airport)
                    <div class="small muted">{{ $ticket->arrival_airport }}</div>
                @endif
            </div>
        </div>

        <dl class="facts one-row">
            <div>
                <dt>Departure</dt>
                <dd>{{ $ticket->departure_date?->format('D, M j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt>Return</dt>
                <dd>{{ $ticket->return_date?->format('D, M j, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt>Trip</dt>
                <dd>{{ $label($ticket->trip_type) ?: '—' }} &middot; {{ ucfirst((string) $ticket->travel_type) }}</dd>
            </div>
            <div>
                <dt>Class</dt>
                <dd>{{ $ticket->travel_class ? $label($ticket->travel_class) : '—' }}</dd>
            </div>
            @if ($ticket->preferred_airline)
                <div>
                    <dt>Airline</dt>
                    <dd>{{ $ticket->preferred_airline }}</dd>
                </div>
            @endif
            @if ($ticket->preferred_flight_time && $ticket->preferred_flight_time !== 'anytime')
                <div>
                    <dt>Preferred time</dt>
                    <dd>{{ ucfirst($ticket->preferred_flight_time) }}</dd>
                </div>
            @endif
            <div>
                <dt>Passengers</dt>
                <dd>
                    {{ $ticket->total_passengers }}
                    @if ($partyBreakdown !== '')
                        <span class="muted small" style="font-weight: normal;">({{ $partyBreakdown }})</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($segments->isNotEmpty())
            <table style="margin-top: 10px;">
                <thead>
                    <tr>
                        <th style="width: 8%;">Leg</th>
                        <th>From</th>
                        <th>To</th>
                        <th class="num">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($segments as $segment)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $segment['from'] ?? '—' }}</td>
                            <td>{{ $segment['to'] ?? '—' }}</td>
                            <td class="num">{{ filled($segment['date'] ?? null) ? \Carbon\Carbon::parse($segment['date'])->format('M j, Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Passengers --}}
    <section>
        <h2>Passengers</h2>
        @if ($ticket->passengers->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Nationality</th>
                        <th>Date of birth</th>
                        <th>Passport / ID</th>
                        <th>Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($ticket->passengers as $passenger)
                        @php
                            $remarks = array_filter([
                                $passenger->travel_tax_included ? 'Travel tax included' : null,
                                $passenger->visa_status ? ucfirst(str_replace('_', ' ', $passenger->visa_status)) : null,
                            ]);
                        @endphp
                        <tr>
                            <td>{{ $passenger->passenger_number }}</td>
                            <td>
                                <span class="strong">{{ strtoupper($passenger->full_name) }}</span>
                                @if ($remarks)
                                    <div class="small muted">{{ implode(' · ', $remarks) }}</div>
                                @endif
                            </td>
                            <td>{{ ucfirst((string) $passenger->passenger_type) }}</td>
                            <td>{{ $passenger->nationality_type === 'filipino' ? 'Filipino' : ($passenger->passport_country ?: 'Foreign national') }}</td>
                            <td style="white-space: nowrap;">{{ $passenger->date_of_birth?->format('M j, Y') ?? '—' }}</td>
                            <td class="mono">{{ $passenger->passport_number ?: ($passenger->government_id_number ?: '—') }}</td>
                            <td style="white-space: nowrap;">{{ $passenger->passport_expiry_date?->format('M j, Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">No passengers recorded on this booking.</p>
        @endif
    </section>

    {{-- Package, insurance and add-on services --}}
    @if ($ticket->package_name || $ticket->has_insurance || ! empty($ticket->selected_services) || ! empty($specs))
        <section>
            <h2>Package and services</h2>
            <div class="box">
                <dl class="facts" style="margin-top: 0;">
                    @if ($ticket->package_name || ! empty($specs))
                        <div style="grid-column: span 2;">
                            <dt>{{ $ticket->isCustomPackage() ? 'Custom package' : 'Tour package' }}</dt>
                            <dd>{{ $ticket->package_name ?: 'Custom tour package' }}</dd>
                        </div>
                    @endif
                    @if (! empty($specs['hotel_name']) || ! empty($specs['preferred_hotel']))
                        <div style="grid-column: span 2;">
                            <dt>Hotel</dt>
                            <dd>{{ $specs['hotel_name'] ?? $specs['preferred_hotel'] }}</dd>
                        </div>
                    @endif
                    @if (! empty($specs['check_in_date']))
                        <div style="grid-column: span 2;">
                            <dt>Stay</dt>
                            <dd>
                                {{ \Carbon\Carbon::parse($specs['check_in_date'])->format('M j, Y') }}
                                @if (! empty($specs['check_out_date']))
                                    &rarr; {{ \Carbon\Carbon::parse($specs['check_out_date'])->format('M j, Y') }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if (! empty($specs['bed_config']))
                        <div>
                            <dt>Room</dt>
                            <dd>{{ $specs['bed_config'] }}{{ ! empty($specs['has_breakfast']) ? ', with breakfast' : '' }}</dd>
                        </div>
                    @endif
                    @if (! empty($specs['has_transportation']))
                        <div>
                            <dt>Transport</dt>
                            <dd>{{ $specs['transportation_type'] ?? 'Arranged' }}</dd>
                        </div>
                    @endif
                    @if ($ticket->has_insurance)
                        <div>
                            <dt>Travel insurance</dt>
                            <dd>{{ ucfirst($ticket->insurance_plan ?? 'Standard') }} plan</dd>
                        </div>
                    @endif
                    @if (! empty($ticket->selected_services))
                        <div style="grid-column: 1 / -1;">
                            <dt>Add-on services</dt>
                            <dd>{{ collect($ticket->selected_services)->map(fn ($service) => $label($service))->implode(' · ') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </section>
    @endif

    {{-- Fare --}}
    <section>
        <h2>Fare summary</h2>
        @if ($total > 0)
            <table class="fare">
                <tbody>
                    @foreach ($fareLines as $fareLabel => $amount)
                        <tr>
                            <td>{{ $fareLabel }}</td>
                            <td class="num">{{ $peso($amount) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total</td>
                        <td class="num">{{ $peso($total) }}</td>
                    </tr>
                    @unless ($ticket->isQuotation() || $ticket->isCancelled())
                        <tr>
                            <td class="muted">Amount paid</td>
                            <td class="num muted">{{ $peso($paid) }}</td>
                        </tr>
                        <tr class="balance">
                            <td>Balance due</td>
                            <td class="num">{{ $peso($balance) }}</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
        @else
            <p class="muted">The fare has not been set yet and will be confirmed by the ticketing officer.</p>
        @endif
    </section>

    {{-- Special requests --}}
    @if ($ticket->special_requests || ! empty($ticket->special_requests_list) || ! empty($specs['special_requests']))
        <section>
            <h2>Special requests</h2>
            <div class="box">
                @if (! empty($ticket->special_requests_list))
                    <div class="strong">{{ collect($ticket->special_requests_list)->map(fn ($request) => $label($request))->implode(' · ') }}</div>
                @endif
                @foreach (array_filter([$ticket->special_requests, $specs['special_requests'] ?? null]) as $note)
                    <div style="white-space: pre-line; margin-top: 2px;">{{ $note }}</div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Contacts --}}
    <section class="two-col">
        <div>
            <h2>Booking contact</h2>
            <div class="strong">{{ $ticket->contact_name }}</div>
            <div class="muted">{{ collect([$ticket->contact_email, $ticket->contact_phone])->filter()->implode(' · ') }}</div>
        </div>
        @if ($ticket->emergency_contact_name)
            <div>
                <h2>Emergency contact</h2>
                <div class="strong">
                    {{ $ticket->emergency_contact_name }}
                    @if ($ticket->emergency_contact_relationship)
                        <span class="muted" style="font-weight: normal;">({{ $ticket->emergency_contact_relationship }})</span>
                    @endif
                </div>
                <div class="muted">{{ collect([$ticket->emergency_contact_phone, $ticket->emergency_contact_email])->filter()->implode(' · ') }}</div>
            </div>
        @endif
    </section>

    <div class="signoff">
        <div>
            <div class="line">
                <div class="strong">{{ $ticket->createdBy?->name ?? 'Amega ticketing desk' }}</div>
                <div class="small muted">Prepared by &middot; {{ $ticket->created_at?->format('M j, Y') }}</div>
            </div>
        </div>
        <div>
            <div class="line">
                @if ($ticket->isIssued())
                    <div class="strong">{{ $ticket->issuedBy?->name ?? 'Amega ticketing desk' }}</div>
                    <div class="small muted">Issued by &middot; {{ $ticket->issued_at?->format('M j, Y g:i A') }}</div>
                @else
                    <div class="strong">&nbsp;</div>
                    <div class="small muted">Received by client</div>
                @endif
            </div>
        </div>
    </div>

    <footer class="footer">
        <span>Amega Travel and Tours Services &middot; {{ $ticket->booking_reference }}</span>
        <span>Printed {{ now()->format('M j, Y g:i A') }}</span>
    </footer>
</main>

</body>
</html>
