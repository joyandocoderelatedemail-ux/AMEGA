{{--
    A red count badge (1, 2, ... 99+) for what is new on a menu entry. Expects $count and $keys (the
    notification keys it adds up, e.g. ['inquiries']); the bell's poll keeps it current. Hidden at zero.
--}}
@if (! empty($keys))
    <span data-notify-count="{{ implode(',', $keys) }}"
          class="{{ $class ?? '' }} {{ $count ? 'inline-flex' : 'hidden' }} items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-rose-600 text-white text-[10px] font-extrabold leading-none shadow-sm ring-2 ring-white/90"
          aria-label="{{ $count }} new">{{ $count > 99 ? '99+' : $count }}</span>
@endif
