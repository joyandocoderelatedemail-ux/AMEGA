{{-- The BI's split date: [DD] - [MMM] - [YYYY], with the DD / MMM / YYYY captions underneath. --}}
@foreach ([0, 1, 2] as $i)
    <div class="a field center" style="{{ $at($x[$i], $y, $w[$i], $h) }}"><span class="fill">{{ $date[$i] }}</span></div>
    @if ($i < 2)
        <div class="a dash" style="{{ $at(($x[$i] + $w[$i] + $x[$i + 1]) / 2, $y + $h / 2) }}">-</div>
    @endif
    @if ($captionY)
        <div class="a cap" style="{{ $at($x[$i] + 6, $captionY) }}">{{ ['DD', 'MMM', 'YYYY'][$i] }}</div>
    @endif
@endforeach
