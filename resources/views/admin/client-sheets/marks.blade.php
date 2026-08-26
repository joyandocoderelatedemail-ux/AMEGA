@php
    /** Status marks for a client sheet. Pass $client, and optionally $size ('sm' or 'md'). */
    $size = $size ?? 'md';
    $pad = $size === 'sm' ? 'px-2 py-0.5 text-[9px]' : 'px-2.5 py-1 text-[10px]';
@endphp

@foreach ($client->status_marks as $mark)
    <span class="inline-flex items-center gap-1 {{ $pad }} rounded-full font-bold uppercase tracking-wider border whitespace-nowrap
        {{ $mark === 'VISA EXPIRED'
            ? 'bg-rose-100 text-rose-800 border-rose-300'
            : 'bg-amber-100 text-amber-800 border-amber-300' }}">
        <i data-lucide="{{ $mark === 'VISA EXPIRED' ? 'alert-octagon' : 'alert-triangle' }}" class="{{ $size === 'sm' ? 'w-2.5 h-2.5' : 'w-3 h-3' }}"></i>
        {{ $mark }}
    </span>
@endforeach
