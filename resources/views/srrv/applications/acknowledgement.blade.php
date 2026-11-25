@php
    /**
     * Acknowledgment of Documents: what the counter received from the retiree, printed for them to check and sign.
     * Expects $lines (SrrvApplicationController): what is filed on the file and the requirements ticked as seen, or for a
     * file not yet saved, what the wizard has ticked and chosen so far.
     */
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
    {{-- Opened in its own tab from the wizard: back closes it, returning to the wizard with what is ticked and added on screen
         (not saved yet, and lost if the wizard were opened again). Opened any other way, it goes to the file's Documents step. --}}
    <a class="ghost" href="{{ $backUrl ?? ($saved ? route('srrv.applications.edit', [$application, 'step' => 5]) : route('srrv.applications.create')) }}"
       onclick="if (history.length <= 1) { window.close(); setTimeout(() => { location.href = this.href; }, 300); return false; }">{{ $saved ? 'Back to file' : 'Back to the application' }}</a>
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
        <tr><th>Service</th><td>{{ $service ?? $application->optionLabel() }}</td></tr>
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
        {{ $purpose ?? 'application' }}, and that the details on them are true and correct.
    </p>

    <div class="signatures">
        <div><div class="filled">{{ $application->retiree_name }}</div><div class="line">Retiree signature over printed name</div></div>
        <div><div class="filled">{{ auth()->user()->name }}</div><div class="line">Received by (AMEGA staff)</div></div>
        <div><div class="filled">{{ now()->format('M d, Y') }}</div><div class="line">Date</div></div>
    </div>
</div>

</body>
</html>
