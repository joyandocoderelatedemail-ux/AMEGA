@php
    /**
     * The counter's country checklist, laid out like the printed sheets: the applicant's
     * requirements on the left, the sponsor's (and a minor's) on the right, each row with
     * ORIG and COPY boxes. Printed for a file, received rows are ticked and there is one
     * sheet per applicant; printed blank, it is handed to the client at the inquiry.
     */
    $title = \App\Support\VisaChecklist::title($country, $variant);
    $progress = $application?->checklist_progress ?? [];
    $left = collect($variant['sections'])->filter(fn ($section) => $section['owner'] === 'applicant' && $section['key'] !== 'minor')->values();
    $right = collect($variant['sections'])->filter(fn ($section) => $section['owner'] === 'sponsor' || $section['key'] === 'minor')->sortBy(fn ($section) => $section['key'] === 'minor' ? 1 : 0)->values();

    // A box is ticked when that column was ticked on the file; an uploaded scan ticks COPY.
    $mark = function (?string $owner, string $kind, array $row, string $column) use ($application, $progress): bool {
        $key = $kind === 'sponsor' ? 'sponsor' : $owner;

        if (! $application || ! $key) {
            return false;
        }

        if (! empty($progress[$key][$row['id']][$column])) {
            return true;
        }

        // A scan attached to the row (sponsor scans have no applicant), or the passport scan filed the old way.
        $applicantId = $kind === 'sponsor' ? null : (int) substr($key, strlen('applicant:'));

        return $column === 'copy' && $application->documents->contains(fn ($document) => $document->visa_applicant_id === $applicantId
            && ($document->checklist_item === $row['id'] || ($kind !== 'sponsor' && isset($row['doc']) && $document->document_type === $row['doc'])));
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}@if ($application) - {{ $application->reference }}@endif</title>
    <style>
        @page { size: A4 landscape; margin: 8mm 9mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: Arial, Helvetica, sans-serif; color: #000; }
        .sheet { width: 297mm; min-height: 210mm; margin: 18px auto; padding: 8mm 9mm; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); page-break-after: always; }
        .sheet:last-of-type { page-break-after: auto; }
        .head { text-align: center; }
        .head img { height: 34px; }
        .head h1 { margin: 4px 0 0; font-size: 21pt; font-weight: 900; letter-spacing: 2px; text-shadow: 0 2px 3px rgba(0, 0, 0, .25); }
        .who { display: flex; justify-content: center; gap: 28px; margin-top: 4px; font-size: 9pt; }
        .cols { display: flex; gap: 6mm; margin-top: 6px; align-items: flex-start; }
        .col { flex: 1; }
        table { width: 100%; border-collapse: collapse; font-family: 'Times New Roman', Times, serif; font-size: 9.5pt; }
        th, td { border: 1px solid #000; padding: 1.5px 4px; vertical-align: top; }
        thead th { text-align: left; font-weight: normal; border-bottom: 2px solid #000; }
        th.box, td.box { width: 11mm; text-align: center; }
        td.group { background: #D9D9D9; }
        td.shade { width: 5mm; background: #E7E7E7; border-right: 1px solid #000; }
        tr.section td { font-weight: normal; border-top: 2px solid #000; }
        .tick { font-family: Arial, sans-serif; font-weight: bold; }
        .opt { font-style: italic; color: #333; }
        .gap { height: 4mm; }
        .foot { display: flex; justify-content: space-between; align-items: flex-end; gap: 10mm; margin-top: 8px; font-size: 9pt; }
        .fees { font-weight: bold; color: #1F2A5C; font-size: 10pt; max-width: 120mm; }
        .contact { text-align: left; }
        .contact strong { display: block; font-size: 12pt; }
        .contact .spaced { letter-spacing: 1.5px; }
        .office { font-size: 7.5pt; letter-spacing: 1px; margin-top: 4px; }
        .toolbar { width: 297mm; margin: 18px auto 0; display: flex; gap: 10px; }
        .toolbar a, .toolbar button { font: bold 12px Arial, sans-serif; padding: 9px 16px; border-radius: 8px; border: 0; background: #003B95; color: #fff; cursor: pointer; text-decoration: none; }
        .toolbar a.ghost { background: #fff; color: #003B95; border: 1px solid #003B95; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: 0; padding: 0; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print checklist</button>
    @if ($application)
        <a class="ghost" href="{{ route('visa.applications.show', $application) }}">Back to file</a>
    @endif
</div>

@foreach ($sheets as $sheet)
    <div class="sheet">
        <div class="head">
            <img src="{{ asset('newassets/Amega Brand/LOGO/AMEGA LOGO_UPDATED.png') }}" alt="AMEGA Travel &amp; Tours Services">
            <h1>{{ $title }}</h1>
            @if ($application)
                <div class="who">
                    <span>Applicant: <strong>{{ $sheet['name'] }}</strong></span>
                    <span>File: <strong>{{ $application->reference }}</strong></span>
                    <span>Date: <strong>{{ now()->format('F j, Y') }}</strong></span>
                </div>
            @endif
        </div>

        <div class="cols">
            @foreach ([$left, $right] as $sections)
                <div class="col">
                    @foreach ($sections as $section)
                        @php $kind = $section['owner'] === 'sponsor' ? 'sponsor' : 'applicant'; @endphp
                        @if (! $loop->first)<div class="gap"></div>@endif
                        <table>
                            <thead>
                                <tr>
                                    <th class="box">ORIG</th>
                                    <th class="box">COPY</th>
                                    <th colspan="2">REQUIREMENTS: <strong>{{ match ($section['key']) {
                                        'sponsor' => 'For Sponsor',
                                        'minor' => 'For Minor',
                                        'interview' => 'Documents to prepare before interview (optional)',
                                        default => 'For Applicant',
                                    } }}</strong></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    @if (isset($row['items']))
                                        <tr>
                                            <td class="box"></td><td class="box"></td>
                                            <td colspan="2" class="group">{{ mb_strtoupper($row['label']) }}</td>
                                        </tr>
                                        @foreach ($row['items'] as $item)
                                            <tr>
                                                @foreach (['orig', 'copy'] as $column)
                                                    <td class="box"><span class="tick">{{ $mark($sheet['owner'], $kind, $item, $column) ? '✓' : '' }}</span></td>
                                                @endforeach
                                                <td class="shade"></td>
                                                <td>{{ $item['label'] }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            @foreach (['orig', 'copy'] as $column)
                                                <td class="box"><span class="tick">{{ $mark($sheet['owner'], $kind, $row, $column) ? '✓' : '' }}</span></td>
                                            @endforeach
                                            <td colspan="2">{{ $row['label'] }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="foot">
            <div>
                @if ($variant['note'])
                    <div class="fees">{{ $variant['note'] }}</div>
                @endif
                <div class="office"><strong>VISIT OUR OFFICE AT:</strong><br>AMEGA TRAVEL AND TOURS SERVICES OFFICE<br>G/F HOTEL SALEH PLARIDEL ST., MALABANIAS ROAD, COR., ANGELES CITY, PAMPANGA</div>
            </div>
            <div class="contact">
                <strong>VISA ASSISTANCE</strong>
                <span class="spaced">CONTACT US THRU:</span><br>
                <strong style="font-size: 10pt">09176264181 / 09929225735</strong>
                <strong style="font-size: 10pt">visas@amegatravelandtour.com</strong>
            </div>
        </div>
    </div>
@endforeach

</body>
</html>
