@props(['ticket', 'leg', 'passenger'])
@php
    /*
     * One passenger on one flight, laid out like a boarding pass: the main
     * ticket on the left, a tear-off stub on the right. Email clients need
     * tables and inline styles, and the Markdown pass around this component
     * ends an HTML block at the first blank line, so none may appear below.
     */
    $place = function (string $value): array {
        return preg_match('/^(.*?)\s*\(([A-Za-z]{3})\)\s*$/', trim($value), $match)
            ? [strtoupper($match[2]), $match[1]]
            : [trim($value), ''];
    };
    [$fromCode, $fromCity] = $place($leg['from']);
    [$toCode, $toCity] = $place($leg['to']);

    $airline = $ticket->airline?->name ?? $ticket->preferred_airline ?? '';
    $class = strtoupper(str_replace('_', ' ', $ticket->travel_class ?: 'economy'));
    $date = $leg['date'] ? strtoupper($leg['date']->format('d M Y')) : 'TO BE CONFIRMED';
    $shortDate = $leg['date'] ? strtoupper($leg['date']->format('d M')) : 'TBC';
    $flight = $leg['flight'] ?: 'TBC';
    $pnr = $ticket->airline_pnr ?: '—';
    $name = strtoupper($passenger);

    $navy = '#003B95';
    $label = 'font-size:9px;line-height:12px;letter-spacing:1px;text-transform:uppercase;color:#6B7280;font-weight:bold;';
    $value = 'font-size:14px;line-height:18px;color:#111827;font-weight:bold;';
    $code = 'font-size:26px;line-height:30px;color:#111827;font-weight:bold;letter-spacing:1px;';
    $city = 'font-size:11px;line-height:14px;color:#4B5563;';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse:separate;border:1px solid #D1D5DB;border-radius:12px;margin:0 0 20px;background:#FFFFFF;">
<tr>
<td width="70%" style="background:{{ $navy }};padding:10px 16px;border-top-left-radius:11px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
<td style="font-size:12px;line-height:16px;letter-spacing:2px;color:#FFFFFF;font-weight:bold;">E-TICKET</td>
<td align="right" style="font-size:12px;line-height:16px;color:#FFFFFF;">{{ $airline }}</td>
</tr></table>
</td>
<td width="30%" style="background:{{ $navy }};padding:10px 14px;border-top-right-radius:11px;border-left:2px dashed #FFFFFF;font-size:11px;line-height:16px;letter-spacing:1px;color:#FFFFFF;font-weight:bold;">AMEGA TRAVEL</td>
</tr>
<tr>
<td valign="top" style="padding:14px 16px 12px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
<td valign="top" width="25%" style="padding-bottom:12px;"><div style="{{ $label }}">Flight</div><div style="{{ $value }}">{{ $flight }}</div></td>
<td valign="top" width="25%" style="padding-bottom:12px;"><div style="{{ $label }}">Departs</div><div style="{{ $value }}">{{ $leg['departs'] ?: '—' }}</div></td>
<td valign="top" width="25%" style="padding-bottom:12px;"><div style="{{ $label }}">Arrives</div><div style="{{ $value }}">{{ $leg['arrives'] ?: '—' }}</div></td>
<td valign="top" width="25%" style="padding-bottom:12px;"><div style="{{ $label }}">Class</div><div style="{{ $value }}">{{ $class }}</div></td>
</tr></table>
<div style="{{ $label }}">Passenger Name</div>
<div style="{{ $value }}padding-bottom:12px;">{{ $name }}</div>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
<td valign="top" width="42%"><div style="{{ $label }}">From</div><div style="{{ $code }}">{{ $fromCode }}</div><div style="{{ $city }}">{{ $fromCity }}</div></td>
<td valign="middle" width="16%" align="center" style="font-size:22px;line-height:22px;color:{{ $navy }};">&#9992;&#xFE0E;</td>
<td valign="top" width="42%"><div style="{{ $label }}">To</div><div style="{{ $code }}">{{ $toCode }}</div><div style="{{ $city }}">{{ $toCity }}</div></td>
</tr></table>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:12px;"><tr>
<td valign="top"><div style="{{ $label }}">Date</div><div style="{{ $value }}">{{ $date }}</div></td>
<td valign="top"><div style="{{ $label }}">Booking Code</div><div style="{{ $value }}font-family:Menlo,Consolas,monospace;">{{ $pnr }}</div></td>
</tr></table>
</td>
<td valign="top" style="padding:14px 14px 12px;border-left:2px dashed #D1D5DB;">
<div style="{{ $label }}">Flight</div>
<div style="{{ $value }}padding-bottom:10px;">{{ $flight }}</div>
<div style="{{ $label }}">Passenger</div>
<div style="font-size:12px;line-height:16px;color:#111827;font-weight:bold;padding-bottom:10px;">{{ $name }}</div>
<div style="{{ $label }}">Route</div>
<div style="{{ $value }}padding-bottom:10px;">{{ $fromCode }} &rarr; {{ $toCode }}</div>
<div style="{{ $label }}">Date</div>
<div style="{{ $value }}">{{ $shortDate }}</div>
</td>
</tr>
<tr>
<td style="padding:8px 16px 10px;border-top:1px solid #E5E7EB;font-size:10px;line-height:14px;letter-spacing:1px;color:#6B7280;">BOOKING REF {{ $ticket->booking_reference }}</td>
<td style="padding:8px 14px 10px;border-top:1px solid #E5E7EB;border-left:2px dashed #D1D5DB;font-size:10px;line-height:14px;letter-spacing:1px;color:#6B7280;">PNR {{ $pnr }}</td>
</tr>
</table>
