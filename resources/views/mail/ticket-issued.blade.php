<x-mail::message>
# {{ $greeting }}

@foreach ($introLines as $line)
{{ $line }}

@endforeach
@foreach ($passes as $pass)
<x-mail::e-ticket :ticket="$ticket" :leg="$pass['leg']" :passenger="$pass['passenger']" />

@endforeach
This is your e-ticket itinerary, not a boarding pass. Check in with {{ $checkInWith }} online or at the airport to get your boarding pass.

Please bring a valid ID or passport matching the names above when you travel.

{{ $salutation }}
</x-mail::message>
