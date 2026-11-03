{{-- The plain-text copy of client-notice, for mail apps that do not show HTML. --}}
@if ($file)
{{ $file['service'] }} · Ref. {{ $file['reference'] }} · {{ $file['status'] }}

@endif
{!! $body !!}

@if ($agent)
Sent by {!! $agent !!}, AMEGA Travel and Tours Immigration Services
@endif
www.amegatravelandtour.com · facebook.com/amegatravel
