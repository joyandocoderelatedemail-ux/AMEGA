{{-- A client message written in Message Templates: its paragraphs as they were typed, the sign-off included. --}}
<x-mail::message>
@foreach ($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
</x-mail::message>
