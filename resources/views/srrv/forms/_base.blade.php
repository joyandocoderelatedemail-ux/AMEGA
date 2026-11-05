{{--
    Shared by the PRA prints. The PRA lays its forms out on A4, except the Retiree
    Request Form, which is on legal paper ($paper = 'legal'). Filled-in entries are
    typed in capitals, as the application form asks ("Entries must be typewritten").
--}}
@include('visa-assistance.tourist-visa._print-base')
@php
    [$pageWidth, $pageHeight] = ($paper ?? 'a4') === 'legal' ? ['8.5in', '14in'] : ['210mm', '297mm'];
@endphp
<style>
    @page { size: {{ $pageWidth }} {{ $pageHeight }}; margin: 0; }
    .sheet { width: {{ $pageWidth }}; height: {{ $pageHeight }}; font-family: 'Arial Narrow', Arial, Helvetica, sans-serif; font-size: 9pt; line-height: 1.2; }
    .sheet + .sheet { page-break-before: always; }
    .toolbar { width: {{ $pageWidth }}; flex-wrap: wrap; gap: 8px; }
    /* One tidy row of equal-height buttons that wrap as a whole, never inside; the note sits under them. */
    .toolbar a, .toolbar button { display: inline-flex; align-items: center; justify-content: center; height: 36px; padding: 0 16px; white-space: nowrap; line-height: 1; }
    .toolbar button { box-shadow: 0 1px 2px rgba(0, 59, 149, .25); }
    .toolbar a.ghost:hover { background: #EEF3FB; }
    .toolbar .hint { flex-basis: 100%; margin-left: 0; padding-top: 2px; }
    .f { font-family: Arial, Helvetica, sans-serif; font-weight: bold; color: #0b2a6b; text-transform: uppercase; }
    .tick { display: inline-flex; width: 9pt; height: 9pt; border: 0.75px solid #000; align-items: center; justify-content: center; font-weight: bold; font-size: 8pt; line-height: 1; color: #0b2a6b; vertical-align: -1pt; margin-right: 3pt; background: #fff; }
    table.form { border-collapse: collapse; width: 100%; table-layout: fixed; }
    table.form td { border: 0.9px solid #000; vertical-align: top; padding: 3pt 5pt; }
    .ln { display: inline-block; border-bottom: 0.75px solid #000; min-height: 11pt; vertical-align: bottom; }
    .opt { display: inline-flex; align-items: center; white-space: nowrap; }
    .italic { font-style: italic; }
    .code { font-size: 7pt; line-height: 1.25; }
    @media print { .f, .tick { color: #000; } }
</style>
