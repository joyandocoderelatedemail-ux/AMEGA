@php
    /**
     * Acknowledgment of Documents: what the counter received from the client,
     * printed for the client to check and sign. Built from the documents filed
     * on the file, so it always matches what staff actually uploaded.
     */
    $documentName = fn (string $type) => ucwords(str_replace('_', ' ', $type));
    $byApplicant = $application->documents->groupBy(fn ($document) => $document->visa_applicant_id ?? 0);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acknowledgment of Documents - {{ $application->reference }}</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #E9EDF3; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 11pt; }
        .sheet { width: 210mm; min-height: 297mm; margin: 18px auto; padding: 16mm 14mm; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); }
        .brand { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #003B95; padding-bottom: 8px; }
        .brand strong { color: #003B95; font-size: 18pt; letter-spacing: .5px; }
        .brand span { font-size: 9pt; color: #333; }
        h1 { text-align: center; font-size: 14pt; margin: 18px 0 4px; letter-spacing: 1px; }
        .ref { text-align: center; font-size: 9.5pt; color: #333; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #000; padding: 6px 8px; text-align: left; font-size: 10pt; vertical-align: top; }
        th { background: #F1F4F9; width: 32%; }
        .section { margin-top: 16px; font-weight: bold; font-size: 10.5pt; }
        .note { font-size: 9.5pt; margin-top: 14px; line-height: 1.5; }
        .signatures { display: flex; justify-content: space-between; gap: 30px; margin-top: 50px; }
        .signatures div { flex: 1; text-align: center; font-size: 9.5pt; }
        .signatures .line { border-top: 1px solid #000; padding-top: 4px; margin-top: 36px; }
        .toolbar { width: 210mm; margin: 18px auto 0; display: flex; gap: 10px; align-items: center; }
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
    <button type="button" onclick="window.print()">Print acknowledgment</button>
    <a class="ghost" href="{{ route('visa.applications.show', $application) }}">Back to file</a>
</div>

<div class="sheet">
    <div class="brand">
        <strong>AMEGA</strong>
        <span>Travel and Tours Services &middot; Visa Assistance Counter</span>
    </div>

    <h1>ACKNOWLEDGMENT OF DOCUMENTS</h1>
    <div class="ref">{{ $application->reference }} &middot; {{ now()->format('F j, Y') }}</div>

    <table>
        <tr><th>Client</th><td>{{ $application->client_name }}</td></tr>
        <tr><th>Service</th><td>{{ ucwords(str_replace('_', ' ', $application->service_type)) }}@if ($application->destination_country) &mdash; {{ $application->destination_country }}@endif</td></tr>
        <tr><th>Applicants</th><td>{{ $application->applicants->map->full_name->implode(', ') ?: '—' }}</td></tr>
    </table>

    <div class="section">Documents received</div>
    <table>
        @forelse ($application->applicants as $applicant)
            <tr>
                <th>{{ $applicant->full_name }}</th>
                <td>{{ $byApplicant->get($applicant->id, collect())->map(fn ($document) => $documentName($document->document_type))->unique()->implode(', ') ?: 'None' }}</td>
            </tr>
        @empty
            <tr><th>Applicants</th><td>None on file</td></tr>
        @endforelse
        @if ($byApplicant->has(0))
            <tr>
                <th>Whole file</th>
                <td>{{ $byApplicant->get(0)->map(fn ($document) => $documentName($document->document_type))->unique()->implode(', ') }}</td>
            </tr>
        @endif
    </table>

    <p class="note">
        I acknowledge that the documents listed above were handed to AMEGA Travel and Tours Services for my
        application, and that the details on them are true and correct.
    </p>

    <div class="signatures">
        <div><div class="line">Client signature over printed name</div></div>
        <div><div class="line">Received by (AMEGA staff)</div></div>
        <div><div class="line">Date</div></div>
    </div>
</div>

</body>
</html>
