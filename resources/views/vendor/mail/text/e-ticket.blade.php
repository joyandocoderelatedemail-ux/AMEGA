@props(['ticket', 'leg', 'passenger'])
@php
    $airline = $ticket->airline?->name ?? $ticket->preferred_airline;
    $times = collect([$leg['departs'] ? 'departs '.$leg['departs'] : null, $leg['arrives'] ? 'arrives '.$leg['arrives'] : null])->filter()->implode(', ');
@endphp
E-TICKET: {{ strtoupper($passenger) }}
{{ $leg['from'] }} to {{ $leg['to'] }}, {{ $leg['date']?->format('D, M j, Y') ?? 'date to be confirmed' }}
Flight {{ $leg['flight'] ?: 'to be confirmed' }}{{ $airline ? ' ('.$airline.')' : '' }}{{ $times !== '' ? ', '.$times : '' }}
Booking code {{ $ticket->airline_pnr ?: 'to be confirmed' }}, ref {{ $ticket->booking_reference }}
