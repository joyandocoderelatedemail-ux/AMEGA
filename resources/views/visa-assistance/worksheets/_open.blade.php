{{--
    The start of an AMEGA worksheet print: A4 pages in the office's style (lavender
    section bars, label with the answer under it), and the screen-only toolbar.
    Expects $docTitle, $pages (how many A4 pages), $links, $blank and $applicant.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $docTitle }}{{ $blank ? '' : ' - '.$applicant->full_name }}</title>
    @include('visa-assistance.tourist-visa._print-base')
    <style>
        @page { size: 210mm 297mm; margin: 0; }
        .sheet { width: 210mm; height: 297mm; padding: 10pt 26pt 0 24pt; font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; font-size: 9.8pt; }
        .sheet + .sheet { page-break-before: always; }
        .toolbar { width: 210mm; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; border: 1.4px solid #000; }
        td { border: 0.9px solid #000; vertical-align: top; padding: 2pt 5pt 2pt; height: 24pt; }
        td.bar { background: #a6a3ef; font-weight: bold; height: auto; padding: 1pt 5pt; border-top: 1.4px solid #000; border-bottom: 1.4px solid #000; }
        td.open { border-left: 0; border-right: 0; }
        .v { display: block; margin-top: 1pt; font-family: Arial, sans-serif; font-weight: bold; font-size: 9.4pt; color: #0b2a6b; text-transform: uppercase; white-space: pre-line; }
        .v.lower { text-transform: none; }
        .v.inline { display: inline; }
        @media print { .v { color: #000; } }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Print worksheet</button>
    @foreach ($links as [$linkLabel, $linkUrl])
        <a class="ghost" href="{{ $linkUrl }}">{{ $linkLabel }}</a>
    @endforeach
    <span class="hint">A4, 100% scale. {{ $pages === 1 ? 'One page.' : $pages.' pages.' }}</span>
</div>
