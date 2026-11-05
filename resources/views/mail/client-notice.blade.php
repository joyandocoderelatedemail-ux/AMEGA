@php
    /*
     * The message an agent sends from the Notice box: AMEGA's navy header, the file it is about (when there is one),
     * the agent's text as typed (escaped, paragraph by paragraph), who sent it, and how to reach AMEGA.
     * Email clients need tables and inline styles. Expects $subject, $paragraphs (HtmlString each, already escaped),
     * $file (null, or reference / service / status), $agent (?string), $department (the desk, for the header).
     */
    $navy = '#003B95';
    $olive = '#A9BD00';
    $logo = asset('images/mail/amega-logo-white.png');
    $label = 'font-size:10px;line-height:14px;letter-spacing:1px;text-transform:uppercase;color:#6B7280;font-weight:bold;';
    $value = 'font-size:14px;line-height:20px;color:#111827;font-weight:bold;';
    $font = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="light">
<title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background:#F1F5F9;{{ $font }}">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#F1F5F9;">
<tr>
<td align="center" style="padding:28px 12px;">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background:#FFFFFF;border:1px solid #E2E8F0;border-radius:14px;border-collapse:separate;">
    {{-- Header --}}
    <tr>
        <td style="background:{{ $navy }};padding:18px 24px;border-top-left-radius:13px;border-top-right-radius:13px;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
                <td><img src="{{ $logo }}" width="130" height="24" alt="AMEGA Travel and Tours" style="display:block;width:130px;height:24px;border:0;"></td>
                <td align="right" style="{{ $font }}font-size:11px;line-height:16px;letter-spacing:2px;text-transform:uppercase;color:#FFFFFF;font-weight:bold;">{{ $department ?? 'Immigration Services' }}</td>
            </tr></table>
        </td>
    </tr>
    <tr><td style="height:4px;line-height:4px;font-size:0;background:{{ $olive }};">&nbsp;</td></tr>

    {{-- The file this is about --}}
    @if ($file)
        <tr>
            <td style="padding:18px 24px 0;">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:10px;border-collapse:separate;">
                    <tr>
                        <td valign="top" style="padding:12px 16px;{{ $font }}"><div style="{{ $label }}">Service</div><div style="{{ $value }}">{{ $file['service'] }}</div></td>
                        <td valign="top" style="padding:12px 16px;{{ $font }}"><div style="{{ $label }}">Reference</div><div style="{{ $value }}font-family:Menlo,Consolas,monospace;">{{ $file['reference'] }}</div></td>
                        <td valign="top" style="padding:12px 16px;{{ $font }}"><div style="{{ $label }}">Status</div><div style="{{ $value }}color:{{ $navy }};">{{ $file['status'] }}</div></td>
                    </tr>
                </table>
            </td>
        </tr>
    @endif

    {{-- The message, as the agent wrote it --}}
    <tr>
        <td style="padding:22px 24px 6px;{{ $font }}">
            @foreach ($paragraphs as $paragraph)
                <p style="margin:0 0 14px;font-size:15px;line-height:24px;color:#334155;">{{ $paragraph }}</p>
            @endforeach
        </td>
    </tr>

    @if ($agent)
        <tr>
            <td style="padding:0 24px 22px;{{ $font }}">
                <table cellpadding="0" cellspacing="0" role="presentation" style="border-top:1px solid #E2E8F0;width:100%;"><tr>
                    <td style="padding-top:14px;font-size:12px;line-height:18px;color:#64748B;">Sent by <strong style="color:#0F172A;">{{ $agent }}</strong>, AMEGA Travel and Tours {{ $department ?? 'Immigration Services' }}</td>
                </tr></table>
            </td>
        </tr>
    @endif

    {{-- How to reach AMEGA --}}
    <tr>
        <td style="background:#F8FAFC;border-top:1px solid #E2E8F0;padding:16px 24px;border-bottom-left-radius:13px;border-bottom-right-radius:13px;{{ $font }}font-size:12px;line-height:18px;color:#64748B;">
            Questions about your file? Reply to this email, or message us on
            <a href="https://www.facebook.com/amegatravel" style="color:{{ $navy }};font-weight:bold;text-decoration:none;">Facebook</a>.
            <br>
            <a href="https://www.amegatravelandtour.com" style="color:{{ $navy }};font-weight:bold;text-decoration:none;">www.amegatravelandtour.com</a>
        </td>
    </tr>
</table>
<p style="margin:14px 0 0;{{ $font }}font-size:11px;line-height:16px;color:#94A3B8;">&copy; {{ date('Y') }} AMEGA Travel and Tours Services. All rights reserved.</p>
</td>
</tr>
</table>
</body>
</html>
