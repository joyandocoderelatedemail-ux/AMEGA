@php
    use App\Models\ImmigrationClient;
    use App\Models\ImmigrationClientDocument;
    use App\Models\ImmigrationClientExtension;

    $isBlank = ! $client->exists;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Information Sheet{{ $isBlank ? '' : ' - '.$client->full_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #E9EDF3;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            font-size: 11pt;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 18px auto;
            padding: 14mm 12mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .18);
        }

        /* ---- masthead ---- */
        .brand { text-align: center; margin-bottom: 14px; }
        .brand img { height: 46px; }
        .brand .wordmark {
            font-size: 26pt;
            font-weight: bold;
            color: #003B95;
            letter-spacing: -0.5px;
        }
        .brand .tagline {
            font-size: 8.5pt;
            font-style: italic;
            color: #003B95;
        }

        h1 {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 10px 0 6px;
            letter-spacing: 0.3px;
        }

        /* ---- status stamps ---- */
        .stamps {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 0 0 10px;
        }
        .stamp {
            border: 2.5px solid;
            border-radius: 3px;
            padding: 4px 14px;
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 1.5px;
        }
        .stamp.expired { color: #C00000; border-color: #C00000; }
        .stamp.penalty { color: #B06000; border-color: #B06000; }

        .status-line {
            text-align: center;
            font-size: 9pt;
            margin: -4px 0 10px;
        }

        h2 {
            text-align: center;
            font-size: 11.5pt;
            font-weight: bold;
            margin: 12px 0 3px;
            text-decoration: underline;
        }

        /* ---- grids ---- */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid #000;
            padding: 5px 7px;
            font-size: 10pt;
            vertical-align: middle;
        }
        th {
            text-align: center;
            font-weight: bold;
        }
        .lbl {
            font-weight: bold;
            white-space: nowrap;
            width: 1%;
        }
        .val { min-width: 60px; }

        .tall td { height: 30px; }
        .addr td { height: 46px; }

        /* ---- footer ---- */
        .certify {
            text-align: center;
            font-size: 10pt;
            margin: 26px 0 34px;
        }

        .signatures {
            display: flex;
            justify-content: space-around;
            gap: 40px;
            margin-bottom: 30px;
        }
        .sig { text-align: center; flex: 0 1 240px; }
        .sig .rule {
            border-bottom: 1px solid #000;
            height: 28px;
            margin-bottom: 4px;
        }
        .sig .who { font-size: 10pt; }

        .contact {
            text-align: center;
            font-size: 8pt;
            line-height: 1.5;
            color: #1a1a1a;
            border-top: 1px solid #ccc;
            padding-top: 8px;
        }
        .contact strong { color: #003B95; }

        /* ---- screen-only toolbar ---- */
        .toolbar {
            width: 210mm;
            margin: 18px auto 0;
            display: flex;
            gap: 10px;
            align-items: center;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar a, .toolbar button {
            font-family: inherit;
            font-size: 12px;
            font-weight: bold;
            padding: 9px 18px;
            border-radius: 999px;
            border: 1px solid #003B95;
            background: #003B95;
            color: #fff;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar a.ghost {
            background: #fff;
            color: #003B95;
        }
        .toolbar .hint {
            font-size: 12px;
            color: #40506B;
            margin-left: auto;
        }

        @media print {
            body { background: #fff; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
            .toolbar { display: none; }
        }

        @media (max-width: 900px) {
            .sheet, .toolbar { width: auto; max-width: 100%; }
            .sheet { padding: 8mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print this sheet</button>
    @if (! $isBlank)
        <a class="ghost" href="{{ route('admin.client-sheets.edit', $client) }}">Back to record</a>
    @else
        <a class="ghost" href="{{ route('admin.client-sheets.index') }}">Back to counter</a>
    @endif
    <span class="hint">
        {{ $isBlank ? 'Blank form — hand this to a new client to fill in.' : 'Check the details, print, then have the client sign.' }}
    </span>
</div>

<div class="sheet">

    <div class="brand">
        <div class="wordmark">&#8734;AMEGA <span style="font-size:13pt;">Travel and Tours Services</span></div>
        <div class="tagline">Endless Possibilities in Travel and Tourism</div>
    </div>

    <h1>CLIENT INFORMATION SHEET</h1>

    @if (! $isBlank && $client->status_marks)
        <div class="stamps">
            @foreach ($client->status_marks as $mark)
                <span class="stamp {{ $mark === 'VISA EXPIRED' ? 'expired' : 'penalty' }}">{{ $mark }}</span>
            @endforeach
        </div>
        @if ($client->status_note)
            <p class="status-line">{{ $client->status_note }}</p>
        @endif
    @endif

    <h2>PERSONAL INFORMATION</h2>
    <table>
        <tr class="tall">
            <td class="lbl">LAST NAME:</td>
            <td class="val">{{ $client->last_name }}</td>
            <td class="lbl">HEIGHT:</td>
            <td class="val">{{ $client->height }}</td>
            <td class="lbl">WEIGHT:</td>
            <td class="val">{{ $client->weight }}</td>
        </tr>
        <tr class="tall">
            <td class="lbl">GIVEN NAME:</td>
            <td class="val">{{ $client->given_name }}</td>
            <td class="lbl">CIVIL STATUS:</td>
            <td class="val" colspan="3">{{ $client->civil_status }}</td>
        </tr>
        <tr class="addr">
            <td class="lbl">ADDRESS:</td>
            <td class="val" colspan="5">{{ $client->address }}</td>
        </tr>
        <tr class="tall">
            <td class="lbl">EMAIL:</td>
            <td class="val">{{ $client->email }}</td>
            <td class="lbl">NATIONALITY:</td>
            <td class="val" colspan="3">{{ $client->nationality }}</td>
        </tr>
        <tr class="tall">
            <td class="lbl">MOBILE #</td>
            <td class="val">{{ $client->mobile_number }}</td>
            <td class="lbl">DATE OF BIRTH:</td>
            <td class="val" colspan="3">{{ $client->date_of_birth?->format('F j, Y') }}</td>
        </tr>
    </table>

    <h2>TRAVEL INFORMATION</h2>
    <table>
        <tr>
            <th style="width:22%;"></th>
            @foreach (ImmigrationClientDocument::TYPES as $key => $label)
                <th style="width:26%;">{{ strtoupper($label) }}</th>
            @endforeach
        </tr>
        @foreach ([
            'REF. NUMBER:' => 'reference_number',
            'DATE PAID:' => 'date_paid',
            'SSRN #' => 'ssrn_number',
            'VALIDITY:' => 'validity',
        ] as $rowLabel => $attribute)
            <tr class="tall">
                <td class="lbl">{{ $rowLabel }}</td>
                @foreach (array_keys(ImmigrationClientDocument::TYPES) as $type)
                    @php $document = $isBlank ? null : $client->documentFor($type); @endphp
                    <td class="val">
                        @if ($document && $attribute === 'date_paid')
                            {{ $document->date_paid?->format('M j, Y') }}
                        @elseif ($document)
                            {{ $document->{$attribute} }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </table>

    <h2>VISA EXTENSION INFORMATION</h2>
    <table>
        <tr>
            <th style="width:9%;">SOA<br>/OR #</th>
            <th style="width:13%;">DATE</th>
            <th>DETAILS</th>
            <th style="width:13%;">AMOUNT<br>PAID</th>
            <th style="width:10%;">A.R</th>
            <th style="width:12%;">REFUND</th>
        </tr>
        @for ($sequence = 1; $sequence <= ImmigrationClient::LEDGER_ROWS; $sequence++)
            @php $row = $isBlank ? null : $client->extensionAt($sequence); @endphp
            <tr class="tall">
                <td class="lbl">{{ $sequence }}<sup>{{ ImmigrationClientExtension::ordinalSuffix($sequence) }}</sup></td>
                <td>{{ $row?->extension_date?->format('M j, Y') }}</td>
                <td>{{ $row?->details }}</td>
                <td>{{ $row?->amount_paid ? number_format((float) $row->amount_paid, 2) : '' }}</td>
                <td>{{ $row?->annual_report }}</td>
                <td>{{ $row?->refund ? number_format((float) $row->refund, 2) : '' }}</td>
            </tr>
        @endfor
    </table>

    <p class="certify">This certifies that all above mentioned information are true and correct.</p>

    <div class="signatures">
        <div class="sig">
            <div class="rule"></div>
            <div class="who">Agent</div>
        </div>
        <div class="sig">
            <div class="rule"></div>
            <div class="who">Passenger/Client</div>
        </div>
    </div>

    <div class="contact">
        Unit 1&amp;2, Astrofield Building, Balibago, Angeles City 2009 Pampanga, Philippines<br>
        +63 992 922 5733 &nbsp;|&nbsp; +63 949 9900 663 &nbsp;|&nbsp; +63 961 645 9703<br>
        sales@amegatravelandtours.com<br>
        <strong>www.amegatravelandtours.com</strong> | Facebook: <strong>@AmegaTravel</strong>
    </div>

</div>

</body>
</html>
