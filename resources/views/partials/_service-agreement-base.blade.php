{{--
    Shared by the Immigration and SRRV service agreements (SRRV_IMMIGRATION_SERVICES_AGREEMENT.docx):
    long bond paper (8.5 x 13 in, the size of the agreement's letterhead), the letterhead repeated on
    every printed page, the screen-only toolbar, and the underlined fields and tick boxes. Every
    underlined field can be typed in, and every box ticked, before printing.
    $title: the browser title. $links: [label, url] pairs for the toolbar.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<style>
    @page { size: 8.5in 13in; margin: 0; }
    * { box-sizing: border-box; }
    html, body { margin: 0; }
    body { background: #E9EDF3; font-family: Arial, Helvetica, sans-serif; color: #000; font-size: 10.5pt; line-height: 1.4; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .paper { position: relative; width: 8.5in; min-height: 13in; margin: 18px auto; background: #fff; box-shadow: 0 2px 12px rgba(0, 0, 0, .12); }
    /* On screen only the letterhead's logo shows; printed, each page gets the whole letterhead behind the text. */
    .screen-logo { position: absolute; top: 0.32in; left: 0.42in; height: 0.5in; }
    .letterhead { display: none; }
    table.layout { width: 100%; border-collapse: collapse; }
    table.layout > thead td { height: 1.15in; padding: 0; }
    table.layout > tfoot td { height: 1.55in; padding: 0; }
    table.layout > tbody > tr > td { padding: 0 0.85in; vertical-align: top; }
    h1 { text-align: center; font-size: 15pt; margin: 0 0 2px; letter-spacing: .3px; }
    .subtitle { text-align: center; font-weight: bold; margin: 0 0 10px; }
    h2 { font-size: 10.5pt; margin: 12px 0 5px; text-transform: uppercase; break-after: avoid; }
    p { margin: 0 0 6px; text-align: justify; }
    /* A line with fields to fill reads left to right, not stretched across. */
    p:has(.fill) { text-align: left; }
    .fill { display: inline-block; min-width: 1.6in; border-bottom: 1px solid #000; padding: 0 3px; font-weight: bold; text-align: left; outline: none; vertical-align: bottom; min-height: 1.3em; }
    .fill.wide { min-width: 4.2in; }
    .fill.block { display: block; width: 100%; }
    .fill:focus, .cell:focus { background: #FFF7D6; }
    table.info { width: 100%; border-collapse: collapse; margin: 2px 0 6px; }
    table.info td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; width: 50%; }
    table.info .label { display: block; font-size: 8.5pt; color: #333; }
    table.info .cell { display: block; font-weight: bold; min-height: 1.3em; outline: none; }
    table.grid { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
    table.grid th, table.grid td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; }
    table.grid th { background: #DCE6F2; text-align: left; }
    table.grid td.num { text-align: right; white-space: nowrap; width: 1.8in; }
    table.grid tr { break-inside: avoid; }
    .checks { display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 4px 0 8px; }
    .checks.stack { display: block; }
    .checks.stack > span { display: block; margin-bottom: 3px; }
    .box { display: inline-flex; align-items: center; justify-content: center; width: 12px; height: 12px; border: 1px solid #000; margin-right: 5px; font-size: 10px; font-weight: bold; line-height: 1; vertical-align: -1px; cursor: pointer; user-select: none; background: #fff; }
    .disclaimer { font-size: 9.5pt; margin-top: 10px; }
    .keep { break-inside: avoid; }
    .signatures { display: flex; justify-content: space-between; gap: 0.8in; margin-top: 0.45in; }
    .signatures > div { flex: 1; text-align: center; }
    .signatures .name { font-weight: bold; border-bottom: 1px solid #000; min-height: 1.4em; outline: none; }
    .toolbar { width: 8.5in; margin: 18px auto 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font: 12px Arial, sans-serif; color: #475569; }
    .toolbar a, .toolbar button { font: bold 12px Arial, sans-serif; padding: 9px 16px; border-radius: 8px; border: 0; background: #003B95; color: #fff; cursor: pointer; text-decoration: none; }
    .toolbar a.ghost { background: #fff; color: #003B95; border: 1px solid #003B95; }
    @media print {
        body { background: #fff; }
        .toolbar { display: none; }
        .paper { margin: 0; box-shadow: none; background: none; min-height: 0; }
        .screen-logo { display: none; }
        .letterhead { display: block; position: fixed; top: 0; left: 0; width: 8.5in; height: 13in; z-index: -1; }
        .fill:focus, .cell:focus { background: none; }
        .box { cursor: default; }
    }
</style>
<script>
    // A box ticks or clears with a click, for what the file does not record.
    document.addEventListener('click', (event) => {
        const box = event.target.closest('.box');
        if (box) box.textContent = box.textContent.trim() ? '' : '✓';
    });
</script>
@if (request()->boolean('autoprint'))
    <script>window.addEventListener('load', () => window.print());</script>
@endif
