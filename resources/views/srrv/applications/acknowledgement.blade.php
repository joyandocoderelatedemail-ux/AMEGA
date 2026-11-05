@php
    /**
     * Acknowledgment of Documents: what the counter received from the retiree, printed for them to check and sign.
     * Built from the documents filed on the file, so it always matches what staff actually uploaded.
     */
    $documentName = fn ($document) => (\App\Models\SrrvApplicationDocument::TYPE_LABELS[$document->document_type] ?? ucwords(str_replace('_', ' ', $document->document_type)))
        .($document->document_type === 'other' && filled($document->remarks) ? ' ('.$document->remarks.')' : '');
    // A file not yet saved passes the lines the wizard has so far; a saved one lists what is filed on it.
    $lines = $lines ?? $application->documents->map($documentName)->all();
    $saved = $application->exists;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acknowledgment of Documents - {{ $application->reference ?? 'New file' }}</title>
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
        .signatures .filled { margin-top: 0; font-weight: bold; min-height: 36px; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 3px; }
        .signatures .filled + .line { margin-top: 0; }
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
    <a class="ghost" href="{{ $saved ? route('srrv.applications.show', $application) : route('srrv.applications.create') }}">{{ $saved ? 'Back to file' : 'Back to the application' }}</a>
</div>

<div class="sheet">
    <div class="brand">
        <strong>AMEGA</strong>
        <span>Travel and Tours Services &middot; PRA-SRRV Counter</span>
    </div>

    <h1>ACKNOWLEDGMENT OF DOCUMENTS</h1>
    <div class="ref">{{ $application->reference ?? 'New file' }} &middot; {{ now()->format('F j, Y') }}</div>

    <table>
        <tr><th>Retiree</th><td>{{ $application->retiree_name }}</td></tr>
        <tr><th>Service</th><td>{{ $application->optionLabel() }}</td></tr>
    </table>

    <div class="section">Documents received</div>
    <table>
        @forelse ($lines as $line)
            <tr>
                <th>{{ $loop->iteration }}</th>
                <td>{{ $line }}</td>
            </tr>
        @empty
            <tr><td>None filed yet</td></tr>
        @endforelse
    </table>

    <p class="note">Prepared by: <strong>{{ auth()->user()?->name }}</strong></p>

    <p class="note">
        I acknowledge that the documents listed above were handed to AMEGA Travel and Tours Services for my
        application, and that the details on them are true and correct.
    </p>

    <div class="signatures">
        <div><div class="filled">{{ $application->retiree_name }}</div><div class="line">Retiree signature over printed name</div></div>
        <div><div class="filled">{{ auth()->user()->name }}</div><div class="line">Received by (AMEGA staff)</div></div>
        <div><div class="filled">{{ now()->format('M d, Y') }}</div><div class="line">Date</div></div>
    </div>
</div>

</body>
</html>
